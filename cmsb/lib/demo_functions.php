<?php


//
function setupDemoIfNeeded() {
  global $SETTINGS, $TABLE_PREFIX;

  // skip if not in demo mode
  if (!inDemoMode()) { return; }

  // error checking
  if (!isInstalled()) { die("You must install the software before you can use demoMode!"); }

  // reset demo if needed
  if (@$_REQUEST['resetDemo']) { unset($_SESSION['demoCreatedTimeAsFloat']); }

  // change tableprefix for active demos
  $isActiveDemo = (@$_SESSION['demoCreatedTimeAsFloat'] && $_SESSION['demoCreatedTimeAsFloat'] >= (time() - MAX_DEMO_TIME));
  if ($isActiveDemo) {
    if (preg_match("/[^\d\.]/", $_SESSION['demoCreatedTimeAsFloat'])) { die("Invalid demo value in session!"); }

    $TABLE_PREFIX  = $SETTINGS['mysql']['tablePrefix'];
    $TABLE_PREFIX .= '(demo' .$_SESSION['demoCreatedTimeAsFloat']. ')_';
    $TABLE_PREFIX  = str_replace('.', '-', $TABLE_PREFIX); // . isn't allowed in tablenames
  }

  // otherwise, create new demo
  else {
    echo t("Creating demo (please wait a moment)...") . "<br>\n";
    _removeOldDemos();
    $demoNum = _createNewDemo();
    $_SESSION['demoCreatedTimeAsFloat'] = $demoNum;
    $refreshUrl = @$_REQUEST['resetDemo'] ? '?' : thisPageUrl();
    printf(t("Done, <a href='%s'>click here to continue</a> or wait a moment while we redirect you."), $refreshUrl);
    print "<br>\n<meta http-equiv='refresh' content='1;$refreshUrl'>";
    exit;
  }
}

// redirect user to new page with "Disabled in demo mode message";
function disableInDemoMode($message = '', $interface = '') {
  if (!inDemoMode()) { return; }

  // display message
  //clearAlertsAndNotices(); // so previous alerts won't display
  if      ($message == '')         { alert(t('This feature is disabled in demo mode.')); }
  else if ($message == 'settings') { alert(t('Changing settings is disabled in demo mode.')); }
  else if ($message == 'plugins')  { alert(t('Plugins are disabled in demo mode.')); }
  else                             { die("Unknown section name '" .htmlencode($section). "'!"); }

  // display interface
  if (!$interface)                   { showInterface('home.php'); }
  else if ($interface == 'ajax')     { die(t('This feature is disabled in demo mode.')); }
  else                               { showInterface($interface); }

  //
  exit;
}

function inDemoMode() {
  return $GLOBALS['SETTINGS']['demoMode'];
}

function inCLI() {
  if (PHP_SAPI == 'cli')                     { return true; }
  if (@$_SERVER['SESSIONNAME'] == 'Console') { return true; }

  return (@$_SERVER['SCRIPT_NAME']) ? false : true; // NOTE: This is sometimes set in CLI, but always set through web
}

// create new demo (copy all CMS tables)
function _createNewDemo() {
  global $TABLE_PREFIX;

  ###
  $maxAttempts  = 12;
  $attempts     = 0;
  $schemaTables = getSchemaTables();
  $demoNum      = sprintf("%.3f", array_sum( explode(' ', microtime()) )); // eg: 1243448178.000 - allows for 999 demos to be created a second
  while (++$attempts <= $maxAttempts) {
    $demoNum    = sprintf("%.3f", $demoNum + 0.001);
    $demoPrefix = "{$TABLE_PREFIX}(demo{$demoNum})_";
    $demoPrefix = str_replace('.', '-', $demoPrefix); // . isn't allowed in tablenames

    foreach ($schemaTables as $tableName) {
      $sourceTable = "{$TABLE_PREFIX}$tableName";
      $targetTable = "{$demoPrefix}$tableName";

      if (strlen($targetTable) > 64) {
        die("Couldn't create demo table ($targetTable) as table name exceeded 64 characters. Try shortening your table prefix or table names.");
      }

      // create table
      if (!mysqli()->query("CREATE TABLE `$targetTable` LIKE `$sourceTable`")) { continue 2; } // skip to next demoNum in while loop

      // copy rows
      mysqli()->query("INSERT INTO `$targetTable` SELECT * FROM `$sourceTable`") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
    }
    break;
  }
  if ($attempts > $maxAttempts) { die("Error: Couldn't create demo after $maxAttempts attempts!  Please contact us and let us know about this error!"); }

  //
  return $demoNum;
}

// remove expired demos
function _removeOldDemos() {
  global $TABLE_PREFIX;

  $rows = mysql_select_query("SHOW TABLES LIKE '{$TABLE_PREFIX}(demo%)_%'", true);
  foreach ($rows as $row) {
    $tableName = $row[0];

    // check table date and expiry
    preg_match("/^{$TABLE_PREFIX}\(demo(\d+).*?\)_/", $tableName, $matches) or die("Error: Table '$tableName' doesn't seem to match naming scheme of demo table!");
    $tableCreatedTime = $matches[1];
    $hasExpired       = ($tableCreatedTime < time() - MAX_DEMO_TIME);

    // drop expired tables
    if ($hasExpired) {
      $query = "DROP TABLE IF EXISTS `$tableName`";
      mysqli()->query($query);
      #print "Debug: $query<br>";
    }
  }
}

//eof
<?php
// these functions were either renamed, replaced, or deprecated but are left here for compatibility with older custom code

// Remind us about old functions that are still being called
// Usage: _logUseOfOldFunction();
function _logUseOfOldFunction() {
#  $stack      = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
#  $callerName = isset($stack[1]['function']) ? $stack[1]['function'] : "?";
#  @trigger_error("DEV: Old alias function '$callerName' called, please update code for next version!", E_USER_NOTICE);
}

// prev to 2.13
function countRecords           ($tableName, $whereClause = '') { _logUseOfOldFunction(); return mysql_count($tableName, $whereClause); }
function mysql_select_count_from($tableName, $whereClause = '') { _logUseOfOldFunction(); return mysql_count($tableName, $whereClause); }
function countRecordsByUser($tableName, $userNum = null) {
  _logUseOfOldFunction(); 
  if (!$userNum) { $userNum = @$GLOBALS['CURRENT_USER']['num']; }
  return mysql_count($tableName, "`createdByUserNum` = '" .mysql_escape($userNum). "'");
}

function escapeMysqlString($string) { _logUseOfOldFunction(); return mysql_escape($string); }
function xmp_r($string)             { _logUseOfOldFunction(); return showme($string); }
function getNumberFromEndOfUrl()    { _logUseOfOldFunction(); return getLastNumberInUrl(); }

// as of 2.13
function mysql_query_fetch_row_assoc($query) { _logUseOfOldFunction(); return mysql_get_query($query); }        // v2.16 replaced mysql_fetch calls with the following...
function mysql_query_fetch_row_array($query) { _logUseOfOldFunction(); return mysql_get_query($query, true); }
function mysql_query_fetch_all_assoc($query) { _logUseOfOldFunction(); return mysql_select_query($query); }
function mysql_query_fetch_all_array($query) { _logUseOfOldFunction(); return mysql_select_query($query, true); }


// as of 2.15
function escapeJs($str) { _logUseOfOldFunction(); return jsEncode($str); }

### as of 2.16

// load data from legacy INI file format
function loadINI($filepath) {
  _logUseOfOldFunction(); 
  // load INI file data
  if (!file_exists($filepath)) { die("Error: Couldn't find ini file '$filepath'"); } // error checking
  $iniValues = @parse_ini_file($filepath, true);
  if (@$php_errormsg) { die(__FUNCTION__ . ": " .@$php_errormsg ); }
  return _decodeIniValues($iniValues);
}

// save old INI format files
function saveINI($filepath, $array, $sortKeys = 0) {
  _logUseOfOldFunction(); 
  $globals  = '';
  $sections = '';
  $invalidKeyRegexp   = '/[^a-zA-Z0-9\-\_\.]/i'; # dis-allowed chars[{}|&~![()"] (from http://ca.php.net/parse_ini_file)
  $filename = pathinfo($filepath, PATHINFO_BASENAME);

  // encode values
  $array = _encodeIniValues($array);

  ### get ini data
  if ($sortKeys) { ksort($array); }
  foreach ($array as $key => $value) {

    # save sub-sections
    if (is_array($value)) {
      $sections .= "\n[{$key}]\n";

      $childArray = $value;
      foreach ($childArray as $childKey => $childValue) {
        if (preg_match($invalidKeyRegexp, $childKey)) {
          $format  = 'Error: Invalid character(s) in key "%1$s", only the following chars are allowed in key names: a-z, A-Z, 0-9, -, _, . Filename: %2$s';
          $error   = sprintf($format, htmlencode($childKey), $filename);
          die($error);
        }

        $needsQuotes = !is_numeric($childValue) && !is_bool($childValue);
        if ($needsQuotes) { $sections .= "$childKey = \"$childValue\"\n"; }
        else              { $sections .= "$childKey = $childValue\n"; }
      }
    }

    # save global keys
    else {
      if (preg_match($invalidKeyRegexp, $key)) { die("Error: Invalid character(s) in key '".htmlencode($key)."', the following chars aren't allowed in key names: a-z, A-Z, 0-9, -, _, ." . t('Filename') . ": $filename"); }

      $needsQuotes = !is_numeric($value) && !is_bool($value);
      if ($needsQuotes) { $globals .= "$key = \"$value\"\n"; }
      else              { $globals .= "$key = $value\n"; }
    }

  }

  # create ini content
  $content = ";<?php die('This is not a program file.'); exit; ?>\n\n";  # prevent file from being executed
  $content .= $globals;
  $content .= $sections;

  # error checking
  if (file_exists($filepath) && !is_writable($filepath)) { die("Error writing to '$filepath'!<br>\nFile isn't writable, check permissions!"); }

  # save ini file
  file_put_contents($filepath, $content) || die("Error writing to '$filepath'! " .@$php_errormsg );
}

// encode key values that parse_ini_file can't handle
function _encodeIniValues($array) {
  _logUseOfOldFunction(); 
  // v2.03 - added encoding/decoding for $ since trailing $'s cause errors in PHP 5.3.0 - http://bugs.php.net/bug.php?id=48660
  static $matches      = array("\\",   "\n",  "\r", '"',  '$');
  static $replacements = array('\\\\', '\\n', '',   '\\q', '\\d');

  foreach (array_keys($array) as $key) {
    $value = &$array[$key];
    if (is_array($value)) { $value = _encodeIniValues($value); }
    else                  { $value = str_replace($matches, $replacements, $value); }
  }

  return $array;
}

// helped function for loadINI
function _decodeIniValues($array) {
  _logUseOfOldFunction(); 
  // check for encoded chars - this check reduces execute time by up to 0.5 seconds or more
  $serializedData = serialize($array);
  if (!preg_match("/\\\\\\\\|\\\\n|\\\\q|\\\\d/", $serializedData)) { return $array; }

  // replace encoded chars
  foreach (array_keys($array) as $key) {
    $value = &$array[$key];
    if (is_array($value)) { $value = _decodeIniValues($value); }
    else {
      $value   = preg_replace_callback('/(\\\\.)/', '_decodeIniValues_replacement_callback',  $value); // 2.65 updated to replace PREG_REPLACE_EVAL
    }
  }
  return $array;
}
function _decodeIniValues_replacement_callback($matches) {
  _logUseOfOldFunction(); 
  // v2.03 - added encoding/decoding for $ since trailing $'s cause errors in PHP 5.3.0 - http://bugs.php.net/bug.php?id=48660
  static $replacements = array('\\\\' => "\\", '\\n' => "\n", '\\q' => '"', '\\d' => '$');
  if (array_key_exists($matches[1], $replacements)) { return $replacements[ $matches[1] ]; }
  else                                              { return $matches[1]; }
}

function userHasSectionEditorAccess($tableName)                { _logUseOfOldFunction(); return userSectionAccess($tableName) >= 9; }
function userHasSectionAuthorViewerAccess($tableName)          { _logUseOfOldFunction(); return userSectionAccess($tableName) >= 7; }
function userHasSectionAuthorAccess($tableName)                { _logUseOfOldFunction(); return userSectionAccess($tableName) >= 6; }
function userHasSectionViewerAccess($tableName)                { _logUseOfOldFunction(); return userSectionAccess($tableName) >= 3; }
function userHasSectionViewerAccessOnly($tableName)            { _logUseOfOldFunction(); return userSectionAccess($tableName) == 3; }
function userHasSectionAuthorViewerAccessOnly($tableName)      { _logUseOfOldFunction(); return userSectionAccess($tableName) == 7; }
function mysql_getValuesAsCSV($valuesArray, $defaultValue='0') { _logUseOfOldFunction(); return mysql_escapeCSV($valuesArray, $defaultValue); }

// returns of MySQL query as all/single row and assoc/indexed array
// $rows = mysql_fetch($query);                              // return all rows as assoc arrays
// $row  = mysql_fetch($query, true);                        // return first row as associative array
// list($value1, $value2) = mysql_fetch($query, true, true); // return first row as indexed array
// $indexedRows = mysql_fetch($query, false, true);          // return all rows as indexed arrays
function &mysql_fetch($query, $firstRowOnly = false, $indexedArray = false) {
  _logUseOfOldFunction(); 
  if ($firstRowOnly) { return mysql_get_query($query, $indexedArray); }
  else               { return mysql_select_query($query, $indexedArray); }
}

// as of v2.50

function _mysql_getMysqlSetValues($colsToValues) { _logUseOfOldFunction(); return mysql_set($colsToValues); }

### as of v2.51

// save username and password as login session, doesn't check if they are valid
function user_createLoginSession($username, $password = null) {
  _logUseOfOldFunction(); 
  loginCookie_set($username, getPasswordDigest($password));
}

// save username and password as login session, doesn't check if they are valid
// NOTE: In future, do this instead: loginCookie_remove(); $GLOBALS['CURRENT_USER'] = false;
function user_eraseLoginSession() {
  _logUseOfOldFunction(); 
  loginCookie_remove();
  $GLOBALS['CURRENT_USER'] = false;
}

// load user with session login credentials, returns false if invalid username or password
// $user = user_loadWithSession();
function user_loadWithSession() { _logUseOfOldFunction(); return getCurrentUser(); }

//
function getCurrentUserAndLogin($useAdminUI = true) {
  _logUseOfOldFunction(); 
  if ($useAdminUI) { die("Please upgrade the admin.php file, it is out of date!"); } // we moved admin code out of getCurrentUser() and into admin.php
  return getCurrentUser();
}


// deprecated as of v2.51
// $emailHeaders = emailTemplate_load();
// $errors       = sendMessage($emailHeaders);
// if ($errors) { alert("Mail Error: $errors"); }
// v2.16 - logging option is now passed through if defined
function emailTemplate_load($options) {
  _logUseOfOldFunction(); 
  $templatePath = @$options['template'];
  $from         = @$options['from'];
  $to           = @$options['to'];
  $subject      = @$options['subject'];
  $placeholders = @$options['placeholders'];

  // error checking
  if (!file_exists($templatePath)) { die(__FUNCTION__.": Couldn't find email template '" .htmlencode($templatePath). "'"); }

  // get message html
  global $FROM, $TO, $SUBJECT, $PLACEHOLDERS;
  list($FROM, $TO, $SUBJECT, $PLACEHOLDERS) = array($from, $to, $subject, $placeholders);
  ob_start();
  include($templatePath);
  $HTML = ob_get_clean();

  // error checking
  if (!$FROM)    { die(__FUNCTION__ . ": No \$FROM set by program or email template '" .htmlencode($templatePath). "'"); }
  if (!@$TO)     { die(__FUNCTION__ . ": No \$TO set by program or email template '" .htmlencode($templatePath). "'"); }
  if (!$SUBJECT) { die(__FUNCTION__ . ": No \$SUBJECT set by program or email template '" .htmlencode($templatePath). "'"); }
  if (!$HTML)    { die(__FUNCTION__ . ": No content found by program or email template '" .htmlencode($templatePath). "'"); }

  //
  $emailHeaders = array(
    'from'    => $FROM,
    'to'      => $TO,
    'subject' => $SUBJECT,
    'html'    => $HTML,
  );
  if (array_key_exists('logging', $options)) { $emailHeaders['logging'] = $options['logging']; }
  return $emailHeaders;
}


// deprecated as of v2.51
function emailTemplate_showPreviewHeader() {
  _logUseOfOldFunction(); 
  if (@$_REQUEST['noheader']) { return; }
  $hideHeaderLink = thisPageUrl( array('noheader' => 1) );

  global $FROM, $TO, $SUBJECT;
?>
  <div style="border: 3px solid #000; background-color: #EEE; padding: 10px; text-align: left; margin: 25px">
  <b>Header Preview:</b> (Users won't see this - <a href="<?php echo $hideHeaderLink; ?>">hide header</a>)
<xmp>   From: <?php echo htmlencode($FROM) . "\n" ?>
     To: <?php echo htmlencode($TO) . "\n"; ?>
Subject: <?php echo htmlencode($SUBJECT); ?></xmp>
  </div>
<?php
}

### as of 2.52

// this function used to return a modified time() with a hour/min offset to represent the users timezone
// it's no longer required as the current minimum PHP version we support supports date_default_timezone_set()
// So we just return time() to avoid breaking any existing plugins or 3rd party code that use this.  If a
// timezone is set in the CMS existing code should work as expected.
function getAdjustedLocalTime() { _logUseOfOldFunction(); return time(); }

// as of 2.54, use mysql_escape($string, true); instead
function escapeMysqlWildcards($string) { _logUseOfOldFunction(); return addcslashes($string, '%_'); }
function getMysqlLimit($perPage, $pageNum) { _logUseOfOldFunction(); return mysql_limit($perPage, $pageNum); }

// as of 2.63
function getAbsolutePath($relativePath, $baseDir = '.') {
  _logUseOfOldFunction(); 
  if (!$baseDir || $baseDir == '.') { $baseDir = getcwd(); }
  $absPath = absPath($relativePath, $baseDir);
  return file_exists($absPath) ? $absPath : ''; // emulate old behaviour, absPath now normalizes paths even if they don't exist
}

function isAbsolutePath($path) { _logUseOfOldFunction(); return isAbsPath($path); }


//
function showHeader() {
  _logUseOfOldFunction(); 
  @trigger_error(__FUNCTION__ . "() is deprecated by new adminUI architecture", E_USER_NOTICE);
  include "lib/menus/header.php";

  _displayNotificationType('warning', t('This plugin is using a deprecated function (showHeader) and may not display or work correctly'));
}


function showFooter() {
  _logUseOfOldFunction(); 
  @trigger_error(__FUNCTION__ . "() is deprecated by new adminUI architecture", E_USER_NOTICE);
  global $APP, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX;
  include "lib/menus/footer.php";

  // display license and build info
  showBuildInfo();

}

// as of v3.10
function mysql_getMysqlSetValues($colsToValues) { _logUseOfOldFunction(); return mysql_set($colsToValues); }

// remove if not needed
//function relatedRecordsButton($args = null) {
//  @trigger_error("Deprecated function '" .__FUNCTION__. "' ignored, please update.", E_USER_NOTICE);
//}

// as of v3.11

function getListValues($tableName, $fieldName, $fieldValue) { _logUseOfOldFunction(); return listValues_unpack($fieldValue); }

// as of v3.13

// old function fixed bug in PHP <5.2.9 where renames would fail on windows when target file existed we now require PHP 5.6 so this function is no longer required
function rename_winsafe($oldfile, $newfile) { _logUseOfOldFunction(); return @rename($oldfile, $newfile); }

// as of 3.16

 // this function is no longer used but might be called from legacy viewer pages.
function poweredByHTML() { _logUseOfOldFunction();  return ""; }

// as of 3.52

// PHP 7.1: This can now be written with the null coalescing operator (??) as follows:
// $username = $_GET['user'] ?? 'nobody';
// same as: $username = isset($_GET['user']) ? $_GET['user'] : 'nobody';
// same as: $username = getFirstDefinedValue(@$_GET['user'], 'nobody');
// Docs: https://www.php.net/manual/en/migration70.new-features.php#migration70.new-features.null-coalesce-op
function getFirstDefinedValue() {
  _logUseOfOldFunction(); 
  foreach (func_get_args() as $arg) {
    if (isset($arg)) { return $arg; }
  }
}

// returns the first argument which evaluates to true (similar to MySQL's COALESCE() which returns the first non-null argument) or the last argument
// PHP Alternative: $val = $expr1 ?: $expr2 ?: $expr3  // Ref: http://php.net/manual/en/language.operators.comparison.php#language.operators.comparison.ternary
function coalesce() {
  _logUseOfOldFunction(); 
  $lastArg = null;
  foreach (func_get_args() as $arg) {
    if ($arg) { return $arg; }
    $lastArg = $arg;
  }
  return $lastArg;
}


// eof

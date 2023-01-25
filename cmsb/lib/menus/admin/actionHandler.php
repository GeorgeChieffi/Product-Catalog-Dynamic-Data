<?php
  global $SETTINGS, $CURRENT_USER;

  ### check access level
  if (!$CURRENT_USER['isAdmin']) {
    alert(t("You don't have permissions to access this menu."));
    showInterface('');
  }

  # error checking
  $isInvalidWebRoot = !@$SETTINGS['webRootDir'] || (!is_dir(@$SETTINGS['webRootDir']) && PHP_OS != 'WINNT'); // Windows returns false for is_dir if we don't have read access to that dir
  if (!@$SETTINGS['adminEmail'] && !@$_REQUEST['adminEmail'])         { alert("Please set 'Admin Email' under: Admin Menu > Email Settings"); }
  if (!@$SETTINGS['developerEmail'] && !@$_REQUEST['developerEmail']) { alert("Please set 'Developer Email' under: Admin Menu > Email Settings"); }
  if ($isInvalidWebRoot)                                              { alert("Please set 'Website Root Directory' under: Admin > General Settings"); }
  if (!@$SETTINGS['adminUrl'])                                        { alert("Please set 'Program Url' under: Admin > General Settings"); }


  # Dispatch actions
  $action = getRequestedAction();
  admin_dispatchAction($action);

//
function admin_dispatchAction($action) {

  if (!empty($_REQUEST['saved'])) { notice(t('Settings have been saved.')); }

  if     ($action == 'general')       { showInterface('admin/general.php'); }
  elseif ($action == 'adminSave')     { admin_saveSettings('admin/general.php'); }

  if     ($action == 'security')       { showInterface('admin/security.php'); }
  elseif ($action == 'securitySave')   { admin_saveSettings('admin/security.php'); }

  if     ($action == 'vendor' || // support legancy link name
          $action == 'branding')     { showInterface('admin/branding.php'); }
  elseif ($action == 'brandingSave') { admin_saveSettings('admin/branding.php'); }

  elseif ($action == 'bgtasks')       { showInterface('admin/backgroundTasks.php'); }
  elseif ($action == 'bgtasksSave')   { admin_saveSettings('admin/backgroundTasks.php'); }

  elseif ($action == 'email')         { showInterface('admin/emailSettings.php'); }
  elseif ($action == 'emailSave')     { admin_saveSettings('admin/emailSettings.php'); }

  elseif ($action == 'phpinfo')       { admin_phpinfo();  }
  elseif ($action == 'ulimit')        { admin_ulimit(); } 
  elseif ($action == 'du')            { admin_diskuse(); }
  elseif ($action == 'ver' || $action == 'systeminfo' || $action == 'releases')  {
    disableInDemoMode('', 'admin/general.php');

    if ($action == 'ver')        { print "<h2>$action (windows only)</h2>\n";  print "<xmp>" .`$action`. "</xmp>"; }
    if ($action == 'systeminfo') { print "<h2>$action (windows only)</h2>\n";  print "<xmp>" .`$action`. "</xmp>"; }
    if ($action == 'releases')   {
      $command = 'grep "" /etc/*-release';
      print "<h2>$action (unix only)</h2>\n";  print "<xmp>" .`$command`. "</xmp>";
    }
    exit;
  }
  
  elseif ($action == 'updateDate')           { getAjaxDate(); }
  elseif ($action == 'getUploadPathPreview') { getUploadPathPreview(@$_REQUEST['dirOrUrl'], @$_REQUEST['inputValue'], @$_REQUEST['isCustomField'], true); }
  elseif ($action == 'getMediaPathPreview')  { getMediaPathPreview(@$_REQUEST['dirOrUrl'], @$_REQUEST['inputValue'], @$_REQUEST['isCustomField'], true); }
  elseif ($action == 'plugins')              { admin_plugins(); }
  elseif ($action == 'pluginHooks')          { showInterface('admin/pluginHooks.php'); }
  elseif ($action == 'deactivatePlugin')     { admin_deactivatePlugin(); } 
  elseif ($action == 'activatePlugin')       { admin_activatePlugin(); } 

  // backup/restore
  elseif ($action == 'backuprestore')        { showInterface('admin/backupAndRestore.php'); }
  elseif ($action == 'backup')               { admin_backup(); } 
  elseif ($action == 'restore')              { admin_restore(); }
  elseif ($action == 'restoreComplete')      { admin_restoreComplete(); }
  elseif ($action == 'backupDownload')       { admin_backupDownload(); } 

  //
  elseif ($action == 'bgtasksLogsClear')     { admin_bgtasksLogsClear(); } 

  // default
  else                              { showInterface('admin/general.php');  }
}


//
function admin_saveSettings($savePagePath) {
  global $SETTINGS, $APP;

  // error checking
  clearAlertsAndNotices(); // so previous alerts won't prevent saving of admin options

  // security checks
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  //
  disableInDemoMode('settings', $savePagePath);

  # program url / adminUrl
  if (array_key_exists('adminUrl', $_REQUEST)) {
    if  (!preg_match('/^http/i', $_REQUEST['adminUrl'])) { alert("Program URL must start with http:// or https://<br>\n"); }
    if  (preg_match('/\?/i',     $_REQUEST['adminUrl'])) { alert("Program URL can not contain a ?<br>\n"); }
  }

  # webPrefixUrl - v2.53
  if (@$_REQUEST['webPrefixUrl'] != '') {
    if  (!preg_match("|^(\w+:/)?/|", $_REQUEST['webPrefixUrl'])) { alert(t("Website Prefix URL must start with /") ."<br>\n"); }
    if  (preg_match("|/$|", $_REQUEST['webPrefixUrl']))          { alert(t("Website Prefix URL cannot end with /") ."<br>\n"); }
  }

  # upload url/dir
  if (array_key_exists('uploadDir', $_REQUEST)) {
#    if      (!preg_match('/\/$/',      $_REQUEST['uploadDir'])) { alert("Upload Directory must end with a slash! (eg: /www/htdocs/uploads/)<br>\n"); }
  }
  if (array_key_exists('uploadUrl', $_REQUEST)) {
#    if      (preg_match('/^\w+:\/\//', $_REQUEST['uploadUrl'])) { alert("Upload Folder Url must be the web path only without a domain (eg: /uploads/)<br>\n"); }
#    else if (!preg_match('/^\//',      $_REQUEST['uploadUrl'])) { alert("Upload Folder Url must start with a slash! (eg: /uploads/)<br>\n"); }
#    if      (!preg_match('/\/$/',      $_REQUEST['uploadUrl'])) { alert("Upload Folder Url must end with a slash! (eg: /uploads/)<br>\n"); }
    $_REQUEST['uploadUrl'] = chop($_REQUEST['uploadUrl'], '\\\/'); // remove trailing slashes
  }

  # admin email
  if (array_key_exists('adminEmail', $_REQUEST) && !isValidEmail($_REQUEST['adminEmail'])) {
    alert("Admin Email must be a valid email (example: user@example.com)<br>\n");
  }

  # developer email
  if (array_key_exists('developerEmail', $_REQUEST) && !isValidEmail($_REQUEST['developerEmail'])) {
    alert("Developer Email must be a valid email (example: user@example.com)<br>\n");
  }

  // error checking - require HTTPS
  if (@$_REQUEST['requireHTTPS'] && !isHTTPS()) {
    alert("Require HTTPS: You must be logged in with a secure HTTPS url to set this option!<br>\n");
  }

  // error checking - require HTTPS
  if (@$_REQUEST['restrictByIP'] && !isIpAllowed(true, @$_REQUEST['restrictByIP_allowed'])) {
    alert(t("Restrict IP Access: You current IP address must be in the allowed IP list!") . "<br>\n");
  }

  // error checking - session values
  $sessionErrors = getCustomSessionErrors(@$_REQUEST['session_cookie_domain'], @$_REQUEST['session_save_path']);
  if ($sessionErrors) { alert($sessionErrors); }



  # show errors
  if (alert()) {
    showInterface($savePagePath);
    exit;
  }


  ### update global settings
  $globalSettings =& $SETTINGS;
  foreach (array_keys($globalSettings) as $key) {
    if (array_key_exists($key, $_REQUEST)) { $globalSettings[$key] = $_REQUEST[$key]; }
  }

  # update subsection settings
  $subsections = array('advanced', 'wysiwyg', 'mysql');
  foreach ($subsections as $subsection) {
    $sectionSettings =& $SETTINGS[$subsection];
    foreach (array_keys($sectionSettings) as $key) {
      if (array_key_exists($key, $_REQUEST)) { $sectionSettings[$key] = $_REQUEST[$key]; }
    }
  }

  # save to file
  saveSettings();

  # return to admin home
  notice(t('Settings have been saved.')); 
  showInterface($savePagePath);
  exit;
}


//
function getAjaxDate() {
  global $SETTINGS;

  // error checking
  if (!@$_REQUEST['timezone']) { die("no timezone value specified!"); }

  // error checking
  $timeZoneOffsetSeconds = abs(date("Z"));
  if ($timeZoneOffsetSeconds > 12*60*60) {
    $error     = "Offset cannot be more than +/- 12 hours from GMT!";
    echo json_encode(array('', '', $error));
    exit;
  }

  // set timezones
  date_default_timezone_set($_REQUEST['timezone']) || die(__FUNCTION__ + ": error setting timezone to '{$_REQUEST['timezone']}' with date_default_timezone_set.  Invalid timezone name.");
  $error = setMySqlTimezone('returnError');

  // get local date
  $offsetSeconds = date("Z");
  $offsetString  = convertSecondsToTimezoneOffset($offsetSeconds);
  $localDate = date("D, M j, Y - g:i:s A") . " ($offsetString)";

  // get mysql date
  $result = mysqli()->query("SELECT NOW(), @@session.time_zone");
  list($mySqlDate, $mySqlOffset) = $result->fetch_row();
  $mysqlDate = date("D, M j, Y - g:i:s A", strtotime($mySqlDate)) . " ($mySqlOffset)";
  if (is_resource($result)) { mysqli_free_result($result); }

  // return dates
  echo json_encode(array($localDate, $mysqlDate, $error));
  exit;
}

//
function admin_phpinfo() {
  disableInDemoMode('', 'admin/general.php');

 // EXPERIMENTAL: server_hardcoded_ini_settings
  $expectedKeyAccessLevels = [
    'arg_separator.output'     => 7,
    'date.default_longitude'   => 7,
    'date.sunrise_zenith'      => 7,
    'date.timezone'            => 7,
    'default_charset'          => 7,
    'default_socket_timeout'   => 7,
    'disable_functions'        => 4,
    'display_errors'           => 7,
    'display_startup_errors'   => 7,
    'error_log'                => 7,
    'expose_php'               => 4,
    'gd.jpeg_ignore_warning'   => 7,
    'highlight.comment'        => 7,
    'highlight.string'         => 7,
    'html_errors'              => 7,
    'log_errors'               => 7,
    'max_input_vars'           => 6,
    'memory_limit'             => 7,
    'mysql.connect_timeout'    => 7,
    'mysql.trace_mode'         => 7,
    'open_basedir'             => 7,
    'output_buffering'         => 6,
    'output_handler'           => 6,
    'post_max_size'            => 6,
    'session.cookie_domain'    => 7,
    'session.cookie_httponly'  => 7,
    'session.cookie_lifetime'  => 7,
    'session.cookie_path'      => 7,
    'session.cookie_secure'    => 7,
    'session.gc_divisor'       => 7,
    'session.gc_maxlifetime'   => 7,
    'session.gc_probability'   => 7,
    'session.name'             => 7,
    'session.save_path'        => 7,
    'session.use_cookies'      => 7,
    'session.use_only_cookies' => 7,
    'session.use_trans_sid'    => 7,
    'SMTP'                     => 7,
    'smtp_port'                => 7,
    'suhosin.session.encrypt'  => 2,
    'suhosin.simulation'       => 2,
    'track_errors'             => 7,
    'upload_max_filesize'      => 6,
    'zlib.output_compression'  => 7,
    'zlib.output_handler'      => 7,
  ];
  // debug, show values on current server
  //print "<xmp>";  foreach ($expectedKeyAccessLevels as $key => $acl) { printf ("      %-26s => %s,\n", "'$key'", @$iniKeysToValues[$key]['access'] ?: "'unknown'"); }  print "</xmp>";

  $iniKeysToValues   = ini_get_all();
  $aclNameByNum = [   // ini_get_all() constant values for 'access' key don't seem to be actual PHP constants.
    7 => 'PHP_INI_ALL',    // Entry can be set anywhere
    6 => 'PHP_INI_PERDIR', // Entry can be set in php.ini, httpd.conf, .htaccess, .user.ini
    4 => 'PHP_INI_SYSTEM', // Entry can be set in php.ini, httpd.conf
    1 => 'PHP_INI_USER',   // Entry can be set in user scripts with ini_set
  ];

  $hardcodedSettingsList = '';
  foreach ($expectedKeyAccessLevels as $key => $expectedACL) {
    if (isset($iniKeysToValues[$key]['access']) && $iniKeysToValues[$key]['access'] != $expectedACL) {

      $hardcodedSettingsList .= "<tr>\n";
      $hardcodedSettingsList .= "  <td>$key</td>\n";
      $hardcodedSettingsList .= "  <td>{$iniKeysToValues[$key]['local_value']}</td>\n";
      $hardcodedSettingsList .= "  <td>{$iniKeysToValues[$key]['global_value']}</td>\n";
      $hardcodedSettingsList .= "  <td>{$aclNameByNum[$iniKeysToValues[$key]['access']]}</td>\n";
      $hardcodedSettingsList .= "  <td>{$aclNameByNum[$expectedACL]}</td>\n";
      $hardcodedSettingsList .= "</tr>\n";
    }
  }

  // table of contents
  $sections = ['phpinfo','get_loaded_extensions','apache_get_modules','get_defined_constants','mb_get_info'];
  if ($hardcodedSettingsList) { array_unshift($sections, 'server_hardcoded_php_settings'); }

  //
  print "php executable: " .htmlencode(_getPhpExecutablePath()). "<br>\n"; 
  echo t('PHP is running as user') . ": ". htmlencode(get_current_user());
  if (function_exists('posix_getpwuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    print " (" .$processUser['name']. ")"; 
  }
  print "<br>\n";
  
  //    
  print "<h2>Sections</h2>\n";
  foreach ($sections as $section) { print "<a href='#$section'>$section</a><br>\n"; }

  // server_hardcoded_php_settings display code
  if ($hardcodedSettingsList) {
    print "<h2 id='server_hardcoded_php_settings'>server_hardcoded_php_settings (experimental)</h2>\n";
    print "Server admins can prevent php settings from being overridden with php_admin_value and php_admin_flag: <a href='http://php.net/manual/en/configuration.changes.php'>http://php.net/manual/en/configuration.changes.php</a><br>\n";
    print "PHP directive access levels should match those listed here: <a href='http://php.net/manual/en/ini.list.php'>http://php.net/manual/en/ini.list.php</a><br><br>\n";

    print "<table border='1' cellspacing='1' cellpadding='1'>\n";
    print "<tr><th>Directive</th><th>Hardedcoded as (local value)</th><th>Global Value</th><th>Access Setting</th><th>Expected Access</th></tr>\n";
    print "$hardcodedSettingsList";
    print "</table>\n";
  }

  // php info
  print "<h2 id='phpinfo'>phpinfo()</h2>\n";

  phpinfo();

  // get_loaded_extensions
  print "<h2 id='get_loaded_extensions'>get_loaded_extensions()</h2>\n";
  $sortedList = get_loaded_extensions();
  natcasesort($sortedList);
  print implode("<br>\n", $sortedList) . "\n";

  // apache_get_modules
  print "<h2 id='apache_get_modules'>apache_get_modules()</h2>\n";
  if (function_exists('apache_get_modules')) {
    $sortedList = apache_get_modules();
    natcasesort($sortedList);
    print implode("<br>\n", $sortedList) . "\n";
  }
  else { print "Not available<br>\n"; }

  // get_defined_constants
  print "<h2 id='get_defined_constants'>get_defined_constants()</h2>\n";
  print "<xmp>" . print_r(get_defined_constants(), true) . "</xmp>\n";
  
  // mb_get_info
  print "<h2 id='mb_get_info'>mb_get_info()</h2>\n";
  $mbInfo = mb_get_info();
  ksort($mbInfo);
  print "<xmp>" . print_r($mbInfo, true) . "</xmp>\n";

  //
  print "Done!";
  exit;
}

//
function admin_ulimit() {
  disableInDemoMode('', 'admin/general.php');

  print "<h2>Soft Resource Limits (ulimit -a -S)</h2>\n";
  list($maxCpuSeconds, $memoryLimitKbytes, $maxProcessLimit, $ulimitOutput) = getUlimitValues('soft');
  showme($ulimitOutput);

  print "<h2>Hard Resource Limits (ulimit -a -H)</h2>\n";
  list($maxCpuSeconds, $memoryLimitKbytes, $maxProcessLimit, $ulimitOutput) = getUlimitValues('soft');
  showme($ulimitOutput);
  exit;
}

//
function admin_diskuse() {
  disableInDemoMode('', 'admin/general.php');

  // get disk space
  $totalBytes    = @disk_total_space(__DIR__);
  $freeBytes     = @disk_free_space(__DIR__);
  $diskSpaceText = t('Disk Space') . ": ";
  if ($totalBytes) { $diskSpaceText .= sprintf(t('Free: %1$s, Total: %2$s'), formatBytes($freeBytes), formatBytes($totalBytes)); }
  else             { $diskSpaceText .= t("Unavailable"); }
  
  // get largest folders
  $command = "du -hS / | sort -hr | head -n 30"; // -hSa to show files as well, add 2>/dev/null to hide errors/warnings
  print "<h2 style='margin: 0'>Show Largest Dirs</h2>\n";
  print "$diskSpaceText<br/>\n";
  print "Shell Command: $command<br/>\n";
  print "Note: Not all directories may be visible, run the above command as root from the shell to see full file list.<br>";  
  
  exec("$command 2>&1", $outputArray, $returnCode);
  $output = implode("\n", $outputArray);
  print "<hr><xmp>$output</xmp><hr>\n"; 
  print showExecuteSeconds(). " seconds";
  exit;
}

//
function admin_plugins() {
  // allow disabling plugins
  if (file_exists("{$GLOBALS['PROGRAM_DIR']}/plugins/_disable_all_plugins.txt")) {
    alert('Development Mode: Plugins are disabled.  Remove or rename /plugins/_disable_all_plugins.txt to enable.<br>'); 
  }
  if (file_exists("{$GLOBALS['PROGRAM_DIR']}/plugins/_disable_sys_plugins.txt")) {
    alert('Development Mode: "System Plugins" flag is being ignored.  Remove or rename /plugins/_disable_sys_plugins.txt to enable.<br>'); 
  }

  getPluginList(); // preload plugin list (cached in function) to generate alerts about auto activating plugins      
  showInterface('admin/plugins.php');
}

//
function admin_deactivatePlugin() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  disableInDemoMode('plugins', 'admin/plugins.php');
  deactivatePlugin(@$_REQUEST['file']);
  redirectBrowserToURL('?menu=admin&action=plugins', true);
  exit;
}

//
function admin_activatePlugin() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  disableInDemoMode('plugins', 'admin/plugins.php');
  activatePlugin(@$_REQUEST['file']);
  redirectBrowserToURL('?menu=admin&action=plugins', true);
  exit;
}

//
function admin_backup() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
 
  disableInDemoMode('','admin/backupAndRestore.php');
  $filename = backupDatabase(null, @$_REQUEST['backupTable']);
  notice(sprintf(t('Created backup file %1$s (%2$s seconds)'), $filename, showExecuteSeconds(true)));
  showInterface('admin/backupAndRestore.php');
  exit;
}

//
function admin_restore() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();

  disableInDemoMode('','admin/backupAndRestore.php');
  
  $restoreDatabaseFilePath = $GLOBALS['BACKUP_DIR'] . @$_REQUEST['file'];
  incrementalRestore( $restoreDatabaseFilePath );
  exit;
}

//
function admin_restoreComplete() {
  disableInDemoMode('','admin/backupAndRestore.php');
  
  //
  $basename = !empty($_REQUEST['file']) ? basename($_REQUEST['file']) : ''; 
  notice("Restored backup file " .htmlencode($basename). "");
  showInterface('admin/backupAndRestore.php');
}

//
function admin_backupDownload() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();

  disableInDemoMode('','admin/backupAndRestore.php');

  // security check
  $filename = @$_REQUEST['file'];
  $filepath = $GLOBALS['BACKUP_DIR'] . $filename;
  $error    = ''; 
  if     (empty($filename))                               { $error .= "No file specified!" . "\n"; }
  elseif (!in_array($filename, getBackupFiles_asArray())) { $error .= htmlencodef("Invalid backup file '?'!", $filename) . "\n"; }
  elseif (!file_exists($filepath))                        { $error .= htmlencodef("File doesn't exists '?'!", $filename) . "\n"; }
  if ($error) {
    alert($error);
    showInterface('admin/backupAndRestore.php');
    exit;
  }

  // download file
  header('Content-Description: File Transfer');
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="' .addcslashes($filename, '"\\'). '"'); 
  header('Expires: 0');
  header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
  header('Pragma: public');
  header('Content-Length: ' . filesize($filepath));
  readfile($filepath);
  exit;
}

//
function admin_bgtasksLogsClear() {
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  disableInDemoMode('','admin/general.php');
  mysql_delete('_cron_log', null, 'true');
  notice(t("Background Task logs have been cleared."));
  showInterface('admin/backgroundTasks.php');
  exit;
}

//
function getTimeZoneOptions($selectedTimezone = '') {
  global $SETTINGS;

  // get timezone name to offset
  $tzNameToOffset = [];
  foreach (timezone_abbreviations_list() as $zoneName => $abbrZones) {
    foreach ($abbrZones as $abbrZoneArray) {
      $name   = $abbrZoneArray['timezone_id'];
      if($zoneName == 'gmt' && isset($tzNameToOffset[ $name ])) { continue; } //Do not process the GMT options as these already appear in other timezone lists
      $offset = convertSecondsToTimezoneOffset($abbrZoneArray['offset']);
      $tzNameToOffset[ $name ] = $offset;
    }
  }

  // sort from GMT-11:00 to GMT+14:00
  $tzKeyValuesArray = [];
  foreach ($tzNameToOffset as $tzName => $tzOffset) {  $tzKeyValuesArray[] = array($tzName,$tzOffset); }
  uasort($tzKeyValuesArray, '_sortTimeZones');

  $tzNameToOffset = [];
  foreach ($tzKeyValuesArray as $keyAndValue) {
    list($key, $value) = $keyAndValue;
    $tzNameToOffset[$key] = $value;
  }

  // get options
  $options = '';
  foreach ($tzNameToOffset as $tzName => $tzOffset) {
    if (!$tzName) { continue; }
    $isSelected    = $tzName == $selectedTimezone;
    $selectedAttr  = $isSelected ? 'selected="selected"' : '';
    $options      .= "<option value='$tzName' $selectedAttr>(GMT $tzOffset) $tzName</option>\n";
  }

  return $options;
}

// return timezones sorted from GMT-11:00 to GMT+14:00, and then by name
// usage: uasort($tzKeyValuesArray, '_sortTimeZones');
function _sortTimeZones($arrayA, $arrayB) {
  list($nameA, $offsetA) = $arrayA;
  list($nameB, $offsetB) = $arrayB;

  // sort by -/+ offset first
  $isNegativeA = (bool) strstr($offsetA, '-'); // eg: -08:00
  $isNegativeB = (bool) strstr($offsetB, '-');
  $cmp = strcmp($isNegativeB, $isNegativeA);
  if ($cmp != 0) { return $cmp; }

  // sort by offset value next
  $cmp = strcmp($offsetA, $offsetB);
  if ($isNegativeA) { $cmp *= -1; }        // sort negative offsets in reverse
  if ($cmp != 0) { return $cmp; }

  // sort by name last
  return strcasecmp($nameA, $nameB);
}

// list($maxCpuSeconds, $memoryLimitMegs, $maxProcessLimit, $ulimitOutput) = getUlimitValues('soft');
function getUlimitValues($type = 'soft') {
  $maxCpuSeconds     = '';
  $memoryLimitKbytes = '';
  $maxProcessLimit   = '';
  $output            = '';

  // get shell command
  if     ($type == 'soft') { $cmd = 'sh -c "ulimit -a -S" 2>&1'; }
  elseif ($type == 'hard') { $cmd = 'sh -c "ulimit -a -H" 2>&1'; }
  else                     { die(__FUNCTION__ . ": type must be either hard or soft"); }

  // get output
  if (!function_exists('shell_exec')) { return ['unknown','unknown','unknown','unknown']; }
  $output = @shell_exec($cmd);

  // parse output
  if (preg_match("/^(time|cpu time).*?\s(\S*)$/m", $output, $matches))                  { $maxCpuSeconds = $matches[2]; }
  if (preg_match("/^(data|data seg).*?\s(\S*)$/m", $output, $matches))                  { $dataSegLimit  = $matches[2]; }
  if (preg_match("/^(vmemory|virtual mem).*?\s(\S*)$/m", $output, $matches))            { $vmemoryLimit  = $matches[2]; }
  if (preg_match("/^(concurrency|max user processes).*?\s(\S*)$/m", $output, $matches)) { $maxProcessLimit  = $matches[2]; }

  if (@$vmemoryLimit > @$dataSegLimit) { $memoryLimitKbytes = @$vmemoryLimit; }
  else                                 { $memoryLimitKbytes = @$dataSegLimit; }

  //
  return array($maxCpuSeconds, $memoryLimitKbytes, $maxProcessLimit, $output);
}

// get binary path to PHP executable, eg: /usr/bin/php
function _getPhpExecutablePath() {
  static $phpFilepath, $isCached;
  if (isset($isCached)) { return $phpFilepath; } // caching
  $isCached = true;

  $phpFilepath = 'php';

  // First, try PHP_BINARY if we're CLI.  This won't work for apache2handler SAPI which are set to httpd.exe or other values
  if     (PHP_BINARY && PHP_SAPI == 'cli') {
    $phpFilepath = PHP_BINARY;
  }
  elseif (PHP_BINARY && PHP_SAPI == 'fpm-fcgi' && preg_match("/php(\.exe)?$/i", PHP_BINARY)) {  // can't run scripts with php-fpm, whitelist allowed filenames to php and php.exe
    $phpFilepath = PHP_BINARY;
  }

  // next, check above PHP extension dir for valid PHP binaries - eg: c:/wamp/bin/php/php5.5.12/ext/, check for ../bin/php.exe
  else {
    $extensionDir     = ini_get('extension_dir');
    $phpPossiblePaths = ['/php','/php.exe', '/bin/php','/bin/php.exe'];
    foreach (range(1,10) as $counter) { // limit to X recursions

      // check for valid php binaries
      foreach ($phpPossiblePaths as $possiblePath) {
        $testPath = "$extensionDir/$possiblePath";
        //print "DEBUG: Test: $testPath " .is_file($testPath). " - " .is_executable($testPath). "<br>\n";
        if (@is_file($testPath) && @is_executable($testPath)) { // Use @ to catch open_basedir errors
          $phpFilepath = absPath($testPath);
          //print "DEBUG: MATCH!!! $phpFilepath<br>\n";
          break 2; // found valid binary
        }
      }

      // continue and check parent directory - unless we're already at the root
      $parentDir = dirname($extensionDir);
      if ($parentDir == $extensionDir) { break; }  // stop once we've checked the root folder
      $extensionDir = $parentDir;
    }
  }

  return $phpFilepath;
}

// eof


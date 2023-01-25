<?php

  ### CMS Constants Reference:
  // CMS_ASSETS_DIR         - filepath url of cms assets folder, also available as $GLOBALS['CMS_ASSETS_DIR'], eg: /usr/local/apache2/htdocs/cmsb
  // CMS_ASSETS_URL         - url of cms assets folder, also available as $GLOBALS['CMS_ASSETS_URL'] and javascript as phpConstant('CMS_ASSETS_URL'), eg: http://www.example.com/cmsb
  // DATA_DIR               - The filepath of the cms data folder, eg: /usr/local/apache2/htdocs/cmsb/data
  // IS_CMS_ADMIN           - if inside of cms admin, set in admin.php
  // MAX_DEMO_TIME          - Demo mode: When creating new demos, demos older than this are removed
  // PREFIX_URL             - development prefix added to urls, from $SETTINGS['webPrefixUrl'], eg: /~username or /development/client-name
  // REQUIRED_MYSQL_VERSION - Version of MySQL required by software (program dies if version isn't supported), set in init.php
  // REQUIRED_PHP_VERSION   - Version of PHP required by software (program dies if version isn't supported), set in init.php
  // SCRIPT_DIR             - The filepath of the cms script folder, eg: /usr/local/apache2/htdocs/cmsb
  // SESSION_STARTED        - whether a session has started, set in startSessionIfRequired()
  // SETTINGS_DEV_FILENAME  - dev server settings file (auto-defined and used if it exists), eg: settings.example.com.php
  // SETTINGS_DEV_FILEPATH  - dev server settings path (auto-defined and used if it exists), eg: /usr/local/apache2/htdocs/cmsb/data/settings.example.com.php
  // SETTINGS_FILENAME      - actual settings file (either settings.dat.php or dev filename), eg: settings.dat.php
  // SETTINGS_FILEPATH      - actual settings file (either settings.dat.php or dev filepath), eg: /usr/local/apache2/htdocs/cmsb/data/settings.dat.php
  // START_SESSION          - whether to start a session, set in admin.php and plugins that require session access

  // define globals
  global $APP, $SETTINGS, $TABLE_PREFIX, $PROGRAM_DIR, $CMS_ASSETS_DIR, $CMS_ASSETS_URL, $BACKUP_DIR;
  $GLOBALS['APP'] = [
    'version'           => '3.57',
    'build'             => '2312',
    'id'                => '19',
    'key'               => '35809',
    'alerts'            => isset($GLOBALS['APP']['alerts']) ? $GLOBALS['APP']['alerts'] : '', // preserve value if already defined
  ];

  // check PHP Version
  // NOTE: When updating change code in both admin.php and /lib/init.php
  if (!defined('REQUIRED_PHP_VERSION')) { define('REQUIRED_PHP_VERSION', '7.3.0'); } // Supported PHP versions: http://php.net/supported-versions.php
  if (version_compare(phpversion(), REQUIRED_PHP_VERSION) < 0) {
    $errors  = "This program requires PHP v" .REQUIRED_PHP_VERSION. " or greater. You have PHP v" .phpversion(). " installed.<br>\n";
    $errors .= "Please ask your server administrator to upgrade PHP to a newer version.<br><br>\n";
    die($errors);
  }

  // define constants
  define('REQUIRED_MYSQL_VERSION', '5.5.8');                         // Released Dec 2010 - http://dev.mysql.com/doc/relnotes/mysql/5.5/en/ - this is checked in /lib/database)functions.php - connectToMySQL()
  define('MAX_DEMO_TIME', 60*60*1);                                  // Demo data is removed after this number of seconds
  define('SCRIPT_DIR', fixSlashes(dirname(__DIR__)));                // eg: /usr/local/apache2/htdocs/cmsb
  define('DATA_DIR', _init_getDataDir());                            // eg: /usr/local/apache2/htdocs/cmsb/data
  define('CMS_ASSETS_DIR', realpath(SCRIPT_DIR));                    // eg: /usr/local/apache2/htdocs/cmsb
  $GLOBALS['CMS_ASSETS_DIR'] = CMS_ASSETS_DIR;                       // use global for easy access to constants inside strings {$GLOBALS['LIKE_THIS']}
  $GLOBALS['BACKUP_DIR']     = DATA_DIR.'/backups/';                 // use global so plugins can override the backup directory path

  // error reporting
  error_reporting(-1);
  ini_set('display_errors', '1');         // disabled below if $SETTINGS['advanced']['phpHideErrors'] is set
  ini_set('display_startup_errors', '1'); // disabled below if $SETTINGS['advanced']['phpHideErrors'] is set
  ini_set('track_errors', '1');           // store last error in $php_errormsg
  ini_set('html_errors', '0');            // output html links in error messages
  ini_set('mysql.trace_mode', '0');       // when this is enabled SQL_CALC_FOUND_ROWS and FOUND_ROWS doesn't work: http://bugs.php.net/bug.php?id=33021
  if (function_exists('xdebug_disable')) { xdebug_disable(); }; // disable xdebug stack traces, to re-enable call: xdebug_enable()

  // PHP error logging (log to file)
  $php_error_log_file = false;
  if ($php_error_log_file) {
    $php_error_logfile = 'php_error.log.php';
    $php_error_logpath = DATA_DIR."/$php_error_logfile";
    ini_set('log_errors', '1');
    ini_set('error_log', $php_error_logpath);
    if (!file_exists($php_error_logpath) || filesize($php_error_logpath) < 10) { // if no file or file cleared (margin or error: allow 10 bytes for leftover whitespace)
      $noExecHeader = "<?php die('This is not a program file.'); /* This is a PHP data file */\n";
      file_put_contents($php_error_logpath, $noExecHeader) || die("Couldn't write data/$php_error_logfile. " . @$php_errormsg);
    }
  }
  else {
    // prevent server from creating error_log files everywhere when there is a PHP error
    ini_set('log_errors', '0');
  }

  // Load PHP compatibility functions (for older PHP versions)
  $functions = ['random_compat'];
  foreach ($functions as $_phpcompat_function) {
    if (function_exists($_phpcompat_function)) { continue; }
    require_once CMS_ASSETS_DIR . "/3rdParty/PHP_Compat/$_phpcompat_function.php";
  }

  // ensure that base64_decode is available for use (this will return an error if it's not)
  base64_decode('Y2hlY2s=');

  // load CMS libraries
  require_once SCRIPT_DIR . '/lib/common.php';
  require_once SCRIPT_DIR . '/lib/database_functions.php';
  require_once SCRIPT_DIR . '/lib/demo_functions.php';
  require_once SCRIPT_DIR . '/lib/errorlog_functions.php';
  require_once SCRIPT_DIR . '/lib/auditlog_functions.php';
  require_once SCRIPT_DIR . '/lib/field_class.php';
  require_once SCRIPT_DIR . '/lib/file_functions.php';
  require_once SCRIPT_DIR . '/lib/html_functions.php';
  require_once SCRIPT_DIR . '/lib/http_functions.php';
  require_once SCRIPT_DIR . '/lib/image_functions.php';
  require_once SCRIPT_DIR . '/lib/language_functions.php';
  require_once SCRIPT_DIR . '/lib/login_functions.php';
  require_once SCRIPT_DIR . '/lib/mail_functions.php';
  require_once SCRIPT_DIR . '/lib/media_functions.php';
  require_once SCRIPT_DIR . '/lib/mysql_functions.php';
  require_once SCRIPT_DIR . '/lib/old_alias_functions.php';
  require_once SCRIPT_DIR . '/lib/plugin_functions.php';
  require_once SCRIPT_DIR . '/lib/schema_functions.php';
  require_once SCRIPT_DIR . '/lib/security_functions.php';
  require_once SCRIPT_DIR . '/lib/upload_functions.php';
  require_once SCRIPT_DIR . '/lib/validation_functions.php';

  // init & setup
  _init_showServerRequirementsErrors();
  _init_fixPhpConfig();                  // call this _before_ anything else so we have reliable php values
  _init_loadSettings();                  // sets $SETTINGS global
  _init_fixPreviewDnsDomains();

  // use default permission on created files
  if ($GLOBALS['SETTINGS']['advanced']['permissions_umask'] != '') {
    $mask = octdec($GLOBALS['SETTINGS']['advanced']['permissions_umask']);
    umask($mask);
  }

  // security: hide PHP errors if requested
  if ($GLOBALS['SETTINGS']['advanced']['phpHideErrors']) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
  }

  // set globals
  $_REQUEST      = _init_createRequestGlobal();  // call AFTER _init_fixPhpConfig(): uses fixed PATH_INFO
  $TABLE_PREFIX  = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['tablePrefix'] ?? $SETTINGS['mysql']['tablePrefix'];
  $PROGRAM_DIR   = SCRIPT_DIR;
  define('PREFIX_URL', $SETTINGS['webPrefixUrl']);  // v2.53
  define('CMS_ASSETS_URL', parse_url(dirname($SETTINGS['adminUrl']), PHP_URL_PATH));  // for more complicated path references: parse_url( realUrl('../cms-assets', $SETTINGS['adminUrl']), PHP_URL_PATH));
  $GLOBALS['CMS_ASSETS_URL'] = CMS_ASSETS_URL;

  // set timezone
  _init_setTimezone();

  // check for internet connectivity
  if (!isInstalled()) { _init_showInternetConnectivityErrors(); }  // only run if software not installed yet

  // connect to mysql
  if (isInstalled()) { connectToMySQL(); }
  // load plugins
  if (isInstalled() && !isUpgradePending()) { // don't load plugins when upgrading because they might interfere
    loadPlugins();
  }

  // Check for accidentally added whitespace or other output (by end users) - extra linebreaks at the end of library or plugin files
  // ... can cause ajax calls to fail (whitespace is interpreted as error message and returned in 'blank' popups) or gzip output to
  // ... become corrupted. Note that server behaviour will vary based on output_buffering another other settings (search "output" in phpinfo)
  if (defined('IS_CMS_ADMIN')) { // only run this in CMS admin, we don't want errors on viewer pages
    $unexpectedOutputErrors = '';
    if (headers_sent($outputSentFile, $outputSentLine)) {  // headers_sent() gets triggered when php's 'output_buffer' bytes are exceeded
      $unexpectedOutputErrors .= sprintf('Unexpected output was sent by the following file: %1$s (on line %2$s)', htmlencode(@$outputSentFile), htmlencode(@$outputSentLine)). "\n";
      $unexpectedOutputErrors .= "Developers: Check plugins and library files for accidentally added whitespace or other characters.\n";
      die(nl2br($unexpectedOutputErrors)); // start_session will fail if headers_sent so we need to die here to show error.
    }
    elseif (ob_get_length()) { // ... otherwise we check if any 'output' is in the buffer, there shouldn't be.
      $unexpectedOutputErrors .= "Unexpected output was sent by a program library or plugin." . "\n";
      $unexpectedOutputErrors .= sprintf('Output was "%1$s" (%2$s chars)', htmlencode(ob_get_contents()), ob_get_length()) . "\n";
      $unexpectedOutputErrors .= "Developers: Check plugins and library files for accidentally added whitespace or other characters.\n";
      alert(nl2br($unexpectedOutputErrors)); // we can show this as a program alert to minimize interruption while error is being resolved.
    }
  }

  // start session
  _init_startSession();

  //
  setupDemoIfNeeded();

  //
  doAction('init_complete');

  // all done!
  return;

//
function _init_getDataDir() {
  $defaultDataDir  = fixSlashes(dirname(__DIR__) . "/data/");
  $altPathBasename = "data_folder.php";
  $altDataPathFile = __DIR__ . "/../$altPathBasename";
  $altDataPath     = '';

  // use alternative data file path if it exists
  if (file_exists($altDataPathFile)) {
    $altDataPath = require($altDataPathFile);

    // if relative path, prefix with current dir (and .. since current dir is in /lib/)
    if (preg_match("/^\./", $altDataPath)) { $altDataPath = __DIR__ . "/../$altDataPath"; }

    // error checking
    $error = '';
    if (!$altDataPath)                { die("$altPathBasename: No data path specified!"); }
    if (!file_exists($altDataPath))   { die("$altPathBasename: Couldn't find specified data folder: "   .$altDataPath); }
    if (!is_dir($altDataPath))        { die("$altPathBasename: Specified data path isn't a directory: " .$altDataPath); }
    if (!realpath($altDataPath))      { die("$altPathBasename: Couldn't get realpath for: " .$altDataPath); }
    if (file_exists($defaultDataDir)) { die("$altPathBasename: You must remove the default /data/ folder to use an alternate data path!"); }

    if ($error) {
      @trigger_error($error, E_USER_NOTICE); // log error
      die($error); // display error
    }
  }


  // get data dir
  $dataDir = $altDataPath ? $altDataPath : $defaultDataDir;
  $dataDir = fixSlashes(realpath($dataDir));

  // check for known subdirs
  if (!is_dir("$dataDir/schema/")) {
    die("Can't find /schema/ subdirectory under data folder: " . $dataDir);
  }

  // error checking
  if (!$dataDir) { die("Couldn't find /data/ folder!"); }

  //
  return $dataDir;
}

//
function _init_fixPhpConfig() {

  ini_set('open_basedir', '');        // disable open_basedir restrictions (we also set this with php.ini for earlier php versions)
  if (!ini_get('suhosin.memory_limit')) { // don't increase memory if suhosin doesn't allow it (or script will die): http://www.hardened-php.net/suhosin/configuration.html#suhosin.memory_limit
    $desired_limit = '128M';
    $current_limit = ini_get('memory_limit');
    if (parseIniSize($current_limit) < parseIniSize($desired_limit)) {
      ini_set('memory_limit', $desired_limit); // raise memory limit (if allowed)
    }
  }
  ini_set('mysql.connect_timeout', 6);  // PHP default of 60 ties up browser for too long - mysql.connect_timeout may not work on windows
  ini_set('gd.jpeg_ignore_warning', 1); // suppress "Warning: imagecreatefromjpeg(): gd-jpeg, libjpeg: recoverable error: Premature end of JPEG file" and other GD errors.  See: http://bugs.php.net/bug.php?id=39918
  ini_set('default_charset', 'utf-8');  //
  if (function_exists('mb_internal_encoding')) { // just in-case mbstring isn't loaded
    mb_internal_encoding('utf-8');        // required for mb_encode_mimeheader() to work properly
  }

  // disable zlib.output_compression so we can output content from register_shutdown_function - http://php.net/manual/en/function.register-shutdown-function.php#72604
  @ini_set('zlib.output_compression', 0);
  // experimental - while (@ob_end_flush()); // disable any and all output buffers (so they don't interfere)
  // this code prevents sending of headers after this line on some servers (headers already sent)- figure out why this triggers internal whitespace plugin detector error


  // Set PHP arg_separator.output value to & so PHP http_build_query() function always works as expected
  ini_set('arg_separator.output', '&');

  // set_include_path - so we can load libraries without full path like this: require_once("/lib/library.php");
  // ... Note that init.php is called from files in different directories so we can't use relative paths
  // ... Note: set_include_path won't work here if host set it in httpd config - see: http://bugs.php.net/bug.php?id=45288#c140023

  // There's a bug in some PHP installs where the follow error is reported intermittently if set_include_path() is called:
  // "Fatal error: Allowed memory size of 268435456 bytes exhausted (tried to allocate 268692400 bytes) in Unknown on line 0"
  // The memory amounts vary but they are usually very large.  A workaround is to:
  // - Copy the CMS dir path from: Admin > General Settings > Program Directory
  // - Update php.ini and add the path to the beginning of "include_path" followed by a path separator (either : or ;)
  // - Comment out the lines below:

  $newIncludePath  = SCRIPT_DIR                          . PATH_SEPARATOR;   // include program dir
  $newIncludePath .= CMS_ASSETS_DIR.'/3rdParty' . PATH_SEPARATOR;   // include /3rdParty/ dir for libraries that expect themselves to be off the current directory (like Zend)
  //$newIncludePath .= CMS_ASSETS_DIR.'/3rdParty/PEAR' . PATH_SEPARATOR;   // v2.xx - not yet added
  $newIncludePath .= rtrim(get_include_path(), PATH_SEPARATOR); // Remove trailing PATH_SEPARATOR (":") - This is good form anyway, but note that a PHP v5.0.4 bug caused trailing PATH_SEPARATOR (":") to check for include()d files in the root ("/") and return errors that it couldn't find it.
  set_include_path($newIncludePath);

  // Set common _SERVER values that are undefined when from from cronjobs and command-line (to prevent warnings) - v2.17
  $_SERVER['SCRIPT_NAME'] = @$_SERVER['SCRIPT_NAME'];
  $_SERVER['HTTP_HOST']   = @$_SERVER['HTTP_HOST'];

  // Fix SCRIPT_NAME and PHP_ SELF (often set incorrectly when PHP is in CGI mode)
  $isCgiMode = PHP_SAPI == 'cgi' || PHP_SAPI == 'cgi-fcgi';
  if ($isCgiMode && @$_SERVER['REQUEST_URI']) {
    list($scriptUri) = explode('?', $_SERVER['REQUEST_URI']);             // remove query string
    if (@$_SERVER["PATH_INFO"] && @$_SERVER["PATH_INFO"] != $scriptUri) { // remove PATH_INFO
      $escapedPathInfo = preg_quote($_SERVER['PATH_INFO'], '/');
      $scriptUri = preg_replace("/$escapedPathInfo$/", '', $scriptUri);
    }
    $_SERVER['SCRIPT_NAME_ORIGINAL'] = @$_SERVER['SCRIPT_NAME']; // for debugging - save original value
    $_SERVER['SCRIPT_NAME'] = $scriptUri;

  }
  $_SERVER['PHP_'.'SELF_ORIGINAL'] = @$_SERVER['PHP_'.'SELF'];  // for debugging - save original value
  $_SERVER['PHP_'.'SELF'] = @$_SERVER['SCRIPT_NAME'];

  // Fix PATH_INFO - CGI PHP sometimes sets pathinfo to PHP_ SELF when it's blank (which causes problems when reading numbers from PATH_INFO in viewer_functions.php
  if (@$_SERVER["PATH_INFO"] == @$_SERVER['PHP_'.'SELF']) {
    $_SERVER["PATH_INFO_ORIGINAL"] = @$_SERVER["PATH_INFO"];
    $_SERVER["PATH_INFO"] = '';
  }

  // fix DOCUMENT_ROOT - Not set in MS-IIS and often incorrect in Apache virtual hosting configurations
  $validDocRoot = @$_SERVER['DOCUMENT_ROOT'] && @is_dir(@$_SERVER['DOCUMENT_ROOT'].'/'); // Add trailing slash so hosts who mis-configured 'open_basedir' with a trailing slash don't get an "not in basedir" error
  if (!$validDocRoot && @$_SERVER['SCRIPT_NAME']) {                   // Note: If SCRIPT_NAME isn't set (such as in PHP CLI) we don't overwrite DOCUMENT_ROOT, since we need it to calculate the path
    $callers      = debug_backtrace();
    $caller       = array_pop($callers);
    $pathOfCaller = isset($caller['file']) ? $caller['file'] : '';     // filepath of calling script (since path to this lib file might be different)
    $fullFilepath = fixSlashes($pathOfCaller ?: __FILE__);  // eg: C:/wamp/www/application/admin.php - path of script that included us, or path of this file if no caller
    $search       = fixSlashes($_SERVER['SCRIPT_NAME']);              // eg:            /application/admin.php
    $webroot      = str_replace($search, '', $fullFilepath);          // eg: C:/wamp/www

    if (@is_dir("$webroot/")) { // add trailing slash so hosts who mis-configured 'open_basedir' with a trailing slash don't get an "not in basedir" error
      $_SERVER['DOCUMENT_ROOT_ORIGINAL'] = @$_SERVER['DOCUMENT_ROOT']; // for debugging - save original value
      $_SERVER['DOCUMENT_ROOT']          = $webroot;
    }
  }

  // define HTTP_REFERER
  if (!array_key_exists('HTTP_REFERER', $_SERVER)) {
    $_SERVER['HTTP_REFERER'] = '';
  }

}



// Some web hosts (godaddy?) use a *.previewdns.com to preview their websites before the DNS propegates.
// This site injects html/css/js into the page to display a popup on how to setup their DNS.
// This injected code redefines $ in js (to use mootools) and can interfere with other code on the page so we remove it.
function _init_fixPreviewDnsDomains($html = null) {

  // skip if not on *.previewdns.com host
  if (@$_SERVER['HTTP_X_FORWARDED_SERVER'] != 'previewdns.com') { return; }

  // call this function to filter output
  if ($html === null) { ob_start('_init_fixPreviewDnsDomains'); }

  // returned filtered output
  // *.previewdns.com injects code right before closing body tag.  Adding trailing space in body tag
  // prevents their system from matching it and injecting their code
  if ($html) { return preg_replace('|</body>|i', '</body >', $html); }
}

//
function _init_showServerRequirementsErrors() {
  $errors = '';

  // check php extensions
  // Warning for upcoming requirements can be added to: /lib/menus/admin/general.php in _getPreFormContent()
  $missingExtensions = '';
  $requiredExtensions = ['curl','gd','mbstring','mysqli','openssl'];

  foreach ($requiredExtensions as $extension) {
    if (extension_loaded($extension)) { continue; }
    $missingExtensions .= "This program requires the PHP '$extension' extension.<br>\n";
  }
  if ($missingExtensions) {
    $errors .= $missingExtensions;
    $errors .= "Please ask your hosting provider (or server administrator) to install missing PHP extension(s).<br><br>\n";
  }

  // check for GD jpeg support
  // note: check gd_info() this function for more details on specific GD features that are enabled/disabled
  if (extension_loaded("gd") && !function_exists('imagecreatefromjpeg')) {
    $errors .= "PHP doesn't support jpegs (or thumbnails). Please ask your server administrator to recompile PHP with jpeg support.<br>\n";
    $errors .= "<b>Server Administrators:</b> imagecreatefromjpeg() isn't defined.\n";
    $errors .= "Search the comments on <a href='http://www.php.net/imagecreatefromjpeg'>php.net/imagecreatefromjpeg</a> for 'undefined function' for more details.<br><br>\n";
  }

  // ensure json_encode is available - As of Jan 2019 it's not always bundles with distros anymore.  See: https://stackoverflow.com/questions/18239405/php-fatal-error-call-to-undefined-function-json-decode
  if (!function_exists('json_encode')) {
    $errors .= "PHP doesn't have the json_encode() function enabled. Please ask your server administrator to ensure JSON is enabled and the extension is loaded.<br>\n";
    // Jan 2019 - Don't uncomment this unless we see a real-world example of this.
    //$errors .= "<b>Server Administrators:</b> Some linux distros need to have the json extension manually added.\n";
    //$errors .= "Search for <a href='https://www.google.com/search?q=php+undefined+function+json_encode'>undefined php function json_encode</a> for more details.<br><br>\n";
  }

  // check php settings


  // check for CSRF Token errors - prevent private token from being exposed in GET urls (and subsequently referers and apache logs)
  // .. this is actually redundant and is done by security_dieOnInvalidCsrfToken() as well but is here for security (if security_dieOnInvalidCsrfToken is missed)
  if (array_key_exists('_CSRFToken', $_GET)) { $errors .= "Error: _CSRFToken is not allowed in url, use POST instead."; }


  // show errors
  if ($errors) { die($errors); }

}

//
function _init_createRequestGlobal() {

  $request = $_POST + $_GET;

  //
  if (@$_SERVER['PATH_INFO']) { // add form values from PATH_INFO (example app.php/name-value/city-Vancouver/)
    $pairs = explode("/", $_SERVER['PATH_INFO']);
    foreach ($pairs as $pair) {
      if (!$pair) { continue; } // skip blank
      @list($encodedName, $encodedValue) = explode('-', $pair, 2);
      $name  = urldecode($encodedName);
      $value = urldecode($encodedValue);
      if (array_key_exists($name, $request)) { continue; } // skip if already defined in GET/POST
      $request[$name] = $value;
    }
  }

  // remove :disableAutocomplete####### fieldname prefix - added to prevent password managers that don't respect
  // ... autocomplete="off" from recognizing and autofilling password fields
  foreach ($request as $key => $value) {
    if (preg_match("/(^.*?):disableAutocomplete\d+\z/", $key, $matches)) {
      list($fieldnameWithPrefix, $originalFieldname) = $matches;
      $request[$originalFieldname] = $value;
      unset($request[$fieldnameWithPrefix]);
    }
  }

  //
  return $request;

}

// set timezone, timezone list here: http://www.php.net/manual/en/timezones.php
function _init_setTimezone() {
  global $SETTINGS;
  if (@date_default_timezone_set($SETTINGS['timezone'])) { return; }

  // set this manually in case alert() isn't defined
  alert("Error setting timezone to '{$SETTINGS['timezone']}', Invalid timezone name.<br>\n");
  date_default_timezone_set('UTC');
}



// test outbound internet connectivity and display errors and tips for system admins to correct common issues
/*// Example usage in a stand-alone script (for demonstrating connectivity problems to web hosts):
  include "lib/init.php";
  _init_showInternetConnectivityErrors('www.example.com', '8080');
  die("Connection was successful - everything appears to be working");
*/
function _init_showInternetConnectivityErrors($hostname = 'www.google.com', $port = 80) {  // use google for testing as it should always be available worldwide

  ### set vars and do connection test
  $ipOrHostname               = gethostbyname($hostname); // returns IP or passed hostname if IP lookup failed
  $resolvedIp                 = ($ipOrHostname == $hostname) ? '' : $ipOrHostname;
  $connectToDomain            = @fsockopen("tcp://$hostname", $port, $hostErrno, $hostErrstr, 2);
  $connectToIpv4              = $resolvedIp ? @fsockopen("tcp://$ipOrHostname", $port, $ipErrno, $ipErrstr, 2) : false;
  $fsockopenDisabled          = preg_match('/fsockopen/i', ini_get('disable_functions'));
  $outboundConnectionsWorking = $resolvedIp && $connectToDomain && $connectToIpv4;

  ### shell commands
  $hostnameAndPort = $hostname . (($port && $port != 80) ? ":$port" : ''); // add :port if it's defined and not 80
  $wgetCommand     = "wget --spider -T2 -O- http://$hostnameAndPort/ 2>&1"; // wget docs: http://www.gnu.org/software/wget/manual/wget.html
  // --spider                    // don't download pages, just check if they are there
  // -T2 or --timeout=2          // timeout after X seconds on DNS, connect, or read (independently)
  // -O- or --output-document=-  // output to STDOUT, needed so wget 1.12 won't save a file on second GET request after first HEAD request gets a 501
  // -d  or --debug              // (not used - add for debugging) Show debugging info such as HTTP request and response headers, and more detailed wget data

  $curlCommand     = "curl --head -v -m2 http://$hostnameAndPort/ 2>&1"; // curl docs: http://curl.haxx.se/docs/manpage.html
  // -I  or --head               // send HTTP HEAD request only (instead of get)
  // -v  or --verbose            // Show request and response headers and debug data
  // -m2 or --max-time 2         // timeout after max-time seconds (for whole operation)
  // -N  or --no-buffer          // (not used) disable output buffering
  // -s  or --silent             // (not used) Don't show progress meter or error messages.
  // -S  or --show-error         // (not used) When used with -s it makes curl show an error message if it fails.
  // -i  or --include            // (not used) Show HTTP headers received

  ### wget|curl debug output
  if (!$outboundConnectionsWorking && (@$_REQUEST['wget'] || @$_REQUEST['curl'])) {
    $command = @$_REQUEST['curl'] ? $curlCommand : $wgetCommand;
    header("Content-type: text/plain");
    print "Executing command: $command\n\n";
    print `$command`;
    print "\nDone, " . showExecuteSeconds(true). " seconds";
    exit;
  }

  // default error message
  if (!$outboundConnectionsWorking) {
    if ($hostname == 'www.google.com') { $errors  = "Error: Outbound internet connections from this server are not working.<br>\n"; }
    else                               { $errors  = "Error: Internet connections from this server to '$hostname' are not working.<br>\n"; }
    $errors .= "Please ask your system administrator to enable network connectivity for PHP and/or resolve this issue.<br><br>\n";
    $errors .= "Technical details for system administrators:<br>\n";

    $errors .= "<ul>\n";
    if (!$resolvedIp)                        { $errors .= "<li>Unable to resolve IP address for test domain '$hostname' - Check DNS</li>"; }
    if (!$connectToDomain)                   { $errors .= "<li>Unable to connect to test domain '$hostname' (Error #" .@$hostErrno. " - " .@$hostErrstr. ") - Check Firewall</li>"; }
    if (!$connectToDomain && $connectToIpv4) { $errors .= "<li>Unable to connect to test domain '$hostname' but able to resolve and connect to IPv4 ($resolvedIp) for domain - Check IPv6 config</li>"; }
    if ($fsockopenDisabled) {
      $errors .= "<li>fsockopen appears in list of disabled php functions 'disable_functions' - Check php.ini or httpd.conf";
      $errors .= "<ul>\n";
        $errors .= "<li>Loaded php.ini: " .php_ini_loaded_file(). "\n";
        $errors .= "<li>disable_function: " .get_cfg_var("disable_functions"). "\n";
      $errors .= "</ul></li>\n";
    }

    // debug utilities
    $errors .= "<li>Debugging - check resolved addresses and connection status with:\n";
    $errors .= "<ul>\n";
    $errors .= "<li><a href='?wget=1'>$wgetCommand</a></li>\n";
    $errors .= "<li><a href='?curl=1'>$curlCommand</a></li>\n";
    $errors .= "</ul></li>\n";

    $errors .= "</ul>\n";

    die($errors);
  }
}

// load settings from var_export file, as saved by settingsSave() (or, for backwards compatibility, INI file)
function _init_loadSettings() {

  // get settings filenames and paths (either)
  if (defined('CRON_HOSTNAME')) { $hostnameWithoutPort = CRON_HOSTNAME; }                           // use CRON_HOSTNAME if it's set (for command-line cron scripts where HTTP_HOST isn't set)
  else                          { $hostnameWithoutPort = explode(':', @$_SERVER['HTTP_HOST'])[0]; } // otherwise use HTTP_HOST sent by browser
  $hostnameWithoutPort       = strtolower($hostnameWithoutPort);
  $hostnameWithoutPort       = preg_replace('/[^\w\-\.]/', '', $hostnameWithoutPort); // security: HTTP_HOST is user defined - remove non-filename chars to prevent ../ attacks
  $hostnameWithoutPort       = preg_replace('/^www\./i', '', $hostnameWithoutPort);   // v2.50 - usability: don't require www. prefix so www.example.com and example.com both check for settings.example.com.php
  $settings_fileName         = 'settings.' .preg_replace('/[^\w\-\.]/', '', $hostnameWithoutPort). '.php';
  
  define('SETTINGS_DEV_FILENAME', $settings_fileName);
  define('SETTINGS_DEV_FILEPATH', DATA_DIR.'/'.SETTINGS_DEV_FILENAME);

  // set settings name and path for this server
  $useDev = is_file(SETTINGS_DEV_FILEPATH);
  define('SETTINGS_FILENAME', ($useDev ? SETTINGS_DEV_FILENAME : 'settings.dat.php'));
  define('SETTINGS_FILEPATH', ($useDev ? SETTINGS_DEV_FILEPATH : DATA_DIR.'/settings.dat.php'));

  // Require hostname-based settings files on development server domains (this section to be expanded)
  if (isInstalled() && isDevServer() && !is_file(SETTINGS_DEV_FILEPATH)) {
    header("Content-type: text/plain");
    die("Development server requires custom settings files.  Delete /data/isInstalled.php and re-install to create one.");
  }

  // load settings
  global $SETTINGS;
  if (!is_file(SETTINGS_FILEPATH)) { renameOrRemoveDefaultFiles(); } // rename settings.dat.php.default to settings.dat.php
  $SETTINGS = loadStructOrINI(SETTINGS_FILEPATH);

  ### set defaults (if not already defined in settings file - this happens when a user upgrades)
  // NOTE: Do this here for future instead of _upgradeSettings()
  $defaults = [
    'activePlugins'          => '',
    'adminEmail'             => '',
    'adminUrl'               => '',
    'advanced'               => [],
    'bgtasks_disabled'       => '0',
    'bgtasks_lastEmail'      => '0',
    'bgtasks_lastRun'        => '0',
    'cookiePrefix'           => substr(md5(mt_rand()), 0, 5) . '_',
    'cronLogLimit'           => 1000,
    'cssTheme'               => 'theme_blue.css',
    'dateFormat'             => '',
    'dateRegistered'         => '0',
    'demoMode'               => '0',
    'developerEmail'         => isset($SETTINGS['adminEmail']) ? $SETTINGS['adminEmail'] : '',       // added in 3.11, defaults to adminEmail
    'errorlog_lastEmail'     => '0',  // added in v3.00
    'footerHTML'             => '',
    'headerImageUrl'         => '',
    'helpUrl'                => '',
    'installPath'            => '',
    'language'               => '',
    'licenseCompanyName'     => '',
    'licenseDomainName'      => '',
    'programBuild'           => $GLOBALS['APP']['build'],
    'programName'            => '',
    'programVersion'         => $GLOBALS['APP']['version'],
    'timezone'               => 'UTC',
    'uploadDir'              => '', // set in code below
    'uploadUrl'              => '', // set in code below
    'vendorLocation'         => '',
    'vendorName'             => '',
    'vendorUrl'              => '',
    'webPrefixUrl'           => '',
    'websiteUrl'             => '/#setThisInAdminMenu',
    'webRootDir'             => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '',  // added in 2.01
    'wysiwyg'                => [],

    'advanced' => [
      'allowRelatedRecordsDragSorting' => '0',
      'auditLog_enabled'               => true,
      'bgtasks_disabled'               => '0',
      'checkReferer'                   => '1',
      'convertUploadsToWebp'           => '0',
      'codeGeneratorExpertMode'        => '0',
      'disableAutocomplete'            => '0',
      'disableHTML5Uploader'           => '0',
      'hideLanguageSettings'           => '0',
      'httpProxyServer'                => '',
      'imageResizeQuality'             => '80',
      'languageDeveloperMode'          => '0',
      'legacy_mysql_support'           => '0',        // added in 3.09, set to 1 in upgrade_functions.php if upgrading from earlier versions
      'login_expiry_limit'             => '30',
      'login_expiry_unit'              => 'minutes',
      'outgoingMail'                   => 'sendOnly',
      'permissions_dirs'               => '0755',     // added in 3.11 - set to 0775 for group access or 0777 so all users can access
      'permissions_files'              => '0644',     // added in 3.11 - set to 0664 for group access or 0666 so all users can access
      'permissions_umask'              => '0022',
      'phpEmailErrors'                 => '0',
      'phpHideErrors'                  => '0',
      'requireHTTPS'                   => '0',
      'restrictByIP'                   => '0',
      'restrictByIP_allowed'           => '',
      'session_cookie_domain'          => '',
      'session_save_path'              => '',
      'showExpandedMenu'               => '0',
      'smtp_hostname'                  => '',
      'smtp_method'                    => 'php',
      'smtp_password'                  => '',
      'smtp_port'                      => '',
      'smtp_username'                  => '',
      'useDatepicker'                  => '0',
      'useMediaLibrary'                => '0',
    ],

    'wysiwyg' => [
      'wysiwygLang'          => 'en',
      'includeDomainInLinks' => '0',
    ],

    'mysql' => [
      'hostname'                => 'localhost',
      'database'                => '',
      'username'                => '',
      'password'                => '',
      'tablePrefix'             => 'cmsb_',
      'allowSystemFieldEditing' => '0',
      'textOnlyErrors'          => '0',
      'requireSSL'              => '0',
      'columnEncryptionKey'     => '',
      // not in use yet, may be removed // '_charset'                => 'utf8mb4',             // added in v3.13
      // not in use yet, may be removed // '_collation'              => 'utf8mb4_general_ci',  // added in v3.13
    ],
  ];

  // use defaults if no previous value defined
  foreach ($defaults as $key => $value) {
    if (is_array($value)) { // if subkeys
      foreach ($value as $subKey => $subValue) {
        if (!isset($SETTINGS[$key][$subKey])) { $SETTINGS[$key][$subKey] = $subValue; }
      }
    }
    if (!isset($SETTINGS[$key])) { $SETTINGS[$key] = $value; }
  }

  ### custom defaults

  // adminUrl - update if url path has changed
  if (defined('IS_CMS_ADMIN')) {
    
    // require admin url to end in .php
    $currentUrl = realUrl($_SERVER['SCRIPT_NAME']);
    
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    $oldPath = parse_url(@$SETTINGS['adminUrl'], PHP_URL_PATH);
    
    $hasAdminPathChanged = $currentPath != $oldPath;
    if ($hasAdminPathChanged) { // only update adminUrl when in the CMS admin
      $SETTINGS['adminUrl'] = @array_shift(explode('?', $currentUrl));  // added in 2.12 - this must be set when admin.php is being access directly so we get the right URL
      saveSettings();

      if (isInstalled()) {
        alert(sprintf(t("Updating Program Url to: %s")."<br>\n", htmlencode($SETTINGS['adminUrl'])));
      }

    }
  }

  // set default uploadDir and uploadUrl (do this here as above defaults code only runs when keys are undefined, not when they are blank)
  if (!$SETTINGS['uploadDir']) {
    $SETTINGS['uploadDir'] = 'uploads/';  // previously: /../uploads/
  }
  if (!$SETTINGS['uploadUrl'] && !inCLI()) { // SCRIPT_NAME is set to filepath not web path when running in CLI, giving us incorrect values
    $SETTINGS['uploadUrl'] = dirname($_SERVER['SCRIPT_NAME']) ."/uploads/";    // previously: /../uploads/
    $SETTINGS['uploadUrl'] = realUrl($SETTINGS['uploadUrl']);                  // remove ../ parent reference
    $SETTINGS['uploadUrl'] = parse_url($SETTINGS['uploadUrl'], PHP_URL_PATH);  // remove scheme://hostname and leave /url/path
  }
  
  // remove old settings
  $removeKeys  = array('vendorPoweredBy', 'timezoneOffsetAddMinus', 'timezoneOffsetHours', 'timezoneOffsetMinutes');
  $removeCount = 0;
  foreach ($removeKeys as $key) {
    if (array_key_exists($key, $SETTINGS)) { unset($SETTINGS[$key]); $removeCount++; }
  }
  if ($removeCount) { saveSettings(); }

  // remove/convert old 'isInstalled' setting (from v2.09)
  if (array_key_exists('isInstalled', $SETTINGS)) {
    isInstalled( true ); // set new installed status (semaphore file)
    unset( $SETTINGS['isInstalled'] );
    saveSettings();
  }


  // Update PHP config with SMTP values from settings (only effects users who call mail() explicitly)
  if ($GLOBALS['SETTINGS']['advanced']['smtp_hostname']) { ini_set('SMTP',      $GLOBALS['SETTINGS']['advanced']['smtp_hostname']); }
  if ($GLOBALS['SETTINGS']['advanced']['smtp_port'])     { ini_set('smtp_port', $GLOBALS['SETTINGS']['advanced']['smtp_port']); }

  // Note: We don't need to return $SETTINGS because we're modifying the global.
}

//
function _init_startSession() {

  // Remove 0-byte session files on shutdown - added in v2.52
  // PHP _always_ creates session files, even when the sessions are empty.  This means 10 hits from a search engine spider creates
	// ... ten 0-byte session files that won't be removed until after session.gc_maxlifetime is reached.  This can create tens of thousands
	// ... of unneeded files.  This function removes zero-byte session files on shutdown by calling session destroy. For session in active
	// ... use, unsetting all $_SESSION values will cause the session file to be removed, but the same file will be recreated on next page-view.
	// PHP Docs Reference: "A file for each session (regardless of if any data is associated with that session) will be created. This is due to
	// ... the fact that a session is opened (a file is created) but no data is even written to that file. Note that this behavior is a
	// ... side-effect of the limitations of working with the file system... from: http://php.net/manual/en/session.installation.php
  function _remove_empty_session_file() { if (isset($_SESSION) && empty($_SESSION) && session_id()) { @session_destroy(); }} // removes session file
  register_shutdown_function('_remove_empty_session_file'); // remove session file on shutdown
  // note: run this here so even if we're not creating a session, session's created by other PHP code won't leave 0 byte files either.

  // check if we need to start a session
  $startSession = false;
  if     (inCLI())                  { $startSession = false; } // skip for command-line scripts
  elseif (inDemoMode())             { $startSession = true; }  // demos need to create a session to remember what table to load (even for demo viewer pages)
  elseif (defined('IS_CMS_ADMIN'))  { $startSession = true; }  // cms admin uses sessions to save temporary settings
  elseif (defined('START_SESSION')) { $startSession = true; }  // if starting a session is explicitly requested
  if (!$startSession) { return; }

  startSessionIfRequired();
}

//
function startSessionIfRequired() {
  global $SETTINGS;

  // don't run this more than once
  if (defined('SESSION_STARTED')) { return; }
  define('SESSION_STARTED', true);

  // error-checking for custom session settings
  $customSessionErrors = getCustomSessionErrors(@$SETTINGS['advanced']['session_cookie_domain'], @$SETTINGS['advanced']['session_save_path']);
  if ($customSessionErrors) {
    $customSessionErrors .= sprintf(t('To change %1$s settings edit %2$s'), 'session', '/data/'.SETTINGS_FILENAME);
    die($customSessionErrors);
  }

  // Initialize session
  $session_name = cookiePrefix() . 'PHPSESSID'; // use a unique session cookie for each CMS installation
  ini_set('session.name',             $session_name);  // sets session.name
  ini_set('session.cookie_secure',    isHTTPS() ); // use/require secure cookies when on HTTPS:// connections
  ini_set('session.use_cookies',      true );
  ini_set('session.use_only_cookies', true );
  ini_set('session.cookie_domain',    @$SETTINGS['advanced']['session_cookie_domain']);  // use this to allow shared login access between subdomains such as host1.example.com, host2.example.com, example.com
  ini_set('session.cookie_path',      '/' );
  ini_set('session.cookie_httponly',  true );
  ini_set('session.cookie_lifetime',  60*60*24*365*25 ); // save session cookies forever (or 25 years) so they'll work even if users who have turned their system clocks back a few years
  ini_set('session.gc_maxlifetime',   60*60*24 );       // session garbage-collection code starts getting randomly called after this many seconds of inactiity
  ini_set('session.use_trans_sid',    false );
  if (@$SETTINGS['advanced']['session_save_path']) {
    ini_set('session.save_path',   @$SETTINGS['advanced']['session_save_path']); // use this if your host imposes restrictive session removal timeouts
    ini_set('session.gc_probability',   1 );        // after gc_maxlifetime is met old session are cleaned up randomly every (gc_probability / gc_divisor) requests
    ini_set('session.gc_divisor',       100 );      // after gc_maxlifetime is met old session are cleaned up randomly every (gc_probability / gc_divisor) requests
    // we don't set gc_ values by default because they cause errors on some server configs: http://bugs.php.net/bug.php?id=20720
  }
  if (!session_start()) { trigger_error("Couldn't start session! '" .@$php_errormsg. "'!", E_USER_ERROR); }

}

// $sessionErrors = getCustomSessionErrors(@$SETTINGS['advanced']['session_cookie_domain'], @$SETTINGS['advanced']['session_save_path']);
// $sessionErrors = getCustomSessionErrors(@$_REQUEST['session_cookie_domain'], @$_REQUEST['session_save_path']);
function getCustomSessionErrors($cookieDomain, $savePath) {
  $errors = '';

  // session.save_path
  $settingName = 'session.save_path';
  if ($savePath != '') {
    if     (!file_exists($savePath)) { $errors .= sprintf('%1$s doesn\'t exist! (%2$s)',                     $settingName, htmlencode($savePath)) . "<br>\n"; }
    elseif (!is_dir($savePath))      { $errors .= sprintf('%1$s isn\'t a directory! (%2$s)',                 $settingName, htmlencode($savePath)) . "<br>\n"; }
    elseif (!is_writable($savePath)) { $errors .= sprintf('%1$s isn\'t writable, check permissions! (%2$s)', $settingName, htmlencode($savePath)) . "<br>\n"; }
  }

  // session.cookie_domain
  $settingName = 'session.cookie_domain';
  if ($cookieDomain != '') {
    $regexp   = "/\b" .preg_quote($cookieDomain, '/'). "$/i"; // \b so foo.com matches www.foo.com but not xfoo.com
    $hostname = @$_SERVER['HTTP_HOST'];
    if (!preg_match($regexp, $hostname)) {
      $errors  .= sprintf('%1$s doesn\'t match current domain: %2$s', $settingName, htmlencode($cookieDomain)) . "<br>\n";
    }
  }

  //
  return $errors;
}


// check if program has been installed... Or change the isInstalled status
function isInstalled($markAsInstalled = false) {
  $semaphoreFile = DATA_DIR . '/isInstalled.php';
  $isInstalled   = file_exists($semaphoreFile);

  // set installed status
  if ($markAsInstalled && !$isInstalled) {
    $fileContents = "<?php die('Delete this file to re-install the software.'); ?>\n";
    file_put_contents($semaphoreFile, $fileContents) || die("Error writing to '$semaphoreFile'! " .@$php_errormsg );
  }

  //
  return $isInstalled;
}

// return true if upgrade is pending
function isUpgradePending() {
  $isUpgradePending = $GLOBALS['SETTINGS']['programVersion'] != $GLOBALS['APP']['version'];
  return $isUpgradePending;
}

// check if this is a dev server - this is an experimental feature to be expanded on...
function isDevServer() {
  if (!array_key_exists('HTTP_HOST', $_SERVER)) { return; } // skip if no HTTP_HOST defined

  //
  $lcHostnameWithoutPort = array_first(explode(':', strtolower(@$_SERVER['HTTP_HOST'])));
  $isDevOnlyDomain       = endsWith('.cms'.'b.me', $lcHostnameWithoutPort);
  return $isDevOnlyDomain;
}



// replace and collapse slashes in filepaths
function fixSlashes($path) {
  $isUncPath  = substr($path, 0, 2) == '\\\\';
  $path = preg_replace('/[\\\\\/]+/', '/', $path); // replace and collapse slashes
  if ($isUncPath) { $path = substr_replace($path, '\\\\', 0, 1); } // replace UNC prefix

  return $path;
}

//eof
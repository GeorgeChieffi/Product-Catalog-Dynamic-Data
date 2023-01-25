<?php

// DEVELOPER NOTES:
// ALL plugins can be disabled by creating semaphore file:             plugins/_disable_all_plugins.txt
// Required System plugins can be disabled by creating semaphore file: plugins/_disable_sys_plugins.txt

// load activated plugins - NOTE: As of v3.00 this does NOT scan /plugins/ dir for required system plugins
// ...The plugin folder is automatically scanned and required system plugins added when you access the plugins menu
// ...or when you call getPluginList();
function loadPlugins() {
  if (!isInstalled()) { return; }
  if (!empty($_REQUEST['menu']) && $_REQUEST['menu'] == '_incrementalRestore') { return; } // don't load plugins if doing incremental restore - prevents plugins from interfering
  
  // load active plugin files
  $pluginsDir        = "{$GLOBALS['PROGRAM_DIR']}/plugins";
  $activePluginPaths = preg_split('/,\s+/', @$GLOBALS['SETTINGS']['activePlugins'], -1, PREG_SPLIT_NO_EMPTY);
  foreach ($activePluginPaths as $pathFromPluginsDir) {
    $filepath   = "$pluginsDir/$pathFromPluginsDir"; 
    $pluginData = _getPluginData($filepath, $pathFromPluginsDir);
    if (!$pluginData) { continue; } // if not a plugin or couldn't load plugin data - may occur when plugins are being uploaded and overwritten

    if ($pluginData['isActive']) { include_once($filepath); }
    else                         {
      @trigger_error("Automatically deactivated plugin", E_USER_NOTICE); // log for debugging - should happen if plugin required CMSB version is too high such as when a user downgraded their CMS
      deactivatePlugin($pathFromPluginsDir);
    }
  }
}

// return list of plugins by scanning /plugins/ folder (up to 2 levels deep)
// NOTE: This also automatically activates system plugins
function getPluginList() {
  $pluginsDir = "{$GLOBALS['PROGRAM_DIR']}/plugins";

  // allow disabling plugins
  $disableAllPluginsFile = "$pluginsDir/_disable_all_plugins.txt";
  if (file_exists($disableAllPluginsFile)) { return []; }
  
  // load and cache plugin list
  static $pluginList;
  if (!$pluginList) {
    $pluginList   = [];
    $phpFilepaths = _getPhpFilepathsFromPluginsDir();
    foreach ($phpFilepaths as $filepath) {
      $pathFromPluginsDir = str_replace("$pluginsDir/", '', $filepath); // remove base plugin dir to get path from /plugins/
      $pluginData         = _getPluginData($filepath, $pathFromPluginsDir);
      if ($pluginData) { $pluginList[$pathFromPluginsDir] = $pluginData; }
    }
  }

  // return cached plugin list
  return $pluginList;
}


// returns a list of PHP files from the /plugins/ folder.
// Skips: C-V-S folders, and files that end in .ini.php or .defaultSqlData.php
// Only looks 2 levels deep under the plugins folder, eg: /plugins/dir1/dir2/
// NOTE: This function is highly optimized for speed
function _getPhpFilepathsFromPluginsDir() {
  $pluginsDir       = "{$GLOBALS['PROGRAM_DIR']}/plugins";
  $pluginsDirLength = strlen($pluginsDir);
  $dirStack   = array($pluginsDir);
  $files      = [];
  
  while ($thisDir = array_pop($dirStack)) {
    $dirHandle = opendir($thisDir);
    while ($filename = readdir($dirHandle)) {
      $path = "$thisDir/$filename";
      if     ( // if file
              pathinfo($filename, PATHINFO_EXTENSION) == 'php' &&
              !preg_match("/\.(ini|defaultSqlData)\.php$/", $filename) &&
              is_file($path)
              ) {
        $files[] = $path;
      }
      elseif ( // if dir
              $filename != '.' &&
              $filename != '..' &&
              $filename != 'C'.'VS' && // this string is concatenated so the build script doesn't stop on the banned keyword
              substr_count(substr($thisDir, $pluginsDirLength), '/') < 2 && // Only scan 2 levels under /plugins/.  Eg: /plugins/dir1/dir2/
              is_dir($path)
              ) {
        $dirStack[] = $path;
      }
    }
    closedir($dirHandle);
  }
  return $files;
}

// return data about about plugin from plugin header comments
function _getPluginData($filepath, $pathFromPluginsDir) {

  // Skip PHP files without a "Plugin Name:" set in the first 2k bytes of the file (speed optimization)
  $fileHandle = @fopen($filepath, 'r');
  if (!$fileHandle) { return []; }
  $fileHeader = @fread($fileHandle, 2048);
  @fclose($fileHandle);
  if (!preg_match("/Plugin Name:/mi", $fileHeader)) { return []; }

  // cache data
  static $activePluginFiles = [];
  if (!$activePluginFiles) {
    $activePluginFiles = preg_split('/,\s+/', @$GLOBALS['SETTINGS']['activePlugins']);
  }

  // plugin data to match
  static $textKeyToFieldname = [];
  if (!$textKeyToFieldname) {
    $textKeyToFieldname['Plugin Name']            = 'name';            // shown in plugin menu
    $textKeyToFieldname['Plugin URI']             = 'uri';             // shown in plugin menu
    $textKeyToFieldname['Version']                = 'version';         // shown in plugin menu
    $textKeyToFieldname['Description']            = 'description';     // shown in plugin menu
    $textKeyToFieldname['Author']                 = 'author';          // shown in plugin menu
    $textKeyToFieldname['Author URI']             = 'authorUri';       // shown in plugin menu
    $textKeyToFieldname['Requires at least']      = 'cmsVersionMin';   // DEPRECATED: minimum CMS version required.  Plugin can't be activated if CMS version isn't high enough
    $textKeyToFieldname['CMS Version Required']   = 'cmsVersionMin';   // NOTE THIS WON'T WORK FOR <3.00 USERS so use "Requires At Least" until most users are on 3.0+.  minimum CMS version required.  Plugin can't be activated if CMS version isn't high enough
// FUTURE:   $textKeyToFieldname['PHP Version Required']   = 'phpVersionMin';   // minimum PHP version required.  Plugin can't be activated if PHP version isn't high enough
    $textKeyToFieldname['Required System Plugin'] = 'isSystemPlugin';  // always load this plugin - can't be de-activated
  }

  // load values from plugin header
  $pluginData = [];
  $pluginData['filename'] = $pathFromPluginsDir;
  foreach ($textKeyToFieldname as $textKey => $fieldname) {
    preg_match("/^\s*$textKey:(.*?)(\r|\n)/mi", $fileHeader, $matches); // match \r as well for mac users who uploaded file in ascii with wrong line end chars (and windows users who would be \r\n)
    $pluginData[$fieldname] = $matches ? trim(@$matches[1]) : '';
  }

  // allow ignoring of System Plugins
  $disableSystemPluginsFlag      = file_exists("{$GLOBALS['PROGRAM_DIR']}/plugins/_disable_sys_plugins.txt");
  $pluginData['wasSystemPlugin'] = $pluginData['isSystemPlugin'] && $disableSystemPluginsFlag;
  $pluginData['isSystemPlugin']  = $pluginData['isSystemPlugin'] && !$disableSystemPluginsFlag;
  
  // auto-activate system plugins
  $hasRequiredCmsVersion = ($pluginData['cmsVersionMin'] <= $GLOBALS['APP']['version']);
  $inActivePluginsList   = in_array($pathFromPluginsDir, $activePluginFiles);
  if (!$inActivePluginsList && $hasRequiredCmsVersion && $pluginData['isSystemPlugin']) {
    activatePlugin($pathFromPluginsDir);
    $inActivePluginsList = true;
    
    // show alert on plugins menu
    if (isset($_REQUEST['menu'])   && $_REQUEST['menu'] == 'admin' &&
        isset($_REQUEST['action']) && $_REQUEST['action'] == 'plugins') {
      alert(sprintf(t("Automatically activating System Plugin: %s"), $pathFromPluginsDir)."<br>\n");
    }
  }

  //  set isActive flag
  $pluginData['isActive'] = $hasRequiredCmsVersion && $inActivePluginsList;

  //
  return $pluginData;
}

// Plugin paths are stored as relative from plugin dir, eg: superwidget/sw_plugin.php
// This function returns true if specified filename or path matches an active plugin exactly (eg: superwidget/sw_plugin.php)
// 2.52 - Now also returns true if relative plugin path "endsWith" string, eg: sw_plugin.php now matches
// ... we do this so: Section Editors > Advanced (tab) > Required Plugins, can use filenames only since users tend to rename plugin dirs.
function isPluginActive($filename) {

  $activePluginPaths = preg_split('/,\s+/', @$GLOBALS['SETTINGS']['activePlugins'], -1, PREG_SPLIT_NO_EMPTY);

  // load active plugin paths
  static $activePluginPaths;
  if (!isset($activePluginPaths)) {
    $activePluginPaths = preg_split('/,\s+/', @$GLOBALS['SETTINGS']['activePlugins'], -1, PREG_SPLIT_NO_EMPTY);
  }

  // check if filename or path is active
  $isActive = false;
  foreach (array_values($activePluginPaths) as $relativePluginPath) {
    if (!endsWith($filename, $relativePluginPath)) { continue; }
    $isActive = true;
    break;
  }

  return $isActive;
}

// add generator, or call with no args to return generator list
// type can only be specified for built-in generators and is used for specifying legacy generators
function addGenerator($functionName = '', $name = '', $description = '', $builtInType = 'private') {
  static $generators = [];

  // return generator list (if no args specified)
  if (!$functionName && !$name && !$description) { return $generators; }

  // is this a built in generator? (built-in generators show in a list above plugin added generators)
  $callerFilepath = fixSlashes(array_value(array_value(debug_backtrace(),'0'),'file'));
  if     (preg_match('|/plugins/|', $callerFilepath))        { $isBuiltIn = false; }
  elseif (preg_match('|/_codeGenerator/|', $callerFilepath)) { $isBuiltIn = true; }
  else { dieAsCaller("addGenerator() can only be called from plugins in /plugins/ or in /lib/menus/_codeGenerator/, not $callerFilepath!"); }

  if ($isBuiltIn) {
    if ($builtInType == 'legacy')       { $name = t("Legacy") . " $name"; }
//    if ($builtInType == 'private')      { $name .= " (" .t("new"). ")"; }
    if ($builtInType == 'experimental') { $name .= " (" .t("experimental"). ")"; }
  }
  
  // add new generator
  $generators[] = array(
    'function'    => $functionName,
    'name'        => $name,
    'description' => $description,
    'type'        => $isBuiltIn ? $builtInType : 'public',
  );
}


//
function getGenerators($type) {

  // get generator list
  $generators = addGenerator();

  // filter list by type
  if     ($type == 'all')          { $generators = $generators; }
  elseif ($type == 'public')       { $generators = array_where($generators, array('type' => 'public')); }
  elseif ($type == 'private')      { $generators = array_where($generators, array('type' => 'private')); }
  elseif ($type == 'legacy')       { $generators = array_where($generators, array('type' => 'legacy')); }
  elseif ($type == 'experimental') { $generators = array_where($generators, array('type' => 'experimental')); }
  else { dieAsCaller(__FUNCTION__ . ": Unknown type argument '" .htmlencode($type). "'!"); }

  // sort 'public' generators by 'name'
  if ($type == 'public') {
    array_multisort(
      array_pluck($generators, 'name'), SORT_ASC,
      $generators
    );
  }

  //
  return $generators;
}

//
function plugin_getDeprecatedHooks() {
  static $deprecatedHooks = [
    'edit_buttonsRight',
    'list_buttonsRight',
    'view_buttonsRight',
    'header_links',
  ];
  return $deprecatedHooks;
}

//
// $priority is sorted numerically with lower numbers running first: -1, 0, 1, 10, 100, etc
// old usage: addAction($hookName, $functionName, $priority = 10, $acceptedArgs = 1);
// new usage: addAction($hookName, function() {}, $priority = 10);
function addAction($hookName, $function, $priority = 10, $acceptedArgs = null) {
  if (!$hookName)     { die(__FUNCTION__ . ": No hookname specified!"); }
  
  // check for deprecated hooks/actions/filters
  if (in_array($hookName, plugin_getDeprecatedHooks())) {
    $pluginRelPath = getPluginPathAndUrl()[0];
    @trigger_error("Update plugin: plugins/$pluginRelPath\nPlugin attempted to register the deprecated hook '$hookName' [MAXLOG1]", E_USER_NOTICE);
    return;
  }

  // add action
  $actionList = &_getActionList();
  if ( is_string($function) ) {
    $actionList[$hookName][$priority][] = [ 'named', $function, $acceptedArgs ];
  }
  elseif ( is_object($function) && get_class($function) === 'Closure' ) {
    $actionList[$hookName][$priority][] = [ 'anon', $function, $acceptedArgs ];
  }
  else {
    dieAsCaller(__FUNCTION__ . ": Second parameter must by a functionName or an anonymous function!");
  }
}

// alias for add_action
// $priority is sorted numerically with lower numbers running first: -1, 0, 1, 10, 100, etc
// old usage: addFilter($hookName, $functionName, $priority = 10, $acceptedArgs = 1);
// new usage: addFilter($hookName, function($value) { return $value; }, $priority = 10);
function addFilter($hookName, $function, $priority = 10, $acceptedArgs = null) {
  return addAction($hookName, $function, $priority, $acceptedArgs);
}

// note, the same args are passed to every plugin
function doAction($hookName, $arg = '') {
  pluginsCalled($hookName, 'action');

  // skip if no actions registered
  $actionList = &_getActionList();
  if (empty($actionList[$hookName])) { return; }

  // execute all registered actions for named hook
  ksort($actionList[$hookName], SORT_NUMERIC); // order by priority
  foreach ($actionList[$hookName] as $_priority => $functionList) {
    foreach ($functionList as $_key => $functionDetail) {
      list($functionType, $function, $acceptedArgs) = $functionDetail;

      // error checking: does named function exist?
      if ($functionType === 'named') {
        if (!function_exists($function)) {
          print "Plugin hook $hookName called undefined function $function()<br>\n";
          continue;
        }
      }
      
      // if $acceptedArgs wasn't specified, use reflection to determine it now
      // (also cache it in the $actionList object in case this operation is expensive)
      if ($acceptedArgs === null) {
        $reflectionInfo = new ReflectionFunction($function);
        $acceptedArgs   = $reflectionInfo->getNumberOfParameters();
        $actionList[$hookName][$_priority][$_key][2] = $acceptedArgs;
      }
      
      // get arguments this function (doAction()) was called with (but skip the first one, which was $hookName)
      $functionArgs = array_slice(func_get_args(), 1, $acceptedArgs); // Note: This won't maintain reference, to pass by reference pass as array that contains a reference
      
      // call the function! (note that call_user_func_array() accepts either a function name or an anonymous function object)
      call_user_func_array($function, $functionArgs);
    }
  }

  //
  return true;
}

// note, the first arg is the value to be filtered and the modified value is passed forward to each plugin
function applyFilters($hookName, $filteredValue = '') {
  pluginsCalled($hookName, 'filter');

  // skip if no filters registered
  $actionList = &_getActionList();
  if (empty($actionList[$hookName])) { return $filteredValue; }

  // execute all registered filters for named hook
  ksort($actionList[$hookName], SORT_NUMERIC); // order by priority
  foreach ($actionList[$hookName] as $_priority => $functionList) {
    foreach ($functionList as $_key => $functionDetail) {
      list($functionType, $function, $acceptedArgs) = $functionDetail;
      
      // error checking: does named function exist?
      if ($functionType === 'named') {
        if (!function_exists($function)) {
          print "Plugin hook $hookName called undefined function $function()<br>\n";
          continue;
        }
      }
      
      // if $acceptedArgs wasn't specified, use reflection to determine it now
      // (also cache it in the $actionList object in case this operation is expensive)
      if ($acceptedArgs === null) {
        $reflectionInfo = new ReflectionFunction($function);
        $acceptedArgs   = $reflectionInfo->getNumberOfParameters();
        $actionList[$hookName][$_priority][$_key][2] = $acceptedArgs;
      }
      
      // get arguments this function (applyFilters()) was called with (but skip the first one, which was $hookName)
      $functionArgs = array_slice(func_get_args(), 1, $acceptedArgs);
      if ($acceptedArgs) { $functionArgs[0] = $filteredValue; } // set first arg to already filtered value

      // call the function! (note that call_user_func_array() accepts either a function name or an anonymous function object)
      $filteredValue = call_user_func_array($function, $functionArgs);
    }
  }

  //
  return $filteredValue;
}


// $actionList = &_getActionList();
function &_getActionList() {
  static $actionList = [];
  return $actionList;
}

// add a scheduled cronjob to be dispatched by cron.php and logged in CMS
// Cron Expression Format: minute(0-59), hour(0-23), dayOfMonth(1-31), month(1-12), dayOfWeek(0-7, 0=Sunday)
// Supports: *(any/all), 6(numbers), 1-3(ranges), 15,30,45(number lists)
// Note: Cronjob functions should return any content they want added to the log as the "Summary" and print/echo any content they wanted displayed as "output" textbox
// Example: addCronJob('my_function1', 'Activity Name', '5 * * * *');  // Run at 5 minutes after every hour
// Example: addCronJob('my_function2', 'Activity Name', '0 1 * * 0');  // Run at 1am every Sunday Night
function addCronJob($functionName, $activityName, $cronExpression) {
  if (!$functionName)                  { dieAsCaller(__FUNCTION__ . ": No functioname specified!"); }
  if (!function_exists($functionName)) { dieAsCaller(__FUNCTION__ . ": Specified function '" .htmlencode($functionName). "' doesn't exist!"); }

  // add actions
  $cronList = &getCronList();
  if (array_key_exists($functionName, $cronList)) { dieAsCaller(__FUNCTION__ . ": Specified function '" .htmlencode($functionName). "' already exists in cron list!"); }
  $cronList[$functionName] = array(
    'function' => $functionName,
    'activity' => $activityName,
    'expression' => $cronExpression,
  );
}

// $cronList = &_getCronList();
function &getCronList() {
  static $cronList = [];
  return $cronList;
}

//
function activatePlugin($file) {
  global $SETTINGS;

  // test for errors - if this dies it won't activate the plugin
  $pluginsDir = "{$GLOBALS['PROGRAM_DIR']}/plugins";
  include "$pluginsDir/$file";

  // add plugin to list
  $activePluginFiles = [];
  $activePluginFiles[$file] = 1;
  foreach (preg_split('/,\s+/', $SETTINGS['activePlugins'], -1, PREG_SPLIT_NO_EMPTY) as $activeFile) {
    $activePluginFiles[$activeFile] = 1;
  }
  ksort($activePluginFiles);
  
  // save settings
  $GLOBALS['SETTINGS']['activePlugins'] = join(', ', array_keys($activePluginFiles));
  saveSettings();

  //
  doAction( 'plugin_activate', $file );
}

//
function deactivatePlugin($file) {
  global $SETTINGS;

  // remove plugin from list
  $activePluginFiles = [];
  foreach (preg_split('/,\s+/', $SETTINGS['activePlugins'], -1, PREG_SPLIT_NO_EMPTY) as $activeFile) {
    $activePluginFiles[$activeFile] = 1;
  }
  unset($activePluginFiles[$file]);

  // save settings
  $GLOBALS['SETTINGS']['activePlugins'] = join(', ', array_keys($activePluginFiles));
  saveSettings();

  doAction( 'plugin_deactivate', $file );
}

// list($pluginPath, $pluginUrl) = getPluginPathAndUrl();
// return path/url of the current plugin, or the last plugin called in the call stack.  You can
// ... also specify an alernate filename to get the path and url of files or folders in or under the plugin folder
function getPluginPathAndUrl($alernateFilename = '', $returnAbsoluteFilePath = false) {
  $pluginPath = '';
  $pluginUrl  = '';

  foreach (debug_backtrace() as $caller) {
    $callerFilepath = fixSlashes(@$caller['file']);
    if (!preg_match('|/plugins/|', $callerFilepath)) { continue; }

    // get path and url
    $pluginPath   = $callerFilepath;
    $pluginPath   = preg_replace("|^.*plugins/(.*?)$|", '\1', $pluginPath);       // eg: myPlugin/myPlugin.php
    $pluginUrl    = str_replace(' ', '%20', $_SERVER['SCRIPT_NAME']??'');            // url encoded spaces
    $pluginUrl    = preg_replace("|[^/]+$|", "plugins/$pluginPath", $pluginUrl);  // eg: /myCMS/plugins/myPlugin/myPlugin.php

    // use alternate filename
    if ($alernateFilename) {
      $pluginPath = preg_replace("|[^/]+$|", $alernateFilename, $pluginPath);
      $pluginUrl  = preg_replace("|[^/]+$|", $alernateFilename, $pluginUrl);
    }

    break;
  }

  // error checking
  if (!$pluginPath) {
    $error  = __FUNCTION__ . ": Couldn't find any plugins in caller stack.  This function can only be called by source files under the /plugins/ folder!<br>\n";
    die($error);
  }

  //
  if ($returnAbsoluteFilePath) { $pluginPath = $GLOBALS['PROGRAM_DIR'] . "/plugins/$pluginPath"; } // vv3.06
  return array($pluginPath, $pluginUrl);
}

// return header for plugin UI pages
// echo plugin_header('Page Title Here');
function plugin_header($title, $buttons = '', $showBackToPluginsButton = true) {
  
  // prepare adminUI() placeholders
  $adminUI = [];

  // page title
  $adminUI['PAGE_TITLE'] = [ t('CMS Setup'), t('Plugins') => '?menu=admin&action=plugins', $title ];

  // buttons
  $adminUI['BUTTONS'] = [];
  if (is_array($buttons)) {
    $adminUI['BUTTONS'] = $buttons;
  }
  if ($showBackToPluginsButton) {
    $adminUI['BUTTONS'][] = [ 'name' => 'null', 'label' => t('Back to Plugins &gt;&gt;'), 'onclick' => "window.location='?menu=admin&action=plugins'; return false;", ];
  }

  //
  return ob_capture('adminUI_header', $adminUI);
}


// return footer for plugin UI pages
// echo plugin_footer();
function plugin_footer() {
  return ob_capture('adminUI_footer');
}



// create schemas from /plugins/pluginDir/pluginSchemas/ if they don't already exist
// Usage: plugin_createSchemas(array('plgn_data', 'plgn_news', 'plgn_clicks'));
function plugin_createSchemas($tableNames = []) {
  $sourceDir = SCRIPT_DIR .'/plugins/'. array_value( getPluginPathAndUrl("pluginSchemas/"), 0);
  $targetDir = realpath(DATA_DIR . '/schema');
  if (!file_exists($sourceDir)) { die(__FUNCTION__ . ": Schema source dir doesn't exist: '$sourceDir'!"); }

  // if no tablenames specified load all schemas in /pluginSchemas/ - call from your plugin like this:
  // Usage: plugin_createSchemas(); // create all schemas in /plugins/yourPlugin/pluginSchemas/
  if (!$tableNames) {
    foreach (scandir($sourceDir) as $filename) {
      if (!endsWith('.ini.php', $filename)) { continue; }
      $tableNames[] = basename($filename, '.ini.php');
    }
  }

  //
  foreach ($tableNames as $basename) {

    // skip if schema already exists
    $sourceSchema = "$sourceDir/$basename.ini.php";
    $targetSchema = "$targetDir/$basename.ini.php";
    if (file_exists($targetSchema)) { continue; }

    // copy default data (if supplied)
    $sourceData = "$sourceDir/$basename.defaultSqlData.php";
    $targetData = "$targetDir/$basename.defaultSqlData.php";
    if (file_exists($sourceData)) {
      copy($sourceData, $targetData);
      if (!file_exists($targetData)) { die(__FUNCTION__ .": Error writing to '$targetData'! " .@$php_errormsg); }
    }

    // copy/create schema
    createSchemaFromFile( $sourceSchema );
  }
}



// adds a link to the plugin menu that calls a function in your plugin when clicked
// Add this code to the top of your plugin:
// pluginAction_addHandlerAndLink('test command', 'plgn_function', 'admins');
// $requiredAccess can be 'admins', 'users', 'all'
// admins - users with admin access
// users - any logged in user
// all - anyone that clicks the link, even if they're not logged in
function pluginAction_addHandlerAndLink($linkName, $functionName, $requiredAccess = 'admins') {

  // add handler
  pluginAction_addHandler($functionName, $requiredAccess);

  // save links
  $linkHTML = "<a href='" .pluginAction_getLink($functionName). "'>" .htmlencode($linkName). "</a><br>\n";
  list($pluginPath, $pluginUrl) = getPluginPathAndUrl(null);
  @$GLOBALS['PLUGIN_ACTION_MENU_LINKS'][$pluginPath] .= $linkHTML;

  // this function is called to add links
  if (function_exists('_pluginAction_addHandlerAndLink')) { return; }
  function _pluginAction_addHandlerAndLink($pluginPath) {
    echo @$GLOBALS['PLUGIN_ACTION_MENU_LINKS'][$pluginPath];
  }
  addAction('plugin_actions', '_pluginAction_addHandlerAndLink');
}


// call a function specified by pluginAction_getLink().  Use this for executing functions called by custom links in your plugin
// Add this code to the top of your plugin:
// pluginAction_addHandler('plgn_function', 'admins');
function pluginAction_addHandler($functionName, $requiredAccess = 'admins') {
  if (!function_exists($functionName)) { die(__FUNCTION__. ": Can't add plugin action handler, function '" .htmlencode($functionName). "' doesn't exist!"); }

  ### only run when plugin action is being called
  $pluginAction = $_REQUEST['_pluginAction'] ?? ''; // cannot pass null to strtolower as of PHP 8.1
  if (strtolower($pluginAction) != strtolower($functionName)) {
    return;
  }

  // error checking
  $validAccessTypes = array('admins', 'users', 'all');
  if (!in_array($requiredAccess, $validAccessTypes)) {
    $typesAsCSV = join(', ', $validAccessTypes);
    dieAsCaller(__FUNCTION__ . ": invalid 2nd argument for 'required access' must be one of ($typesAsCSV).  Please update your code!");
  }

  // default to showing plugin menu as highlighted - developers can change this in their function call
  $_REQUEST['menu']   = 'admin';
  $_REQUEST['action'] = 'plugins';

  // call function (we use a wrapper function so we can do this after user login when $CURRENT_USER is defined,
  // ... otherwise we'd just add a handler for $functionName directly)
  if (!function_exists('_pluginAction_runHandler')) { // check if function already defined (happens when addHandler is called twice for same function.
    $GLOBALS['PLUGIN_ACTION_FUNCTION_NAME']   = $functionName;
    $GLOBALS['PLUGIN_ACTION_REQUIRED_ACCESS'] = $requiredAccess;
    function _pluginAction_runHandler() {
      if ($GLOBALS['PLUGIN_ACTION_REQUIRED_ACCESS'] == 'admins' && !$GLOBALS['CURRENT_USER']['isAdmin']) { die("This action requires administrator access."); }
      if ($GLOBALS['PLUGIN_ACTION_REQUIRED_ACCESS'] == 'users'  && !$GLOBALS['CURRENT_USER'])            { die("This action you to be logged in."); }
      call_user_func($GLOBALS['PLUGIN_ACTION_FUNCTION_NAME'] );
    }
  }

  //
  if (defined('IS_CMS_ADMIN')) { $hookName = 'admin_postlogin'; } // called after user logs in, so $CURRENT_USER is defined and we can check access
  else                         { $hookName = 'viewer_postinit'; } // called after website membership runs, so $CURRENT_USER is defined and we can check access
  addAction($hookName, '_pluginAction_runHandler', 999, 0); // Set priority to 999 so this runs after all other plugins (such as website membership which defines $CURRENT_USER)
}

// create a plugin action link then will call a function when clicked
// Add this code where you want your link:  <a href='" .pluginAction_getLink('plgn_function'). "'>Do action</a>
// Add this code to the top of your plugin: pluginAction_addHandler('plgn_function', true);
function pluginAction_getLink($functionName) {
  if (!function_exists($functionName)) { die(__FUNCTION__. ": Can't create plugin action link, function '" .htmlencode($functionName). "' doesn't exist!"); }
  $link = "?_pluginAction=$functionName";
  return $link;
}


// adds a link to the plugin menu - add this code to the top of your plugin:
// pluginAction_addLink('Google', 'http://www.google.com/');
// pluginAction_addLink("<a href='http://www.google.com'>Google</a>");
function pluginAction_addLink($labelOrHTML, $url = '') {

  // save links to global
  $linkHTML = $url ? "<a href='$url'>$labelOrHTML</a><br>\n" : $labelOrHTML;
  list($pluginPath) = getPluginPathAndUrl(null);
  @$GLOBALS['_pluginAction_addLink.content'][$pluginPath] .= $linkHTML;

  // define a function to display links and add plugin action for it
  if (!function_exists('_pluginAction_addLink')) {
    function _pluginAction_addLink($pluginPath) { echo @$GLOBALS['_pluginAction_addLink.content'][$pluginPath]; }
    addAction('plugin_actions', '_pluginAction_addLink');
  }
}

// log instance of plugin being called or output list of plugins called on page
// This is help make it easier for us to find an available plugin hook for a specific page
function pluginsCalled($name = '', $type = '') {
  static $pluginsCalled = [];

  // debug: echo comment each time plugin hook is called
  //print "<!-- plugin $type: $name -->\n";

  // count instances of plugin being called  
  if ($name) { 
    if (!isset($pluginsCalled[$name])) { $pluginsCalled[$name] = 0; } // initialize key
    $pluginsCalled[$name]++;
  }
  
  // list plugin hooks called
  if (!$name) {
    $output  = ""; 
    $output .= "<!--\n  Plugin hooks called on this page (only visible for admins):\n";
    foreach ($pluginsCalled as $name => $count) { $output .= "  $name - $count times\n"; }
    $output .= "-->\n";
    return $output;
  }

}

// eof
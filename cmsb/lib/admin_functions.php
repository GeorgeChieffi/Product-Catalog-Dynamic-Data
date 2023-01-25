<?php

  // for compatibility with older plugins, include functions that have been factored out of admin_functions.php
  $libDir = pathinfo(__FILE__, PATHINFO_DIRNAME);
  require_once "$libDir/login_functions.php";

  // require HTTPS
  if (@$SETTINGS['advanced']['requireHTTPS'] && !isHTTPS()) {
    $httpsUrl = preg_replace('/^http:/i', 'https:', thisPageUrl());
    die(sprintf(t("Secure HTTP login required: %s"), "<a href='$httpsUrl'>$httpsUrl</a>"));
  }

  // restrict IP access
  if (@$SETTINGS['advanced']['restrictByIP'] && !isIpAllowed()) {
    die(sprintf(t("Access is not permitted from your IP address (%s)"), $_SERVER['REMOTE_ADDR']));
  }

  // install or upgrade if needed
  installIfNeeded();
  upgradeIfNeeded();


// set current user or show login menu
function adminLoginMenu() {
  global $CURRENT_USER;
  $errors = '';
  
  // login menu actions
  $action = @$_REQUEST['action'];
  if ($action == 'logoff')      { user_logoff(); exit; }
  if ($action == 'loginSubmit') {
    security_dieUnlessPostForm();
    security_dieUnlessInternalReferer();
    security_dieOnInvalidCsrfToken();
    
    foreach (array('username','password') as $field) { // v2.52 remove leading and trailing whitespace (for usability, users accidentally add whitespace) 
      $_REQUEST[$field] = preg_replace("/^\s+|\s+$/s", '', @$_REQUEST[$field]);
    }

    // don't allow blank password
    if (empty($_REQUEST['username'])) { $errors .= t("Please specify a username.") ."<br>\n"; }    
    if (empty($_REQUEST['password'])) { $errors .= t("Please specify a password.") ."<br>\n"; }    
  
    loginCookie_set(@$_REQUEST['username'], getPasswordDigest(@$_REQUEST['password']));
    
  }

  // load current user
  $CURRENT_USER = getCurrentUser($loginExpired);

  // report any errors
  if      ($errors)                                    { 1; } // if we've already got errors just show those
  else if ($loginExpired)                              { $errors .= t("You've been logged out due to inactivity, please login again to continue." ."<br>\n"); }
  else if (!$CURRENT_USER && $action == 'loginSubmit') { 
    $errors .= t("Invalid username or password"); 
    
    // audit log entry
    auditLog_addEntry('Login: Failure', [ 'username' => $_REQUEST['username'] ]);
  }
  else if (@$CURRENT_USER['disabled'])                 { $errors .= t("Your account has been disabled." ."<br>\n"); }
  else if (@$CURRENT_USER['isExpired'])                { $errors .= t("Your account has expired.") ."<br>\n"; }
  if ($errors) {
    alert($errors);
    loginCookie_remove();         // if data in login cookie is invalid, remove login cookie so we don't keep checking it
    $CURRENT_USER = false;        // if login is invalid, clear user variable
    usleep(mt_rand(1000000, 3000000));  // sleep somewhere between 1-3 seconds to delay brute force attacks (random sleep time makes it so attacker can't assume slow response is failed password)
  }

  // if no logged in user
  if (!$CURRENT_USER) {

    // perform login screen maintenance actions - useful place to run common operations
    if (!$action) {
      createMissingSchemaTablesAndFields(); // create/update missing schemas, etc

      // show helpful messages
      if (!mysql_count('accounts')) { alert(t("There are no user accounts in the database.")); }
    }

    // show login screen if user not logged in
    showInterface('login.php');
    exit;
  }

  // if user logged in
  if ($CURRENT_USER) {
    // reset login cookie (to update lastAccess time used to track session expiry)
    loginCookie_set(@$CURRENT_USER['username'], getPasswordDigest(@$CURRENT_USER['password']));
    
    if ($action == 'loginSubmit') {
      // audit log entry
      auditLog_addEntry('Login: Success', [ 'username' => $_REQUEST['username'] ]);
    }
    
    // redirect to last url - on valid login
    $redirectUrl = @$_REQUEST['redirectUrl'];
    if ($redirectUrl) {
      redirectBrowserToURL($redirectUrl, true);
      exit;
    }
  }

}

//
function installIfNeeded() {
  global $SETTINGS, $APP, $TABLE_PREFIX;
  if (isInstalled()) { return; }   // skip if already installed

  // rename default files
  renameOrRemoveDefaultFiles();

  // error checking
  if ($SETTINGS['uploadDir'] && !is_dir($SETTINGS['uploadDir'])) {
    $absoluteUploadDir = absPath($SETTINGS['uploadDir'], SCRIPT_DIR);
    print "Error: Upload directory doesn't exist, please create it and reload this page:\n";
    print "<blockquote>$absoluteUploadDir</blockquote>\n";
    print "or manually update the 'uploadDir' value to 'uploads/' in: /data/" .SETTINGS_FILENAME. "<br>\n";
    exit;
  }

  // error checking
  checkFilePermissions();

  // display license
  if (@$_REQUEST['menu'] == 'license') { showInterface('license.php'); }

  // save
  if (@$_REQUEST['save']) {

    // error checking
    if (!$_REQUEST['agreeToLicense'])                                { alert("Please check 'I accept the terms of the License Agreement'<br>\n"); }
    if (!$_REQUEST['mysqlHostname'])                                 { alert("Please enter your 'MySQL Hostname'<br>\n"); }
    if (!$_REQUEST['mysqlDatabase'])                                 { alert("Please enter your 'MySQL Database'<br>\n"); }
    if (!$_REQUEST['mysqlUsername'])                                 { alert("Please enter your 'MySQL Username'<br>\n"); }
    if     (!$_REQUEST['mysqlTablePrefix'])                          { alert("Please enter your 'MySQL Table Prefix'<br>\n"); }
    elseif (preg_match("/[A-Z]/", $_REQUEST['mysqlTablePrefix']))    { alert("Value for 'MySQL Table Prefix' must be lowercase.<br>\n"); }
    elseif (!preg_match("/^[a-z]/i", $_REQUEST['mysqlTablePrefix'])) { alert("Value for 'MySQL Table Prefix' must start with a letter.<br>\n"); }
    elseif (!preg_match("/_$/", $_REQUEST['mysqlTablePrefix']))      { alert("Value for 'MySQL Table Prefix' must end in underscore.<br>\n"); }
    elseif (preg_match("/[^a-z0-9\_\-\.]/i", $_REQUEST['mysqlTablePrefix'])) { alert("Please enter a valid 'MySQL Table Prefix' (Example: cms_)<br>\n"); }
    
    // New Installation
    if (!@$_REQUEST['restoreFromBackup']) {
      if (!$_REQUEST['adminFullname'])                                 { alert("Please enter 'Admin Full Name'<br>\n"); }
      if     (!$_REQUEST['adminEmail'])                                { alert("Please enter 'Admin Email'<br>\n"); }
      elseif (!isValidEmail($_REQUEST['adminEmail']))                  { alert("Please enter a valid email for 'Admin Email' (Example: user@example.com)<br>\n"); }
      if (!$_REQUEST['adminUsername'])                                 { alert("Please enter 'Admin Username'<br>\n"); }

      $passwordErrors = getNewPasswordErrors($_REQUEST['adminPassword1'], $_REQUEST['adminPassword2'], $_REQUEST['adminUsername']); // v2.52
      if ($passwordErrors)  { alert( nl2br(htmlencode($passwordErrors)) ); }
    }

    // Restore from Backup
    if (@$_REQUEST['restoreFromBackup']) {
      if (!$_REQUEST['restore'])                                       { alert("Please select a backup file to restore<br>\n"); }
    }

    // Advanced - v2.53
    if (!@$_REQUEST['useCustomSettingsFile']) {
      if     (is_file(SETTINGS_DEV_FILEPATH)) { alert(t("You must select 'Use Custom Settings File' since a custom settings file for this domain already exists!"). "<br>\n"); }
      elseif (isDevServer())                  { alert("This is a development server, you must select 'Use Custom Settings File'.". "<br>\n"); }
    }
    if (@$_REQUEST['webPrefixUrl'] != '') {
      if  (!preg_match("|^(\w+:/)?/|", $_REQUEST['webPrefixUrl'])) { alert(t("Website Prefix URL must start with /") ."<br>\n"); }
      if  (preg_match("|/$|", $_REQUEST['webPrefixUrl']))          { alert(t("Website Prefix URL cannot end with /") ."<br>\n"); }
    }


    // update settings (not saved unless there are no errors)
    $SETTINGS['cookiePrefix']         = substr(md5(mt_rand()), 0, 5) . '_'; //v2.51 shortened prefix so it's easy to see full cookie names in browser cookie list
    $SETTINGS['adminEmail']           = @$SETTINGS['adminEmail']     ? $SETTINGS['adminEmail']     : $_REQUEST['adminEmail'];
    $SETTINGS['developerEmail']       = @$SETTINGS['developerEmail'] ? $SETTINGS['developerEmail'] : $_REQUEST['adminEmail'];
    $SETTINGS['licenseCompanyName']   = '';
    $SETTINGS['licenseDomainName']    = '';
    $SETTINGS['webRootDir']           = @$SETTINGS['webRootDir'] ? $SETTINGS['webRootDir'] : @$_SERVER['DOCUMENT_ROOT'];
    $SETTINGS['mysql']['hostname']    = $_REQUEST['mysqlHostname'];
    $SETTINGS['mysql']['database']    = $_REQUEST['mysqlDatabase'];
    $SETTINGS['mysql']['username']    = $_REQUEST['mysqlUsername'];
    $SETTINGS['mysql']['password']    = $_REQUEST['mysqlPassword'];
    $SETTINGS['mysql']['tablePrefix'] = $_REQUEST['mysqlTablePrefix'];
    $TABLE_PREFIX                     = $SETTINGS['mysql']['tablePrefix']; // update TABLE_PREFIX global as well.
    $SETTINGS['webPrefixUrl']         = $_REQUEST['webPrefixUrl'];

    // display errors
    if (alert()) {
      require "lib/menus/install.php";
      exit;
    }

    // connect to mysql
    $errors = connectToMySQL('returnErrors');
    if ($errors) {
      alert($errors);
      require "lib/menus/install.php";
      exit;
    }
    else { connectToMySQL(); }

    // create schema tables
    createMissingSchemaTablesAndFields();
    clearAlertsAndNotices(); // don't show "created table/field" alerts

    // New Installation: check if admin user already exists
    if (!@$_REQUEST['restoreFromBackup']) {
      $passwordHash  = getPasswordDigest($_REQUEST['adminPassword1']);
      $identicalUserExists = mysql_count('accounts', array('username' => $_REQUEST['adminUsername'], 'password' => $passwordHash, 'isAdmin' => '1'));
      if (!$identicalUserExists) { // if the don't exist, check if a user with the same username exists and show an error if they do
        $count = mysql_count('accounts', array('username' => $_REQUEST['adminUsername']));
        if (!$identicalUserExists && $count > 0) { alert("Admin username already exists, please choose another.<br>\n"); }
      }

      // create admin user
      if (!$identicalUserExists && !alert()) {
        mysqlStrictMode(false); // disable Mysql strict errors for when a field isn't defined below (can be caused when fields are added later)
        mysqli()->query("INSERT INTO `{$TABLE_PREFIX}accounts` SET
                          createdDate      = NOW(),
                          createdByUserNum = '0',
                          updatedDate      = NOW(),
                          updatedByUserNum = '0',
                          fullname         = '".mysql_escape( $_REQUEST['adminFullname'] )."', email    = '".mysql_escape( $_REQUEST['adminEmail'] )."',
                          username         = '".mysql_escape( $_REQUEST['adminUsername'] )."', password = '".mysql_escape($passwordHash)."',
                          disabled         = '0',
                          isAdmin          = '1',
                          expiresDate      = '0000-00-00 00:00:00',
                          neverExpires     = '1'") or alert("MySQL Error Creating Admin User:<br>\n". htmlencode(mysqli()->error) . "\n");

        // create accesslist entry
        mysqli()->query("INSERT INTO `{$TABLE_PREFIX}_accesslist` (userNum, tableName, accessLevel, maxRecords, randomSaveId)
                          VALUES (LAST_INSERT_ID(), 'all', '9', NULL, '1234567890')") or alert("MySQL Error Creating Admin Access List:<br>\n". htmlencode(mysqli()->error) . "\n");
      }
    }

    // Restore from Backup: Restore backup file
    if (@$_REQUEST['restoreFromBackup']) {
      $userCount = mysql_count('accounts');
      if ($userCount) {
        $userTable = $TABLE_PREFIX . 'accounts';
        $errorMessage  = sprintf("Can't restore from backup because it would overwrite the %s existing user accounts in the specified database location.<br>\n", $userCount);
        $errorMessage .= sprintf("Try changing the MySQL Database or Table Prefix to restore to a different location, or remove existing users from '%s'.<br>\n", $userTable);
        alert($errorMessage);
      }

      else {  // restore database
        $restoreDatabaseFilePath = $GLOBALS['BACKUP_DIR'] . @$_REQUEST['restore'];
        saveSettings( @$_REQUEST['useCustomSettingsFile'] );
        isInstalled(true);
        incrementalRestore($restoreDatabaseFilePath, true);
      }
    }

    // save settings
    if (!alert()) {
      saveSettings( @$_REQUEST['useCustomSettingsFile'] );
      isInstalled(true);                  // save installed status
      redirectBrowserToURL('?menu=home', true); // refresh page
      exit;
    }
  }

  // set defaults
  if (!array_key_exists('licenseDomainName', $_REQUEST)) { $_REQUEST['licenseDomainName'] = $_SERVER['HTTP_HOST']; }
  if (!array_key_exists('mysqlHostname',     $_REQUEST)) { $_REQUEST['mysqlHostname']     = $SETTINGS['mysql']['hostname']; }
  if (!array_key_exists('mysqlDatabase',     $_REQUEST)) { $_REQUEST['mysqlDatabase']     = $SETTINGS['mysql']['database']; }
  if (!array_key_exists('mysqlUsername',     $_REQUEST)) { $_REQUEST['mysqlUsername']     = $SETTINGS['mysql']['username']; }
  if (!array_key_exists('mysqlTablePrefix',  $_REQUEST)) { $_REQUEST['mysqlTablePrefix']  = $SETTINGS['mysql']['tablePrefix']; }

  // show form
  require "lib/menus/install.php";
  exit;
}

//
function upgradeIfNeeded() {
  global $SETTINGS, $APP;
  if (!isUpgradePending()) { return; }

  // rename default files
  renameOrRemoveDefaultFiles();

  // run upgrades
  require "lib/upgrade_functions.php";

  // log upgrade
  auditLog_addEntry('CMS Version Changed from ' . $SETTINGS['programVersion'] . ' to ' . $APP['version']);

  // update version in settings
  $SETTINGS['programVersion'] = $APP['version'];
  saveSettings();
}

// Returns program directory
// _getInstallPath();        // returns /var/htdocs/cms/
// _getInstallPath('test');  // returns /var/htdocs/cms/test
function _getInstallPath( $addSuffix = '' ) {
  // install path should be one up from this library file
  $moduleFilepath = __FILE__;
  $moduleDir      = dirname($moduleFilepath);
  $installPath    = dirname($moduleDir);

  // add suffix if needed
  if ($addSuffix) {
    $installPath .= "/$addSuffix";
  }

  //
  $installPath = preg_replace('/[\\\\\/]+/', '/', $installPath); // replace and collapse slashes
  return $installPath;
}

function checkFilePermissions() {
  global $PROGRAM_DIR, $APP, $SETTINGS;

  $dirs = [];
  $dirs[] = DATA_DIR;
  $dirs[] = DATA_DIR.'/schema';
  $dirs[] = DATA_DIR.'/schemaPresets';

  // get list of files
  $filepaths = [];
  foreach ($dirs as $dir) {
    foreach (scandir($dir) as $filename) {
      $filepath = "$dir/$filename";
      if (!is_file($filepath)) { continue; }
      if (!preg_match("/\.php$/", $filepath)) { continue; }
      $filepaths[] = $filepath;
    }
  }
  $filepaths[] = DATA_DIR.'/';
  $filepaths[] = SETTINGS_FILEPATH;
  $filepaths[] = $GLOBALS['BACKUP_DIR'];
  $filepaths[] = DATA_DIR.'/schema/';
  $filepaths[] = DATA_DIR.'/schemaPresets/';
  $filepaths[] = DATA_DIR.'/temp/';
  $filepaths[] = $SETTINGS['uploadDir'].'/';
  $filepaths[] = $SETTINGS['uploadDir'].'/thumb/';
  $filepaths[] = $SETTINGS['uploadDir'].'/thumb2/';
  $filepaths[] = $SETTINGS['uploadDir'].'/thumb3/';
  $filepaths[] = $SETTINGS['uploadDir'].'/thumb4/';
  sort($filepaths);

  // check permissions
  $notFoundErrors    = '';
  $notWritableErrors = '';
  foreach ($filepaths as $filepath) {
    $filepath = preg_replace("/\/+/", "/", $filepath); // collapse multiple slashes
    if      (!file_exists($filepath) && !@mkdir($filepath)) { $notFoundErrors    .= "<li>$filepath</li>\n"; }
    else if (!isPathWritable($filepath))                    { $notWritableErrors .= "<li>$filepath</li>\n"; }
  }
  if ($notFoundErrors) {
    print t("<b>Configuration Error</b> - Please make sure the following files and directories have been uploaded:") . "<br>\n";
    print "<ul>$notFoundErrors</ul>";
    exit;
  }
  if ($notWritableErrors) {
    print t("<b>Configuration Error</b> - Please make the following files and directories writable:") . "<br>\n";
    print "<ul>$notWritableErrors</ul>";
    if (isWindows()) { print t("Windows: Ask your 'server administrator' to give PHP write permissions to these files"); }
    else             { print t("Linux/Unix: To find the highest security supported by your server try these chmod permissions and use the first one that works: 755, 775, 777"); }
    exit;
  }

}



//
function allowSublicensing() {
  global $SETTINGS;
  $allowSublicencing = md5($SETTINGS['vendorName']) == 'b1e8e6f4faf2741fd0ca553c46c3fee3';

  return $allowSublicencing;
}

//
function showInterfaceError($alert) {
  $errors = alert($alert);
  if (isAjaxRequest()) { die($errors); }
  else {
    // show error alert, header, footer, and no body.
    include "lib/menus/header.php";
    include "lib/menus/footer.php";
    exit;
  }
}

//
function showInterface($body, $showHeaderAndFooter = "n/a") {
  if ($showHeaderAndFooter !== "n/a") {
    @trigger_error(__FUNCTION__ . "() should not be called with \$showHeaderAndFooter option by new adminUI architecture", E_USER_NOTICE);
  }

  if ($body == '') { $body = "home.php"; }
  include "lib/menus/$body";
  
  exit;
}

// noCacheUrlForCmsFile($pathFromCmsDir); // return filepath with ?modified_time on url to prevent caching
// Usage: echo noCacheUrlForCmsFile("3rdParty/clipone/css/main.css");
function noCacheUrlForCmsFile($pathFromCmsDir) {
  $cmsFilePath  = CMS_ASSETS_DIR .'/'. $pathFromCmsDir;
  $cmsFileUrl   = CMS_ASSETS_URL .'/'. $pathFromCmsDir;
  $modifiedTime = @filemtime($cmsFilePath);
  return "$cmsFileUrl?$modifiedTime";  // on file change browsers should no longer use cached versions
}

//
function clearAlertsAndNotices() {
  global $APP;
  $APP['alerts'] = '';
  $APP['notices'] = '';
}




/* Compose and output the Admin UI template from <!DOCTYPE html> to </html>.

### USAGE:
 adminUI([
   'PAGE_TITLE'       => [ t("Admin"), t("Section Editor") => "?menu=database", $schema['menuName'] ],
   'FORM'             => [ 'name' => 'searchForm', 'autocomplete' => 'off' ],
   'HIDDEN_FIELDS'    => [ [ 'name' => 'changeProductId', 'value' => '1' ] ],
   'BUTTONS'          => [
     [ 'name' => '_action=save', 'label' => 'Save',                                  ],
     [ 'name' => 'cancel',       'label' => 'Cancel',  'onclick' => 'editCancel();', ],
   ],
   'ADVANCED_ACTIONS' => ['Example: 123' => '?example=123', 'Example: 456' => '?example=456'],
   'CONTENT'          => $content,
 ]);

### EXAMPLES: ADDING BUTTONS WITH PLUGINS:
  // Add button on left
  addFilter('adminUI_args', function($adminUI_args, $tableName, $action) {
    if ($action == 'edit') {
      array_unshift($adminUI_args['BUTTONS'], [
        'label'   => t('Left Button'),
        'name'    => '_action=leftButton',
      ]);
    }
    return $adminUI_args;
  });

  // Add button second from left
  addFilter('adminUI_args', function($adminUI_args, $tableName, $action) {
    if ($action == 'edit') {
      array_splice($adminUI_args['BUTTONS'], 1, 0, [[
        'label'   => t('Left+1 Button'),
        'name'    => '_action=leftPlusOneButton',
      ]]);
    }
    return $adminUI_args;
  });

  // Add button on right
  addFilter('adminUI_args', function($adminUI_args, $tableName, $action) {
    if ($action == 'edit') {
      array_push($adminUI_args['BUTTONS'], [
        'label'   => t('Right Button'),
        'name'    => '_action=rightButton',
      ]);
    }
    return $adminUI_args;
  });

// =======================================
// Template for constructing adminUI pages
// =======================================

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Section Editor') => '?menu=database', $schema['menuName'] ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => '', 'label' => t(''), 'onclick' => '', ];

// advanced actions
$adminUI['ADVANCED_ACTIONS'] = [];
$adminUI['ADVANCED_ACTIONS']['Admin: Example'] = '';

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => '_defaultAction', 'value' => 'example', 'id' => 'example', ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  ...
<?php });

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture(function() { ?>
  ...
<?php });

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  ...
<?php });

// compose and output the page
adminUI($adminUI);

*/
function adminUI($args) {

  //
  adminUI_header($args);

  // note that adminUI_header() above triggers the 'adminUI_args' plugin hook, which can modify the args, so we should use the copy it stored, not this function's $args
  echo @$GLOBALS['ADMINUI_ARGS']['CONTENT'] . "\n";

  //
  adminUI_footer();
}

function adminUI_header($args) {
  if (@$GLOBALS['ADMINUI_ARGS']) { die("adminUI_header called a second time!"); }

  // allow plugins to modify adminUI() $args
  $action    = getRequestedAction('list');
  $tableName = @$GLOBALS['tableName'];
  $args      = applyFilters('adminUI_args', $args, $tableName, $action);

  // compose array-style placeholders into html
  $args = _adminUI_inputArraysToHTML($args);

  // store $args as a global so adminUI_footer() can access it
  $GLOBALS['ADMINUI_ARGS'] = $args;

  // show admin header (including html, head, body, nav menu, .main-content>.container, and optional alerts)
  include "lib/menus/header.php";

  ?>

    <?php echo @$args['PRE_FORM_HTML'] . "\n" ?>

    <?php echo @$args['FORM'] . "\n" ?>

      <?php if (!@$args['_SKIP_CSRF_TOKEN']): ?>
        <?php echo security_getHiddenCsrfTokenField() ?>
      <?php endif ?>

      <?php echo @$args['HIDDEN_FIELDS'] . "\n" ?>

      <!-- bugfix: hitting enter in textfield submits first submit button on form -->
      <input type="submit" style="width: 0px; height: 0px; position: absolute; border: none; padding: 0px">

      <?php disableAutocomplete('form-headers', 'force'); ?>


      <div class="panel panel-default"><!-- main panel -->

        <div class="panel-heading float-clear-children"><!-- main panel header -->
          <div><!-- page title -->
            <h3>
              <?php echo @$args['PAGE_TITLE'] . "\n" ?>
            </h3>
          </div><!-- /page title -->
          <div><!-- main panel header buttons -->
            <?php echo @$args['BUTTONS'] . "\n" ?>
          </div><!-- /main panel header buttons -->
        </div><!-- /main panel header -->


<?php if (!empty($args['DESCRIPTION'])): ?>
        <!-- section description -->
        <div class="modal-header float-clear-children" style="background-color: #EEE" >
          <div><?php echo @$args['DESCRIPTION'] . "\n" ?></div>
        </div>
<?php endif ?>
        
        <!-- main panel content -->
        <div class="panel-body">
  <?php
}

//
function adminUI_footer() {
  
  // restore global saved by adminUI_header()
  $args = $GLOBALS['ADMINUI_ARGS'];
  doAction('admin_footer_preButtons');
  ?>
        </div><!-- /main panel content -->
        
        <?php if (@$args['ADVANCED_ACTIONS'] || @$args['BUTTONS']): ?>
          <div class="panel-footer float-clear-children"><!-- main panel footer -->
            <div class="form-horizontal"><!-- advanced actions -->
              <?php echo @$args['ADVANCED_ACTIONS'] . "\n" ?>
            </div><!-- /advanced actions -->
            <div><!-- main panel footer buttons -->
              <?php echo @$args['BUTTONS'] . "\n" ?>
            </div><!-- /main panel footer buttons -->
          </div><!-- /main panel footer -->
        <?php endif ?>
      </div><!-- /main panel -->
    </form>
    
    <?php echo @$args['POST_FORM_HTML'] . "\n" ?>
    
  <?php
  
  // show admin footer (execute_seconds, closes tags opened by admin header above, /body, /html)
  include "lib/menus/footer.php";
  
  // display license and build info
  showBuildInfo();
}

// _adminUI_validateAttrs($attrs, ['label', 'name'], ['onclick']);
function _adminUI_validateAttrs($attrs, $requiredAttrs, $optionalAttrs) {
  $missingAttrs = array_diff($requiredAttrs, array_keys($attrs));
  $unknownAttrs = array_diff(array_keys($attrs), $requiredAttrs, $optionalAttrs);
  if ($missingAttrs) { trigger_error(htmlencodef(__FUNCTION__ . ': missing required attr "?"', reset($missingAttrs)), E_USER_ERROR); }
  if ($unknownAttrs) { trigger_error(htmlencodef(__FUNCTION__ . ': unknown attr "?"',          reset($unknownAttrs)), E_USER_ERROR); }
}

// $html = adminUI_button(['name' => 'name', 'label' => 'label']);
// See adminUI() header for examples on adding buttons with plugins
function adminUI_button($attrs) {
  _adminUI_validateAttrs($attrs, ['label'], ['name', 'value', 'style', 'class', 'onclick', 'type', 'btn-type', 'data-dismiss', 'tabindex', 'id']);

  // Special attributes, not used to construct tag
  $label    = $attrs['label'];  // label become the child text node of the <button>
  $btn_type = isset($attrs['btn-type']) ? "btn-".$attrs['btn-type'] : 'btn-primary';  // bootstrap button type
  unset($attrs['label'], $attrs['btn-type']);

  // Set defaults
  $attrs['class'] = isset($attrs['class']) ? "btn $btn_type {$attrs['class']}" : "btn $btn_type"; // primary, default
  $attrs['type']  = isset($attrs['type']) ? $attrs['type'] : 'submit';
  
  //
  $html = tag('button', $attrs, t($label), false) . "\n";
  return $html;
}

// $html = adminUI_checkbox(['name' => 'name', 'label' => 'label', 'checked' => true, 'inline' => false]);
// Value is always "1", and a hidden field is added to ensure a "0" is sent if unchecked
function adminUI_checkbox($attrs) {
  _adminUI_validateAttrs($attrs, ['name', 'label', 'checked'], ['id', 'inline', 'disabled']);

  // Set defaults
  $divClass = !empty($attrs['inline']) ? 'checkbox-inline' : 'checkbox'; 
  
  //
  $inputTag = tag('input', [
    'type'     => 'checkbox',
    'name'     => $attrs['name'],
    'value'    => '1',
    'checked'  => $attrs['checked'] ? 'checked' : null,
    'disabled' => empty($attrs['disabled']) ? '' : 'disabled',
  ]);
  $html = adminUI_hidden([ 'name' => $attrs['name'], 'value' => 0 ]);
  $html .= "<div class=\"$divClass\"><label>";
  $html .= $inputTag;
  $html .= $attrs['label'] ? $attrs['label'] : '&nbsp;';
  $html .= '</label></div>';
  return $html;
}

// $html = adminUI_hidden(['name' => 'name', 'value' => 'value']);
function adminUI_hidden($attrs) {
  _adminUI_validateAttrs($attrs, ['name', 'value'], ['id']);
  $attrs['type'] = 'hidden';
  return tag('input', $attrs, null, false);
}

// Display separator bar
/*
<?php echo adminUI_separator(t('Section Name')); // short form ?>
<?php echo adminUI_separator([  // long form
    'label' => t('Server Info'),
    'href'  => "?menu=admin&action=general#server-info",
    'id'    => "server-info",
    'type'  => '', // options: 'blank line', 'html', 'header bar' or blank for default
  ]);
?> */
function adminUI_separator($attrs = []) {
  if (!is_array($attrs)) { $attrs = [ 'label' => $attrs ]; }                         // support passing label as single argument string
  _adminUI_validateAttrs($attrs, ['label'], ['label','href','id','class', 'type']);  // error checking

  // set default separator type
  $attrs['type']  = isset($attrs['type']) ? $attrs['type'] : "header bar"; // default to "header bar" type
  
  // add CSS class base from the separator type
  $typeClass      = '';
  if ($attrs['type'] == "header bar"){
    $typeClass      = 'separator';
    $attrs['label'] = $attrs['label'] !== '' ? $attrs['label'] : '&nbsp;'; // default to &nbsp; to display even with empty string
  }
  elseif ($attrs['type'] == "blank line"){
    $typeClass      = 'separator-blank-line';
    $attrs['label'] = '&nbsp;';
  }
  elseif ($attrs['type'] == "html"){
    $typeClass      = 'separator-html';
    
    // add support for cmsb2-style HTML separator code
    if (preg_match('/\s*<tr/is', $attrs['label'], $matches)) {
      $attrs['label'] = tag('table', ['width' => '100%'], $attrs['label'], false);
    }
  }
  $attrs['class'] = isset($attrs['class']) ? ("$typeClass " . $attrs['class']) : $typeClass;
  
  //
  $html = $attrs['label'];
  
  // add label link
  if (isset($attrs['href'])) { $html = tag('a', ['href' => $attrs['href']], $html, false); } 
  
  // add show/hide button to collapsible header bar separator with 'separator-collapsible' class
  if ($attrs['type'] == "header bar" && preg_match("/separator-collapsible/", @$attrs['class'])){
    $html .= tag('i', ['class' => "separator-collapse-btn glyphicon glyphicon-chevron-up"], '');
  }
  
  $html = tag('div', [], $html, false); // wrap in inner div
  $html = tag('div', array_slice_keys($attrs, 'id', 'class'), $html, false); // wrap in outer div

  return $html;
}


// EXPERIMENTAL: instead of assigning to CONTENT and then calling adminUI(), you may call this function then output CONTENT and exit
function adminUI_contentFollows($args) {
  
  // FUTURE: $args error-checking should probably be done now, rather than waiting until shutdown
  
  ob_start();
  
  register_shutdown_function(function() use($args) {
    $args['CONTENT'] = ob_get_clean();
    adminUI($args);
  });
}


// compose array-style placeholders into html
function _adminUI_inputArraysToHTML($args) {
  
  // caller must supply PAGE_TITLE as an array
  if (is_array(@$args['PAGE_TITLE'])) {
    $pageTitleHtml = '';
    $labels = [];
    foreach ($args['PAGE_TITLE'] as $label => $url) {
      if (is_int($label)) { $label = $url; $url = null; } // allow [ 'foo' ] to be a shortcut for [ 'foo' => null ] (instead of [ 0 => 'foo' ])
      if ($pageTitleHtml) { $pageTitleHtml .= ' &gt; '; } // separate breadcrumbs with " > "
      // append $label (and optionally link it to $url)
      if ($url)           { $pageTitleHtml .= '<a href="' . htmlencode($url) . '">' . htmlencode($label) . '</a>'; }
      else                { $pageTitleHtml .= htmlencode($label);                                                  }
      // also keep track of just the labels
      $labels[] = $label;
    }
    $args['PAGE_TITLE'] = $pageTitleHtml;
    $args['_PAGE_TITLE_TEXT'] = implode(' - ', array_reverse($labels)); // used in <head><title>
  }
  elseif (@$args['PAGE_TITLE']) { die(__FUNCTION__ . ": PAGE_TITLE must be an array"); }

  // caller must supply HIDDEN_FIELDS as an array
  if (is_array(@$args['HIDDEN_FIELDS'])) {
    $hiddenFieldsHtml = '';
    foreach ($args['HIDDEN_FIELDS'] as $hiddenInfo) {
      $hiddenFieldsHtml .= adminUI_hidden($hiddenInfo);
    }
    $args['HIDDEN_FIELDS'] = $hiddenFieldsHtml;
  }
  elseif (@$args['HIDDEN_FIELDS']) { die(__FUNCTION__ . ": HIDDEN_FIELDS must be an array"); }

  // caller must supply buttons as an array
  if (is_array(@$args['BUTTONS'])) {
    $buttonsHtml = '';
    foreach ($args['BUTTONS'] as $buttonInfo) {
      if (!is_array($buttonInfo)) { dieAsCaller("adminUI buttons must be specified as an array of arrays!",2); }
      $buttonsHtml .= adminUI_button($buttonInfo) . ' ';
    }
    $args['BUTTONS'] = $buttonsHtml;
  }
  elseif (@$args['BUTTONS']) { die(__FUNCTION__ . ": BUTTONS must be an array"); }
  
  // caller must supply ADVANCED_ACTIONS as an array
  if (is_array(@$args['ADVANCED_ACTIONS'])) {
    ob_start();
    $labels = array_map('t', array_keys($args['ADVANCED_ACTIONS'])); // translate labels
    ?>
    <div class="form-inline">
      <select name="_advancedAction" id="advancedAction" class="form-control">
        <option value=''><?php et('Advanced Commands...') ?></option>
        <option value=''>&nbsp;</option>
        <?php echo getSelectOptions(null, array_values($args['ADVANCED_ACTIONS']), $labels); ?>
      </select>

      <?php
        echo adminUI_button([
          'label'   => t('go'),
          'type'    => 'submit',
          'name'    => '_advancedActionSubmit',
          'value'   => '1',
          'onclick' => 'return unbindAndOrSubmit()',
        ]);
      ?>
      <script>
        function unbindAndOrSubmit() {
          // check if advancedAction value is NOT empty
          // .. if true, unbind ajaxForm events (ONLY if ajaxFormUnbind is loaded) then submit the form
          // .. we're doing this so that we don't submit the form with regular actions like "edit", "list", and "save" which we want to override to do the advance action
          var isActionSelected = $("#advancedAction").val() != '';
          if (isActionSelected) {
            if ($.fn.ajaxFormUnbind) { $("form").ajaxFormUnbind(); } // ajaxFormUnbind is loaded by jqueryForm.js ONLY on pages we need it (edit and section editor)
            // v3.11 - Disabled, renamed _advancedActionSubmitButton to _advancedActionSubmit instead
            //$("<input type='hidden' name='_advancedActionSubmit' id='_advancedActionSubmit' value='1'>").appendTo("form"); // add hidden field _advancedActionSubmit with value = 1 to trigger the advanced action
            //$("button[name='_advancedActionSubmitButton']").parents("form:first").submit();
            return true;
          }
          return false; // do nothing if no Advanced Action selected
        }
      </script>

    </div>
    <?php
    $args['ADVANCED_ACTIONS'] = ob_get_clean();
  }
  elseif (@$args['ADVANCED_ACTIONS']) { die(__FUNCTION__ . ": ADVANCED_ACTIONS must be an array"); }
  
  // caller may customize <form> tag with an array of attrs
  $formAttrWhitelist = ['method', 'onsubmit', 'autocomplete', 'name', 'action', 'id', 'class', 'enctype'];
  $args['FORM'] = $args['FORM'] ?? [];
  _adminUI_validateAttrs($args['FORM'], [], $formAttrWhitelist);
  $args['FORM'] = array_merge(['method' => 'post', 'action' => '?'], $args['FORM']); // apply defaults
  $args['_SKIP_CSRF_TOKEN'] = (strtolower(@$args['FORM']['method']) === 'get'); // if <form method="get">, skip CSRF hidden fields
  $args['FORM'] = tag('form', $args['FORM'], null, false);

  return $args;
}

// show build and license info
function showBuildInfo() {
  global $APP, $SETTINGS;

  // display build info
  echo "<!--\n";

  echo "{$SETTINGS['programName']} v{$APP['version']} (Build: {$APP['build']})\n";
  echo "Licensed to: {$SETTINGS['licenseCompanyName']} ~ {$SETTINGS['licenseDomainName']}\n";

  echo "Execute time: " . showExecuteSeconds('return') . " seconds\n";
  echo "-->\n";

}


// returns bootstrap width class, ie: "col-xs-12 col-sm-6 col-md-4 col-lg-3"
// $sizeName: "tiny", "small", "medium", "large", or "full"
function getBootstrapFieldWidthClass($sizeName = null){
  $validSizes = ['tiny','small','medium','large','full'];
  if (!in_array($sizeName, $validSizes)) { $sizeName = 'medium'; } // default to ful size
  
  //
  $styleClassWidth = '';
  if ($sizeName == 'tiny')       { $styleClassWidth = 'col-xs-12 col-sm-4 col-md-2 col-lg-1'; }
  elseif ($sizeName == 'small')  { $styleClassWidth = 'col-xs-12 col-sm-6 col-md-4 col-lg-3'; }
  elseif ($sizeName == 'medium') { $styleClassWidth = 'col-xs-12 col-sm-8 col-md-6 col-lg-6'; }
  elseif ($sizeName == 'large')  { $styleClassWidth = 'col-xs-12 col-sm-10 col-md-8 col-lg-9'; }
  elseif ($sizeName == 'full')   { $styleClassWidth = 'col-xs-12 col-sm-12'; }
  
  return $styleClassWidth;
}


// attempt to disable browser autocomplete functionality.  Set $force to true to always output regardless of setting
/*
  <form method="post" <?php disableAutocomplete(); ?>>         // return: autocomplete='off'
  <input type="password" <?php disableAutocomplete(); ?>>      // return: autocomplete='off'
  <form ... ><?php disableAutocomplete('form-headers'); ?>     // return headers to interfere with browsers the ignore autocomplete="off"
*/
function disableAutocomplete($arg1 = '', $force = false) {
  // skip if disabled by Admin Settings (meaning autocomplete is ENABLED now)
  if (!$force && !$GLOBALS['SETTINGS']['advanced']['disableAutocomplete']) { return ''; }

  //
  $html = '';
  if     ($arg1 == '')     { return "autocomplete='off'"; }
  elseif ($arg1 == 'form-headers') {
    $html .= "\n<!-- Attempt to disable autocomplete for browsers that ignore HTML5 autocomplete standard -->";
    $html .= "<input type='password' style='display:none'>"; // originally from: http://crbug.com/352347#c11
    $html .= "<input type='password' style='display:none'>"; // 3 password fields seems to prevent built in password functionality from activating (in Chrome)
    $html .= "<input type='password' style='display:none' autocomplete='new-password'>"; // https://stackoverflow.com/a/15917221
    $html .= "\n";
  }
  else { dieAsCaller(__FUNCTION__. ": Unknown argument '" .htmlencode($arg1). "'!"); }

  echo $html;
}


//eof
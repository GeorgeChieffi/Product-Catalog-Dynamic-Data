<?php /*><h2>NOTE: If you are seeing this in your web browser it means PHP isn't setup correctly!<br>Ask your web host to enable PHP or to give you instructions on how to run PHP scripts. <!-- */

  // check PHP Version - do this first before running any other code to prevent returning errors and return instructions on old PHP versions
  // NOTE: When updating change code in both admin.php and /lib/init.php
  if (!defined('REQUIRED_PHP_VERSION')) { define('REQUIRED_PHP_VERSION', '7.3.0'); } // Supported PHP versions: http://php.net/supported-versions.php
  if (version_compare(phpversion(), REQUIRED_PHP_VERSION) < 0) {
    $errors  = "This program requires PHP v" .REQUIRED_PHP_VERSION. " or greater. You have PHP v" .phpversion(). " installed.<br>\n";
    $errors .= "Please ask your server administrator to upgrade PHP to a newer version.<br><br>\n";
    die($errors);
  }

  define('START_SESSION', true);
  define('IS_CMS_ADMIN', true);
  global $APP, $SETTINGS, $TABLE_PREFIX, $CURRENT_USER;

  require_once "lib/init.php";
  require_once "lib/login_functions.php";
  require_once "lib/user_functions.php";
  require_once "lib/admin_functions.php";

  ### Security: Disable external referers and form submissions
  $securityErrors  = ''; 
  $securityErrors .= security_disablePostWithoutInternalReferer();
  $securityErrors .= security_disableExternalReferers();
  $securityErrors .= security_warnOnInputWithNoReferer();
  alert($securityErrors);
  
  ### pre-login actions
  $menu = isset($_REQUEST['menu']) ? $_REQUEST['menu'] : '';
  if ($menu == "forgotPassword")      { forgotPassword(); }
  if ($menu == "resetPassword")       { resetPassword(); }
  if ($menu == 'license')             { showInterface('license.php'); }

  ### Backup/restore functions
  if ($menu == '_incrementalRestore') { incrementalRestore(); }
  _incrementalDbAction_showInProgressError_HTTP503(true); // show error if backup/restore in progress
  
  ### Login
  doAction('admin_prelogin');
  adminLoginMenu();
  doAction('admin_postlogin');
  
  ### Dispatch actions
  if ($menu == 'home' || !$menu) { showInterface('home.php'); }
  else                           { include "lib/menus/default/actionHandler.php"; }

// eof
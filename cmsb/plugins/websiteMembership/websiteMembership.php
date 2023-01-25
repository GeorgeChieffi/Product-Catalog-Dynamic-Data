<?php
/*
Plugin Name: Website Membership
Description: Website membership functions for user sign-up, password reminder, login, user specific content, and login only content
Version: 1.14
Requires at least: 3.12
Required System Plugin: true
*/

// UPDATE THESE VALUES
// NOTE: PREFIX_URL is for sites that use: Admin > General > Website Prefix URL
$GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']  = PREFIX_URL.'/user-login.php';                 // url to login form
$GLOBALS['WEBSITE_LOGIN_SIGNUP_URL']      = PREFIX_URL.'/user-signup.php';                // signup url linked to from the login page
$GLOBALS['WEBSITE_LOGIN_REMINDER_URL']    = PREFIX_URL.'/user-password-request.php';      // password reminder url linked to from the login page
$GLOBALS['WEBSITE_LOGIN_PROFILE_URL']     = PREFIX_URL.'/user-profile.php';               // url to "edit my profile" page
$GLOBALS['WEBSITE_LOGIN_REQUIRED_FIELDS'] = array('agree_tos','agree_legal');             // if user is logged in and any of these fields exist and are blank (or zero) they will be redirected to the profile url with ?missing_fields=fieldname1,fieldname2,etc set

// After login, user gets redirected to the last page they were on (if defined), the url below, or to /
$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']  = PREFIX_URL.'/';

// After logoff, user gets redirected to the last page they were on (if defined), the url below, or to /
$GLOBALS['WEBSITE_LOGIN_POST_LOGOFF_URL'] = PREFIX_URL.'/';

$GLOBALS['WSM_ACCOUNTS_TABLE']            = 'accounts';  // set the accounts table WSM uses, change this to store website accounts in a different table
$GLOBALS['WSM_SEPARATE_LOGIN']            = false;        // set this to allow you to login to the website and CMS simultaneously as different users

// DON'T UPDATE ANYTHING BELOW THIS LINE

$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']  = true;
$GLOBALS['WEBSITE_MEMBERSHIP_VERSION'] = '1.12';

// plugin actions
addAction('viewer_postinit',           '_websiteLogin_init',                    null, 0);
addAction('record_presave',            'wsm_customAccountsTable_hashPasswords', null, 3);

// add links to plugin menu
pluginAction_addHandlerAndLink('Email Templates', 'wsm_plugin_menu_redirect_templates', 'admins');
pluginAction_addHandlerAndLink('Code Generator', 'wsm_plugin_menu_redirect_generator', 'admins');

// add menu to code generator
addGenerator('wsm_codeGenerator',  t("Website Membership"), t("Create membership pages such as login, signup, reset password, profile, etc"));

// add email templates to CMS
addAction('emailTemplate_addDefaults', 'wsm_emailTemplates_install',              null, 0);

// uncomment this to automatically start a session with every page load
// if (!defined('START_SESSION')) { define('START_SESSION', true); }

//
function _websiteLogin_init() {
  if (defined('IS_CMS_ADMIN')) { return; } // only run this form website viewers, not CMS admin pages
  if (inCLI()) { return; } // don't run for command line scripts

  // load login functions
  require_once SCRIPT_DIR . "/lib/login_functions.php";
  if (@$GLOBALS['WSM_ACCOUNTS_TABLE']) { accountsTable($GLOBALS['WSM_ACCOUNTS_TABLE']); }
  if (@$GLOBALS['WSM_SEPARATE_LOGIN']) { cookiePrefix('web'); } // use different prefix for login cookies

  // create accounts table if needed
  wsm_customAccountsTable_create();

  // load current user
  $GLOBALS['CURRENT_USER'] = _websiteLogin_getCurrentUser(); //

  //
  doAction('_websiteLogin_init.pre_actionHandler');

  // perform website login actions
  if (@$_REQUEST['action'] == 'logoff')  { _websiteLogin_logoff(); }
  if (@$_REQUEST['action'] == 'login')   { _websiteLogin_login(); }

  // check for required profile fields
  $accountsSchema = loadSchema(accountsTable());
  if ($GLOBALS['CURRENT_USER'] && !@$GLOBALS['WEBSITE_MEMBERSHIP_PROFILE_PAGE']) {
    $missingFields = array();
    foreach ($GLOBALS['WEBSITE_LOGIN_REQUIRED_FIELDS'] as $field) {
      $isCheckbox = (@$accountsSchema[$field]['type'] == 'checkbox');
      if (!array_key_exists($field, $GLOBALS['CURRENT_USER']))   { continue; } // if field isn't defined in database, skip it
      if     ($isCheckbox && $GLOBALS['CURRENT_USER'][$field] != '0') { continue; } // checkboxs default to 0, so require them to have 1 (checked)
      elseif (!$isCheckbox && $GLOBALS['CURRENT_USER'][$field] != '') { continue; } // for all other fields, skip unless they are blank
      $missingFields[] = $field;
    }
    if ($missingFields) {
      $url = $GLOBALS['WEBSITE_LOGIN_PROFILE_URL'] . "?missing_fields=" . implode(',', $missingFields);
      redirectBrowserToURL($url);
      exit;
    }
  }

}

// if (!$CURRENT_USER) { websiteLogin_redirectToLogin(); }
function websiteLogin_redirectToLogin($returnAfterLogin = true) {

  // remember page they're trying to access
  if ($returnAfterLogin) { setPrefixedCookie('lastUrl', thisPageUrl()); }

  // redirect to login
  $loginUrl = $GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL'] . "?loginRequired=1";
  redirectBrowserToURL($loginUrl);
  exit;
}
// if (!$CURRENT_USER['isAdmin']) { adminFailed_redirectToLogin(); }
function adminFailed_redirectToLogin($returnAfterLogin = true) {

  // remember page they're trying to access
  if ($returnAfterLogin) { setPrefixedCookie('lastUrl', thisPageUrl()); }

  // redirect to login
  $loginUrl = $GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL'] . "?adminLoginRequired=1";
  redirectBrowserToURL($loginUrl);
  exit;
}


// be sure to set password or user will be logged out.  The $password argument isn't required by the function for legacy support
function websiteLogin_setLoginTo($username, $password = null) {
  loginCookie_set($username, getPasswordDigest($password));
}

// load user from database with cookie login details
function _websiteLogin_getCurrentUser() {
  global $CURRENT_USER;

  // load current user
  $CURRENT_USER = getCurrentUser();
  if (!$CURRENT_USER) { return false; }

  // error checking - logoff expired and disabled users
  if (@$CURRENT_USER['disabled'])    { alert(t("Your account has been disabled.")); }
//if (@$CURRENT_USER['isExpired'])   { alert(t("Your account has expired.")); } // future: maybe we should add an expires url where user gets redirect when their account has expired? For subscription renewal
  if (alert()) {
    loginCookie_remove();
    return false;
  }

  //
  return $CURRENT_USER;
}

//
function _websiteLogin_login() {
  global $CURRENT_USER;

  // attempt login?
  if (@$_REQUEST['username'] && @$_REQUEST['password']) {
    foreach (array('username','password') as $field) { // v1.10 remove leading and trailing whitespace
      $_REQUEST[$field] = preg_replace("/^\s+|\s+$/s", '', @$_REQUEST[$field]);
    }

    // get a list of accounts matching password and either email or username (we allow login with either)
    // ... checking for valid password ensure we get error messages from getCurrentUser() that are for a valid username/password combination (eg: expired/disabled)
    $accountsTable    = $GLOBALS['TABLE_PREFIX'] . accountsTable();
    $query            = mysql_escapef("SELECT `username`, `password` FROM `$accountsTable`
                                        WHERE password IN (?,?) AND ? IN (`username`,`email`)",
                                      $_REQUEST['password'],
                                      getPasswordDigest($_REQUEST['password']),
                                      $_REQUEST['username']);
    $userRecords      = mysql_select_query($query);

    //
    foreach ($userRecords as $userRecord) {
      if (!isPasswordDigest($userRecord['password'])) { encryptAllPasswords(); } // encrypt ALL passwords if password is plaintext

      loginCookie_set($userRecord['username'], getPasswordDigest(@$_REQUEST['password']));
      $CURRENT_USER = _websiteLogin_getCurrentUser();
      if (alert()) { return; }
    }
  }

  // error checking
  if      (!@$_REQUEST['username']) { alert(t("Please enter a username!"). "<br>\n"); }
  else if (!@$_REQUEST['password']) { alert(t("Please enter a password!"). "<br>\n"); }
  else if (!$CURRENT_USER)          { alert(t("Invalid username or password!"). "<br>\n"); }
  if (function_exists('wsm_login_errorchecking')) { alert(wsm_login_errorchecking($CURRENT_USER)); }
  if (alert()) {
    return;
  }

  // clear form values
  $_REQUEST['username'] = '';
  $_REQUEST['password'] = '';

  // redirect on success
  $postLoginUrl = getPrefixedCookie('lastUrl') ?: $GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL'] ?: '/';
  removePrefixedCookie('lastUrl');

  doAction('wsm_loginSuccess');

  redirectBrowserToURL($postLoginUrl);
  exit;
}

// remove login cookies
function _websiteLogin_logoff() {

  // get logoff url
  $currentPageUrl = (@$_REQUEST['action'] == 'logoff') ? thisPageUrl(array('action' => null)) : thisPageUrl(); // remove action=logoff to prevent redirect loops
  $logoffUrl = coalesce(@$_SERVER['HTTP_REFERER'], $GLOBALS['WEBSITE_LOGIN_POST_LOGOFF_URL'],  $currentPageUrl, '/');

  // logoff and redirect
  user_logoff($logoffUrl);
  exit;
}

//
function websiteLogin_pluginDir() {
  return __DIR__;
}


//
function wsm_generatePassword() {
  $password = substr(md5(uniqid(mt_rand(), true)), 15); // example output: c5560251ef0b3eef9
  return $password;
}

// list($mailErrors, $fromEmail) = wsm_sendSignupEmail($userNum, $passwordText);
// if ($mailErrors) { alert("Mail Error: $mailErrors"); }
function wsm_sendSignupEmail($userNum, $passwordText) {
  $user         = mysql_get(accountsTable(), $userNum);

  $placeholders = array_keys_prefix('user.', $user);
  $placeholders['user.password'] = $passwordText;
  $placeholders['loginUrl']      = realUrl($GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']);
  unset( $placeholders['user._tableName'] );

  $emailHeaders = emailTemplate_loadFromDB(array(
    'template_id'  => 'USER-SIGNUP',
    'placeholders' => $placeholders,
  ));
  $mailErrors   = sendMessage($emailHeaders);

  //
  return array($mailErrors, $emailHeaders['from']);
}

// Encode password on save in for custom accounts table
function wsm_customAccountsTable_hashPasswords($tableName, $isNewRecord, $oldRecord) {
  global $SETTINGS;

  // skip for all but custom-accounts tables
  if (!$GLOBALS['WSM_ACCOUNTS_TABLE'])              { return; } // skip if no custom accounts table set
  if ($GLOBALS['WSM_ACCOUNTS_TABLE'] == 'accounts') { return; } // skip if using default 'accounts' table
  if ($GLOBALS['WSM_ACCOUNTS_TABLE'] != $tableName) { return; } // skip if the table being saved isn't the custom-accounts table

  // encrypt password being submitted in form input
  $_REQUEST['password'] = getPasswordDigest(@$_REQUEST['password']);
}



// if custom accounts table doesn't exist, create it.
function wsm_customAccountsTable_create() {
  // check for custom accounts table
  if (accountsTable() == 'accounts') { return; }

  // check if schema exists
  $schemaPath = realpath(DATA_DIR . '/schema') . "/" .accountsTable(). ".ini.php";
  if (file_exists($schemaPath)) { return; }

  // create schema
  $schema = array(
    '_detailPage' => '',
    '_disableAdd' => '0',
    '_disableErase' => '0',
    '_disableModify' => '0',
    '_disablePreview' => '0',
    '_disableView' => '1',
    '_filenameFields' => '',
    '_hideRecordsFromDisabledAccounts' => '0',
    '_indent' => '0',
    '_listPage' => '',
    '_maxRecords' => '',
    '_maxRecordsPerUser' => '',
    '_perPageDefault' => '100',
    '_previewPage' => '',
    '_requiredPlugins' => '',
    'listPageFields' => 'fullname, username, email, password, lastLoginDate',
    'listPageOrder' => 'fullname, username',
    'listPageSearchFields' => '_all_',
    'menuHidden' => '0',
    'menuName' => 'Website Users',
    'menuOrder' => '-1',
    'menuType' => 'multi',
    'tableHidden' => '0',
    'num'              => array('order'=>'1','isSystemField'=>'1'),
    'createdDate'      => array('order'=>'2','type'=>'none','label'=>'Created','isSystemField'=>'1'),
    'createdByUserNum' => array('order'=>'3','type'=>'none','label'=>'Created By','isSystemField'=>'1'),
    'updatedDate'      => array('order'=>'4','type'=>'none','label'=>'Last Updated','isSystemField'=>'1'),
    'updatedByUserNum' => array('order'=>'5','type'=>'none','label'=>'Last Updated By','isSystemField'=>'1'),
    'fullname'         => array('order'=>68,'label'=>'Full Name','type'=>'textfield','defaultValue'=>'','fieldWidth'=>'','isPasswordField'=>'0','isRequired'=>'1','isUnique'=>'0','minLength'=>'','maxLength'=>'','charsetRule'=>'','charset'=>''),
    'email'            => array('order'=>69,'label'=>'Email','type'=>'textfield','defaultValue'=>'','isPasswordField'=>'0','isRequired'=>'1','isUnique'=>'1','minLength'=>'','maxLength'=>'','charsetRule'=>'','charset'=>'','isSystemField'=>'1'),
    'username'         => array('order'=>70,'label'=>'Username','type'=>'textfield','defaultValue'=>'','isPasswordField'=>'0','isRequired'=>'1','isUnique'=>'1','minLength'=>'','maxLength'=>'','charsetRule'=>'','charset'=>'','isSystemField'=>'1'),
    'password'         => array('order'=>72,'label'=>'Password','type'=>'textfield','defaultValue'=>'','isPasswordField'=>'1','isRequired'=>'1','isUnique'=>'0','minLength'=>'','maxLength'=>'','charsetRule'=>'','charset'=>'','isSystemField'=>'1'),
    '__separator002__' => array('order'=>73,'type'=>'separator','isSystemField'=>'0','separatorType'=>'blank line'),
    'lastLoginDate'    => array('order'=>74,'label'=>'Last Login','type'=>'date','fieldPrefix'=>'','description'=>'','isRequired'=>'0','isUnique'=>'0','defaultDate'=>'none','defaultDateString'=>'2011-01-01 00:00:00','showTime'=>'1','showSeconds'=>'1','use24HourFormat'=>'0','yearRangeStart'=>'2010','yearRangeEnd'=>'2020'),
    'disabled'         => array('order'=>79,'label'=>'Disable Access','type'=>'checkbox','checkedByDefault'=>'0','description'=>'Disable account (user won\'t be able to login)','isSystemField'=>'1'),
  );
  saveSchema(accountsTable(), $schema);

  // display message and create tables
  createMissingSchemaTablesAndFields();  // create mysql tables

}


//
function wsm_plugin_menu_redirect_templates() {
  redirectBrowserToURL('?menu=_email_templates');
}

//
function wsm_plugin_menu_redirect_generator() {
  redirectBrowserToURL('?menu=_codeGenerator&_generator=wsm_codeGenerator');
}

//
function wsm_codeGenerator() {
  require_once("wsm_codeGenerator.php"); wsm_codeGenerator_showPage();
  exit;
}

//
function wsm_emailTemplates_install() {
  ### NOTE: Make sure this file (admin_functions.php) is saved as UTF-8 or chars with accents may not get saved to MySQL on insert


  // USER-PASSWORD-RESET
  emailTemplate_addToDB(array(
    'template_id'  => "USER-PASSWORD-RESET",
    'description'  => "Website users get this email when they request a password reset",
    'placeholders' => array('user.username','user.email','loginUrl','resetUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#server.http_host# Password Reset",
    'html'         => <<<__HTML__
<p>Hi #user.username#,</p>
<p>You requested a password reset for #server.http_host#.</p>
<p>To reset your password click this link:<br><a href="#resetUrl#">#resetUrl#</a></p>
<p>This request was made from IP address: #server.remote_addr#</p>
__HTML__
  ));


  // USER-SIGNUP
  emailTemplate_addToDB(array(
    'template_id'  => "USER-SIGNUP",
    'description'  => "Website users receive this email when they sign up with their password.",
    'placeholders' => array('user.username','user.email','user.password','loginUrl','resetUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#server.http_host# Account Details",
    'html'         => <<<__HTML__
<p>Hi #user.username#,</p>
<p>Thanks for signing up to #server.http_host#.</p>
<p>Your username is: #user.username#<br>Your password is: #user.password#</p>
<p>Please click here to login:<br><a href="#loginUrl#">#loginUrl#</a></p>
<p>Thanks!</p>
__HTML__
  ));

}
?>

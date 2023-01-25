<?php // Website Membership Code Generator Functions

//
function wsm_codeGenerator_showPage() {

  // Support default "Go Back >>" button
  if (array_key_exists('_showCode', $_REQUEST)) { redirectBrowserToURL('?menu=_codeGenerator'); }

  //
  $GENERATOR_FILENAMES_TO_DESCRIPTIONS = array(
    'user-login.php'            => "Login Page",
    'user-profile.php'          => "Edit Profile",
    'user-signup.php'           => "Create Account",
    'user-password-request.php' => "Forgot Password",

  );

  // show header
  $function = "_wsm_codeGenerator";
  $menuName = t("Website Membership");
  echo cg2_header($function, $menuName);

  // show page menu
  print "<ul style='padding-top: 0; padding-bottom: 0;'>\n";
  foreach ($GENERATOR_FILENAMES_TO_DESCRIPTIONS as $filename => $description) {
    $url = thisPageUrl(array('filename' => $filename));
    print  "  <li><a href='$url'>" .htmlencode($description).  "</a></li>\n";
  }
  print "</ul>\n";

  // show page
  $filename = @$_REQUEST['filename'];
  if ($filename && @$GENERATOR_FILENAMES_TO_DESCRIPTIONS[$filename]) {

    // show separator bar
    print adminUI_separator(t('Source Code'));

    // get function name
    $getCodeFunction = "wsmcode_" . preg_replace("/[^a-z\_]+/s", "_", $filename);
    if (!function_exists($getCodeFunction)) { die("Function $getCodeFunction doesn't exist!"); }

    // show instructions
    $instructions   = array(); // show as bullet points
    $instructions[] = t(sprintf("Save this code as <b>%s</b> (or choose your own name)", $filename));
    #$instructions[] = sprintf('Update the <a href="%s">Archive Url</a> under Newsletter Settings', '?menu=_nlb_settings');
    $filenameSuffix = 'list'; // eg: tablename-FILENAMESUFFIX.php
    cg2_showCode(null, null, $instructions, null, $getCodeFunction());
    exit;
  }

  // show footer (cg2_showCode sends it automatically)
  echo cg2_footer();
  exit;
}

//
function wsmcode_user_login_php() {
  // generate code
  ob_start();
?><#php
  <?php cg2_code_loadLibraries(); ?>
  if (!@$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']) { die("You must activate the Website Membership plugin before you can access this page."); }

  // error checking
  $errorsAndAlerts = alert();
  if (@$CURRENT_USER)                                { $errorsAndAlerts .= "You are already logged in! <a href='{$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']}'>Click here to continue</a> or <a href='?action=logoff'>Logoff</a>.<br>\n"; }
  if (!$CURRENT_USER && @$_REQUEST['loginRequired']) { $errorsAndAlerts .= "Please login to continue.<br>\n"; }

  // save url of referring page so we can redirect user there after login
  // if (!getPrefixedCookie('lastUrl')) { setPrefixedCookie('lastUrl', @$_SERVER['HTTP_REFERER'] ); }

#><?php cg2_code_header(); ?>
<h1>Sample User Login Form</h1>

<!-- USER LOGIN FORM -->
  <#php if (@$errorsAndAlerts): #>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <#php echo $errorsAndAlerts; #><br>
    </div>
  <#php endif #>

<#php if (!@$CURRENT_USER): #>
  <form action="?" method="post">
  <input type="hidden" name="action" value="login">

  <table border="0" cellspacing="0" cellpadding="2">
   <tr>
    <td>Email or Username</td>
    <td><input type="text" name="username" value="<#php echo htmlencode(@$_REQUEST['username']); #>" size="30" autocomplete="off"></td>
   </tr>
   <tr>
    <td>Password</td>
    <td><input type="password" name="password" value="<#php echo htmlencode(@$_REQUEST['password']); #>" size="30" autocomplete="off"></td>
   </tr>

   <tr>
    <td colspan="2" align="center">
      <br><input type="submit" name="submit" value="Login">
      <a href="<#php echo $GLOBALS['WEBSITE_LOGIN_SIGNUP_URL'] #>">or sign-up</a><br><br>
      
      <#php if (function_exists('fbl_login')): // NOTE: This feature requires the Facebook Login v2+ plugin! #>
        <#php fbl_loginForm_javascript(); #>
        <a href="#" onclick="fbl_login(); return false;">Login with Facebook</a><br><br>
      <#php endif; #>
      
      <#php if (@$GLOBALS['TWITTERAPI_ENABLE_LOGIN']): #>
        <a href="<#php echo twitterLogin_getTwitterLoginUrl();#>"
        onclick="<#php echo twitterLogin_getTwitterLoginUrl_popupOnClick(); #>">Login with Twitter</a><br><br>
      <#php endif #>

      <a href="<#php echo $GLOBALS['WEBSITE_LOGIN_REMINDER_URL'] #>">Forgot your password?</a>

    </td>
   </tr>
  </table>
  </form>
<#php endif #>
<!-- /USER LOGIN FORM -->
<?php cg2_code_footer(); ?>

<?php
  // return code
  $code = ob_get_clean();
  return $code;
}


//
function wsmcode_user_password_request_php() {
  // generate code
  ob_start();
?><#php
  <?php cg2_code_loadLibraries(); ?>
  
  //
  $errorsAndAlerts = alert(); // load any predefined alerts or errors
  $showForm        = true;  
  $isResetPage     = isset($_REQUEST['userNum']) || isset($_REQUEST['resetCode']); // request password reset email
  $isRequestPage   = !$isResetPage; // reset password (with link from email)

  // error checking
  if (!@$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']) { die("You must activate the Website Membership plugin before you can access this page."); }
  if (!empty($CURRENT_USER)) {
    $errorsAndAlerts = "You are already logged in! <a href='{$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']}'>Click here to continue</a> or <a href='?action=logoff'>Logoff</a>.";
    $showForm = false;
  }

  // START: REQUEST FORGOT PASSWORD EMAIL - email a password reset link to user
  if ($isRequestPage && isset($_POST['submitForm'])) {

    // error checking
    if (empty($_REQUEST['usernameOrEmail'])) { $errorsAndAlerts .= "No username or email specified!\n"; }
    if (!$errorsAndAlerts) {
      $where = mysql_escapef("? IN (`username`,`email`)", $_REQUEST['usernameOrEmail']);
      $user  = mysql_get(accountsTable(), null, $where);
      if (!$user)                            { $errorsAndAlerts .= "No matching username or email was found!\n"; }
      elseif (!isValidEmail($user['email'])) { $errorsAndAlerts .= "User doesn't have a valid email specified!\n"; }
    }

    // send password reset email
    if (!$errorsAndAlerts && !empty($user)) {
      $emailHeaders = emailTemplate_loadFromDB(array(
        'template_id'  => 'USER-PASSWORD-RESET',
        'placeholders' => array(
          'user.username' => $user['username'],
          'user.email'    => $user['email'],
          'loginUrl'      => realUrl($GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']),
          'resetUrl'      => realUrl($GLOBALS['WEBSITE_LOGIN_REMINDER_URL'] . "?userNum={$user['num']}&resetCode=" . _generatePasswordResetCode( $user['num'] )),
      )));
      $mailErrors = sendMessage($emailHeaders);
      if ($mailErrors) { $errorsAndAlerts .= "Mail Error: $mailErrors"; }

      //
      $errorsAndAlerts .= "Thanks, we've emailed you instructions on resetting your password.<br><br>
      If you don't receive an email within a few minutes check your spam filter for messages from {$emailHeaders['from']}\n";
      $_REQUEST = array(); // clear form fields
      $showForm = false;
    }
  }
  // END: REQUEST FORGOT PASSWORD EMAIL 


  // START: RESET PASSWORD FORM - using link from password reset email
  if ($isResetPage) {

    // error checking
    if (empty($_REQUEST['userNum']))   { die("No 'userNum' value specified!"); }
    if (empty($_REQUEST['resetCode'])) { die("No 'resetCode' value specified!"); }
    $isValidResetCode = _isValidPasswordResetCode($_REQUEST['userNum'], $_REQUEST['resetCode']);
    if (!$isValidResetCode) {
      $errorsAndAlerts .= t("Password reset code has expired or is not valid. Try resetting your password again.");
      $showForm = false;
    }

    // load user details
    $user = mysql_get(accountsTable(), $_REQUEST['userNum']);

    // reset password
    if (isset($_POST['submitForm']) && $isValidResetCode) {
      // error checking
      $errorsAndAlerts .= getNewPasswordErrors(@$_REQUEST['password'], @$_REQUEST['password:again'], $user['username']);

      // update password
      if (!$errorsAndAlerts) {
        $newPassword = getPasswordDigest($_REQUEST['password']);
        mysql_update(accountsTable(), $user['num'], null, array('password' => $newPassword));

        // show message
        $errorsAndAlerts .= t('Password updated!');
        $_REQUEST = array(); // clear form fields
        $showForm = false;
      }
    }
  }
  // END: RESET PASSWORD FORM

#><?php cg2_code_header(); ?>

  <!-- PAGE TITLE -->
  <#php if ($isRequestPage): #> <h1>Forgot your password?</h1> <#php endif #>
  <#php if ($isResetPage):   #> <h1>Reset your Password</h1> <#php endif #>

 
  <!-- ERRORS & ALERTS -->
  <#php if (@$errorsAndAlerts): #>
    <div style="color: #C00; font-weight: bold;"><#php echo $errorsAndAlerts; #></div>
  <#php endif #>


  <!-- START: REQUEST FORGOT PASSWORD EMAIL -->
  <#php if ($isRequestPage && $showForm): #>
    <p>Just enter your username or email address to reset your password.</p>

    <form action="?" method="post">
      <input type="hidden" name="submitForm" value="1">
      Email or username:
      <input type="text" name="usernameOrEmail" value="<#php echo htmlencode(@$_REQUEST['usernameOrEmail']) #>" size="20" autocomplete="off" autofocus>
      <input type="submit" name="submit" value="Lookup">
    </form>
  <#php endif #>
  <!-- END: REQUEST FORGOT PASSWORD EMAIL -->
    
  
  <!-- START: RESET PASSWORD FORM -->
  <#php if ($isResetPage && $showForm): #>
    <form method="post" action="?">
    <input type="hidden" name="userNum"    value="<#php echo htmlencode(@$_REQUEST['userNum']); #>">
    <input type="hidden" name="resetCode"  value="<#php echo htmlencode(@$_REQUEST['resetCode']); #>">
    <input type="hidden" name="submitForm" value="1">
  
    <table border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td><#php et('Username') #></td>
        <td style="padding: 10px 0px"><#php echo htmlencode( $user['username'] ); #></td>
      </tr>
      <tr>
        <td><#php et('New Password') #></td>
        <td><input class="text-input" type="password" name="password"  value="<#php echo htmlencode(@$_REQUEST['password']) #>" autocomplete="off"></td>
      </tr>
      <tr>
        <td><#php et('New Password (again)') #> &nbsp;</td>
        <td><input class="text-input" type="password" name="password:again"  value="<#php echo htmlencode(@$_REQUEST['password:again']) #>" autocomplete="off"></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td><input class="button" type="submit" name="send" value="<#php et('Update') #>"></td>
      </tr>
    </table>
    </form>
    <#php endif #>
    <!-- /RESET PASSWORD FORM -->
    
    
  <!-- FOOTER -->
  <br>
  <a href="<#php echo $GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL'] #>">&lt;&lt; Login Page</a>
  
<?php cg2_code_footer(); ?>

<?php
  // return code
  $code = ob_get_clean();
  return $code;
}

//
function wsmcode_user_signup_php() {
  // generate code
  ob_start();
?><#php
  <?php cg2_code_loadLibraries(); ?>
  if (!@$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']) { die("You must activate the Website Membership plugin before you can access this page."); }

  //
  $useUsernames   = true; // Set this to false to disallow usernames, email will be used as username instead
  $showSignupForm = true; // don't change this value

  // error checking
  $errorsAndAlerts = "";
  if (@$CURRENT_USER) {
    $errorsAndAlerts .= "You are already signed up! <a href='{$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']}'>Click here to continue</a>.<br>\n";
    $showSignupForm = false;
  }

  // process form
  if (@$_POST['save']) {

    // redirect to profile page after after signing up
    setPrefixedCookie('lastUrl', $GLOBALS['WEBSITE_LOGIN_PROFILE_URL']);

    // error checking
    $emailAlreadyInUse    = mysql_count(accountsTable(), mysql_escapef("? IN (`username`, `email`)", @$_REQUEST['email']));
    $usernameAlreadyInUse = mysql_count(accountsTable(), mysql_escapef("? IN (`username`, `email`)", @$_REQUEST['username']));

    if     (!@$_REQUEST['fullname'])                       { $errorsAndAlerts .= "You must enter your full name!<br>\n"; }
    if     (!@$_REQUEST['email'])                          { $errorsAndAlerts .= "You must enter your email!<br>\n"; }
    elseif (!isValidEmail(@$_REQUEST['email']))            { $errorsAndAlerts .= "Please enter a valid email (example: user@example.com)<br>\n"; }
    elseif ($emailAlreadyInUse)                            { $errorsAndAlerts .= "That email is already in use, please choose another!<br>\n"; }
    if ($useUsernames) {
      if     (!@$_REQUEST['username'])                     { $errorsAndAlerts .= "You must choose a username!<br>\n"; }
      elseif (preg_match("/\s+/", @$_REQUEST['username'])) { $errorsAndAlerts .= "Username cannot contain spaces!<br>\n"; }
      elseif ($usernameAlreadyInUse)                       { $errorsAndAlerts .= "That username is already in use, please choose another!<br>\n"; }
    }
    elseif (!$useUsernames) {
      if (@$_REQUEST['username'])                          { $errorsAndAlerts .= "Usernames are not allowed!<br>\n"; }
    }

    // add user
    if (!$errorsAndAlerts) {

      // generate password
      $passwordText = wsm_generatePassword();
      $passwordHash = getPasswordDigest($passwordText);

      //
      $colsToValues = array();
      $colsToValues['createdDate=']     = 'NOW()';
      $colsToValues['updatedDate=']     = 'NOW()';
      $colsToValues['createdByUserNum'] = 0;
      $colsToValues['updatedByUserNum'] = 0;

      // fields defined by form:
      //$colsToValues['agree_tos']      = $_REQUEST['agree_tos'];
      $colsToValues['fullname']         = $_REQUEST['fullname'];
      $colsToValues['email']            = $_REQUEST['email'];
      $colsToValues['username']         = $_REQUEST['username'] ?: $_REQUEST['email']; // email is saved as username if usernames not supported
      $colsToValues['password']         = $passwordHash;
      // ... add more form fields here by copying the above line!
      $userNum = mysql_insert(accountsTable(), $colsToValues, true);

      // set access rights for CMS so new users can access some CMS sections
      $setAccessRights = false; // set to true and set access tables below to use this
      if ($setAccessRights && accountsTable() == "accounts") { // this is only relevant if you're adding users to the CMS accounts table

        // NOTE: You can repeat this block to grant access to multiple sections
        mysql_insert('_accesslist', array(
          'userNum'      => $userNum,
          'tableName'    => '_sample',   // insert tablename you want to grant access to, or 'all' for all sections
          'accessLevel'  => '0',         // access level allowed: 0=none, 6=author, 9=editor
          'maxRecords'   => '',          // max listings allowed (leave blank for unlimited)
          'randomSaveId' => '123456789', // ignore - for internal use
        ));
      }

      // send message
      list($mailErrors, $fromEmail) = wsm_sendSignupEmail($userNum, $passwordText);
      if ($mailErrors) { alert("Mail Error: $mailErrors"); }

      // show thanks
      $errorsAndAlerts  = "Thanks, We've created an account for you and emailed you your password.<br><br>\n";
      $errorsAndAlerts .= "If you don't receive an email from us within a few minutes check your spam filter for messages from {$fromEmail}<br><br>\n";
      $errorsAndAlerts .= "<a href='{$GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']}'>Click here to login</a>.";

      $_REQUEST        = array(); // clear form values
      $showSignupForm  = false;
    }
  }

#><?php cg2_code_header(); ?>

<h1>Sample User Signup Form</h1>

<!-- USER SIGNUP FORM -->
  <#php if (@$errorsAndAlerts): #>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <#php echo $errorsAndAlerts; #><br>
    </div>
  <#php endif #>

<#php if ($showSignupForm): #>
  <form method="post" action="?">
  <input type="hidden" name="save" value="1">

  <table border="0" cellspacing="0" cellpadding="2">
   <tr>
    <td>Full Name</td>
    <td><input type="text" name="fullname" value="<#php echo htmlencode(@$_REQUEST['fullname']); #>" size="50"></td>
   </tr>
   <tr>
    <td>Email</td>
    <td><input type="text" name="email" value="<#php echo htmlencode(@$_REQUEST['email']); #>" size="50"></td>
   </tr>
<#php if ($useUsernames): #>
   <tr>
    <td>Username</td>
    <td><input type="text" name="username" value="<#php echo htmlencode(@$_REQUEST['username']); #>" size="50"></td>
   </tr>
<#php endif #>

   <tr>
    <td colspan="2" align="center">
      <br><input class="button" type="submit" name="submit" value="Sign up &gt;&gt;">
    </td>
   </tr>
  </table>

  </form>
<#php endif #>
<!-- /USER SIGNUP FORM -->
<?php cg2_code_footer(); ?>


<?php
  // return code
  $code = ob_get_clean();
  return $code;
}


//
function wsmcode_user_profile_php() {
  // generate code
  ob_start();
?><#php $GLOBALS['WEBSITE_MEMBERSHIP_PROFILE_PAGE'] = true; // prevent redirect loops for users missing fields listed in $GLOBALS['WEBSITE_LOGIN_REQUIRED_FIELDS'] #>
<#php
  # Developer Notes: To add "Agree to Terms of Service" checkbox (or similar checkbox field), just add it to the accounts menu in the CMS and uncomment agree_tos lines
  <?php cg2_code_loadLibraries(); ?>
  if (!@$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']) { die("You must activate the Website Membership plugin before you can access this page."); }

  //
  $useUsernames   = true; // Set this to false to disallow usernames, email will be used as username instead

  // error checking
  $errorsAndAlerts = "";
  if (@$_REQUEST['missing_fields']) { $errorsAndAlerts = "Please fill out all of the following fields to continue.<br>\n"; }
  if (!$CURRENT_USER) { websiteLogin_redirectToLogin(); }


  ### Update User Profile
  if (@$_POST['save']) {

    // error checking
    $emailAlreadyInUse    = mysql_count(accountsTable(), mysql_escapef("`num` != ?  AND ? IN (`username`, `email`)", $CURRENT_USER['num'], @$_REQUEST['email']));
    $usernameAlreadyInUse = mysql_count(accountsTable(), mysql_escapef("`num` != ?  AND ? IN (`username`, `email`)", $CURRENT_USER['num'], @$_REQUEST['username']));

    if     (!@$_REQUEST['fullname'])            { $errorsAndAlerts .= "You must enter your full name!<br>\n"; }
    if     (!@$_REQUEST['email'])               { $errorsAndAlerts .= "You must enter your email!<br>\n"; }
    elseif (!isValidEmail(@$_REQUEST['email'])) { $errorsAndAlerts .= "Please enter a valid email (example: user@example.com)<br>\n"; }
    elseif ($emailAlreadyInUse)                 { $errorsAndAlerts .= "That email is already in use, please choose another!<br>\n"; }
    if ($useUsernames) {
      if     (!@$_REQUEST['username'])                     { $errorsAndAlerts .= "You must choose a username!<br>\n"; }
      elseif (preg_match("/\s+/", @$_REQUEST['username'])) { $errorsAndAlerts .= "Username cannot contain spaces!<br>\n"; }
      elseif ($usernameAlreadyInUse)                       { $errorsAndAlerts .= "That username is already in use, please choose another!<br>\n"; }
    }
    elseif (!$useUsernames) {
      if (@$_REQUEST['username'])                          { $errorsAndAlerts .= "Usernames are not allowed!<br>\n"; }
    }
    //if (!@$_REQUEST['agree_tos'])               { $errorsAndAlerts .= "You must agree to the Terms of Service!<br>\n"; }

    // update user
    if (!$errorsAndAlerts) {
      $colsToValues = array();
      //$colsToValues['agree_tos']        = $_REQUEST['agree_tos'];
      $colsToValues['fullname']         = $_REQUEST['fullname'];
      $colsToValues['username']         = $_REQUEST['username'] ?: $_REQUEST['email']; // email is saved as username if username code (not this line) is commented out
      $colsToValues['email']            = $_REQUEST['email'];
      // ... add more form fields here by copying the above line!
      $colsToValues['updatedByUserNum'] = $CURRENT_USER['num'];
      $colsToValues['updatedDate=']     = 'NOW()';
      mysql_update(accountsTable(), $CURRENT_USER['num'], null, $colsToValues);

      // on success
      websiteLogin_setLoginTo( $colsToValues['username'], $CURRENT_USER['password'] );  // update login session username in case use has changed it.
      $errorsAndAlerts = "Thanks, we've updated your profile!<br>\n";
    }
  }


  ### Change Password
  if (@$_POST['changePassword']) {

    // error checking
    $_REQUEST['oldPassword'] = preg_replace("/^\s+|\s+$/s", '', @$_REQUEST['oldPassword']); // v1.10 remove leading and trailing whitespace
    $oldPasswordHash  = getPasswordDigest(@$_REQUEST['oldPassword']);
    if     (!@$_REQUEST['oldPassword'])                             { $errorsAndAlerts .= "Please enter your current password<br>\n"; }
    elseif ($oldPasswordHash != $CURRENT_USER['password'])          { $errorsAndAlerts .= "Current password isn't correct!<br>\n"; }
    $newPasswordErrors = getNewPasswordErrors(@$_REQUEST['newPassword1'], @$_REQUEST['newPassword2'], $CURRENT_USER['username']); // v2.52
    $errorsAndAlerts  .= nl2br(htmlencode($newPasswordErrors));

    // change password
    if (!$errorsAndAlerts) {
      $passwordHash = getPasswordDigest($_REQUEST['newPassword2']);
      mysql_update( accountsTable(), $CURRENT_USER['num'], null, array('password' => $passwordHash)); // update password
      websiteLogin_setLoginTo( $CURRENT_USER['username'], $_REQUEST['newPassword2'] );                // update current login session
      unset($_REQUEST['oldPassword'], $_REQUEST['newPassword1'], $_REQUEST['newPassword2']);          // clear form password fields
      $errorsAndAlerts = "Thanks, we've updated your password!<br>\n";
    }
  } ### END: Change Password


  ### Delete Account
  if (@$_POST['deleteAccount']) {
    if ($CURRENT_USER['isAdmin']) { die("Error: Deleting admin accounts is not permitted!"); }
    removeUploads( mysql_escapef("tableName = ? AND recordNum = ?", accountsTable(), $CURRENT_USER['num']) );  // delete uploads
    mysql_delete(accountsTable(), $CURRENT_USER['num']); // delete user record
    websiteLogin_redirectToLogin(); // redirect to login
  } ### END: Delete Account


  // prepopulate form with current user values
  foreach ($CURRENT_USER as $name => $value) {
    if (array_key_exists($name, $_REQUEST)) { continue; }
    $_REQUEST[$name] = $value;
  }

#><?php cg2_code_header(); ?>

<h1>Sample Edit Profile Page</h1>

<!-- EDIT PROFILE FORM -->
  <#php if (@$errorsAndAlerts): #>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <#php echo $errorsAndAlerts; #><br>
    </div>
  <#php endif #>

  <form method="post" action="?">
  <input type="hidden" name="save" value="1">

  <table border="0" cellspacing="0" cellpadding="2">
   <tr>
    <td>Full Name</td>
    <td><input type="text" name="fullname" value="<#php echo htmlencode(@$_REQUEST['fullname']); #>" size="50"></td>
   </tr>
   <tr>
    <td>Email</td>
    <td><input type="text" name="email" value="<#php echo htmlencode(@$_REQUEST['email']); #>" size="50"></td>
   </tr>
<#php if ($useUsernames): #>
   <tr>
    <td>Username</td>
    <td><input type="text" name="username" value="<#php echo htmlencode(@$_REQUEST['username']); #>" size="50"></td>
   </tr>
<#php endif #>

<!--
   <tr>
    <td>Agree TOS</td>
    <td>
      <input type="hidden"   name="agree_tos" value="0">
      <label>
        <input type="checkbox" name="agree_tos" value="1" <#php checkedIf('1', @$_REQUEST['agree_tos']); #>>
        I agree to the <a href="#">terms of service</a>.
      </label>
    </td>
   </tr>
-->
   <tr>
    <td colspan="2" align="center">
      <input class="button" type="submit" name="submit" value="Update profile &gt;&gt;">
    </td>
   </tr>
  </table>

  </form><br>
<!-- /EDIT PROFILE FORM -->


<!-- CHANGE PASSWORD FORM -->
<div style="border: 1px solid #000; background-color: #EEE; padding: 10px; width: 500px">
  <b>Change Password</b><br>

  <form method="post" action="?">
  <input type="hidden" name="changePassword" value="1">

  <p><table border="0" cellspacing="0" cellpadding="1">
   <tr>
    <td>Current Password</td>
    <td><input type="password" name="oldPassword" value="<#php echo htmlencode(@$_REQUEST['oldPassword']); #>" size="40" autocomplete="off"></td>
   </tr>
   <tr>
    <td>New Password</td>
    <td><input type="password" name="newPassword1" value="<#php echo htmlencode(@$_REQUEST['newPassword1']); #>" size="40" autocomplete="off"></td>
   </tr>
   <tr>
    <td>New Password (again)</td>
    <td><input type="password" name="newPassword2" value="<#php echo htmlencode(@$_REQUEST['newPassword2']); #>" size="40" autocomplete="off"></td>
   </tr>
   <tr>
    <td colspan="2" align="center">
      <input class="button" type="submit" name="submit" value="Change Password &gt;&gt;">
    </td>
   </tr>
  </table>

  </form>
</div><br>
<!-- /CHANGE PASSWORD -->


<!-- REMOVE PROFILE FORM -->
<div style="border: 1px solid #000; background-color: #EEE; padding: 10px; width: 500px">
  <b>Delete Account</b>
  <p>If you want to delete your account you can do so here.<br>
  Please note that all data will be lost and this is irreversible.</p>

  <form method="post" action="?" onsubmit="return confirm('Are you sure you want to delete your account?')">
  <input type="submit" name="deleteAccount" value="Delete Account">
  </form>
</div>
<!-- /REMOVE PROFILE FORM -->
<?php cg2_code_footer(); ?>


<?php
  // return code
  $code = ob_get_clean();
  return $code;
}

?>
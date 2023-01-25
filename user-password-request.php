<?php

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title></title>
  <style type="text/css">
    body          { font-family: arial; }
    .instructions { border: 3px solid #000; background-color: #EEE; padding: 10px; text-align: left; margin: 25px}
  </style>
</head>
<body>


  <!-- PAGE TITLE -->
  <?php if ($isRequestPage): ?> <h1>Forgot your password?</h1> <?php endif ?>
  <?php if ($isResetPage):   ?> <h1>Reset your Password</h1> <?php endif ?>


  <!-- ERRORS & ALERTS -->
  <?php if (@$errorsAndAlerts): ?>
    <div style="color: #C00; font-weight: bold;"><?php echo $errorsAndAlerts; ?></div>
  <?php endif ?>


  <!-- START: REQUEST FORGOT PASSWORD EMAIL -->
  <?php if ($isRequestPage && $showForm): ?>
    <p>Just enter your username or email address to reset your password.</p>

    <form action="?" method="post">
      <input type="hidden" name="submitForm" value="1">
      Email or username:
      <input type="text" name="usernameOrEmail" value="<?php echo htmlencode(@$_REQUEST['usernameOrEmail']) ?>" size="20" autocomplete="off" autofocus>
      <input type="submit" name="submit" value="Lookup">
    </form>
  <?php endif ?>
  <!-- END: REQUEST FORGOT PASSWORD EMAIL -->


  <!-- START: RESET PASSWORD FORM -->
  <?php if ($isResetPage && $showForm): ?>
    <form method="post" action="?">
    <input type="hidden" name="userNum"    value="<?php echo htmlencode(@$_REQUEST['userNum']); ?>">
    <input type="hidden" name="resetCode"  value="<?php echo htmlencode(@$_REQUEST['resetCode']); ?>">
    <input type="hidden" name="submitForm" value="1">

    <table border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td><?php et('Username') ?></td>
        <td style="padding: 10px 0px"><?php echo htmlencode( $user['username'] ); ?></td>
      </tr>
      <tr>
        <td><?php et('New Password') ?></td>
        <td><input class="text-input" type="password" name="password"  value="<?php echo htmlencode(@$_REQUEST['password']) ?>" autocomplete="off"></td>
      </tr>
      <tr>
        <td><?php et('New Password (again)') ?> &nbsp;</td>
        <td><input class="text-input" type="password" name="password:again"  value="<?php echo htmlencode(@$_REQUEST['password:again']) ?>" autocomplete="off"></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td><input class="button" type="submit" name="send" value="<?php et('Update') ?>"></td>
      </tr>
    </table>
    </form>
    <?php endif ?>
    <!-- /RESET PASSWORD FORM -->


  <!-- FOOTER -->
  <br>
  <a href="<?php echo $GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL'] ?>">&lt;&lt; Login Page</a>

</body>
</html>

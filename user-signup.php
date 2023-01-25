<?php

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }
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
    if     (strcmp(@$_REQUEST['password'], @$_REQUEST['password1']) !== 0) {$errorsAndAlerts .= "Your passwords must match!<br>\n"; }
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
      $passwordHash = getPasswordDigest($_REQUEST['password']);

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
      $colsToValues['address']          = $_REQUEST['address'];
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


      // show thanks
      $errorsAndAlerts  = "Thanks, We've created an account for you.<br><br>\n";
      $errorsAndAlerts .= "<a href='{$GLOBALS['WEBSITE_LOGIN_LOGIN_FORM_URL']}'>Click here to login</a>.";

      $_REQUEST        = array(); // clear form values
      $showSignupForm  = false;
    }
  }

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
<h1>User Signup Form</h1>

<!-- USER SIGNUP FORM -->
  <?php if (@$errorsAndAlerts): ?>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <?php echo $errorsAndAlerts; ?><br>
    </div>
  <?php endif ?>
  <?php if ($showSignupForm): ?>
    <form method="post" action="?">
    <input type="hidden" name="save" value="1">

    <table border="0" cellspacing="0" cellpadding="2">
     <tr>
      <td>Full Name</td>
      <td><input type="text" name="fullname" value="<?php echo htmlencode(@$_REQUEST['fullname']); ?>" size="50"></td>
     </tr>
     <tr>
      <td>Email</td>
      <td><input type="text" name="email" value="<?php echo htmlencode(@$_REQUEST['email']); ?>" size="50"></td>
     </tr>
  <?php if ($useUsernames): ?>
     <tr>
      <td>Username</td>
      <td><input type="text" name="username" value="<?php echo htmlencode(@$_REQUEST['username']); ?>" size="50"></td>
     </tr>
  <?php endif ?>
  <tr>
   <td>Password</td>
   <td><input type="text" name="password" value="<?php echo htmlencode(@$_REQUEST['password']); ?>" size="50"></td>
  </tr>
  <tr>
   <td>re-type Password</td>
   <td><input type="text" name="password1" value="<?php echo htmlencode(@$_REQUEST['password1']); ?>" size="50"></td>
  </tr>
  <tr>
   <td>Address</td>
   <td><input type="text" name="address" value="<?php echo htmlencode(@$_REQUEST['address']); ?>" size="50"></td>
  </tr>
  <?php endif ?>
     <tr>
      <td colspan="2" align="center">
        <br><input class="button" type="submit" name="submit" value="Sign up &gt;&gt;">
      </td>
     </tr>
    </table>

    </form>

</body>
</html>

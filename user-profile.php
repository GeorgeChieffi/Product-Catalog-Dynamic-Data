<?php $GLOBALS['WEBSITE_MEMBERSHIP_PROFILE_PAGE'] = true; // prevent redirect loops for users missing fields listed in $GLOBALS['WEBSITE_LOGIN_REQUIRED_FIELDS'] ?>
<?php
  # Developer Notes: To add "Agree to Terms of Service" checkbox (or similar checkbox field), just add it to the accounts menu in the CMS and uncomment agree_tos lines

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }
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


<h1>Edit Profile Page</h1>

<!-- EDIT PROFILE FORM -->
  <?php if (@$errorsAndAlerts): ?>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <?php echo $errorsAndAlerts; ?><br>
    </div>
  <?php endif ?>

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

<!--
   <tr>
    <td>Agree TOS</td>
    <td>
      <input type="hidden"   name="agree_tos" value="0">
      <label>
        <input type="checkbox" name="agree_tos" value="1" <?php checkedIf('1', @$_REQUEST['agree_tos']); ?>>
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

  <a href="index.php"> Visit Catalog </a>
</body>
</html>

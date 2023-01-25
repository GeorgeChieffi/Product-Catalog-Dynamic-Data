<?php

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }
  if (!@$GLOBALS['WEBSITE_MEMBERSHIP_PLUGIN']) { die("You must activate the Website Membership plugin before you can access this page."); }

  // error checking
  $errorsAndAlerts = alert();
  if(@$CURRENT_USER && @$_REQUEST['adminLoginRequired']) { $errorsAndAlerts .= "You must be an admin to access that page <a href='{$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']}'>Click here to continue</a> or <a href='?action=logoff'>Logoff</a>.<br>\n"; }
  else if (@$CURRENT_USER)                                { $errorsAndAlerts .= "You are already logged in! <a href='{$GLOBALS['WEBSITE_LOGIN_POST_LOGIN_URL']}'>Click here to continue</a> or <a href='?action=logoff'>Logoff</a>.<br>\n"; }
  if (!$CURRENT_USER && @$_REQUEST['loginRequired']) { $errorsAndAlerts .= "Please login to continue.<br>\n"; }

  // save url of referring page so we can redirect user there after login
  // if (!getPrefixedCookie('lastUrl')) { setPrefixedCookie('lastUrl', @$_SERVER['HTTP_REFERER'] ); }

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

<h1>Sample User Login Form</h1>

<!-- USER LOGIN FORM -->
  <?php if (@$errorsAndAlerts): ?>
    <div style="color: #C00; font-weight: bold; font-size: 13px;">
      <?php echo $errorsAndAlerts; ?><br>
    </div>
  <?php endif ?>

<?php if (!@$CURRENT_USER): ?>
  <form action="?" method="post">
  <input type="hidden" name="action" value="login">

  <table border="0" cellspacing="0" cellpadding="2">
   <tr>
    <td>Email or Username</td>
    <td><input type="text" name="username" value="<?php echo htmlencode(@$_REQUEST['username']); ?>" size="30" autocomplete="off"></td>
   </tr>
   <tr>
    <td>Password</td>
    <td><input type="password" name="password" value="<?php echo htmlencode(@$_REQUEST['password']); ?>" size="30" autocomplete="off"></td>
   </tr>

   <tr>
    <td colspan="2" align="center">
      <br><input type="submit" name="submit" value="Login">
      <a href="<?php echo $GLOBALS['WEBSITE_LOGIN_SIGNUP_URL'] ?>">or sign-up</a><br><br>

      <?php if (function_exists('fbl_login')): // NOTE: This feature requires the Facebook Login v2+ plugin! ?>
        <?php fbl_loginForm_javascript(); ?>
        <a href="#" onclick="fbl_login(); return false;">Login with Facebook</a><br><br>
      <?php endif; ?>

      <?php if (@$GLOBALS['TWITTERAPI_ENABLE_LOGIN']): ?>
        <a href="<?php echo twitterLogin_getTwitterLoginUrl();?>"
        onclick="<?php echo twitterLogin_getTwitterLoginUrl_popupOnClick(); ?>">Login with Twitter</a><br><br>
      <?php endif ?>
    </td>
   </tr>
  </table>
  </form>
<?php endif ?>
<!-- /USER LOGIN FORM -->
  <a href="index.php">Home Page</a>
</body>
</html>

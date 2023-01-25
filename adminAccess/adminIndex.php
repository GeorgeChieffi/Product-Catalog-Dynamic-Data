<?php require_once "../cmsb/lib/viewer_functions.php"; ?>
<?php if (!$CURRENT_USER) { websiteLogin_redirectToLogin(); } ?>
<?php if (!$CURRENT_USER['isAdmin']) {adminFailed_redirectToLogin();}?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title></title>
  </head>
  <body>
    <h3>Welcome to the admin index page</h3>
    <p>only users with admin privleges may view this page</p>
    <br>
    <a href="orders-list.php">View all orders</a>
    <a href="../index.php">back to home page</a>
  </body>
</html>

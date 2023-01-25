<?php

  // Let search engines know we're only down temporarily and they should check back later
  header('HTTP/1.1 503 Service Temporarily Unavailable');
  header('Status: 503 Service Temporarily Unavailable');
  header('Retry-After: 7200'); // in seconds

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="<?php echo CMS_ASSETS_URL ?>/3rdParty/clipone/plugins/bootstrap/css/bootstrap.min.css">
</head>
<body style="background-color: #eeeeee;" class="container">
  
  <br class="hidden-xs">
  <br class="hidden-xs hidden-sm">
  
  <div class="row">
    <div style="max-width: 600px; margin: auto;">
      <div class="panel panel-default" style="margin: 10px;">
        <div class="panel-heading">
          <h3 style="margin: 10px 0 5px;"><?php et("Hello, Website Visitors!"); ?></h3>
        </div>
        <div class="panel-body">
          <p>
            <?php et("We are temporarily experiencing <b>high website traffic</b> or technical difficulties. Please try again later."); ?>
          </p>
          
          <h4><?php et("Do you run this website?"); ?></h4>
          <p>
            <?php et("We were unable to connect to the database, possibly because:"); ?>
            <ol type="A">
              <li><?php echo sprintf(t("Your database settings are incorrect (check in %s)"), '/data/' . SETTINGS_FILENAME); ?></li>
              <li><?php et("Your database server is down or overloaded (check with your host)"); ?></li>
            </ol>
          </p>
            
          <p>
            <?php et("The database error given was:"); ?><br>
            <?php echo @$connectionError ? $connectionError : 'none' ?>
          </p>

        </div>
      </div>
    </div>
  </div>

  <div style="text-align: center; font-size: 8px; color: #666666">
    <?php printf(t("%s seconds"), showExecuteSeconds()) ?>
  </div>

</body>
</html>

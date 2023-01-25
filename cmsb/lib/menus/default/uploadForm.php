<?php
  global $errors, $isUploadLimit, $uploadsRemaining, $maxUploads, $menu;
  require_once "lib/menus/default/uploadForm_functions.php";

  $maxUploadFields = 10; // max number of upload fields to show.
  if (!$isUploadLimit) { $uploadsRemaining = $maxUploadFields; } // if unlimited uploads allowed show max fields

  $errors .= alert();
?>
<!DOCTYPE html
          PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
          "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
 <head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">

  <?php include "lib/menus/header_css.php"; ?>

  <!-- javascript -->
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery.js"></script>
  </head>
<body>

<form method="post" action="?" enctype="multipart/form-data" autocomplete="off">
<input type="hidden" name="_defaultAction" value="uploadForm">
<input type="hidden" name="menu"          value="<?php echo htmlencode($menu); ?>">
<input type="hidden" name="fieldName"     value="<?php echo htmlencode(@$_REQUEST['fieldName']) ?>">
<input type="hidden" name="num"           value="<?php echo htmlencode(@$_REQUEST['num']) ?>">
<input type="hidden" name="preSaveTempId" value="<?php echo htmlencode(@$_REQUEST['preSaveTempId']) ?>">
<input type="hidden" name="submitUploads" value="1">

<script>
window.focus();
$(window).on('resize', function() { window.parent.resizeIframe('iframeModal-iframe'); });
</script>

<div align="center" style="padding: 10px">

  <!-- header -->
  <div style="float: left">
    <h2><?php et('Upload Files') ?></h2>
  </div>

  <div style="float: right">
     <?php echo adminUI_button(['label' => t('Upload'), 'type' => 'submit', 'name' => '_action=uploadForm', 'value' => '1']); ?>
     <?php echo adminUI_button(['label' => t('Cancel'), 'type' => 'button', 'onclick' => 'self.parent.hideModal()' ]); ?>
  </div>

  <div class="clear"></div>


  <!-- errors and alerts -->
  <div style="width: 400px;">

    <?php if (@$errors): ?>
      <div class="errorMessage" style="font-weight: bold; color: red"><?php echo @$errors ?></div><br>
    <?php endif ?>

    <?php if ($isUploadLimit && $uploadsRemaining == 0): ?>
      <b><?php printf(t("The maximum number of uploads (%s) has been reached for this field. You need to remove some files before you can upload more."), $maxUploads); ?>
      </b><br><br>
    <?php else: ?>

      <?php if ($isUploadLimit): ?>
        <b><?php

          if ($maxUploads == 1)       { printf(t("This field allows %s upload."),  $maxUploads); }
          else                        { printf(t("This field allows %s uploads."), $maxUploads); }
          print " ";
          if ($uploadsRemaining == 1) { printf(t("You can upload %s more file."),  $uploadsRemaining); }
          else                        { printf(t("You can upload %s more files."), $uploadsRemaining); }

          ?></b><br>
      <?php endif ?>

      <b><?php et('Please be patient after clicking "Upload" as it can take some time to transfer all the files to the server.'); ?></b>
    <?php endif ?>
  </div>

  <!-- upload fields -->
  <br>
  <?php if ($uploadsRemaining): ?>
    <?php foreach (range(1, (int) min($uploadsRemaining, $maxUploadFields)) as $count): ?>
      <?php et("Upload File") ?> &nbsp;<input type="file" name="upload<?php echo $count ?>" size="50" style="vertical-align: middle;"><br>
    <?php endforeach ?>
  <?php endif ?>

  <?php printf(t("%s seconds"), showExecuteSeconds()) ?>

</div>
</form>

<div class="clear"></div>

</body>
</html>

<?php
  global $tableName, $errors, $menu;
  //require_once "lib/menus/default/uploadModify_functions.php";
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

<?php security_getHiddenCsrfTokenField(); ?>

<script>
window.focus();
// resize on initial load, ie. clicking "modify"
$(window).on('resize', function() { window.parent.resizeIframe('iframeModal-iframe'); });

// resize when the iframe is reloaded, ie. clicking "Modify All Uploads" or when coming from the Upload form (html5 uploader disabled)
$(document).ready(function(){
  var duration = 0;
  
  var newHeight = $(document).contents().find('body').height();
  self.parent.resizeIframe('iframeModal-iframe', duration);
});


// add media to parent record
function addMedia(mediaNum) {
  var tableName     = '<?php echo jsEncode(@$_REQUEST['menu']); ?>';
  var fieldName     = '<?php echo jsEncode(@$_REQUEST['fieldName']); ?>';
  var recordNum     = '<?php echo jsEncode(@$_REQUEST['num']); ?>';
  var preSaveTempId = '<?php echo jsEncode(@$_REQUEST['preSaveTempId']); ?>';

  // debug: alert media record we want to add
  //alert("Add media " +mediaNum+ " to  " +tableName+ ",  " +fieldName+ " record, " +recordNum+ " .");

  // ajax call to insert media record
  $.ajax({
    url: '?',
    type: "POST",
    crossDomain: true,
    data: {
      menu:       tableName,
      action:     'mediaAdd',
      mediaNum:   mediaNum,
      tableName:  tableName,
      fieldName:  fieldName,
      recordNum:  recordNum,
      _CSRFToken: $('[name=_CSRFToken]').val(),
      preSaveTempId: preSaveTempId
    },

    error:  function(msg){ alert("There was an error adding media!"); },
    success: function(msg){
      if (msg) { return alert("Error: " + msg); };
            
      // refresh upload iframe in parent window
      self.parent.document.getElementById(fieldName + '_iframe').contentDocument.location.reload(true);
      
      // close this modal
      self.parent.hideModal();
      
      //
      return true;
    }
  });
  
}

</script>

<div align="center" style="padding: 10px;">

  <!-- page header -->

  
  <div style="float: left">
    <h2><?php et('Media Library') ?></h2>
  </div>

  <div style="float: right">
   <?php echo adminUI_button(['label' => t('Cancel'), 'type' => 'button', 'onclick' => 'self.parent.hideModal()' ]); ?>
  </div>

  <div class="clear"></div>

  <!-- list media library items -->

 <?php media_showMediaList('mediaList'); ?>

  <div class="clear"></div>

  <div style="font-size: 10px; color: #888888; padding: 0px 2px; text-align: center" align="center">


    <?php printf(t("%s seconds"), showExecuteSeconds()) ?>
  </div>


</div>

</form>

<div class="clear"></div>

</body>
</html>

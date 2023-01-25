<?php
  global $tableName, $menu;

  // error checking
  if (!array_key_exists('menu', $_REQUEST))               { die("no 'menu' value specified!"); }
  if (!@$_REQUEST['fieldName'])                           { die("no 'fieldName' value specified!"); }
  if (!@$_REQUEST['num'] && !@$_REQUEST['preSaveTempId']) { die("No record 'num' or 'preSaveTempId' was specified!"); }

  // get uploads
  $uploads         = [];
  $fieldSchema     = $schema[$_REQUEST['fieldName']];
  $hasModifyFields = @$fieldSchema["infoField1"] || @$fieldSchema["infoField2"] || @$fieldSchema["infoField3"] || @$fieldSchema["infoField4"] || @$fieldSchema["infoField5"];
  $uploadCount = 0;
  $records = getUploadRecords($tableName, $_REQUEST['fieldName'], @$_REQUEST['num'], @$_REQUEST['preSaveTempId'], null);
  foreach ($records as $row) {
    $filename             = pathinfo($row['filePath'], PATHINFO_BASENAME);
    $jsEscapedFilename    = addcslashes(htmlencode($filename), '\\\'');
    $row['_modifyLinkJS'] = "modifyUpload('{$row['num']}', '$jsEscapedFilename', this); return false;";
    $row['_removeLinkJS'] = "removeUpload('{$row['num']}', '$jsEscapedFilename', this); return false;";

    $row['_infoFields']   = '';
    foreach (range(1,5) as $num) {
      $fieldLabel = @$fieldSchema["infoField$num"];
      $fieldValue = @$row["info$num"];
      if (!$fieldLabel) { continue; }
      $row['_infoFields'] .= htmlencode($fieldLabel) . ': '. htmlencode($fieldValue). "<br>\n";
    }

    $uploads[] = $row;
  }
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
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery-ui-1.11.4.min.js"></script><?php /* for datepicker and jquery sortable */ ?>
  <link  href="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryUI/css/smoothness/jquery-ui-1.8.18.custom.css" rel="stylesheet" type="text/css">
  <script src="<?php echo noCacheUrlForCmsFile("lib/dragsort.js"); ?>"></script>
  <link href="<?php echo CMS_ASSETS_URL ?>/3rdParty/font-awesome/css/all.min.css" rel="stylesheet">
  <link href="<?php echo CMS_ASSETS_URL ?>/3rdParty/font-awesome/css/v4-shims.min.css" rel="stylesheet">

 </head>
<body>

<form method="post" action="?" autocomplete="off">
<input type="hidden" name="menu" id="menu" value="<?php echo htmlencode($menu) ?>">
<input type="hidden" name="tableName" id="tableName" value="<?php echo htmlencode($tableName) ?>">
<input type="hidden" name="fieldName" id="fieldName" value="<?php echo htmlencode(@$_REQUEST['fieldName']) ?>">
<input type="hidden" name="num"  id="num" value="<?php echo htmlencode(@$_REQUEST['num']) ?>">
<input type="hidden" name="preSaveTempId"  id="preSaveTempId" value="<?php echo htmlencode(@$_REQUEST['preSaveTempId']) ?>">
<?php echo security_getHiddenCsrfTokenField(); ?>

  <?php if (@$_REQUEST['errors']): ?>
    <div style="color: red; margin: 10px 0;">
      <?php
        $errors = $_REQUEST['errors'];
        $errors = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;", urldecode($errors)); // convert utf8 url encoding to utf8 string
        $errors = preg_replace('/<br\>/', "\n", $errors);
        $errors = strip_tags($errors);
        $errors = htmlencode($errors); // prevent XSS
        $errors = nl2br($errors);

        echo $errors;
      ?>
    </div>
  <?php endif ?>

<table border="0" cellspacing="0" cellpadding="0" class="data sortable uploadlist table table-striped table-hover">
 <thead>
 <tr class="nodrag nodrop">
<?php if (@$_REQUEST['formType'] != 'view'): ?>
  <th style="text-align: center; width:40px;"><?php et('Drag') ?></th>
<?php endif ?>
  <th style="text-align: center; width:60px;"><?php et('Preview') ?></th>
  <th style="width:260px;"><?php et('Details') ?></th>
<?php if (@$_REQUEST['formType'] != 'view'): ?>
  <th width="130" style="text-align: center; width:130px;" colspan="2"><?php et('Commands') ?></th>
<?php endif ?>
 </tr>
 </thead>

<?php
 foreach ($uploads as $row):
   $uploadCount++;
?>
    <tr class="uploadRow">
<?php if (@$_REQUEST['formType'] != 'view'): ?>
     <td style="text-align: center; vertical-align: middle; width:5%;" class="dragger">
      <input type='hidden' name='_uploadNum' value='<?php echo $row['num'] ?>' class='_uploadNum'>
      <span    class="fa fa-chevron-down" aria-hidden="true" title="<?php et('Click and drag to change order.') ?>"></span><!--
      --><span class="fa fa-chevron-up"   aria-hidden="true" title="<?php et('Click and drag to change order.') ?>"></span>
     </td>
<?php endif ?>
     <td style="text-align: center; vertical-align: middle; padding: 2px; width:10%;">
       <?php showUploadPreview($row, 50); ?>
     </td>
     <td style="vertical-align: top; width:65%;" valign="top">
       <?php echo $row['_infoFields']; ?>
       <?php et('Filename:'); ?> <?php echo htmlencode(pathinfo($row['filePath'], PATHINFO_BASENAME)); ?>
       <?php if (!empty($row['mediaNum'])) { echo "(<a href='?menu=_media&action=edit&num=" .$row['mediaNum']. "' target='_blank'>" .t("from media library"). "</a>)"; }; ?>
     </td>
<?php if (@$_REQUEST['formType'] != 'view'): ?>
     <?php if ($hasModifyFields): ?>
       <td style="text-align: center; vertical-align: middle; width:10%;"><a href="#" onclick="<?php echo $row['_modifyLinkJS']; ?>"><?php et('modify') ?></a></td>
       <td style="text-align: center; vertical-align: middle; width:10%;"><a href="#" onclick="<?php echo $row['_removeLinkJS']; ?>"><?php et('remove') ?></a></td>
     <?php else: ?>
       <td style="text-align: center; vertical-align: middle; width:20%;" colspan="2"><a href="#" onclick="<?php echo $row['_removeLinkJS']; ?>"><?php et('remove') ?></a></td>
     <?php endif ?>
<?php endif ?>
    </tr>
<?php endforeach; ?>
</table>

 <table border="0" cellspacing="0" cellpadding="0" class="noUploads" style="display: none; width: 100%">
  <tr><td style="text-align: center; padding: 30px"><?php et('There are no files uploaded for this record.') ?></td></tr>
 </table>

<script type="text/javascript"><!-- // language strings
  lang_confirm_erase_image = '<?php echo addslashes(t("Remove file: %s")) ?>';
//--></script>
<script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/admin_functions.js"); ?>"></script>
<script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/menus/default/uploadList_functions.js"); ?>"></script>
</form>
</body>
</html>

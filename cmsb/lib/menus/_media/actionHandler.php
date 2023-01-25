<?php
// define globals
global $action; // $APP, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX;

addFilter('listHeader_displayLabel',   'media_cmsList_messageColumn', null, 3);
addFilter('listRow_displayValue',      'media_cmsList_messageColumn', null, 4);
addAction('record_preerase',           'media_preErase', null, 2);

addAction('list_postListTable',        'media_cmsList_uploadBox_placeholder', null, 2);
addAction('admin_footer',              'media_cmsList_uploadBox', null, 0);

// error checking
if (empty($GLOBALS['SETTINGS']['advanced']['useMediaLibrary'])) { // disable if media library not enabled.
  die("Media library not enabled.");
}

// Dispatch actions
if     ($action == 'dropzoneUpload')  { media_cmsList_uploadSave(); }

// Let regular actionHandler run
$REDIRECT_FOR_CUSTOM_MENUS_DONT_EXIT = true;
return;

//
function media_cmsList_uploadSave() {

  $errors = ""; 
  foreach (array_values($_FILES) as $uploadInfo) {
    
    // create new media record
    $colsToValues = [];
    $colsToValues['createdDate=']     = 'NOW()'; 
    $colsToValues['updatedDate=']     = 'NOW()'; 
    $colsToValues['createdByUserNum'] = $GLOBALS['CURRENT_USER']['num'];
    $colsToValues['updatedByUserNum'] = $GLOBALS['CURRENT_USER']['num'];
    $recordNum = mysql_insert('_media', $colsToValues);

    // add upload to record
    $tableName = "_media";
    $fileName  = "media"; 
    $errors   .= saveUpload($tableName, $fileName, $recordNum, '', $uploadInfo, $newUploadNums);
    
    if ($errors) {
      mysql_delete('_media', $recordNum);
    }
    
  }

}

//
function media_cmsList_uploadBox() {
  if (!in_array($GLOBALS['action'], ['list','eraseRecords'])) { return; } // only show on list menu

?>
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/dropzone/dropzone.min.js"></script>
  <link rel="stylesheet" href="<?php echo CMS_ASSETS_URL ?>/3rdParty/dropzone/dropzone.min.css">
  
  <form action="?" id="uploadBox" class="dropzone" style="background-color: #EEE; display: none">
    <input type="hidden" name="action"        value="dropzoneUpload">
    <input type="hidden" name="menu"          value="_media">
  
    <div class="dz-message needsclick" style="text-align: center">
      <i class="fas fa-cloud-download-alt" style="font-size: 50px"></i><br>
      <?php et("Drag and drop files here or click to upload"); ?>
    </div>
  </form>
  
  <?php
  
    // best guess of upload size in MB
    $maxUpload = (int) floor(fileUploadMaxSize()/1048576);
  
  ?>

  <!-- move upload box -->
  <script>
    // set options before dropzone initalizes
    Dropzone.options.uploadBox = {
      init: function() {
        // reload page on upload complete
        this.on("queuecomplete", function(file) { 
          window.location = "?menu=_media"; 
        });
      },
      <?php if (!empty($maxUpload)): ?>
      maxFilesize: <?php echo $maxUpload; ?>
      <?php endif; ?>
    };
    
    $(function() {

      // resize placeholder to match uploadBox
      var uploadBoxHeight = $('#uploadBox').outerHeight()
      $('#uploadBoxPlaceholder').outerHeight( uploadBoxHeight);
      
      // display uploadbox over placeholder
      var position = $('#uploadBoxPlaceholder').offset(); // position = { left: 42, top: 567 }
      $('#uploadBox').css({ position: "absolute" });
      $('#uploadBox').css(position);
      $('#uploadBox').innerWidth( $('#uploadBoxPlaceholder').innerWidth() - 4 ); // -4 for 2px border on dropzone element
      $('#uploadBox').css({ display: "block" });
    });    
    
  </script>

<?php
}

function media_cmsList_uploadBox_placeholder() {
?>

  <div id="uploadBoxPlaceholder" style="background-color: #EEE; height: 153px; margin-top: 15px;">
  </div>

<?php
}

//
function media_cmsList_messageColumn($displayValue, $tableName, $fieldname, $record = []) {
  if ($tableName != '_media') { return $displayValue; } // skip all by our table

  //
  if ($fieldname == '_filename_') {
    if (!$record) { return t("File Name"); }               // header - we detect the header hook by checking if the 4th argument is set
    // row cell - we detect the row cell by checking if $record is set
    $filename = $record['media'][0]['filename'] ?? '';
    $displayValue = $filename;
  }

  if ($fieldname == '_filetype_') {
    if (!$record) { return t("File Type"); } // header - we detect the header hook by checking if the 4th argument is set
    // row cell - we detect the row cell by checking if $record is set
    $filename = $record['media'][0]['filename'] ?? '';
    $displayValue = pathinfo($filename, PATHINFO_EXTENSION);
  }

  if ($fieldname == '_filesize_') {
    if (!$record) { return t("File Size"); } // header - we detect the header hook by checking if the 4th argument is set
    // row cell - we detect the row cell by checking if $record is set
    $filesize = $record['media'][0]['filesize'] ?? '';
    $displayValue = $filesize ? formatBytes($filesize) : 'unknown';
  }

  return $displayValue;
}

//
function media_preErase($tableName, $recordNumsAsCSV) {
  if ($tableName != '_media') { return $displayValue; } // skip all by our table
  
  // get nums as array
  $nums = preg_split("/,/", $recordNumsAsCSV);
  $nums = array_map('intval', $nums);
  $nums = array_unique($nums);
  
  //
  $isInUse = false;
  foreach ($nums as $mediaNum) {
    if (media_getUploadsUsingMedia($mediaNum)) {
      $isInUse = true;
      break;
    }
  }
  
  
  //
  
  //
  if ($isInUse) { 
    alert("You can't erase media if it's currently in use.");
    showInterface('default/list.php');
    exit;
  }
}

// eof
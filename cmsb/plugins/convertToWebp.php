<?php
/*
Plugin Name: Convert To WebP
Description: Convert GIF, JPG, and PNG image files to WebP.
Version: 1.01
CMS Version Required: 3.55
*/

$GLOBALS['CONVERT_TO_WEBP']['BATCH_MAX'] = 2;

pluginAction_addHandlerAndLink('Convert to WebP', 'convert_to_webp', 'admins');
addAction('section_unknownAction', 'convert_to_webp');
function convert_to_webp() {

  $_pluginAction = !empty($_REQUEST['_pluginAction']) ? $_REQUEST['_pluginAction'] : '';

  if(empty( $_pluginAction )) {
    $_pluginAction = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
  }

  if($_pluginAction != 'convert_to_webp') { return; }
  
  $adminUI = [
    'PAGE_TITLE' => [
      'Convert to WebP' => '',
    ]
  ];

  $totalCovertableFiles = 0;
  $totalNonConvertableFiles = 0;
  $convertableFileList = array();

  $uploadDir = getUploadPathPreview('dir', $GLOBALS['SETTINGS']['uploadDir'], 0);
  
  $mediaSchema = loadSchema('_media');
  list($mediaDir,) = getUploadDirAndUrl($mediaSchema['media']);
  
  $uploadRecords = mysql_select('uploads', 'filePath LIKE "%.gif" OR filePath LIKE "%.jpg" OR filePath LIKE "%.jpeg" OR filePath LIKE "%.png" ORDER BY num ASC');
  
  foreach($uploadRecords as $uploadRecord) {
    
    $fileDir = $uploadDir;
    if($uploadRecord['tableName'] == '_media') { $fileDir = $mediaDir; }
    
    foreach($GLOBALS['UPLOAD_FILE_PATH_FIELDS'] as $filePathField) {

      if(!empty($uploadRecord[$filePathField]) && file_exists($fileDir.$uploadRecord[$filePathField])) {

        $fileExtension = strtolower(pathinfo($uploadRecord[$filePathField]??'', PATHINFO_EXTENSION));

        if(in_array($fileExtension, array('gif', 'jpg', 'jpeg', 'png'))) {

          if($fileExtension != 'gif' || !image_isAnimatedGif($fileDir.$uploadRecord[$filePathField])) {

            $convertableFileList[] = $uploadRecord[$filePathField];
            $totalCovertableFiles++;
            
          }
          else {

            $totalNonConvertableFiles++;
            break;
            
          }
        }
        else {

          $totalNonConvertableFiles++;
          break;
          
        }
      }
    }
  }

  $adminUI['CONTENT'] = ob_capture(function() use($totalCovertableFiles, $totalNonConvertableFiles, $convertableFileList) {

    ?>

    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> &nbsp; It is highly recommended that you backup your database and upload folders before continuing.</div>

    <p style="margin: 20px 0;"><strong><?php echo number_format($totalCovertableFiles); ?> uploaded image file<?php echo $totalCovertableFiles != 1 ? 's' : ''; ?> found to convert.</strong> (animated GIFs will not be converted)</p>
    <p style="margin: 20px 0;"><?php echo number_format($totalNonConvertableFiles); ?> uploaded image file<?php echo $totalNonConvertableFiles != 1? 's have' : ' has'; ?> been ignored.</p>

    <p>
      <strong>Note:</strong> Running this plugin will convert all uploads and media library items to the  <a target="_blank" href="https://en.wikipedia.org/wiki/WebP">WebP Format</a>. This action cannot be reversed.
    </p>

    <?php

    if($totalCovertableFiles) {

      echo adminUI_button(array(

        'id' => 'button',
        'type' => 'button',
        'btn-type' => 'default',
        'label' => 'Begin Conversion',
        'onclick' => 'window.location="?menu=convert_to_webp&action=convert_to_webp_go";',

      ));

    }

  });

  adminUI($adminUI);

  exit;

}

addAction('section_unknownAction', 'convert_to_webp_go');
function convert_to_webp_go() {

  $_pluginAction = !empty($_REQUEST['_pluginAction']) ? $_REQUEST['_pluginAction'] : '';

  if(empty($_pluginAction)) {

    $_pluginAction = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
    
  }

  if($_pluginAction != 'convert_to_webp_go') { return; }
  
  $adminUI = [
    'PAGE_TITLE' => [
      'Convert to WebP' => '',
    ]
  ];


  $batchMax         = $GLOBALS['CONVERT_TO_WEBP']['BATCH_MAX'];
  $totalRecords     = mysql_count('uploads', 'num > 0 ORDER BY num ASC');
  $currentRecord    = isset($_GET['currentRecord']) ? $_GET['currentRecord'] : 0;
  $progressPercent  = round($currentRecord / $totalRecords * 100);
  $startTime        = isset($_GET['startTime']) ? $_GET['startTime'] : microtime(true);
  $extensionRegex   = '/\.[^.\/\\\\]+$/'; // match final portion of string that doesn't contain / \ or .

  $uploadRecords = mysql_select('uploads', 'num > 0 ORDER BY num ASC LIMIT '.$currentRecord.', '.$batchMax);

  // imagemagick wants the full "system" path
  $uploadDir = getUploadPathPreview('dir', $GLOBALS['SETTINGS']['uploadDir'], 0);

  $mediaSchema = loadSchema('_media');
  list($mediaDir,) = getUploadDirAndUrl($mediaSchema['media']);

  foreach($uploadRecords as $uploadRecord) {

    $fileDir = $uploadDir;
    if($uploadRecord['tableName'] == '_media') { $fileDir = $mediaDir; }
    
    // we need to check each of the upload/thumb directories
    foreach($GLOBALS['UPLOAD_FILE_PATH_FIELDS'] as $index => $filePathField) {

      $urlPathField = $GLOBALS['UPLOAD_URL_PATH_FIELDS'][ $index ];
      
      $fileExtension = strtolower(pathinfo( $uploadRecord[ $filePathField ]??'', PATHINFO_EXTENSION ));

      if (!empty( $uploadRecord[ $filePathField ])) {

        if (in_array($fileExtension, array('gif', 'jpg', 'jpeg', 'png'))) {

          $sourceFile = $fileDir.$uploadRecord[$filePathField];
          $targetFile = $fileDir.preg_replace($extensionRegex, '.webp', $uploadRecord[ $filePathField ]);

          if ($fileExtension != 'gif' || !image_isAnimatedGif($sourceFile)) {

            if (image_convertToWebp( $sourceFile, $targetFile, 'gd' ) !== false) {

              // verify that the new file exists
              if (file_exists($targetFile)) {

                $colsToValues = [
                  $filePathField => preg_replace( $extensionRegex, '.webp', $uploadRecord[ $filePathField ]),
                  $urlPathField  => preg_replace( $extensionRegex, '.webp', $uploadRecord[ $urlPathField ]),
                ];

                // update the upload record in the database
                mysql_update('uploads', $uploadRecord['num'], null, $colsToValues);
                
                // delete the original image file
                unlink($sourceFile);
                
              }
            }
            else {
              // unable to convert
            }
            
          }
          else {
            // animated gif ignored
          }
          
        }
      }
    }
  }

  $adminUI['CONTENT'] = ob_capture(function() use($batchMax, $totalRecords, $currentRecord, $progressPercent, $startTime, $uploadRecords) {

    if ($currentRecord + $batchMax < $totalRecords || $progressPercent < 100) {
      ?>

      <p style="margin: 20px 0;">Processing...</p>

      <?php
    }
    else {
      ?>

      <p style="margin: 20px 0;">Done.</p>

      <?php
    }

    ?>
    <div class="progress">
      <div class="progress-bar" role="progressbar" style="width: <?php echo $progressPercent; ?>%" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <?php

    if($currentRecord + $batchMax < $totalRecords || $progressPercent < 100) {

      echo adminUI_button(array(

        'id' => 'button',
        'type' => 'button',
        'btn-type' => 'danger',
        'label' => 'Cancel Conversion',
        'onclick' => 'window.location="?menu=convert_to_webp&action=convert_to_webp";',

      ));

      ?>

      <script>

        window.setTimeout(function() {

          window.location.href = "?menu=convert_to_webp&action=convert_to_webp_go&currentRecord=<?php echo $currentRecord + count($uploadRecords); ?>&startTime=<?php echo $startTime; ?>";

        }, 100);

      </script>

      <?php
    }
    else {
      ?>

      <div class="alert alert-success"><i class="fa fa-check"></i> &nbsp; Conversion completed in <?php echo round(microtime(true) - $startTime, 2); ?> seconds.</div>

      <?php
    }

  });

  adminUI($adminUI);

  exit();
  
}

addAction('plugin_activate', 'convert_to_webp_plugin_activate');
function convert_to_webp_plugin_activate() {

  // create the schema
  $schema = array(

    '_description' => '',
    '_detailPage' => '',
    '_filenameFields' => '',
    '_iframeHeight' => '',
    '_indent' => '0',
    '_linkMessage' => '',
    '_linkTarget' => '',
    '_tableName' => 'convert_to_webp',
    'menuHidden' => '1',
    'menuName' => 'Convert to WebP',
    'menuOrder' => '2',
    'menuPrefixIcon' => '',
    'menuType' => 'single',

  );

  // save the schema
  saveSchema('convert_to_webp', $schema);
  
}

?>
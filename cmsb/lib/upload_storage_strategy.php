<?php

function upload_storage_strategy($newInstance = null) {
  static $instance;
  
  // caller can set instance by providing one
  if ($newInstance) {
    
    // make sure provided object implements our interface
    $interfaces = class_implements($newInstance);
    if (!@$interfaces['iUploadStorageStrategy']) {
      die(__FUNCTION__ . ": object must implement iUploadStorageStrategy");
    }
    
    // set instance
    $instance = $newInstance;
  }
  
  // set default instance if required
  if (!$instance) {
    $instance = new DefaultUploadStorageStrategy();
  }
  
  return $instance;
}

interface iUploadStorageStrategy {
  public function storeMainFile($mainUploadWorkingPath, $desiredFilename, $fieldSchema, &$uploadRecordDetails);
  public function storeThumbFile($thumbWorkingPath, $fieldSchema, $suffix, &$uploadRecordDetails);
  public function makeUploadPathAndUrlAbsolute(&$record, $fieldSchema);
  public function removeUploadsForRecord($record);
  public function resizeThumb($uploadRecord, $thumbNum, $maxWidth, $maxHeight);
}

class DefaultUploadStorageStrategy implements iUploadStorageStrategy {
  
  public function storeMainFile($mainUploadWorkingPath, $desiredFilename, $fieldSchema, &$uploadRecordDetails) {
    
    // determine ultimate home for file
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
    
    // choose a uniqueNumberedFilename_123.ext
    list($finalFilename, $finalFilepath) = _saveUpload_chooseUniqueNumberedFilename( $desiredFilename, $uploadDir );
    
    // move the file there
    rename($mainUploadWorkingPath, $finalFilepath) || die("Error moving uploaded file! " .@$php_errormsg);
    
    // determine url
    $urlPath = _getUploadUrlFromPath($fieldSchema, $finalFilepath);
    
    // store the relative filePath and relative urlPath to be saved to the upload record
    $uploadRecordDetails['filePath'] = removeUploadPathPrefix($finalFilepath, $uploadDir);
    $uploadRecordDetails['urlPath']  = removeUploadPathPrefix($urlPath,       $uploadUrl);
    
  }
  
  public function storeThumbFile($thumbWorkingPath, $fieldSchema, $suffix, &$uploadRecordDetails) {
    
    // determine ultimate home for file (based on location of main upload file)
    $mainUploadRelativeFilepath  = $uploadRecordDetails['filePath'];
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
    $finalThumbpath = $uploadDir . preg_replace("|([^/]+)$|", "thumb$suffix/$1", $mainUploadRelativeFilepath);
    
    // create final thumb path dir if it doesn't exist. ie, if upload directory was changed and the thumb folders don't exist
    $finalThumbpathDir = dirname($finalThumbpath);
    if (!file_exists($finalThumbpathDir)) {
      mkdir_recursive($finalThumbpathDir) || die("Error creating dir '" .htmlencode($finalThumbpathDir). "'.  Check permissions or try creating directory manually.");
    }

    // move the file there
    rename($thumbWorkingPath, $finalThumbpath) || die("Error moving uploaded file! " .@$php_errormsg);
    
    // determine url
    $thumbUrl = _getUploadUrlFromPath($fieldSchema, $finalThumbpath);
    $thumbUrl = preg_replace("|([^/]+)$|", "thumb$suffix/$1", $thumbUrl);
    
    // blank out values if thumb doesn't exist
    if (!file_exists($finalThumbpath)) {
      $finalThumbpath = '';
      $thumbUrl       = '';
    }
    
    // store the relative filePath and relative urlPath to be saved to the upload record
    $uploadRecordDetails['thumbFilePath' . $suffix] = removeUploadPathPrefix($finalThumbpath, $uploadDir);
    $uploadRecordDetails['thumbUrlPath'  . $suffix] = removeUploadPathPrefix($thumbUrl,       $uploadUrl);
    
  }
  
  public function makeUploadPathAndUrlAbsolute(&$record, $fieldSchema) {
    
    // get custom upload path
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);

    // make paths absolute
    foreach ($GLOBALS['UPLOAD_FILE_PATH_FIELDS'] as $filePathField) {
      $record[$filePathField] = addUploadPathPrefix($record[$filePathField], $uploadDir);
    }
    foreach ($GLOBALS['UPLOAD_URL_PATH_FIELDS'] as $urlPathField) {
      $record[$urlPathField] = addUploadPathPrefix($record[$urlPathField], $uploadUrl);
      $record[$urlPathField] = str_replace(' ', '%20', $record[$urlPathField]); // replace spaces to avoid XHTML validation errors
    }
    
  }
  
  public function removeUploadsForRecord($record) {
    
    // get custom upload path
    $schema = loadSchema($record['tableName']);
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($schema[$record['fieldName']]);

    foreach ($GLOBALS['UPLOAD_FILE_PATH_FIELDS'] as $filePathField) {
      $filepath = addUploadPathPrefix(@$record[$filePathField], $uploadDir); // make path absolute

      $filepath = applyFilters('upload_removeFilePath', $filepath, $record);
      
      // don't erase filepaths from shared media files
      if (!empty( $record['mediaNum'] )) { continue; }
      
      if (!$filepath || !file_exists($filepath)) { continue; }
      if (!@unlink($filepath)) {
        $error  = "Unable to remove file '" .htmlencode($filepath). "'\n\n";
        $error .= "Please ask your server administrator to check permissions on that file and directory.\n\n";
        $error .= "The PHP error message was: " .@$php_errormsg. "\n";
        trigger_error($error, E_USER_ERROR);
      }
    }

  }
  
  public function resizeThumb($uploadRecord, $thumbNum, $maxWidth, $maxHeight, $crop = false) {

    // get uploadDir and uploadUrl
    $tableName = $uploadRecord['tableName'];
    $schema    = loadSchema($tableName);
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($schema[ $uploadRecord['fieldName'] ]);

    // get upload's absolute filepath
    $absoluteFilepath = addUploadPathPrefix($uploadRecord['filePath'], $uploadDir); // make path absolute

    // error checking
    if (!file_exists($absoluteFilepath)) {
      $error  = "Upload doesn't exist '$absoluteFilepath'!<br>\n";
      $error .= "Found in: {$tableName}, {$uploadRecord['fieldName']}, record {$uploadRecord['recordNum']}.";
      die($error);
    }

    ### resize image
    if (isImage($absoluteFilepath)) {
      $thumbSavePath = preg_replace("|([^/]+)$|", "thumb$thumbNum/$1", $absoluteFilepath);
      $thumbUrlPath  = preg_replace("|([^/]+)$|", "thumb$thumbNum/$1", $uploadRecord['urlPath']);

      // erase old thumbnail
      if (file_exists($thumbSavePath)) {
        @unlink($thumbSavePath) || die("Can't erase old thumbnail '$thumbSavePath': " .@$php_errormsg);
      }

      // create new thumbnail
      list($thumbWidth, $thumbHeight) = saveResampledImageAs($thumbSavePath, $absoluteFilepath, $maxWidth, $maxHeight, $crop);
      
      return [$thumbSavePath, $thumbUrlPath, $thumbWidth, $thumbHeight];
    }
    else {
      return [null, null, null, null];
    }
  }

}


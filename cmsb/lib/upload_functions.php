<?php

$GLOBALS['UPLOAD_FILE_PATH_FIELDS'] = array('filePath', 'thumbFilePath', 'thumbFilePath2', 'thumbFilePath3', 'thumbFilePath4');
$GLOBALS['UPLOAD_URL_PATH_FIELDS']  = array('urlPath',  'thumbUrlPath',  'thumbUrlPath2',  'thumbUrlPath3',  'thumbUrlPath4');

require_once "upload_storage_strategy.php";

//
function addUploadsToRecords(&$rows, $tableName = null, $debugSql = FALSE, $preSaveTempId = null) {
  global $TABLE_PREFIX;

  // get recordNums
  $recordNums = array_pluck($rows, 'num');
  if (!$recordNums) { return; }

  // get tableName from record(s) if not supplied
  if (!$tableName) { $tableName = @$rows[0]['_tableName']; }

  // get upload fields
  $uploadFields = [];
  $uploadFieldsAsCSV = '';
  $schema = loadSchema($tableName);
  foreach ($schema as $fieldname => $fieldSchema) {
    if (!is_array($fieldSchema))           { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata
    if (@$fieldSchema['type'] != 'upload') { continue; }  // skip all but upload fields
    if ($uploadFieldsAsCSV) { $uploadFieldsAsCSV .= ','; }
    $uploadFields[]     = $fieldname;
    $uploadFieldsAsCSV .= "'$fieldname'";
  }

  // load uploads
  $uploadsByNumAndField = [];
  $recordNumsAsCSV      = implode(',', $recordNums);
  if ($uploadFieldsAsCSV) {
    $where   = "tableName = '" .mysql_escape($tableName). "' AND\n";
    $where  .= "fieldName IN ($uploadFieldsAsCSV) AND\n";
    if ($preSaveTempId) { $where  .= mysql_escapef('preSaveTempId = ?', $preSaveTempId); }
    else                { $where  .= "recordNum IN ($recordNumsAsCSV)\n"; }
    
    
    $where  .= " ORDER BY `order`, num";
    if ($debugSql) { print "<xmp>SELECT * FROM `" .getTableNameWithPrefix('uploads'). "` WHERE $where</xmp>"; }

    $uploads = mysql_select('uploads', $where);
  
    // replace uploads that reference media files with data from that media file
    $uploads = media_replaceUploadsWithMedia($uploads); 

    //
    foreach ($uploads as $record) {
      _addUploadPseudoFields( $record, $schema, $record['fieldName'] );
      $fieldName = $record['_fieldName_original'] ?? $record['fieldName'];
      $uploadsByNumAndField[$record['recordNum']][$fieldName][] = $record;
    }
  }
  
  // add uploads to records
  foreach (array_keys($rows) as $index) {
    $record = &$rows[$index];

    foreach ($uploadFields as $fieldname) {
      $record[$fieldname] = [];
      $uploadsArray    = @$uploadsByNumAndField[$record['num']][$fieldname];
      if ($uploadsArray) { $record[$fieldname] = $uploadsArray; }
    }
  }

}

//
function addUploadsToRecord(&$record, $tableName = null, $debugSql = FALSE, $preSaveTempId = null) {
  if (!$record) { return; }
  $rows = array($record);
  addUploadsToRecords($rows, $tableName, $debugSql, $preSaveTempId);
  $record = $rows[0];
}


// remove a single upload (helper function called by external upload forms)
function removeUpload($uploadNum, $recordNum, $preSaveTempId) {
  // create where
  $where = mysql_escapef("num = ?", $uploadNum);
  if      ($recordNum)     { $where .= mysql_escapef('AND recordNum     = ?', $recordNum); }
  else if ($preSaveTempId) { $where .= mysql_escapef('AND preSaveTempId = ?', $preSaveTempId); }
  else                     { die("No value specified for 'recordNum' or 'preSaveTempId'!"); }

  removeUploads($where);
}

// remove one or more uploads given a where clause
function removeUploads($where) {
  global $TABLE_PREFIX;

  // remove upload files
  $query  = "SELECT * FROM `{$TABLE_PREFIX}uploads` WHERE $where";
  $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $count  = 0;
  while ($row = $result->fetch_assoc()) {

    $count += 1;
    
    upload_storage_strategy()->removeUploadsForRecord($row);
    
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  // remove upload records
  mysqli()->query("DELETE FROM `{$TABLE_PREFIX}uploads` WHERE $where") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

  return $count;
}

// helper function called by external upload forms
// function assumes upload fields are named after the target field they're for with [] on the end. eg: <input type="file" name="photos[]">
// Also adds 'fieldname' field to uploadinfo with name of target field
// Ref: http://www.php.net/manual/en/reserved.variables.files.php#89674
function getUploadInfoArrays() {
  $uploadInfoArrays = [];
  foreach (array_keys($_FILES) as $fieldname) {
    foreach (array_keys($_FILES[$fieldname]['name']) as $index) {
      $uploadInfoArrays[] = array(
        '_fieldname' => $fieldname,
        'name'       => $_FILES[$fieldname]['name'][$index],
        'type'       => $_FILES[$fieldname]['type'][$index],
        'tmp_name'   => $_FILES[$fieldname]['tmp_name'][$index],
        'error'      => $_FILES[$fieldname]['error'][$index],
        'size'       => $_FILES[$fieldname]['size'][$index],
      );
    }
  }

  return $uploadInfoArrays;
}


// Save a copy of local file to the uploads table
function saveUploadFromFilepath($tablename, $fieldname, $recordNum, $preSaveTempId, $filepath) {

  // copy local file
  $tmpFilepath = tempnam(DATA_DIR, 'temp_upload_');
  copy($filepath, $tmpFilepath);
  
  // remove temp file on shutdown (do this now so we don't leave an orphan file if we die() with an error
  unlink_on_shutdown($tmpFilepath);

  // fake uploadInfo (like $_FILE would have contained)
  $fakeUploadInfo = array(
    'name'     => basename($filepath), // filename to use when adopting upload
    'tmp_name' => $tmpFilepath,        // filepath to current file
    'error'    => '',                  //
    'size'     => 1,                   // bypass 0-byte size error check
  );
  $errors = saveUpload($tablename, $fieldname, $recordNum, $preSaveTempId, $fakeUploadInfo, $newUploadNums, true);

  //
  return $errors;
}

// Save a form upload, $uploadInfo is $_FILES['upload-form-field-name'], returns errors on fail
// $errors = saveUpload(...);
function saveUpload($tablename, $fieldname, $recordNum, $preSaveTempId, $uploadInfo, &$newUploadNums, $skipUploadSecurityCheck = false) {
  if (!$uploadInfo['name']) { return; } // skip upload fields that were left blank (such as when you have 10 upload fields and select files in 2 of them)
  
  $schema      = loadSchema($tablename);
  $fieldSchema = $schema[$fieldname];
  
  $workingDir  = DATA_DIR . '/temp';
  
  // check for upload errors
  $errors = _saveUpload_getErrors($tablename, $fieldname, $uploadInfo, $recordNum, $preSaveTempId);
  if ($errors) { return $errors; }
  
  // are we dealing with a chunked upload?
  $mainUploadWorkingPath = '';
  $isChunked = @$_REQUEST['chunks'] > 1;
  if ($isChunked) {
    
    // move first chunk to working directory, append subsequent chunks to same file, finally report when the last file has been appended
    list($errors, $isLastChunk, $mainUploadWorkingPath) = _saveUpload_processChunking($uploadInfo, $workingDir, $fieldSchema, $skipUploadSecurityCheck);
    if ($errors)        { return "ERROR: " . htmlencode($errors); }
    if (!$isLastChunk)  { return "PROCEED WITH NEXT CHUNK"; }
    
    unlink_on_shutdown($mainUploadWorkingPath);
    
  }
  
  // ...not chunked?
  else {
    
    if (!$skipUploadSecurityCheck && !is_uploaded_file($uploadInfo['tmp_name'])) {
      return "Error saving '" .htmlencode($uploadInfo['name']). "', file wasn't uploaded properly.<br>\n";
    }
    
    // move file into working directory
    $mainUploadWorkingPath = _saveUpload_moveTempUploadFile($uploadInfo['tmp_name'], $workingDir, $skipUploadSecurityCheck);
    unlink_on_shutdown($mainUploadWorkingPath);
    
  }
  
  // fix file permission if needed
  _saveUpload_fixFilePermission($mainUploadWorkingPath);
  
  // note the following validation steps must be done after moving the file to the working directory because open_basedir may limit access to the temp directory

  // check for filetype/extension issues
  $errors = _saveUpload_validateUploadFileExt($uploadInfo, $fieldSchema, $mainUploadWorkingPath);
  if ($errors) { return $errors; }
  
  // Convert CMYK to RGB if needed
  image_convertCMYKToRGBIfNeeded($mainUploadWorkingPath);

  // check for valid content in image and swf/swc files
  list($error, $isImage) = _saveUpload_checkFileIsValid($mainUploadWorkingPath, $uploadInfo);
  if ($error && !_saveUpload_allowAllExt($fieldSchema)) {
    @unlink($mainUploadWorkingPath);
    return $error;
  }
  
  // prepare record data to be inserted when resizing and thumbs are complete
  $uploadRecordDetails = [
    'tableName'     => $tablename,
    'fieldName'     => $fieldname,
    'recordNum'     => $recordNum,
    'preSaveTempId' => $preSaveTempId,
    'storage'       => '',
  ];
  
  // [experimental] rotate uploaded JPEG file if EXIF Orientation flag has been set
  // explanation: certain cameras save rotated JPEG content and use a EXIF flag to tell users the image must be rotated (90, -90, or 180)
  // our resampling code, which relies on GD, doesn't respect or preserve that flag, which can result in certain uploaded files being saved with incorrect rotation
  // Note that: doing rotations like the following before resampling can use a lot of memory and cause out-of-memory errors on reasonably large image files
  $fixJpegExifOrientation = true; // v3.15 now set to true by default - will use more memory when rotated images are uploaded
  if ($fixJpegExifOrientation) {
    _image_fixJpegExifOrientation($mainUploadWorkingPath);
  }
  
  // convert image (gif, jpg, png) to webp (except animated gif files)
  $uploadExt = _saveUpload_getExtensionFromFileData($mainUploadWorkingPath);
  if (!empty($GLOBALS['SETTINGS']['advanced']['convertUploadsToWebp']) and in_array($uploadExt, array('gif', 'jpg', 'png'))) {
    
    if ($uploadExt != 'gif' || !image_isAnimatedGif($mainUploadWorkingPath)) {
      
      if (image_convertToWebp($mainUploadWorkingPath, $mainUploadWorkingPath)) {
        
        list(, , $type) = getimagesize($mainUploadWorkingPath);
        
        $uploadInfo['name'] = $uploadInfo['name'] .= '.webp';
        $uploadInfo['type'] = $type;
        $uploadInfo['size'] = filesize($mainUploadWorkingPath);
      }
    }
  }

  // resize/resample image?
  list($width, $height, $type) = [ null, null, null ];
  $isSvgImage = isImage_SVG($mainUploadWorkingPath);
  if ($isImage && !$isSvgImage) {
    // for images except SVG, use saveResampledImageAs() or getimagesize()
    $resizeIfNeeded = $fieldSchema['resizeOversizedImages'] && $fieldSchema['maxImageHeight'] && $fieldSchema['maxImageWidth'];
    if ($resizeIfNeeded) {
      list($width, $height, $type) = saveResampledImageAs($mainUploadWorkingPath, $mainUploadWorkingPath, $fieldSchema['maxImageWidth'], $fieldSchema['maxImageHeight']);
    }
    else {
      list($width, $height, $type) = @getimagesize($mainUploadWorkingPath);
    }
  }
  elseif ($isImage && $isSvgImage) {
    // for SVG images, we don't need to resample the image to resize it because they are scalable
    // ... we just need to set the width and height
    
    // note: we're not checking $resizeIfNeeded because we don't have the actual width and height of the SVG file to compare with
    // ... so let's set the size manually to 600x800
    $width  = '600';
    $height = '800';
  }
  // record image details for saving to the uploads table later
  $uploadRecordDetails['width']     = $width;
  $uploadRecordDetails['height']    = $height;
  $uploadRecordDetails['filesize']  = filesize($mainUploadWorkingPath);
  $uploadRecordDetails['imageType'] = $type;   // record this in case Upload Storage Strategy wants it
  
  // create thumbnails
  $workingThumbs = [];
  if ($isImage) {
    foreach (_getThumbFieldSuffixes() as $suffix) {
      list($createThumb, $cropThumb, $maxThumbHeight, $maxThumbWidth) = _getThumbDetails($suffix, $fieldSchema);
      if (!$createThumb) { continue; }
      
      $thumbWorkingPath = tempnam($workingDir, 'thumb_');
      _saveUpload_fixFilePermission($thumbWorkingPath);
      unlink_on_shutdown($thumbWorkingPath);
      
      if ($isSvgImage) {
        // SVG image doesn't need to be resized/resampled, we just need to copy the main file and set the width and height
        copy($mainUploadWorkingPath, $thumbWorkingPath) || die(__FUNCTION__ . ": error copying image '$mainUploadWorkingPath' - " . @$php_errormsg);
        $thumbWidth  = $maxThumbWidth;
        $thumbHeight = $maxThumbHeight;
      }
      else{
        // resample other image file type
        list($thumbWidth, $thumbHeight) = saveResampledImageAs($thumbWorkingPath, $mainUploadWorkingPath, $maxThumbWidth, $maxThumbHeight, $cropThumb);
      }
      
      $workingThumbs[$suffix] = [
        'path'   => $thumbWorkingPath,
        'width'  => $thumbWidth,
        'height' => $thumbHeight,
      ];
      
    }
  }
  
  // move main upload to long-term storage
  upload_storage_strategy()->storeMainFile($mainUploadWorkingPath, $uploadInfo['name'], $fieldSchema, $uploadRecordDetails);
  if (!array_key_exists('filePath', $uploadRecordDetails)) { die("upload_storage_strategy()->storeMainFile() did not set 'filePath' key"); }
  if (!array_key_exists('urlPath',  $uploadRecordDetails)) { die("upload_storage_strategy()->storeMainFile() did not set 'urlPath' key"); }
  
  list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
  $finalMainUploadPath         = fixSlashes("{$uploadDir}/{$uploadRecordDetails['filePath']}");
  
  // pre-thumbnail hook for watermarking plugin
  doAction('upload_save', array($tablename, $fieldname, $finalMainUploadPath, $uploadRecordDetails));
  
  // move thumbs to long-term storage
  foreach ($workingThumbs as $suffix => $thumbDetails) {
    
    upload_storage_strategy()->storeThumbFile($thumbDetails['path'], $fieldSchema, $suffix, $uploadRecordDetails);
    
    // hook for watermarking plugin
    $finalThumbUploadPath = fixSlashes("{$uploadDir}/thumb{$suffix}/{$uploadRecordDetails['filePath']}");
    doAction('upload_thumbnail_save', array($tablename, $fieldname, $suffix, $finalThumbUploadPath, $uploadRecordDetails));
    
    // record thumb image details for saving to the uploads table later
    $uploadRecordDetails['thumbWidth'  . $suffix] = $thumbDetails['width'];
    $uploadRecordDetails['thumbHeight' . $suffix] = $thumbDetails['height'];
  }

  // add upload record to database
  $newUploadNum = _saveUpload_addToDatabase($uploadRecordDetails);

  // add to list of new upload nums
  if ($newUploadNum) {
    if (!is_array($newUploadNums)) { $newUploadNums = []; }
    array_push($newUploadNums, $newUploadNum);
  }

  // post-thumbnail hook for watermarking plugin
  doAction('upload_saved', $tablename, $fieldname, $recordNum, $newUploadNum);
}





// get the (lowercased) extension of the submitted upload file
// $fileExt = _saveUpload_getExtensionFromFileName( $uploadInfo['name'] ); 
function _saveUpload_getExtensionFromFileName($uploadName) {
  $fileExt = pathinfo(strtolower($uploadName), PATHINFO_EXTENSION);
  return $fileExt;
}


// get the file extension from the data, must be called on completely saved (not partially saved) files
// $dataFileExt = _saveUpload_getExtensionFromFileData( $uploadInfo['tmp_name'] ); 
function _saveUpload_getExtensionFromFileData( $uploadFilepath ) {

  // get file "imageType" which contains one of the IMAGETYPE_XXX constants indicating the type of the file.
  list($width, $height, $imageType) = @getimagesize($uploadFilepath);
  
  //
  $ext = '';
  if     ($imageType == IMAGETYPE_GIF)     { $ext = 'gif'; }
  elseif ($imageType == IMAGETYPE_JPEG)    { $ext = 'jpg'; }
  elseif ($imageType == IMAGETYPE_PNG)     { $ext = 'png'; }
  elseif ($imageType == IMAGETYPE_WEBP)    { $ext = 'webp'; }
  elseif ($imageType == IMAGETYPE_SWF)     { $ext = 'swf'; }
  elseif ($imageType == IMAGETYPE_PSD)     { $ext = 'psd'; }
  elseif ($imageType == IMAGETYPE_BMP)     { $ext = 'bmp'; }
  elseif ($imageType == IMAGETYPE_TIFF_II) { $ext = 'tiff'; }
  elseif ($imageType == IMAGETYPE_TIFF_MM) { $ext = 'tiff'; }
  elseif ($imageType == IMAGETYPE_JPC)     { $ext = 'jpc'; }
  elseif ($imageType == IMAGETYPE_JP2)     { $ext = 'jp2'; }
  elseif ($imageType == IMAGETYPE_JPX)     { $ext = 'jpx'; }
  elseif ($imageType == IMAGETYPE_JB2)     { $ext = 'jb2'; }
  elseif (defined('IMAGETYPE_SWC') && // IMAGETYPE_SWC constant isn't always defined by GD even though it's listed here: http://php.net/manual/en/image.constants.php
          $imageType == IMAGETYPE_SWC)     { $ext = 'swc'; }
  elseif ($imageType == IMAGETYPE_IFF)     { $ext = 'iff'; }
  elseif ($imageType == IMAGETYPE_WBMP)    { $ext = 'wbmp'; }
  elseif ($imageType == IMAGETYPE_XBM)     { $ext = 'xbm'; }
  elseif ($imageType == IMAGETYPE_ICO)     { $ext = 'swc'; }
  elseif (isImage_SVG($uploadFilepath))    { $ext = 'svg'; } // getimagesize doesn't return the image type of SVG images so use isImage_SVG() instead
  
  //
  return $ext;
}


// check if file extension is allowed by file upload rules in schema
// $isValidExt = _saveUpload_hasValidExt($uploadInfo['name'], $fieldSchema); 
function _saveUpload_hasValidExt($uploadName, $fieldSchema) {

  // check for allowed file extension
  $fileExt       = _saveUpload_getExtensionFromFileName( $uploadName );
  $validExtArray = preg_split("/\s*\,\s*/", strtolower($fieldSchema['allowedExtensions']));  
  $isValidExt    = in_array('*', $validExtArray) || ($fileExt && in_array($fileExt, $validExtArray));

  //
  return $isValidExt;
}

//
function _saveUpload_allowAllExt($fieldSchema) {
  $validExtArray = preg_split("/\s*\,\s*/", strtolower($fieldSchema['allowedExtensions']));  
  $allAllExt     = in_array('*', $validExtArray);

  //
  return $allAllExt;
}

// list($errors, $isLastChunk, $mainUploadWorkingPath) = _saveUpload_processChunking($uploadInfo, $workingDir, $fieldSchema, $skipUploadSecurityCheck);
function _saveUpload_processChunking(&$uploadInfo, $workingDir, $fieldSchema, $skipUploadSecurityCheck) {
  
  // prepare return values
  $errors                 = '';
  $mainUploadWorkingPath  = '';
  
  // plupload sets $uploadInfo['name'] to "blob", the real (client-desired) filename is in $_REQUEST['name']
  $uploadInfo['name'] = @$_REQUEST['name'];
  
  // check to make sure file passes "is_uploaded_file" (unless we're skipping that check)
  if (!$skipUploadSecurityCheck && !is_uploaded_file($uploadInfo['tmp_name'])) {
    return "Error saving '" . htmlencode($uploadInfo['name']) . "', file wasn't uploaded properly.<br>\n";
  }
  
  // determine if we are processing the first or last chunk
  $chunkTotal   = @$_REQUEST['chunks'];
  $chunkIndex   = @$_REQUEST['chunk'];
  $isFirstChunk = ($chunkIndex == 0);
  $isLastChunk  = ($chunkIndex == $chunkTotal - 1);
  
  // first chunk of upload should create a file in processing/
  if ($isFirstChunk) {
    
    // promote temp file
    $mainUploadWorkingPath = _saveUpload_moveTempUploadFile($uploadInfo['tmp_name'], $workingDir, $skipUploadSecurityCheck);
    
    // store $mainUploadWorkingPath in session (since future calls to _saveUpload_chooseUniqueNumberedFilename would generate a new, unique filename) and stop processing the upload for now...
    $_SESSION['uploadChunks'][$uploadInfo['name']]['mainUploadWorkingPath'] = $mainUploadWorkingPath;
    
    // done for now, we need to wait for more chunk(s)
  }
  
  // subsequent chunks should append to the target file
  else {
    
    // load saveAsFilenameAndPath from session (saved when the first chunk was processed)
    $mainUploadWorkingPath = @$_SESSION['uploadChunks'][$uploadInfo['name']]['mainUploadWorkingPath'];
    if (!$mainUploadWorkingPath) { return ["Chunked uploading failed -- session problem?", $isLastChunk, $mainUploadWorkingPath]; }
    
    // append subsequent chunk
    $errors = _saveUpload_appendChunk($mainUploadWorkingPath, $uploadInfo, $fieldSchema);
    if ($errors) {
      @unlink($mainUploadWorkingPath);
      unset($_SESSION['uploadChunks'][$uploadInfo['name']]);
      return ["Chunked uploading failed -- " . $error, $isLastChunk, $mainUploadWorkingPath];
    }
    
    // if this is the last chunk, clean up the session...
    if ($isLastChunk) {
      unset($_SESSION['uploadChunks'][$uploadInfo['name']]);
    }
    
  }
  
  return [$errors, $isLastChunk, $mainUploadWorkingPath];
}

// move an uploaded temp file to a working directory
// $saveAsFilepath = _saveUpload_moveTempUploadFile($uploadedAsFilename, $targetDir, $skipUploadSecurityCheck);
function _saveUpload_moveTempUploadFile($uploadedAsFilename, $targetDir, $skipUploadSecurityCheck) {
  
  // move and rename upload (from system /tmp/ folder to our /uploads/ folder)
  $saveAsFilepath = tempnam($targetDir, 'upload_');
  if ($skipUploadSecurityCheck) {
    rename($uploadedAsFilename, $saveAsFilepath) || die("Error moving uploaded file! " .@$php_errormsg);
  }
  else {
    move_uploaded_file($uploadedAsFilename, $saveAsFilepath) || die("Error moving uploaded file! " .@$php_errormsg);
  }
  
  return $saveAsFilepath;
}

// fix file permission
function _saveUpload_fixFilePermission($filePath){
  // Set permissions (make upload readable and writable)
  // Note: Sometimes when upload are create in /tmp/ by PHP they don't the correct read and write permissions
  $permissions = fileperms($filePath);
  $isReadable  = (($permissions | 0444) == $permissions); // has read bits for User, Group, and World
  $isWritable  = (($permissions | 0222) == $permissions); // has write bits for User, Group, and World
  if (!$isReadable) {
    $mode = octdec($GLOBALS['SETTINGS']['advanced']['permissions_files']);
    chmod($filePath, $mode) || die("Error changing permissions on '" .htmlspecialchars($filePath). "'! " .@$php_errormsg);
  }
}

// $error = _saveUpload_appendChunk($saveAsFilepath, $uploadInfo, $fieldSchema);
function _saveUpload_appendChunk($saveAsFilepath, $uploadInfo, $fieldSchema) {
  
  // first check to make sure this chunk doesn't break filesize limit
  $currentSize  = filesize($saveAsFilepath);
  $newChunkSize = (int) $uploadInfo['size'];
  if ($fieldSchema['checkMaxUploadSize'] && $fieldSchema['maxUploadSizeKB'] < ceil(($currentSize + $newChunkSize) / 1024)) {
    return "File '" . htmlspecialchars($uploadInfo['name']) . "' exceeds max upload size (max: {$fieldSchema['maxUploadSizeKB']}K).<br>\n";
  }
  
  // append new chunk to target file
  $out = fopen($saveAsFilepath, 'ab');         if (!$out) { die("Error appending uploaded file chunk! " .@$php_errormsg); }
  $in  = fopen($uploadInfo['tmp_name'], 'rb'); if (!$in)  { die("Error reading uploaded file chunk! " .@$php_errormsg); }
  while ($buff = fread($in, 4096)) {
    fwrite($out, $buff);
  }
  @fclose($out);
  @fclose($in);
  
  return '';
}

// list($error, $isImage) = _saveUpload_checkFileIsValid($uploadFilepath, $uploadInfo);
function _saveUpload_checkFileIsValid($uploadFilepath, $uploadInfo) {
  
  // get file extension from file and guessed extension from data
  $plannedFileExt = _saveUpload_getExtensionFromFileName($uploadInfo['name']);
  $dataFileExt    = _saveUpload_getExtensionFromFileData($uploadFilepath); // blank if data isn't recognized
  $imageData      = @getimagesize($uploadFilepath);
  
  // check for invalid CMYK images that can't be displayed in all browsers
  if (@$imageData['channels'] == 4) {
    $errors = sprintf(t("File '%s' isn't valid (CMYK isn't browser-safe)."), htmlspecialchars($uploadInfo['name']));
    return [$errors, false];
  }
  
  // iOS devices save jpg images with file extension 'jpeg'
  // ... if detected, convert $plannedFileExt to 'jpg'
  if ($plannedFileExt == 'jpeg' && $dataFileExt == 'jpg'){
    $plannedFileExt = 'jpg';
  }

  // check binary data and file extension match
  $isValidFileType = false;
  if     ($plannedFileExt == 'gif' && $dataFileExt != 'gif')                                           { $isValidFileType = false; } // REQUIRE these extensions to match binary data
  elseif ($plannedFileExt == 'png' && $dataFileExt != 'png')                                           { $isValidFileType = false; }
  elseif ($plannedFileExt == 'jpg' && $dataFileExt != 'jpg')                                           { $isValidFileType = false; }
  elseif ($plannedFileExt == 'webp' && $dataFileExt != 'webp')                                         { $isValidFileType = false; }
  elseif (in_array($plannedFileExt, array('swf','swc')) && in_array($dataFileExt, array('swf','swc'))) { $isValidFileType = false; } // v2.50 - swf files have been observed to return IMAGETYPE_SWC types - https://bugs.php.net/bug.php?id=51700
  elseif ($dataFileExt && $plannedFileExt != $dataFileExt)                                             { $isValidFileType = false; } // All other files - fail if we detected the data type and it doesn't match extension (we can't detect data type for word, pdf, zip, etc)
  else                                                                                                 { $isValidFileType = true; }  // All other files - pass if we couldn't detect the data type (and it wasn't one of the above)

  // report errors
  if (!$isValidFileType) {
    $errors = '';
    if (!$plannedFileExt) { $errors = sprintf(t("File '%s' doesn't have a file extension (appears to be a '%s' file)."), htmlspecialchars(basename($uploadFilepath)), $dataFileExt); }
    else           { $errors = sprintf(t("File '%s' isn't a valid '%s' file (appears to be a '%s' file)."), htmlspecialchars(basename($uploadFilepath)), $plannedFileExt, $dataFileExt); }
    $errors .= "<br>\n";

    //    
    return array($errors, FALSE);
  }

  $isImage = isImage($dataFileExt, true);
  return array('', $isImage);
}

// return errors for an individual upload
// $uploadInfo is $_FILES['upload-form-field-name'] // eg: upload1
// Note: upload fieldnames and CMS fieldnames are unrelated.  Upload form may have fields upload1, upload2, etc that all get saved to 'photos' fields
function _saveUpload_getErrors($tableName, $fieldname, $uploadInfo, $recordNum, $preSaveTempId) {
  // error checking
  if (!$tableName)        { die(__FUNCTION__ . ": No 'tablename' specified!"); }
  if (!$fieldname)        { die(__FUNCTION__ . ": No 'fieldname' specified!"); }
  if (!$uploadInfo)       { die(__FUNCTION__ . ": No 'uploadInfo' specified!"); }

  //
  $errors      = '';
  $schema      = loadSchema($tableName);

  // server issues
  $uploadTmpDir = ini_get('upload_tmp_dir');
  list($uploadDir, $uploadUrl) = getUploadDirAndUrl( $schema[$fieldname] );
  if ($uploadTmpDir && !is_dir($uploadTmpDir)) { $errors .= "Temp Upload dir '$uploadTmpDir' does't exist!  Ask server admin to check 'upload_tmp_dir' setting in php.ini.<br>\n"; }
  if     (!file_exists($uploadDir))            { $errors .= "Upload directory '" .htmlencode($uploadDir). "' doesn't exist!"; }
  elseif (!is_writable($uploadDir))            { $errors .= "Upload directory '" .htmlencode($uploadDir). "' isn't writable!"; }
  if ($errors) { return $errors; } // return early errors here since nothing else will work otherwise

  // php upload errors
  $encodedFilename = htmlencode($uploadInfo['name']);
  if      ($uploadInfo['error'] == UPLOAD_ERR_INI_SIZE)   { $errors .= "Error saving '$encodedFilename', file is larger than '" .ini_get('upload_max_filesize'). "' max size allowed by PHP (check 'upload_max_filesize' in php.ini).<br>\n";  }
  else if ($uploadInfo['error'] == UPLOAD_ERR_PARTIAL)    { $errors .= "Error saving '$encodedFilename', file was only partially uploaded.<br>\n"; }
  else if ($uploadInfo['error'] == UPLOAD_ERR_NO_TMP_DIR) { $errors .= "Error saving '$encodedFilename', PHP temporary upload folder doesn't exist or isn't defined.  Ask your hosting provider to fix this (check 'upload_tmp_dir' in php.ini).<br>\n"; }
  else if ($uploadInfo['error'] == UPLOAD_ERR_CANT_WRITE) { $errors .= "Error saving '$encodedFilename', can't write to disk (could be disk full or permissions).<br>\n"; }
  else if ($uploadInfo['error'])                          { $errors .= "Error saving '$encodedFilename', unknown error code ({$uploadInfo['error']}).<br>\n"; }

  // field type errors
  $fieldSchema        = $schema[$fieldname];
  $encodedLabelOrName = $fieldSchema['label'] ? htmlencode($fieldSchema['label']) : htmlencode($fieldname);
  if ($schema[$fieldname]['type'] != 'upload' && $schema[$fieldname]['type'] != 'wysiwyg') { $errors .= "Field '$encodedLabelOrName' doesn't accept uploads (field type is '{$fieldSchema['type']}').<br>\n"; }
  if ($schema[$fieldname]['type'] == 'wysiwyg' && !@$schema[$fieldname]['allowUploads'])   { $errors .= "Wysiwyg field '" .htmlencode($fieldname). "' doesn't allow uploads!"; }

  // filesize errors
  $filesizeKbytes     = $uploadInfo['size'] ? (int) ceil( $uploadInfo['size']/1024 ) : 0;
  if ($uploadInfo['size'] == 0 && !$errors) { $errors .= "Error saving '$encodedFilename', file is 0 bytes.<br>\n"; }
  if ($fieldSchema['checkMaxUploadSize'] &&
      $fieldSchema['maxUploadSizeKB'] < $filesizeKbytes) { $errors .= "File '$encodedFilename' exceeds max upload size (file: {$filesizeKbytes}K, max: {$fieldSchema['maxUploadSizeKB']}K).<br>\n"; }

  // check max upload limit
  list($isUploadLimit, $maxUploads, $remainingUploads) = getUploadLimits($tableName, $fieldname, $recordNum, $preSaveTempId);
  if ($isUploadLimit && $remainingUploads <= 0) {
    $errors .= sprintf(t("Skipped '%1\$s', max uploads of %2\$s already reached."), $encodedFilename, $maxUploads);
    $errors .= "<br>\n";
  }

  //
  return $errors;
}

function _saveUpload_validateUploadFileExt($uploadInfo, $fieldSchema, $mainUploadWorkingPath) {
    
  $errors = '';
  $encodedFilename = htmlencode($uploadInfo['name']);
  
  // add file extension if no recognized file extension found (do this before error checking)
  // Ref: Dec 2014 - PhoneGapBuild Android Apps upload images are rejected as invalid files when:
  // ... $_FILES contains: 'name' = '26', 'type' = 'application/octet-stream', with no information that would allow us to determine file type being sent.
  // ... HTTP_USER_AGENT is: Mozilla/5.0 (Linux; Android 4.4.4; Nexus 4 Build/KTU84Q) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/33.0.0.0 Mobile Safari/537.36
  // ... So we detect the 
  if (!_saveUpload_hasValidExt($uploadInfo['name'], $fieldSchema)) { // if file doesn't have a valid file extension
    $fileExt     = _saveUpload_getExtensionFromFileName( $uploadInfo['name'] ); 
    $dataFileExt = _saveUpload_getExtensionFromFileData( $mainUploadWorkingPath );
    if ($dataFileExt && $dataFileExt != $fileExt) { $uploadInfo['name'] .= ".$dataFileExt"; } // add .ext of detected data
  }

  // check allowed extensions
  if (!_saveUpload_hasValidExt($uploadInfo['name'], $fieldSchema)) {
    $errors .= sprintf(t("File '%s' isn't allowed (valid file extensions: %s)."), $encodedFilename, htmlencode($fieldSchema['allowedExtensions']) );
    $errors .= "<br>\n";
  }
  
  return $errors;
}


// list($saveAsBasename, $saveAsExtension) = _saveUpload_getTargetBasenameAndExtension($uploadedAsFilename);
function _saveUpload_getTargetBasenameAndExtension($uploadedAsFilename) {
  $saveAsBasename  = strtolower($uploadedAsFilename);                                    // file names are saved in lower case for better cross platform compatability
  $saveAsBasename  = pathinfo($saveAsBasename, PATHINFO_BASENAME);
  $saveAsBasename  = preg_replace("/\.[^\.]+$/", '', $saveAsBasename);                   // remove ext
  $saveAsBasename  = preg_replace("/[^A-Za-z0-9\&\*\(\)\_\-]+/", '-', $saveAsBasename);  // replace invalid chars with
  $saveAsBasename  = preg_replace("/-+/", '-', $saveAsBasename);                         // condense duplicate dashes
  $saveAsBasename  = preg_replace("/(^-+|-+$)/", '', $saveAsBasename);                   // remove leading and trailing dashes
  $saveAsBasename  = $saveAsBasename ?: 'upload';                                        // default name if no valid chars
  $saveAsExtension = _saveUpload_getExtensionFromFileName( $uploadedAsFilename );
  return [$saveAsBasename, $saveAsExtension];
}

// list($saveAsFilename, $saveAsFilepath) = _saveUpload_chooseUniqueNumberedFilename( $uploadInfo['name'], $uploadDir );
function _saveUpload_chooseUniqueNumberedFilename($uploadedAsFilename, $uploadDir) {

  // get sanitized and formatted filename parts: basename, extension, filename, and filepath
  list($saveAsBasename, $saveAsExtension) = _saveUpload_getTargetBasenameAndExtension($uploadedAsFilename);
  $saveAsExtension = !empty( $saveAsExtension ) ? ".$saveAsExtension" : '';
  $saveAsFilename  = $saveAsBasename.$saveAsExtension;
  $saveAsFilepath  = "$uploadDir/$saveAsBasename".$saveAsExtension;
  
  // if file already exists, increment filename by adding _### to basename
  if (file_exists($saveAsFilepath)) {
    $basenameWithoutSuffix = preg_replace('/_[0-9]+$/', '', $saveAsBasename);  // strip off _001 so re-uploaded incremented filenames doesn't create photo_001_001_001_001.jpg
    $counter               = 0; 

    // increment by hundreds, then tens, then ones, until we find an unused filename.  This is for speed so we don't need to call file_exists()
    // ... hundreds of times on busy sites, a 501st duplicate of photo.jpg would actually take 8 file_exists calls instead of 502
    foreach (array(100, 10, 1) as $increment) { 
      while (file_exists("$uploadDir/{$basenameWithoutSuffix}_" .sprintf("%03d", $counter+$increment) . $saveAsExtension)) {
        $counter += $increment; // jump ahead in increments to just *before* largest unused number
      }
    }
    $counter += 1; // add one to get to the largest unused number

    //    
    $saveAsBasename = "{$basenameWithoutSuffix}_" .sprintf("%03d", $counter);
    $saveAsFilename = $saveAsBasename.$saveAsExtension;
    $saveAsFilepath = "$uploadDir/$saveAsBasename".$saveAsExtension;
  }
  
  // apply filters
  $saveAsFilename = applyFilters('upload_saveAsFilename', $saveAsFilename, $uploadedAsFilename, $uploadDir);
  $saveAsFilepath = "$uploadDir/$saveAsFilename";
  
  // Remove any extraneous slashes in the filepath
  $saveAsFilepath = fixSlashes($saveAsFilepath);

  //
  return array($saveAsFilename, $saveAsFilepath);
}

// get the highest order value for an upload field so we can assign a new upload a greater value so it sorts to the bottom
function _saveUpload_getHighestUploadOrder($tablename, $fieldname, $recordNum, $preSaveTempId) {
  global $TABLE_PREFIX;

  // creating query
  $query  = "SELECT MAX(`order`) FROM `{$TABLE_PREFIX}uploads` ";
  $query .= " WHERE tableName = '".mysql_escape( $tablename )."' AND ";
  $query .= "       fieldName = '".mysql_escape( $fieldname )."' AND ";
  if      ($recordNum)     { $query .= "recordNum     = '".mysql_escape( $recordNum )."' "; }
  else if ($preSaveTempId) { $query .= "preSaveTempId = '".mysql_escape( $preSaveTempId )."' "; }
  else                     { die("You must specify either a record 'num' or 'preSaveTempId'!"); }

  // get result
  list($highestOrder) = mysql_get_query($query, true);

  //
  return $highestOrder;
}


//
function _saveUpload_addToDatabase($uploadRecordDetails) {
  global $TABLE_PREFIX;

  //
  $order = 1 + _saveUpload_getHighestUploadOrder($uploadRecordDetails['tableName'], $uploadRecordDetails['fieldName'], $uploadRecordDetails['recordNum'], $uploadRecordDetails['preSaveTempId']);

  // create query
  $query =  "INSERT INTO `{$TABLE_PREFIX}uploads` SET \n";
  #$query .= "num = NULL,\n";
  $query .= "createdTime    = NOW(),\n";
  $query .= "`order`        = '" . $order . "',\n";
  $query .= "tableName      = '".mysql_escape( $uploadRecordDetails['tableName'] )."',\n";
  $query .= "fieldName      = '".mysql_escape( $uploadRecordDetails['fieldName'] )."',\n";
  $query .= "recordNum      = '".mysql_escape( (int) $uploadRecordDetails['recordNum'] )."',\n";
  $query .= "preSaveTempId  = '".mysql_escape( $uploadRecordDetails['preSaveTempId'] )."',\n";
  $query .= "storage        = '".mysql_escape( $uploadRecordDetails['storage'] )."',\n";
  $query .= "filePath       = '".mysql_escape( $uploadRecordDetails['filePath'] )."',\n";
  $query .= "urlPath        = '".mysql_escape( $uploadRecordDetails['urlPath'] )."',\n";
  $query .= "width          = '".mysql_escape( (int) @$uploadRecordDetails['width'] )."',\n";
  $query .= "height         = '".mysql_escape( (int) @$uploadRecordDetails['height'] )."',\n";
  $query .= "filesize       = '".mysql_escape( (int) @$uploadRecordDetails['filesize'] )."',\n";
  foreach (_getThumbFieldSuffixes() as $suffix) {
    $query .= "thumbFilePath$suffix = '".mysql_escape( @$uploadRecordDetails['thumbFilePath' . $suffix] )."',\n";
    $query .= "thumbUrlPath$suffix  = '".mysql_escape( @$uploadRecordDetails['thumbUrlPath'  . $suffix] )."',\n";
    $query .= "thumbWidth$suffix    = '".mysql_escape( (int) @$uploadRecordDetails['thumbWidth'  . $suffix] )."',\n";
    $query .= "thumbHeight$suffix   = '".mysql_escape( (int) @$uploadRecordDetails['thumbHeight' . $suffix] )."',\n";
  }
  $query .= "info1          = '',\n";
  $query .= "info2          = '',\n";
  $query .= "info3          = '',\n";
  $query .= "info4          = '',\n";
  $query .= "info5          = ''\n";

  // insert record
  mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  
  $newUploadNum = mysqli()->insert_id;
  return $newUploadNum;
}

//
function adoptUploads($tableName, $preSaveTempId, $newRecordNum) {
  global $TABLE_PREFIX;

  $query = "UPDATE `{$TABLE_PREFIX}uploads` "
         . "   SET recordNum     = '".mysql_escape($newRecordNum)."', preSaveTempId = '' "
         . " WHERE tableName     = '".mysql_escape($tableName)."' AND "
         . "       preSaveTempId = '".mysql_escape($preSaveTempId)."'";
  mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

  //
  doAction('upload_adopted', $tableName, $newRecordNum);
}

//
function getUploadRecords($tablename, $fieldname, $recordNum, $preSaveTempId = "", $uploadNumsAsCSV = null) {
  global $TABLE_PREFIX;

  //
  $query  = "SELECT * FROM `{$TABLE_PREFIX}uploads` ";
  $query .= " WHERE tableName = '".mysql_escape( $tablename )."' AND ";
  $query .= "       fieldName = '".mysql_escape( $fieldname )."' AND ";

  if      ($recordNum)     { $query .= "recordNum     = '".mysql_escape( $recordNum )."' "; }
  else if ($preSaveTempId) { $query .= "preSaveTempId = '".mysql_escape( $preSaveTempId )."' "; }
  else                     { die("You must specify either a record 'num' or 'preSaveTempId'!"); }
  if ($uploadNumsAsCSV)    { $query .= " AND num IN(".mysql_escape( $uploadNumsAsCSV ).") "; }

  $query  .= " ORDER BY `order`, num";
  $uploads = mysql_select_query($query);

  // replace uploads that reference media files with data from that media file
  $uploads = media_replaceUploadsWithMedia($uploads); 

  // add pseudo-fields
  $schema      = loadSchema($tablename);
  foreach (array_keys($uploads) as $index) {
    $record = &$uploads[$index];
    
    _addUploadPseudoFields( $record, $schema, $fieldname );
  }
  
  $uploads = applyFilters('upload_getUploadRecords',$uploads,$tablename, $fieldname);

  //
  return $uploads;
}

//
function _addUploadPseudoFields( &$record, $schema, $fieldname ) {
  static $mediaSchema = null;
  if (!$mediaSchema) { $mediaSchema = loadSchema('_media'); }
  
  // if upload is a reference to a media file, use media schema to lookup filepath for media files
  if (!empty($record['mediaNum']) && intval($record['mediaNum'])) { 
    $schema = $mediaSchema;
    $fieldname = 'media';
  }

  upload_storage_strategy()->makeUploadPathAndUrlAbsolute($record, $schema[$fieldname]);

  $record['filename']     = pathinfo($record['filePath'], PATHINFO_BASENAME);
  $record['extension']    = _saveUpload_getExtensionFromFileName( $record['filePath'] );
  $record['isImage']      = preg_match("/\.(gif|jpg|jpeg|png|webp)$/i", $record['filePath']);
  $record['hasThumbnail'] = $record['isImage'] && $record['thumbUrlPath'];

}

//
function getUploadCount($tableName, $fieldName, $recordNum, $preSaveTempId) {
  global $TABLE_PREFIX;
  $uploadCount = 0;

  // create query
  $where  = "tableName = '".mysql_escape( $tableName )."' AND ";
  $where .= "fieldName = '".mysql_escape( $fieldName )."' AND ";
  if      ($recordNum)     { $where .= "recordNum     = '".mysql_escape( $recordNum )."'"; }
  else if ($preSaveTempId) { $where .= "preSaveTempId = '".mysql_escape( $preSaveTempId )."'"; }
  else { die("You must specify either a record 'num' or 'preSaveTempId'!"); }

  // execute query
  $uploadCount = mysql_count('uploads', $where);

  //
  return $uploadCount;
}


// remove temporary uploads from unsaved records and uploads who's field has been erased
// remove uploads without record numbers that are older than 1 day
function removeExpiredUploads() {
  global $TABLE_PREFIX;

  // List old uploads in database (limit to 25 at a time to avoid timeouts)
  $query  = "SELECT * FROM `{$TABLE_PREFIX}uploads`";
  $query .= " WHERE (recordNum = 0 AND preSaveTempId != '' AND createdTime < (NOW() - INTERVAL 1 DAY)) OR"; // temporary upload for unsaved record more than 1 day old
  $query .= "       fieldName = ''";  // upload from field that was removed
  $query .= " LIMIT 0, 25";
  $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  while ($row = $result->fetch_assoc()) {

    $schema = loadSchema($row['tableName']);
    
    if (!@$schema[$row['fieldName']]){
      continue; // skip if there is no field schema, ie. upload field has been deleted
    }
    
    upload_storage_strategy()->removeUploadsForRecord($row);

    // remove record
    mysqli()->query("DELETE FROM `{$TABLE_PREFIX}uploads` WHERE num = {$row['num']}") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }
}

// Returns the absolute uploadDir and uploadUrl for a specific upload field. This is determined by: The dir/url of the CMS,
// .. the (potentially relative) upload dir/url in settings, and the (potentially defined) custom upload dir/url for the field.
// Usage: list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
function getUploadDirAndUrl($fieldSchema, $settingsUploadDir = null, $settingsUploadUrl = null, $returnOnErrors = false) {
  if ($settingsUploadDir === null) { $settingsUploadDir = $GLOBALS['SETTINGS']['uploadDir']; }
  if ($settingsUploadUrl === null) { $settingsUploadUrl = $GLOBALS['SETTINGS']['uploadUrl']; }
  
  // get upload dir and url
  if (@$fieldSchema['useCustomUploadDir']) {       // if field is configured to use custom upload paths, use them
    $uploadDir = $fieldSchema['customUploadDir'];
    $uploadUrl = $fieldSchema['customUploadUrl'];
    list($baseDir, $baseUrl) = getUploadDirAndUrl([]); // path for resolving CUSTOM dirs/urls: Uses upload dir/url from general settings made absolute using CMS dir/url
  }
  else {                                           // default to using global SETTINGS upload paths
    $uploadDir = $settingsUploadDir;
    $uploadUrl = $settingsUploadUrl;
    $baseDir   = SCRIPT_DIR;                                        // paths for resolving relative dirs and urls (CMS Script Dir)
    $baseUrl   = dirname(@$GLOBALS['SETTINGS']['adminUrl']) . '/';  // paths for resolving relative dirs and urls (CMS Script Dir URL)
  }

  $uploadDir = applyFilters('upload_uploadDir', $uploadDir, $fieldSchema);
  $uploadUrl = applyFilters('upload_uploadUrl', $uploadUrl, $fieldSchema);
  doAction('upload_dirAndUrl', $uploadDir, $uploadUrl);
  
  // make path absolute (and format/normalize it)
  $uploadDir = absPath($uploadDir, $baseDir);
  if (!endsWith('/', $uploadDir)) { $uploadDir .= '/'; } // absPath doesn't return trailing slash, but we require that for user entered values and expect it in the code

  // make urls absolute, starting with either http:// or /
  if (!isAbsoluteUrl($uploadUrl) && !preg_match("|^/|", $uploadUrl)) {
    $uploadUrl = $uploadUrl ?: './'; // added ./ so blank upload url gets DIR of $baseUrl, not baseurl with admin.php on the end
    $uploadUrl = realUrl($uploadUrl, $baseUrl); 
    $uploadUrl = preg_replace("|^\w+://[^/]+|", '', $uploadUrl); // remove scheme://hostname
  }
  if (!endsWith('/', $uploadUrl)) { $uploadUrl .= '/'; }

  //
  return array($uploadDir, $uploadUrl);
}


// called on page load directly and by ajax on upload url/dir value change
// usage: ?menu=admin&action=getUploadPathPreview&dirOrUrl=dir&inputValue=relativeOrAbsolutePathOrUrl
// This is used by: Admin General > Upload fields, and Field Editor > Uploads > Custom Upload Paths
function getUploadPathPreview($dirOrUrl, $inputValue, $isCustomField, $forAjax = false) {

  //
  $output = '';
  $fakeFieldSchema = []; // create fake field schema (only used for calculating custom upload field paths)
  if ($dirOrUrl == 'dir') {
    $settingsUploadDir = $inputValue;
    if ($isCustomField) {
      $settingsUploadDir = null; // use defaults so custom fields will be relative to that
      $fakeFieldSchema   = array('useCustomUploadDir' => 1, 'customUploadDir' => $inputValue, 'customUploadUrl' => ''); 
    }
    list($absoluteDir, $absoluteUrl) = getUploadDirAndUrl($fakeFieldSchema, $settingsUploadDir, null, true);
    $output = $absoluteDir;
    if (!is_dir($absoluteDir)) { $output .= " (" .t("not found! check dir exists"). ")"; }
  }
  elseif ($dirOrUrl == 'url') {
    $settingsUploadUrl = $inputValue;    
    if ($isCustomField) {
      $settingsUploadUrl = null; // use defaults so custom fields will be relative to that
      $fakeFieldSchema = array('useCustomUploadDir' => 1, 'customUploadDir' => '', 'customUploadUrl' => $inputValue); 
    }
    list($absoluteDir, $absoluteUrl) = getUploadDirAndUrl($fakeFieldSchema, null, $settingsUploadUrl, true);
    $output = $absoluteUrl;
  }
  else {
    dieAsCaller("Invalid value for \$dirOrUrl '" .htmlencode($dirOrUrl). "'!");
  }
  
  //
  if ($forAjax) { die($output); }
  return $output;
}


// called on page load directly and by ajax on media url/dir value change
// usage: ?menu=admin&action=getMediaPathPreview&dirOrUrl=dir&inputValue=relativeOrAbsolutePathOrUrl
// This is used by: Admin General > Media fields
function getMediaPathPreview($dirOrUrl, $inputValue, $isCustomField, $forAjax = false) {
  return getUploadPathPreview($dirOrUrl, $inputValue, $isCustomField, $forAjax); 
}

//
function _getThumbFieldSuffixes() {
  return array('',2,3,4);
}

// list($createThumb, $cropThumb, $maxHeight, $maxWidth, $filepath, $urlPath, $width, $height) = _getThumbDetails($thumbFieldSuffix, $fieldSchema);
function _getThumbDetails($suffix, $fieldSchema) {
  $maxHeight   = @$fieldSchema["maxThumbnailHeight$suffix"];
  $maxWidth    = @$fieldSchema["maxThumbnailWidth$suffix"];
  $createThumb = @$fieldSchema["createThumbnails$suffix"] && $maxHeight && $maxWidth;
  $cropThumb   = @$fieldSchema["cropThumbnails$suffix"];

  return array($createThumb, $cropThumb, $maxHeight, $maxWidth); //, $filepath, $urlPath, $width, $height);
}

//
function _getUploadUrlFromPath($fieldSchema, $filepath) {
  list($uploadDir, $uploadUrl) = getUploadDirAndUrl( $fieldSchema );
  $urlPath = preg_replace('/^.+\//', $uploadUrl, $filepath);
  return $urlPath;
}


// list($isUploadLimit, $maxUploads, $remainingUploads) = getUploadLimits($tableName, $fieldName, $num, $preSaveTempId);
// if $maxUploads or $remainingUploads is blank it means unlimited
function getUploadLimits($tablename, $fieldname, $num, $preSaveTempId) {
  $schema      = loadSchema($tablename);
  $fieldSchema = $schema[$fieldname];

  $isUploadLimit    = @$fieldSchema['checkMaxUploads'];
  $maxUploads       = (int) $fieldSchema['maxUploads'];
  $uploadCount      = getUploadCount($tablename, $fieldname, $num, $preSaveTempId);
  $remainingUploads = max($maxUploads - $uploadCount, 0);

  return array($isUploadLimit, $maxUploads, $remainingUploads);
}


// remove all uploads for a record
function eraseRecordsUploads($recordNumsAsCSV) {
  global $tableName;

  //
  if (inDemoMode()) { return; }

  // create query
  $recordNumsAsCSV = preg_replace('/[^0-9,]/', '', $recordNumsAsCSV);  // optimization, nums as ints not strings
  $where  = "tableName = '".mysql_escape($tableName)."' AND ";
  $where .= " recordNum IN (".mysql_escape( $recordNumsAsCSV ).")";

  removeUploads($where);
}

// remove a single upload (this is called via ajax and should be renamed in future)
function eraseUpload() {
  global $tableName, $escapedTableName;

  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();

  //
  disableInDemoMode('', 'ajax');

  // error checking
  if (!array_key_exists('fieldName', $_REQUEST))     { die("no 'fieldName' value specified!"); }
  if (!array_key_exists('uploadNum', $_REQUEST))     { die("no 'uploadNum' value specified!"); }

  // create where query
  $where = "";
  if      ($_REQUEST['num'])           { $where .= "recordNum     = '".mysql_escape( $_REQUEST['num'] )."' AND "; }
  else if ($_REQUEST['preSaveTempId']) { $where .= "preSaveTempId = '".mysql_escape( $_REQUEST['preSaveTempId'] )."' AND "; }
  else                                 { die("No value specified for 'num' or 'preSaveTempId'!"); }
  $where .= "num       = '".mysql_escape($_REQUEST['uploadNum'])."' AND ";
  $where .= "tableName = '".mysql_escape($tableName)."' AND ";
  $where .= "fieldName = '".mysql_escape($_REQUEST['fieldName'])."'";

  $count = removeUploads($where);

  //
  if ($count == 0) { die("Upload not found!"); }

  // this function is called via ajax, any output will be returns as errors with javascript alert
  exit;
}

// make all upload records use relative paths
function makeAllUploadRecordsRelative() {
  global $TABLE_PREFIX, $SETTINGS;

  // get field list
  $urlOrPathFields = array_merge($GLOBALS['UPLOAD_FILE_PATH_FIELDS'], $GLOBALS['UPLOAD_URL_PATH_FIELDS']);

  // create sql
  $sql = "UPDATE `{$TABLE_PREFIX}uploads` SET";
  foreach ($urlOrPathFields as $fieldname) {
    $isThumb   = preg_match('/thumb/', $fieldname);
    $thumbNum  = intval(substr($fieldname, -1));
    if ($thumbNum == '0') { $thumbNum = ''; }
    $dirPrefix = $isThumb ? "thumb$thumbNum/" : '';

    $setSqlValue  = "CONCAT('$dirPrefix', SUBSTRING_INDEX($fieldname,'/',-1))";

    $sql      .= "\n$fieldname = IF(LENGTH($fieldname), $setSqlValue, $fieldname),";
  }
  $sql = rtrim($sql, ", \n"); // remove trailing commas or whitespace
  
  // don't change upload records using a different upload storage strategy!
  $sql .= " WHERE storage = ''";
  
  //
  mysqli()->query($sql) or die(__FUNCTION__ ." MySQL Error: ". htmlencode(mysqli()->error) ."\n");
}

//
function removeUploadPathPrefix($path, $pathPrefix) {
  $relativePath = str_replace($pathPrefix, '', $path);
  $relativePath = ltrim($relativePath, '/'); // _saveUpload_chooseUniqueNumberedFilename() adds an extra slash between path and filename
  //showme(array(__FUNCTION__, "INPUT: '$path', '$pathPrefix'", "OUTPUT: '$relativePath'"));
  return $relativePath;
}

//
function addUploadPathPrefix($path, $pathPrefix) {
  //showme(array(__FUNCTION__ . " INPUT", $path, $pathPrefix));
  if (!$path) { return $path; }
  $relativePath = removeUploadPathPrefix($path, $pathPrefix);
  $fullPath     = $pathPrefix . $relativePath;
  //showme(array(__FUNCTION__ . " OUTPUT", $fullPath));
  return $fullPath;
}



// for recreating thumbnails from field editor
// called by /lib/menus/database/actionHandler.php
function recreateThumbnails() {
  global $TABLE_PREFIX;
  $tableNameWithoutPrefix = getTablenameWithoutPrefix($_REQUEST['tablename']);

  // error checking
  $stopPrefix = "STOPJS:"; // this tells javascript to stop creating thumbnails
  $requiredFields = array('tablename','fieldname','maxHeight','maxWidth');
  foreach ($requiredFields as $fieldname) {
    if (!@$_REQUEST[$fieldname]) { die($stopPrefix . "Required fieldname '$fieldname' not specified!"); }
  }
  if (preg_match('/[^0-9\_]/i', $_REQUEST['maxHeight'])) { die($stopPrefix . "Invalid value for max height!\n"); }
  if (preg_match('/[^0-9\_]/i', $_REQUEST['maxWidth']))  { die($stopPrefix . "Invalid value for max width!\n"); }

  // get upload count
  static $count;
  if ($count == '') {
    $where = mysql_escapef("tableName = ? AND fieldName = ?", $tableNameWithoutPrefix, $_REQUEST['fieldname']);
    $totalUploads = mysql_count('uploads', $where);
  }

  // load upload
  $whereEtc  = mysql_escapef("tableName = ? AND fieldname = ?", $tableNameWithoutPrefix, $_REQUEST['fieldname'] );
  $whereEtc .= " LIMIT 1 OFFSET " . intval($_REQUEST['offset']);
  @list($upload) = mysql_select('uploads', $whereEtc);

  //
  if ($upload) {
    $fieldSchemas = getSchemaFields($tableNameWithoutPrefix);
    $fieldSchema  = $fieldSchemas[$upload['fieldName']];
    list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
    
    //
    $thumbNum          = $_REQUEST['thumbNum'];
    $mainFullFilePath  = addUploadPathPrefix($upload['filePath'], $uploadDir);
    $thumbFullFilePath = addUploadPathPrefix($upload["thumbFilePath{$thumbNum}"], $uploadDir);
    if (isImage_SVG($mainFullFilePath)) {
      // note: SVG image doesn't need to be resized/resampled, just copy the main file as a new thumbnail file (if thumb doesn't exist yet)
      // ... then set the width and height
      
      // if SVG thumbnail doesn't exist yet, create a new one
      if (!file_exists($thumbFullFilePath)) {
        
        // copy main file to a temp file
        $thumbWorkingPath = tempnam(DATA_DIR . '/temp', 'thumb_');
        _saveUpload_fixFilePermission($thumbWorkingPath);
        unlink_on_shutdown($thumbWorkingPath);
        copy($mainFullFilePath, $thumbWorkingPath) || die(__FUNCTION__ . ": error copying image '$mainFullFilePath' - " . @$php_errormsg);
        
        // move thumb to long-term storage
        upload_storage_strategy()->storeThumbFile($thumbWorkingPath, $fieldSchema, $thumbNum, $upload);
      }
      
      $thumbFilePath = $upload['thumbFilePath'.$thumbNum];
      $thumbUrlPath  = $upload['thumbUrlPath'.$thumbNum];
      $thumbWidth    = $_REQUEST['maxWidth'];
      $thumbHeight   = $_REQUEST['maxHeight'];
    }
    else {
      // resize/resample other image file type
      list($thumbFilePath, $thumbUrlPath, $thumbWidth, $thumbHeight) = upload_storage_strategy()->resizeThumb($upload, $thumbNum, $_REQUEST['maxWidth'], $_REQUEST['maxHeight'], @$_REQUEST['crop']);
    }
    
    // if this is stored in the filesystem, remove upload path prefix
    if ($upload['storage'] === '') {
      $thumbFilePath = removeUploadPathPrefix($thumbFilePath, $uploadDir);
      $thumbUrlPath  = removeUploadPathPrefix($thumbUrlPath, $uploadDir);
    }
    
    if ($thumbWidth) {
      // update upload database
      $query  =  "UPDATE `{$TABLE_PREFIX}uploads`\n";
      $query .= "   SET `thumbFilepath$thumbNum` = '".mysql_escape( $thumbFilePath )."',\n";
      $query .= "       `thumbUrlPath$thumbNum`  = '".mysql_escape( $thumbUrlPath )."',\n";
      $query .= "       `thumbWidth$thumbNum`    = '".mysql_escape( $thumbWidth )."',\n";
      $query .= "       `thumbHeight$thumbNum`   = '".mysql_escape( $thumbHeight )."'\n";
      $query .= " WHERE num = '".mysql_escape( $upload['num'] )."'";
      mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
    }
    
    // add plugin hook
    $thumbnailInfo = array($tableNameWithoutPrefix, $upload['fieldName'], $thumbNum, $thumbFullFilePath, $upload);
    doAction('upload_thumbnail_save', $thumbnailInfo);
  }

  // print status message
  $offset = $_REQUEST['offset'] + 1;
  if ($offset <= $totalUploads) { print "$offset/$totalUploads"; }
  else                          { print "done"; }
  exit;
}

// showUploadPreview($uploadRecord, 50, 100);
// Outputs a thumbnail image link (or "download" text link if the upload is not an image) best fit to the specified dimensions.
// This function will choose the smallest available thumb (or the fullsize image) to be downscaled to fit the target size, while preserving the
// aspect ratio. If the fullsize image is smaller than the target size, the fullsize image will be used without scaling it up.
function showUploadPreview($uploadRecord, $maxWidth = 50, $maxHeight = null) {
  if ($maxWidth && !$maxHeight) { $maxHeight = $maxWidth * 2; } // legacy 2-argument support

  // find the biggest image or thumb with the least scaling
  list($bestSrc, $bestWidth, $bestHeight, $bestSize, $bestScaledBy) = array('', 0, 0, 0, 0);
  $isImage = isImage($uploadRecord['urlPath']);
  if ($isImage) {
    foreach (range(0, 4) as $thumbNum) {
      if     ($thumbNum === 0) { list($widthField, $heightField, $urlField) = array('width', 'height', 'urlPath'); }
      elseif ($thumbNum === 1) { list($widthField, $heightField, $urlField) = array('thumbWidth', 'thumbHeight', 'thumbUrlPath'); }
      else                     { list($widthField, $heightField, $urlField) = array("thumbWidth$thumbNum", "thumbHeight$thumbNum", "thumbUrlPath$thumbNum"); }

      // skip images with no height or width
      if (!@$uploadRecord[$widthField] && !@$uploadRecord[$heightField]) { continue; }
      
      // calculate dimensions of scaling this image or thumbnail (do not allow scaling-up)
      list($resizeWidth, $resizeHeight, $resizeScale) = image_resizeCalc($uploadRecord[$widthField], $uploadRecord[$heightField], $maxWidth, $maxHeight);
      
      // when calculating sizes, longest run (width or height) is more appropriate than width * height, since rounding errors can cause the minor
      // axis to deviate by 1 pixel (e.g. a 10x150 image, thumbnailed to 100x100 and 64x64, will resize to 50x50 as 50x4 and 50x3 respectively; the
      // 64x64 thumb should be chosen, even though its minor axis is one pixel smaller than the 100x100 thumb)
      $resizeSize = max($resizeWidth, $resizeHeight); 
      
      // if this resized image/thumb is the biggest, or if it's the same size but requires less scaling (meaning that the original thumb is smaller)
      // Note: Multiple thumbs that are larger than maxHeight/maxWidth will have similar resized height/width but different resizing scales,
      // ... we want to select the image that needs to be scaled down the least, since we're not scaling the image itself but the height/width in
      // ... the image tag.  We want to load the image that is closest in size to reduce bandwidth required.
      if ($resizeSize > $bestSize || ($resizeSize === $bestSize && $resizeScale > $bestScaledBy)) {
        $bestSrc = $uploadRecord[$urlField];  // keep track of best match
        list($bestWidth, $bestHeight, $bestSize, $bestScaledBy) = array($resizeWidth, $resizeHeight, $resizeSize, $resizeScale);
      }
    }
  }
  
  // output preview html
  $aLink   = urlencodeSpaces($uploadRecord['urlPath']);
  $title   = htmlencode( $uploadRecord['filename'] );
  $bestSrc = htmlencode($bestSrc);
  $html   = ''; 
  $html  .= "<a href='$aLink' title='$title' target='_BLANK'>";
  if ($isImage && $bestSrc) { $html .= "<img src='$bestSrc' border='0' width='$bestWidth' height='$bestHeight' alt='$title' title='$title'>"; }
  else                      { $html .= t('download'); }
  $html .= "</a>\n";
  
  //
  $html = applyFilters('showUploadPreview_html', $html, $uploadRecord);
  print $html;
  
  
}

// foreach (getUploadInfoFields($record) as $name => $label) { ...
function getUploadInfoFields($fieldname) {
  global $schema;
  $infoFields = [];

  //
  $fieldSchema = $schema[$fieldname];
  foreach ($fieldSchema as $name => $value) {
    if (!preg_match("/^infoField\d+$/", $name)) { continue; } // skip if not info field
    if (!$value)                                { continue; } // skip if no field label
    $fieldname = preg_replace("/Field/", '', $name);

    $infoFields[$fieldname] = $value;
  }

  return $infoFields;
}

//Prepare allowed file extensions from a field schema so they can be passed to Uploadify
function schemaFileExtForUploadify($allowedExtensions) {
  $allowedExts = explode(",", $allowedExtensions);
  foreach($allowedExts as $key => $allowedExt) {
    $allowedExt        = trim($allowedExt); //Remove any white space
    $allowedExts[$key] = ".".$allowedExt; //Add . before file extension
  }
  return implode(',', $allowedExts);
}

// returns server's max upload size in bytes, by looking at post_max_size and upload_max_filesize
function fileUploadMaxSize() {
  static $max_size = -1;

  if ($max_size < 0) {
    // Start with post_max_size.
    $post_max_size = parseIniSize(ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parseIniSize(ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size) {
      $max_size = $upload_max;
    }
  }
  return $max_size;
}

// converts a php.ini 'size' value to bytes
// e.g. parseIniSize('1k') returns 1024
function parseIniSize($size) {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}
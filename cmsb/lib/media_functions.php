<?php

// Show link under uploadify "Select from media library"
function media_showUploadifyLink($tableName, $fieldName, $recordNum) {
  if (empty($GLOBALS['SETTINGS']['advanced']['useMediaLibrary'])) { return; } // disable if media library not enabled.
  if ($tableName == "_media") { return; } // don't show in media library itself
  
  $link = "?menu=" .urlencode($tableName)
         ."&fieldName="       .urlencode($fieldName)
         ."&num="             .intval($recordNum)
         ."&action=mediaList";
?>
  <div class="uploadifive-button" style="text-align: center; width: 100%; margin-top: -6px;">
      <a href="#" onclick="self.parent.showModal('<?php echo $link ?>&preSaveTempId=' + $('input[name=\'preSaveTempId\']').val()); return false;">Select from media library</a>
  </div>
<?php
}

// show link "(from media library)" beside files that are from the media library
function media_showFromMediaLibraryText($mediaNum) {
  if (!$mediaNum) { return; }

  echo "(<a href='?menu=_media&action=edit&num=" .intval($mediaNum). "' target='_blank'>";
  echo t("from media library");
  echo "</a>)";
}


// this function is called by: Media Library > Field Editor > User By
// admin.php?menu=database&action=editField&tableName=cms__media&fieldname=__separator001__&
// the php code is called from the separator html field and displays a list of records that use specific media file
function media_showRecordsUsingMedia() {

  // 
  $mediaNum = $GLOBALS['RECORD']['num'] ?? 0;
  $records  = media_getUploadsUsingMedia($mediaNum);

  //
  print "<div style='padding:7px 0px;'>\n"; 

  //
  if (!$records) {
    print "This media is not not used anywhere.";
  }

  //
  if ($records) {
    foreach ($records as $record) {
      
      $schema         = loadSchema($record['tableName']);
      $menuNameLabel  = $schema['menuName'] ?? $record['tableName'];
      $fieldNameLabel = $schema[$record['fieldName']]['label'] ?? $record['fieldName'];
      $fieldType      = $schema[$record['fieldName']]['type'] ?? '';
      
      print "<a href='?menu=" .urlencode($record['tableName']). "&action=edit&num=" .intval($record['recordNum']). "'>"; 
      print htmlencodef("? &gt; Record ? &gt; ?", $menuNameLabel, $record['recordNum'], $fieldNameLabel);
      if ($fieldType == 'wysiwyg') { print " (WYSIWYG field)"; }
      print "</a><br>\n";
    }
  }
  
  //
  print "</div>"; 
}


// return all upload records that reference mediaNum
function media_getUploadsUsingMedia($mediaNum) {

  $uploads = [];
  if (!empty($mediaNum)) {
    $query = [ 'mediaNum' => $mediaNum ];
    $uploads = mysql_select('uploads', $query);
  }
  
  return $uploads;
}


// return all media records
function media_getAllMediaRecords($perPage, $pageNum = 1) {

  // get mysql limit clause (if paging)
  $LIMIT = "";
  if ($perPage) { $LIMIT = mysql_limit($perPage, $pageNum); } 

  // get media records
  $mediaUploads = mysql_select('uploads', " `tableName` = '_media' AND `recordNum` > 0 ORDER BY createdTime DESC $LIMIT ");

  //// get media record created date (from _media table, not uploads table)
  //$mediaRecordNums             = array_column($mediaUploads,'recordNum');
  //$mediaRecordNumsAsCSV        = mysql_escapeCSV($mediaRecordNums);
  //$mediaRecords                = mysql_select_query("SELECT num, createdDate FROM {$GLOBALS['TABLE_PREFIX']}_media WHERE num IN ($mediaRecordNumsAsCSV)");
  //$mediaRecordNumToCreatedDate = array_column($mediaRecords,'createdDate','num');
  //
  //// get mediaView.php path
  //$mediaSchema = loadSchema('_media');
  //$fieldSchema = $mediaSchema['media'];
  //list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);
  //$mediaViewUrl = $uploadUrl . "mediaView.php";
  
  // add pseudo-fields
  $tablename = "_media";
  $fieldname = "media";
  $schema    = loadSchema($tablename);
  foreach (array_keys($mediaUploads) as $index) {
    $uploadRecord = &$mediaUploads[$index];
    _addUploadPseudoFields( $uploadRecord, $schema, $fieldname );
    
    // add mediaView.php link
    //$mediaRecordNum = $uploadRecord['recordNum'];
    //$hash           = sha1($mediaRecordNumToCreatedDate[ $uploadRecord['recordNum'] ]);
    //$uploadRecord['_mediaLink'] = "$mediaViewUrl?num=$mediaRecordNum&hash=$hash";
  }
  
  //
  return $mediaUploads;
}

// Replace any uploads with 'mediaNum' with the data from that media record
// Usage: $uploads = media_replaceUploadsWithMedia($uploads); // replace uploads that reference media files with data from that media file
function media_replaceUploadsWithMedia($uploads) {

  // get media record nums that are referenced by uploads
  $mediaRecordNums = [];
  foreach ($uploads as $upload) {
    if (empty($upload['mediaNum']) || empty( intval( $upload['mediaNum'] ))) { continue; }
    $mediaRecordNums[] = $upload['mediaNum'];
  }
  $mediaRecordNumsAsCSV         = mysql_escapeCSV($mediaRecordNums);
  $mediaUploads                 = mysql_select('uploads', "tableName = '_media' AND recordNum IN ($mediaRecordNumsAsCSV)");
  $mediaUploadsByMediaRecordNum = array_groupBy($mediaUploads, 'recordNum');
  
  // populate upload records that reference media with related media data
  foreach ($uploads as $index => $upload) {
    if (empty($upload['mediaNum']) || empty( intval( $upload['mediaNum'] ))) { continue; }
    $mediaRecord = @$mediaUploadsByMediaRecordNum[ @$upload['mediaNum'] ];
    if (empty($mediaRecord)) { continue; } 
    
    // check this function isn't being called twice on same upload array
    if ($upload['mediaNum'] && $upload['filePath']) {
      dieAsCaller("Upload has already been replaced with media data."); 
    }

    // save original fieldName
    $originalFieldname = $upload['fieldName'];

    foreach ($upload as $key => $value) {
      if (in_array($key, ['num','mediaNum','recordNum'])) { continue; } // skip these fields
      $uploads[$index][$key] = $mediaRecord[$key];      
    }
    
    // save original fieldName
    $uploads[$index]['_fieldName_original'] = $originalFieldname;
    
  }
  
  //
  return $uploads;
}


// show list of media items
function media_showMediaList($action) {
  global $SETTINGS, $menu;
  $uploadsPerPage = 12;
  $records        = media_getAllMediaRecords($uploadsPerPage, intval($_GET['page'] ?? 1));
  
  $count = 0;
  if ($records):
  ?>

    <?php media_showMediaList_pagingButtons($action, $uploadsPerPage, $records); ?>

    <div class="row col-xs-12" style="margin-top: 10px">
      <?php foreach ($records as $row): ?>
        <div class="col-xs-3 photobox">
          <div class="thumbnail text-center">
            <?php media_showMediaPreview($row); ?>
            <div class="small">
            <?php
              $filename     = pathinfo($row['filePath'], PATHINFO_BASENAME);
              $isImage      = (isImage($row['urlPath']))? 'true' : 'false' ;

              // show "add media" link
              $onclick = "addMedia('" .intval($row['recordNum']). "'); return false;";
              print "<a href='#' onclick=\"$onclick\">" .t('Add Media'). "</a>";

              // show filename
              print "<div style='color: #666; padding-top: 1px'>$filename</div>";
            ?>
            </div>
          </div>
        </div>
        
        <?php if (++$count % 4 == 0): ?>
          <div class="clear"></div>
        <?php endif ?>
        
      <?php endforeach ?>

      <?php media_showMediaList_pagingButtons($action, $uploadsPerPage, $records); ?>

    </div>

  
  <?php else: ?>
  
    <div style="padding: 50px 0px;" class="noUploads text-center">
      There are no files in the media library.
    </div>

  <?php endif; ?>
<?php
}


//
function media_showMediaList_pagingButtons($action, $uploadsPerPage, $records) {
  global $menu;
  static $functionInit = false;
  static $baselink, $totalRecords, $totalPages, $currentPage, $prevPageLink, $lastPageLink, $nextPageLink;
  if (!$functionInit) {
    // baselink for tabs - add &action=mediaList or &action=wysiwygMedia
    $baselink  = "?menu="          . htmlencode($menu);
    $baselink .= "&fieldName="     . htmlencode(@$_REQUEST['fieldName']);
    $baselink .= "&num="           . htmlencode(@$_REQUEST['num']);
    $baselink .= "&preSaveTempId=" . htmlencode(@$_REQUEST['preSaveTempId']);
  
    $totalRecords = mysql_count('uploads', "`tableName` = '_media' AND `recordNum` > 0");
    $totalPages   = ceil($totalRecords / $uploadsPerPage);
    $currentPage  = (isset($_GET['page']) && 1 <= $_GET['page']) ? intval($_GET['page']) : 1;
    
    if ($currentPage > 1)                  { $prevPageLink = $baselink . "&action=$action&page=" .($currentPage - 1); }
    else                                   { $prevPageLink = $baselink . "&action=$action&page=1"; }
    if ((($currentPage+1) <= $totalPages)) { $nextPageLink = $baselink . "&action=$action&page=" . ($currentPage+1); }
    else                                   { $nextPageLink = $baselink . "&action=$action&page=" . ($totalPages); }
    $lastPageLink = $baselink . "&action=$action&page=" . ($totalPages);
    
    //
    $functionInit = true;
  }


  // skip no records
  if (!$totalRecords) { return; } 
  
  //  class="text-muted" to hide button border
  ?>
    <div class="center clear" style="margin: 0px auto 14px">
      <ul class="pagination" style="margin: 0">
        <li <?php if ($currentPage == 1) { print 'class="disabled"'; } ?>><a href="<?php echo $baselink."&action=$action&page=1"; ?>"><?php et("first"); ?></a></li>
        <li <?php if ($currentPage == 1) { print 'class="disabled"'; } ?>><a href="<?php echo $prevPageLink; ?>"><?php et("prev"); ?></a> </li>

      <?php for($currentRecordPage = 0; $currentRecordPage<$totalPages; $currentRecordPage++): ?>
        <?php $isCurrentPage = $currentPage == ($currentRecordPage+1); ?>
        <?php $liClass       = $isCurrentPage ? "class='active'" : ""; ?>
        
        <li <?php echo $liClass; ?>>
          <a href="<?php echo $baselink."&action=$action&page=".($currentRecordPage+1); ?>" target="_self"><?php echo ($currentRecordPage+1); ?></a>
        </li>
      <?php endfor; ?>
        
        <li <?php if ($currentPage >= $totalPages) { print 'class="disabled"'; } ?>><a href="<?php echo $nextPageLink; ?>"><?php et("next"); ?></a></li>
        <li <?php if ($currentPage >= $totalPages) { print 'class="disabled"'; } ?>><a href="<?php echo $lastPageLink; ?>"><?php et("last"); ?></a></li>
      </ul>
    </div>
  <?php
}


// Show html for a single media item
// modified from _showWysiwygUploadPreview()
function media_showMediaPreview($row, $maxWidth = 150, $maxHeight = 125) {
  $filename     = pathinfo($row['filePath'], PATHINFO_BASENAME);
  $isImage      = isImage($row['urlPath']);
  $hasThumbnail = $isImage && $row['thumbUrlPath'];

  // get preview size
  if ($isImage) {
    $widthScale   = $maxWidth / $row['width'];
    $heightScale  = $maxHeight / $row['height'];
    $scaleFactor  = min($widthScale, $heightScale, 1);  # don't scale above 1:1
    $previewHeight = ceil($row['height'] * $scaleFactor); # round up
    $previewWidth  = ceil($row['width'] * $scaleFactor);  # round up
  }

  // show preview
  $html = '';
  if ($hasThumbnail) {
    $html = "<a href='{$row['urlPath']}' target='_BLANK'><img src='{$row['thumbUrlPath']}' width='$previewWidth' height='$previewHeight' alt='' title='Click to view $filename'></a>\n";
  }
  elseif ($isImage) {
    $html = "<a href='{$row['urlPath']}' target='_BLANK'><img src='{$row['urlPath']}' width='$previewWidth' height='$previewHeight' alt='' title='Click to view $filename'></a>\n";
  }
  else {
    $html = "(No Preview)<br><a href='{$row['urlPath']}' target='_BLANK'>" .t('Download'). "</a>\n";
  }
  
  print $html;
}


// copy a media record reference into the uploads table
function media_addMediaAjax() {
  if (empty($GLOBALS['SETTINGS']['advanced']['useMediaLibrary'])) { return; } // disable if media library not enabled.
  
  // add media file to uploads
  $colsToValues = [
    'createdTime='   => 'NOW()',
    'tableName'      => @$_REQUEST['tableName'],
    'fieldName'      => @$_REQUEST['fieldName'],
    'recordNum'      => @$_REQUEST['recordNum'],
    'mediaNum'       => @$_REQUEST['mediaNum'],
    'preSaveTempId'  => @$_REQUEST['preSaveTempId'],
    'order'          => time(),
    'width'          => 0,
    'height'         => 0,
    'thumbWidth'     => 0,
    'thumbHeight'    => 0,
    'thumbWidth2'    => 0,
    'thumbHeight2'   => 0,
    'thumbWidth3'    => 0,
    'thumbHeight3'   => 0,
    'thumbWidth4'    => 0,
    'thumbHeight4'   => 0,
    // info1	info2	info3	info4	info5
  ];

  //
  mysql_insert('uploads', $colsToValues, true);
}

// eof
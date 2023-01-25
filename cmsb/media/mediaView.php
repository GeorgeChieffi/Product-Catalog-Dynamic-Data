<?php
require_once '../lib/init.php';

// error checking
if (!isset($_REQUEST['num']))  { die("num not specified!"); }  // this is _media record num, not upload num
if (!isset($_REQUEST['hash'])) { die("hash not specified!"); } 

// load media record upload
$upload = mysql_get('uploads', null, ['tableName' => '_media', 'recordNum' => intval($_REQUEST['num'])]);
if (!$upload) {
	die("Couldn't find media upload '" .intval($_REQUEST['num']). "'");
}

// load media record
$mediaRecord = mysql_get('_media', $upload['recordNum']);
if (!$mediaRecord) {
	die("Couldn't find media record '" .intval($upload['recordNum']). "'");
}

// check if hash is correct
if ($_REQUEST['hash'] != sha1($mediaRecord['createdDate'])) { // media record created date shouldn't change even if upload changes
	$error = "Invalid hash for media upload '" .intval($_REQUEST['num']). "'<br>"; 
	#/* For debugging */ $error .= "Should be " . sha1($mediaRecord['createdDate']);
	die($error);
} 

// redirect to actual file
$mediaSchema = loadSchema('_media');
$fieldSchema = $mediaSchema['media'];
list($uploadDir, $uploadUrl) = getUploadDirAndUrl($fieldSchema);

$urlPath = $upload['urlPath'];
if (isset($_REQUEST['thumb'])) {
	if ($_REQUEST['thumb'] == 1) { $thumbKey = "thumbUrlPath"; }
	else                         { $thumbKey = "thumbUrlPath{$_REQUEST['thumb']}"; }
	if (array_key_exists($thumbKey, $upload) && $upload[$thumbKey]) {
		$urlPath = $upload[$thumbKey];
	}
}
$relativeFileUrl = $uploadUrl . $urlPath;
header("Location: $relativeFileUrl");
exit;

// eof
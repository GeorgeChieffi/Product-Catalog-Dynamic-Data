<?php


// return resized width, height, and imageType
function saveResampledImageAs($targetPath, $sourcePath, $maxWidth, $maxHeight, $crop = false) {
  global $SETTINGS;

  // error checking
  if (!$targetPath) { die(__FUNCTION__ . ": No targetPath specified! "); }

  // create target dir
  $dir = dirname($targetPath);
  if (!file_exists($dir)) {
    mkdir_recursive($dir) || die("Error creating dir '" .htmlencode($dir). "'.  Check permissions or try creating directory manually.");
  }

  // open source image
  $sourceImage = null;
  list($sourceWidth, $sourceHeight, $imageType) = getimagesize($sourcePath);
  
  $sourceWidth = (int) $sourceWidth;
  $sourceHeight = (int) $sourceHeight;

  // get new height/width
  $widthScale   = $maxWidth / $sourceWidth;
  $heightScale  = $maxHeight / $sourceHeight;
  $scaleFactor  = min($widthScale, $heightScale, 1);  # don't scale above 1:1
  $targetHeight = (int) ceil($sourceHeight * $scaleFactor); # round up
  $targetWidth  = (int) ceil($sourceWidth * $scaleFactor);  # round up

  if ($scaleFactor == 1) {
    if ($sourcePath != $targetPath) {
      copy($sourcePath, $targetPath) || die(__FUNCTION__ . ": error copying image '$sourcePath' - " . @$php_errormsg);
    }
    return array($sourceWidth, $sourceHeight, $imageType);
  }

  // create new image
  switch($imageType) {
    case IMAGETYPE_JPEG: $sourceImage = @imagecreatefromjpeg($sourcePath); break; // Use @ and ini_set('gd.jpeg_ignore_warning') in init.php to suppress gd invalid jpeg errors. See: http://bugs.php.net/bug.php?id=39918
    case IMAGETYPE_GIF:  $sourceImage = imagecreatefromgif($sourcePath); break;
    case IMAGETYPE_PNG:  $sourceImage = @imagecreatefrompng($sourcePath); break;
    case IMAGETYPE_WEBP: $sourceImage = imagecreatefromwebp($sourcePath); break;
    default:             die(__FUNCTION__ . ": Unknown image type for '$sourcePath'!"); break;
  }
  if (!$sourceImage) { die("Error opening image file!"); }
  
  // crop image - set x and y coordinates and override width and height variables
  $dst_x = 0;
  $dst_y = 0;
  if ($crop){
    $ratio = 1; // ratio when cropping the image
    $ow    = imagesx($sourceImage);
    $oh    = imagesy($sourceImage);
    if ($ow < $maxWidth || $oh < $maxHeight) {
      if ($ow < $oh) {
        $cropWidth  = $ow;
        $cropHeight = $ow*$ratio;
      }
      else {
        $cropWidth  = $oh/$ratio;
        $cropHeight = $oh;
      }
    }
    else {
      $cropWidth  = $maxWidth;
      $cropHeight = $maxHeight;
    }
    
    // resize factor
    $rsf = 1;
    $wrs = $ow/$cropWidth;
    $hrs = $oh/$cropHeight;
    if ($wrs > $hrs){
      $rsf = $hrs;
    }else{
      $rsf = $wrs;
    }
    
    // x and y coordinate of destination point
    $dst_x = (int) (($ow/2)-($cropWidth*$rsf/2));
    $dst_y = (int) (($oh/2)-($cropHeight*$rsf/2));
    
    // override width and height variables
    $targetWidth  = (int) $cropWidth;
    $targetHeight = (int) $cropHeight;
    $sourceWidth  = (int) ($cropWidth*$rsf);
    $sourceHeight = (int) ($cropHeight*$rsf);
  }

  $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
  _image_saveTransparency($targetImage, $sourceImage, $imageType);
  
  // resample image
  $quality = 5; // v3.15 Use less memory but more CPU time, might not matter now that servers are faster (was 4 previously)
  _fastimagecopyresampled($targetImage, $sourceImage, 0, 0, $dst_x,  $dst_y,  $targetWidth, $targetHeight, $sourceWidth, $sourceHeight, $imageType, $quality) || die("There was an error resizing the uploaded image!");

  // enable progressive JPEGs
  imageinterlace($targetImage, true);

  // save target image
  $savedFile = false;
  switch($imageType) {
    case IMAGETYPE_JPEG: $savedFile = imagejpeg($targetImage, $targetPath, $SETTINGS['advanced']['imageResizeQuality']); break;
    case IMAGETYPE_GIF:  $savedFile = imagegif($targetImage, $targetPath); break;
    case IMAGETYPE_PNG:  $savedFile = imagepng($targetImage, $targetPath); break;
    case IMAGETYPE_WEBP: $savedFile = imagewebp($targetImage, $targetPath); break;
    default:             die(__FUNCTION__ . ": Unknown image type for '$targetPath'!"); break;
  }
  if (!$savedFile) { die("Error saving file!"); }
  imagedestroy($sourceImage);
  imagedestroy($targetImage);

  //
  return array($targetWidth, $targetHeight, $imageType);
}

// from: http://ca2.php.net/manual/en/function.imagecopyresampled.php#77679
function _fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $imageType, $quality = 3) {
  // Plug-and-Play _fastimagecopyresampled function replaces much slower imagecopyresampled.
  // Just include this function and change all "imagecopyresampled" references to "_fastimagecopyresampled" (and add $imageType argument)
  // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
  // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
  //
  // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
  // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
  // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
  // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
  // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
  // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
  // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

  if (empty($src_image) || empty($dst_image) || $quality <= 0) { return false; }
  if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
    $temp = imagecreatetruecolor ($dst_w * $quality + 1, $dst_h * $quality + 1);
    _image_saveTransparency($temp, $src_image, $imageType);
    imagecopyresized ($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
    imagecopyresampled ($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
    imagedestroy($temp);
  }
  else {
    imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
  }

  return true;
}

// save transparency - based on code from: http://ca3.php.net/manual/en/function.imagecolortransparent.php#80935
function _image_saveTransparency(&$targetImage, &$sourceImage, $imageType) {
  if($imageType == IMAGETYPE_GIF) {
    $transparentIndex = imagecolortransparent($sourceImage);
    $transparentColor = @imagecolorsforindex($sourceImage, $transparentIndex);
    if ($transparentColor) {
      // Fix in progress: $newTransparentIndex = imagecolorallocatealpha($targetImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue'], 127);
      $newTransparentIndex = imagecolorallocate($targetImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
      imagefill($targetImage, 0, 0, $newTransparentIndex);
      imagecolortransparent($targetImage, $newTransparentIndex);
    }
  }
  else if ($imageType == IMAGETYPE_PNG or $imageType == IMAGETYPE_WEBP) {
    imagealphablending($targetImage, false);
    $transparentColor = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
    imagefill($targetImage, 0, 0, $transparentColor);
    imagesavealpha($targetImage, true);
  }

}

// potentially rotate a JPEG file with an EXIF Orientation flag so that future image processing doesn't inadvertently save it without the flag and without rotating it
function _image_fixJpegExifOrientation($imageFilePath) {
  
  // get image dimensions and type
  list($width, $height, $imageType) = getimagesize($imageFilePath);
  
  // if this isn't a JPEG, do nothing
  if ($imageType !== IMAGETYPE_JPEG) { return; }
  
  // get image orientation
  $orientation = false;
  if (!$orientation && extension_loaded('imagick')) {  // try finding orientation with ImageMagick first
    $image    = new Imagick($imageFilePath);
    $exifProp = $image->getImageProperties("exif:*");  // load exif properties
    if ( !empty( $exifProp['exif:Orientation'] )) {    // check for orientation
      $orientation = $exifProp['exif:Orientation'];
    }
  }
  if (!$orientation && extension_loaded('exif')) { // next try with exif
    $exif = @exif_read_data($imageFilePath);
    if( !empty( $exif['Orientation'] )) {
      $orientation = $exif['Orientation'];
    }
  }
  if(!$orientation ) { return false; } // if we didn't find an orientation, skip
  
  // determine rotation required based on EXIF Orientation flag
  $rotationRequiredByOrientation = [ // https://secure.php.net/manual/en/function.exif-read-data.php#110894
    8 => 90,
    3 => 180,
    6 => -90,
  ];
  $rotationRequired = @$rotationRequiredByOrientation[ $orientation ];
    
  // if we need to rotate by 0 degrees, do nothing
  if (!$rotationRequired) { return; }
  
  // try rotating with ImageMagick first
  if (extension_loaded('imagick')) {
    
    // reverse rotation for imagick http://php.net/manual/en/imagick.rotateimage.php#119184
    if ($rotationRequired == 90 || $rotationRequired == -90) {
      $imagickRotation = $rotationRequired * -1;
    } else {
      $imagickRotation = $rotationRequired;
    }
    
    // rotate image
    $image->rotateImage("#000000", $imagickRotation);
    
    // strip old exif data as orientation is now correct (can't set individual property http://php.net/manual/en/imagick.setimageproperty.php#123346)
    // save ICC profile https://stackoverflow.com/a/3615080
    $profiles = $image->getImageProfiles("icc", true); 
    $image->stripImage();
    if(!empty($profiles)) { $image->profileImage("icc", $profiles['icc']); }
    
    // set to save as progressive | http://php.net/manual/en/imagick.setinterlacescheme.php#83132
    $image->setInterlaceScheme(Imagick::INTERLACE_PLANE); 
    
    // save image
    $image->writeImage($imageFilePath);

  }
  // fall back to GD
  else {

    // determine final width and height
    $targetWidth  = ($rotationRequired === 180) ? $width  : $height;
    $targetHeight = ($rotationRequired === 180) ? $height : $width;
    
    // load and create image objects
    $sourceImage = @imagecreatefromjpeg($imageFilePath); // Use @ and ini_set('gd.jpeg_ignore_warning') in init.php to suppress gd invalid jpeg errors. See: http://bugs.php.net/bug.php?id=39918
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // rotate
    $targetImage = imagerotate($sourceImage, $rotationRequired, 0);

    // enable progressive JPEGs
    imageinterlace($targetImage, true);
    
    // save
    $savedFile = imagejpeg($targetImage, $imageFilePath, $GLOBALS['SETTINGS']['advanced']['imageResizeQuality']);
    if (!$savedFile) { die("Error saving file!"); }
    
    // clean up image objects
    imagedestroy($sourceImage);
    imagedestroy($targetImage);

  }
}

// returns true if the file path or the file extension is an image
// ... set $testAsExt to true if we're checking a file extension
// ... it checks for 'gif', 'jpg', 'png', 'svg', 'webp' image files
function isImage($filePathOrFileExtension, $testAsExt = false) {
  
  $isImage             = false;
  $imageFileExtensions = ['gif', 'jpg', 'png', 'svg', 'webp'];
  $filePathOrExt       = strtolower($filePathOrFileExtension);
  
  if ($testAsExt && in_array($filePathOrExt, $imageFileExtensions)) { // if this is a file extension
    $isImage = true;
  }
  elseif (preg_match("/\.(gif|jpg|jpeg|png|svg|webp)$/i", $filePathOrExt)){ // if this is a file name/path
    $isImage = true;
  }
  
  return $isImage;
}

// returns true if the image type is SVG
function isImage_SVG($filePath) {
  
  // get the file extension
  $fileName      = basename($filePath);
  $fileExtension = _saveUpload_getExtensionFromFileName($fileName);
  
  // return false if the file extension is neither svg nor tmp
  // note: we're allowing 'tmp' and '' extension so we can also check the file that's converted into a temp file during the upload process
  if (!in_array($fileExtension, ['svg', 'tmp',''])) { return false; }
  
  // return true if the <svg tag is found in the file contents
  $svgXML = @file_get_contents($filePath);
  if (preg_match("/<svg/i", $svgXML)) { return true; }
  
  // note: we're using the method above because getimagesize() can't be used to determine the image type as it returns blank
  // ... also, checking for mime type returns "image/svg+xml", but will also return "text/plain" if the doctype is not declared
  // ... DOCTYPE in SVG files isn't always declared
  
  return false;
}


// Check if image file is CMYK and overwrite it with an RGB image if it is
// NOTE: We do this because some browsers don't support CMYK images.
function image_convertCMYKToRGBIfNeeded($filePath) { 
  
  // Check if file is a CMYK image
  $isCMYK = true;
  $imageData = getimagesize($filePath);

  if     (!is_array($imageData))                                                       { $isCMYK = false; } // skip if no imagedata detected
  elseif (!array_key_exists('channels', $imageData) || $imageData['channels'] != 4)    { $isCMYK = false; } // not CMYK
  elseif (!array_key_exists('mime', $imageData) || $imageData['mime'] != 'image/jpeg') { $isCMYK = false; } // not a jpeg (gif and png don't support CMYK)
  if (!$isCMYK) { return; } // leave original image unchanged

  // Convert CMYK image to RGB
  if (extension_loaded('imagick')) {
    $image = new Imagick($filePath);
    $image->transformImageColorspace(Imagick::COLORSPACE_RGB);
    $success = $image->writeImage($filePath);
    if (!$success) { dieAsCaller(__FUNCTION__. ": Error rewriting CMYK image as RGB with Imagick:  " .@$php_errormsg ); }
  }
  else {
    $imageRes = imagecreatefromjpeg($filePath);
    $success = imagejpeg($imageRes, $filePath);
    if (!$success) { dieAsCaller(__FUNCTION__. ": Error rewriting CMYK image as RGB with GD: " .@$php_errormsg); }
    imagedestroy($imageRes);
  }
}


// For resizing images, specify the original image h/w and the target h/w and function will return a h/w
// ... that fits within target and still maintains aspect ratio. By default it will not scale up images.
// Examples:
// image_resizeCalc(32, 32, 50, 50)      --> array(32, 32, 1)        // image was not scaled up
// image_resizeCalc(64, 64, 50, 50)      --> array(50, 50, 0.78125)  // image was scaled down to fit
// image_resizeCalc(1920, 1080, 100, 50) --> array(89, 50, 0.046...) // image was height-bound
// image_resizeCalc(1920, 1080, 50, 100) --> array(50, 28, 0.026...) // image was width-bound
// list($finalWidth, $finalHeight, $scaledBy) = image_resizeCalc($upload['width'], $upload['height'], 50, 50);
function image_resizeCalc($sourceWidth, $sourceHeight, $targetWidthMax, $targetHeightMax, $allowScaleUp = false) {

  // catch divide by zero errors
  if ($sourceHeight == 0 || $sourceWidth == 0 || $targetHeightMax == 0) { return array(0, 0, 0); }

  $sourceAspectRatio    = $sourceWidth    / $sourceHeight;
  $targetAspectRatioMax = $targetWidthMax / $targetHeightMax;

  if ($sourceAspectRatio < $targetAspectRatioMax) { $scale = $targetHeightMax / $sourceHeight; }
  else                                            { $scale = $targetWidthMax  / $sourceWidth; }

  if ($allowScaleUp == false && $scale > 1) {
    $scale = 1;
  }

  //
  $finalWidth  = max(1, round($sourceWidth  * $scale));
  $finalHeight = max(1, round($sourceHeight * $scale));
  return array($finalWidth, $finalHeight, $scale);
}

/*====================================================================

image_convertToWebp(string $sourceFile [, string $targetFile [, string $extension ]])

- converts an image file to webp
- supports gif, jpg, png
- maintains transparency

@param string  $sourceFile  (required, full path and file name of source image)
@param string  $targetFile  (optional, full path and file name of target image, defaults to empty string)
@param string  $extension   (optional, preferred extension to use (imagick or gd), defaults to imagick)

returns: $targetFile on success, false on fail

====================================================================*/

function image_convertToWebp($sourceFile, $targetFile = '', $extension = 'imagick') {
  
  // do not continue if a source file name was not passed or the file does not exist
  if(empty($sourceFile) or !file_exists($sourceFile)) return false;
  
  // verify the file extension
  //if(!in_array(strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION)), array('gif', 'jpg', 'jpeg', 'png'))) return false;
  
  // get information about the source file
  list($width, $height, $type) = getimagesize($sourceFile);
  
  // do not continue if the image type is not valid
  if(!in_array($type, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG))) return false;
  
  // update the targetFile value if necessary
  if(!$targetFile) {
    $targetFileNoExt = preg_replace('/\.[^.]+$/', '', $sourceFile); // remove preview gif|jpg|etc extension
    $targetFile      = $targetFileNoExt . '.webp';
  }
  
  // try converting with imagemagick first
  if(extension_loaded('imagick') and $extension != 'gd' and in_array('WEBP', \Imagick::queryformats())) {
    
    $image = new Imagick($sourceFile);
    
    // this was necessary to get the first frame in an animated gif
    //if($type == IMAGETYPE_GIF) $image = $image -> coalesceImages();
    
    $image -> setImageFormat('webp');
    $image -> writeImage($targetFile);
    $image -> clear();
    $image -> destroy();
  }
  
  // fallback to gd
  elseif(function_exists('imagewebp')) {
    
    // create a new image based on the source image type
    switch($type) {
      
      case IMAGETYPE_GIF:
        $image = imagecreatefromgif($sourceFile);
        //imagepalettetotruecolor($image);
        break;
      
      case IMAGETYPE_JPEG:
        $image = imagecreatefromjpeg($sourceFile);
        break;
      
      case IMAGETYPE_PNG:
        $image = imagecreatefrompng($sourceFile);
        //imagepalettetotruecolor($image);
        //imagealphablending($image, true);
        //imagesavealpha($image, true);
        break;
    }
    
    // make sure the image is truecolour, not palette
    if(!imageistruecolor($image)) { imagepalettetotruecolor($image); }
    
    // output a webp image
    imagewebp($image, $targetFile);
    
    // destroy the gd image
    imagedestroy($image);
  }
  
  else {
    return false;
  }
  
  return $targetFile;
}

// source: https://www.php.net/manual/en/function.imagecreatefromgif.php
function image_isAnimatedGif($sourceFile) {
  
  $totalCount = 0;

  if(($handle = fopen($sourceFile, 'rb')) !== false) {
    
    $chunk = '';
    
    // An animated gif contains multiple "frames", with each frame having a header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)
    
    // We read through the file until we reach the end of it, or we've found at least 2 frame headers.
    while (!feof($handle) && $totalCount < 2) {
      
      // Read 100kb at a time and append it to the remaining chunk.
      $chunk .= fread($handle, 1024 * 100);
      $count = preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
      $totalCount += $count;
      
      // Execute this block only if we found at least one match,
      // and if we did not reach the maximum number of matches needed.
      if($count > 0 and $totalCount < 2) {
        
        // Get the last full expression match.
        $lastMatch = end($matches[0]);
        
        // Get the string after the last match.
        $end = strrpos($chunk, $lastMatch) + strlen($lastMatch);
        $chunk = substr($chunk, $end);
      }
    }
  
    fclose($handle);
  }
  
  return $totalCount > 1;
}


//eof
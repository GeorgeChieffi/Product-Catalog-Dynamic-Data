<?php

//
// shortcut function for return translate
function t($str) { return translateString($str); }

// shortcut function for echo translate
function et($str) { echo translateString($str); }

// shortcut function for echo htmlencode translate
// 2.16 switched from htmlspec ialchars to htmlencode
function eht($str) { echo htmlencode( translateString($str) ); }

// translate - return translated string
function translateString($originalString) {
  $langCode = @$GLOBALS['SETTINGS']['language'] ?: 'en';
  if (!$langCode && !$GLOBALS['SETTINGS']['advanced']['languageDeveloperMode']) { return $originalString; } // skip if no language defined
  static $translation = [];

  // get language dirs
  list($defaultLangDir, $adminLangDir, $pluginLangDir) = _languageFiles_getDirs();
  $isAdminMenu = in_array(@$_REQUEST['menu'], array('admin','database','_codeGenerator'));
  if     ($pluginLangDir) { $tryDirs = array($pluginLangDir, $adminLangDir, $defaultLangDir); }
  elseif ($isAdminMenu)   { $tryDirs = array($adminLangDir, $defaultLangDir); }
  else                    { $tryDirs = array($defaultLangDir); }
  $targetLangDir = $tryDirs[0]; // first dir to try and where language strings are added

  // load/cache language file and return translation
  $langFile = "$langCode.php";
  foreach ($tryDirs as $thisDir) {
    $thisFilepath       = "$thisDir/$langFile";
    // load/cache file
    if (!array_key_exists($thisFilepath, $translation)) {
      $translation[$thisFilepath] = _languageFile_load($thisFilepath);
    }

    // load string
    if (array_key_exists($originalString, $translation[$thisFilepath])) { // return translation
      $string = $translation[$thisFilepath][$originalString];
      if ($langCode == 'd|e|b|u|g') { $string = preg_replace("/(?<=\w)(\w)/", "|\\1", $string); } // added in v2.50
      return $langCode ? $string : $originalString; // v2.53 - if 'default' language selected, return original text here if translation found.  If no translation found, return original string below so string can be added to files.
    }
  }

  // if not found, add string if in developer mode and return original
  if ($GLOBALS['SETTINGS']['advanced']['languageDeveloperMode']) {
    _languageFiles_addString($defaultLangDir, $targetLangDir, $originalString);
  }
  if ($langCode == 'd|e|b|u|g') { $originalString =  preg_replace("/(?<=\w)(\w)/", "|\\1", $originalString); } // added in v2.50

  return $originalString;
}

//
function _languageFile_load($filepath) {
  $translation = [];
  if (!file_exists($filepath)) { return []; }
  //if (!file_exists($filepath))  { die("Language file doesn't exist at '$filepath'!"); }

  // v2.18 - convert old language files
  if (!isStructFile($filepath)) {
    require($filepath);
    _languageFile_save($filepath, $strings); // old files stored values in $strings var
  }

  // load file
  $translation = loadStruct($filepath);
  return $translation;
}

//
function _languageFile_save($filepath, $translation) {
  $headerMessage  = "### NOTE: This file was last updated: " .date('Y-m-d H:i:s'). "\n";
  $headerMessage .= "### NOTE: Changes to this file will be overwritten next time you upgrade!\n";
  $headerMessage .= "### NOTE: Either send us your changes or create a new language called custom.php to avoid this\n";
  $headerMessage .= "### Default Text => Translation Text";
  saveStruct($filepath, $translation, true, $headerMessage);
}


// add a language string to the language files
function _languageFiles_addString($defaultLangDir, $targetLangDir, $originalString) {

  // get language filenames (from target and default lang dirs)
  $langFiles = [];
  $langDirs  = array($defaultLangDir, $targetLangDir);
  foreach ($langDirs as $langDir) {
    if (!is_dir($langDir)) { continue; }
    $langFiles += preg_grep("/^\w\w\.php$/", scandir($langDir));
  }
  if ($GLOBALS['SETTINGS']['language'] && $GLOBALS['SETTINGS']['language'] != 'd|e|b|u|g') {
    $langFiles[] = $GLOBALS['SETTINGS']['language'] . ".php"; // add current language
  }
  $langFiles = array_unique($langFiles);

  // add language string to language files that don't have it in target language dir
  mkdir_recursive($targetLangDir); // create target dir if needed
  foreach ($langFiles as $langFile) {
    $langFilepath = "$targetLangDir/$langFile";
    $translation  = _languageFile_load($langFilepath);
    if (!array_key_exists($originalString, $translation)) {
      $translation[$originalString] = $originalString;
     _languageFile_save($langFilepath, $translation);
    }
  }
}

// list($defaultLangDir, $adminLangDir, $pluginLangDir) = _languageFile_getDirs($source);
function _languageFiles_getDirs() {
  $source = 'auto'; // possible future optimization to avoid checking all language files on every lookup.  Add $source parameter to function (auto, 'default', 'admin', $pluginDirPath)

  // get calling plugin language dir (if being called by a plugin)
  $pluginLangDir = '';
  if (!in_array($source, array('default', 'admin'))) {
    $callerFilepaths  = array_map('fixSlashes', array_unique( array_pluck( debug_backtrace(), 'file' )));
    $pluginDir        = fixSlashes( SCRIPT_DIR .'/plugins/');
    $pluginDirRegexp  = '/^' .preg_quote($pluginDir, '/'). '/';
    $pluginPath       = array_value(array_values(preg_grep($pluginDirRegexp, $callerFilepaths)), 0);
    $pluginLangDir    = $pluginPath ? fixSlashes( dirname($pluginPath).'/languages' ) : '';
  }

  // get other language dirs
  static $defaultLangDir, $adminLangDir;
  if (!isset($defaultLangDir)) { $defaultLangDir = SCRIPT_DIR."/lib/languages"; }
  if (!isset($adminLangDir))   { $adminLangDir   = SCRIPT_DIR."/lib/languages/adminMenu"; }

  //
  return array($defaultLangDir, $adminLangDir, $pluginLangDir);
}

// eof

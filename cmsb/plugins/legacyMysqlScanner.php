<?php
/*
Plugin Name: Legacy MySQL Scanner
Description: Search for references to legacy PHP MySQL functions so they can be upgraded to mysqli (Required for PHP 7)
Version: 1.03
Requires at least: 3.11
*/

// DON'T UPDATE ANYTHING BELOW THIS LINE

//
function lms_scanPlugins() { _lms_scanDir(SCRIPT_DIR ."/plugins/"); }
function lms_scanWebsite() { _lms_scanDir(absPath(SCRIPT_DIR ."/../")); }

// these are mysql_* suffixes to skip because they're no related to legacy mysql_ functions
$GLOBALS['LMSCANNER_SKIP_SUFFIXES'] = [ 
  'count',
  'datetime',
  'decrypt_addSelectExpr',
  'decrypt_column',
  'decrypt_getColumnsNamesOrExpr',
  'delete',
  'do',
  'encrypt_column',
  'encrypt_listColumns',
  'encryptExpr_colsToValues',
  'escape',
  'escapecsv',
  'escapef',
  'escapeLikeWildcards',
  'fetch',
  'get',
  'get_lock',
  'get_query',
  'getcolstovaluesfromrequest',
  'getMysqlSetValues',
  'getvaluesascsv',
  'insert',
  'isConnected',
  'isSupportedColumn',
  'limit',
  'query_fetch_all_array',
  'query_fetch_all_assoc',
  'query_fetch_row_array',
  'query_fetch_row_assoc',
  'release_lock',
  'select',
  'select_count_from',
  'select_query',
  'session_storage',
  'set',
  'update',
  'where',
];
$GLOBALS['LMSCANNER_SKIP_SUFFIXES'] = array_map('strtolower', $GLOBALS['LMSCANNER_SKIP_SUFFIXES']);  // lowercase suffixes

// plugin actions
pluginAction_addHandlerAndLink('Scan plugins', 'lms_scanPlugins', 'admins');
pluginAction_addHandlerAndLink('Scan website', 'lms_scanWebsite', 'admins');


//
function _lms_scanDir($dir) {
  $GLOBALS['LMSCANNER_DIR'] = $dir; 


  $pluginsDir = absPath(SCRIPT_DIR ."/plugins/");

  // get files
  print "<h3>Scanning directory: <b>{$GLOBALS['LMSCANNER_DIR']}</b> (You can change this in the plugin)</h3>\n";
  print "Note: This is a reference only, click on the legacy function name and see PHP.net docs for details on specific replacement recommendations.<br>\n";
  print "Note: And when making replacements, note that some end in brackets () and some don't.<br>\n";
  print "<hr>\n"; 
  $phpFilepaths = scandir_recursive($GLOBALS['LMSCANNER_DIR'], "/\.php\z/i");

  // skip list
  
  // scan files
  $fileCounter = 0;
  $matchedFileCounter = 0;
  $globalFunctionList  = [];
  $fileSkipKeywords    = ['/aws_s3/','/legacyMysqlScanner.php','/database_functions.php']; // skip paths with these keywords
  foreach ($phpFilepaths as $phpFilepath) {
    $isScanningPlugins = startsWith($pluginsDir, $GLOBALS['LMSCANNER_DIR']);
    if (!$isScanningPlugins && startsWith($pluginsDir, $phpFilepath)) { continue; }
    
    // skip non-code files AND files with skip keywords
    if (preg_match("/\.ini\.php\z/i", $phpFilepath))            { continue; }
    if (preg_match("/\.defaultSqlData\.php\z/i", $phpFilepath)) { continue; }
    foreach ($fileSkipKeywords as $keyword) { if (contains($keyword, $phpFilepath)) { continue 2; }}
    
    // count files scanned
    $fileCounter++;
  
    // scan for mysql_ functions that need upgrading
    $code = file_get_contents($phpFilepath);
    $functionsFound = [];
    if (preg_match_all("/\b(mysql_(\w+))\s*\(/i", $code, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        list($matchedString, $function, $suffix) = $match; // eg: mysql_escapef, escapef
        if (in_array(strtolower($suffix), $GLOBALS['LMSCANNER_SKIP_SUFFIXES'])) { continue; }
        $functionsFound[] = $function;
        $globalFunctionList[] = $function;
      }
    }
    if (!$functionsFound) { continue; } // skip file if no matches

    // show relative filepath and matched functions
    print str_replace($GLOBALS['LMSCANNER_DIR'], "", $phpFilepath); //
    
    $functionsFound = array_unique($functionsFound);
    natcasesort($functionsFound);
    lms_showFunctionsAndReplacements($functionsFound);
    
    $matchedFileCounter++;
  }

  // show list of all matched functions
  //if ($matchedFileCounter) { 
  //  print "\n<hr><b>Matched function names</b>\n";
  //  $globalFunctionList = array_unique($globalFunctionList);
  //  natcasesort($globalFunctionList);
  //  lms_showFunctionsAndReplacements($globalFunctionList);
  //}
  
  //
  if (!$matchedFileCounter) {
    print "No PHP files with legacy mysql code found!"; 
  }
  
  //
  print "\n<hr>\n"; 
  print "Files scanned: $fileCounter<br>\n"; 
  print "Execute time: "  .showExecuteSeconds(true). " seconds<br>\n"; 
  print "Done";
  exit;

}


//
function lms_showFunctionsAndReplacements($functions) {
  if (!$functions) { return; }
  
  print "<ul>\n";
  foreach ($functions as $function) {
      $instructions = ""; 
      $replacement = "";
      if ($function == 'mysql_affected_rows')      { $replacement  = "mysqli()->affected_rows;"; }
      if ($function == 'mysql_close')              { $instructions = "mysqli()->close();"; }
      if ($function == 'mysql_connect')            { $replacement  = "See connectToMySQL() and http://php.net/manual/en/mysqli.quickstart.connections.php"; }
      if ($function == 'mysql_error')              { $replacement  = "mysqli()->error;"; }
      if ($function == 'mysql_fetch_array')        { $replacement  = "mysqli_fetch_array(\$result);"; }
      if ($function == 'mysql_fetch_row')          { $replacement  = "mysqli_fetch_row(\$result);"; }
      if ($function == 'mysql_fetch_assoc')        { $replacement  = "mysqli_fetch_assoc(\$result);"; }
      if ($function == 'mysql_free_result')        { $replacement  = "mysqli_free_result(\$result);"; }
      if ($function == 'mysql_insert_id')          { $replacement  = "mysqli()->insert_id;"; }
      if ($function == 'mysql_num_rows')           { $replacement  = "mysqli_num_rows(\$result);"; }
      if ($function == 'mysql_ping')               { $replacement  = "mysqli()->ping();"; }
      if ($function == 'mysql_real_escape_string') { $replacement  = "mysql_escape(...);"; }
      if ($function == 'mysql_query')              { $replacement  = "mysqli()->query(...);"; }
      if ($function == 'mysql_result')             { $instructions = "Example: Replace \$value = mysql_result(\$result, 1, 3) with: mysqli_data_seek(\$result, 1); \$value = mysqli_fetch_array(\$result)[3]; // test after replacing!"; }
      if ($function == 'mysql_select_db')          { $replacement  = "mysqli()->select_db (...);"; }

      if ($replacement) { $instructions .= "replace with: $replacement"; }
      print "<li><a href='http://php.net/$function' target='_blank'>$function</a> - $instructions</li>";
  } 
  print "</ul>\n";

}

// eof
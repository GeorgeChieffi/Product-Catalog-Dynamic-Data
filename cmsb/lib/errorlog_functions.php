<?php
/*
  NOTES:
    - You can manually create an error log entry with this code and the error will be
      ... NOT be displayed to end user and code execution won't be interrupted:

      @trigger_error("Your error message", E_USER_NOTICE);

      To display an error (if show errors is enabled) and die, use this code:

      trigger_error("Your error message", E_USER_ERROR);
    
    - This is a good way to capture a snapshot of the global vars and symbol table.
    - *** NOTE: If "Hide Errors" is enabled under Admin > General, then the above will produce "(An unexpected error occurred: #1234)" where the number is the error number
    
    - We can't/don't catch E_PARSE compile time errors that are returned by the parser.
    - List of error types: http://php.net/manual/en/errorfunc.constants.php
*/

// enable error logging
if (isInstalled()) {
  //if ($_SERVER['REQUEST_METHOD'] != 'HEAD') { // prevents HEAD request errors being logged from _errorlog_getBrokenPhpFunctionsWarning()
  errorlog_enable();
  //}
}

// check if function is being called from error log (to prevent recursive errors)
function errorlog_inCallerStack() {
  $functionStack = array_column( debug_backtrace(), 'function' );
  $inCallerStack = array_search('_errorlog_logErrorRecord', $functionStack) !== false;
  return $inCallerStack;
}

// enable error logging - log error to internal mysql table
function errorlog_enable() {
  
  // error-checking
  if (error_reporting() !== -1) { die(__FUNCTION__ . ": error_reporting() must be set to -1, not " .error_reporting(). "!"); }
   
  // setup handlers
  set_error_handler('_errorlog_catchRuntimeErrors');  
  set_exception_handler('_errorlog_catchUncaughtExceptions');
  register_shutdown_function('_errorlog_catchFatalErrors');
}

// catch runtime errors - called by set_error_handler() above
// argument definitions: http://php.net/manual/en/function.set-error-handler.php
function _errorlog_catchRuntimeErrors($errno = '', $errstr = '', $errfile = '', $errline = '', $errcontext = '') {  
  _errorlog_alreadySeenError($errfile, $errline); // track that this error was seen so we don't report it again in _errorlog_catchFatalErrors()
  $is_E_USER_TYPE   = in_array($errno, array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED));  // E_USER_* errors are called explicitly, so we'll assume if they use @error-suppression they still want to be logged
  $isErrorSupressed = (error_reporting() === 0 or error_reporting() === 4437); // error_reporting() returns 0 when @ is used in php < 8 and 4437 in PHP >= 8
  if ($isErrorSupressed && !$is_E_USER_TYPE) {  // ignore '@' error control operator except for E_USER_
    return false;  // continue standard PHP execution (includes: error handling and set error_get_last)
  } 

  $logData = array(
    'logType'     => 'runtime',
    'errno'       => $errno,
    'errstr'      => $errstr,
    'errfile'     => $errfile,
    'errline'     => $errline,
    'errcontext'  => $errcontext,
  );
  $errorRecordNum = _errorlog_logErrorRecord($logData);

  // PHP will not continue or show any errors if we're catching exceptions, so show errors ourselves if it's enabled:
  if (!ini_get('display_errors') && !$isErrorSupressed) {  // or check: $GLOBALS['SETTINGS']['advanced']['phpHideErrors']
    $error = errorlog_messageWhenErrorsHidden($errorRecordNum);
    print $error;
  }
  
  //
  return false; // return false to continue with standard PHP error handling and set error_get_last() - NOTE: @suppressed errors are still suppressed
}


// catch fatal errors - called by register_shutdown_function() above
function _errorlog_catchFatalErrors() {
  $error = error_get_last();
  if ($error === null) { return; } // no error
  if (_errorlog_alreadySeenError($error['file'], $error['line'])) { return; } // error already processed (or ignored for @hidden warnings)

  $logData = array(
    'logType'     => 'fatal',
    'errno'       => $error['type'],     // eg: 8   - from: http://php.net/manual/en/errorfunc.constants.php
    'errstr'      => $error['message'],  // eg: Undefined variable: a
    'errfile'     => $error['file'],     // eg: C:\WWW\index.php
    'errline'     => $error['line'],     // eg: 2
  );
  _errorlog_logErrorRecord($logData);

  // halt script execution
  exit;
}


// catch uncaught exceptions - called by set_error_handler() above
function _errorlog_catchUncaughtExceptions($exceptionObj) {
  //$logData = (array) $exceptionObj; // http://php.net/manual/en/class.exception.php
  $logData = array(
    'logType'      => 'exception',
    'errno'        => 'UNCAUGHT_EXCEPTION',
    'errstr'       => $exceptionObj->getMessage(),  // method reference: http://php.net/manual/en/language.exceptions.extending.php
    'errfile'      => $exceptionObj->getFile(),
    'errline'      => $exceptionObj->getLine(),
    'exceptionObj' => (array) $exceptionObj,
    'backtrace'    => _errorlog_getExceptionBacktraceText($exceptionObj),
  );
  $errorRecordNum = _errorlog_logErrorRecord($logData);
  
  // PHP will not continue or show any errors if we're catching exceptions, so show errors ourselves if it's enabled:
  if (ini_get('display_errors')) {  // or check: $GLOBALS['SETTINGS']['advanced']['phpHideErrors']
    //$error  = "Fatal Error: Uncaught exception '".get_class($exceptionObj)."'";
    //$error .= " with message '".$exceptionObj->getMessage()."'";
    //$error .= " in ".$exceptionObj->getFile(). " on line " .$exceptionObj->getLine();
    $error  = $exceptionObj->getMessage()."\n";
    $error .= "in ".$exceptionObj->getFile();
    $error .= " on line " .$exceptionObj->getLine(). ". "; 
  }
  else {
    $error = errorlog_messageWhenErrorsHidden($errorRecordNum);
  }
  print $error;
  
  // halt script execution after uncaught exceptions
  exit;
}

// return true if error matches a previous one processed
// Background: Code that supresses errors with @ still causes set_error_handler() to be called, but we can
// ... detect that scenario by checking if error_reporting() === 0, but when catching fatal errors with
// ... register_shutdown_function() error_reporting() can't be used to detect previous use of @, so we check
// ... errors reported there were previously seen (and ignored) by _errorlog_catchRuntimeErrors().
function _errorlog_alreadySeenError($filePath, $lineNum) {
  static $filesToLineNums = [];

  // have we seen this error
  $alreadySeenError = !empty($filesToLineNums[$filePath][$lineNum]);

  // record this as a seen error
  $filesToLineNums[$filePath][$lineNum] = 1;

  //
  return $alreadySeenError;
}


// log errors - used to log error caught with: set_error_handler, set_exception_handler, register_shutdown_function
// NOTE: If you add "[MAXLOG1]" to the end of 'errstr' all previous error logs with the same error message will be removed leaving only 1
// max errors in log: 1000 (oldest records are removed when record count hits 1100)
// max errors to log per page: 25 (further errors won't be logged)
/*
    $errorRecordNum = _errorlog_logErrorRecord([
      'errno'      => 'CUSTOM ERROR [LOGMAX1]', // (OPTIONAL) Errorfunc constant or text string you'd like to use as a prefix
      'errstr'     => "Error message goes here",
      'errfile'    => __FILE__,       // pathfile of file error occurred in
      //'errline'    => __LINE__,       // (OPTIONAL) line number where error occurred
      //'errurl'     => null,        // (OPTIONAL) url of script where error occurred, automatically set if not defined
      //'backtrace'  => null,        // (OPTIONAL) backtrace text, automatically set if not defined
      //'errcontext' => $GLOBALS,    // (OPTIONAL) symbol table, not set if it's not defined
    ]);
*/
function _errorlog_logErrorRecord($logData) {
  if (!isInstalled()) { return; } 
  
  // limit errors logged per session (to prevent infinite loops from logging infinite errors)
  $maxErrorsPerPage = 25;
  $maxErrorsReached = false;
  static $totalErrorsLogged = 0;
  $totalErrorsLogged++;
  if ($totalErrorsLogged > ($maxErrorsPerPage+1)) { return; } // ignore any errors after max error limit
  if ($totalErrorsLogged > $maxErrorsPerPage) { $maxErrorsReached = true; }

  // create error message
  if ($maxErrorsReached)        { $errorMessage = t(sprintf("Max error limit reached! Only the first %s errors per page will be logged.", $maxErrorsPerPage)); }
  else { 
    if (isset($logData['errno'])) { $errorName = _errorlog_erronoToConstantName($logData['errno']); } // eg: E_WARNING
    else                          { $errorName = 'UNKNOWN_ERROR'; }
    $errorMessage = "$errorName: " . (isset($logData['errstr']) ? $logData['errstr'] : '');
  }
  
  // detect servers with broken print_r, etc functions.  See function header for more details
  $errorMessage = rtrim($errorMessage, "\n");
  $errorMessage .= _errorlog_getBrokenPhpFunctionsWarning();

  // add to error summary
  $errfile = isset($logData['errfile']) ? $logData['errfile'] : '';
  $errline = isset($logData['errline']) ? $logData['errline'] : '';
  errorlog_summary($errorMessage, $errfile, $errline);

  // get backtrace text
  if (isset($logData['backtrace'])) { // created for exceptions by _errorlog_catchUncaughtExceptions
    $backtraceText = $logData['backtrace'];
    unset($logData['backtrace']); // we don't need to log this twice
  }
  else {
    // emulate debug_print_backtrace since we can't rely on ob_start being available (see: _errorlog_getBrokenPhpFunctionsWarning)
    $backtraceText = ''; 
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 100) as $index => $frame) {
      $backtraceText .= "\n#{$index}  {$frame['function']}() called";
      if (isset($frame['line']) || isset($frame['line'])) { $backtraceText .= " at [{$frame['file']}:{$frame['line']}]"; }
    }
    $backtraceText = ltrim($backtraceText, "\n");
  }

  // check if we have a valid mysql connection
  if (mysql_isConnected() && !@mysqli()->ping()) { mysql_isConnected(false); } // flag as disconnected if can't ping so isConnected checks below return correct value

  //  create log record data
  $colsToValues = array(
    'dateLogged='      => 'NOW()',
    'updatedDate='     => 'NOW()',  // set this so we can detect if users modify error log records from within the CMS
    'updatedByuserNum' => '0',      // set this so we can detect if users modify error log records from within the CMS

    'error'           => $errorMessage,
    'url'             => isset($logData['errurl']) ? $logData['errurl'] : thisPageUrl(),
    'filepath'        => isset($logData['errfile']) ? $logData['errfile'] : '', // $logData['errfile'],
    'line_num'        => isset($logData['errline']) ? $logData['errline'] : '', // $logData['errline'],
    'user_cms'        => _errorlog_getCurrentUserSummaryFor('cms'),
    'user_web'        => _errorlog_getCurrentUserSummaryFor('web'),
    'http_user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', // $_SERVER['HTTP_USER_AGENT'],
    'remote_addr'     => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',

    'backtrace'       => $backtraceText,
    'request_vars'    => _errorlog_serialize($_REQUEST),
    'get_vars'        => _errorlog_serialize($_GET),
    'post_vars'       => _errorlog_serialize($_POST),
    'cookie_vars'     => _errorlog_serialize($_COOKIE),
    'session_vars'    => isset($_SESSION) ? _errorlog_serialize($_SESSION)  : '',
    'server_vars'     => _errorlog_serialize($_SERVER),
    'symbol_table'    => isset($logData['errcontext']) ? "Script died before symbol table could be written (possible infinite recursion?)" : 'Not available',
    'raw_log_data'    => "Script died before raw log data could be written (possible infinite recursion?)", //
  );
  
  // debug - log error log output to text log
  $enable_debug_errorlog = false;
  if ($enable_debug_errorlog) {
    $debug_errorlog_data = '';
    $debug_errorlog_file = 'error_log_debug.log';
    $debug_errorlog_path = DATA_DIR."/$debug_errorlog_file";
    $debug_errorlog_data .= "\n\nError Log \$colsToValues:\n" . _errorlog_serialize($colsToValues);
    //$debug_errorlog_data .= "\n\nError Log ColsToValues (utf8_force):\n" . _errorlog_serialize(utf8_force($colsToValues));
    file_put_contents($debug_errorlog_path, $debug_errorlog_data, FILE_APPEND);
  }

  // Single log errors - if [MAXLOG1] string found, remove it and all errors with same title before adding error
  if (mysql_isConnected()) {
    $colsToValues['error']  = preg_replace("/\s*\[MAXLOG1\]/", "", $colsToValues['error'], -1, $foundMaxLogString);
    $isErrorLogEditViewPage = isset($_REQUEST['menu']) && $_REQUEST['menu'] == '_error_log' && in_array(getRequestedAction(), ['edit','view']);  // don't erase duplicate errors while viewing error_log, otherwise errors that get triggered on every page will get erased when we click from list -> edit page resulting in an blank/missing/empty record when we click view/modify
    if ($foundMaxLogString && !$isErrorLogEditViewPage) { mysql_delete('_error_log', null, ['error' => $colsToValues['error']]); }
  }
  
  // insert record - use manual code to catch errors
  $recordNum = 0;
  if (mysql_isConnected()) {
    $tableName    = $GLOBALS['TABLE_PREFIX'] . '_error_log';
    $insertQuery  = "INSERT INTO `$tableName` SET " . mysql_set(utf8_force($colsToValues));
    mysqlStrictMode(false); // hide errors in case of extra fields in _error_log that return "default value not set" warning
    $success = mysqli()->query($insertQuery);
    $recordNum = mysqli()->insert_id;
    if (!$success) {
      print "Error writing to error log!\nMySQL Error: ". mysqli()->error. "<br>\n<br>\n";
      print "Original error: ";
      if (ini_get('display_errors')) {
        print htmlencodef("?\n in ?:?<br>\n<br>\n", $colsToValues['error'], $colsToValues['filepath'], $colsToValues['line_num']);
      }
      else {
        print "Not shown because 'Hide PHP Errors' is enabled under Admin &gt; General.<br>\n";
      }
    }
    mysqlStrictMode(true);
  }

  // add raw_log_data and symbol_table to error record - do this as a separate step so infinite recursion and memory limit problems don't prevent initial error record from being saved
  if (mysql_isConnected() && $recordNum) {
    
    // Add raw_log_data first: in case symbol_table have infinite recursion, we'll have at least raw_log_data before the memory limit exceeds
    // create $logDataSummary without symboltable
    $logDataSummary = $logData;
    if (isset($logData['errcontext']))   { $logDataSummary['errcontext']   = "*** in symbol table field above ***"; }  // errcontext data doesn't need to be displayed twice
    if (isset($logData['exceptionObj'])) { $logDataSummary['exceptionObj'] = "*** in backtrace field above ***"; }     // exceptionObjects can cause infinite recursion
    $rawLogDataAsText  =  utf8_force(_errorlog_serialize($logDataSummary));
    $updateQuery       = mysql_escapef("UPDATE `$tableName` SET `raw_log_data` = ? WHERE `num` = ?", $rawLogDataAsText, $recordNum);
    $success           = mysqli()->query($updateQuery);
    if (!$success) { print "Error adding raw log data to error log num $recordNum!\nMySQL Error: ". mysqli()->error. "<br>\n<br>\n"; }
    
    // Add symbol_table
    if (isset($logData['errcontext'])) {
      $symbolTableAsText =  utf8_force(_errorlog_serialize($logData['errcontext']));
      $updateQuery       = mysql_escapef("UPDATE `$tableName` SET `symbol_table` = ? WHERE `num` = ?", $symbolTableAsText, $recordNum);
      $success           = @mysqli()->query($updateQuery); // @ - hide warnings generated here when mysql connection goes away: Warning: mysqli::query(): MySQL server has gone away in ...
      if (!$success) { 
        print "Error adding symbol table to error log num $recordNum!\nMySQL Error: ". mysqli()->error. "<br>\n<br>\n"; 
        
        // set connection to false on "gone away" error
        if (mysqli()->errno == 2006) { mysql_isConnected(false); }
      }
    }
    
    
  }
  
  // remove old log records
  if (mysql_isConnected()) {
    $maxRecords = 900;
    $buffer     = 100; // only erase records when we're this many over (to avoid erasing records every time)
    if (mysql_count('_error_log') > ($maxRecords + $buffer)) {
      $oldestRecordToSave_query = "SELECT * FROM `{$GLOBALS['TABLE_PREFIX']}_error_log` ORDER BY `num` DESC LIMIT 1 OFFSET " .($maxRecords-1);
      $oldestRecordToSave = mysql_get_query($oldestRecordToSave_query);
      if (!empty($oldestRecordToSave['num'])) {
        mysql_delete('_error_log', null, "num < {$oldestRecordToSave['num']}");
      }
    }
  }
  
  // send email update
  register_shutdown_function('_errorlog_sendEmailAlert');

  // 
  return $recordNum;
}

//
// list of constants: // http://php.net/manual/en/errorfunc.constants.php
function _errorlog_erronoToConstantName($errno) {
 
  static $numsToNames;
  
  // create index of nums to names
  if (!$numsToNames) {
    foreach (get_defined_constants() as $name => $num) {  
      if (preg_match("/^E_/", $name)) { $numsToNames[$num] = $name; }
    }
  }
  
  //
  if (array_key_exists($errno, $numsToNames)) { return $numsToNames[$errno]; }
  else                                        { return $errno; }
}

// Send an email after the script has finished executing, called by _errorlog_logErrorRecord function
// register this function to run with: register_shutdown_function('_errorlog_sendEmailAlert');
function _errorlog_sendEmailAlert() {
  if (!$GLOBALS['SETTINGS']['advanced']['phpEmailErrors']) { return; } 
  if (!mysql_isConnected()) { return; } // can't load records if we can't connect to mysql
  
  // send hourly email alert about new errors
  $secondsAgo = time() - $GLOBALS['SETTINGS']['errorlog_lastEmail'];
  if ($secondsAgo >= (60*60)) { // don't email more than once an hour

    // get date format
    if     ($GLOBALS['SETTINGS']['dateFormat'] == 'dmy') { $dateFormat  = "jS M, Y - h:i:s A"; }
    elseif ($GLOBALS['SETTINGS']['dateFormat'] == 'mdy') { $dateFormat  = "M jS, Y - h:i:s A"; }
    else                                                 { $dateFormat  = "M jS, Y - h:i:s A"; }

    // load latest error list
    $latestErrors     = mysql_select('_error_log', "`dateLogged` > (NOW() - INTERVAL 1 HOUR) ORDER BY `dateLogged` DESC LIMIT 25");
    $latestErrorsList = '';
    foreach ($latestErrors as $thisError) {
      $latestErrorsList .= date($dateFormat, strtotime($thisError['dateLogged']))."\n";
      $latestErrorsList .= $thisError['error']."\n";
      $latestErrorsList .= $thisError['filepath']." (line ".$thisError['line_num'].")\n";
      $latestErrorsList .= $thisError['url']."\n\n";
    }

    // send email message
    $placeholders = array(
      'error.hostname'         => parse_url($GLOBALS['SETTINGS']['adminUrl'], PHP_URL_HOST),
      'error.latestErrorsList' => nl2br(htmlencode($latestErrorsList)),
      'error.errorLogUrl'      => realUrl("?menu=_error_log", $GLOBALS['SETTINGS']['adminUrl']),
      'error.servername'       => php_uname('n'),
    );
    $errors  = sendMessage(emailTemplate_loadFromDB(array(
      'template_id'  => 'CMS-ERRORLOG-ALERT',
      'placeholders' => $placeholders,
    )));

    // log/display email sending errors
    if ($errors) {
      trigger_error("Unable to send error notification email from " .__FUNCTION__ . ": $errors", E_USER_NOTICE);
      die(__FUNCTION__. ": $errors");
    }

    // update last emailed time
    $GLOBALS['SETTINGS']['errorlog_lastEmail'] = time();
    saveSettings();
  }
  
}


// Return a more detailed backtrace of exceptions
// Based on jTraceEx from: http://php.net/manual/en/exception.gettraceasstring.php#114980
// Usage: $stack = _errorlog_getExceptionBacktraceText($exceptionErrorObj)
function _errorlog_getExceptionBacktraceText($e, $seen=null) {
  $starter = $seen ? 'Caused by: ' : '';
  $result = [];
  if (!$seen) $seen = [];
  $trace    = $e->getTrace();
  $prev     = $e->getPrevious();
  $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
  $file = $e->getFile();
  $line = $e->getLine();
  while (true) {
    $current = "$file:$line";
    if (is_array($seen) && in_array($current, $seen)) {
        $result[] = sprintf(' ... %d more', count($trace)+1);
        break;
    }
    $result[] = sprintf(' at %s%s%s [%s%s%s]',
                        count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
                        count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
                        count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
                        $line === null ? $file : basename($file),
                        $line === null ? '' : ':',
                        $line === null ? '' : $line);
    if (is_array($seen))
        $seen[] = "$file:$line";
    if (!count($trace))
        break;
    $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
    $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
    array_shift($trace);
  }
  $result = join("\n", $result);
  if ($prev) {
    $result  .= "\n" . _errorlog_getExceptionBacktraceText($prev, $seen);
  }

  return $result;
}

// get summary data of logged in users
// Usage: _errorlog_getCurrentUserSummaryFor($userType); // cms or web
function _errorlog_getCurrentUserSummaryFor($userType = '') {
  if (!mysql_isConnected()) { return ''; }

  if     ($userType == 'cms') {
    $tablename     = "accounts";
    $userRecord    = getCurrentUserFromCMS();
  }
  elseif ($userType == 'web') {
    $tablename     = accountsTable();
    $userRecord    = getCurrentUser();
  }
  else {
    dieAsCaller("Unknown userType '" .htmlencode($userType). "'!");
  }

  // create summary data
  $summaryText   = "";
  if ($userRecord) {
    $summaryFields = array('num','username');
    $summaryRecord = array_intersect_key($userRecord, array_flip($summaryFields));
    $summaryRecord['_tableName'] = $tablename;
    $summaryText   = _errorlog_serialize($summaryRecord);
  }

  //
  return $summaryText;
}

// Returns HTML summary of errors from the current script execution (for use in plugins)
// Usage: list($errorMessages, $errorCount) = errorlog_summary(); // get HTML summary of errors
// Usage: errorlog_summary($error['error'], $error['filepath'], $error['line_num']); // Add an error to summary
function errorlog_summary($errorMessage = '', $filepath = '', $lineNum = '') {
  static $summary = '';
  static $count   = 0;

  // add to summary
  if ($errorMessage || $filepath || $lineNum) {
    $filename       = basename($filepath);
    $errorMessage   = trim($errorMessage); // remove any nextlines since we're adding our own
    $errorMessage   = preg_replace('/\n([^\n]*)/', "\n&nbsp;&nbsp;\\1", $errorMessage); // indent all but first line in errors so it's easy to see where errors start and end.
    $summary       .= nl2br(htmlencodef("<b>?:?</b> &mdash; ?\n", $filename, $lineNum, $errorMessage));
    $count++;
  }

  //
  return [$summary, $count];
}

// show this when errors are hidden
function errorlog_messageWhenErrorsHidden($recordNum) {
  $error = "\n" .sprintf(t("(An unexpected error occurred: #%s)"), $recordNum). "\n";
  return $error;
}


// returns a text warning if broken PHP functions are detected on server, or blank otherwise
// detect servers with broken print_r (Seen July 2015 on 1&1 UK Shared Hosting Package occurs
// ... on HEAD request after any content sent, and causes our error logs to be blank!
// These errors can be recreating by sending a HEAD request from the command line with curl
// ... as follows: curl -I https://www.example.com/
function _errorlog_getBrokenPhpFunctionsWarning() {
  static $brokenFunctions; // cache result
  
  // if no cached result, check for broken functions
  if (!is_array($brokenFunctions)) {
    $brokenFunctions = [];
    
    // test if ob_start/ob_get_clean() is working.
    $ob_get_clean_response  = '1';
    $inOutputBufferCallback = 0;  // PHP throws an error if you call output buffering inside an output buffer callback
    foreach (ob_get_status(true) as $bufferStatus) { if ($bufferStatus['type'] == 1) { $inOutputBufferCallback = true; break; }}
    if (!$inOutputBufferCallback) { ob_start(); echo '1'; $ob_get_clean_response = ob_get_clean(); }
    
    //
    if (print_r(1, true) != '1')       { $brokenFunctions[] = "print_r"; }
    if ($ob_get_clean_response != '1') { $brokenFunctions[] = "ob_get_clean"; }
    if (var_export(1, true) != '1')    { $brokenFunctions[] = "var_export"; }   // no reported errors with this function yet
    if (json_encode(1) != '1')         { $brokenFunctions[] = "json_encode"; }  // no reported errors with this function yet
  }
  
  // return
  $errorMessage = ''; 
  if ($brokenFunctions) {
    $brokenFunctionsAsCSV = implode(', ', $brokenFunctions);
    $errorMessage .= "\n\nNOTE: Server has broken PHP functions: $brokenFunctionsAsCSV. This is sometimes caused by HTTP HEAD requests. ";
    $errorMessage .= "Error occurred during a HTTP {$_SERVER['REQUEST_METHOD']} request from: {$_SERVER['REMOTE_ADDR']}\n"; 
  }
  return $errorMessage;
}

// print_r doesn't work on all servers (see: _errorlog_getBrokenPhpFunctionsWarning) so this uses alternatives where needed
// NOTE: This function also REMOVED sensitive values (passwords, etc) for security!
function _errorlog_serialize(&$var) {
  $output = ""; 
  
  // check for broken output functions
  static $brokenFunctions = null; // cache result
  if (is_null($brokenFunctions)) { $brokenFunctions = (bool) _errorlog_getBrokenPhpFunctionsWarning(); }
  
  //
  if ($brokenFunctions) {
    // json_encode returns NULL for recursive/circular references ONLY if it's already seen them, so passing [$var]
    // ... causes it to catch the first circular reference, whereas $var doesn't.  This comes up with $GLOBALS with
    // ... contains $GLOBALS['GLOBALS'] and so on.
    $output = json_encode([$var], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR ); 
    $output = preg_replace("/^(\[$|    |\]$)/m", '', $output); // remove 4 space leading padding and surrounding [ and ] 
    $output = preg_replace("/    /", '  ', $output); // replace 4 space padding with 2
    if (json_last_error()) { $output = "// JSON Encoding Error: " .json_last_error_msg(). "\n$output";  }
    return $output;
  
    // Alternative option: var_export is also an option, but var_export can't handle recursions and 'errcontext' sometimes
    // lists $GLOBAL which lists $GLOBAL and so on.  This may not be an issue with PHP 5.6+
    $output = @var_export($struct, true);
  }
  else {
    $output = print_r($var, true);
  }
  
  ### remove potentially sensitive values
  $keysRegexp  = ".*?(api|code|key|password|secret|token).*?";
  $replaceText = "*** symbols containing '\\2' are not logged ***";
  $output  = preg_replace("/(\[$keysRegexp\])\s*=>\s*.*?$/im", "\\1 => $replaceText", $output);  // print_r style.  eg: [my_password_str] => value,
  $output  = preg_replace("/(\"$keysRegexp\")\s*:\s*.*?$/im",  "\\1 = $replaceText", $output);  // json_style.     eg: "my_password_str": "value",
  
  //
  return $output;
  
}

//eof
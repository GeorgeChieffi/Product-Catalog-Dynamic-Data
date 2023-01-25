<?php
// cron.php - command line script script to run scheduled background tasks
// Ask your web admin to have this script to run every minute using a cronjob or scheduled task
// Example command line: /path/to/php /path/to/cron.php example.com

// For basic example plugin see: /plugins/cron-example.php

// SETTINGS FILE - The hostname must be specified in the command line so that the correct settings
// file can be loaded if multiple settings files are being used for different environments.
// If the hostname is not specified, the default settings file will be used.
// For more info see: data/how_to_use_settings_files.txt
if (empty($_SERVER['HTTP_HOST']) && isset( $_SERVER['argv'][1])) { // If we can't auto-detect the hostname and it's been specified
  define('CRON_HOSTNAME', $_SERVER['argv'][1]);  // used by init.php:_init_loadSettings() to determine settings file
  $_SERVER['SERVER_NAME'] = $_SERVER['argv'][1]; // set server name for SwiftMailer Message-ID in cron tasks (https://forge.typo3.org/issues/24686)
}

// load viewer library
chdir(__DIR__); // change dir to the directory the script is in (so relative paths below work).
require_once('lib/init.php');

// send headers (for running through web)
if (!inCLI()) {
  header("Content-type: text/plain");
  header("Content-Disposition: inline; filename='output.txt'");                                              // Force IE to display as text and not download file
  $CMS_USER = getCurrentUserFromCMS();                                                                       // security check for web access - don't show cron filepaths unless logged in
  if (!@$CMS_USER['isAdmin']) { die(t("You must be logged in as Admin to run this script from the web!")); } // security check for web access - don't show cron filepaths unless logged in
  ob_disable();              // Turn off browser buffering
  ignore_user_abort( true ); // continue running even if user clicks stop on their browser
  session_write_close();     // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress
}

// Check if the cron system is disabled.
if ($SETTINGS['bgtasks_disabled']) { die("cron.php is currently disabled. You can enable it again via the Background Tasks menu."); }

// ignore PHP's max_execution_time directive
set_time_limit(0);

// Show cronjob instructions and web warning
if (!inCLI()) {
  $thisScriptPath = __FILE__;
  $thisScriptUrl  = preg_replace("/\?.*/", '', thisPageUrl()) . '?run=1';

  print "CRON WEB INTERFACE WARNING!\n";
  print "-------------------------------------------------------------------------------\n";
  print "You are currently running cron.php through the web interface.  This can be useful\n";
  print "for testing but please be aware that many web servers will terminate tasks that run\n";
  print "for more than a few seconds causing some of your scheduled tasks to not complete.\n";
  print "\n";
  print "For best results ask you website administrator to setup a cronjob or 'scheduled task'\n";
  print "to run this command every 1 minute (or as frequently as they otherwise allow):\n";
  print "php -q $thisScriptPath\n\n";

  print "CHECKING TASKS\n";
  print "-------------------------------------------------------------------------------\n";
  print "Please don't close this browser until the page is finished loading.\n";
  print "Task output and updates can be found under the Admin Menu.\n\n";
}

// error checking
if (!isInstalled()) { die("You must install the software before the cron script can dispatch tasks!"); }

// Dispatch cron jobs (aka: background tasks, scheduled tasks)
cron_dispatcher();

//
if (!inCLI()) {
  print "\n-------------------------------------------------------------------------------\n";
  print "Done, total time " .showExecuteSeconds(true)." seconds.\n";
}

exit;

// cron_dispatcher() - run all cron jobs that:
// - were scheduled to run now or since since cron.php last executed
// - haven't run since cron.php last executed
// Note: This means even if cron.php is delayed, it will always run your cronjob, but not more than once per each execution of cron.php.
/*

// Add CronJob Example
// minute(0-59), hour(0-23), dayOfMonth(1-31), month(1-12), dayOfWeek(0-7, 0=Sunday)
// Supports: *(any/all), 6(numbers), 1-3(ranges), 15,30,45(number lists)
// ****NOTE: Cronjob functions should return any content they want added to the log as the "Summary" and print/echo any content
// ... they wanted displayed as "output" textbox
addCronJob('my_function1', '5 * * * *');  // Run every 5 minutes
addCronJob('my_function2', '0 1 * * 0');  // Run at 1am every Sunday Night

Reference:
http://en.wikipedia.org/wiki/Cron#CRON_expression
http://en.wikipedia.org/wiki/Cron#Format
https://help.ubuntu.com/community/CronHowto
http://wiki.dreamhost.com/Crontab
*/
function cron_dispatcher() { // runs due or overdue jobs

  // get last cron.php run time
  $cronLastRunTime    = $GLOBALS['SETTINGS']['bgtasks_lastRun'];
  $thisCronRunTime    = time();

  // call log function if cron jobs exit or die
  register_shutdown_function('cron_logErrorsOnDieOrExit');

  // run cron tasks
  $dispatchedTaskCounter = 0;
  foreach (getCronList() as $cron) {
    //print "DEBUG: Checking... " .$cron['function']. " => " .$cron['expression']. "\n";

    // get last job run time and oldest time to check
    $jobLastLogRecord  = mysql_get('_cron_log', null, ' `function` = "' .mysql_escape($cron['function']). '" ORDER BY num DESC');
    $jobLastRunTime    = strtotime($jobLastLogRecord['createdDate']??false);
    $oldestTimeToCheck = max($cronLastRunTime, $jobLastRunTime);

    // get most recent valid run time (from now to the last time cron.php ran)
    $lastScheduleRunTime = cronExpression_getLastScheduledTime($cron['expression'], $oldestTimeToCheck, $cronExprParseErrors);
    $skipTask = false;
    if (!$lastScheduleRunTime && !$cronExprParseErrors) { $skipTask = true; } // skip if no scheduled runtime found since last cronrun (and no errors which might have caused that)
    if ($lastScheduleRunTime && $lastScheduleRunTime <= $cronLastRunTime) { $skipTask = true; } // skip if scheduled to run, but not quite yet (if scheduled time is blank then there was an error)
    if (($thisCronRunTime - 60) < $jobLastRunTime) { $skipTask = true; } // don't run jobs more than once a minute
    if ($skipTask) {
      if (!inCLI()) { print "Skipping {$cron['activity']}, function: {$cron['function']} (not scheduled to run again yet)\n"; }
      continue;
    }

    // Add log entry for job
    $hasLock = mysql_get_lock($cron['function']); // get a lock for this specific function
    if     ($cronExprParseErrors) { $summary = $cronExprParseErrors; }
    elseif (!$hasLock)            { $summary = t('Aborting, task still running from last time.'); }
    else                          { $summary = t('Running...'); }
    $jobLogNum = mysql_insert('_cron_log', array(
      'createdDate=' => 'NOW()',
      'function'     => $cron['function'],
      'activity'     => $cron['activity'],
      'summary'      => $summary,
      'completed'    => 0,
    ));

    // skip if errors parsing cronExpression or getting lock
    if ($cronExprParseErrors || !$hasLock) { continue; }

    // execute function
    $dispatchedTaskCounter++;
    if (!inCLI()) { print "Running {$cron['activity']}, function: {$cron['function']}\n"; }
    ob_start();
    $startTime = microtime(true);
    $GLOBALS['CRON_JOB_START']   = $startTime; // store job num in a global so we can update it after die/exit with cron_logErrorsOnDieOrExit
    $GLOBALS['CRON_JOB_LOG_NUM'] = $jobLogNum; // store job num in a global so we can update it after die/exit with cron_logErrorsOnDieOrExit
    $summary   = call_user_func($cron['function'], array('note' => 'this $info array is for future use'));
    $GLOBALS['CRON_JOB_LOG_NUM'] = '';
    $endTime   = microtime(true);
    $output    = ob_get_clean();

    // update job log entry
    mysql_update('_cron_log', $jobLogNum, null, array(
      'completed' => 1,
      'summary'   => $summary,
      'output'    => $output,
      'runtime'   => sprintf("%0.2f", $endTime - $startTime),
    ));
    mysql_release_lock($cron['function']);
  }
  
  // truncate cron log to configured max
  $cronLogMax = $GLOBALS['SETTINGS']['cronLogLimit'];
  $cronLogCount = mysql_count('_cron_log');
  
  if ($cronLogMax > 0 && $cronLogCount > $cronLogMax) {
    
    $cronLogToDelete = $cronLogCount - $cronLogMax;
    mysql_do('DELETE FROM ' . getTableNameWithPrefix('_cron_log') . ' ORDER BY createdDate LIMIT ' . $cronLogToDelete);
    
  }
  
  // update lastrun time
  $GLOBALS['SETTINGS']['bgtasks_lastRun'] = time();
  saveSettings();
}

//
function cron_logErrorsOnDieOrExit() {
  if (!@$GLOBALS['CRON_JOB_LOG_NUM']) { return; }
  $summary = t("Returned errors");
  $output  = ob_get_clean();
  $runtime = sprintf("%0.2f", microtime(true) - $GLOBALS['CRON_JOB_START']);

  // update job log entry
  mysql_update('_cron_log', $GLOBALS['CRON_JOB_LOG_NUM'], null, array(
    'summary'   => $summary,
    'output'    => $output,
    'runtime'   => $runtime,
  ));

  // send email
  $secondsAgo = time() - $GLOBALS['SETTINGS']['bgtasks_lastEmail'];
  if ($secondsAgo >= (60*60)) { // don't email more than once an hour

    // get email placeholders
    $cronLog = mysql_get('_cron_log', $GLOBALS['CRON_JOB_LOG_NUM']);
    $placeholders = array(
      'bgtask.date'         => $cronLog['createdDate'],
      'bgtask.activity'     => $cronLog['activity'],
      'bgtask.summary'      => nl2br(htmlencode($cronLog['summary'])),
      'bgtask.completed'    => $cronLog['completed'],
      'bgtask.function'     => $cronLog['function'],
      'bgtask.output'       => nl2br(htmlencode($cronLog['output'])),
      'bgtask.runtime'      => $cronLog['runtime'],
      'bgtask.function'     => $cronLog['function'],

      'bgtasks.logsUrl'     => realUrl("?menu=_cron_log", $GLOBALS['SETTINGS']['adminUrl']),
      'bgtasks.settingsUrl' => realUrl("?menu=admin&action=bgtasks", $GLOBALS['SETTINGS']['adminUrl']),
    );

    // send message
    $errors  = sendMessage(emailTemplate_loadFromDB(array(
      'template_id'  => 'CMS-BGTASK-ERROR',
      'placeholders' => $placeholders,
    )));
    if ($errors) { die("Mail Error: $errors"); }

    // update last emailed time
    $GLOBALS['SETTINGS']['bgtasks_lastEmail'] = time();
    saveSettings();
  }

}

// Return latest unixtime that cronExpression is scheduled to run (between now and $oldestTimeToCheck)
// On errors returns 0 and sets $parseErrors
// Only supports cron expressions as: *, number, or */number or number,number,number
function cronExpression_getLastScheduledTime($cronExpression, $oldestTimeToCheck = 0, &$parseErrors = '') {
  $parseErrors = '';

  // fail-safe: don't check for valid dates older than 91 minutes ago.
  // ... we ask hosts to run cron.php every 1 minute, but we'll assume a worst case of every 90 minutes
  $ninetyOneMinutesAgo = time() - (60*91);
  if ($oldestTimeToCheck < $ninetyOneMinutesAgo) { $oldestTimeToCheck = $ninetyOneMinutesAgo; }

  // parse cron expression and get valid values for date intervals
  @list($minute, $hour, $dayOfMonth, $month, $dayOfWeek) = explode(' ',  $cronExpression);
  $validMinute     = cronExpression_getValidValuesForField('minute',     $minute);
  $validHour       = cronExpression_getValidValuesForField('hour',       $hour);
  $validDayOfMonth = cronExpression_getValidValuesForField('dayOfMonth', $dayOfMonth);
  $validMonth      = cronExpression_getValidValuesForField('month',      $month);
  $validDayOfWeek  = cronExpression_getValidValuesForField('dayOfWeek',  $dayOfWeek);
  if (!$validMinute || !$validHour || !$validDayOfMonth || !$validMonth || !$validDayOfWeek) {
    $parseErrors .= sprintf(t("Invalid cron expression '%s'"), $cronExpression) ."\n";
    return;
  }

  // get last scheduled run time (between now and $oldestTimeToCheck)
  $lastScheduledTime = 0;
  $checkTime = time();
  while ($checkTime >= $oldestTimeToCheck) {
    $checkTimeString = date('i-H-j-n-w', $checkTime);
    list($cMinute, $cHour, $cDayOfMonth, $cMonth, $cDayOfWeek) = explode('-', $checkTimeString);
    #print "Debug: ". mysql_datetime($checkTime)." $checkTimeString - $cMinute, $cHour, $cDayOfMonth, $cMonth, $cDayOfWeek\n";

    // check for matching intervals from largest to smallest
    // Note: We could optimize this by jumping back in larger increments, so middle of Feb jumps back 14 days to end of Jan but in typical
    // ... usage of a cron job running every 1-5 minute we're only going to have 5 loop iterations so we'll just go back a minute at a time.
    if (!in_array($cMonth,      $validMonth))      { $checkTime -= 60; continue; } // jump back one minute
    if (!in_array($cDayOfMonth, $validDayOfMonth)) { $checkTime -= 60; continue; }
    if (!in_array($cDayOfWeek,  $validDayOfWeek))  { $checkTime -= 60; continue; }
    if (!in_array($cHour,       $validHour))       { $checkTime -= 60; continue; }
    if (!in_array($cMinute,     $validMinute))     { $checkTime -= 60; continue; }

    // if all date intervals were valid, return this date as last scheduled time
    $lastScheduledTime = $checkTime;
    break;
  }

  //
  return $lastScheduledTime;
}


// return an array of valid values for a cron expression field.
// Support expressions: '*', '4', '5,6,7', '10-20'
// On errors returns empty array;
// Examples: cronExpression_getValidValuesForField('dayOfWeek', '*');    // returns [0,1,2,3,4,5,6]
// Examples: cronExpression_getValidValuesForField('hour', '1');         // returns [1]
// Examples: cronExpression_getValidValuesForField('minute', '0,15,45'); // returns [0,15,45]
// Examples: cronExpression_getValidValuesForField('dayOfWeek', '1-5');  // returns [1,2,3,4,5] // Mon-Fri
function cronExpression_getValidValuesForField($type, $expr) {

  ### Get min/max values for each time increment
  if     ($type == 'minute')     { list($min, $max) = array(0, 59); }
  elseif ($type == 'hour')       { list($min, $max) = array(0, 23); }
  elseif ($type == 'dayOfMonth') { list($min, $max) = array(1, 31); }
  elseif ($type == 'month')      { list($min, $max) = array(1, 12); }
  elseif ($type == 'dayOfWeek')  { list($min, $max) = array(0, 6); }  // 0 = Sunday
  else { dieAsCaller("Unknown time increment '$type'"); }

  ### Get valid values for time increment expression
  if ($expr == '*')                       { return range($min, $max); }    // * allows any value
  if (preg_match("/^\d+$/", $expr))       { return array(intval($expr)); } // # a specific number
  if (preg_match("/^(\d+\,?)+$/", $expr)) { return explode(',',$expr); }   // #,#,# a list of value numbers
  if (preg_match("/^(\d+)-(\d+)$/", $expr, $matches)) {                    // #-# a range of numbers
    return range($matches[1], $matches[2]);
  }

  // on error return empty array
  return array();
}

?>
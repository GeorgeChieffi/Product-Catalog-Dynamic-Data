<?php
/*
Plugin Name: Cron Example
Description: Example of how to run a background task at a set interval such as once a day or once an minute.
Version: 1.00
Requires at least: 3.07
Plugin URI: ?menu=admin&action=bgtasks
*/

// INSTRUCTIONS: To create a background task plugin do the following
// - Copy this example file and save it as a new filename
// - Update the plugin header with a new name and description to reflect what your plugin does
// - Update addCronJob() with a short description of the cron activity, and set the cron expression to run as frequently as you want
// - Rename all instances of 'cron_example' to a new function name
// - Add any code as needed to your function and then test by clicking "Run Manually" from the plugins menu.
// - Check the Background Tasks menu and make sure your plugin is running on schedule: admin.php?menu=admin&action=bgtasks


// Setup scheduled background tasks
// Cron Expression: minute(0-59), hour(0-23), dayOfMonth(1-31), month(1-12), dayOfWeek(0-7, 0=Sunday) Supports: *(any/all), 6(numbers), 1-3(ranges), 15,30,45(number lists)
// Note: Called functions should return any content they want added to the log as the "Summary" and print/echo any content they wanted displayed as "output" textbox
//addCronJob('yourFunctionName', "Activity Name to Log", '* * * * *');      // Run every minute
//addCronJob('yourFunctionName', "Activity Name to Log", '0 * * * *');      // Run every hour
//addCronJob('yourFunctionName', "Activity Name to Log", '0 3 * * *');      // Run at 3am every day
//addCronJob('yourFunctionName', "Activity Name to Log", '0 10,13 * * *');  // Run at 10am, 1pm
addCronJob('cron_example', "Cron Example Script", '0 * * * *');             // Run every hour

// Plugin Menu - Add link to allow users to "Run Manually", this can be useful for testing
pluginAction_addHandlerAndLink(t('Run Manually'), 'cron_example', 'admins');  // Arguments: $linkName, $functionName, $requiredAccess ('admins', 'users', 'all')


// this function is called by cron, and logged under: Admin > General > Background Tasks
// return()'d content is logged as "Summary" and and print/echo content is logged as "output"
function cron_example() {
  if (!inCLI()) {  // send headers (for running through web with "Run Manually" link above)
    if (!headers_sent()) { 
      header("Content-type: text/plain");
      header("Content-Disposition: inline; filename='output.txt'"); // Force IE to display as text and not download file
      ob_disable();              // Turn off browser buffering
    }
    // This lines are only needed if your cron script is going to be called directly and you need to enforce security, if cron is added as a CMS "Background Task" then you can specify access rights in: pluginAction_addHandlerAndLink
    // $CMS_USER = getCurrentUserFromCMS();                                                                       // security check for web access - don't show cron filepaths unless logged in
    // if (!@$CMS_USER['isAdmin']) { die(t("You must be logged in as Admin to run this script from the web!")); } // security check for web access - don't show cron filepaths unless logged in
    ignore_user_abort( true ); // continue running even if user clicks stop on their browser
    session_write_close();     // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress
  }
  set_time_limit(0);  // ignore PHP's max_execution_time directive
  
  //
  print "My sample Cron \n";
  print "--------------------------------------------------------------------------------\n";
  // Add your own code here...
  print "The current time is: " .date('Y-m-d H:i:s'). "\n\n";
  print "Done!\n";


  // return summary message
  $summary = "Successful";
  if (@$_REQUEST['_pluginAction'] == __FUNCTION__) { exit; }  // exit if being run manually from web
  return $summary;  // otherwise, return summary if being run by cron
}

// eof
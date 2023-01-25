<?php
/*
Plugin Name: Display Demo Notice
Plugin URI:
Description: When in demo mode display "Demo Mode" notice and countdown timer in the footer of all the admin pages
Author:
Version: 1.0
Author URI:
*/

addFilter('execute_seconds', 'plugin_footerDemoModeNotice');

//
function plugin_footerDemoModeNotice($html) {
  global $TABLE_PREFIX;

  // only show in demo mode
  if (!inDemoMode()) { return $html; }

  // get minutes remaining
  $secondsRemaining = ($_SESSION['demoCreatedTimeAsFloat'] + MAX_DEMO_TIME) - time();
  $minutesRemaining = intval($secondsRemaining / 60);

  //
  $html .= "<br><br><div style='font-size: 12px; color: #C00'><b>Demo Mode: Some features are disabled. Demo will reset in $minutesRemaining minutes.</b></div>";
  return $html;
}

?>

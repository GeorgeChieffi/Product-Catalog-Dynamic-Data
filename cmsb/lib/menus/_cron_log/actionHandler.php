<?php
// define globals
global $APP; //, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX;
$APP['selectedMenu'] = 'admin'; // show admin menu as selected

// check access level - admin only!
if (!$GLOBALS['CURRENT_USER']['isAdmin']) {
  alert(t("You don't have permissions to access this menu."));
  showInterface('');
}

// mailer plugin hooks
addAction('section_preDispatch',     '_cronlog_showModeNotice',  null, 2);
addAction('section_preDispatch',     '_cronlog_clearLog',  null, 2);

// Prefix Menu with "Admin >"
addFilter('adminUI_args', function($adminUI) {
  array_unshift($adminUI['PAGE_TITLE'], t('Admin'));
  return $adminUI;
});

// Let regular actionHandler run
$REDIRECT_FOR_CUSTOM_MENUS_DONT_EXIT = true;
return;



//
function _cronlog_showModeNotice($tableName, $action) {
  if ($action != 'list') { return; }

  $notice = sprintf(t("Background Tasks: This menu lists all log entries (<a href='%s'>Task Settings</a>, <a href='%s'>Clear Log</a>)"), "?menu=admin&action=bgtasks", "?menu=$tableName&action=clearLog");
  notice($notice);
}

function _cronlog_clearLog($tableName, $action){
  if($action !== 'clearLog'){ return; }

  mysql_delete($tableName, null, 'true');
  redirectBrowserToURL("?menu=" . $tableName);
}
?>

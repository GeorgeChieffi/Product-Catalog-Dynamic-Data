<?php
// PHP Audit Log Menu

// define globals
$GLOBALS['APP']['selectedMenu'] = 'admin'; // show admin menu as selected

// check access level - admin only!
if (!$GLOBALS['CURRENT_USER']['isAdmin']) {
  alert(t("You don't have permissions to access this menu."));
  showInterface('');
}

// check if Audit Log is enabled
if (!$GLOBALS['SETTINGS']['advanced']['auditLog_enabled']) {
  alert(t("The Audit Log is not enabled."));
  showInterface('');
}

// prevent delete/edit actions (even if allowed by schema; prevents tampering with the log)
if (isset( $_REQUEST['action'] )) {
  switch($_REQUEST['action']) {
    case 'edit':
      // error message
      alert(t('Modifying records has been disabled for this section!'));
      
      // change to list
      showInterface('default/list.php');
      break;
      
    case 'eraseRecords':
      alert(t("Erasing records has been disabled for this section!"));
      
      // change to list
      showInterface('default/list.php');
      break;
  }
}

// Prefix Menu with "Admin >"
addFilter('adminUI_args', function($adminUI) {
  array_unshift($adminUI['PAGE_TITLE'], t('Security'));
  array_unshift($adminUI['PAGE_TITLE'], t('Admin'));
  return $adminUI;
});


// menu plugin hooks
addAction('section_preDispatch',     '_auditlog_showModeNotice',  null, 2);

// Dispatch Actions
if ($GLOBALS['action'] == 'clearLog') { // clear error log
  mysql_delete($GLOBALS['schema']['_tableName'], null, 'true');
  
  // log the clear action
  auditLog_addEntry('Audit Log Cleared');
  
  redirectBrowserToURL("?menu=" . $GLOBALS['schema']['_tableName']);
}

// Let regular actionHandler run
$REDIRECT_FOR_CUSTOM_MENUS_DONT_EXIT = true;
return;

//
function _auditlog_showModeNotice($tableName, $action) {
  if ($action != 'list') { return; }

  $notice = t("Audit Log"). " (<a href='?menu=$tableName&action=clearLog'>" .t("Clear Log"). "</a>)";
  notice($notice);
}
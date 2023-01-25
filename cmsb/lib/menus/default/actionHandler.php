<?php

  // load libraries
  require_once "lib/menus/default/common.php";
  require_once file_exists('lib/wysiwyg_custom.php') ? 'lib/wysiwyg_custom.php' : 'lib/wysiwyg.php';

  // set globals
  global $TABLE_PREFIX, $tableName, $escapedTableName, $action, $schema, $CURRENT_USER, $hasEditorAccess, $hasAuthorAccess, $hasViewerAccess, $hasViewerAccessOnly, $hasAuthorViewerAccess, $isMyAccountMenu, $isSingleMenu;
  $isMyAccountMenu       = ($menu == '_myaccount');
  $tableName             = $isMyAccountMenu ? 'accounts' : $menu;
  $schema                = loadSchema($tableName);
  $escapedTableName      = mysql_escape( $TABLE_PREFIX . $tableName );
  $hasEditorAccess       = (userSectionAccess($tableName) >= 9);
  $hasAuthorAccess       = (userSectionAccess($tableName) >= 6);
  $hasViewerAccess       = (userSectionAccess($tableName) >= 3);
  $hasViewerAccessOnly   = (userSectionAccess($tableName) == 3);
  $hasAuthorViewerAccess = (userSectionAccess($tableName) >= 7);
  $isSingleMenu          = (@$schema['menuType'] == 'single');
  
  // get action
  if     ($isSingleMenu && $hasAuthorAccess) { $_defaultAction = 'edit'; }
  elseif ($isSingleMenu && $hasViewerAccess) { $_defaultAction = 'view'; }
  else                                       { $_defaultAction = 'list'; }
  $action = getRequestedAction($_defaultAction);

  //
  doAction('section_init', $tableName, $action);

  //
  _redirectForCustomMenus();      // If file exists, call: /lib/menus/$menu/actionHandler.php
  _myAccountMenuOverrides();      // override menuName, recordNum, selectedRecords and action

  // error checking
  _displayRequiredPluginErrors();
  displaySectionAccessErrors($action);
  _displayRecordAccessErrors($action);

  // display alerts
  $message = '';
  if (@$_REQUEST['saved']) {
    $recordNum = (int) @$_REQUEST['saved'];
    $message   = t("Record saved.");
    $message   = applyFilters('record_saved_message', $message, $tableName, $recordNum);
    notice($message);
  }

  // show iframe menus
  _showIframeSections();

  //
  doAction('section_preDispatch', $tableName, $action);

  ### Dispatch actions
  if     ($action == 'list')                       { include('lib/menus/default/list.php'); }
  elseif ($action == 'listDragSort')               { listDragSort(); }
  elseif ($action == 'add')                        { showMaxRecordsError(); showInterface('default/edit.php'); }
  elseif ($action == 'edit')                       { showInterface('default/edit.php'); }
  elseif ($action == 'view')                       { showInterface('default/view.php'); }
  elseif ($action == 'eraseRecords')               {
    eraseRecords();
    if (@$_REQUEST['returnUrl']) { redirectBrowserToURL( $_REQUEST['returnUrl'], true ); }
    include('lib/menus/default/list.php');
  }
  elseif ($action == 'save')                       { include('lib/menus/default/save.php');  }
  elseif ($action == 'mediaList')                  { include('lib/menus/default/mediaList.php');  }
  elseif ($action == 'mediaAdd')                   { media_addMediaAjax();  }
  elseif ($action == 'uploadList')                 { include('lib/menus/default/uploadList.php');  }
  elseif ($action == 'uploadListReOrder')          { uploadListReOrder();  }
  elseif ($action == 'uploadForm')                 { include('lib/menus/default/uploadForm.php');  }
  elseif ($action == 'uploadModify')               { include('lib/menus/default/uploadModify.php');  }
  elseif ($action == 'uploadErase')                { eraseUpload(); }
  elseif ($action == 'wysiwygUploads')             { include('lib/menus/default/wysiwygUploads.php');  }
  elseif ($action == 'wysiwygMedia')               { include('lib/menus/default/wysiwygMedia.php');  }
  elseif ($action == 'ajaxGetUsersAsPulldown')     { ajaxGetUsersAsPulldown(); }
  elseif ($action == 'ajaxUpdateListFieldOptions') { ajaxUpdateListFieldOptions(); }
  elseif ($action == 'categoryMove')               { categoryMove();  }

  elseif ($action == 'editSection')                { redirectBrowserToURL( '?menu=database&action=editTable&tableName=' . urlencode($tableName), true); }
  elseif ($action == 'codeGenerator')              { redirectBrowserToURL( '?menu=_codeGenerator&tableName=' . urlencode($tableName), true); }
  else {
    doAction('section_unknownAction', $tableName, $action);

    alert("Unknown action '" . htmlencode($action) . "'");
    showInterface('');
    exit;
  }

//
function ajaxUpdateListFieldOptions() {
  global $schema;

  $fieldname   = @$_REQUEST['fieldname'];
  $fieldSchema = @$schema[$fieldname];

  // error checking
  if (!$fieldname)                             { die("No fieldname specified!\n"); }
  if (!$fieldSchema)                           { die("Couldn't find field in schema!\n"); }
  if (@$fieldSchema['type'] != 'list')         { die("Field type isn't 'list'!\n"); }
  if (@$fieldSchema['listType'] != 'pulldownMulti' &&
      @$fieldSchema['listType'] != 'pulldown') { die("List type must be 'pulldown' or 'pulldownMulti'!\n"); }
  if (@$fieldSchema['optionsType'] != 'query') { die("Options type isn't 'query'!\n"); }
  if (!@$fieldSchema['filterField'])           { die("No 'filterField' value specified for field!\n"); }

  // get list options
  $record      = array(
    $fieldSchema['filterField'] => $_REQUEST['newFilterValue'],
  );
  $listOptions = getListOptionsFromSchema($fieldSchema, $record);

  // get options html
  $optionsHTML = "<option value=''>&lt;select&gt;</option>\n";
  foreach ($listOptions as $optionArray) {
    list($value,$label) = $optionArray;
    $encodedValue = htmlencode($value);
    $selectedAttr = ($value == @$_REQUEST['selectedValue'] || (is_array(@$_REQUEST['selectedValue']) && in_array($value, $_REQUEST['selectedValue']))) ? 'selected="selected"' : '';
    $encodedLabel = htmlencode($label);
    $optionsHTML .= "<option value=\"$encodedValue\" $selectedAttr>$encodedLabel</option>\n";
  }

  // print options HTML
  print $optionsHTML;
  exit;
}


//
function _myAccountMenuOverrides() {
  global $schema, $CURRENT_USER, $action, $isMyAccountMenu;

  if (!$isMyAccountMenu) { return; }

  $schema['menuName'] = t('My Account');      // display different menu name
  $_REQUEST['num']    = $CURRENT_USER['num'];  // only allow operations on their own account
  unset($_REQUEST['selectedRecords']);         // don't allow selecting records with checkboxes
  if ($action == 'list') { $action = 'edit'; } // don't allow list page access

}

//
function _redirectForCustomMenus() {
  global $tableName;

  $libDir     = dirname(dirname(__DIR__));
  $customMenu = file_exists("$libDir/menus/$tableName/actionHandler.php");
  if ($customMenu && $tableName != 'accounts') {
    include "lib/menus/$tableName/actionHandler.php";

    // allow custom actionHandler.php to use existing code
    if (isset($REDIRECT_FOR_CUSTOM_MENUS_DONT_EXIT)) { return; } // v2.16

    // or just exit
    exit;
  }
}


//
function _displayRequiredPluginErrors() {
  global $schema;

  // display missing plugin errors
  $missingPlugins  = '';
  $schema['_requiredPlugins'] = $schema['_requiredPlugins'] ?? ''; // cannot preg_split() on null as of PHP 8.1
  $requiredPlugins = preg_split("/\s*,\s*/", $schema['_requiredPlugins']);
  foreach ($requiredPlugins as $filename) {
    if (!isPluginActive($filename)) { $missingPlugins .= "$filename, "; }
  }
  $missingPlugins = trim($missingPlugins, ', ');

  if ($missingPlugins) {
    $error = "Please activate the following plugins before using this section: $missingPlugins";
    showInterfaceError($error);
  }

}

//
function displaySectionAccessErrors($action) {
  global $schema, $CURRENT_USER, $hasEditorAccess, $hasAuthorAccess, $hasViewerAccess, $hasViewerAccessOnly, $tableName, $isMyAccountMenu;
  $error = '';

  // no schema
  $noSchema = !@$schema;
  if ($noSchema) {
    $error = sprintf(t("Unknown menu '%s'"), htmlencode($tableName));
    showInterfaceError($error);
  }

  // user has no access
  $adminOnlyTables = array('_email_templates','_cron_log');
  $noAccess        = !$CURRENT_USER['isAdmin'] && !$hasEditorAccess && !$hasAuthorAccess && !$isMyAccountMenu && !$hasViewerAccess;
  if (!$CURRENT_USER['isAdmin'] && in_array($tableName, $adminOnlyTables)) { $noAccess = true; } // v2.50
  $validActions = array('list','view','uploadList');
  $validActions = applyFilters('viewerOnly_allowed_actions', $validActions);
  if ($hasViewerAccessOnly && !in_array($action, $validActions)) { $noAccess = 1; }
  if ($noAccess) {
    $error = t("You don't have permissions to access this menu.");
    showInterfaceError($error);
  }

  // section isn't configured properly for authors
  $notConfiguredForAuthors = ($hasAuthorAccess && !$hasEditorAccess && !@$schema['createdByUserNum']);
  if ($notConfiguredForAuthors) {
    $error = t("This section isn't configured to allow 'Author' user access.  Ask the webmaster to give you 'Admin' access to this section or create a field called 'createdByUserNum'.");
    showInterfaceError($error);
  }

}


//
function _displayRecordAccessErrors($action) {
  global $CURRENT_USER, $hasEditorAccess, $hasAuthorAccess, $hasAuthorViewerAccess, $schema, $tableName, $escapedTableName, $isSingleMenu;

  //
  $isAuthor        = !$CURRENT_USER['isAdmin'] && !$hasEditorAccess && $hasAuthorAccess;
  $recordNums      = array_unique(array_merge( (array) @$_REQUEST['selectedRecords'], (array) @$_REQUEST['num'] ));
  $recordNumsAsCSV = join(',', array_map('intval', $recordNums)); // escape nums by converting them to integers
  $invalidNums     = [];

  // don't allow authors to edit records they don't own
  $allowAuthorViewerAccess = ($hasAuthorViewerAccess && in_array($action, array('view','uploadList')) );
  if ($isAuthor && $recordNums && !$isSingleMenu && !$allowAuthorViewerAccess) {
    $accessWhere = "`createdByUserNum` = '{$CURRENT_USER['num']}'";
    $accessWhere = applyFilters('record_access_where', $accessWhere, $tableName); // this is also called in list_functions_init()
    $query       = "SELECT num FROM `$escapedTableName` WHERE num IN ($recordNumsAsCSV) AND !($accessWhere)";

    $records = mysql_select_query($query, true); // these are records not owned by the user (who has author access)
    foreach ($records as $record) { $invalidNums[] = $record[0]; }
  }

  // User Accounts: don't allow non-admin's to edit 'isAdmin' accounts
  if ($tableName == 'accounts' && !$CURRENT_USER['isAdmin'] && $recordNums) {
    $query            = "SELECT num FROM `$escapedTableName` WHERE num IN ($recordNumsAsCSV) AND isAdmin = '1'";
    $records          = mysql_select_query($query, true); // these are records not owned by the user (who has author access)
    foreach ($records as $record) { $invalidNums[] = $record[0]; }
  }

  // show errors
  if ($invalidNums) {
    $invalidNumsAsCSV = join(',', $invalidNums);
    $error            = sprintf(t("You don't have permission to access these records: %s"), $invalidNumsAsCSV);
    showInterfaceError($error);
  }

}

//
function _showIframeSections() {
  global $schema;
  if (@$schema['menuType'] != 'link') { return; }

  // show messages
  alert( @$schema['_linkMessage'] );

  // get page html
  $iframeHeight = @$schema['_iframeHeight'] ?: 600;
  $iframeHTML   = "<iframe src='{$schema['_url']}' style='height: {$iframeHeight}px; width: 100%;' id='iframe-container'></iframe>";

  // show page
  adminUI([
    'PAGE_TITLE'       => [ @$schema['menuName'] ],
    'CONTENT'          => $iframeHTML,
  ]);
  exit;

}

?>

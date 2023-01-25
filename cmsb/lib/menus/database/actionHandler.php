<?php

  # set globals
  global $APP, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX;
  $APP['selectedMenu'] = 'admin';

  ### check access level
  if (!$GLOBALS['CURRENT_USER']['isAdmin']) {
    alert(t("You don't have permissions to access this menu."));
    showInterface('');
  }

  ### Dispatch actions
  $action        = getRequestedAction();
  if     (!$action || $action == 'listTables')    {
    if (@$_REQUEST['newOrder'])     { updateMenuOrder();  }
    if (@$action == '' && !alert()) {  // skip if action specified or alerts, such as when user is redirected back to this page

      ### If no actions and we're just on list page, perform these utility tasks

      // Create missing schema tables and fields
      createMissingSchemaTablesAndFields();

      // update all tables to utf8mb4
      _mysql_upgradeTablesToUTF8mb4();
      
      // update all tables to InnoDB
      _mysql_convertTablesToInnoDB();

      // show alerts about unknown files in /schema/ folder
      foreach (scandir(DATA_DIR.'/schema/') as $unknownFile) {
        if (in_array($unknownFile, ['.','..','.htaccess','vssver.scc'])) { continue; } // skip known files
        if (preg_match("/\.(ini|defaultSqlData)\.php(\.default)?$/", $unknownFile)) { continue; }    // skip known files
        notice(t("Unknown file in /data/schema/ folder: ")." $unknownFile<br>\n");
      }

    }
    showInterface('database/listTables.php');
  }
  //elseif ($action == 'addTable')             { include "lib/menus/database/addTable.php"; }
  elseif ($action == 'addTable_save')        { addTable(); }
  elseif ($action == 'editTable')            { include "lib/menus/database/editTable.php"; }
  elseif (startsWith('editTable_', $action)) { include "lib/menus/database/editTable.php"; } // editTable subdisptach
  elseif ($action == 'editField')            { include "lib/menus/database/editField.php"; }
  elseif ($action == 'adminHome')            { showInterface('home.php'); }
  elseif ($action == 'recreateThumbnails')   { recreateThumbnails(); }
  elseif ($action == 'previewDefaultDate')   { previewDefaultDate(); }
  else {
    alert("Unknown action '" . htmlencode($action) . "'");
    showInterface('home.php');
  }

//
function updateMenuOrder() {

  //
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();

  //
  disableInDemoMode('', 'database/listTables.php');

  // update table/menu order
  $orderedTables = explode(',', $_REQUEST['newOrder']);
  $newOrder = 0;
  foreach ($orderedTables as $tablenameWithPrefix) {

    // load schema
    $schema = loadSchema($tablenameWithPrefix);
    if (empty($schema)) { die("no can't find schema file for table '" . htmlencode($tablenameWithPrefix) . "'"); }

    // update schema
    $schema['menuOrder'] = sprintf("%010d", ++$newOrder); // v2.51 zero-pad menuOrder so that changing order doesn't change schema filesize (this is for the developers, some change detection tools go on filesize and we rarely need to know if the menu order has changed, just the schema data)

    // save schema
    saveSchema($tablenameWithPrefix, $schema);


  }
}


//
function addTable() {
  global $TABLE_PREFIX, $APP, $SETTINGS;
  $menuType        = @$_REQUEST['menuType'];
  $presetTableName = @$_REQUEST['presetName'];
  $advancedType    = @$_REQUEST['advancedType'];

  //
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  //
  disableInDemoMode('', 'ajax');

  // error checking
  $errors = '';
  if (!$menuType)              { $errors .= "No menu type selected!\n"; }
  if (!@$_REQUEST['menuName']) { $errors .= "No menu name specified!\n"; }
  $errors .= getTablenameErrors(@$_REQUEST['tableName']);

  $newSchema = null;

  if ($menuType == 'copy') {

    if ($errors) { die($errors); }

    $sourceSchemaName = @$_REQUEST['copy'];
    if (!in_array($sourceSchemaName, getSchemaTables())) { die("Couldn't load source schema"); }
    $newSchema = loadSchema($sourceSchemaName) or die("Couldn't load source schema");
  }

  else {
    if ($menuType == 'preset') {
      $schemaPresets = getSchemaPresets();
      $presetFound   = array_key_exists(@$_REQUEST['preset'], $schemaPresets);
      if     (!@$_REQUEST['preset']) { $errors .= "You must select a preset from the pulldown!\n"; }
      elseif (!$presetFound)         { $errors .= "No schema preset file found for '" .htmlencode($presetTableName). "'\n"; }
    }
    if ($errors) { die($errors); }

    // create new schema data
    if     ($menuType == 'single')                                   { $presetTableName = "customSingle"; }
    elseif ($menuType == 'multi')                                    { $presetTableName = "customMulti"; }
    elseif ($menuType == 'preset')                                   { $presetTableName = @$_REQUEST['preset']; }
    elseif ($menuType == 'advanced' && $advancedType == 'category')  { $presetTableName = "customCategory"; }
    elseif ($menuType == 'advanced' && $advancedType == 'textlink')  { $presetTableName = "customTextLink"; }
    elseif ($menuType == 'advanced' && $advancedType == 'menugroup') { $presetTableName = "customMenuGroup"; }
    else { die("Unable to determine preset table name to load!"); }
    $schemaPresetDir        = DATA_DIR . "/schemaPresets/";
    $newSchema              = loadSchema($presetTableName, $schemaPresetDir) or die("Couldn't load preset schema");
  }

  $newSchema['menuName']  = @$_REQUEST['menuName']; // change menu name
  $newSchema['menuOrder'] = time(); // use time to sort to bottom


  // create mysql table
  // (this isn't required but done here so we catch get mysql errors creating the table)
  // createMissingSchemaTablesAndFields() creates if this doesn't.
  $tableNameWithPrefix = $TABLE_PREFIX . @$_REQUEST['tableName'];

  $result = mysqli()->query("CREATE TABLE `".mysql_escape($tableNameWithPrefix)."` (
                                          num int(10) unsigned NOT NULL auto_increment,
                                          PRIMARY KEY (num)
                                        ) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if (!$result) {
    print "Error creating MySQL table.\n\nMySQL error was: ". htmlencode(mysqli()->error) . "\n";
    exit;
  }


  // save new schema
  saveSchema(@$_REQUEST['tableName'], $newSchema, null, false);

  // Create schema table and fields in MySQL
  createMissingSchemaTablesAndFields();
  clearAlertsAndNotices(); // don't display alerts about adding new fields

  exit; // this is called with ajax so returning nothing means success - see: addTable_functions.js - initSubmitFormWithAjax
}

//
function getSchemaPresets() {
  global $APP;
  $schemaPresets = [];

  // get schema tablenames
  $schemaTables = [];
  $schemaPresetDir = DATA_DIR . '/schemaPresets/';
  foreach (getSchemaTables($schemaPresetDir) as $tableName) {
    $tableSchema = loadSchema($tableName, $schemaPresetDir);
    $menuName    = @$tableSchema['menuName'] ? $tableSchema['menuName'] : $tableName;

    $schemaPresets[$tableName] = @$tableSchema['menuName'];
  }

  return $schemaPresets;
}


//
function previewDefaultDate() {
  disableInDemoMode('', 'ajax');

  $defaultDate       = @$_REQUEST['defaultDate'];
  $defaultDateString = @$_REQUEST['defaultDateString'];
  $format            = "D, M j, Y - g:i:s A";

  // show date preview

  if     (!$defaultDate)                 { echo date($format); }
  elseif ($defaultDate == 'none')        { echo ''; }
  elseif ($defaultDate == 'custom')      {
    $output = @date($format, strtotime($defaultDateString));

    if     (!$defaultDateString) { echo ''; }
    elseif (@$php_errormsg)         { print @$php_errormsg; }
    else                         { print $output; }
  }
  else                                   { die("Can't create date preview!"); }



  exit; // this is called with ajax so returning nothing means success - see: addTable_functions.js - initSubmitFormWithAjax
}


?>

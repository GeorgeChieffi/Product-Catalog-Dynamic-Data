<?php
global $PROGRAM_DIR;

// Prevent multiple instances from upgrading at a time.
$isLocked = mysql_get_lock('cms_upgrade');
if (!$isLocked) {
  print t("Upgrade in progress.  Please wait while we reload page... ");
  print "<meta http-equiv='refresh' content='5;" .thisPageUrl(). "'>";
  exit;
}


//
showUpgradeErrors();
checkFilePermissions();

//
_upgradeToVersion1_04();
_upgradeToVersion1_06();
_upgradeToVersion1_07();
_upgradeToVersion1_08();
_upgradeToVersion1_10();
_upgradeSettings();
_upgradeAccounts();
_upgradeToVersion1_24();
_upgradeToVersion2_05();
_upgradeToVersion2_07();
_upgradeToVersion2_09();
_upgradeToVersion3_00();
_upgradeToVersion3_09();
_upgradeToVersion3_11();
_upgradeToVersion3_14();
_upgradeToVersion3_52();

//
_removeOldCacheFiles();
encryptAllPasswords(); // force encryption of all plaintext passwords
forceValidCssTheme('theme_blue.css'); // if non-existant CSS theme file is specified then default to theme_blue.css

//
createMissingSchemaTablesAndFields(); // create any fields that have been added to the schemas

//
_notifyUpgradeComplete();

//
function showUpgradeErrors() {
  $upgradeErrors = '';

  // check for accesslist schema
  $schemaPath = DATA_DIR.'/schema/_accesslist.ini.php';
  if (!file_exists($schemaPath)) {
    $upgradeErrors .= "<b>Upgrade Notice:</b> You must upload the latest /data/schema/_accesslist.ini.php before upgrading!<br>\n";
  }

  // check for settings schema
  #$schemaPath = DATA_DIR.'/schema/_settings.ini.php';
  #if (!file_exists($schemaPath)) {
  #  $upgradeErrors .= "<b>Upgrade Notice:</b> You must upload the latest /data/schema/_settings.ini.php before upgrading!<br>\n";
  #}

  // check for old plugin dir (changed in v1.34)
  $oldPluginDir = "{$GLOBALS['PROGRAM_DIR']}/lib/plugins";
  if (is_dir($oldPluginDir)) {
    $upgradeErrors .= "<b>Upgrade Notice</b>: Plugins directory has changed! If you have custom plugins, move them from /lib/plugins/ to /plugins/.  Then remove old /lib/plugins/ folder<br>\n";
  }

  // check for old wysiwyg path in custom wysiwyg file then check for compatibility issues
  $customWysiwygCode = @file_get_contents('lib/wysiwyg_custom.php');
  if (preg_match("/\/tiny_mce\//", $customWysiwygCode) || preg_match("/tinymce3/", $customWysiwygCode) || preg_match("/['\"]3rdParty/", $customWysiwygCode)) {
    // display instructions
    $upgradeErrors .= "<b>Upgrade Notice:</b> You need to manually update your /lib/wysiwyg_custom.php file to support the new version 4 wysiwyg. <br><br>Here's how to do that: <br>\n";
    $upgradeErrors .= "<ul>\n";
    $upgradeErrors .= "<li>Rename /lib/wysiwyg_custom.php to /lib/wysiwyg_custom_tinymce3_old.php</li>\n";
    $upgradeErrors .= "<li>Compare wysiwyg_custom_tinymce3_old.php to wysiwyg_tinymce3_old.php using a file compare utility such as WinMerge</li>\n";
    $upgradeErrors .= "<li>Create a copy of wysiwyg.php named wysiwyg_custom.php</li>\n";
    $upgradeErrors .= "<li>Copy any changes you made in wysiwyg_custom_tinymce3_old.php to wysiwyg_custom.php</li>\n";
    $upgradeErrors .= "</ul>\n";
    $upgradeErrors .= _getTinymce4UpgradeErrors($customWysiwygCode);
  }
  if (preg_match("/css\/wysiwyg.css/", $customWysiwygCode)) {
    $upgradeErrors .= "<b>Upgrade Notice</b>: WYSIWYG paths have changed.  Please replace 'css/wysiwyg.css' with 'lib/wysiwyg.css' in /lib/wysiwyg_custom.php<br>\n";
  }

  //
  if ($upgradeErrors) { die($upgradeErrors); }

}

//
function _upgradeToVersion3_52() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '3.52') { return; }

  // update all tables to InnoDB
  _mysql_convertTablesToInnoDB();

  //
  saveAndRefresh('3.52');
}

//
function _upgradeToVersion3_14() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '3.14') { return; }

  // error checking
  $encryptedColumnList = mysql_encrypt_listColumns();
  if ($encryptedColumnList) {
    print "Upgrade error: Field encryption method has changed.  Please downgrade to a previous beta build, disable all field encryption, and then upgrade again.\n";
    print "<p>Encrypted fields: " . nl2br(implode("\n", $encryptedColumnList));
    exit;
  }

  // update all tables to utf8mb4 (do this again in case some columns were missed by v3.11)
  _mysql_upgradeTablesToUTF8mb4();

  //
  saveAndRefresh('3.14');
}


//
function _upgradeToVersion3_11() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '3.11') { return; }

  // Update developer email templates to send to developer email
  $updateWhere = "template_id IN ('CMS-ERRORLOG-ALERT','CMS-BGTASK-ERROR') AND `to` = '#settings.adminEmail#'";
  mysql_update("_email_templates", null, $updateWhere, ['to' => '#settings.developerEmail#']);

  // update all tables to utf8mb4
  _mysql_upgradeTablesToUTF8mb4();

  //
  saveAndRefresh('3.11');
}

//
function _upgradeToVersion3_09() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '3.09') { return; }

  // Enable legacy mysql support if upgrading from earlier versions
  $SETTINGS['advanced']['legacy_mysql_support'] = '1';
  saveSettings();

  //
  saveAndRefresh('3.09');
}

//
function _upgradeToVersion3_00() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '3.00') { return; }

  // create missing tables and sections: eg: _error_log backtrace field
  createMissingSchemaTablesAndFields();

  // remove 'email_sent' from _error_log
  _upgrade_removeColumnIfExists('_error_log', 'email_sent');

  // reset theme - theme filenames have changed
  if     ($SETTINGS['cssTheme'] == 'blue.css')  { $SETTINGS['cssTheme'] = "theme_blue.css"; }
  elseif ($SETTINGS['cssTheme'] == 'green.css') { $SETTINGS['cssTheme'] = "theme_green.css"; }
  elseif ($SETTINGS['cssTheme'] == 'red.css')   { $SETTINGS['cssTheme'] = "theme_red.css"; }
  else                                          { $SETTINGS['cssTheme'] = "theme_blue.css"; }
  saveSettings();

  // activate system plugins (system plugins now only activate on upgrade to v3 or when plugin list is viewed)
  getPluginList(); // calling this function activates system plugins

  //
  saveAndRefresh('3.00');
}



//
function _upgradeToVersion2_09() {
  global $SETTINGS;
  if ($SETTINGS['programVersion'] >= '2.09') { return; }

  // create missing table "_settings"
  createMissingSchemaTablesAndFields();

  //
  makeAllUploadRecordsRelative();

  //
  saveAndRefresh('2.09');
}


//
function _upgradeToVersion2_07() {
  global $SETTINGS, $APP, $TABLE_PREFIX;
  if ($SETTINGS['programVersion'] >= '2.07') { return; }


  // update mysql tables, schema, schema preset files
  $schemaDirs = array(DATA_DIR .'/schema', DATA_DIR . '/schemaPresets');
  foreach ($schemaDirs as $schemaDir) {
    foreach (getSchemaTables($schemaDir) as $tableName) {
      $schema = loadSchema($tableName, $schemaDir);
      if (@$schema['menuType'] == 'link') {
        // add field
        if (!array_key_exists('_targetBlank', $schema)) { $schema['_targetBlank'] = 1;  }
      }
      // add field
      if (!array_key_exists('_disablePreview', $schema)) { $schema['_disablePreview'] = 0;  }
      uasort($schema, '__sortSchemaFieldsByOrder'); // sort schema keys
      saveSchema($tableName, $schema, $schemaDir);
    }
  }

  //
  saveAndRefresh('2.07');
}

//
function _upgradeToVersion2_05() {
  global $SETTINGS, $APP, $TABLE_PREFIX;
  if ($SETTINGS['programVersion'] >= '2.05') { return; }


  // update mysql tables, schema, schema preset files
  $skipTables = array('uploads','_accesslist');
  $schemaDirs = array(DATA_DIR .'/schema', DATA_DIR . '/schemaPresets');
  foreach ($schemaDirs as $schemaDir) {
    foreach (getSchemaTables($schemaDir) as $tableName) {
      if (in_array($tableName, $skipTables)) { continue; }  // skip tables
      $schema           = loadSchema($tableName, $schemaDir);

      // add field
      if (!array_key_exists('_disableView', $schema)) { $schema['_disableView'] = 1;  }
      uasort($schema, '__sortSchemaFieldsByOrder'); // sort schema keys
      saveSchema($tableName, $schema, $schemaDir);
    }
  }

  //
  saveAndRefresh('2.05');
}

//
function _upgradeToVersion1_24() {
  global $SETTINGS, $APP, $TABLE_PREFIX;
  if ($SETTINGS['programVersion'] >= '1.24') { return; }

  ### Update account with "Editor" access to all sections to have Editor access
  ### to all by User Accounts so upgrading doesn't grant additional access

  // get list of accounts to update
  $query   = "SELECT * FROM `{$TABLE_PREFIX}_accesslist` acl\n";
  $query  .= "         JOIN `{$TABLE_PREFIX}accounts` a\n";
  $query  .= "           ON acl.tableName = 'all' AND acl.accessLevel = 9 AND a.num = acl.userNum AND a.isAdmin != 1\n";
  $records = mysql_select_query($query);

  // DEBUG - force _accesslist maxRecord to allow nulls (upgrading a site from 1.18 to 2.65 produced "column 'maxRecords' value cannot be null" error
  $debugTable = "{$TABLE_PREFIX}_accesslist";
  $debugField = "maxRecords";
  mysqli()->query("ALTER TABLE `$debugTable` CHANGE COLUMN `$debugField` `$debugField` int(10) unsigned DEFAULT NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);

  // DEBUG - show table
  //$result = mysqli()->query("SHOW CREATE TABLE `{$TABLE_PREFIX}_accesslist`");
  //list(,$createStatement) = $result->fetch_row() or die("MySQL Error: ".htmlencode(mysqli()->error));
  //print $createStatement;

  // update users
  $schemaTables = getSchemaTables();
  foreach ($records as $user) {

    // insert new access levels
    $insertRows  = '';
    $randomId    = uniqid('', true);
    foreach ($schemaTables as $tableName) {
      if ($tableName == 'accounts') { continue; }
      if ($tableName == '_accesslist') { continue; }
      if ($tableName == 'uploads') { continue; }
      if ($insertRows) { $insertRows .= ",\n"; }
      $escapedUserNum   = mysql_escape( $user['num'] );
      $escapedTableName = mysql_escape( $tableName );
      $accessLevel      = '9';
      $maxRecords       = "NULL";
      $escapedSaveId    = mysql_escape( $randomId );
      $insertRows  .= "('$escapedUserNum', '$escapedTableName', '$accessLevel', $maxRecords, '$escapedSaveId')";
    }

    $insertQuery  = "INSERT INTO `{$TABLE_PREFIX}_accesslist`\n";
    $insertQuery .= "(userNum, tableName, accessLevel, maxRecords, randomSaveId)\n";
    $insertQuery .= "VALUES $insertRows\n";
    mysqli()->query($insertQuery) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

    // delete old access levels
    $deleteQuery  = "DELETE FROM `{$TABLE_PREFIX}_accesslist`\n";
    $deleteQuery .= "WHERE userNum = '" .mysql_escape( $user['num'] ). "'\n";
    $deleteQuery .= "  AND randomSaveId != '" .mysql_escape( $randomId ). "'\n";
    mysqli()->query($deleteQuery) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }

  //
  saveAndRefresh('1.24');
}

//
function _upgradeToVersion1_10() {
  global $SETTINGS, $APP, $TABLE_PREFIX;
  if ($SETTINGS['programVersion'] >= '1.10') { return; }

  ### Update Access Levels
  _upgradeToVersion1_10_accessLevels();


  // update mysql tables, schema, schema preset files
  $schemaDirs = array(DATA_DIR .'/schema', DATA_DIR . '/schemaPresets');
  $fieldsToMaintainOrder = array('num','createdDate','createdByUserNum','updatedDate','updatedByUserNum');
  foreach ($schemaDirs as $schemaDir) {
    foreach (getSchemaTables($schemaDir) as $tableName) {
      $schema           = loadSchema($tableName, $schemaDir);
      $escapedTableName = mysql_escape( getTableNameWithPrefix($tableName) );
      $isPreset         = $schemaDir == DATA_DIR.'/schemaPresets';

      // skip tables
      if ($tableName == 'uploads')     { continue; }
      if ($tableName == '_accesslist') { continue; }

      // add fields
      $schema['num']['order']     = "1";
      $schema['createdDate']      = array('order' => '2', 'type' => 'none', 'label' => "Created", 'isSystemField' => '1');
      $schema['createdByUserNum'] = array('order' => '3', 'type' => 'none', 'label' => "Created By", 'isSystemField' => '1');
      $schema['updatedDate']      = array('order' => '4', 'type' => 'none', 'label' => "Last Updated", 'isSystemField' => '1');
      $schema['updatedByUserNum'] = array('order' => '5', 'type' => 'none', 'label' => "Last Updated By", 'isSystemField' => '1');

      //
      foreach (array_keys($schema) as $fieldname) {
        $fieldSchema = &$schema[$fieldname];
        if (!is_array($fieldSchema)) { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata
        if (!in_array($fieldname, $fieldsToMaintainOrder)) {
          $fieldSchema['order'] = @$fieldSchema['order'] + 6;
        }

        ### Change column type for checkbox fields
        if (@$fieldSchema['type'] == 'checkbox' && !$isPreset) {
          mysqli()->query("UPDATE `$escapedTableName` SET `$fieldname` = 0 WHERE `$fieldname` IS NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
          mysqli()->query("ALTER TABLE `$escapedTableName` CHANGE COLUMN `$fieldname` `$fieldname` tinyint(1) unsigned NOT NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
        }

        ### Change column type for datetime fields
        if (@$fieldSchema['type'] == 'date' && !$isPreset) {
          mysqli()->query("UPDATE `$escapedTableName` SET `$fieldname` = '0000-00-00 00:00:00' WHERE `$fieldname` IS NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
          mysqli()->query("ALTER TABLE `$escapedTableName` CHANGE COLUMN `$fieldname` `$fieldname` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
        }

        // Rename autoPublish fields
        if ($fieldname == 'autoPublishStartDate' && !@$schema['publishDate']) {
          $schema['publishDate'] = $fieldSchema;
          unset($schema[$fieldname]);
          if (!$isPreset) {
            mysqli()->query("UPDATE `$escapedTableName` SET `$fieldname` = '0000-00-00 00:00:00' WHERE `$fieldname` IS NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
            mysqli()->query("ALTER TABLE `$escapedTableName` CHANGE COLUMN `$fieldname` `publishDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
          }
        }
        if ($fieldname == 'autoPublishEndDate' && !@$schema['removeDate']) {
          $schema['removeDate'] = $fieldSchema;
          unset($schema[$fieldname]);
          if (!$isPreset) {
            mysqli()->query("UPDATE `$escapedTableName` SET `$fieldname` = '0000-00-00 00:00:00' WHERE `$fieldname` IS NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
            mysqli()->query("ALTER TABLE `$escapedTableName` CHANGE COLUMN `$fieldname` `removeDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
          }
        }
        if ($fieldname == 'autoPublishNeverExpires' && !@$schema['neverRemove']) {
          $schema['neverRemove'] = $fieldSchema;
          unset($schema[$fieldname]);
          if (!$isPreset) {
            mysqli()->query("UPDATE `$escapedTableName` SET `$fieldname` = 0 WHERE `$fieldname` IS NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
            mysqli()->query("ALTER TABLE `$escapedTableName` CHANGE COLUMN `$fieldname` `neverRemove` tinyint(1) unsigned NOT NULL") or die("Mysql error:\n\n". htmlencode(mysqli()->error) . " in " .__FILE__ . ", line " .__LINE__);
          }
        }
      }

      uasort($schema, '__sortSchemaFieldsByOrder'); // sort schema keys
      saveSchema($tableName, $schema, $schemaDir);
    }
  }

  //
  createMissingSchemaTablesAndFields(); // create missing fields
  clearAlertsAndNotices(); // don't show "created table/field" alerts

  saveAndRefresh('1.10'); // uncomment this after next update
}


//
function _upgradeToVersion1_10_accessLevels() {
  global $TABLE_PREFIX;

  // error checking (check upgrade files were uploaded)
  $errors           = '';
  $accessListSchema = loadSchema("_accesslist");
  $accountsSchema   = loadSchema("accounts");
  if (empty($accessListSchema)) { $errors .= "Error: You must upload the latest /data/schema/_accesslist.ini.php before upgrading!<br>\n"; }
  if ($errors) {
    die($errors);
  }

  // check if already upraded
  $result = mysqli()->query("SELECT * FROM `{$TABLE_PREFIX}accounts` LIMIT 0,1") or die("MySQL Error: ". htmlencode(mysqli()->error) ."\n");
  $record = $result->fetch_assoc();
  if (!$record || !array_key_exists('tableAccessList', $record)) { return; }


  // create new access table
  $query = "CREATE TABLE IF NOT EXISTS `{$TABLE_PREFIX}_accesslist` (
    `userNum`      int(10) unsigned NOT NULL,
    `tableName`    varchar(255) NOT NULL,
    `accessLevel`  tinyint(3) unsigned NOT NULL,
    `maxRecords`   int(10) unsigned default NULL,
    `randomSaveId` varchar(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  mysqli()->query($query) || die("Error creating new access table.<br>\n MySQL error was: ". htmlencode(mysqli()->error) . "\n");

  // create accessList field
  if (!@$accountsSchema['accessList']) {
    $accountsSchema['accessList'] = array('type' => 'accessList', 'label' => "Section Access", 'isSystemField' => '1', 'order' => 20);
    createMissingSchemaTablesAndFields(); // create missing fields
    clearAlertsAndNotices(); // don't show "created table/field" alerts
  }

  // drop tableAccessList
  if (@$accountsSchema['tableAccessList']) {
    unset($accountsSchema['tableAccessList']);
    saveSchema('accounts', $accountsSchema);
  }

  ### upgrade access levels
  $schemaTables = getSchemaTables();
  $schemaTables[] = "all";
  $result = mysqli()->query("SELECT * FROM `{$TABLE_PREFIX}accounts`") or die("MySQL Error: ". htmlencode(mysqli()->error) ."\n");
  while ($record = $result->fetch_assoc()) {
    if(!array_key_exists('tableAccessList', $record)) { die(__FUNCTION__ . ": Couldn't load field 'tableAccessList'!"); }

    // convert section access to new format
    $tableNames = [];
    $tableNames['all'] = 1; // default all to "By Section" access
    foreach ($schemaTables as $tableName) {
      $adminAccess = preg_match("/\b$tableName\b/i", $record['tableAccessList']);
      if ($adminAccess) { $tableNames[$tableName] = '9'; }
    }

    // foreach table - add to insert query
    $insertRows   = '';
    $fieldNames   = "userNum, tableName, accessLevel, maxRecords, randomSaveId";
    $foundAll     = false;
    foreach ($tableNames as $tableName => $accessLevel) {
      if ($insertRows) { $insertRows .= ",\n"; }
      $escapedUserNum   = mysql_escape( $record['num'] );
      $escapedTableName = mysql_escape( $tableName );
      $maxRecords       = "NULL";
      $escapedSaveId    = mysql_escape( uniqid('', true) );
      $insertRows  .= "('$escapedUserNum', '$escapedTableName', '$accessLevel', $maxRecords, '$escapedSaveId')";
    }

    // add all
    $insertQuery  = "INSERT INTO `{$TABLE_PREFIX}_accesslist` ($fieldNames) VALUES $insertRows;";

    // insert new access rights
    if ($insertRows) {
      mysqli()->query($insertQuery) or die("MySQL Error Inserting New Access Rights: ". htmlencode(mysqli()->error) . "\n");
    }
  }

  // drop tableAccessList
  $query = "ALTER TABLE `{$TABLE_PREFIX}accounts` DROP COLUMN `tableAccessList`;";
  mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

}

//
function _upgradeToVersion1_08() {
  global $SETTINGS, $APP;
  if ($SETTINGS['programVersion'] >= '1.08') { return; }

  ### re-encode all ini files (store "\n" as '\n', \ as \\, and " as \q)

  // settings
  $SETTINGS = parse_ini_file(SETTINGS_FILEPATH, true); // load without decoding
  saveSettings(); // save (automatically encodes)

  // schema files
  $schemaDir = DATA_DIR . '/schema';
  foreach (getSchemaTables($schemaDir) as $tableName) {
    $schemaPath = "$schemaDir/$tableName.ini.php";
    $schema     = parse_ini_file($schemaPath, true); // load without decoding
    saveSchema($tableName, $schema, $schemaDir);
  }

  // schema presets
  $presetDir = DATA_DIR . '/schemaPresets';
  foreach (getSchemaTables($presetDir) as $tableName) {
    $presetPath = "$presetDir/$tableName.ini.php";
    $schema     = parse_ini_file($presetPath, true); // load without decoding
    saveSchema($tableName, $schema, $presetDir);
  }

  saveAndRefresh('1.08');
}

//
function _upgradeToVersion1_07() {
  global $SETTINGS, $APP;
  if ($SETTINGS['programVersion'] >= '1.07') { return; }

  // rename schema fields
  foreach (getSchemaTables() as $tableName) {
    $schema = loadSchema($tableName);
    foreach (array_keys($schema) as $fieldname) {
       $fieldSchema = &$schema[$fieldname];
      if (!is_array($fieldSchema)) { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata

      if (@$fieldSchema['type'] == 'list') {
        // add 'optionsType'
        if (!@$fieldSchema['optionsType']) { $fieldSchema['optionsType'] = 'text'; }

        // rename 'listOptions' to 'optionsText'
        if (array_key_exists('listOptions', $fieldSchema)) {
          $fieldSchema['optionsText'] = $fieldSchema['listOptions'];
          unset($fieldSchema['listOptions']);
        }
      }

    }
    saveSchema($tableName, $schema);
  }

  createMissingSchemaTablesAndFields(); // create missing fields
  clearAlertsAndNotices(); // don't show "created table/field" alerts

  saveAndRefresh('1.07');

}

//
function _upgradeToVersion1_06() {
  global $SETTINGS, $APP;
  if ($SETTINGS['programVersion'] >= '1.06') { return; }

  // change schema key 'checkboxDescription' to 'description'
  foreach (getSchemaTables() as $tableName) {
    $schema = loadSchema($tableName);
    foreach (array_keys($schema) as $fieldname) {
      $fieldSchema = &$schema[$fieldname];
      if (!is_array($fieldSchema)) { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata

      // rename attribute
      if (array_key_exists('checkboxDescription', $fieldSchema)) {
        $fieldSchema['description'] = $fieldSchema['checkboxDescription'];
        unset($fieldSchema['checkboxDescription']);
      }

      // set allowUploads to off for existing WYSIWYG fields
      if (@$fieldSchema['type'] == 'wysiwyg') {
         if (!array_key_exists('allowUploads', $fieldSchema))    { $fieldSchema['allowUploads']    = '0'; }
      }

    }
    saveSchema($tableName, $schema);
  }

  saveAndRefresh('1.06');
}

//
function _upgradeToVersion1_04() {
  global $SETTINGS, $APP;
  if ($SETTINGS['programVersion'] >= '1.04') { return; }

  // update schema files with new upload fields (checkboxes)
  foreach (getSchemaTables() as $tableName) {
    $schema = loadSchema($tableName);
    foreach (array_keys($schema) as $fieldname) {
      $fieldSchema = &$schema[$fieldname];
      if (@$fieldSchema['type'] != 'upload') { continue; }  // skip all but upload fields

      // add fields
      if (!array_key_exists('checkMaxUploadSize', $fieldSchema))    { $fieldSchema['checkMaxUploadSize']    = !empty($fieldSchema['maxUploadSizeKB']); }
      if (!array_key_exists('checkMaxUploads', $fieldSchema))       { $fieldSchema['checkMaxUploads']       = '1'; }
      if (!array_key_exists('resizeOversizedImages', $fieldSchema)) { $fieldSchema['resizeOversizedImages'] = $fieldSchema['maxImageHeight'] && $fieldSchema['maxImageWidth']; }
      if (!array_key_exists('createThumbnails', $fieldSchema))      { $fieldSchema['createThumbnails']      = $fieldSchema['maxThumbnailHeight'] && $fieldSchema['maxThumbnailWidth']; }
      if (!array_key_exists('customUploadDir', $fieldSchema))       { $fieldSchema['customUploadDir']       = ''; }
      if (!array_key_exists('customUploadUrl', $fieldSchema))       { $fieldSchema['customUploadUrl']       = ''; }
      if (!array_key_exists('infoField1', $fieldSchema))            { $fieldSchema['infoField1']            = ''; }
      if (!array_key_exists('infoField2', $fieldSchema))            { $fieldSchema['infoField2']            = ''; }
      if (!array_key_exists('infoField3', $fieldSchema))            { $fieldSchema['infoField3']            = ''; }
      if (!array_key_exists('infoField4', $fieldSchema))            { $fieldSchema['infoField4']            = ''; }
      if (!array_key_exists('infoField5', $fieldSchema))            { $fieldSchema['infoField5']            = ''; }
    }
    saveSchema($tableName, $schema);
  }

  saveAndRefresh('1.04');
}

//
function saveAndRefresh($version) {
  global $SETTINGS, $APP;

  // save settings
  $SETTINGS['programVersion'] = $version;
  saveSettings();

  print "Software updated to v$version<br>\n";
  print '<meta http-equiv="refresh" content="1">';
  exit;
}


//
function _upgradeSettings() {
  global $SETTINGS, $APP;

  // NOTE: These are now set in _init_loadSettings()

  // save INI (do this once when upgrading to make sure any new settings are saved)
  saveSettings();
}

//
function _upgradeAccounts() {

  // add new upload fields
  $schema = loadSchema('accounts');

  // make schema and menu visible
  if (@$schema['tableHidden'])       { $schema['tableHidden'] = 0; }
  if (@$schema['menuHidden'])        { $schema['menuHidden']  = 0; }

  // add new fields
  if (!@$schema['createdDate'])      { $schema['createdDate']      = array('type' => 'none', 'label' => "Created", 'isSystemField' => '1'); }
  if (!@$schema['createdByUserNum']) { $schema['createdByUserNum'] = array('type' => 'none', 'label' => "Created By", 'isSystemField' => '1'); }
  if (!@$schema['updatedDate'])      { $schema['updatedDate']      = array('type' => 'none', 'label' => "Last Updated", 'isSystemField' => '1'); }
  if (!@$schema['updatedByUserNum']) { $schema['updatedByUserNum'] = array('type' => 'none', 'label' => "Last Updated By", 'isSystemField' => '1'); }
  if (!@$schema['accessList'])       { $schema['accessList']       = array('type' => 'accessList', 'label' => "Section Access", 'isSystemField' => '1', 'order' => time()); }
  if (!@$schema['lastLoginDate'])    { // added in v2.08
    $schema['lastLoginDate'] = array('type' => 'date', 'label' => "Last Login", 'defaultDate' => 'none', 'order' => time(),
                                     'showTime' => '1', 'use24HourFormat' => '0', 'showSeconds' => '1', 'yearRangeStart' => '2010', 'yearRangeEnd' => '2020');
  }

  // remove fields
  foreach (array_keys($schema) as $fieldname) {
    $fieldSchema = &$schema[$fieldname];
    if (!is_array($fieldSchema)) { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata

    // remove old "show tablenames" field for old access settings
    if (@$fieldSchema['type'] == 'separator' && preg_match("/listTableNames\(\)'>MySQL Tablenames/", ($fieldSchema['separatorHTML'] ?? ''))) {
      unset($schema[$fieldname]);
    }
  }

  ### update order
  // increase field order for all fields
  foreach (array_keys($schema) as $fieldname) {
    $fieldSchema = &$schema[$fieldname];
    if (!is_array($fieldSchema)) { continue; }  // fields are stored as arrays, other entries are table metadata, skip metadata
    $fieldSchema['order'] += 10;
  }

  // hard code field order
  if (@$schema['num'])              { $schema['num']['order']              = '1';  }
  if (@$schema['createdDate'])      { $schema['createdDate']['order']      = '2';  }
  if (@$schema['createdByUserNum']) { $schema['createdByUserNum']['order'] = '3';  }
  if (@$schema['updatedDate'])      { $schema['updatedDate']['order']      = '4';  }
  if (@$schema['updatedByUserNum']) { $schema['updatedByUserNum']['order'] = '5';  }

  ### change fields

  // Set checked/unchecked values for 'isAdmin' field
  if (@$schema['isAdmin']) {
    if (@$schema['isAdmin']['checkedValue'] == '')   { $schema['isAdmin']['checkedValue']   = 'Yes'; }
    if (@$schema['isAdmin']['uncheckedValue'] == '') { $schema['isAdmin']['uncheckedValue'] = '-'; }
    $schema['isAdmin']['adminOnly'] = "2";
  }

  // Set accessList to be a system field
  if (@$schema['accessList']) { $schema['accessList']['isSystemField'] = 1; }

  // v1.32 - add "My Account" fields
  $myAccountFields = array('fullname','username','email','password');
  foreach ($myAccountFields as $field) {
    if (!is_array(@$schema[$field])) { continue; }
    if (array_key_exists('myAccountField', $schema[$field])) { continue; } // ignore if already set
    $schema[$field]['myAccountField'] = 1;
  }


  // save changes
  saveSchema('accounts', $schema);       // add to schema
  createMissingSchemaTablesAndFields(); // add to database
  clearAlertsAndNotices(); // don't show "created table/field" alerts
}

//
function _removeOldCacheFiles() {

  // remove old tinymce cache files (to ensure any new tinymce changes are used)
  // Source: /lib/upgrade_functions.php:_removeOldCacheFiles() and /3rdParty/TinyMCE4/tinymce.gzip.php (search for "Write cached file")
  $cacheFiles = scandir_recursive(DATA_DIR, '/\/(tiny_mce_|tinymce-).*?\.(gz|js)$/i', 0);  // cache files have been called both tiny_mce_cache_* and tiny_mce_* and tinymce-*
  foreach ($cacheFiles as $filepath) { unlink($filepath); }

}

//
function _notifyUpgradeComplete() {
  global $APP;
  notice("Software upgraded to v{$APP['version']}<br>\n");

  # notifications about old files when upgrading
  $oldFilepaths = array(
    '/css/ui.css',
    '/css/ui_ie6.css',
    '/css/wysiwyg.css',
    '/images/',
    '/tinymce3/',
    '/lib/plugins/',
    '/lib/menus/accounts/',
    '/tinyMCE/',
  );
  $filepathsToRemove = '';
  foreach ($oldFilepaths as $oldFilepath) {
    if (file_exists(SCRIPT_DIR . $oldFilepath)) {
      $filepathsToRemove .= "- $oldFilepath<br>\n";
    }
  }
  if ($filepathsToRemove) {
    notice("Optional Upgrade Step - Remove these old files:<br>\n$filepathsToRemove");
  }
}

// remove column from CMS schema and MySQL 
function _upgrade_removeColumnIfExists($tableName, $fieldname) {

  // remove from schema
  $schema = loadSchema($tableName);
  if (isset($schema[$fieldname])) {
    $schema[$fieldname] = $fieldSchema;
    saveSchema($tableName, $schema);
  }

  // remove from mysql
  $escapedTableName = getTableNameWithPrefix($tableName);
  $colsToType       = getMySqlColsAndType($escapedTableName);
  if (array_key_exists($fieldname, array_keys($colsToType))) {
    $query = mysql_escapef("ALTER TABLE `?` DROP COLUMN `?`;", $tableName, $fieldname);
    mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  }
}

// check custom_wysiwyg files for old file paths
// ... and for options, themes, and plugins that has been deprecated/changed in TinyMCE v4
function _getTinymce4UpgradeErrors($customWysiwygCode){
  $issuesHtml = '';
  
  ### check issues in custom_wysiwyg.php ###
  $issues     = [];
  
  // check for old paths
  if (preg_match("|/tiny_mce/|", $customWysiwygCode)) { // changed in CMSB v3.07
    $issues[] = 'Old library path "/tiny_mce/" path found, the new path is "/TinyMCE4/"';
  }
  if (preg_match("/tinymce3/", $customWysiwygCode)) {
    $issues[] = 'Old library path "/tinymce3/" path found, the new path is "/TinyMCE4/"';
  }
  
  // check for old tinyMCE compressor
  if (preg_match("/tiny_mce_gzip\.php/", $customWysiwygCode)) { // changed in CMSB v3.07
    $issues[] = 'Old compressor file "tiny_mce_gzip.php" found, the new file is "tinymce.gzip.php"';
  }
  
  // check for old themes
  // ... the "simple" and "advanced" themes were removed in TinyMCE 4.0.
  if (preg_match("/[\"']simple[\"']/", $customWysiwygCode)) { // removed in removed in TinyMCE v4
    $issues[] = 'Old theme "simple" found, new theme is "modern" or "inlite"';
  }
  if (preg_match("/[\"']advanced[\"']/", $customWysiwygCode)) { // removed in removed in TinyMCE v4
    $issues[] = 'Old theme "advanced" found, new theme is "modern" or "inlite"';
  }
  
  // check for old plugins
  // ... plugins removed in TinyMCE 4.0: advhr, advimage, advlink, iespell, inlinepopups, style, emotions and xhtmlxtras.
  $deprecatedPlugins      = ['advhr', 'advimage', 'advlink', 'iespell', 'inlinepopups', 'style', 'emotions', 'xhtmlxtras'];
  $deprecatedPluginsFound = [];
  foreach ($deprecatedPlugins as $plugin) {
    if (preg_match("/['\",\s]" . $plugin . "['\",]/", $customWysiwygCode)) {
      $deprecatedPluginsFound[] = "'$plugin'";
    }
  }
  if ($deprecatedPluginsFound) {
    $issues[] = 'Deprecated plugins found: ' . implode(", ", $deprecatedPluginsFound);
  }
  
  // check for old toolbar buttons options
  if (preg_match("/theme_advanced_buttons/", $customWysiwygCode)) { // changed in removed in TinyMCE v4
    $issues[] = 'Old toolbar buttons option "theme_advanced_buttons" found, new option is "toolbar"'; 
  }
  
  // check for old upload file browser option
  if (preg_match("/theme_advanced_buttons/", $customWysiwygCode)) { // changed in removed in TinyMCE v4
    $issues[] = 'Old "file_browser_callback" option found, new option is "file_picker_callback"'; 
  }
  
  // check for other known deprecated options 
  $otherOldOptions = ['theme_advanced_blockformats',
                      'paste_text_sticky',
                      'theme_advanced_statusbar_location',
                      'theme_advanced_statusbar_location',
                      'theme_advanced_resizing',
                      'theme_advanced_toolbar_location',
                      'theme_advanced_toolbar_align'
                     ];
  $otherOldOptionsFound = [];
  foreach ($otherOldOptions as $oldOption) {
    if (preg_match("/$oldOption/", $customWysiwygCode)) {
      $otherOldOptionsFound[] = "'$oldOption'";
    }
  }
  if ($otherOldOptionsFound) {
    $issues[] = 'Old deprecated options found: ' . implode(", ", $otherOldOptionsFound);
  }
  
  
  ### check issues in custom_wysiwyg.css ###
  $cssIssues        = [];
  $customWysiwygCSS = @file_get_contents('lib/wysiwyg_custom.css');
  
  // check for old body class
  if (preg_match("/mceContentBody/", $customWysiwygCSS)) { // changed in TinyMCE v4
    $cssIssues[] = 'Old body class name "mceContentBody" found, please remove as there is no need to specify the body class'; 
  }
  
  
  ### generate html ###
  if ($issues) {
    $issuesHtml .= "<b>Issues found in /lib/wysiwyg_custom.php:</b><br>\n";
    $issuesHtml .= "<ul>\n";
    foreach ($issues as $issue) {
      $issuesHtml .= "<li>$issue</li>\n";
    }
    $issuesHtml .= "</ul>\n";
  }
  
  if ($cssIssues) {
    $issuesHtml .= "<b>Issues found in /lib/wysiwyg_custom.css:</b><br> \n";
    $issuesHtml .= "<ul>\n";
    foreach ($cssIssues as $cssIssue) {
      $issuesHtml .= "<li>$cssIssue</li>\n";
    }
    $issuesHtml .= "</ul>\n";
  }
  
  return $issuesHtml;
}

// if non-existant CSS theme file is specified then default to theme_blue.css
// forceValidCssTheme('theme_blue.css'); // if non-existant CSS theme file is specified then default to theme_blue.css
function forceValidCssTheme($defaultThemeFile = 'theme_blue.css') {
  $themeFilepath = CMS_ASSETS_DIR ."/3rdParty/clipone/css/". $GLOBALS['SETTINGS']['cssTheme'];
  if (!$GLOBALS['SETTINGS']['cssTheme'] || !is_file($themeFilepath)) {
    $GLOBALS['SETTINGS']['cssTheme'] = $defaultThemeFile;
    saveSettings();
  }

}

//eof

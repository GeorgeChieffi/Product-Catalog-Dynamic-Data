<?php


// create missing tables and fields
// AND encrypt/decrypt fields that need it.
function createMissingSchemaTablesAndFields() {
  global $TABLE_PREFIX;

  $schemaTables = getSchemaTables();
  $mysqlTables  = getMysqlTablesWithPrefix();

  // create missing schema tables in mysql
  foreach ($schemaTables as $tableName) {
    $mysqlTableName   = $TABLE_PREFIX . $tableName;
    $schemaFields     = getSchemaFields($tableName, false);
    $schemaFieldnames = array_keys( $schemaFields );

    // create mysql table
    if (!in_array($mysqlTableName, $mysqlTables)) {
      notice(t("Creating MySQL table for schema table: ").$tableName."<br>\n");
      $query  = "CREATE TABLE `".mysql_escape($mysqlTableName)."`\n";
      $query .= "(num int(10) unsigned NOT NULL auto_increment, PRIMARY KEY (num))\n";
      $query .= "ENGINE=InnoDB ROW_FORMAT=DYNAMIC CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      $result = mysqli()->query($query);
      if (!$result) { alert(sprintf("Error creating MySQL table: %s<br>\MySQL error was: ",$mysqlTableName).htmlencode(mysqli()->error) . "\n"); }
      if (is_resource($result)) { mysqli_free_result($result); }

      // run defaultSqlData if applicable
      $defaultSqlFile = DATA_DIR . "/schema/$tableName.defaultSqlData.php";
      if (file_exists($defaultSqlFile)) {
        restoreDatabase($defaultSqlFile, $tableName);
        notice(t("Importing default data for schema table: ").$tableName."<br>\n");
      }
    }

    //Load MySQL columns and types after the table has been created.
    $mysqlColsToTypes = getMySqlColsAndType($mysqlTableName);
    $mysqlIndexes     = mysql_select_query('SHOW INDEXES FROM `'.mysql_escape($mysqlTableName).'` ');
    $indexNames       = array_map('strtolower', array_column($mysqlIndexes,'Key_name'));

    // add missing fieldnames to mysql
    $addFieldSQL = [];
    $mysqlFieldnamesLc = array_map('strtolower', array_keys($mysqlColsToTypes));
    
    foreach ($schemaFieldnames as $fieldname) {
      $columnType = getColumnTypeFor($fieldname, @$schemaFields[$fieldname]['type'], @$schemaFields[$fieldname]['customColumnType']);
      if (!$columnType) { continue; }
      //If field isn't in the table, add it...
      if (!in_array(strtolower($fieldname), $mysqlFieldnamesLc)) {
        $addFieldSQL[] = "ADD COLUMN `".mysql_escape($fieldname)."` $columnType";
      }
      //If field in indexed...
      if (@$schemaFields[$fieldname]['indexed']) {
        list($indexName, $indexColList) = getIndexNameAndColumnListForField($fieldname, $columnType);
        //...check index name against the list of indexes in the DB, add the index if it is missing
        if(!in_array(strtolower($indexName),$indexNames)){
          $addFieldSQL[] = "ADD INDEX `$indexName` $indexColList";
        }
      }
    }
    
    if ($addFieldSQL) {
      $addFieldSQL = implode(', ',$addFieldSQL);
      mysqli()->query("ALTER TABLE `".mysql_escape($mysqlTableName)."` $addFieldSQL") or die("Error adding fields to '$mysqlTableName', the error was:\n\n". htmlencode(mysqli()->error));
      notice(t("Adding MySQL fields for schema table:")." $tableName<br>\n");
    }

    //reloadload MySQL columns and types after missing fields have been populated
    $mysqlColsToTypes = getMySqlColsAndType($mysqlTableName);

    // encrypt fields (where schema has changed but mysql hasn't - caused when an encrypted fi)
    foreach ($schemaFields as $fieldname => $fieldSchema) {
      $mysqlColType    = empty($mysqlColsToTypes[$fieldname])    ? '' : strtolower($mysqlColsToTypes[$fieldname]);
      $schemaColType   = empty($fieldSchema['customColumnType']) ? '' : strtolower($fieldSchema['customColumnType']);
      $needsEncryption = !empty($fieldSchema['isEncrypted']) && $fieldSchema['isEncrypted'] && $mysqlColType != strtolower(_mysql_encryption_colType()); // schema says it's an encrypted field, but mysql col type isn't the required type
      if ($needsEncryption) {
        alert("Encrypting {$fieldname} to match schema settings.<br>\n");
        mysql_encrypt_column($mysqlTableName, $fieldname);
      }
    }

  }
  

  
  
}


// create schema from sourcefile (if schema doesn't already exist) and display notices about new schema
function createSchemaFromFile($sourcePath) {
  $schemaFile = basename($sourcePath);
  $targetPath = realpath(DATA_DIR . '/schema') . '/' . $schemaFile;

  // error checking
  if (!file_exists($sourcePath)) { throw new Exception(__FUNCTION__. ": source file doesn't exist: $sourcePath"); }

  static $order;
  if (!$order) { $order = time(); }
  $order++; // add schema sequentially, even if adding them in the same second.

  // copy schema file
  if (file_exists($targetPath)) { return; }
  @copy($sourcePath, $targetPath) or die(t("Error copying schema file!").@$php_errormsg);

  // update schema file
  list($tableName) = explode('.', $schemaFile);
  $schema = loadSchema($tableName);
  $schema['menuOrder'] = $order; // sort new schema to the bottom
  saveSchema($tableName, $schema);

  // display message and create tables
  createMissingSchemaTablesAndFields();  // create mysql tables
}


//
function getSortedSchemas() {

  $schemas = [];
  foreach (getSchemaTables() as $tableName) {
    $schemas[$tableName] = loadSchema($tableName);
  }
  uasort($schemas, '_sortMenusByOrder');

  return $schemas;
}


// $schemaTables = getSchemaTables();
// $schemaTables = getSchemaTables(DATA_DIR.'/schemaPresets/');
function getSchemaTables($dir = '') {

  if (!$dir) { $dir = realpath(DATA_DIR . '/schema/'); }

  // get schema files
  $schemaTables = [];
  foreach (scandir($dir) as $file) {
    if (!preg_match("/([^.]+)\.ini\.php$/", $file, $matches)) { continue; } // skip non-schema files
    $tableName = $matches[1];
    $schemaTables[] = $tableName;
  }

  return $schemaTables;
}


// $schemaFields = getSchemaFields($tablename);
// foreach ($schemaFields as $fieldname => $fieldSchema) {
// $fieldnames = array_keys( getSchemaFields($tablename) );
function &getSchemaFields($tableNameOrSchema, $useCache = true) {

  // load schema
  $schema = $tableNameOrSchema;
  if (!is_array($schema)) { $schema = loadSchema($tableNameOrSchema, '', $useCache); }

  // load fields
  $fieldList = [];
  foreach ($schema as $name => $valueOrArray) {
    // log error on numeric fieldnames, you can't create them in the field editor, and when present they cause array_merge() to renumber schema fields losing field name keys
    // Reference: http://stackoverflow.com/questions/4100488/a-numeric-string-as-array-key-in-php#answer-4100765
    if (preg_match('/^\d+$/', $name)) { @trigger_error("Warning: invalid fieldname \"$name\", fieldnames cannot be all numeric", E_USER_WARNING); }
    
    if (is_array($valueOrArray)) {  // only fields have arrays as values, other values are table metadata
      $fieldList[$name]               = $valueOrArray;
      $fieldList[$name]['name']       = $name;                 // add pseudo-field for fieldname
      $fieldList[$name]['_tableName'] = $schema['_tableName']; // add pseudo-field for _tableName
    }
  }

  //
  return $fieldList;
}


// Returns schema file or a blank array if schema not found
// $schema = loadSchema($tableName);
function loadSchema($tableName, $schemaDir = '', $useCache = false) {
  $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName);
  if ($schemaDir && !is_dir($schemaDir)) { dieAsCaller (__FUNCTION__ . ": specified schemaDir '" .htmlencode($schemaDir). "' doesn't exist!"); } // catch error where $useCache paramater is passed as second argument

  // returned cached files
  static $cache;
  if ($useCache && isset($cache[$tableNameWithoutPrefix])) { return $cache[$tableNameWithoutPrefix]; }  // v3.09
  
  // error checking
  $errors = ''; 
  if     (!$tableName)                                       { $errors .= __FUNCTION__ . ": no tableName specified!"; }
  elseif (preg_match('/[^a-zA-Z0-9\-\_\(\)]+/', $tableName)) { $errors .= __FUNCTION__ . ": tableName '" .htmlencode($tableName). "' contains invalid characters!"; }
  if ($errors) {
    @trigger_error($errors, E_USER_NOTICE); // Log errors
    die($errors);
  }

  // get schemapath
  if (!$schemaDir) { $schemaFilepath = DATA_DIR . "/schema/$tableNameWithoutPrefix.ini.php"; }
  else             { $schemaFilepath = "$schemaDir/". getTableNameWithoutPrefix($tableName) . ".ini.php"; }

  // load schema
  $schema = [];
  if (file_exists($schemaFilepath)) {
    $schema = loadStructOrINI($schemaFilepath);
  }

  // add _tableName (v2.16+)
  if ($schema) {
    $schema['_tableName'] = $tableNameWithoutPrefix;
  }
  
  // add default schema fields if they're not defined (so we don't have to check if they're defined everywhere else in the code)
  if ($schema) {
    $defaultFields = ['_filenameFields','_detailPage'];
    foreach ($defaultFields as $field) {
      if (!array_key_exists($field, $schema)) { $schema[$field] = ''; } 
    }
  }
  
  // field encoding
  if ($schema) {
    foreach (['_detailPage'] as $field) {
      $schema[$field] = str_replace(' ', '%20', $schema[$field]); // urlencode spaces so they validate 
    }

  }
  
  // add pseudo-fields
  foreach ($schema as $name => $valueOrArray) {
    // log error on numeric fieldnames, you can't create them in the field editor, and when present they cause array_merge() to renumber schema fields losing field name keys
    // Reference: http://stackoverflow.com/questions/4100488/a-numeric-string-as-array-key-in-php#answer-4100765
    if (preg_match('/^\d+$/', $name)) { @trigger_error("Warning: invalid fieldname \"$name\", fieldnames cannot be all numeric", E_USER_WARNING); }
    
    if (is_array($valueOrArray)) {  // only fields have arrays as values, other values are table metadata
      $schema[$name]               = $valueOrArray;
      $schema[$name]['name']       = $name;                 // add pseudo-field for fieldname
      $schema[$name]['_tableName'] = $schema['_tableName']; // add pseudo-field for _tableName
    }
  }
  
  //
  $schema = applyFilters('post_loadSchema', $schema, $tableName);

  //
  $cache[$tableNameWithoutPrefix] = $schema;
  return $schema;
}


// save schema, sorting entries by field order
// Added $removePrefix option so we can disable that when adding tables that might start with tableprefix, eg: cms_cms_details
function saveSchema($tableName, $schema, $schemaDir = '', $removePrefix = true) {

  // error checking
  if (!$tableName)                            { die(__FUNCTION__ . ": no tableName specified!"); }
  if (!array_key_exists('menuName', $schema)) { dieAsCaller(__FUNCTION__ . ": no menuName specified!"); } // catch saveSchema calls that would wipe out schema

  // get schema filepath
  if ($removePrefix) { $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName); }
  else               { $tableNameWithoutPrefix = $tableName; } // assume prefix isn't present
  if (!$schemaDir) { $schemaFilepath = DATA_DIR . "/schema/$tableNameWithoutPrefix.ini.php"; }
  else             { $schemaFilepath = "$schemaDir/$tableNameWithoutPrefix.ini.php"; }




  // sort schema
  $metaData = [];
  $fields   = [];
  foreach ($schema as $name => $value) {
    if (is_array($value)) { $fields[$name]   = $value; }
    else                  { $metaData[$name] = $value; }
  }
  ksort($metaData);
  uasort($fields, '__sortSchemaFieldsByOrder'); // sort schema keys
  $schema = $metaData + $fields;

  // add/update _tableName (v2.16+)
  if ($schema) { // we need to do this in saveSchema as well as load in case the table was renamed (we want to save the new tablename value)
    $schema['_tableName'] = $tableNameWithoutPrefix;
  }

  // remove pseudo-fields
  foreach ($schema as $name => $valueOrArray) {
    if (is_array($valueOrArray)) {  // only fields have arrays as values, other values are table metadata
      unset($schema[$name]['name']);       // remove pseudo-field for fieldname
      unset($schema[$name]['_tableName']); // remove pseudo-field for _tableName
    }
  }
  
  // save schema
  saveStruct($schemaFilepath, $schema);

  // debug: save in old format - uncomment, click "Save Details" under Section Editors for the table you want, then re-comment
  //saveINI($schemaFilepath, $schema, true); die("saved in old format: $schemaFilepath");
}


// sort field list by order key, eg: uasort($schema, '__sortSchemaFieldsByOrder');
function __sortSchemaFieldsByOrder($fieldA, $fieldB) {

  // mixed metadata/fields - sort metadata keys up, field arrays down
  if (!is_array($fieldA)) { return -1; }
  if (!is_array($fieldB)) { return 1; }

  // sort field meta data below sorted by "order" value
  $orderA = array_key_exists('order', $fieldA) ? $fieldA['order'] : 1000000000;
  $orderB = array_key_exists('order', $fieldB) ? $fieldB['order'] : 1000000000;
  if ($orderA < $orderB) { return -1; }
  if ($orderA > $orderB) { return 1; }
  return 0;
}


//
function getListOptionsFromSchema($fieldSchema, $record=null, $useCache=false, $listValues=null) {
  global $TABLE_PREFIX;

  $listOptions     = [];
  $optionsType     = @$fieldSchema['optionsType'];

  // get list values to lookup
  $listValuesAsCSV = '';
  if ($listValues) {
    foreach ($listValues as $value) { $listValuesAsCSV .= "'" .mysql_escape($value). "',"; }
    $listValuesAsCSV = chop($listValuesAsCSV, ','); // remove trailing comma
  }

  ### parse text options
  if ($optionsType == 'text') { // parse
    $optionText = explode("\n", getEvalOutput(@$fieldSchema['optionsText']));

    foreach ($optionText as $optionString) {
      if (preg_match("/(^|[^\|])(\|\|)*(\|)(?!\|)/", $optionString, $match, PREG_OFFSET_CAPTURE)) {
        $delimiterOffset = $match[3][1];
        $value = substr($optionString, 0, $delimiterOffset);
        $label = substr($optionString, $delimiterOffset+1);
      }
      else {
        $value = $optionString;
        $label = $optionString;
      }

      $value = str_replace("||", "|", $value);
      $label = str_replace("||", "|", $label);

      // remove trailing whitespace
      $value = rtrim($value);
      $label = rtrim($label);

      $listOptions[] = array($value, $label);
    }
  }

  ### lookup table values
  else {
    $cacheTable = '';

    // create query
    if ($optionsType == 'table') {
      $valueField   = @$fieldSchema['optionsValueField'];
      $labelField   = @$fieldSchema['optionsLabelField'];
      $selectTable  = $TABLE_PREFIX . $fieldSchema['optionsTablename'];
      $tableSchema  = loadSchema($fieldSchema['optionsTablename']);
      if (!$tableSchema) {
        $error = htmlencodef("?:? - couldn't find the specified list options table '?'", $fieldSchema['_tableName'], $fieldSchema['name'], $fieldSchema['optionsTablename']);
        @trigger_error($error, E_USER_NOTICE);
        die($error);
      }
      
      $where        = $listValuesAsCSV               ? "WHERE `$valueField` IN ($listValuesAsCSV)" : '';
      $orderBy      = @$tableSchema['listPageOrder'] ? "ORDER BY {$tableSchema['listPageOrder']}"  : '';
      $maxLimit     = getListOptionsFromSchema_maxResults();
      $selectExpr   = mysql_decrypt_getColumnsNamesOrExpr($tableSchema, [$valueField, $labelField]);
      $query        = "SELECT $selectExpr FROM `$selectTable` $where $orderBy LIMIT 0, $maxLimit";

      $cacheTable   = $fieldSchema['optionsTablename'];
    }
    else if ($optionsType == 'query') {
      $filterFieldValue                = @$record[ @$fieldSchema['filterField'] ];
      $GLOBALS['ESCAPED_FILTER_VALUE'] = mysql_escape($filterFieldValue);
      $query                           = getEvalOutput($fieldSchema['optionsQuery']);

      if (preg_match("/\bFROM\s+(\S+)/", $query, $matches)) {
        $cacheTable = $matches[1];
        $cacheTable = preg_replace("/\W/", '', $cacheTable); // remove ` quotes, etc
      }
    }
    else { die("Unknown optionsType '$optionsType'!"); }

    // load cache module
    if ($useCache && $cacheTable) {
      $libDir = __DIR__;
      if (file_exists("$libDir/viewer_turboCache.php")) { require_once "$libDir/viewer_turboCache.php"; }

      // load cached result
      if (!function_exists('turboCache_load')) { die("Error: 'useCaching' enabled but no caching plugin found!<br>Either disable 'useCaching' or install caching plugin."); }

      $listOptions = turboCache_load($cacheTable, $query);
      if ($listOptions) {
        return $listOptions;
      }
    }

    // execute query
    $result = mysqli()->query($query);
    if (!$result) {
      $error  = "There was an error creating the list field '" .@$fieldSchema['name']. "'.\n\n";
      $error .= "MySQL Error: " .mysqli()->error. "\n\n";
//      header("Content-type: text/plain");
//      die($error);
      trigger_error($error, E_USER_ERROR);
    }
    while ($row = $result->fetch_row()) {
      $value = $row[0];
      $label = array_key_exists(1, $row) ? $row[1] : $value; // use value if no label specified
      $listOptions[] = array($value,$label);
    }
    if (is_resource($result)) { mysqli_free_result($result); }

    // save to cache
    if ($useCache && $cacheTable) {
      turboCache_save($cacheTable, $query, $listOptions);
    }
  }

  //
  return $listOptions;
}


// returns maximum of 1000 list options
function getListOptionsFromSchema_maxResults() {
  $maxResults = 1000;
  $maxResults = applyFilters('listFieldOptions_maxResults', $maxResults);
  
  return $maxResults;
}


// This function generates a <select> <option> list for you from a fieldschema
function getSelectOptionsFromSchema($selectedValue, $fieldSchema, $showEmptyOptionFirst = false) {
  // error checking
  if (!$fieldSchema)          { die(__FUNCTION__ . ": No fieldSchema specified!"); }
  if (!@$fieldSchema['name']) { die(__FUNCTION__ . ": fieldSchema must have fieldname defined in 'name'!"); }

  // get field options
  $optionValues = [];
  $optionLabels = [];
  $listOptions  = getListOptionsFromSchema($fieldSchema);
  foreach ($listOptions as $valueAndLabel) {
    list($optionValue, $optionLabel) = $valueAndLabel;
    $optionValues[] = $optionValue;
    $optionLabels[] = $optionLabel;
  }
  $optionsHTML = getSelectOptions($selectedValue, $optionValues, $optionLabels, $showEmptyOptionFirst);

  //
  return $optionsHTML;
}


// Check if schema file exists and return true or false.  Returns false on errors
// Doesn't check if corresponding MySQL table exists or not.
function schemaExists($tableName, $customSchemaDir = '') {
  $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName);
  $defaultSchemaDir       = DATA_DIR . "/schema";
  
  // check for missing or invalid tablenames
  if     (!$tableName)                                       { return false; }
  elseif (preg_match('/[^a-zA-Z0-9\-\_\(\)]+/', $tableName)) { return false; } // invalid chars

  // check if exists
  $schemaDir      = $customSchemaDir ?: $defaultSchemaDir;
  $schemaFilepath = "$schemaDir/$tableNameWithoutPrefix.ini.php"; 
  $schemaExists   = file_exists($schemaFilepath);
 
  //
  return $schemaExists;
}

// return true if specified field is a multi-value list field
// $isMultiValue = schema_isMultiValueField($tablename, $field);
function schema_isMultiValueField($tablename, $field) {
  $schemaFields      = getSchemaFields($tablename, true);
  $listType          = isset($schemaFields[$field]['listType']) ? $schemaFields[$field]['listType'] : '';
  $isMultiValueField = in_array($listType, ['pulldownMulti','checkboxes']);
  return $isMultiValueField;
}

// Unpack multiValue field data into an array of values
// $valuesAsTSV = listValues_pack($valuesArray);
function listValues_pack($valuesArray) {
  $valuesAsTSV = "\t";
  if (is_array($valuesArray)) { $valuesAsTSV .= implode("\t", $valuesArray); }
  $valuesAsTSV .= "\t";

  return $valuesAsTSV;
}

// Return array of values from packed multiValue field data.
//
// In "multi-value" database fields we store multiple values in a packed string format.  The
// format is tab separated values with a leading and trailing tab.  This function returns an
// array of list values stored in this format: "\tVALUE1\tVALUE2\tVALUE3\t".
//
// If the user converts a single value database field to a multi-value field it may still have
// the original non-tab separated value (eg: "VALUE1").  If this functions input is a string
// it will still return it as an array ["VALUE1"] so both single value and multi-value fields
// return the same thing.
//
// Usage: $valuesArray = listValues_unpack($valuesAsTSV);
function listValues_unpack($valuesAsTSV) {
  $valuesAsTSV = $valuesAsTSV ?? ''; // cannot pass null to explode as of PHP 8.1
  $values = explode("\t", $valuesAsTSV);

  // packed values start end end with \t - remove leading/trailing elements
  if (end($values)   == '') { array_pop($values); }   // remove last element if it's blank
  if (reset($values) == '') { array_shift($values); } // remove first element if it's blank

  return $values;
}


//eof
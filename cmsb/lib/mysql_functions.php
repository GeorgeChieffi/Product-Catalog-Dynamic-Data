<?php


// Escape special characters in the mysql queries to prevent SQL injection attacks.
// Doesn't require a database connection like mysql_real_escape string.  The
// following characters are escaped: \x00, \r, \n, \, `, ', ", and \x1a
// ***NOTE*** MySQL values must be wrapped in quotes to be secure: fieldname = "$escapedValue"
function mysql_escape($string, $escapeLikeWildcards = false) {
  // this debug line is to help us find instances where mysql_escape is called before the db is connected
  if (!mysql_isConnected()) { dieAsCaller("DB not connected, can't escape '" .htmlencode($string). "'"); } // debug
  
  // cannot pass null to real_escape_string() as of PHP 8.1
  $string = $string ?? '';

  // v3.54 Switch back to built in function to prevent false positives with static code analysis scanners
  $escaped = mysqli()->real_escape_string($string);
  if ($escapeLikeWildcards) { 
    $escaped = addcslashes($escaped, '%_');
  }
  return $escaped;
  
  // old code
  //// Note that even though this function isn't character-set aware it's a safe alternative
  //// to mysql_real_escape string() so long as ascii or utf8 charsets are used (which we use).
  //// NOTE: Extended comments are available below function in C-V-S copy.
  //
  //// v3.16 error checking: return "invalid input" on arrays|objects to prevent errors
  //// Developers often pass _REQUEST values to mysql_escape, eg: mysql_escape($_REQUEST['name']) and security scanners
  //// often pass arrays values ?name[]=test, which results in errors and false positives. 
  //if (is_array($string) || is_object($string)) { return "invalid input"; }
  //
  ////
  //$escaped = strtr($string, array(
  //  "\x00" => '\0',
  //  "\n"   => '\n',
  //  "\r"   => '\r',
  //  '\\'   => '\\\\',
  //  '`'    => "\`",
  //  "'"    => "\'",
  //  '"'    => '\"',
  //  "\x1a" => '\Z'
  //));
  //
  ////
  //if ($escapeLikeWildcards) { // added in 2.60
  //  $escaped = addcslashes($escaped, '%_');
  //}
  //
  ////
  //return $escaped;
}

//
function mysql_escapeLikeWildcards($string) { 
  return addcslashes($string, '%_');
}



// Automatically espaces and quotes input values and inserts them into query, kind of like mysqli_prepare()
// Example: mysql_escapef("num = ? AND name = ?", $num, $name),
function mysql_escapef() {
  $args         = func_get_args();
  $queryFormat  = array_shift($args);
  $replacements = $args;

  // make replacements
  $escapedQuery = '';
  $queryParts   = explode('?', $queryFormat);
  $lastPart     = array_pop($queryParts); // don't add escaped value on end of query
  foreach ($queryParts as $part) {
    $escapedQuery .= $part;
    $escapedQuery .= "'" . mysql_escape( array_shift($replacements) ) . "'";
  }
  $escapedQuery .= $lastPart;

  //
  return $escapedQuery;
}

// disable mysql strict mode - prevent errors when user inserts records from front-end forms without setting a value for every field
function mysqlStrictMode($strictMode) {
  $mysqlVersion  = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);

  // For future use (if needed)...
  // MySQL > 5.7.7 - Signifigant changes here: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sql-mode-changes
  //if (version_compare($mysqlVersion, '5.7.7', '>')) {
  //}
  //
  // MySQL 5.7.4 to 5.7.7 - Signifigant changes here: https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html#sql-mode-changes
  //else if (version_compare($mysqlVersion, '5.7.4', '>=') && version_compare($mysqlVersion, '5.7.7', '<=')) {
  //}
  
  // MySQL < 5.7.4 (legacy behaviour) - Reference: http://web.archive.org/web/20160502022553/http://dev.mysql.com/doc/refman/5.0/en/sql-mode.html
  //else {
    if ($strictMode) { $sql_mode = "STRICT_ALL_TABLES,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"; }
    else             { $sql_mode                   = "NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"; }
    mysqli()->query("SET SESSION sql_mode = '$sql_mode'") or dieAsCaller("MySQL Error: " .mysqli()->error. "\n");
  //}
  
}

//
function setMySqlTimezone($returnError = false) {
  if (!mysql_isConnected()) { return; } // skip if not connected to MySQL - such as when installing software
  global $SETTINGS;
  $tzOffsetSeconds = date("Z");

  // ignore offsets greater than 12 hours (illegal offset)
  if (abs($tzOffsetSeconds) > 12*60*60) { return; }

  // set mysql timezone
  $offsetString = convertSecondsToTimezoneOffset($tzOffsetSeconds);
  $query        = "SET time_zone = '$offsetString';";
  if (!mysqli()->query($query)) {
    $error = "MySQL Error: " .mysqli()->error. "\n";
    if ($returnError) { return $error; }
    else              { die($error); }
  }
}


// Generate LIMIT clause for paging from pageNum and perPage
// Usage: $limitClause = mysql_limit($perPage, $pageNum);
function mysql_limit($perPage, $pageNum) {
  $limit   = '';
  $perPage = (int) $perPage;
  $pageNum = (int) $pageNum;

  //
  if ($pageNum == 0) { $pageNum = 1; }

  //
  if ($perPage) {
    $offset = ($pageNum-1) * $perPage;
    $limit  = "LIMIT $perPage OFFSET $offset";
  }

  //
  return $limit;
}


//
function mysql_count($tableName, $whereEtc = 'TRUE') {
  if (!$tableName) { die(__FUNCTION__ . ": No tableName specified!"); }
  $tableNameWithPrefix = getTableNameWithPrefix($tableName);
  $escapedTableName    = mysql_escape( $tableNameWithPrefix );

  //
  if (!$whereEtc) { $whereEtc = 'TRUE'; } // old function took where as optional argument so '' would return all
  if (is_array($whereEtc)) { $whereEtc = mysql_where($whereEtc); }
  $query =  "SELECT COUNT(*) FROM `$escapedTableName` WHERE $whereEtc";

  $result = mysqli()->query($query) or dieAsCaller(__FUNCTION__ . "() MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  list($recordCount) = $result->fetch_row();
  if (is_resource($result)) { mysqli_free_result($result); }

  //
  return intval($recordCount); // v2.52 intval added
}


// Wait up to $timeout seconds to get a lock and then return 0 or 1
// Docs: http://dev.mysql.com/doc/refman/4.1/en/miscellaneous-functions.html#function_get-lock
// Usage: mysql_get_lock(__FUNCTION__, 3) or dieAsCaller("Timed out waiting for mysql lock");
function mysql_get_lock($lockName, $timeout = 0) {
  $lockName = implode('.', [$GLOBALS['SETTINGS']['mysql']['database'], $GLOBALS['SETTINGS']['mysql']['tablePrefix'], $lockName]); // v3.08 - make locks specific to this CMS install instead of server wide, eg: database.tableprefix.lockname
  $query    = mysql_escapef("SELECT GET_LOCK(?, ?)", md5($lockName), $timeout); // v3.51 hash lock name to stay under 64 character limit in MySQL 5.7
  $result   = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $isLocked = $result->fetch_row()[0];
  if (is_resource($result)) { mysqli_free_result($result); }

  return $isLocked;
}

// Release a previously held lock
// Docs: http://dev.mysql.com/doc/refman/4.1/en/miscellaneous-functions.html#function_release-lock\
// Usage: mysql_release_lock(__FUNCTION__);
function mysql_release_lock($lockName) {
  $lockName   = implode('.', [$GLOBALS['SETTINGS']['mysql']['database'], $GLOBALS['SETTINGS']['mysql']['tablePrefix'], $lockName]); // v3.08 - make locks specific to this CMS install instead of server wide, eg: database.tableprefix.lockname
  $query      = mysql_escapef("SELECT RELEASE_LOCK(?)", md5($lockName));
  $result     = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $isReleased = $result->fetch_row()[0];
  if (is_resource($result)) { mysqli_free_result($result); }

  return $isReleased;
}

// Format time in MySQL datetime format (default to current server time)
function mysql_datetime($timestamp = null) {
  if ($timestamp === null) { $timestamp = time(); }
  return date('Y-m-d H:i:s', $timestamp); // MySQL format: YYYY-MM-DD HH:MM:SS
}


// return comma separated list of escaped values for construction WHERE ... IN('val1','val2','val3') queries
// if array is empty $defaultValue or 0 is returned so the query will always be value (and not ... IN() which is invalid) MySQL
// Usage: $where = "myfield IN (" .mysql_escapeCSV($values). ")";
function mysql_escapeCSV($valuesArray, $defaultValue = '0') {

  // get CSV values
  $csv = '';
  foreach ($valuesArray as $value) { $csv .= "'" .mysql_escape($value) ."',"; }
  $csv = chop($csv, ',');

  // set default
  if ($csv == '') { $csv = "'" .mysql_escape($defaultValue) ."'"; } // v2.50 quote default value to valid unexpected results comparing a number (0) with a string

  //
  return $csv;
}

// return first matching record or FALSE
// $record = mysql_get($tableName, 123);                   // get first record where: num = 123
// $record = mysql_get($tableName, null, "name = 'test'"); // get first record where: name = 'test'
// $record = mysql_get($tableName, 123,  "name = 'test'"); // get first record where: num = 123 AND name = 'test'
// Data Encryption: Automatically loads decrypted field values
function mysql_get($tableName, $recordNum, $customWhere = null) {
  if ($recordNum && preg_match("/[^0-9]/", strval($recordNum))) { die(__FUNCTION__ . ": second argument must be numeric or null, not '" .htmlencode(strval($recordNum)). "'!"); }

  $fullTableName = getTableNameWithPrefix($tableName);
  $where         = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $addSelectExpr = mysql_decrypt_addSelectExpr($tableName);
  $query         = "SELECT *$addSelectExpr FROM `$fullTableName` WHERE $where LIMIT 1";
  $record        = mysql_get_query($query);

  // add _tableName key to record
  if ($record) { $record['_tableName'] = $tableName; }

  return $record;
}

// return result, for queries that return boolean values
// Usage: mysql_do($query) or dieAsCaller("MySQL Error: " .mysqli()->error. "\n");
function &mysql_do($query) {
  $result = mysqli()->query($query);
  return $result;
}


// shortcut functions for mysql_fetch
function &mysql_get_query($query, $indexedArray = false) {
  $result   = mysqli()->query($query) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $firstRow = $indexedArray ? $result->fetch_row() : $result->fetch_assoc();
  if (is_resource($result)) { mysqli_free_result($result); }
  return $firstRow;
}

// return array of matching records.  Where can contain LIMIT and other SQL
// $records = mysql_select($tableName, "createdByUserNum = '1'");
// $records = mysql_select($tableName, "createdByUserNum = '1' LIMIT 10");
// $records = mysql_select($tableName); // get all records
function mysql_select($tableName, $whereEtc = 'TRUE') {
  if (is_array($whereEtc)) { $whereEtc = mysql_where($whereEtc); }
  $fullTableName  = getTableNameWithPrefix($tableName);
  $addSelectExpr  = mysql_decrypt_addSelectExpr($tableName);
  $query          = "SELECT *$addSelectExpr FROM `$fullTableName` WHERE $whereEtc";
  $records        = mysql_select_query($query);

  // add _tableName key to records
  foreach ($records as $key => $record) { $records[$key]['_tableName'] = $tableName; }

  return $records;
}

// shortcut functions for mysql_fetch
function &mysql_select_query($query, $indexedArray = false) {
  // $isTextOutput = preg_match("|\nContent-type: text/plain|i", implode("\n", headers_list())); // future: for not htmlencoding errors if text output only
  $result = mysqli()->query($query) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $rows   = [];
  if   (!$indexedArray) { while ($row = $result->fetch_assoc()) { $rows[] = $row; } }
  else                  { while ($row = $result->fetch_row())   { $rows[] = $row; } }
  if (is_resource($result)) { mysqli_free_result($result); }
  return $rows;
}

// erase matching records
// mysql_delete($tableName, 123);                            // erase records where: num = 123
// mysql_delete($tableName, null, "createdByUserNum = '1'"); // erase records where: createdByUserNum = '1'
// mysql_delete($tableName, 123,  "createdByUserNum = '1'"); // erase records where: num = 123 AND createdByUserNum = '1'
// Note: For safety either recordnum or a where needs to be specified to delete ALL records.  No recordNum and no where does nothing
function mysql_delete($tableName, $recordNum, $customWhere = null) {
  if ($recordNum && preg_match("/[^0-9]/", strval($recordNum))) { die(__FUNCTION__ . ": second argument must be numeric or null, not '" .htmlencode(strval($recordNum)). "'!"); }
  $tableName  = getTableNameWithPrefix($tableName);
  $where      = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $delete     = "DELETE FROM `$tableName` WHERE $where";
  mysqli()->query($delete) or dieAsCaller("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  return mysqli()->affected_rows; // added in 2.13
}


// update matching records
// mysql_update($tableName, $recordNum, null,                     array('set' => '1234'));
// mysql_update($tableName, null,       "createdByUserNum = '1'", array('set' => '1234'));
// mysql_update($tableName, $recordNum, "createdByUserNum = '1'", array('set' => '1234', 'updatedDate=' => 'NOW()'));
function mysql_update($tableName, $recordNum, $customWhere, $colsToValues) {
  $tableNameWithPrefix = getTableNameWithPrefix($tableName);
  $where               = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
  $colsToValues        = mysql_encryptExpr_colsToValues($tableNameWithPrefix, $colsToValues); // encrypt columns
  $set                 = mysql_set($colsToValues, $tableNameWithPrefix);
  $update              = "UPDATE `$tableNameWithPrefix` SET $set WHERE $where";
  mysqli()->query($update) or dieAsCaller("MySQL Error: ". mysqli()->error. "\n");
}


// insert a record
// $newRecordNum = mysql_insert($tableName, $colsToValues);
// $newRecordNum = mysql_insert($tableName, array('name' => 'asdf', 'createdDate=' => 'NOW()'));
// v2.16 - added $tempDisableMysqlStrictMode option
function mysql_insert($tableName, $colsToValues, $tempDisableMysqlStrictMode = false) {

  //
  $tableNameWithPrefix = getTableNameWithPrefix($tableName);
  $colsToValues        = mysql_encryptExpr_colsToValues($tableNameWithPrefix, $colsToValues); // encrypt columns
  $set                 = mysql_set($colsToValues, $tableNameWithPrefix);
  $insertQuery         = "INSERT INTO `$tableNameWithPrefix` SET $set";

  //
  if ($tempDisableMysqlStrictMode) { mysqlStrictMode(false); }
  mysqli()->query($insertQuery) or dieAsCaller("MySQL Error: ". mysqli()->error. "\n");
  $recordNum = mysqli()->insert_id;
  if ($tempDisableMysqlStrictMode) { mysqlStrictMode(true); }

  return $recordNum;
}


// $where = _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere);
// v2.50 - $recordNum now accepts zero "0" and doesn't treat it as undefined
function _mysql_getWhereFromNumAndCustomWhere($recordNum, $customWhere) {
  if (is_array($customWhere)) { $customWhere = mysql_where($customWhere); }
  $where      = '';
  if ($recordNum != '') { $where .= "`num` = " .intval($recordNum); }
  if ($customWhere)     { $where .= ($where) ? " AND ($customWhere)" : $customWhere; }
  if ($where == '')     { $where  = 'FALSE'; } // match nothing if no where specified
  return $where;
}


// Internal function for INSERT and UPDATE queries.  Creates quotes and mysql escaped
// ... SET clause from $colsToValues array
/*
  $colsToValues = [
    'message'      => "User 'input' gets quoted and escaped!",
    'userNum'      => 1234,
    'updatedDate=' => 'NOW()', // trailing = doesn't escape or quote value
  ];
  $setClause = mysql_set($colsToValues);
  // returns: `message` = 'User \'input\' gets quoted and escaped!', `userNum` = '1234', `updatedDate` = NOW()
  
*/
function mysql_set($columnsToValues, $tableNameWithPrefix = '') {
  $mysqlSet = "\n";

  if (is_array($columnsToValues)) {
    foreach ($columnsToValues as $column => $value) {
      list($column, $dontEscapeValue) = extractSuffixChar($column, '=');

      // error checking: whitelist column chars to prevent sql injection
      if (!preg_match('/^([\w\-]+)$/i', $column)) {
        dieAsCaller(__FUNCTION__. ": Invalid column name '" .htmlencode($column). "', contains disallowed chars!");
      } 

      if ($dontEscapeValue) { $mysqlSet .= "`$column` = $value,\n"; }
      else                  { $mysqlSet .= "`$column` = '" . mysql_escape($value) . "',\n"; }
    }
  }

  //
  $mysqlSet = rtrim($mysqlSet, "\n, ");

  return $mysqlSet;
}

// convenience function for turning an array into a WHERE clause
function mysql_where($criteriaArray = null, $extraWhere = 'TRUE') {
  $where = '';
  if ($criteriaArray) {
    foreach ($criteriaArray as $fieldName => $value) {
      if (!preg_match('/^(\w+)$/', $fieldName)) { die(__FUNCTION__. ": Invalid column name '" .htmlencode($fieldName). "'!"); } // error checking: whitelist column chars to prevent sql injection
      
      // if $value is an array, use the IN operator
      if (is_array($value)) {
        $where .= "`$fieldName` IN (" . mysql_escapeCSV($value) . ") AND ";
      }
      
      // otherwise, test for equality
      else {
        $where .= mysql_escapef("`$fieldName` = ? AND ", $value);
      }
    }
  }
  $where .= $extraWhere;
  return $where;
}

// Convert all tables to support 4-byte UTF8 if they're not already using that charset
// NOTE: This may change column types.  Reference "...CONVERT TO CHARACTER SET changes the data
// type as necessary to ensure that the new column is long enough to store as many characters as
// the original column." - Source: https://dev.mysql.com/doc/refman/5.7/en/alter-table.html#alter-table-character-set
// Reference: https://dev.mysql.com/doc/refman/5.6/en/charset-unicode-conversion.html
// Usage: _mysql_upgradeTablesToUTF8mb4();
/* // To run manually (through PHP console or otherwise);
   _mysql_upgradeTablesToUTF8mb4('force');
   echo alert();
*/
function _mysql_upgradeTablesToUTF8mb4($force = false) {
  global $TABLE_PREFIX;

  // allow users to skip upgrading
  if (isset($_REQUEST['deferCharsetUpgrade'])) { return; }

  // upgrade schema tables (if required)
  $schemaTables = getSchemaTables();
  foreach ($schemaTables as $schemaTable) {
    $tableWithPrefix = getTableNameWithPrefix($schemaTable);
    $wasUpdated      = __mysql_upgradeTableToUTF8mb4($tableWithPrefix, $force);
    if ($wasUpdated) { alert("Upgrading MySQL table '$schemaTable' to utf8mb4<br>\n"); }
  }
}

// upgrade a mysql table to UTF8mb4
// Set $force to true to update charset even if table/column is already utf8mb4 (for debugging)
/* // To run manually (through PHP console or otherwise);
   $mysqlTable = 'cms_example';
   print "updated: " . __mysql_upgradeTableToUTF8mb4($mysqlTable, 'force');
*/
function __mysql_upgradeTableToUTF8mb4($mysqlTable, $force = false) {
  global $SETTINGS;

  // get table info
  $query = "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION FROM information_schema.tables
             WHERE TABLE_SCHEMA = '" .mysql_escape($SETTINGS['mysql']['database']). "' AND
                   TABLE_NAME   = '" .mysql_escape($mysqlTable). "'"; 
  $tableInfo = mysql_get_query($query);

  // get column info for non utf8mb4 columns (not all columns get automatically updated when you change the table charset/collation, so we set them explicitly as well)
  $query = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, IS_NULLABLE, EXTRA, COLUMN_DEFAULT, COLUMN_COMMENT
              FROM information_schema.columns
             WHERE TABLE_NAME = '" .mysql_escape($mysqlTable). "' AND
                   table_schema = '{$GLOBALS['SETTINGS']['mysql']['database']}' AND
                   CHARACTER_SET_NAME IS NOT NULL AND
                   COLLATION_NAME IS NOT NULL"; // check for NULL to skip numeric/date fields that don't have charset/collation
  if (!$force) { $query .= " AND\n CHARACTER_SET_NAME != 'utf8mb4' "; } // for debugging update all fields regardless of current charset
  $columnsInfo = mysql_select_query($query);
    
  // skip if no updates required
  if (!$tableInfo) { return 0; } // no mysql table found
  if (!$columnsInfo && startsWith('utf8mb4_', $tableInfo['TABLE_COLLATION'])) { return 0; } // skip if neither table or columns need updating

  // update table and column
  $query = "ALTER TABLE `$mysqlTable` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
  foreach ($columnsInfo as $colInfo) {
    $NOT_NULL = ($colInfo['IS_NULLABLE'] == 'YES') ? '' : 'NOT NULL ';
    $COMMENT  = ($colInfo['COLUMN_COMMENT'] == '') ? '' : "COMMENT '" .mysql_escape($colInfo['COLUMN_COMMENT']). "' ";
    $DEFAULT  = ($colInfo['COLUMN_DEFAULT'] == '') ? '' : "DEFAULT '" .mysql_escape($colInfo['COLUMN_DEFAULT']). "' ";
    $EXTRA    = ($colInfo['EXTRA'] == '')          ? '' : $colInfo['EXTRA']. " "; // eg: auto_increment
    $query .= ",\n     MODIFY `{$colInfo['COLUMN_NAME']}` {$colInfo['COLUMN_TYPE']} CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' {$NOT_NULL}{$EXTRA}{$DEFAULT}{$COMMENT}";
  }
  
  //
  $helpText = "TIP: Add &deferCharsetUpgrade=1 to URL to temporarily skip this upgrade and then remove and re-add any indexes in the field editor for the fields listed above.";
  mysql_do($query) or dieAsCaller("MySQL Error: " .nl2br(htmlencode(mysqli()->error . ".  MySQL Query:\n\n$query\n\n$helpText", false, false). "\n\n"));
  return 1;
}

// encrypt mysql column contents and set mysql/schema column type to binary
function mysql_encrypt_column($tableName, $colName) {
  $password               =  _mysql_encryption_key();
  $encryptedMySQLColType  = _mysql_encryption_colType();
  $encryptedSchemaColType = _mysql_encryption_colType();
  
  if (!$password) { dieAsCaller("Can't encrypt field '$colName', no encryption key in settings!"); } 
  
  // get current mysql column type
  $currentMySQLColType = getMysqlColumnType($tableName, $colName);

  // get current schema column type
  $schema = loadSchema($tableName);
  $currentSchemaColType = isset($schema[$colName]['customColumnType']) ? $schema[$colName]['customColumnType'] : '';
  
  // error checking
  if (startsWith($encryptedMySQLColType, $currentMySQLColType, false)) { dieAsCaller(__FUNCTION__ .": Can't encrypt, mysql column already set to encrypted column type ($currentMySQLColType)!"); }  // prevent double encryption, note: this assumes encrypted columns are always _mysql_encryption_colType()
  // if ($currentSchemaColType == $encryptedSchemaColType)                { dieAsCaller(__FUNCTION__ .": Can't encrypt, schema column already set to encrypted column type ($currentSchemaColType)!"); } // prevent double encryption, note: this assumes encrypted columns are always _mysql_encryption_colType()

  // update mysql column type to binary column type (encrypted data is binary)
  $query  = "  ALTER TABLE `" .mysql_escape(getTableNameWithPrefix($tableName)). "`\n";
  $query .= "MODIFY COLUMN `" .mysql_escape($colName). "`\n";
  $query .= "              $encryptedMySQLColType";
  mysql_do($query) or dieAsCaller("MySQL Error: " .htmlencode(mysqli()->error, false, false). "\n");

  // encrypt column - DO THIS AFTER changing mysql (but not schema) so we have a binary column to store data in and if anything fails error checking above above will prevent double decryption
  $query = "UPDATE `" .mysql_escape(getTableNameWithPrefix($tableName)). "` SET `$colName` = " . _mysql_encrypt_columnExpr($colName, $password);
  mysql_do($query) or dieAsCaller("MySQL Error: " .htmlencode(mysqli()->error, false, false). "\n");

  // update cms schema column type
  $schema = loadSchema($tableName);
  $schema[$colName]['_prevColType_schema'] = $currentSchemaColType;
  $schema[$colName]['_prevColType_mysql']  = $currentMySQLColType;
  $schema[$colName]['customColumnType']    = $encryptedSchemaColType;
  $schema[$colName]['isEncrypted']         = 1;   // also set by /lib/menus/database/editField_functions.php, duplicated here in case this function is called outside of CMS
  saveSchema($tableName, $schema);
}

// dencrypt mysql column contents and revert mysql/schema column type to previous format
function mysql_decrypt_column($tableName, $colName) {
  $password               = _mysql_encryption_key();
  $encryptedMySQLColType  = _mysql_encryption_colType();
  $encryptedSchemaColType = _mysql_encryption_colType();
  $plaintextMySQLColType  = 'MEDIUMTEXT'; // default, can be overwritten below 
  $plaintextSchemaColType = 'MEDIUMTEXT'; // default, can be overwritten below
    
  if (!$password) { dieAsCaller("Can't decrypt field '$colName', no encryption key in settings!"); } 
    
  // get previous mysql column type from 'comments' column field (if set)
  $schema = loadSchema($tableName);
  if (!empty($schema[$colName]['_prevColType_mysql'])) { $plaintextMySQLColType = $schema[$colName]['_prevColType_mysql']; }

  // get previous schema column type (if set)
  if (!empty($schema[$colName]['_prevColType_schema'])) { $plaintextSchemaColType = $schema[$colName]['_prevColType_schema']; }
  
  // error checking
  $currentMySQLColType = getMysqlColumnType($tableName, $colName);
  if (!startsWith($encryptedMySQLColType, $currentMySQLColType, false)) { dieAsCaller(__FUNCTION__ .": Can't decrypt, mysql column isn't an encrypted column type (got $currentMySQLColType expected $encryptedMySQLColType)!"); }  // prevent double decryption, note: this assumes encrypted columns are always _mysql_encryption_colType()
  if ($schema[$colName]['customColumnType'] != $encryptedSchemaColType) { dieAsCaller(__FUNCTION__ .": Can't decrypt, schema column isn't an encrypted column type (got {$schema[$colName]['customColumnType']} expected $encryptedSchemaColType)!"); } // prevent double decryption, note: this assumes encrypted columns are always _mysql_encryption_colType()

  // decrypt data - DO THIS AFTER changing schema so if mysql fails below error checking above above will prevent double decryption
  $query = "UPDATE `$tableName` SET `$colName` = " . _mysql_decrypt_columnExpr($colName, $password);
  mysql_do($query) or dieAsCaller("MySQL Error: " .htmlencode(mysqli()->error, false, false). "\n");

  // revert schema column type to previous (or plaintext) column type
  $schema = loadSchema($tableName);
  $schema[$colName]['customColumnType'] = $plaintextSchemaColType;
  unset($schema[$colName]['_prevColType_schema']);
  unset($schema[$colName]['_prevColType_mysql']);
  unset($schema[$colName]['isEncrypted']);  // also set by /lib/menus/database/editField_functions.php, duplicated here in case this function is called outside of CMS
  saveSchema($tableName, $schema);
  
  // revert mysql column type to previous (or plaintext) column type
  $query  = "  ALTER TABLE `" .mysql_escape(getTableNameWithPrefix($tableName)). "`\n";
  $query .= "MODIFY COLUMN `" .mysql_escape($colName). "`\n";
  $query .= "              $plaintextMySQLColType";
  mysql_do($query) or dieAsCaller("MySQL Error: " .htmlencode(mysqli()->error, false, false). "\n");
  
}


// return selectExpression for encrypted fields from schema or table, adding these to the end of a select expression
// overwrites fields previously loaded, eg: *, mysql_decrypt_addSelectExpr(...)
function mysql_decrypt_addSelectExpr($tableName, $schema = [], $tableAlias = '') {
  if (!$schema) { $schema = loadSchema($tableName); }
  if (!$schema) { return; } // skip if no schema found
  
  // get addSelectExpr
  $colNames      = array_keys(getSchemaFields($schema));
  $addSelectExpr = mysql_decrypt_getColumnsNamesOrExpr($schema, $colNames, $tableAlias, false);
  if ($addSelectExpr) { $addSelectExpr = "," . $addSelectExpr; }

  // error checking
  if ($addSelectExpr) {
    if (!_mysql_encryption_key()) { dieAsCaller("Encrypted fields found but no encryption key defined in settings!"); } 
  }
  
  return $addSelectExpr;
}

// Return column name(s) or decrypt expression(s)
// For unecrypted columns, returns: `column1`, `column2`, etc
// for encrypted columns, returns:  AES_DECRYPT(`table`.`column`, UNHEX(SHA2('password',512))) AS `column`
function mysql_decrypt_getColumnsNamesOrExpr($tablenameOrSchema, $colNames, $tableAlias = '', $outputUnencryptedColExpr = true) {
  if (!$tablenameOrSchema) { dieAsCaller("Nothing specified for \$tablenameOrSchema!"); }
  $schema            = is_array($tablenameOrSchema) ? $tablenameOrSchema : loadSchema($tablenameOrSchema);
  $tableAlias        = $tableAlias ?: getTableNameWithPrefix($schema['_tableName']);
  $colNamesOrExpr    = "";
  $hasEncryptedCols  = false;

  foreach ($colNames as $colName) {
    if (empty($schema[$colName])) { dieAsCaller("Unknown field $tableAlias.$colName!"); } 
    $schemaField     = $schema[$colName];
    $tableAndColName = "$tableAlias`.`{$schemaField['name']}";
    
    if (!empty($schemaField['isEncrypted'])) {
      $colNamesOrExpr .= "\n". _mysql_decrypt_columnExpr($tableAndColName, _mysql_encryption_key());
      $colNamesOrExpr .= " AS `{$schemaField['name']}`,";
      $hasEncryptedCols = true;
    }
    elseif ($outputUnencryptedColExpr) { // unencrypted field
      $colNamesOrExpr .= "`$tableAndColName`,";
    }
  }
  $colNamesOrExpr = rtrim($colNamesOrExpr, ", ");

  // error checking
  if ($hasEncryptedCols) {
    if (!_mysql_encryption_key()) { dieAsCaller("Encrypted fields found but no encryption key defined in settings!"); }
  }
  
  //
  return $colNamesOrExpr;
}


// return expression for getting encrypted value, eg: AES_ENCRYPT('plaintext content', UNHEX(SHA2('password',512)))
function _mysql_encrypt_valueExpr($value, $isValueEscapedAndQuoted = false) {
  $escapedPassword = mysql_escape(_mysql_encryption_key());
  $escapedValue    = $isValueEscapedAndQuoted ? $value : "'" .mysql_escape($value). "'";
  $expression      = "AES_ENCRYPT($escapedValue, UNHEX(SHA2('$escapedPassword',512)))";
  return $expression;
}

// return expression for getting encrypted value from an existing column, eg: AES_ENCRYPT(`title`, UNHEX(SHA2('password',512)))
// this is used when convert columns from plaintext to encrypted
function _mysql_encrypt_columnExpr($colName) {
  $escapedPassword = mysql_escape(_mysql_encryption_key());
  $expression      = "AES_ENCRYPT(`$colName`, UNHEX(SHA2('$escapedPassword',512)))";
  return $expression;
}

// return expression for getting unencrypted column value, eg: AES_DECRYPT(`title`, UNHEX(SHA2('password',512)))
function _mysql_decrypt_columnExpr($colName) {
  $password   = _mysql_encryption_key();
  $expression = "AES_DECRYPT(`$colName`, UNHEX(SHA2('" .mysql_escape($password). "',512)))";
  return $expression;
}

// get a list of encrypted columns for all tables or specified table
// when listing ALL tables, tablename: is prefixed to fieldname, eg: tablename:encryptedField
// $encryptedColumns = mysql_encrypt_listColumns();
function mysql_encrypt_listColumns($tableName = '') {
  $encryptedFieldList     = [];
  $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName);
  $schemaTables           = $tableNameWithoutPrefix ? [$tableNameWithoutPrefix] : getSchemaTables();
  
  foreach ($schemaTables as $schemaTable) {
    $schemaFields    = getSchemaFields($schemaTable);
    $encryptedFields = array_filter($schemaFields, function($fieldSchema) { return !empty($fieldSchema['isEncrypted']); }); 
    foreach ($encryptedFields as $encryptedField) {
      if ($tableName) { $encryptedFieldList[] = "{$encryptedField['name']}"; }
      else            { $encryptedFieldList[] = "{$encryptedField['_tableName']}:{$encryptedField['name']}"; }
    }
  }
  return $encryptedFieldList;
}

// return encryption key
function _mysql_encryption_key() {
  return $GLOBALS['SETTINGS']['mysql']['columnEncryptionKey'];
}

// column type used to store encrypted data
// Usage: _mysql_encryption_colType();
function _mysql_encryption_colType() {
  return 'MEDIUMBLOB';
}

// return $colsToValues with values replaced with encryption code so that encrypted values will be inserted into the database
// Usage: $colsToValuesWithEncryption = mysql_encryptExpr_colsToValues($colsToValues);
// Example: [title]  => 'hello world', // becomes:
//          [title=] => AES_ENCRYPT('hello world', UNHEX(SHA2('encryptionPassword',512)))
// Example: [title=] => 'NOW()', // becomes:
//          [title=] => AES_ENCRYPT(NOW(), UNHEX(SHA2('encryptionPassword',512)))
function mysql_encryptExpr_colsToValues($tableName, $colsToValues) {
  $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName);
  $encryptedColumns       = mysql_encrypt_listColumns($tableNameWithoutPrefix);  

  foreach ($colsToValues as $column => $value) {
    list($columnNameOnly, $dontEscapeValue) = extractSuffixChar($column, '=');
    $isColumnEncrypted = in_array($columnNameOnly, $encryptedColumns);
    if (!$isColumnEncrypted) { continue; } 
    
    // remove old key/value
    unset($colsToValues[$column]);
    
    // add new key/value with value including encryption code
    if (!$dontEscapeValue) { $value = "'" .mysql_escape($value). "'"; } 
    $colsToValues["$columnNameOnly="] = _mysql_encrypt_valueExpr($value, true);
  }

  return $colsToValues;
}


// Check if encrypting column is supported, encrypting some columns would cause issues (eg: num)
function mysql_isSupportedColumn($tableName, $colname) {
  $isSupported = true;
  $tableName = getTableNameWithoutPrefix($tableName);
  
  // specific tables
  if ($tableName == 'accounts') {
    $unsupportedCols = ['email','username','password','expiresDate','neverExpires','isAdmin','disabled','lastLoginDate'];
    if (in_array($colname, $unsupportedCols)) { $isSupported = false; }
  }
  
  // all other tables
  $unsupportedCols = ['num','createdDate','createdByUserNum','updatedDate','updatedByUserNum','publishDate','removeDate','neverRemove','hidden','dragSortOrder'];
  if (in_array($colname, $unsupportedCols)) { $isSupported = false; }

  //
  return $isSupported;
}
  

// upgrade all MySQL tables to InnoDB
function _mysql_convertTablesToInnoDB() {
  
  global $SETTINGS, $TABLE_PREFIX;
  
  // get supported engines
  $engines = mysql_select_query('SHOW ENGINES');
  $engines = array_groupBy($engines, 'Engine');
  
  // check for InnoDB support
  if (!isset( $engines['InnoDB'] ) || !in_array( $engines['InnoDB']['Support'], ['YES','DEFAULT'] )) {
    @trigger_error('Attempted database conversion to InnoDB failed: InnoDB not supported.');
    return false;
  }
  
  // get database name from settings
  $database = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['database'] ?? $SETTINGS['mysql']['database'];
  
  // query for MyISAM tables; limit with table prefix
  $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = '" . mysql_escape($database) . "' 
            AND `TABLE_TYPE` = 'BASE TABLE'
            AND TABLE_NAME LIKE '" . mysql_escape($TABLE_PREFIX) . "%'
            AND ENGINE = 'MyISAM'";
  $myIsamTables = mysql_select_query($query);
  
  // verify tables exist in schema (in case of similar prefix names)
  foreach ($myIsamTables as $key => $table) {
    $schemaName = getTableNameWithoutPrefix($table['TABLE_NAME']);
    if (!schemaExists( $schemaName )) { unset( $myIsamTables[ $key ] ); }
  }
  
  // did we find tables to convert?
  if (!empty( $myIsamTables )) {
    
    // check for total tables
    $totalTables = isset( $_REQUEST['totalTables'] ) ? $_REQUEST['totalTables'] : count($myIsamTables);
    
    // initialize vars
    $start_time    = microtime(true);
    $updatedTables = 0;
    
    // loop through tables
    foreach ($myIsamTables as $table) {
      
      // update table to InnoDB
      mysql_do("ALTER TABLE `" . mysql_escape($table['TABLE_NAME']) . "` ENGINE=InnoDB");
      
      $updatedTables++;
      
      // give user feedback after 2 seconds
      $end_time = microtime(true);
      if ($end_time - $start_time > 2) {
        
        // pass through total tables
        $redirectUrl = thisPageUrl(['totalTables'=>$totalTables]);
        
        // calculate updated tables
        $totalUpdated = $totalTables - count($myIsamTables) + $updatedTables;
        
        // print feedback and refresh
        print "Updated " . $totalUpdated . " of " . $totalTables . " tables from MyISAM to InnoDB.<br>Please be patient, this may take a few minutes.\n";
        print '<meta http-equiv="refresh" content="0; url=' . $redirectUrl . '">';
        exit;
      }
    }
    
    // if update completes, remove totalTables from URL
    $redirectUrl = thisPageUrl(['totalTables'=>null]);
    
    // print feedback and refresh
    Print "InnoDB update complete! Redirecting...\n";
    print '<meta http-equiv="refresh" content="1; url=' . $redirectUrl . '">';
    exit;
  }
  
}

//Estimate remaining row length on an InnoDB table
function mysql_getRemainingInnoDBRowSize($tableName) {
  $innoDbPageSizeRecord = mysql_get_query("SHOW VARIABLES WHERE Variable_name = 'innodb_page_size'");

  $innoDbPageSize = $innoDbPageSizeRecord['Value'] ?? 16000;
  $maxRowSize = floor($innoDbPageSize / 2.05);

  $columns = mysql_select_query('SHOW COLUMNS FROM `' . mysql_escape(getTableNameWithPrefix($tableName)) . "`");  
  $rowLength = count($columns) * 40;

  $remainingSize = $maxRowSize - $rowLength;
  
  return $remainingSize;
}


// eof
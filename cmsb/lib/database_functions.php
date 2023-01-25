<?php

// check if we've tried to connect to the database yet.
function mysql_isConnected($setValue = null) {
  // NOTE: This doesn't indicate if we successfully connected or are still connected,
  // ... only that we tried and got something back from mysqli.  To check if an existing
  // ... connection is still live try this code:
  // if (mysqli()->ping()) { printf ("Our connection is ok!\n"); }
  // else { printf ("Error: %s\n", mysqli()->error); }

  static $isConnected = false;
  if (!is_null($setValue)) { $isConnected = $setValue; }
  return $isConnected;
}

// set/get mysqli object
function mysqli($setValue = null) {
  // set new value
  static $obj = null;
  if (!is_null($setValue)) { $obj = $setValue; }

  // error checking
  if (!$obj && !mysql_isConnected()) { dieAsCaller("No database connection!"); }

  //
  return $obj;
}

//
function connectIfNeeded() {
  if (!mysql_isConnected()) { connectToMySQL(); }
}

//
function connectToMySQL($returnErrors = false) {
  global $SETTINGS;

  ### Get connection details
  $hostnameAndPort = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['hostname'] ?? $SETTINGS['mysql']['hostname'];
  $username        = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['username'] ?? $SETTINGS['mysql']['username'];
  $password        = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['password'] ?? $SETTINGS['mysql']['password'];
  $database        = $SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['database'] ?? $SETTINGS['mysql']['database'];
  $textOnlyErrors  = inCLI() ?: @$SETTINGS["mysql:{$_SERVER['HTTP_HOST']}"]['textOnlyErrors'] ?: $SETTINGS['mysql']['textOnlyErrors'];

  // get port from hostname - mysqli doesn't support host:port passed as one value
  $hostname = $hostnameAndPort;
  $port     = null; // defaults to ini_get("mysqli.default_port") which is usually 3306
  if (contains(':', $hostnameAndPort)) { list($hostname, $port) = explode(':', $hostnameAndPort, 2); }

  ### Connect to database

  try {
    $flags = $SETTINGS['mysql']['requireSSL'] ? MYSQLI_CLIENT_SSL : 0;     // require ssl connections
    
    // force IP for "localhost" to fix SSL connection issues on *NIX
    // disabled dec 30, 2020 - causes some servers to not be able to connect
    #if (preg_match('/^localhost$/i', $SETTINGS['mysql']['hostname'])) { $SETTINGS['mysql']['hostname'] = '127.0.0.1'; }

    mysqli_report(MYSQLI_REPORT_ALL); // catch connection exceptions instead of outputing PHP Warnings
    $mysqli = mysqli_init();
    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3); // wait up to x seconds to connect to mysql
    $isConnected = $mysqli->real_connect($hostname, $username, $password, '', $port, null, $flags);
    mysqli_report(MYSQLI_REPORT_OFF);
  } catch (Exception $e ) {
    $isConnected = false; // doesn't get defined above if exception is thrown
    //showme($e->getMessage());
  }

  if (!$isConnected || $mysqli->connect_errno) {
    $connectionError = $mysqli->connect_errno ." - ". $mysqli->connect_error;
    if ($mysqli->connect_errno == 2006 && $SETTINGS['mysql']['requireSSL']) { // 2006 = MySQL server has gone away
      $connectionError .= "<br>(Database Encryption is turned on - try turning off \"requireSSL\" in the settings.)";
    }
    if     ($returnErrors)   { return "Error connecting to MySQL:<br>\n$connectionError"; }
    elseif ($textOnlyErrors) { die("Error connecting to MySQL: $connectionError"); }
    else                     {
      $libDir = pathinfo(__FILE__, PATHINFO_DIRNAME); // viewers may be in different dirs
      include("$libDir/menus/dbConnectionError.php");
    };
    exit();
  }
  mysqli($mysqli); // save object on successful connection

  // set connected flag
  mysql_isConnected(true);
  
  // select db
  $isDbSelected = mysqli()->select_db($database);
  if (!$isDbSelected) {
    mysqli()->query("CREATE DATABASE `$database`") or die("MySQL Error: ". mysqli()->error. "\n");
    mysqli()->select_db($database) or die("MySQL Error: ". mysqli()->error. "\n");
  }


  ### check for required mysql version
  $currentVersion  = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);

  if (version_compare(REQUIRED_MYSQL_VERSION, $currentVersion, '>')) {
    $error  = "This program requires MySQL v" .REQUIRED_MYSQL_VERSION. " or newer. This server has v$currentVersion installed.<br>\n";
    $error .= "Please ask your server administrator to install MySQL v" .REQUIRED_MYSQL_VERSION. " or newer.<br>\n";
    if ($returnErrors) { return $error; }
    die($error);
  }

  ### Set Character Set
  # note: set through PHP 'set_charset' function so mysql_real_escape string() knows what charset to use. setting the charset
  # ... through mysql queries with 'set names' didn't cause mysql_client_encoding() to return a different value
  mysqli()->set_charset("utf8mb4") or die("mysqli: error setting character set utf8mb4: " .mysqli()->error. '');

  # set MySQL strict mode - http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
  mysqlStrictMode(true);

  ### set InnoDB strict mode off
  #Set off to allow for restore and upgrade of large MyISAM or older InnoDB tables
  #https://mariadb.com/kb/en/innodb-strict-mode, https://mariadb.com/kb/en/troubleshooting-row-size-too-large-errors-with-innodb
  mysqli()->query("SET SESSION innodb_strict_mode=off;");
    
  # set MySQL timezone offset
  setMySqlTimezone();

  // check accounts table exists
  if (isInstalled()) {
    $r = mysql_get_query("SHOW TABLES LIKE '{$GLOBALS['TABLE_PREFIX']}accounts'", true);
    if (!$r) { die("Error: No accounts table found.  To re-run install process remove file data/isInstalled.php."); }
  }

  ### Legacy support - connect with mysql library (for any old custom code that directly calls mysql functions)
  if (extension_loaded('mysql') && !empty($SETTINGS['advanced']['legacy_mysql_support'])) {

    ### Connect to database
    $DBH = @mysql_connect($hostnameAndPort, $username, $password);
    if (!$DBH) {
      $connectionError = mysql_error();
      if     ($returnErrors)   { return "Error connecting to legacy MySQL:<br>\n$connectionError"; }
      elseif ($textOnlyErrors) { die("Error connecting to legacy MySQL: $connectionError"); }
      else                     {
        $libDir = pathinfo(__FILE__, PATHINFO_DIRNAME); // viewers may be in different dirs
        include("$libDir/menus/dbConnectionError.php");
      };
      exit();
    }

    // Set Character Set
    mysql_set_charset("utf8mb4") or die("mysql: error setting character set utf8mb4: " .mysql_error(). '');
  
    // select db
    $isDbSelected = @mysql_select_db($database);
    if (!$isDbSelected) {
      mysql_query("CREATE DATABASE `$database`") or die("MySQL Error: ". mysql_error(). "\n");
      mysql_select_db($database) or die("MySQL Error: ". mysql_error(). "\n");
    }
  }
  ### /Legacy support
  
  //
  return '';
}



// list($indexName, $indexColList) = getIndexNameAndColumnListForField($fieldName, $columnType);
// generate an indexName and "index column clause" for use by CREATE INDEX or DROP INDEX
function getIndexNameAndColumnListForField($fieldName, $columnType) {
  
  // determine if the column type is a string type (we must supply a key length for BLOB/TEXT, we must not for non-string types)
  $stringTypes = array(
    'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB',
    'LONGBLOB', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'
  ); 
  preg_match('/(\w+)/', $columnType, $matches);
  $firstWordInColumnType = $matches[1] ?? '';
  $isStringType          = in_array(strtoupper($firstWordInColumnType), $stringTypes);
  
  
  // get index prefix length for strings
  $keyLength = ''; 
  if ($isStringType) { // To speed up ORDER BY on text fields the index prefix length must be as long as the field
    if     (preg_match('/^[\w\s*]+\((\d+)\)/', $columnType, $matches) ) { $keyLength = min(@$matches[1], 250); }  // max key length is 1000 and utf8mb4 uses 4 bytes per char.
    else                                                                { $keyLength = 16; }
    if ($keyLength) { $keyLength = "($keyLength)"; }
  }
  
  // construct return values: $indexName and $indexColList
  $indexName    = "_auto_".mysql_escape($fieldName);
  $indexColList = "(".mysql_escape($fieldName)."$keyLength)";
  return array($indexName, $indexColList);
}


//
function _sortMenusByOrder($fieldA, $fieldB) {

  // sort field meta data below sorted by "order" value
  $orderA = array_key_exists('menuOrder', $fieldA) ? $fieldA['menuOrder'] : 1000000000;
  $orderB = array_key_exists('menuOrder', $fieldB) ? $fieldB['menuOrder'] : 1000000000;
  if ($orderA < $orderB) { return -1; }
  if ($orderA > $orderB) { return 1; }
  return 0;
}


//
function getTableNameWithoutPrefix($tableName) {  // add $TABLE_PREFIX to table if it isn't there already
  
  // cache list of schema tables
  static $schemaTables = null;
  if (is_null($schemaTables)) { $schemaTables = getSchemaTables(); } 

  // remove table prefix if needed
  $isKnownSchema    = in_array($tableName, $schemaTables);
  $startsWithPrefix = startsWith($GLOBALS['TABLE_PREFIX'], $tableName);
  $removePrefix     = !$isKnownSchema && $startsWithPrefix;
  if ($removePrefix) {
    $regexp    = "/^" .preg_quote($GLOBALS['TABLE_PREFIX']). '/';
    $tableName = preg_replace($regexp, '', $tableName);
  }

  return $tableName;
}


//
function getTableNameWithPrefix($tableName) { // add $TABLE_PREFIX to table if it isn't there already
  return $GLOBALS['TABLE_PREFIX'] . getTableNameWithoutPrefix($tableName);
}


//
function getColumnTypeFor($fieldName, $fieldType, $customColumnType = '') {
  $columnType = '';

  // special case: default column type specified
  if      ($customColumnType)        { $columnType = $customColumnType; }

  // Special Fieldnames
  elseif  ($fieldName == 'num')              { $columnType = 'int(10) unsigned NOT NULL auto_increment'; }
  elseif  ($fieldName == 'createdDate')      { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; }
  elseif  ($fieldName == 'createdByUserNum') { $columnType = 'int(10) unsigned NOT NULL'; }
  elseif  ($fieldName == 'updatedDate')      { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; }
  elseif  ($fieldName == 'updatedByUserNum') { $columnType = 'int(10) unsigned NOT NULL'; }
  elseif  ($fieldName == 'dragSortOrder')    { $columnType = 'int(10) unsigned NOT NULL'; }
  // NOTE:  Other special field types don't need to be specified here because they have required
  //        ... field types in /lib/menus/default/editField_functions.php that map to the column
  //        ... types below.  We only need to specify the column types above because they are
  //        ... not available with any predefined field type.

  // otherwise return columnType for fieldType
  elseif ($fieldType == '')               { $columnType = ''; }
  elseif ($fieldType == 'none')           { $columnType = ''; }
  elseif ($fieldType == 'textfield')      { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'textbox')        { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'wysiwyg')        { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'hidden')        { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'date')           { $columnType = 'datetime NOT NULL DEFAULT "0000-00-00 00:00:00"'; } // v3.08 - Default value is required for MySQL 5.7.x or we get an error.  See "...report an error when adding a DATE or DATETIME column..." here (but occurs even before this version): https://bugs.launchpad.net/ubuntu/+source/mysql-5.7/+bug/1657989
  elseif ($fieldType == 'list')           { $columnType = 'mediumtext'; }
  elseif ($fieldType == 'checkbox')       { $columnType = 'tinyint(1) unsigned NOT NULL'; }
  elseif ($fieldType == 'upload')         { $columnType = ''; }
  elseif ($fieldType == 'separator')      { $columnType = ''; }
  elseif ($fieldType == 'tabGroup')       { $columnType = ''; }
  elseif ($fieldType == 'relatedRecords') { $columnType = ''; }

  // special fields types
  elseif ($fieldType == 'accessList')   { $columnType = ''; }
  elseif ($fieldType == 'dateCalendar') { $columnType = ''; }

  else {
    die(__FUNCTION__ . ": Field '" .htmlencode($fieldName). "' has unknown fieldType '" .htmlencode($fieldType). "'.");
  }

  return $columnType;
}


//
function getMysqlTablesWithPrefix() {
  global $TABLE_PREFIX;

  $tableNames = [];
  $escapedTablePrefix = mysql_escape($TABLE_PREFIX);
  $escapedTablePrefix = preg_replace("/([_%])/", '\\\$1', $escapedTablePrefix);  // escape mysql wildcard chars
  $result    = mysqli()->query("SHOW TABLES LIKE '$escapedTablePrefix%'") or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  while ($row = $result->fetch_row()) {
    array_push($tableNames, $row[0]);
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  return $tableNames;
}

// get mysql column names/types
function getMySqlColsAndType($escapedTableName) {
  $query      = "SHOW COLUMNS FROM `$escapedTableName`";
  $result     = mysql_select_query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $nameToType = array_column($result, 'Type', 'Field');
  return $nameToType;
}

//
function getMysqlColumnType($tableName, $fieldname) {
  if ($fieldname == '') { return ''; }

  $escapedTableName = mysql_escape($tableName);
  $escapedFieldName = mysql_escape($fieldname);
  $query            = "SHOW COLUMNS FROM `$escapedTableName` WHERE Field = '$escapedFieldName'";
  $result           = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) ."\n");
  $row              = $result->fetch_assoc();
  if (is_resource($result)) { mysqli_free_result($result); }

  $columnType       = $row['Type'] ?? '';
  if (!empty($row['Type']) && !empty($row['Null']) && $row['Null'] != 'YES') { $columnType .= " NOT NULL"; }
  if (!empty($row['Extra']))                                                 { $columnType .= " {$row['Extra']}"; }

  return $columnType;
}


//
function getTablenameErrors($tablename) {

  // get used tablenames
  static $usedTableNamesLc = [];
  static $loadedTables;
  if (!$loadedTables++) {
    foreach (getMysqlTablesWithPrefix() as $usedTablename) {
      $withoutPrefixLc = strtolower(getTablenameWithoutPrefix($usedTablename));
      array_push($usedTableNamesLc, $withoutPrefixLc);
    }
    foreach (getSchemaTables() as $usedTableName) {
      $withoutPrefixLc = strtolower($usedTablename);
      array_push($usedTableNamesLc, $withoutPrefixLc);
    }
  }


  // get reserved tablenames
  $reservedTableNamesLc = [];
  array_push($reservedTableNamesLc, 'home', 'admin', 'database', 'accounts', 'license'); // the are hard coded menu names
  array_push($reservedTableNamesLc, 'default');  // can't be used because menu folder exists with default menu files
  array_push($reservedTableNamesLc, 'all');      // can't be used because the "all" keyword gives access to all menus in user accounts

  // get error
  $error       = null;
  $tablenameLc = strtolower(getTableNameWithoutPrefix($tablename));
  if      ($tablenameLc == '')                          { $error = "No table name specified!\n"; }
  else if (!preg_match("/^[a-z]/", $tablenameLc))       { $error = "Table name must start with a letter!\n"; }
  else if (preg_match("/[A-Z]/", $tablename))           { $error = "Table name must be lowercase!\n"; }
  else if (preg_match("/[^a-z0-9\-\_]/", $tablename))   { $error = "Table name can only contain these characters (\"a-z, 0-9, - and _\")!\n"; }
  if (in_array($tablenameLc, $usedTableNamesLc))        { $error = "That table name is already in use, please choose another.\n"; }
  if (in_array($tablenameLc, $reservedTableNamesLc))    { $error = "That table name is not allowed, please choose another.\n"; }
  //
  return $error;
}


// foreach (getListOptions('tableName', 'fieldName') as $value => $label):
function getListOptions($tablename, $fieldname, $useCache = false) {

  $valuesToLabels = [];

  $schema       = loadSchema($tablename);
  $fieldSchema  = $schema[$fieldname];
  $fieldOptions = getListOptionsFromSchema($fieldSchema, null, $useCache);
  foreach ($fieldOptions as $valueAndLabel) {
    list($value, $label) = $valueAndLabel;
    $valuesToLabels[$value] = $label;
  }

  return $valuesToLabels;
}

// return MySQL WHERE clause for google style query: +word -word "multi word phrase"
// ... function always returns a value (or 1) so output can be AND'ed to existing WHERE
// $where = getWhereForKeywords($keywordString, $fieldnames);
// list($where, $andTerms, $notTerms) = getWhereForKeywords($keywordString, $fieldnames, true);
function getWhereForKeywords($keywordString, $fieldnames, $wantArray = false) {
  if (!is_array($fieldnames)) { die(__FUNCTION__ . ": fieldnames must be an array!"); }

  // parse out "quoted strings"
  $searchTerms = [];
  $quotedStringRegexp = "/([+-]?)(['\"])(.*?)\\2/";
  preg_match_all($quotedStringRegexp, $keywordString, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    list(,$plusOrMinus,,$phrase) = $match;
    $phrase = trim($phrase);
    $searchTerms[$phrase] = $plusOrMinus;
  }
  $keywordString = preg_replace($quotedStringRegexp, '', $keywordString); // remove quoted strings

  // parse out keywords
  $keywords = preg_split('/[\\s,;]+/', $keywordString);
  foreach ($keywords as $keyword) {
    $plusOrMinus = '';
    if (preg_match("/^([+-])/", $keyword, $matches)) {
      $keyword = preg_replace("/^([+-])/", '', $keyword, 1);
      $plusOrMinus = $matches[1];
    }

    $searchTerms[$keyword] = $plusOrMinus;
  }

  // create query
  $where = '';
  $conditions = [];
  $andTerms   = [];
  $notTerms   = [];
  foreach ($searchTerms as $term => $plusOrMinus) {
    if ($term == '') { continue; }

    $likeOrNotLike  = ($plusOrMinus == '-') ? "NOT LIKE" : "LIKE";
    $andOrOr        = ($plusOrMinus == '-') ? " AND " : " OR ";
    $termConditions = [];

    if ($plusOrMinus == '-') { $notTerms[] = $term; }
    else                     { $andTerms[] = $term; }

    foreach ($fieldnames as $fieldname) {
      $fieldname = trim($fieldname);
      $escapedKeyword = mysql_escape($term, true);
      $termConditions[] = "`" .mysql_escape($fieldname). "` $likeOrNotLike '%$escapedKeyword%'";
    }

    if ($termConditions) {
      $conditions[] = "(" . join($andOrOr, $termConditions) . ")\n";
    }

  }

  //
  $where = join(" AND ", $conditions);
  if (!$where) { $where = 1; }

  //
  if ($wantArray) { return array($where, $andTerms, $notTerms); }
  else            { return $where; }
}



// leave tablename blank for all tables
function backupDatabase($filenameOrPath = '', $selectedTable = '') {
  global $TABLE_PREFIX;
  $prefixPlaceholder = '#TABLE_PREFIX#_';

  set_time_limit(60*5);  // v2.51 - allow up to 5 minutes to backup/restore database
  if (!inCLI()) { session_write_close(); } // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress

  // error checking
  if ($selectedTable != '') {
    $schemaTables = getSchemaTables();
    if (preg_match("/[^\w\d\-\.]/", $selectedTable)) { die(__FUNCTION__ ." : \$selectedTable contains invalid chars! " . htmlencode($selectedTable)); }
    if (!in_array($selectedTable, $schemaTables)) { die("Unknown table selected '" .htmlencode($selectedTable). "'!"); }
  }

  // open backup file
  $hostname         = preg_replace('/[^\w\d\-\.]/', '', $_SERVER['HTTP_HOST']??'');
  if (!$filenameOrPath) {
    $filenameOrPath  = "$hostname-v{$GLOBALS['APP']['version']}-".date('Ymd-His');
    if ($selectedTable) { $filenameOrPath .= "-$selectedTable"; }
    $filenameOrPath .= ".sql.php";
  }
  $outputFilepath = isAbsPath($filenameOrPath) ? $filenameOrPath : $GLOBALS['BACKUP_DIR'] . $filenameOrPath; // v2.60 if only filename provided, use /data/backup/ as the basedir 
  $fp         = @fopen($outputFilepath, 'x');
  if (!$fp) {  // file already exists - avoid race condition
    if (!inCLI()) { session_start(); }
    return false;
  }  

  // create no execute php header
  fwrite($fp, "-- <?php die('This is not a program file.'); exit; ?>\n\n");  # prevent file from being executed

  // get tablenames to backup
  if ($selectedTable) {
    $tablenames = array( getTableNameWithPrefix($selectedTable) );
  }
  else {
    $skippedTables = backupDatabase_skippedTables();
    $allTables     = getMysqlTablesWithPrefix();
    $tablenames    = array_diff($allTables, $skippedTables);                        // remove skipped tables from list
  }

  // backup database
  foreach ($tablenames as $unescapedTablename) {
    $escapedTablename        = mysql_escape($unescapedTablename);
    $tablenameWithFakePrefix = $prefixPlaceholder . getTableNameWithoutPrefix($escapedTablename);

    // create table
    fwrite($fp, "\n--\n");
    fwrite($fp, "-- Table structure for table `$tablenameWithFakePrefix`\n");
    fwrite($fp, "--\n\n");

    fwrite($fp, "DROP TABLE IF EXISTS `$tablenameWithFakePrefix`;\n\n");

    $result = mysqli()->query("SHOW CREATE TABLE `$escapedTablename`");
    list(,$createStatement) = $result->fetch_row() or die("MySQL Error: ".htmlencode(mysqli()->error));
    $createStatement = str_replace("TABLE `$TABLE_PREFIX", "TABLE `$prefixPlaceholder", $createStatement);
    fwrite($fp, "$createStatement;\n\n");
    if ($result) { mysqli_free_result($result); }

    // create rows
    fwrite($fp, "\n--\n");
    fwrite($fp, "-- Dumping data for table `$tablenameWithFakePrefix`\n");
    fwrite($fp, "--\n\n");

    // use MYSQLI_USE_RESULT to avoid out-of-memory errors on large tables; default mode stores the whole result in memory. 
    $result = mysqli()->query("SELECT * FROM `$escapedTablename`", MYSQLI_USE_RESULT) or die("MySQL Error: ".htmlencode(mysqli()->error));
    while ($row = $result->fetch_row()) {
      $values = '';
      foreach ($row as $value) {
        if (is_null($value)) { $values .= 'NULL,'; }
        else                 { $values .= '"' .mysql_escape($value). '",'; }
      }
      $values = chop($values, ','); // remove trailing comma

      fwrite($fp, "INSERT INTO `$tablenameWithFakePrefix` VALUES($values);\n");
    }
    if ($result) { mysqli_free_result($result); }
  }

  //
  fwrite($fp, "\n");
  $result = fwrite($fp, "-- Dump completed on " .date('Y-m-d H:i:s O'). "\n\n");
  if ($result === false) { die(__FUNCTION__ . ": Error writing backup file! " .@$php_errormsg ); }
  fclose($fp) || die(__FUNCTION__ . ": Error closing backup file! " . @$php_errormsg );

  //
  if (!inCLI()) { @session_start(); } // hide error: E_WARNING: session_start(): Cannot send session cache limiter - headers already sent
  return $outputFilepath;
}

// Return a list of tables that should be skipped in the backup
// Usage: $skippedTables = backupDatabase_skippedTables();
function backupDatabase_skippedTables() {
  $skippedTables = array('_cron_log','_error_log','_outgoing_mail','_nlb_log');   // don't backup these table names
  $skippedTables = applyFilters('backupDatabase_skippedTables', $skippedTables);  // let users skip tables via plugins
  $skippedTables = array_map('getTableNameWithPrefix', $skippedTables);           // add table_prefix to all table names (if needed)
  return $skippedTables;
}

// $backupFiles = getBackupFiles_asArray();
function getBackupFiles_asArray() {
  $backupDir   = $GLOBALS['BACKUP_DIR'];
  $allFiles    = scandir($backupDir);
  $backupFiles = [];
  foreach ($allFiles as $filename) {
    if (!preg_match("/\.sql(\.php)?$/", $filename)) { continue; }
    $backupFiles[] = $filename;
  }

  return $backupFiles;
}

//
function getBackupFiles_asOptions($defaultValue = '') {

  //
  $backupFiles = getBackupFiles_asArray();
  
  // sort recently modified files first
  array_multisort(
    array_map(function($x) {
      return filemtime($GLOBALS['BACKUP_DIR'].$x);
    }, $backupFiles), SORT_DESC, $backupFiles
  );

  //
  if (!$backupFiles) { $labelsToValues = array(t('There are no backups available') => ''); }
  else               {
                        $labelsToValues = array(t('Select version to restore')      => '');
                        $labelsToValues = $labelsToValues + array_combine($backupFiles, $backupFiles);
                     }

  //
  $values      = array_values($labelsToValues);
  $labels      = array_keys($labelsToValues);
  $htmlOptions = getSelectOptions($defaultValue, $values, $labels, false);

  //
  return $htmlOptions;
}

//
function restoreDatabase($filepath, $tablename = '') {
  global $TABLE_PREFIX;
  $prefixPlaceholder = '#TABLE_PREFIX#_';

  set_time_limit(60*5);  // allow up to 5 minutes to backup/restore database
  if (!inCLI()) { session_write_close(); } // v2.51 - End the current session and store session data so locked session data doesn't prevent concurrent access to CMS by user while backup in progress

  // error checking
  if (!$filepath)                      { die("No backup file specified!"); }
  if (preg_match("/\.\./", $filepath)) { die("Backup filename contains invalid characters."); }

  ### restore backup

  // get file contents
  if (!file_exists($filepath)) { die("Backup file '$filepath' doesn't exist!"); }
  $data = file_get_contents($filepath);
  $data = preg_replace('/\r\n/s', "\n", $data);

  // remove comments
  $data = preg_replace('|(?<!\*)/\*.*?\*/|', '', $data); // remove /* comment */ style comments,  v3.14: skip leading * to prevent matching HTTP_ACCEPT: */* 
  $data = preg_replace('|^--.*?$|m', '', $data);  // remove -- single line comments

  // insert table prefix
  $data = preg_replace("/^([^`]+`)$prefixPlaceholder/m", "\\1$TABLE_PREFIX", $data);
  
  // replace MyISAM with InnoDB in CREATE TABLE statements.  Assumes only CREATE TABLE allows starting lines with: ) ENGINE=...
  $data = preg_replace('/(\n\)\s*ENGINE=)MyISAM(\s)/', '\1InnoDB\2', $data); // eg matching last line of CREATE TABLE: \n) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8;

  // insert table name (used for restoring defaultSqlData files)
  if ($tablename) {
    $data = preg_replace("/^([^`]+`[^`]+)#TABLE_NAME#(`)/m", "\\1$tablename\\2", $data);
  }

  // execute statements
  $queries = preg_split("/;\n\s*/", $data);       // nextlines are always encoded in SQL content so we don't need to worry about accidentally matching them
  foreach ($queries as $query) {
    if (!$query) { continue; } // skip blank queries
    if (!mysqli()->query($query)) {
      $recordNum = preg_match_get('/VALUES\("(\d+)",/', $query)[1];
      $error = "MySQL Error"; 
      if ($recordNum) { $error .= " (importing record num $recordNum)"; }
      $error .= ": ". htmlencode(mysqli()->error) . "\n";
      die($error);
    }
  }

  // restore session
  if (!inCLI()) { session_start(); }
}



// web based restore backup function that reloads browser incrementally to prevent timeouts and bypasses
// login system so restore can continue even when accounts table is before restored.
// Usage: 
// incrementalRestore($backupFilepath); // start restore - will error if restore is in progress
// incrementalRestore();                // resume/continue restore - called by admin.php when urlk contains: ?menu=_incrementalRestore
//
// NOTE: Currently this function is ONLY called from the Backup/Restore Menu
// NOTE: This function doesn't lock 
function incrementalRestore($backupFilepath = '', $installFromBackup = false) {
  $currentOperation  = _incrementalDbAction_currentOperation();  // load _operationInProgress
  $isNewRestore      = (bool) $backupFilepath;
  $installFromBackup = ($installFromBackup || isset($_REQUEST['installFromBackup'])) ? true : false;
  
  // error checking
  $error = ''; 
  if ($isNewRestore) {
    if ($currentOperation) { $error = "Can't start new restore, existing '" .htmlencode($currentOperation['action']). "' operation must complete first!"; } 
  }
  else {
    if     ($backupFilepath)                          { $error = "Can't continue restore, backupFilepath can only be specified when starting restore operations!"; } 
    elseif (!$currentOperation)                       { $error = "Can't continue restore, no existing restore operation in progress!"; }     
    elseif ($currentOperation['action'] != 'restore') { $error = "Can't continue restore, existing '" .htmlencode($currentOperation['action']). "' operation is in progress!"; } 
  }
  if ($error) {
    alert($error . "<br>\n");
    return;
  }

  // start operation if needed
  if (!$currentOperation) {
    $currentOperation = _incrementalDbAction_currentOperation('startRestore', $backupFilepath);
  }
  
  // get next group of MySQL statements (1+ lines that end in ;)
  list($statements, $lastFileOffset, $isComplete) = _incrementalRestore_getMoreSqlStatements($currentOperation['filePath'], $currentOperation['fileOffset']);
  $currentOperation = _incrementalDbAction_currentOperation('saveProgress', $lastFileOffset); // update fileOffset and lastUpdated time
  
  // show status
  ob_disable();
  list($percentageComplete, $elapsedTime, $actionName) = _incrementalDbAction_getProgress();
  print "Restore progress: $percentageComplete% complete, elapsed runtime: $elapsedTime<br>\n";

  // execute statements
  foreach ($statements as $query) {
    if (!$query) { continue; } // skip blank queries
    if (!mysqli()->query($query)) {
      $error = "MySQL Error"; 
      $recordNum = preg_match_get('/VALUES\("(\d+)",/', $query)[1];
      if ($recordNum) { $error .= " (importing record num $recordNum)"; }
      $error .= ": ". htmlencode(mysqli()->error) . "\n";
      die($error);
    }
  }
  
  // reload if needed
  if (!$isComplete) {  // if operation isn't complete
    print "Reloading browser...";
    $redirectUrl = "?menu=_incrementalRestore".($installFromBackup ? '&installFromBackup' : '');
    redirectBrowserToURL($redirectUrl);
    exit;
  }
  
  // restore complete?  display alert
  if ($isComplete) {
    print "Operation complete!<br>\n"; 
    _incrementalDbAction_currentOperation('finishRestore'); // remove _operationInProgress key
    createMissingSchemaTablesAndFields();                  // create any fields that weren't in the backup database but were in the schema files
    makeAllUploadRecordsRelative();

    print "Reloading browser...";
    if (!$installFromBackup) {
      $redirectUrl = "?menu=admin&action=restoreComplete&file=" . urlencode($currentOperation['filePath']);    
      redirectBrowserToURL($redirectUrl);
    }
    else {
      redirectBrowserToURL('?menu=home', true);
    }
    exit;
  }

  return true;
}

// return an array of 1+ mysql statements from the file (and update currentOperation file position and update time data)
// list($statements, $lastFileoffset) = _incrementalRestore_getMoreSqlStatements($currentOperation);
function _incrementalRestore_getMoreSqlStatements($filePath, $fileOffset) {
  $maxBytes   = 1024*1024; // 1mb, max bytes to return
  $isComplete = false;     // reached end of file
  
  // open file to last position
  $fh = fopen($filePath, 'rb');
  if (!$fh) { die(__FUNCTION__ . ": Error opening file: " .@$php_errormsg ); } 
  fseek($fh, $fileOffset); // move to last position
  
  // get next statement(s) (1+ lines that end in ;)
  $statements      = [];
  $totalBytes      = 0;
  $exceedsMaxBytes = false;
  while (!$exceedsMaxBytes && !feof($fh)) { 
    
    // get next statement
    $statement = '';
    while (($line = fgets($fh)) !== false) {
      $statement    .= $line; 
      $endsWithComma = preg_match("/;\s*\z/", $line);
      if ($endsWithComma || feof($fh)) { break; }
    }
    
    // Format data, replacement text
    $statement = preg_replace('|^\/\*(.*?)\*\/$|m', '', $statement); // remove full line /* comment */ style comments added my mysqldump, eg: /*!40101 SET NAMES utf8 */;
    $statement = preg_replace('|^--.*?$|m', '', $statement);                                                             // remove -- single line comments
    $statement = preg_replace("/^([\w\s]+`)#TABLE_PREFIX#_([^`]+`)/m", "\\1{$GLOBALS['TABLE_PREFIX']}\\2", $statement);  // insert table prefix, matches: INSERT INTO `#TABLE_PREFIX#__accesslist`, CREATE TABLE `#TABLE_PREFIX#__accesslist` 
    $statement = preg_replace("/^\s+/s", "", $statement);                                                                // remove leading whitespace
    if (preg_match("/ `#(TABLE_PREFIX|TABLE_NAME)#/", $statement, $matches)) { dieAsCaller("Unreplaced string {$matches[0]} found in data!"); } 
    // NOTE: matching for " `#" to avoid false positives within insert values, which should have escaped backticks: "\`#"
    
    // add to statements if it doesn't exceed maxBytes
    $statementLength = strlen($statement);
    $exceedsMaxBytes = ($totalBytes + $statementLength) > $maxBytes;
    if (!$statements || !$exceedsMaxBytes) {
      if ($statementLength) { // if removing comments above left us with a blank statement don't bother adding it
        $statements[]   = $statement;
        $totalBytes    += $statementLength;
      }
      $lastFileOffset = ftell($fh);
    }
  }

  // is restore complete?
  $isComplete = feof($fh) || ($lastFileOffset >= filesize($filePath));
  
  // close file
  fclose($fh);

  //
  return [$statements, $lastFileOffset, $isComplete];
}


// get/set status of operation in progress
// $currentOperation = _incrementalDbAction_currentOperation();                             // get current operation
// $currentOperation = _incrementalDbAction_currentOperation('startRestore', $filepath);    // start a new restore operation
// $currentOperation = _incrementalDbAction_currentOperation('saveProgress', $fileOffset);  // saves restore progress (before reloading)
// $currentOperation = _incrementalDbAction_currentOperation('finishRestore');              // removes _inProgress key from settings
function _incrementalDbAction_currentOperation($action = '', $actionValue = '') {
  $updateSettings = false;

  // error checking
  $validActions = ['', 'startRestore', 'saveProgress', 'finishRestore'];
  if (!in_array($action, $validActions)) { dieAsCaller("Invalid action '" .htmlencode($action). "'!"); } 

  // load currentOperation values
  $currentOperation = null;
  if (isset($GLOBALS['SETTINGS']['mysql']['_operationInProgress'])) { 
    $currentOperation = json_decode($GLOBALS['SETTINGS']['mysql']['_operationInProgress'], true);
    if (json_last_error()) { dieAsCaller("JSON Error: " .htmlencode(json_last_error_msg()). "!"); } 
  }

  // start restore
  if ($action == 'startRestore') {
    $currentOperation = [
      'action'     => 'restore',
      'fileOffset' => 0,
      'startTime'  => time(),
      'lastUpdate' => time(),
      'filePath'   => $actionValue,
    ];
    $updateSettings = true;  
  }

  // save progress
  if ($action == 'saveProgress') {
    $currentOperation['fileOffset'] = $actionValue;
    $currentOperation['lastUpdate'] = time();
    $updateSettings = true;  
  }
  
  // finish restore
  if ($action == 'finishRestore') {
    $currentOperation = null;
    $updateSettings   = true;  
  }

  // update & save values
  if ($updateSettings) {
    if (is_null($currentOperation)) { unset( $GLOBALS['SETTINGS']['mysql']['_operationInProgress'] ); } // remove key
    else { 
      $json = json_encode($currentOperation, JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR);
      if (json_last_error()) { dieAsCaller("JSON Error: " .htmlencode(json_last_error_msg()). "!"); }
      $GLOBALS['SETTINGS']['mysql']['_operationInProgress'] = $json;
    }
    saveSettings();
  }

  //
  return $currentOperation;
}


// show error if incremental operation is in progress
function _incrementalDbAction_showInProgressError_HTTP503($showResumeLink = false) { 
  if (!empty($_REQUEST['menu']) && $_REQUEST['menu'] == '_incrementalRestore') { return; } // don't show if _incrementalRestore is being called

  $currentOperation = _incrementalDbAction_currentOperation();
  if (!$currentOperation) { return; } 
  
  //
  $action = $currentOperation['action'];
  list($percentageComplete, $elapsedTime, $actionName) = _incrementalDbAction_getProgress();
  
  //
  header('HTTP/1.1 503 Service Temporarily Unavailable');
  header('Status: 503 Service Temporarily Unavailable');
  header('Retry-After: 120'); // in seconds

  print "Database $action in progress: please try again in a few minutes ($percentageComplete% complete, elapsed runtime: $elapsedTime).<br>\n";
  
  //
  $maxMin = 5;
  $elapsedMinutes = (time() - $currentOperation['lastUpdate']) / 60;
  if ($currentOperation['action'] == 'restore' && $showResumeLink && $elapsedMinutes > 3) {
    print "<p><b>CMS Admin Notice</b>: It's been over $maxMin minutes since the restore operation has run, you can manually resume the restore process by clicking here: ";
    print "<a href='?menu=_incrementalRestore'>?menu=_incrementalRestore</a>";
    print "<br>\n";
  }
  
  exit;
}

// Usage: list($percentageComplete, $elapsedTime, $actionName) = _incrementalDbAction_getProgress();
function _incrementalDbAction_getProgress() { 
  $currentOperation = _incrementalDbAction_currentOperation();

  $filesize           = filesize($currentOperation['filePath']);
  $offset             = $currentOperation['fileOffset'];
  $percentageComplete = intval(($offset / $filesize) * 100);
  $elapsedSeconds     = time() - $currentOperation['startTime'];
  $elapsedMinutes     = intval($elapsedSeconds / 60);
  $elapsedTime        = $elapsedSeconds < 60 ? "$elapsedSeconds seconds" : "$elapsedMinutes minutes";
  $actionName         = ucfirst($currentOperation['action']);
  
  //$text               = "$actionName progress: $percentageComplete% complete, elapsed runtime: $elapsedTime<br>\n";
  return [$percentageComplete, $elapsedTime, $actionName];
}


//eof
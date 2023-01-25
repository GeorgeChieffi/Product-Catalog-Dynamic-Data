<?php

  // Viewer Functions v2.0
  // The code generator no longer creates code that uses these functions but we keep them
  // here and load them to support older viewers (and some internal CMSB code that calls getRecords)
  
  # define constants and globals
  global $VIEWER_NAME, $FORM, $SETTINGS;
  $FORM = $_REQUEST; // legacy compatibility - eventually we'll remove $FORM and only use $_REQUEST

  # load modules
  $libDir = __DIR__;
  require_once "$libDir/init.php";
  require_once "$libDir/viewer_functions_old.php";
  if (file_exists("$libDir/viewer_turboCache.php")) { require_once "$libDir/viewer_turboCache.php"; }

  // error checking
  if (!isInstalled()) { die("Error: You must install the program before you can use the viewers."); }
  doAction('viewer_postinit', '2.0'); // second argument is viewer library version

  // show errors if DB operation in progress
  _incrementalDbAction_showInProgressError_HTTP503(); // show error if backup/restore in progress

/*
  list($records, $details) = getRecords(array(
    'tableName'           => 'listings', // REQUIRED, error if not specified, tableName is prefixed with $TABLE_PREFIX
    'where'               => '',         // optional, defaults to blank
    'orderBy'             => '',         // optional, defaults to $_REQUEST['orderBy'], or table sort order
    'limit'               => '',         // optional, defaults to blank
    'offset'              => '',         // optional, defaults to blank (if set but no limit then limit is set to high number as per mysql docs)
    'perPage'             => '',         // optional, number of records to show per page
    'pageNum'             => '',         // optional, page number to display defaults to $_REQUEST['page'] or 1
    'allowSearch'         => '',         // optional, defaults to yes, adds search info from query string
    'requireSearchMatch'  => '',         // optional, don't show any results unless search keyword submitted and matched
    'requireSearchSuffix' => '',         // optional, search fields must end in a suffix such as _match or _keyword, original field=value match search is ignored
    'loadUploads'         => '',         // optional, defaults to yes, loads upload array into upload field
    'loadCreatedBy'       => '',         // optional, defaults to yes, adds createdBy. fields for created user
    'loadListDetails'     => '',         // optional, defaults to yes, adds $details with prev/next page, etc info
    'loadPseudoFields'    => false,      // optional, defaults to yes, adds additional fields for :text, :label, :values, etc
    'orWhere'             => '',         // optional, adding " OR ... " to end of where clause
    'useSeoUrls'          => false,      // optional, use SEO urls, defaults to no

    'leftJoin'      => array(        // Note: leftJoins require you to use fully qualified fieldnames in WHERE and ORDER BY, such as tablename.fieldname
      'grocery_aisle' => 'aisleNum', // foreign table => local field (that matches num in foreign table)
      'brands'        => 'brandNum',
      'otherTable'    => 'ON mainTable.foreignKey = foreignTable.num',
    ),

    'ignoreHidden'            => false,  // don't hide records with hidden flag set
    'ignorePublishDate'       => false,  // don't hide records with publishDate > now
    'ignoreRemoveDate'        => false,  // don't hide records with removeDate < now
    'includeDisabledAccounts' => true,   // include records that were created by disabled accounts.  See: Admin > Section Editors > Advanced > Disabled Accounts

    'addSelectExpr'           => 'NOW() as _currentDate',   // add expression to SELECT, useful for generating pseudo-fields or calculated values
    'groupBy'                 => '',                        // optional, defaults to blank
    'having'                  => '',                        // optional, defaults to blank

    'useCache'      => true,       // use cache - requires cache plugin
    'debugSql'      => false,      // optional, display SQL query, defaults to no
  ));
*/
function getRecords($options) {
  $originalOptions = $options;  //Save original options in case we need them later.
  global $VIEWER_NAME, $TABLE_PREFIX, $SETTINGS;
  $VIEWER_NAME = "getRecords(" . @$options['tableName'] . ")";

  // error checking
  _getRecords_errorChecking($options);

  // load schema
  $schema = loadSchema($options['tableName']);
  if (!$schema) { die("$VIEWER_NAME: Couldn't load schema for '" .htmlencode($options['tableName']). "'!"); }

  // set defaults
  if (!array_key_exists('orderBy', $options))            { $options['orderBy']            = _getOrderByFromUrl($schema) ?? $schema['listPageOrder']; }
  if (!array_key_exists('loadUploads', $options))        { $options['loadUploads']        = true; }
  if (!array_key_exists('allowSearch', $options))        { $options['allowSearch']        = true; }
  if (!array_key_exists('requireSearchMatch', $options)) { $options['requireSearchMatch'] = false; }
  if (!array_key_exists('loadCreatedBy', $options))      { $options['loadCreatedBy']      = true; }
  if (!array_key_exists('loadListDetails', $options))    { $options['loadListDetails']    = true; }
  if (!array_key_exists('loadPseudoFields', $options))   { $options['loadPseudoFields']   = true; }

  $options['perPage'] = intval(@$options['perPage']);
  $options['pageNum'] = intval(@$options['pageNum']) ? max(intval($options['pageNum']), 1) : max(intval(@$_REQUEST['page']), 1); 
  $options['limit']   = intval(@$options['perPage']) ? intval($options['perPage']) : intval(@$options['limit']);
  $options['offset']  = intval(@$options['perPage']) ? (($options['pageNum']-1) * $options['perPage']) + @$options['offset'] : @$options['offset'];
  $options['offset']  = min( $options['offset'], PHP_INT_MAX ); // don't overflow php integers when casting to int later (can cause negative numbers which is harmless but causes mysql error)
  if ($options['offset'] && !$options['limit']) { $options['limit'] = 1000000; } // if offset and no limit set limit to high number as per MySQL docs

  // special behaviour if a "preview" is requested
  $isSingleMenuWithBlankWhere = !empty($schema['menuType']) && $schema['menuType'] == 'single' && @$options['where'] == ''; // single record sections have blank where statements when generated by code generator
  $hasWhereWithPreviewNum     = @$options['where'] == "num = '9999999999'"; // and regular detail viewers have: //  'where'       => whereRecordNumberInUrl(0),
  if (@$_REQUEST['preview:menu'] == @$options['tableName'] && ($isSingleMenuWithBlankWhere || $hasWhereWithPreviewNum)) { // checking for 999... prevent an inifine loop
    return _getRecords_preview($schema, $options);
  }

  // get query
  $query = _getRecords_getQuery($options, $schema);

  // load from cache
  if (@$options['useCache']) {
    if (!function_exists('turboCache_load')) { die("Error: 'useCaching' enabled but no caching plugin found!<br>Either disable 'useCaching' or install caching plugin."); }

    $results = turboCache_load($options['tableName'], $query);
    if ($results) {
      list($rows, $listDetails, $schema) = $results;
      //$listDetails['fromCache'] = 1;
      return array($rows, $listDetails, $schema);
    }
  }

  // Get records
  list($rows, $totalRecords) = _getRecords_loadResults($query, $options, $schema);

  // Add _tableName key
  foreach ($rows as $key => $record) { $rows[$key]['_tableName'] = $options['tableName']; }

  // Add pseudo-fields
  if (@$options['loadPseudoFields']) { _getRecords_addPseudoFields($rows, $options, $schema); }

  // Add uploads
  if (@$options['loadUploads'])   { _getRecords_addUploadFields($rows, $options, $schema); }

  // Add createdBy.fields to records
  // // v2.50 set fields for alternate accounts table, eg: otherTable.username
  if (@$options['loadCreatedBy'] && @$schema['createdByUserNum']) {
    $accountsTables = array_filter(array_unique(array('accounts',@$GLOBALS['WSM_ACCOUNTS_TABLE'])));
    foreach ($accountsTables as $accountsTable) {
      _getRecords_joinTable($rows, $options, $accountsTable);
    }
  }

  // Add joinTable fields
  if (@$options['joinTable']) { _getRecords_joinTable($rows, $options); }

  // get List Details
  $listDetails = [];
  if ($options['loadListDetails']) {
    $listDetails = _getRecords_getListDetails($options, count($rows), $totalRecords, $schema);
  }

  // See if pagenum is too high.  If so, call getRecords() again with an in-bounds page num
  if ($listDetails && $options['pageNum'] > $listDetails['totalPages']) {
    $originalOptions['pageNum'] = $listDetails['totalPages'];
    return getRecords($originalOptions);
  }

  // save to cache
  if (@$options['useCache']) {
    turboCache_save($options['tableName'], $query, array($rows, $listDetails, $schema));
  }

  //
  $rows = applyFilters('viewer_output_rows', $rows, $listDetails, $schema);

  //
  return array($rows, $listDetails, $schema);
}

// getRecordsCustom() - return data in the same format as getRecords but with custom SQL quuery
// ... this is an easy way to get paging working
//  list($recordList, $listMetadata) = getRecordsCustom(array(
//    'query'   => "SELECT * FROM tablename",
//    'perPage' => 5,
//    'pageNum' => 4, // defualts to $_REQUEST['page'] if not specified
//  ));
function getRecordsCustom($options) {
  $originalOptions = $options;  //Save original options in case we need them later.

  // error checking
  $errors = '';
  if (!is_array($options))                                      { die("First argument for " .__FUNCTION__. "() must be an array!<br>\n"); }
  if (!@$options['query'])                                      { $errors .= "No 'query' value specified in options!<br>\n"; }
  if (preg_match("/\bLIMIT\b/i", $options['query']))            { die("Query must not include a LIMIT as we'll be adding that."); }
  if ($errors) { die($errors); }

  // set defaults
  $options['pageNum'] = @$options['pageNum'] ? intval($options['pageNum']) : max(intval(@$_REQUEST['page']), 1);
  $options['limit']   = @$options['perPage'] ? intval($options['perPage']) : intval(@$options['limit']);
  $options['offset']  = @$options['perPage'] ? (($options['pageNum']-1) * $options['perPage']) + @$options['offset'] : @$options['offset'];
  $options['offset']  = min( $options['offset'], PHP_INT_MAX ); // don't overflow php integers when casting to int later (can cause negative numbers which is harmless but causes mysql error)
  if ($options['offset'] && !$options['limit']) { $options['limit'] = 1000000; } // if offset and no limit set limit to high number as per MySQL docs

  // add SQL_CALC_FOUND_ROWS and LIMIT to query
  if (!preg_match("/SQL_CALC_FOUND_ROWS/i", $options['query'])) { // add SQL_CALC_FOUND_ROWS if needed
    $options['query'] = preg_replace("/^\s*SELECT\b/si", "SELECT SQL_CALC_FOUND_ROWS", $options['query']);
    if (!preg_match("/SQL_CALC_FOUND_ROWS/i", $options['query'])) { die("Couldn't add SQL_CALC_FOUND_ROWS to select query"); }
  }
  $options['query'] .= ' ' . mysql_limit($options['perPage'], $options['pageNum']); // add limit to query

  // get records
  $records           = mysql_select_query( $options['query'] );

  // Add _tableName key
  if (array_key_exists('tableName', $options)) {
    foreach ($records as $key => $record) { $records[$key]['_tableName'] = $options['tableName']; }
  }

  // get meta data
  $rowCount     = count($records);
  $totalRecords = mysql_get_query("SELECT FOUND_ROWS()", true)[0];
  $fakeSchema   = array('_listPage' => "javascript:alert('You must set _listPage manually')");
  $listDetails = _getRecords_getListDetails($options, $rowCount, $totalRecords, $fakeSchema);

  // See if pagenum is too high.  If so, call getRecordsCustom() again with an in-bounds page num
  if ($listDetails && $options['pageNum'] > $listDetails['totalPages']) {
    $originalOptions['pageNum'] = $listDetails['totalPages'];
    return getRecordsCustom($originalOptions);
  }

  //
  return array($records, $listDetails);
}

//
function _getRecords_preview($schemaIn, $options) {
  global $CURRENT_USER, $schema; // these globals are used by the functions called below
  $schema = $schemaIn;
  
  // get productionRecord from database if 'num' was supplied
  $previewNum = intval( @$_REQUEST['preview:num'] );
  list($productionRecords,) = getRecords(array(
    'tableName'         => @$options['tableName'],  // should match @$_REQUEST['preview:menu']
    'where'             => "num = $previewNum",
    'ignoreHidden'      => true,
    'ignorePublishDate' => true,
    'ignoreRemoveDate'  => true,
    'loadPseudoFields'  => false,
    'loadCreatedBy'     => false,
    'allowSearch'       => false,
    'loadUploads'       => false,
  ));
  $productionRecord = @$productionRecords[0];

  // security: check access
  require_once SCRIPT_DIR . "/lib/admin_functions.php";
  require_once SCRIPT_DIR . "/lib/user_functions.php";
  require_once SCRIPT_DIR . "/lib/login_functions.php";

  $CURRENT_USER  = getCurrentUserFromCMS(); // v2.51 support preview even if website membership enabled with different accounts table and separate login

  global $hasEditorAccess; // needed by /lib/common.php _getRecordValuesFromFormInput
  $hasEditorAccess = (userSectionAccess($options['tableName']) >= 9);
  $hasAuthorAccess = (userSectionAccess($options['tableName']) >= 6);
  $userOwnsRecord  = (!$productionRecord || $CURRENT_USER['num'] == $productionRecord['createdByUserNum']); // user is creating record (no num) or is owner

  if (!$CURRENT_USER)                         { die(t("You must be logged in to use this feature!")); }
  if (!$hasAuthorAccess)                      { die(t("You don't have permissions to access this menu.")); }
  if (!$hasEditorAccess && !$userOwnsRecord)  { die(sprintf(t("You don't have permission to access these records: %s"), $productionRecord['createdByUserNum'])); }

  // build up our record from form input
  $record = _getRecordValuesFromFormInput('preview:');

  // if this is an existing record, load any fields not supplied by form input
  $record['num'] = $previewNum;
  if ($productionRecord) {
    $record = array_merge($productionRecord, $record);
  }

  // supply defaults if this is a new record
  else {
    $record = _addUndefinedDefaultsToNewRecord($record, getMySqlColsAndType(mysql_escape(getTableNameWithPrefix($options['tableName']))));
  }

  // if there was no production record available, default some fields
  if (@$schema['updatedByUserNum']) { $record['updatedByUserNum'] = $CURRENT_USER['num']; }
  if (@$schema['updatedDate']     ) { $record['updatedDate']      = date('Y-m-d H:i:s'); }

  $filenameValue       = getFilenameFieldValue($record, @$schema['_filenameFields']);
  $record['_filename'] = rtrim($filenameValue, '-');
  if    (@!$schema['_detailPage']) { $record['_link'] = "javascript:alert('Set Detail Page Url for this section in: Admin > Section Editors > Viewer Urls')"; }
  elseif(@$options['useSeoUrls'])  { $record['_link'] = PREFIX_URL.@$schema['_detailPage'] . '/' . $filenameValue . $record['num'] . "/"; }
  else                             { $record['_link'] = PREFIX_URL.@$schema['_detailPage'] . '?' . $filenameValue . $record['num']; }

  $rows = array($record);

  // Add pseudo-fields
  if (@$options['loadPseudoFields']) { _getRecords_addPseudoFields($rows, $options, $schema); }

  // Add uploads
  if (@$options['loadUploads'])   {
    // single record sections: don't use preSaveTempId so if no record has ever been created yet make sure 'num' is set to 1
    _getRecords_addUploadFields($rows, $options, $schema, $_REQUEST['preview:preSaveTempId']);
  }

  // Add createdBy.fields to records
  if (@$options['loadCreatedBy'] && @$schema['createdByUserNum']) { _getRecords_joinTable($rows, $options, 'accounts'); }

  // Add joinTable fields
  if (@$options['joinTable']) { _getRecords_joinTable($rows, $options); }

  // get List Details
  $listDetails = [];
  if ($options['loadListDetails']) {
    $listDetails = _getRecords_getListDetails($options, 1, 1, $schema);
  }

  return array($rows, $listDetails, $schema);
}


//
function _getRecords_errorChecking($options) {
  global $VIEWER_NAME;

  ### error checking
  $errors = '';
  if     (!is_array($options))     { $errors .= "First argument for getRecords() must be an array!<br>\n"; }
  elseif (!@$options['tableName']) { $errors .= "No 'tableName' value specified in options!<br>\n"; }

  // check for unknown options!
  $validOptions = array('tableName', 'where', 'orWhere', 'orderBy', 'limit', 'offset', 'perPage', 'loadUploads',
                        'allowSearch', 'requireSearchMatch', 'loadCreatedBy', 'useSeoUrls', 'loadListDetails',
                        'joinTable', 'debugSql', 'leftJoin', 'useCache', 'pageNum', 'ignoreHidden', 'ignorePublishDate',
                        'ignoreRemoveDate','requireSearchSuffix', 'includeDisabledAccounts', 'addSelectExpr', 'groupBy',
                        'having', 'loadPseudoFields');
  foreach (array_keys($options) as $optionName) {
    if (!in_array($optionName, $validOptions)) {
      $validOptionsAsCSV = implode(', ', $validOptions);
      $errors .= "Unknown option '$optionName' specified.  Valid option names are: ($validOptionsAsCSV)<br>\n";
    }
  }


  //
  if (@$options['perPage'] && (@$options['limit'] || @$options['offset'])) {
    $errors .= "Can't set both 'perPage' and 'limit' or 'offset' options at the same time, choose one!<br>\n";
  }

  //
  if ($errors) { die("$VIEWER_NAME errors<br>\n$errors"); }

}


//
function _getRecords_getQuery($options, $schema) {
  global $VIEWER_NAME, $TABLE_PREFIX;

  // create fieldlist
  $tableAlias   = $options['tableName']; // doesn't get $TABLE_PREFIX added by mysql_decrypt_addSelectExpr
  $selectFields = "`{$options['tableName']}`.*" . mysql_decrypt_addSelectExpr($options['tableName'], $schema, $tableAlias);

  // add left joins
  $LEFT_JOIN = '';
  if (@$options['leftJoin']) {

    // Fix $_REQUEST keys containing tablename
    __replaceUnderscoresInRequest($options['tableName']);

    // add qualified fieldsnames to schema
    foreach (array_keys(getSchemaFields($schema)) as $fieldname) {
      $schema["{$options['tableName']}.$fieldname"]         = $schema[$fieldname];
      $schema["{$options['tableName']}.$fieldname"]['name'] = $fieldname;
    }

    //
    foreach ($options['leftJoin'] as $foreignTable => $foreignKey) {

      /* get ON condition
      *  Modified pregmatch statment:
      *  \b= match 'ON ' anywhere in string.
      *  /i= don't match case.
      *  \s= space
      */
      if (preg_match("/\bON\s/i", $foreignKey)) { $ON_CONDITION = $foreignKey; }
      else {
        $ON_CONDITION = "ON {$options['tableName']}.`$foreignKey` = $foreignTable.num";
      }

      // add left join
      $LEFT_JOIN .= "LEFT JOIN `{$TABLE_PREFIX}{$foreignTable}` AS `$foreignTable` $ON_CONDITION\n";

      // add fieldnames to SELECT
      $foreignSchemaFields = getSchemaFields($foreignTable);
      $validFieldTypes = ['textfield','textbox','wysiwyg','date','list','checkbox'];
      $validFieldNames = ['num','createdDate','createdByUserNum','updatedDate','updatedByUserNum']; // added in v3.11
      foreach (array_keys($foreignSchemaFields) as $fieldname) {
        if (in_array(@$foreignSchemaFields[$fieldname]['type'], $validFieldTypes) || in_array($fieldname, $validFieldNames)) {
          $isEncrypted    = !empty($foreignSchemaFields[$fieldname]['isEncrypted']);
          $selectFields .= ",\n";
          $selectFields .= $isEncrypted ? _mysql_decrypt_columnExpr("$foreignTable`.`$fieldname") : "`$foreignTable`.`$fieldname`";
          $selectFields .= " AS `$foreignTable.$fieldname`";
        }

        // Fix $_REQUEST keys containing tablename
        __replaceUnderscoresInRequest($foreignTable);

        // add fieldnames to schema
        $schema["{$foreignTable}.$fieldname"]         = $foreignSchemaFields[$fieldname];
        $schema["{$foreignTable}.$fieldname"]['name'] = $fieldname;
      }
    }
  }

  // create where
  $where = @$options['where'];
  if ($options['allowSearch']) {
    $defaultWhere = _createDefaultWhereWithFormInput($schema, '', $options);
    if ($options['requireSearchMatch'] && !$defaultWhere) { $defaultWhere = "0 = 1"; } // always false

    if     (!$where)                 { $where = $defaultWhere; }
    elseif ($where && $defaultWhere) { $where = "($where) AND ($defaultWhere)"; } // v2.51 Fixed potential AND/OR precedence issue by adding () AND ()
  }
  if (@$schema['createdByUserNum'] && @$schema['_hideRecordsFromDisabledAccounts'] && !@$options['includeDisabledAccounts']) {
    if ($where) { $where .= " AND "; }
    $subquery = "SELECT num FROM `{$TABLE_PREFIX}accounts` WHERE disabled != 1 AND (expiresDate > NOW() OR neverExpires = 1)";
    $where   .= "{$options['tableName']}.createdByUserNum IN ($subquery)";
  }
  $where = _addWhereConditionsForSpecialFields($schema, $where, $options, $options['tableName']); // adds WHERE to beginning of string, do this LAST
  if (@$options['orWhere']) {
    $where = preg_replace("/^\s*WHERE\s*/i", '', $where); // remove WHERE keyword
    if ($where) { $where = "($where) OR {$options['orWhere']}"; }
    else        { $where = $options['orWhere']; }
    if ($where) { $where = "\nWHERE $where"; }
  }

  // add select expr
  if (@$options['addSelectExpr']) {
    $selectFields .= ", {$options['addSelectExpr']}";
  }

  // create query
  $query  = "SELECT SQL_CALC_FOUND_ROWS $selectFields\n";
  $query .= "FROM `$TABLE_PREFIX{$options['tableName']}` as `{$options['tableName']}`\n";
  $query .= $LEFT_JOIN;
  $query .= "$where\n";
  $query .= (@$options['groupBy']) ? " GROUP BY {$options['groupBy']}" : '';
  $query .= (@$options['having'])  ? " HAVING {$options['having']}" : '';
  $query .= (@$options['orderBy']) ? " ORDER BY {$options['orderBy']}" : '';
  if (@$options['limit'])  { $query .= "\n LIMIT "  . (int) $options['limit']; }
  if (@$options['offset']) { $query .= "\nOFFSET " . (int) $options['offset']; }
  if (@$options['debugSql']) { print "<xmp>$query</xmp>"; }

  return $query;
}


//
function _getRecords_loadResults($query, $options, $schema) {

  // execute query
  $result = mysqli()->query($query) or die(@$VIEWER_NAME . " MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $records   = [];
  while ($record = $result->fetch_assoc()) {
    $filenameValue       = getFilenameFieldValue($record, @$schema['_filenameFields']);
    $record['_filename'] = rtrim($filenameValue, '-');

    if    (@!$schema['_detailPage']) { $record['_link'] = "javascript:alert('Set Detail Page Url for this section in: Admin > Section Editors > Viewer Urls')"; }
    elseif(@$options['useSeoUrls'])  { $record['_link'] = PREFIX_URL.@$schema['_detailPage'] . '/' . $filenameValue . $record['num'] . "/"; }
    else                             { $record['_link'] = PREFIX_URL.@$schema['_detailPage'] . '?' . $filenameValue . $record['num']; }
    $record['_link'] = str_replace(' ', '%20', $record['_link']); // v2.60 : urlencode spaces so they validate
    
    // allow _detailPage links of / or ? // NOT YET IMPLEMENTED
    #$record['_link'] = preg_replace("/\?+/", '?', $record['_link']);
    #$record['_link'] = preg_replace("/\/+/", '/', $record['_link']);

    //
    array_push($records, $record);
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  // get record count
  $totalRecords = mysql_get_query("SELECT FOUND_ROWS()", true)[0];

  //
  $results = array($records, $totalRecords);
  return $results;
}

// Fix $_REQUEST keys.  Reference: PHP replaces dots with underscores: http://ca.php.net/variables.external#Dots_in_incoming_variable_names
// Note: We're doing this here because we have the foreign table names, it's used in _createDefaultWhereWithFormInput()
function __replaceUnderscoresInRequest($tablename) {
  foreach ($_REQUEST as $key => $value) {
    $newKey = preg_replace("/^($tablename)_/", '\1.', $key);
    if ($newKey != $key) {
      $_REQUEST[$newKey] = $_REQUEST[$key];
      # unset($_REQUEST[$key]); // unset original
    }
  }
}


//
function _getRecords_joinTable(&$rows, $options, $joinTable = '') {
  global $VIEWER_NAME, $TABLE_PREFIX;

  $joinTable  = $joinTable ? $joinTable : $options['joinTable'];
  $isAccounts = ($joinTable == 'accounts');
  $joinFieldA = 'createdByUserNum';
  //$joinFieldB = $isAccounts ? 'num' : 'createdByUserNum'; // v2.50 if we're joining on another field we're going to want to use num
  $joinFieldB = 'num';


  // get fieldA values as CSV
  $fieldAValues = [];
  foreach ($rows as $row) {
    if ($row[$joinFieldA]) { $fieldAValues[] = $row[$joinFieldA]; }
  }
  $fieldAValues = array_unique($fieldAValues);
  if (!$fieldAValues) { return; }
  $fieldAValuesAsCSV = implode(',', $fieldAValues);

  // load rows
  list($joinrows,,$schema) = getRecords(array(
    'tableName'               => $joinTable,
    'where'                   => "`$joinFieldB` IN ($fieldAValuesAsCSV)",
    'loadUploads'             => $options['loadUploads'],
    'allowSearch'             => false,
    'loadCreatedBy'           => false,
    'loadListDetails'         => false,
    'useSeoUrls'              => @$options['useSeoUrls'],
    'debugSql'                => @$options['debugSql'],
    'useCache'                => @$options['useCache'],
    'includeDisabledAccounts' => @$options['includeDisabledAccounts'],
  ));

  // get join rows by num
  $joinRowsByNum = [];
  foreach ($joinrows as $record) {
    $joinRowsByNum[ $record[$joinFieldB] ] = $record;
  }

  // get tableBfields
  $joinTableFields = [];
  foreach ($schema as $fieldname => $fieldSchema) {
    if (!is_array($fieldSchema)) { continue; }
    if (@$fieldSchema['type'] == 'separator') { continue; }
    $joinTableFields[] = $fieldname;
  }
  $joinTableFields[] = '_filename';
  $joinTableFields[] = '_link';

  // add tableB rows
  $fieldnamePrefix = $isAccounts ? 'createdBy' : $joinTable;
  foreach (array_keys($rows) as $index) {
    $record     = &$rows[$index];
    $joinRecord = @$joinRowsByNum[$record[$joinFieldA]];

    foreach ($joinTableFields as $fieldname) {
      if ($isAccounts && $fieldname == 'password') { continue; }
      $record["$fieldnamePrefix.$fieldname"] = @$joinRecord[$fieldname];
    }
  }
}

//
function _getRecords_addPseudoFields(&$records, $options, $schema) {
  if (!$records) { return; }

  // get source fields
  $sourceFields = [];
  $sourceFieldTypes = array('checkbox','list', 'date');
  foreach ($schema as $fieldname => $fieldSchema) {
    if (!is_array($fieldSchema) || !@$fieldSchema['type']) { continue; }
    if (!in_array($fieldSchema['type'], $sourceFieldTypes)) { continue; }
    $sourceFields[$fieldname] = $fieldSchema;
  }
  if (!$sourceFields) { return; }

  // add pseudo-fields
  foreach ($sourceFields as $fieldname => $fieldSchema) {
    $isDate       = ($fieldSchema['type'] == 'date');
    $isCheckbox   = ($fieldSchema['type'] == 'checkbox');
    $_isList      = ($fieldSchema['type'] == 'list');
    $isSingleList = $_isList && !in_array($fieldSchema['listType'], array('pulldownMulti', 'checkboxes'));
    $isMultiList  = $_isList && !$isSingleList;
    if (!$isDate && !$isCheckbox && !$isSingleList && !$isMultiList) { die(__FUNCTION__ . ": field '$fieldname' of type '{$fieldSchema['type']}' isn't a known source field!"); }

    // List Fields that "Get Options from Database" - only lookup labels for values in record-set.
    $selectedValues = [];
    if ($_isList && @$fieldSchema['optionsType'] == 'table') {
      foreach ($records as $record) {
        foreach (listValues_unpack($record[$fieldname]) as $value) {
          $selectedValues[$value] = 1;
        }
      }
      $selectedValues = array_keys($selectedValues);
      $selectedValues = array_filter($selectedValues); // remove blank entries
    }

    // get values to labels for list fields
    if ($_isList) {
      /*  Special handling for list/query with a filter field because the available values/labels can change based on other fields.
          We need to check the possible options for each record instead of pulling the options for the table as a whole.
          Since this is a different process than any other field, we get the list options and assign the labels here then continue the main loop.
      */
      if(@$fieldSchema['optionsType'] == 'query' && @$fieldSchema['filterField']) {
        $recordListOptions = [];
        foreach (array_keys($records) as $index) {
          $record = &$records[$index]; // PHP4 safe references
          $recordListOptions = getListOptionsFromSchema($fieldSchema, $record);
          $values         = array_pluck($recordListOptions, '0');
          $labels         = array_pluck($recordListOptions, '1');
          $valuesToLabels = $recordListOptions ? @array_combine($values, $labels) : [];
          $label = @$valuesToLabels[ $record[$fieldname] ];
          $record["$fieldname:label"] = $label;
        }
        unset($record);
        continue;
      }
      elseif (@$fieldSchema['optionsType'] == 'table' && empty($selectedValues)) {
        $valuesToLabels = [];
      }
      else {
        $listOptions    = getListOptionsFromSchema($fieldSchema, null, false, $selectedValues);
        $values         = array_pluck($listOptions, '0');
        $labels         = array_pluck($listOptions, '1');
        $valuesToLabels = $listOptions ? array_combine($values, $labels) : [];
      }
    }

    // add pseudo-fields
    foreach (array_keys($records) as $index) {
      $record = &$records[$index]; // PHP4 safe references
      if ($isDate) {
        $time = @strtotime( $record[$fieldname] );
        $record["$fieldname:unixtime"] = $time;
      }
      elseif ($isCheckbox) {
        $text = ($record[$fieldname]) ? @$fieldSchema['checkedValue'] : @$fieldSchema['uncheckedValue'];
        $record["$fieldname:text"] = $text;
      }
      elseif ($isSingleList) {
        $label = @$valuesToLabels[ $record[$fieldname] ];
        $record["$fieldname:label"] = $label;
      }
      elseif ($isMultiList) {
        $values = listValues_unpack($record[$fieldname]);
        $labels = [];
        foreach ($values as $value) { $labels[] = @$valuesToLabels[ $value ]; }
        $record["$fieldname:values"] = $values;
        $record["$fieldname:labels"] = $labels;
      }

      // sort keys so related fields are grouped together
      ksort($record);
      unset($record);
    }
  }
}


//
function _getRecords_addUploadFields(&$rows, $options, $schema, $preSaveTempId = null) {
  if (@!$options['loadUploads']) { return; }
  $tableName = $options['tableName'];
  $debugSql  = @$options['debugSql'];

  addUploadsToRecords($rows, $tableName, $debugSql, $preSaveTempId);
}

//
function _getRecords_getListDetails($options, $rowCount, $totalRecords, $schema) {
  global $VIEWER_NAME;
  $details = [];

  ### get list details
  $details = [];
  $details['invalidPageNum']   = !$rowCount && $options['pageNum'] > 1;
  $details['noRecordsFound']   = !$rowCount && $options['pageNum'] == 1;
  $details['page']             = $options['pageNum'];
  $details['perPage']          = @$options['perPage'];
  //$details['fromCache']        = 0;

  $details['totalPages']       = 1;
  if (@$options['perPage'] && $totalRecords > $options['perPage']) {
    $details['totalPages'] = ceil($totalRecords / $options['perPage']);
  }

  $details['totalRecords']     = $totalRecords;
  $details['pageResultsStart'] = min($totalRecords, $options['offset'] + 1);
  $details['pageResultsEnd']   = min($totalRecords, $options['offset'] + $options['limit']);

  # get page nums
  $_minOfPageNumAndTotalPages    = min($options['pageNum'], $details['totalPages']);
  $details['prevPage']       = ($_minOfPageNumAndTotalPages > 1) ? $_minOfPageNumAndTotalPages-1 : '';
  $details['nextPage']       = ($_minOfPageNumAndTotalPages < $details['totalPages']) ? $_minOfPageNumAndTotalPages+1 : '';
  if ($details['invalidPageNum']) {
    $details['prevPage'] = $details['totalPages'];
  }

  // pass query arguments forward in page links - use http_build_query to support multi-value fields, like this: ?colors[]=red&colors[]=blue&etc...
  $filteredRequest = isset($_REQUEST) ? $_REQUEST : []; // ensure we get an array, some user scripts unset($_REQUEST); on form submit to clear values
  unset( $filteredRequest['page'] );
  $extraQueryArgs    = http_build_query($filteredRequest, '', '&amp;');
  if ($extraQueryArgs) { $extraQueryArgs .= '&amp;'; }
  $extraQueryArgs    = preg_replace('/=&amp;/i', '&amp;', $extraQueryArgs); // v2.50 for query keys with no value remove trailing =, eg: ?record-title-123 instead of ?record-title-123=
  $extraQueryArgs    = preg_replace('/(%5B|\[)\d+(\]|%5D)/i', '[]', $extraQueryArgs); // square brackets get escaped as of PHP 5.1.3 - replace colors[0], colors[1] with colors[], see: http://php.net/manual/en/function.http-build-query.php#77377
  $extraPathInfoArgs = str_replace(array('=','&amp;'), array('-','/'), $extraQueryArgs);

  # get page links
  $listViewer = @$_SERVER['SCRIPT_NAME'];
  $listViewer = str_replace(' ', '%20', $listViewer); // v2.50 : url encoded spaces
  $details['prevPageLink']  = $listViewer;
  $details['nextPageLink']  = $listViewer;
  $details['firstPageLink'] = $listViewer;
  $details['lastPageLink']  = $listViewer;

  // use the same url for page 1 urls if possible, not viewer.php and viewer.php?page=1
  // see: http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=66359
  if (@$options['useSeoUrls']) {
    $details['firstPageLink'] .= ($extraPathInfoArgs) ? "/{$extraPathInfoArgs}page-1/" : '';
    $details['prevPageLink']  .= ($details['prevPage'] != 1 || $extraPathInfoArgs) ? "/{$extraPathInfoArgs}page-{$details['prevPage']}/" : '';
    $details['nextPageLink']  .= "/{$extraPathInfoArgs}page-{$details['nextPage']}/";
    $details['lastPageLink']  .= ($details['totalPages'] != 1 || $extraPathInfoArgs) ? "/{$extraPathInfoArgs}page-{$details['totalPages']}/" : '';
  }
  else {
    $details['firstPageLink'] .= ($extraQueryArgs) ? "?{$extraQueryArgs}page=1" : '';
    $details['prevPageLink']  .= ($details['prevPage'] != 1 || $extraQueryArgs) ? "?{$extraQueryArgs}page={$details['prevPage']}" : '';
    $details['nextPageLink']  .= "?{$extraQueryArgs}page={$details['nextPage']}";
    $details['lastPageLink']  .= ($details['totalPages'] != 1 || $extraQueryArgs) ? "?{$extraQueryArgs}page=" . $details['totalPages'] : '';
  }

  //
  $details['_detailPage'] = @$schema['_detailPage'] ? PREFIX_URL.$schema['_detailPage'] : '';
  $details['_listPage']   = @$schema['_listPage']   ? PREFIX_URL.$schema['_listPage'] : "javascript:alert('Set List Page Url for this section in: Admin &gt; Section Editors &gt; " .jsEncode($schema['menuName']). " &gt; Viewer Urls')"; ;
  $details['_listPage']   = str_replace(' ', '%20', $details['_listPage']); // v2.60 : urlencode spaces so they validate
  return $details;
}

//

//
function _getOrderByFromUrl(&$schema) {
  if (!@$_REQUEST['orderBy']) { return null; }

  foreach ($schema as $fieldname => $fieldSchema) {
    if (!is_array($fieldSchema)) { continue; } // fields are stored as arrays, other entries are table metadata

    // security check: Only return orderby in format of: fieldname, or fieldname DESC
    if ($_REQUEST['orderBy'] == $fieldname)        { return "$fieldname"; }
    if ($_REQUEST['orderBy'] == "$fieldname DESC") { return "$fieldname DESC"; }
  }

  return null;
}


//
function whereRecordNumberInUrl($altWhere = null) {
  $num   = getLastNumberInUrl();

  //
  $where = '';
  if (!$num && $altWhere) { $where = $altWhere; }
  else                    { $where = "num = '$num'"; }

  //
  return $where;

}


/*
  list($categoryRecords, $selectedCategory) = getCategories(array(
    'tableName'             => 'category',          // REQUIRED
    'rootCategoryNum'       => '0',                 // Only categories _below_ this one will be shown (defaults to 0 for all)
    'defaultCategory'       => 'first',             // Enter 'first', a category number, or leave blank '' for none
    'ulAttributes'          => " class='muList' ",  // add html attributes to <ul> tags
    'selectedCategoryNum'   => '',                  // defaults to getLastNumberInUrl()
    'categoryFormat'        => 'showall',           // showall, onelevel, twolevel, breadcrumb
    'loadCreatedBy'         => false,               // optional, defaults to true, adds createdBy. fields for created user
    'ignoreHidden'          => false,               // don't hide records with hidden flag set

    'ulAttributesCallback'  => 'myUlAttr',          // ADVANCED: custom function to return ul attributes, eg: myUlAttr($category);
    'liAttributesCallback'  => 'myLiAttr',          // ADVANCED: custom function to return li attributes, eg: myLiAttr($category);

    //'debugSql'            => true,
  ));
*/
function getCategories($options) {
  $VIEWER_NAME = "Category Viewer ({$options['tableName']})";

  // error checking
  $errors       = '';
  $validOptions = array('tableName', 'useSeoUrls', 'debugSql',
                        'selectedCategoryNum','categoryFormat', 'loadUploads','defaultCategory', 'rootCategoryNum','ulAttributes','ulAttributesCallback','liAttributesCallback','loadCreatedBy','ignoreHidden');
  $validFormats = array('','showall','onelevel','twolevel','breadcrumb');

  if     (!is_array($options))     { $errors .= "First argument for getRecords() must be an array!<br>\n"; }
  elseif (!@$options['tableName']) { $errors .= "No 'tableName' value specified in options!<br>\n"; }
  else { // check options are value

    $unknownOptions  = array_diff(array_keys($options), $validOptions);
    foreach ($unknownOptions as $optionName) { $errors .= "Unknown option '$optionName' specified<br>\n"; }
    if ($unknownOptions)                     { $errors .= "Valid option names are: (" .join(', ', $validOptions). ")<br>\n"; }
  }
  if (!in_array(@$options['categoryFormat'], $validFormats)) {
    $errors .= "categoryFormat must be one of: " .join(", ", $validFormats). "<br>\n";
  }
  if ($errors) { die("$VIEWER_NAME errors<br>\n$errors"); }

  // set defaults
  $options['loadUploads']   = array_key_exists('loadUploads',  $options) ? $options['loadUploads']  : true;  // default to true
  $options['ignoreHidden']  = array_key_exists('ignoreHidden', $options) ? $options['ignoreHidden'] : false; // default to false

  // create where
  $where = '';
  if (@$options['rootCategoryNum']) {
    $rootCategoryNum = (int) $options['rootCategoryNum'];
    $where = " lineage LIKE '%:$rootCategoryNum:%' AND num != '$rootCategoryNum' ";
  }

  //
  list($categoryRecords) = getRecords(array(
    'tableName'       => $options['tableName'],
    'loadUploads'     => $options['loadUploads'],
    'where'           => $where,
    'allowSearch'     => false,                       // optional, defaults to yes, adds search info from query string
    'loadCreatedBy'   => @$options['loadCreatedBy'],  // optional, defaults to yes, adds createdBy. fields for created user
    'loadListDetails' => false,                       // optional, defaults to yes, adds $details with prev/next page, etc info
    'useSeoUrls'      => @$options['useSeoUrls'],     // optional, use SEO urls, defaults to no
    'debugSql'        => @$options['debugSql'],       // optional, display SQL query, defaults to no
    'ignoreHidden'    => @$options['ignoreHidden'],   // optional, displays records flagged as hidden, defaults to false
  ));


  // set defaults (for category display)
  if (!array_key_exists('selectedCategoryNum', $options) || @$options['selectedCategoryNum'] == '') {
    if (getLastNumberInUrl())                         { $options['selectedCategoryNum'] = getLastNumberInUrl(); }
    else if (@$options['defaultCategory'] == 'first') { $options['selectedCategoryNum'] = @$categoryRecords[0]['num']; }
    else if ((int) @$options['defaultCategory'])      { $options['selectedCategoryNum'] = $options['defaultCategory']; }
  }
  if (!@$options['categoryFormat'])      { $options['categoryFormat'] = 'showall'; }

  // get selectedCategory and root depth
  $selectedCategory = [];
  $rootDepth        = 999;
  for ($i=0; $i < count($categoryRecords); $i++) {
    $category = &$categoryRecords[$i];
    if ($category['num'] == @$options['selectedCategoryNum']) { $selectedCategory = &$category; }
    if (@$category['depth'] != '') { $rootDepth = min($rootDepth, $category['depth']); }
    unset($category);
  }

  // reduce category depths if only showing part of tree (so we don't print extra <ul></ul> tags)
  if (@$options['rootCategoryNum'] && $rootDepth) {
    for ($i=0; $i < count($categoryRecords); $i++) {
      $category           = &$categoryRecords[$i];
      $category['depth'] -= $rootDepth;
    }
    unset($category);
  }

  // get category format rules
  if      ($options['categoryFormat'] == 'showall')    { $rootDepthVisible = '999'; $childDepthVisible = '999'; $parentVisibility = 'parentBranches'; }
  else if ($options['categoryFormat'] == 'onelevel')   { $rootDepthVisible = '1';   $childDepthVisible = '1';   $parentVisibility = 'parentBranches'; }
  else if ($options['categoryFormat'] == 'twolevel')   { $rootDepthVisible = '2';   $childDepthVisible = '1';   $parentVisibility = 'parentBranches'; }
  else if ($options['categoryFormat'] == 'breadcrumb') { $rootDepthVisible = '0';   $childDepthVisible = '0';   $parentVisibility = 'parentsOnly'; }
  else { die("Unknown category format '" .htmlencode($options['categoryFormat'])."'!"); }

  // get category meta-details
  $categoryNumHasChildren = [];
  $firstChildOf           = [];
  $lastChildOf            = [];
  foreach ($categoryRecords as $record) {
    $categoryNumHasChildren[ $record['parentNum'] ] = 1;
    if (!array_key_exists($record['parentNum'], $firstChildOf)) {
      $firstChildOf[ $record['parentNum'] ] = $record['num'];
    }
    $lastChildOf[ $record['parentNum'] ] = $record['num'];
  }

  // remove categories we aren't displaying
  $visibleCategoryRecords = [];
  for ($index = 0; $index < count($categoryRecords); $index++) {
    $category         = &$categoryRecords[$index];
    $showThisCategory = _categoryMatchesFormatRules($category, $selectedCategory, $rootDepthVisible, $childDepthVisible, $parentVisibility);
    if (!$showThisCategory) { continue; } // skip categories not matching categoryFormat rules

    $visibleCategoryRecords[] = &$category;
    unset($category);
  }
  $categoryRecords = $visibleCategoryRecords;

  // get displayed categories
  $displayedCategories = [];
  for ($index = 0; $index < count($categoryRecords); $index++) {
    $category = &$categoryRecords[$index];

    // add pseudo fields
    $prevCategory          = ($index > 0)                         ? $categoryRecords[$index-1] : [];
    $nextCategory          = ($index < count($categoryRecords)-1) ? $categoryRecords[$index+1] : [];
    @list($selectedRootNum) = preg_split('/:/', @$selectedCategory['lineage'],-1,PREG_SPLIT_NO_EMPTY); // root num of selected records branch
    $category['_isSelected']           = (int) ($category['num'] == @$options['selectedCategoryNum']);
    $category['_isAncestorSelected']   = $selectedCategory && preg_match("/:{$selectedCategory['num']}:/", $category['lineage']) && !$category['_isSelected'];
    $category['_isDescendantSelected'] = $selectedCategory && preg_match("/:{$category['num']}:/", $selectedCategory['lineage']) && !$category['_isSelected'];
    $category['_isSelectedBranch']     = $selectedCategory && preg_match("/:{$selectedRootNum}:/", $category['lineage']);
    $category['_isBreadcrumb']         = $category['_isDescendantSelected'] || $category['_isSelected'];

    $category['_hasParent']            = (int) ($category['parentNum'] != 0);
    $category['_hasChild']             = (int) @$categoryNumHasChildren[ $category['num'] ];

    $category['_isFirstChild']         = intval($firstChildOf[ $category['parentNum'] ] == $category['num']);
    $category['_isLastChild']          = intval($lastChildOf[ $category['parentNum'] ]  == $category['num']);
    $category['_hasSiblings']          = intval( !($category['_isFirstChild'] && $category['_isLastChild']) );

    // added in 2.10
    $category['_isSiblingSelected']    = intval( $selectedCategory && $selectedCategory['parentNum'] == $category['parentNum'] && !$category['_isSelected'] );
    $category['_isParentSelected']     = intval( $selectedCategory && $selectedCategory['num'] == $category['parentNum'] );
    $category['_isChildSelected']      = intval( $selectedCategory && $category['num'] == $selectedCategory['parentNum'] );

    $category['_listItemStart']        = _getListItemStartTags($prevCategory, $category, $nextCategory, $options);
    $category['_listItemEnd']          = _getListItemEndTags($prevCategory, $category, $nextCategory);

    //
    $displayedCategories[] = &$category;
    unset($category);
  }

  // restore category depths if only showing part of tree (done above so we don't print extra <ul></ul> tags)
  if (@$options['rootCategoryNum'] && $rootDepth) {
    for ($i=0; $i < count($categoryRecords); $i++) {
      $category           = &$categoryRecords[$i];
      $category['depth'] += $rootDepth;
    }
    unset($category);
  }

  //
  return array($displayedCategories, $selectedCategory);

}


//
function _categoryMatchesFormatRules($category, $selectedCategory, $rootDepthVisible, $childDepthVisible, $parentVisibility) {

  # $category         - category record we're testing
  # $selectedCategory  - selected category record (if any)
  # $rootDepthVisible  - always show X level(s) of subcategories from root
  # $childDepthVisible - show Y level(s) of subcategories below selected category
  # $parentVisibility  - show Z above selected categories (nothing, parentsOnly, parentBranches)

  $showThisCategory = false;

  // depth always visible from root
  if ($category['depth'] < $rootDepthVisible) { $showThisCategory = true; }

  // depth visible under child
  if ($selectedCategory) {
    $isChildOfSelected = preg_match("/:{$selectedCategory['num']}:/", $category['lineage']);
    if ($isChildOfSelected) {
      if ($category['depth'] <= (intval($selectedCategory['depth']) + $childDepthVisible)) { $showThisCategory = true; }
    }
  }

  // parentVisibility
  $selectedCategory['lineage'] = $selectedCategory['lineage'] ?? '';
  $directAncestorsOfSelected = preg_split('/:/', $selectedCategory['lineage'],-1,PREG_SPLIT_NO_EMPTY);
  $isChildOfParentOrAncestor = in_array($category['parentNum'], $directAncestorsOfSelected);
  $isDirectParentOfSelected  = preg_match("/:{$category['num']}:/", $selectedCategory['lineage']);
  if      ($parentVisibility == 'parentsOnly'    && $isDirectParentOfSelected)  { $showThisCategory = true; }
  else if ($parentVisibility == 'parentBranches' && $isChildOfParentOrAncestor) { $showThisCategory = true; }

  //
  return $showThisCategory;
}


//
function _getListItemStartTags($prevCategory, $thisCategory, $nextCategory, $options) {
  $listItemStart = '';

  //

  // start new lists
  if ($thisCategory['depth'] && $thisCategory['depth'] > @$prevCategory['depth']) {

    foreach (array_reverse(range(@$prevCategory['depth']+1, $thisCategory['depth'])) as $depth) {
      $ulAttributes = @$options['ulAttributes'];
      if (@$options['ulAttributesCallback']) {
        if (!is_object($options['ulAttributesCallback']) && !function_exists($options['ulAttributesCallback'])) { die("getCategories: Invalid function name '{$options['ulAttributesCallback']}' for ulAttributesCallback"); }
        if ($ulAttributes) { $ulAttributes .= ' '; }
        $ulAttributes .= call_user_func($options['ulAttributesCallback'], $thisCategory);
      }
      if ($ulAttributes) { $ulAttributes = ' '.$ulAttributes; }

      $paddingMultiple    = ($depth * 2);
      $openingListPadding = str_repeat("  ", $paddingMultiple);
      $listItemStart        .= "\n$openingListPadding<ul$ulAttributes>";
    }
  }

  # start this item
  $liAttributes = '';
  if (@$options['liAttributesCallback']) {
    if (!is_object($options['liAttributesCallback']) && !function_exists($options['liAttributesCallback'])) { die("getCategories: Invalid function name '{$options['liAttributesCallback']}' for liAttributesCallback"); }
    $liAttributes .= call_user_func($options['liAttributesCallback'], $thisCategory);
  }
  if ($liAttributes) { $liAttributes = ' '.$liAttributes; }

  $paddingMultiple     = ($thisCategory['depth'] * 2)+1;
  $openingItemPadding  = str_repeat("  ", $paddingMultiple);
  $listItemStart      .= "\n$openingItemPadding<li$liAttributes>";

  #
  return $listItemStart;
}


//
function _getListItemEndTags($prevCategory, $thisCategory, $nextCategory) {
  $listItemEnd = '';

  # close this item
  if (@$nextCategory['depth'] <= $thisCategory['depth']) {
    $listItemEnd .= "</li>";
  }
  # close prev lists and items
  if ($thisCategory['depth'] && $thisCategory['depth'] > @$nextCategory['depth']) {
    $thisCategoryDepth = $thisCategory['depth'];
    $nextCategoryDepth = @$nextCategory['depth']+1;

    foreach (array_reverse(range($nextCategoryDepth, $thisCategoryDepth)) as $depth) {
      $paddingMultiple     = $depth * 2;
      $closingListPadding  = str_repeat("  ", ($paddingMultiple));
      $closingItemPadding  = @str_repeat("  ", ($paddingMultiple-1));
      $listItemEnd        .= "\n$closingListPadding</ul>";
      $listItemEnd        .= "\n$closingItemPadding</li>";
    }
  }

  #
  $isLastCategory = @$nextCategory['num'] ? 0 : 1;
  if ($isLastCategory) { $listItemEnd .= "\n"; }

  #
  return $listItemEnd;
}



//
function _createDefaultWhereWithFormInput($schema, $where, $options) {
  global $VIEWER_NAME;
  if ($where != '') { return $where; } // don't set default where if it's already been specified
  $seenQueries     = [];


  
  //
  $andConditions = [];
  foreach ($_REQUEST as $name => $values) {
    if (!is_array($values)) { $values = array( $values ); } // handle single values as an array with a single value
    $values = array_filter($values, 'is_string');           // remove non-strings (eg: nested arrays) from the array
    $values = array_filter($values, 'strlen');              // remove empty string ("") values from the array (' ' and '0' will be kept)
    if (empty($values)) { continue; }                       // skip fields with empty values

    $suffixList = '_min|_max|_match|_keyword|_prefix|_query|_empty';
    if (!@$options['requireSearchSuffix']) { $suffixList .= '|'; }

    if (!preg_match("/^(.+?)($suffixList)$/", $name, $matches)) { continue; } // skip fields without search suffixes
    $fieldnamesAsCSV = $matches[1];
    $searchType      = $matches[2];
    $fieldnames      = explode(',',$fieldnamesAsCSV);
    $orConditions    = [];


    foreach ($fieldnames as $fieldnameString) {
      foreach ($values as $value) {

        // get field value for date searches.
        // don't do the date search if the suffix is actually part of the field name.
        if (preg_match("/^(.+?)_(year|month|day)$/", $fieldnameString, $matches) && !is_array(@$schema[$fieldnameString])) {
          $fieldname = $matches[1];
          $dateValue = $matches[2];
          if (!is_array(@$schema[$fieldname])) { continue; } // skip invalid fieldnames
          if     ($dateValue == 'year')        { $fieldValue = "YEAR(`$fieldname`)"; }
          elseif ($dateValue == 'month')       { $fieldValue = "MONTH(`$fieldname`)"; }
          elseif ($dateValue == 'day')         { $fieldValue = "DAYOFMONTH(`$fieldname`)"; }
          else                                 { die("unknown date value '$dateValue'!"); }
        }

        // get field value for everything else
        else {
          if (!is_array(@$schema[$fieldnameString])) { continue; } // skip invalid fieldnames
          $fieldValue  = '`' .str_replace('.', '`.`', $fieldnameString). '`'; // quote bare fields and qualified fieldnames (table.field)
          $isEncrypted = !empty($schema[$fieldnameString]['isEncrypted']);
          if ($isEncrypted) {
            $fieldValue = _mysql_decrypt_columnExpr($fieldnameString);
          }
        }

        // add conditions
        $fieldSchema = @$schema[$fieldnameString];
        $isMultiList = @$fieldSchema['type'] == 'list' && (@$fieldSchema['listType'] == 'pulldownMulti' || @$fieldSchema['listType'] == 'checkboxes');
        $valueAsNumberOnly = (float) preg_replace('/[^\d\.]/', '', $value);
        if (@$fieldSchema['type'] == 'date' && strlen($valueAsNumberOnly) == 8) { // add time to dates without it
          if ($searchType == '_min') { $valueAsNumberOnly .= '000000'; } // match from start of day
          if ($searchType == '_max') { $valueAsNumberOnly .= '240000'; } // match to end of day
        }

        if (!$fieldValue) { die("No fieldValue defined!"); }
        if      ($searchType == '_min')     { $orConditions[] = "$fieldValue+0 >= $valueAsNumberOnly"; }
        else if ($searchType == '_max')     { $orConditions[] = "$fieldValue+0 <= $valueAsNumberOnly"; }
        else if ($searchType == '_match' || $searchType == '') {
          // for single value lists: match exact column value
          // for multi value lists: match one of the multiple values or exact column value (to support single value-lists that were converted to multi-value)
          $thisCondition = "$fieldValue = '" .mysql_escape($value). "'"; 
          if ($isMultiList) { $thisCondition = "($thisCondition OR $fieldValue LIKE '%\\t" .mysql_escape($value). "\\t%')"; }
          $orConditions[] = $thisCondition;
        }
        else if ($searchType == '_keyword') { $orConditions[] = "$fieldValue LIKE '%" .mysql_escape($value, true). "%'"; }
        else if ($searchType == '_prefix')  { $orConditions[] = "$fieldValue LIKE '" .mysql_escape($value, true). "%'"; }
        else if ($searchType == '_query')   {
          if (@$seenQueries["$fieldnamesAsCSV={$value}_$searchType"]++) { continue; } // only add each query once since we're add all fields at once
          $orConditions[] = _getWhereForSearchQuery($value, $fieldnames, $schema);
        }
        else if ($searchType == '_empty')  { $orConditions[] = "$fieldValue = ''"; }
        else { die($VIEWER_NAME . ": Unknown search type '$searchType'!"); }
      }
    }

    $condition = join(' OR ', $orConditions);
    if ($condition) { $andConditions[] = "($condition)"; }

  }

  $where = join(" AND ", $andConditions);

  return $where;
}



//
function _getListDetails($options, $rowCount) {
  global $VIEWER_NAME;

  # get record count
  $totalRecords = mysql_get_query("SELECT FOUND_ROWS()", true)[0];

  # set defaults
  if ($options['pageNum'] == 0) { $options['pageNum'] = 1; }

  ### get list details
  $listDetails = [];
  $listDetails['invalidPageNum'] = !$rowCount && $options['pageNum'] > 1;
  $listDetails['noRecordsFound'] = !$rowCount && $options['pageNum'] == 1;
  $listDetails['page']           = $options['pageNum'];
  $listDetails['perPage']        = $options['perPage'];
  $listDetails['totalPages']     = ($totalRecords >= 1) ? ceil($totalRecords / $options['perPage']) : 1;
  $listDetails['totalRecords']   = $totalRecords;
  $listDetails['pageResultsStart'] = min($totalRecords, (($listDetails['page']-1) * $listDetails['perPage']) + 1);
  $listDetails['pageResultsEnd']   = min($totalRecords, $listDetails['page'] * $options['perPage']);

  # get page nums
  $_minOfPageNumAndTotalPages    = min($options['pageNum'], $listDetails['totalPages']);
  $listDetails['prevPage']       = ($_minOfPageNumAndTotalPages > 1) ? $_minOfPageNumAndTotalPages-1 : '';
  $listDetails['nextPage']       = ($_minOfPageNumAndTotalPages < $listDetails['totalPages']) ? $_minOfPageNumAndTotalPages+1 : '';
  if ($listDetails['invalidPageNum']) {
    $listDetails['prevPage'] = $listDetails['totalPages'];
  }

  # pass query arguments forward in page links
  $extraQueryArgs    = '';
  $extraPathInfoArgs = '';
  foreach ($_REQUEST as $key => $value) {
    if ($key == 'page')  { continue; } // skip page value, we set it below
    if (is_array($value)) { continue; } // skip multi value php fields
    $extraQueryArgs    .= urlencode($key) .'='. urlencode($value) . '&amp;';
    $extraPathInfoArgs .= urlencode($key) .'-'. urlencode($value) . '/';
  }

  # get page links
  $listViewer = @$_SERVER['SCRIPT_NAME'];
  if (@$options['useSeoUrls']) {
    $listDetails['prevPageLink']   = "$listViewer/{$extraPathInfoArgs}page-{$listDetails['prevPage']}/";
    $listDetails['nextPageLink']   = "$listViewer/{$extraPathInfoArgs}page-{$listDetails['nextPage']}/";
    $listDetails['firstPageLink']  = "$listViewer/{$extraPathInfoArgs}page-1/";
    $listDetails['lastPageLink']   = "$listViewer/{$extraPathInfoArgs}page-{$listDetails['totalPages']}/";
  }
  else {
    $listDetails['prevPageLink']  = "$listViewer?{$extraQueryArgs}page={$listDetails['prevPage']}";
    $listDetails['nextPageLink']  = "$listViewer?{$extraQueryArgs}page={$listDetails['nextPage']}";
    $listDetails['firstPageLink'] = "$listViewer?{$extraQueryArgs}page=1";
    $listDetails['lastPageLink']  = "$listViewer?{$extraQueryArgs}page=" . $listDetails['totalPages'];
  }

  return $listDetails;
}


// Note: This function is EXPERIMENTAL and may change in future
function searchMultipleTables($searchTables, $searchOptions) {
  global $VIEWER_NAME, $TABLE_PREFIX;
  $VIEWER_NAME = "Search Multiple Tables";

  # error checking
  if (!@$searchOptions['perPage'])  { die("$VIEWER_NAME : No perPage option specified!\n"); }

  ### create subqueries
  $subqueries = [];
  foreach ($searchTables as $tablename => $tableOptions) {
    foreach (array('viewerUrl', 'searchFields', 'titleField') as $optionName) {
      if (!@$tableOptions[$optionName]) { die("$VIEWER_NAME : No '$optionName' option specified for searchTable '" .htmlencode($tablename). "'!\n"); }
    }

    // get search fields
    $searchFieldsCSV   = '';
    foreach ($tableOptions['searchFields'] as $fieldname) {
      if ($searchFieldsCSV) { $searchFieldsCSV .= ", "; }
      $searchFieldsCSV .= "`" . mysql_escape($fieldname) . "`";
    }

    // create query
    $fullEscapedTable = mysql_escape($TABLE_PREFIX . $tablename);
    $schema           = loadSchema($tablename);
    $where            = _addWhereConditionsForSpecialFields($schema, '', $searchOptions);
    
    if (!empty( $tableOptions['customWhere'] )) {
      if ($where) { $where .= "AND ".$tableOptions['customWhere']; }
      else { $where = 'WHERE '.$tableOptions['customWhere']; }
    }
    
    $subquery         = "SELECT '$tablename' as `tablename`, num, `{$tableOptions['titleField']}` as `_title`, ";
    if (@$tableOptions['summaryField']) { $subquery .= "`{$tableOptions['summaryField']}` as `_summary`, "; }
    else                                { $subquery .= "'' as `_summary`, "; }
    foreach (range(1,10) as $num) {
      $fieldname = "field$num";
      if (@$tableOptions["field$num"]) { $subquery .= "`{$tableOptions[$fieldname]}` as `$fieldname`, "; }
      else                             { $subquery .= "'' as `$fieldname`, "; }
    }
    $subquery        .= "CONCAT_WS('\\t', $searchFieldsCSV) as _content FROM `$fullEscapedTable` $where\n";

    $subqueries[] = $subquery;
  }

  # create query
  $schema = array('_content' => []); // allow this field to be searched in _getWhereForSearchQuery
  $where  = _getWhereForSearchQuery($searchOptions['keywords'], array('_content'), $schema);
  $query       = "SELECT SQL_CALC_FOUND_ROWS * FROM (\n  " .implode('  UNION ', $subqueries). ") as combinedTable\n";
  if ($where) { $query .= "WHERE $where"; }
  if (@$searchOptions['orderBy']) { $query .= " ORDER BY ".$searchOptions['orderBy'] ." "; }
  $query      .= mysql_limit($searchOptions['perPage'], @$_REQUEST['page']);

  #

  ## execute query
  $rows = [];
  if ($searchOptions['keywords'] && $where) {
    if (@$searchOptions['debugSql']) { print "<xmp>$query</xmp>"; }
    $result     = mysqli()->query($query) or die("$VIEWER_NAME: MySQL Error: ". htmlencode(mysqli()->error) . "\n");

    while ($record = $result->fetch_assoc()) {
      $detailUrl       = $searchTables[$record['tablename']]['viewerUrl'];
      $filenameValue   = ''; // not working yet... getFilenameFieldValue($record, $searchTables[$record['tablename']]['filenameFields']);
      $useSeoUrls      = false;
      $link            = _getLink($detailUrl, $filenameValue, $record['num'], $useSeoUrls);

      $record['_title']   = $record['_title'];
      $record['_summary'] = strip_tags($record['_summary']);
      $record['_link']    = $link;

      array_push($rows, $record);
    }


  }

  $searchOptions['pageNum'] = @$_REQUEST['page'];
  $listDetails = _getListDetails($searchOptions, count($rows));

  //
  return array($rows, $listDetails);
}


// returns page number from url.  matches: view.php/anythinghere-####.html
function getLastNumberInUrl($defaultNum = null) {
  $recordNum = 0;
  $urlDataFields = array(@$_SERVER["PATH_INFO"], @$_SERVER["QUERY_STRING"]);
  foreach ($urlDataFields as $urlData) {
    $urlData = $urlData ?? ''; // cannot pass null subject to preg_replace() as of PHP 8.1
    $urlData = preg_replace("/^\?/", "&", $urlData); // replace ?arg=123 with &arg=123 so replacements below catch first arguments as well, eg: ?gclid=123

    // 3rd party websites sometimes add their own field-value pairs to the query string.  We remove them here in case they contain trailing numbers
    $removeFields  = array(
      'utm_source','utm_medium','utm_term','utm_content','utm_campaign',          // google utm names: http://www.google.com/support/googleanalytics/bin/answer.py?answer=55578
      'fb_source','fb_action_ids','fb_action_types','fb_ref','fb_aggregation_id', // facebook parameter names: https://developers.facebook.com/docs/technical-guides/opengraph/link-parameters/
      'action_object_map','action_type_map','action_ref_map','fbclid',            // additional facebook parameters
      'gclid',                                                                    // used by Google AdWords auto-tagging: https://support.google.com/analytics/answer/1033981
    );
    $removeFields = applyFilters('getLastNumberInUrl_removeFields', $removeFields);
    foreach ($removeFields as $removeField) { $urlData = preg_replace("/&$removeField=[^&]*/", '', $urlData); }

    // remove page=# so we don't get that by accident
    $urlData = preg_replace("/\bpage=\d+\b/", '', $urlData);

    //
    if (preg_match("/\D*(\d+)(\D+)?$/", $urlData, $matches)) {
      $recordNum = $matches[1];
      break;
    }
  }

  //
  if (!$recordNum && $defaultNum) { $recordNum = $defaultNum; }

  //
  return $recordNum;
}



//
function _addWhereConditionsForSpecialFields($schema, $extraConditions, $options = [], $tableName = '') {
  $where            = '';
  $conditions       = [];
  $tableNameDot     = $tableName ? "`$tableName`." : '';

  if ($extraConditions)        { array_push($conditions, "($extraConditions)"); }
  if (!@$options['ignoreHidden'] && @$schema['hidden']) {
    array_push($conditions, "{$tableNameDot}hidden = 0");
  }
  if (!@$options['ignorePublishDate'] && @$schema['publishDate']) {
    array_push($conditions, "{$tableNameDot}publishDate <= NOW()");
  }
  if (!@$options['ignoreRemoveDate'] && @$schema['removeDate'])  {
    $thisCondition  = "({$tableNameDot}removeDate >= NOW() || {$tableNameDot}removeDate = '0000-00-00 00:00:00')";  // NULL end date or future end date
    if (@$schema['neverRemove']) { $thisCondition .= " OR {$tableNameDot}neverRemove = 1"; } // never expires checked
    array_push($conditions, "($thisCondition)");
  }
  if ($conditions) {
    $where = " WHERE " . implode(" AND ", $conditions) . " ";
  }

  return $where;
}


// return MySQL WHERE clause for google style query: +word -word "multi word phrase"
function _getWhereForSearchQuery($query, $fieldnames, $schema = null) {

  // error checking
  if (!is_array($fieldnames)) { die(__FUNCTION__ . ": fieldnames must be an array!"); }

  // parse out "quoted strings"
  $searchTerms = [];
  $quotedStringRegexp = "/([+-]?)(['\"])(.*?)\\2/";
  preg_match_all($quotedStringRegexp, $query, $matches, PREG_SET_ORDER);
  foreach ($matches as $match) {
    list(,$plusOrMinus,$quote,$phrase) = $match;
    if (!$quote) { $phrase = trim($phrase); }
    $searchTerms[$phrase] = $plusOrMinus;
  }
  $query = preg_replace($quotedStringRegexp, '', $query); // remove quoted strings

  // parse out keywords
  $keywords = preg_split('/[\\s,;]+/u', $query);
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
  foreach ($searchTerms as $term => $plusOrMinus) {
    if ($term == '') { continue; }
    $likeOrNotLike  = ($plusOrMinus == '-') ? "NOT LIKE" : "LIKE";
    $andOrOr        = ($plusOrMinus == '-') ? " AND " : " OR ";
    $termConditions = [];

    foreach ($fieldnames as $fieldname) {
      if ($schema && !is_array(@$schema[$fieldname])) { continue; } // fields are stored as arrays, other entries are table metadata
      $fieldname = trim($fieldname);
      $escapedKeyword = mysql_escape($term, true);
      $quotedFieldname  = '`' . str_replace('.', '`.`', $fieldname) . '`';
      $termConditions[] = "$quotedFieldname $likeOrNotLike '%$escapedKeyword%'";
    }

    if ($termConditions) {
      $conditions[] = "(" . join($andOrOr, $termConditions) . ")\n";
    }

  }

  //
  $where = join(" AND ", $conditions);
  return $where;
}


//
function _getLink($detailUrl, $filenameFieldValue, $recordNum, $useSeoUrls) {
  global $VIEWER_NAME;

  // error checking
  //

  // define vars
  $link          = '';

  // add http://domain for viewer
  $includesProto = preg_match("!^\w+://!", $detailUrl);
  if (!$includesProto) {
    $link   .= isHTTPS() ? 'https://' : 'http://';
    $link   .= $_SERVER['HTTP_HOST'];
  }

  //
  $link .= PREFIX_URL;
  
  // add url path (without http, domain, query or PATH_INFO) for viewer
  $relativeUrl = !preg_match("!^(/|\w+://)!", $detailUrl);
  if ($relativeUrl) {
    $thisDirUrl = dirname(@$_SERVER['SCRIPT_NAME']) ."/". $detailUrl;
    $thisDirUrl = preg_replace("|^[\\\\/]+|", "/", $thisDirUrl); // remove multiple leading slashes (and replace \ returned by dirname on windows in root)
    $link      .= $thisDirUrl;
  }
  else {
    $link .= $detailUrl;
  }


  // add url delimiter, filenameField, and record number
  $link .= $useSeoUrls ? '/' : '?';
  $link .= $filenameFieldValue;
  $link .= $recordNum;

  //
  return $link;
}

function getFilenameFieldValue($record, $filenameFields) {
  global $VIEWER_NAME;
  $filenameValue = '';
  $filenameFields = $filenameFields ?? [];

  // convert string to array
  if (!is_array($filenameFields)) {
    $filenameFields = preg_split("/\s*,\s*/", $filenameFields);
  }

  // error checking
  foreach ($filenameFields as $fieldname) {
    if ($fieldname == '') { continue; }
    if (!array_key_exists($fieldname, $record)) { die("$VIEWER_NAME: Unknown field '" .htmlencode($fieldname). "' in filenameFields or titleField options!"); }
  }

  // get first defined field value
  $fieldValue    = '';
  foreach ($filenameFields as $fieldname) {
    if (@$record[$fieldname] == '') { continue; }
    $fieldValue    = @$record[$fieldname];
    $filenameValue = $fieldValue;
    break;
  }
  $filenameValue = preg_replace('/[^a-z0-9\.\-\_]+/i', '-', $filenameValue);
  $filenameValue = preg_replace("/(^-+|-+$)/", '', $filenameValue); # remove leading and trailing underscores
  if ($filenameValue) { $filenameValue .= "-"; }

  //
  $filenameValue = applyFilters('viewer_link_field_content', $filenameValue, $fieldValue, $record);

  //
  return $filenameValue;
}

// Supported options: tableName (required), recordNum (optional), and orderBy (optional, defaults to schema listPageOrder)
// list($prevRecord, $nextRecord, $firstRecord, $lastRecord) = getPrevAndNextRecords(array(
//   'tableName' => 'news',
//   'recordNum' => $record['num'],
//   'where'     => '1', // optional - defaults to all records in section
//   'orderBy'   => 'createdDate',  // optional - defaults to schema listPageOrder
// ));
function getPrevAndNextRecords($options) {
  global $TABLE_PREFIX;

  // error checking
  $errors = '';
  if (!@$options['tableName']) { $errors .= "No 'tableName' value specified in options!<br>\n"; }
  if ($errors) { die(__FUNCTION__ . ": $errors"); }

  $tableSchema    = loadSchema($options['tableName']);
  $mysqlTableName = mysql_escape($TABLE_PREFIX . $options['tableName']);
  $targetNum      = @$options['recordNum'] ? mysql_escape($options['recordNum']) : 0;
  $orderBy        = @$options['orderBy'] ? $options['orderBy'] : $tableSchema['listPageOrder'];

  // set inital mysql variables
  $query = "SELECT @lastSeenNum:=0, @prevNum:=0, @nextNum:=0, @firstNum:=0, @lastNum:=0, @prevNumSet:=0, @foundTarget:=0";
  if (@$options['debugSql']) { print "<xmp>$query</xmp>"; }
  mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error). "\n");

  // get mysql to figure out which nums are prev, next, first, and last
  // NOTE: "The order of evaluation for expressions involving user variables is undefined..." See: http://dev.mysql.com/doc/refman/5.0/en/user-variables.html
  $query  = "SELECT \n";
  $query .= "  IF(@firstNum, NULL, @firstNum:=num),\n";                                                     // get firstRecordNum
  $query .= "  @lastNum := num,\n";                                                                         // get lastRecordNum
  $query .= "  IF(num='$targetNum', (@foundTarget:=1) & (@prevNum:=@lastSeenNum), @lastSeenNum:=num),\n";   // get prevRecordNum (Note that using AND here instead of & caused an issue with unexpected evalutation of the assignment operators on a Windows/MySQL 5.5.33 server)
  $query .= "  IF(@foundTarget=1 AND num !='$targetNum' AND @nextNum = 0, @nextNum := num, null)\n";        // get nextRecordNum
  $query .= "FROM `$mysqlTableName` \n";
  if (@$options['where']) { $query .= "WHERE {$options['where']} \n"; }
  $query .= "ORDER BY $orderBy  \n";
  if (@$options['debugSql']) { print "<xmp>$query</xmp>"; }
  mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error). "\n");

  // load our calculated nums
  $query = "SELECT @prevNum as 'prevRecordNum', @nextNum as 'nextRecordNum', @firstNum as 'firstRecordNum', @lastNum as 'lastRecordNum'";
  if (@$options['debugSql']) { print "<xmp>$query</xmp>"; }
  $row = mysql_get_query($query);

  // load records matching returned nums
  $numsToGet = array_values(array_filter($row));
  array_push($numsToGet, 0);
  list($records, $metaData) = getRecords(array(
    'tableName'   => $options['tableName'],
    'where'       => "num IN (" . implode(',', $numsToGet) . ")",
    'allowSearch' => false,
    'debugSql'    => @$options['debugSql'],
  ));
  $recordsByNum = array_combine(array_pluck($records, 'num'), $records);

  // package up output
  $firstRecord = $row['firstRecordNum'] ? @$recordsByNum[ $row['firstRecordNum'] ] : [];
  $prevRecord  = $row['prevRecordNum']  ? @$recordsByNum[ $row['prevRecordNum']  ] : [];
  $nextRecord  = $row['nextRecordNum']  ? @$recordsByNum[ $row['nextRecordNum']  ] : [];
  $lastRecord  = $row['lastRecordNum']  ? @$recordsByNum[ $row['lastRecordNum']  ] : [];
  return array($prevRecord, $nextRecord, $firstRecord, $lastRecord);

}

// Note: This function is EXPERIMENTAL and may change in future
function incrementCounterField($tablename, $fieldname, $recordNumber) {
  global $VIEWER_NAME;

  // error checking
  if (!$tablename)    { die(__FUNCTION__ . ": No 'tablename' value specified!"); }
  if (!$fieldname)    { die(__FUNCTION__ . ": No 'fieldname' value specified!"); }
  if (!$recordNumber) { die(__FUNCTION__ . ": No 'recordNumber' value specified!"); }

  // update counter
  $escapedTableName = mysql_escape(getTableNameWithPrefix($tablename));
  $query  = "UPDATE `$escapedTableName` SET `$fieldname` = IFNULL(`$fieldname`,0) + 1";
  $query .= " WHERE `num` = '" .mysql_escape($recordNumber). "'";
  $result = mysqli()->query($query);
  if (!$result) { die(__FUNCTION__ . " MySQL Error: ". htmlencode(mysqli()->error) . "\n"); }
  if (!mysqli()->affected_rows) {
    die(__FUNCTION__ . ": Couldn't find record '" .htmlencode($recordNumber). "'!");
  }

}


// return an array of list labels
function getListLabels($tableName, $fieldName, $fieldValue) {
  $values = listValues_unpack($fieldValue);

  // load values to labels
  static $valuesToLabels;
  if (!@$valuesToLabels[$tableName][$fieldName]) {
    $valuesToLabels[$tableName][$fieldName] = getListOptions($tableName, $fieldName);
  }

  //
  $labels = [];
  foreach ($values as $value) {
    if (@$valuesToLabels[$tableName][$fieldName][$value]) {
      array_push($labels, $valuesToLabels[$tableName][$fieldName][$value]);
    }
    else {
      array_push($labels, $value);
    }
  }

  return $labels;
}

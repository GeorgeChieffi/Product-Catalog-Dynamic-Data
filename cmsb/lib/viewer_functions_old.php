<?php

// Viewer Functions v1.0
// The code generator no longer creates code that uses these functions but we keep them
// here and load them to support older viewers

//
function getListRows($options) {
  global $VIEWER_NAME, $TABLE_PREFIX;
  $VIEWER_NAME = "List Viewer ({$options['tableName']})";

  // error checking
  $requiredOptions = array('tableName');
  $validOptions    = array('tableName', 'titleField', 'perPage', 'where', 'orderBy', 'viewerUrl', 'pageNum', 'useSeoUrls');
  $errors          = _getOptionErrors($requiredOptions, $validOptions, $options);
  if ($errors) { die("$VIEWER_NAME errors<br>\n$errors"); }

  // set defaults
  if (!@$options['pageNum'])   { $options['pageNum']   = @$_REQUEST['page']; }
  if (!@$options['pageNum'])   { $options['pageNum']   = "1"; } // default to page 1
  if (!@$options['perPage'])   { $options['perPage']   = 10; }
  if (!@$options['viewerUrl']) { $options['viewerUrl'] = "No_viewerUrl_value_specified_in_options"; }

  // get absolute url for viewer
  if (@$options['useSeoUrls'] && @$options['viewerUrl'] && !preg_match("|[/]|", $options['viewerUrl'])) {
    $options['viewerUrl'] = dirname($_SERVER['SCRIPT_NAME']) ."/". $options['viewerUrl'];
    $options['viewerUrl'] = preg_replace("|^[\\\\/]+|", "/", $options['viewerUrl']); // remove multiple leading slashes (and replace \ returned by dirname on windows in root)
  }

  # create query
  $schema           = loadSchema($options['tableName']);
  $fullTableName    = getTableNameWithPrefix($options['tableName']);
  $escapedTableName = mysql_escape($fullTableName);

  if (@$options['where'] != '') { $where = @$options['where']; }
  else                          { $where = _createDefaultWhereWithFormInput($schema, @$options['where'], $options); }

  $where            = _addWhereConditionsForSpecialFields($schema, $where);
  $orderBy          = (@$options['orderBy']) ? "ORDER BY {$options['orderBy']}" : '';
  $offset           = ($options['pageNum']-1) * $options['perPage'];
  $limit            = "LIMIT " .mysql_escape($options['perPage']). " OFFSET " .mysql_escape($offset);
  $query            = "SELECT SQL_CALC_FOUND_ROWS * FROM `$escapedTableName` $where $orderBy $limit";

  # execute query
  $result     = mysqli()->query($query) or die("$VIEWER_NAME: MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $rows       = [];
  while ($record = $result->fetch_assoc()) {

    $filenameValue   = getFilenameFieldValue($record, @$options['titleField']);
    $record['_link'] = _getLink($options['viewerUrl'], $filenameValue, $record['num'], @$options['useSeoUrls']);

    array_push($rows, $record);
  }
  $listDetails = _getListDetails($options, count($rows));

  //
  return array($rows, $listDetails);
}


//
function getRecord($options) {
  global $VIEWER_NAME, $TABLE_PREFIX;
  $VIEWER_NAME = "Page Viewer ({$options['tableName']})";

  // error checking
  $requiredOptions = array('tableName');
  $validOptions    = array('tableName', 'recordNum', 'where','titleField', 'orderBy');
  $errors          = _getOptionErrors($requiredOptions, $validOptions, $options);
  if ($errors) { die("$VIEWER_NAME errors<br>\n$errors"); }

  // set defaults
  $schema = loadSchema($options['tableName']);
  if (!@$options['recordNum'])          { $options['recordNum'] = getLastNumberInUrl(); }
  if (@$schema['menuType'] == 'single') { $options['recordNum'] = "1"; }  // always load record 1 for single menus

  // get where condition
  $whereConditions  = '';
  $escapedRecordNum = mysql_escape( (int) $options['recordNum'] );
  if     ($options['where'])     { $whereConditions = $options['where']; }
  elseif ($options['recordNum']) { $whereConditions = "num = '$escapedRecordNum'"; }

  // get record
  $fullTableName    = getTableNameWithPrefix($options['tableName']);
  $escapedTableName = mysql_escape($fullTableName);
  $where            = _addWhereConditionsForSpecialFields($schema, $whereConditions, $options);
  $orderBy          = (@$options['orderBy']) ? "ORDER BY {$options['orderBy']}" : '';
  $query            = "SELECT * FROM `$escapedTableName` $where $orderBy LIMIT 0, 1";
  $result           = mysqli()->query($query) or die("$VIEWER_NAME: MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  $record           = $result->fetch_assoc();

  // add _link field
  if ($record) {
    $filenameValue   = getFilenameFieldValue($record, @$options['titleField']);
    $record['_link'] = _getLink($_SERVER['SCRIPT_NAME'], $filenameValue, $record['num'], @$options['useSeoUrls']);
  }

  // define upload fields
  if ($record) {
    foreach ($schema as $fieldname => $fieldSchema) {
      if (!is_array($fieldSchema)) { continue; } // not a field definition, table metadata field
      if (@$fieldSchema['type'] != 'upload') { continue; }  // skip all but upload fields
      $record[$fieldname] = "Use getUploads() function to list uploads (See code generator).\n";
    }
  }

  //
  return $record;
}


//
function getUploads($tableName, $fieldName, $recordNum) {
  global $TABLE_PREFIX;
  $uploads = [];
  // error checking
  if (!$tableName) { die(__FUNCTION__ . ": no 'tableName' value specified!"); }
  if (!$fieldName) { die(__FUNCTION__ . ": no 'fieldName' value specified!"); }
  if (!$recordNum) { die(__FUNCTION__ . ": no 'recordNum' value specified!"); }

  // get record uploads
  $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableName);
  $query   = "   SELECT * FROM `{$TABLE_PREFIX}uploads` ";
  $query  .= "    WHERE tableName = '" .mysql_escape($tableNameWithoutPrefix). "' AND ";
  $query  .= "          fieldName = '" .mysql_escape($fieldName). "' AND";
  $query  .= "          recordNum = '" .mysql_escape($recordNum). "'";
  $query  .= " ORDER BY `order`, num";
  $result  = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");

  //
  $schema = loadSchema($tableName);
  while ($upload = $result->fetch_assoc()) {
    _addUploadPseudoFields($upload, $schema, $fieldName);
    array_push($uploads, $upload);
  }

  return $uploads;
}

// return errors for missing required options and invalid option names
function _getOptionErrors($requiredOptions, $validOptions, $options) {
  $errors = '';

  // check for required fields
  foreach ($requiredOptions as $requiredOptionName) {
    if (!$options[$requiredOptionName]) { $errors .= "No '$requiredOptionName' value specified in options.<br>\n"; }
  }

  // check for invalid options
  foreach ($options as $optionName => $optionValue) {
    if (!in_array($optionName, $validOptions)) {
      $validOptionsAsCSV = implode(', ', $validOptions);
      $errors .= "Unknown option '$optionName' specified.  Valid option names are: ($validOptionsAsCSV)<br>\n";
    }
  }

  return $errors;
}


?>

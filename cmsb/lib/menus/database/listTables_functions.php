<?php



//
function getTableList() {
  global $TABLE_PREFIX, $APP;

  // get table names
  $mysqlTables  = getMysqlTablesWithPrefix();
  $schemaTables = getSchemaTables();

  // create multi query
  $tables         = [];
  $tableRowCounts = [];
  foreach ($schemaTables as $tableName) {

    $tableNameWithPrefix     = getTableNameWithPrefix($tableName);
    if (in_array($tableNameWithPrefix, $mysqlTables)) {
      $rowCount = mysql_count($tableNameWithPrefix);
    }

    $localTableSchema = loadSchema($tableName);
    array_push($tables, array(
      'tableName'   => $tableName,
      'menuName'    => @$localTableSchema['menuName'],
      'menuType'    => @$localTableSchema['menuType'],
      'menuOrder'   => @$localTableSchema['menuOrder'],
      'menuHidden'  => @$localTableSchema['menuHidden'],
      'tableHidden' => @$localTableSchema['tableHidden'],
      '_indent'     => @$localTableSchema['_indent'],
      'recordCount' => $rowCount
    ));

  }

  // sort table list
  uasort($tables, '_sortMenusByOrder');


  //
  return $tables;

}



?>

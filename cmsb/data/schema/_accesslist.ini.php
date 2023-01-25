<?php /* This is a PHP data file */ if (!@$LOADSTRUCT) { die("This is not a program file."); }
return array (
  'menuName' => 'Access Levels',
  'menuType' => '',
  'menuHidden' => '1',
  'tableHidden' => '1',
  'userNum' => array(
    'label' => 'User Number',
    'type' => 'none',
    'order' => '1',
    'customColumnType' => 'int(10) unsigned NOT NULL',
  ),
  'tableName' => array(
    'label' => 'Table Name',
    'type' => 'none',
    'order' => '2',
    'customColumnType' => 'varchar(255) NOT NULL',
  ),
  'accessLevel' => array(
    'label' => 'Access Level',
    'type' => 'none',
    'order' => '3',
    'customColumnType' => 'TINYINT UNSIGNED NOT NULL',
  ),
  'maxRecords' => array(
    'label' => 'User Number',
    'type' => 'none',
    'order' => '4',
    'customColumnType' => 'int(10) unsigned',
  ),
  'randomSaveId' => array(
    'label' => '',
    'type' => 'none',
    'order' => '5',
    'customColumnType' => 'varchar(255) NOT NULL',
  ),
);
?>
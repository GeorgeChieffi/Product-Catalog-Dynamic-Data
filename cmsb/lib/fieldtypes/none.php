<?php

class NoneField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}


//
function getDisplayValue($record) {

  // format createdBy/updatedBy dates
  require_once(SCRIPT_DIR . '/lib/fieldtypes/date.php');
  $dateFields = array('createdDate', 'updatedDate');
  if (in_array($this->name, $dateFields)) {
    $dateFieldObj = new DateField($this);
    return $dateFieldObj->getDisplayValue($record);
    // old code throws error on PHP 7: Using $this when not in object context
    //return @DateField::getDisplayValue($record, $this->name); // XXX: surpress warning about calling a non-static method statically
  }

  // format createByUserNum/updatedByUserNum
  $value = parent::getDatabaseValue($record);
  $userNumFields = array('createdByUserNum', 'updatedByUserNum');
  if (in_array($this->name, $userNumFields)) {
    $accountsTable  = "{$GLOBALS['TABLE_PREFIX']}accounts";
    $query          = mysql_escapef("SELECT username FROM `$accountsTable` WHERE num = ?", $value);
    list($username) = mysql_get_query($query, true);
    $value          = $username;
    return $value;
  }

  return parent::getDisplayValue($record);
}


//
function getTableRow($record, $value, $formType) {

  // Don't show record number on view page
  if ($formType == 'view' && $this->name == 'num') { return ''; }

  // Don't show record number on view page
  if ($formType == 'view' && $this->name == 'dragSortOrder') { return ''; }

  //
  $html = parent::getTableRow($record, $value, $formType);

  // add linebreak after lastUpdatedBy
  if ($this->name == 'updatedByUserNum') {
    $html .= <<<__HTML__
      <div class="form-group">
        <div class="col-sm-12">
          &nbsp;
        </div>
      </div>
__HTML__;
  }

  //
  return $html;
}


} // end of class

// eof
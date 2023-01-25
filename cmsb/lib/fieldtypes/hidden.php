<?php

class HiddenField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}

//
function getTableRow($record, $value, $formType) {

  return '';
  
}

} // end of class

?>

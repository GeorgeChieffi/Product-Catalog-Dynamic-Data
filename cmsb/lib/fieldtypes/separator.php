<?php

class SeparatorField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}

//
function getTableRow($record, $value, $formType) {
  $header       = '';
  $collapsibleClass  = 'clear';
  if      ($this->separatorType == 'blank line') {
    $header = '';
  }
  else if ($this->separatorType == 'header bar') {
    $header = $this->separatorHeader;
    
    // add collapsible class if isCollapsible is set for edit page
    $collapsibleClass .= @$this->isCollapsible ? ' separator-collapsible' : '';
    $collapsibleClass .= @$this->isCollapsed   ? ' separator-collapsed'   : ''; // if set, separator is closed by default
  }
  else if ($this->separatorType == 'html') {
    $header = getEvalOutput( $this->separatorHTML );
    
    // rewrite old cmsb2-style separators
    if (preg_match('#^\s*<tr>\s*<td colspan=\'2\'>\s*(.*?)\s*</td>\s*</tr>\s*$#', $header, $matches)) {
      $header = ($matches[1]);
    }
  }
  else {
    die("Unknown separator type '{$this->separatorType}'!");
  }
  
  return adminUI_separator(['label' => $header, 'type' => $this->separatorType, 'id' => $this->name, 'class' => $collapsibleClass]);
  
}

} // end of class

?>

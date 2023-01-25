<?php

class TabGroupField extends Field {

  function __construct($fieldSchema) {
    parent::__construct($fieldSchema);
  }

  //
  function getTableRow($record, $value, $formType) {

    return '';
    
  }
  
  // open HTML for tab group, plus tab navigation
  function tabGroupStart($tableSchema) {
    
    // start tab nav
    $html = '<ul class="nav nav-tabs" id="sectionTabs">';
    
    // build tab nav items
    $tabNums = 0;
    foreach( $tableSchema as $fieldName => $fieldSchema) {
      if (!empty( $fieldSchema['type'] ) && $fieldSchema['type'] == 'tabGroup') {
        if (!userHasFieldAccess($fieldSchema)) { continue; } // skip tabs that the user has no access to
        $html .= '<li class="nav-item ' . ($tabNums == 0 ? 'active' : '') . '">';
        $html .=  '<a id="tab-' . $fieldName . '" data-toggle="tab" role="tab" class="nav-link ' . ($tabNums == 0 ? 'active' : '') . '" href="#' . $fieldName. '">';
        $html .= htmlencode( $fieldSchema['label']) ?: "&nbsp;";
        $html .= '</a>';
        $html .= '</li>';
        $tabNums++;
      }
    }
    $html .= '</ul>';
    
    // if we have tabs, start tab container and output HTML
    if ($tabNums > 0) {
      
      $html .= '<div class="tab-content" id="sectionTabContent">';
      
      return $html;
    }
  }

  // close HTML for tab group
  function tabGroupEnd() {
    $html  = '</div></div>';
    return $html;
  }

  // open HTML for tab panel
  function tabPanelStart($first = false) {
    $html  = '<div class="tab-pane fade ' . ($first ? 'active in' : '') . '" id="' . $this->name . '" role="tabpanel">';
    return $html;
  }

  // close HTML for tab panel
  function tabPanelEnd() {
    $html  = '</div>';
    return $html;
  }

} // end of class

?>

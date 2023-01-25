<?php

class WysiwygField extends Field {

  // configure htmlBasicRow to add a break after fieldPrefix and vertically-align the label cell to top
  function htmlBasicRow_configure() {
    return [
      'addBreakAfterFieldPrefix'  => true,
      'labelVerticalAlignTop'     => true,
    ];
  }
  
  //
  function htmlViewContent($value, $record = null) {

    $fieldHeight = @$this->fieldHeight ?: 100;
    
    return "<div style='height:{$fieldHeight}px; border: 1px solid #CCC; padding: 0px 5px; overflow:auto'>$value</div>\n";
  }

  // override htmlEditContent to generate our <textarea> tag!
  function htmlEditContent($value, $record = null) {
    
    // set field attributes
    $fieldHeight = @$this->fieldHeight ?: 100;

    $encodedValue = htmlencode($value);
    
  // display field
  print <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2">
        {$this->label}
      </div>
      <div class="col-sm-9">
        $fieldPrefix
        <textarea name="{$this->name}" id="field_{$this->name}" rows="5" cols="40" style="width: 100%; height: {$fieldHeight}px; visibility: hidden;">$encodedValue</textarea>
        $description
      </div>
    </div>
__HTML__;
  }
  
} // end of class

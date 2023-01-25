<?php

class TextboxField extends Field {

  function __construct($fieldSchema) {
    parent::__construct($fieldSchema);
  }

  //
  function getDisplayValue($record) { // override me in derived classes
    $value = parent::getDatabaseValue($record);

    //
    if (!@$this->autoFormat) { $value = nl2br($value); }

    return $value;
  }

  // editFormHtml
  function editFormHtml($record) {
    // set field attributes
    $fieldHeight = @$this->fieldHeight ? $this->fieldHeight : 100;
    $fieldPrefix = @$this->fieldPrefix;
    if ($fieldPrefix != '') { $fieldPrefix .= "<br>\n"; }

    // get field value
    if      ($record)                                  { $fieldValue = @$record[ $this->name ]; }
    else if (array_key_exists($this->name, $_REQUEST)) { $fieldValue = @$_REQUEST[ $this->name ]; }
    else                                               { $fieldValue = getEvalOutput(@$this->defaultContent); }

    //
    if ($this->autoFormat) { $fieldValue = preg_replace("/<br\>\n/", "\n", $fieldValue); } // remove autoformat break tags
    $encodedValue  = htmlencode($fieldValue);

    // display field
    print <<<__HTML__
      <div class="form-group">
        <div class="col-sm-2">
          {$this->label}
        </div>
        <div class="col-sm-9">
          $fieldPrefix
          <textarea name="{$this->name}" class="form-control" style=" height: {$fieldHeight}px" rows="5" cols="50">$encodedValue</textarea>
        </div>
      </div>
__HTML__;
  }

} // end of class

?>

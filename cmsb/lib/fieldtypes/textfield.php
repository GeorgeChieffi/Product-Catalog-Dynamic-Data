<?php

class TextField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}


function getDisplayValue($record) { // override me in derived classes
  $value = parent::getDatabaseValue($record);

  // obscure password fields
  if ($GLOBALS['tableName'] == 'accounts' && $this->name == 'password') { return '**********'; }

  //
  return $value;
}



//
function getValueAsTableRow($record) {
  if (!$record) { die(__FUNCTION__ . ": record not defined!"); }
  $fieldValue   = $this->getValue($record);
  $encodedValue = htmlencode($fieldValue);

  $label        = @$this->label;
  $fieldPrefix  = @$this->fieldPrefix;
  $description  = @$this->description;

  // display field
  print <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2">
        $label
      </div>
      <div class="col-sm-9">
       <span>$fieldPrefix</span>
       $encodedValue
       <span>$description</span>
      </div>
    </div>
__HTML__;
}

// editFormHtml
function editFormHtml($record) {
  global $isMyAccountMenu;

  // set field attributes
  $formRowAttrs = array(
    'inputType'     => @$this->isPasswordField ? 'password'                                    : 'text',
    'maxLengthAttr' => @$this->maxLength       ? "maxlength='{$this->maxLength}'" : '',
    'styleWidth'    => @$this->fieldWidth      ? "{$this->fieldWidth}px"          : "250px",
    'description'   => getEvalOutput( @$this->description ),
    'prefixText'    => @$this->fieldPrefix,
    'readOnly'      => '', // add option for this later
  );

  // get field value
  if      ($record)                                  { $fieldValue = @$record[ $this->name ]; }
  else if (array_key_exists($this->name, $_REQUEST)) { $fieldValue = @$_REQUEST[ $this->name ]; }
  else                                               { $fieldValue = getEvalOutput(@$this->defaultValue); }
  $encodedValue  = htmlencode($fieldValue);

  // special case for My Account's password field
  if ($isMyAccountMenu && $this->name == 'password') {
    $this->_editFormRow($formRowAttrs + array(
      'label' => t('Current Password'),
      'fieldname' => 'password:old',
      'encodedValue' => '',
    ));
    $this->_editFormRow($formRowAttrs + array(
      'label' => t('New Password'),
      'fieldname' => $this->name,
      'encodedValue' => '',
    ));
    $this->_editFormRow($formRowAttrs + array(
      'label' => t('New Password (again)'),
      'fieldname' => 'password:again',
      'encodedValue' => '',
    ));
  }

  // general case
  else {
    $this->_editFormRow($formRowAttrs + array(
      'label' => $this->label,
      'fieldname' => $this->name,
      'encodedValue' => $encodedValue,
    ));
  }
}

function _editFormRow($attrs) {
  // display field
  print <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2">
        {$attrs['label']}
      </div>
      <div class="col-sm-9">
        <span>{$attrs['prefixText']}</span>
        <input type="{$attrs['inputType']}" name="{$attrs['fieldname']}" value="{$attrs['encodedValue']}" size="20" {$attrs['maxLengthAttr']} style="width: {$attrs['styleWidth']}" {$attrs['readOnly']}>
        <span>{$attrs['description']}</span>
      </div>
    </div>
__HTML__;
}

} // end of class

?>

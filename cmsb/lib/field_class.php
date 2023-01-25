<?php

//
class Field {

// member variables
public $name;
public $fieldSchema;

// constructor (called by derived classes)
function __construct($fieldSchema) {
  foreach ($fieldSchema as $name => $value) {
    $this->$name = $value;
  }
}

//
function getDatabaseValue($record) { // override me in derived classes
  //if (!$record) { dieAsCaller(__FUNCTION__ . ": record not defined!"); } // skip errorchecking - type 'none' doesn't pass a record
  $value = @$record[ $this->name ];
  return $value;
}

//
function getDisplayValue($record) { // override me in derived classes
  $value = $this->getDatabaseValue($record);
  if (is_array($value)) { return 'array'; } // for debugging
  return htmlencode( $value );
}

//
function getTableRow($record, $value, $formType) { // $formType is edit or view
  if (!$record) { die(basename(__FILE__).':'.__FUNCTION__ . ": record not defined!"); }

  $label        = @$this->label;
  $fieldPrefix  = @$this->fieldPrefix;
  $description  = getEvalOutput( @$this->description );
  
  // if this is a 'view' page, add top padding to value's div
  $valueDivStyle = '';
  if ($formType == 'view'){
    $valueDivStyle = 'style="padding-top: 7px"';
    $value = html_entity_decode($value ?? ''); // decode HTML on view page
  }
  
  // display field
  $html = <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2 control-label">
        {$label}
      </div>
      <div class="col-sm-10" $valueDivStyle>
        <span>$fieldPrefix<!-- --></span>
        $value
        <span>$description<!-- --></span>
      </div>
    </div>
__HTML__;

  $rowContents = [
                  'label'         => $label,
                  'value'         => $value,
                  'fieldPrefix'   => $fieldPrefix,
                  'description'   => $description,
                  'valueDivStyle' => $valueDivStyle,
                 ];
  $fieldSchema = get_object_vars($this);
  $html        = applyFilters('viewPageFieldRowHtml', $html, $rowContents, $fieldSchema, $record);
  
  return $html;
}

#// editFormHtml (override me in derived classes)
#function editFormHtml($record) {
#  $html = "<tr><td colspan='2' align='center'><b>field '" . $this->name . "' has unknown field type '" .@$this->type. "'</b></td></tr>";
#  return $html;
#}



}


// factory (getFieldObjects_fromSchema() -> array(new Field-derived objects))
function getFieldObjects_fromSchema($schema) {
  $fields = [];
  foreach ($schema as $name => $fieldSchema) {
    if (!is_array($fieldSchema)) { continue; } // fields are stored as arrays, other entries are table metadata
    $fieldSchema['name'] = $name;
    $field    = createFieldObject_fromSchema($fieldSchema);
    if (!$field) { continue; }
    $fields[] = $field;
  }
  return $fields;
}


// factory (_createFieldObject_fromSchema() -> new Field-derived object)
function createFieldObject_fromSchema($fieldSchema) {
  if (!@$fieldSchema['type']) { return; }

  // load class
  $classPath = SCRIPT_DIR .'/lib/fieldtypes/'. $fieldSchema['type'] .'.php';
  if (!file_exists($classPath)) { die("Couldn't find field class file: $classPath\n"); }
  require_once($classPath);

  //
  $className = ucfirst( $fieldSchema['type'] );
  if (!preg_match("/Field$/i", $className)) { $className .= 'Field'; }; // Don't change TextField to TextFieldField
  return new $className($fieldSchema);
}


//
#function showEditFormRows($record) {
#  global $schema, $escapedTableName, $CURRENT_USER, $tableName, $menu, $isMyAccountMenu;
#
#  $record = &$GLOBALS['RECORD'];
#
#  $fields = getFieldObjects_fromSchema($schema);
#
##showme($fields);
##exit;
#
#  // load schema columns
#  _showCreatedUpdated($schema, $record);
#  foreach ($fields as $field) {
#
#    // special cases: skip fields if:
#    if (@$field->adminOnly && !$CURRENT_USER['isAdmin'] && !$GLOBALS['hasEditorAccess']) { continue; }     // skip admin only fields
#    if ($tableName == 'accounts' && $field->name == 'isAdmin' && !$CURRENT_USER['isAdmin']) { continue; }  // only admin users can see/change "isAdmin" field
#    if ($isMyAccountMenu && @!$field->myAccountField) { continue; }                                        // only show fields set as 'myAccountField' on My Accounts page
#
#    // display field
#    $field->editFormHtml($record);
#  }
#}


//
function showViewFormRows($record) {
  global $schema, $escapedTableName, $CURRENT_USER, $tableName, $menu, $isMyAccountMenu;

  $record = &$GLOBALS['RECORD'];

  $fields = getFieldObjects_fromSchema($schema);
  
  // set flag to see if we've started a tab group
  $tabGroupStarted = false;
  $lastTab         = false;
  
  // load schema columns
  $html = '';
  foreach ($fields as $field) {

    // special cases: skip fields if:
    if (!userHasFieldAccess(get_object_vars($field))) { continue; } // skip fields that the user has no access to
    if ($tableName == 'accounts' && $field->name == 'isAdmin' && !$CURRENT_USER['isAdmin']) { continue; } // only admin users can see/change "isAdmin" field
    if ($isMyAccountMenu && @!$field->myAccountField) { continue; }                                       // only show fields set as 'myAccountField' on My Accounts page

    // custom output for tab groups
    if ($field->type == 'tabGroup') {
      
        // tab group - if this is the first tab group, output the tabs and tab container  
        if (!$tabGroupStarted) { $html .= $field->tabGroupStart($schema); }
        
        // tab panel
        if ($tabGroupStarted) { $html .= $field->tabPanelEnd($field, $record); } // if not first tab panel, close tab panel HTML
        $html .= $field->tabPanelStart($field, !$tabGroupStarted);               // start tab panel HTML
        
        // flag start of tab group
        $tabGroupStarted = true;
        
        // save last seen tab group
        $lastTab         = $field;
        
    }

    // display field
    $fieldValue   = $field->getDisplayValue($record);
    $html       .= $field->getTableRow($record, $fieldValue, 'view');
  }
    
  // tab group - if we encountered any tab groups, close the tab group/panel HTML
  if ($tabGroupStarted && $lastTab) { $html .= $lastTab->tabGroupEnd(); }

  print $html;

} // end of class




?>

<?php

class ListField extends Field {


function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}


//
function getDisplayValue($record) { // override me in derived classes
  $value = parent::getDatabaseValue($record);

  $fieldname = $this->name;
  if (array_key_exists("$fieldname:labels", $record)) {
    $value = join("<br>\n", $record[ "$fieldname:labels" ]);
  }
  else if (array_key_exists("$fieldname:label", $record))  {
    $value = $record[ "$fieldname:label" ];
  }

  return $value;
}


// editFormHtml
function editFormHtml($record) {
  // set field attributes
  $listOptions = getListOptionsFromSchema($this, $record);
  $valignTop   = ($this->listType != 'pulldown') ? 'style="vertical-align: top;"' : '';
  $prefixText  = @$this->fieldPrefix;
  $description = getEvalOutput( @$this->description );

  // get field value
  if      ($record)                                  { $fieldValue = @$record[ $this->name ]; }
  else if (array_key_exists($this->name, $_REQUEST)) { $fieldValue = join("\t", (array) @$_REQUEST[ $this->name ]); }
  else                                               { $fieldValue = ''; }
  $fieldValues = preg_split("/\t/", $fieldValue, -1, PREG_SPLIT_NO_EMPTY); // for multi value fields
  $encodedValue  = htmlencode($fieldValue);

  // get list of values in database that aren't in list options (happens when list values are removed or field
  // ... was a textfield than switched to a pulldown that doesn't offer all the previously entered values as options
  $fieldValuesNotInList = [];
  $listOptionValues = [];
  foreach ($listOptions as $optionArray) {
    list($value,$label) = $optionArray;
    $listOptionValues[] = $value;
  }
  $fieldValuesNotInList = array_diff($fieldValues, $listOptionValues);
  $noLongerInListText   = (count($fieldValuesNotInList) > 1) ? t('Previous selections (no longer in list)') : t('Previous selection (no longer in list)');

  //
  print "  <tr>\n";
  print "   <td $valignTop>{$this->label}</td>\n";
  print "   <td>\n";

  // pulldown
  if      ($this->listType == 'pulldown') {
    print "$prefixText\n";
    print "  <select name='{$this->name}'>\n";
    print "  <option value=''>&lt;select&gt;</option>\n";
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $selectedAttr = selectedIf($value, $fieldValue, true);
      $encodedLabel = htmlencode($label);
      print "<option value=\"$encodedValue\" $selectedAttr>$encodedLabel</option>\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "  <optgroup label='$noLongerInListText'>\n";
      foreach ($fieldValuesNotInList as $value) {
        print "    <option value=\"" .htmlencode($value). "\" selected='selected'>" .htmlencode($value). "</option>\n";
      }
      print "  </optgroup>\n";
    }

    print "  </select>\n";
    print "$description\n";
  }

  // multi pulldown
  else if      ($this->listType == 'pulldownMulti') {
    if ($prefixText) { print "$prefixText<br>\n"; }
    print "  <select name='{$this->name}[]' multiple='multiple' size='5'>\n";
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $selectedAttr = (in_array($value, $fieldValues)) ? 'selected="selected"' : '';
      $encodedLabel = htmlencode($label);
      print "<option value=\"$encodedValue\" $selectedAttr>$encodedLabel</option>\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "  <optgroup label='$noLongerInListText'>\n";
      foreach ($fieldValuesNotInList as $value) {
        print "    <option value=\"" .htmlencode($value). "\" selected='selected'>" .htmlencode($value). "</option>\n";
      }
      print "  </optgroup>\n";
    }

    print "  </select>\n";
    if ($description) { print "<br>$description\n"; }
  }

  // radios
  else if ($this->listType == 'radios') {
    if ($prefixText) { print "$prefixText<br>\n"; }
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $encodedLabel = htmlencode($label);
      $checkedAttr  = ($value == $fieldValue) ? 'checked="checked"' : '';
      $idAttr       = "{$this->name}.$encodedValue";

      print "<input type='radio' name='{$this->name}' value='$encodedValue' id='$idAttr' $checkedAttr>\n";
      print "<label for='$idAttr'>$encodedLabel</label><br>\n\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "$noLongerInListText<br>\n";
      foreach ($fieldValuesNotInList as $value) {
        $encodedValue = htmlencode($value);
        $encodedLabel = htmlencode($value);
        $idAttr       = "{$this->name}.$encodedValue";
        print "<input type='radio' name='{$this->name}' value='$encodedValue' id='$idAttr' checked='checked'>\n";
        print "<label for='$idAttr'>$encodedLabel</label><br>\n\n";
      }
    }
    if ($description) { print "$description\n"; }
  }

  // checkboxes
  else if ($this->listType == 'checkboxes') {
    if ($prefixText) { print "$prefixText<br>\n"; }
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $encodedLabel = htmlencode($label);
      $checkedAttr  = in_array($value, $fieldValues) ? 'checked="checked"' : '';
      $idAttr       = "{$this->name}.$encodedValue";

      print "<input type='checkbox' name='{$this->name}[]' value='$encodedValue' id='$idAttr' $checkedAttr>\n";
      print "<label for='$idAttr'>$encodedLabel</label><br>\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "$noLongerInListText<br>\n";
      foreach ($fieldValuesNotInList as $value) {
        $encodedValue = htmlencode($value);
        $encodedLabel = htmlencode($value);
        $idAttr       = "{$this->name}.$encodedValue";
        print "<input type='checkbox' name='{$this->name}[]' value='$encodedValue' id='$idAttr' checked='checked'>\n";
        print "<label for='$idAttr'>$encodedLabel</label><br>\n\n";
      }
    }
    if ($description) { print "$description\n"; }
  }

  //
  else { die("Unknown listType '{$this->listType}'!"); }


  // list fields w/ advanced filters - add onchange event handler to local filter field
  if (@$this->filterField) {
    ?>
    <script type="text/javascript"><!--
      $("[name='<?php echo $this->filterField ?>']").change(function () {
        var targetListField = '<?php echo $this->name ?>';
        var newFilterValue  = this.value;
        updateListFieldOptions(targetListField, newFilterValue);
      });
    // --></script>
    <?php
  }


  //
  print "   </td>\n";
  print "  </tr>\n";
}

} // end of class

?>

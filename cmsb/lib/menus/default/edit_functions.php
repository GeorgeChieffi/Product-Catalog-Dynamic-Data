<?php

//
function showFields($record) {
  global $schema, $escapedTableName, $CURRENT_USER, $tableName, $menu, $isMyAccountMenu;

  $record = &$GLOBALS['RECORD'];

  // copy global schema state, so that if changed (i.e. by _showrelatedRecords), we can restore it
  $active_menu = $menu; $active_tableName = $tableName; $active_schema = $schema;
  
  // set flag to see if we've started a tab group
  $tabGroupStarted = false;
  $lastTab         = false;

  // load schema columns
  _showCreatedUpdated($schema, $record);
  foreach ($active_schema as $name => $fieldHash) {

    if (!is_array($fieldHash)) { continue; } // fields are stored as arrays, other entries are table metadata
    $fieldSchema = array('name' => $name) + $fieldHash;
    $fieldSchema = applyFilters('edit_fieldSchema', $fieldSchema, $tableName);

    // special cases: skip fields if:
    if (!userHasFieldAccess($fieldHash)) { continue; }                   // skip fields that the user has no access to
    if ($tableName == 'accounts' && $name == 'isAdmin' && !$CURRENT_USER['isAdmin']) { continue; } // only admin users can set/change "isAdmin" field
    if ($isMyAccountMenu && @!$fieldSchema['myAccountField']) { continue; }                        // only show fields set as 'myAccountField' on My Accounts page

    // allow hooks to override (return false to override)
    if (!applyFilters('edit_show_field', true, $fieldSchema, $record)) { continue; }
    
    // hook for adding CSS class to the field row's div
    $fieldRowClass = '';
    $fieldRowClass = applyFilters('showField_addRowClass', $fieldRowClass, $fieldSchema, $tableName, $record);

    //
    switch (@$fieldHash['type']) {
      case '': case 'none': break;
      case 'textfield':  _showTextfield($fieldSchema, $record, $fieldRowClass); break;
      case 'textbox':    _showTextbox($fieldSchema,   $record, $fieldRowClass); break;
      case 'wysiwyg':    _showWysiwyg($fieldSchema,   $record, $fieldRowClass); break;
      case 'date':       _showDateTime($fieldSchema,  $record, $fieldRowClass); break;
      case 'list':       _showList($fieldSchema,      $record, $fieldRowClass); break;
      case 'checkbox':   _showCheckbox($fieldSchema,  $record, $fieldRowClass); break;
      case 'upload':     _showUpload($fieldSchema,    $record, $fieldRowClass); break;
      case 'separator':  _showSeparator($fieldSchema, $record); break;
      case 'hidden':     _showHidden($fieldSchema, $record); break;
      case 'tabGroup':
      
        // tab group - if this is the first tab group, output the tabs and tab container  
        if (!$tabGroupStarted) { _showTabGroupStart($fieldSchema, $active_schema); }
        
        // tab panel
        if ($tabGroupStarted) { _showTabPanelEnd($fieldSchema, $record); } // if not first tab panel, close tab panel HTML
        _showTabPanelStart($fieldSchema, !$tabGroupStarted);               // start tab panel HTML
        
        // flag start of tab group
        $tabGroupStarted = true;
        
        // save last seen tab group
        $lastTab         = $fieldSchema;
        
      break;

      // advanced fields
      case 'relatedRecords': _showrelatedRecords($fieldSchema, $record); break;
      case 'parentCategory': _showParentCategory($fieldSchema, $record, $schema); break;

      // custom fields
      case 'accessList':     _showAccessList($fieldSchema, $record); break;

      case 'dateCalendar':   _showDateCalendar($fieldSchema, $record); break;

      default:

        echo '<div class="form-group">';
        echo '<div class="col-sm-12"><b>field "'.$name.'" has unknown field type "'.@$fieldHash['type'].'"</b></div>';
        echo '</div>';

        break;

    }

    // restore global schema state in case any of the above functions (i.e. _showrelatedRecords) modified it
    $menu = $active_menu; $tableName = $active_tableName; $schema = $active_schema;
  }
    
  // tab group - if we encountered any tab groups, close the tab group/panel HTML
  if ($tabGroupStarted && !empty( $lastTab )) { _showTabGroupEnd($lastTab); }

}

function _showCreatedUpdated($schema, $record) {
  global $CURRENT_USER, $TABLE_PREFIX, $isSingleMenu, $tableName, $SETTINGS;

  // get date format
  if     (@$SETTINGS['dateFormat'] == 'dmy') { $dateFormat  = "jS M, Y - h:i:s A"; }
  elseif (@$SETTINGS['dateFormat'] == 'mdy') { $dateFormat  = "M jS, Y - h:i:s A"; }
  else                                       { $dateFormat  = "M jS, Y - h:i:s A"; }

  // create dates
  $currentDate = date($dateFormat);

  if      (!@$record['createdDate'])                        { $createdDate = $currentDate; } // for new unsaved records
  else if ($record['createdDate'] == '0000-00-00 00:00:00') { $createdDate = ''; } // for records with no previous value
  else                                                      { $createdDate = date($dateFormat, strtotime($record['createdDate'])); }

  if      (!@$record['updatedDate'])                        { $updatedDate = $currentDate; } // for new unsaved records
  else if ($record['updatedDate'] == '0000-00-00 00:00:00') { $updatedDate = ''; } // for records with no previous value
  else                                                      { $updatedDate = date($dateFormat, strtotime($record['updatedDate'])); }

  // get user names
  $usernameField = 'username'; // change this to 'username' to show usernames
  $createdByUsername = t('Unknown');
  $updatedByUsername = t('Unknown');

  if ($record) { // editing record
    $accountTable = $TABLE_PREFIX . "accounts";
    if (@$record['createdByUserNum']) {
      $query  = "SELECT $usernameField FROM `$accountTable` WHERE num = '".mysql_escape($record['createdByUserNum'])."'";
      $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
      list($createdByUsername) = $result->fetch_row();
    }
    if (@$record['updatedByUserNum']) {
      $query  = "SELECT $usernameField FROM `$accountTable` WHERE num = '".mysql_escape($record['updatedByUserNum'])."'";
      $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
      list($updatedByUsername) = $result->fetch_row();
    }
  }
  else { // adding record
    $createdByUsername = $CURRENT_USER[$usernameField];
    $updatedByUsername = $CURRENT_USER[$usernameField];
  }

  // header
  $html = '';
  $showCreated = @$schema['createdDate'];
  $showUpdated = @$schema['updatedDate'];
  if ($showCreated || $showUpdated) {
    $html .= "<div class=\"form-group\">\n";
  }

  // Created field
  if ($showCreated) {
    $allowChangeLink = ($CURRENT_USER['isAdmin'] || $GLOBALS['hasEditorAccess']);
    $changeLink      = $allowChangeLink ? "<a href='#' id='createdByUserNumChangeLink' onclick='showCreatedByUserPulldown(); return false;'>".t('change')."</a>" : '';
    $displayValue    = $createdDate;
    if (@$schema['createdByUserNum']) {
      $displayValue = sprintf(t("%s (by %s)"), $createdDate, "<span id='createdByUserNumHTML'>" . htmlencode($createdByUsername) . "</span>") . " $changeLink";
    }
    $html .= "<div class=\"col-sm-2 control-label\">{$schema['createdDate']['label']}</div>\n";
    $html .= "<div class=\"col-sm-10 control-label\"><div class=\"text-left\">$displayValue</div></div>\n";
  }

  // show updated
  if ($showUpdated) {
    $displayValue = $updatedDate;
    if (@$schema['updatedByUserNum']) {
      $displayValue = sprintf(t("%s (by %s)"), $updatedDate, htmlencode($updatedByUsername));
    }
    $html .= "<div class=\"col-sm-2 control-label\">{$schema['updatedDate']['label']}</div>\n";
    $html .= "<div class=\"col-sm-10 control-label\"><div class=\"text-left\">$displayValue</div></div>\n";

  }

  // footer
  if ($showCreated || $showUpdated) {
    $html .= "</div>\n";
    $html .= '<div class="form-group"><div class="col-sm-12"></div></div>'; // add linebreak
  }

  print $html;

}



//
function _showTextfield($fieldSchema, $record, $fieldRowClass = null) {
  global $isMyAccountMenu;

  // set field attributes
  $inputType        = @$fieldSchema['isPasswordField'] ? 'password'                                : 'text';
  $autoComplete     = @$fieldSchema['isPasswordField'] ? 'autocomplete="off"'                      : ''; 
  $maxLengthAttr    = @$fieldSchema['maxLength']       ? "maxlength='{$fieldSchema['maxLength']}'" : '';
  $description      = getEvalOutput( @$fieldSchema['description'] );
  $fieldname        = $fieldSchema['name'];
  $fieldnameSuffix  = @$fieldSchema['isPasswordField'] ? ":disableAutocomplete".time() : ""; // random value to prevent password field matching
  $prefixText       = @$fieldSchema['fieldPrefix'];
  $fieldAddonBefore = @$fieldSchema['fieldAddonBefore'];
  $fieldAddonAfter  = @$fieldSchema['fieldAddonAfter'];
  $readOnlyAttr     = @$fieldSchema['readonly'] ? "readonly" : ''; // Future: when we implement UI for this double check on save and ignore any user submitted values

  // get field width class
  $widthName      = $fieldSchema['fieldWidth'] ?? ''; // eg: tiny, small, medium, large, full
  $isWidthNumeric = preg_match('/^\d+$/', $widthName);
  if ($isWidthNumeric) {  // convert legacy v2.xx field width pixels to named size (eg: 340 to small)
    $pixels = @$fieldSchema['fieldWidth'];
    if     (0 < $pixels   && $pixels <= 120)  { $widthName = "tiny"; }
    elseif (120 < $pixels && $pixels <= 340)  { $widthName = "small"; }
    elseif (340 < $pixels && $pixels <= 675)  { $widthName = "medium"; }
    elseif (675 < $pixels && $pixels <= 1010) { $widthName = "large"; }
    elseif (1010 < $pixels)                   { $widthName = "full"; }
  }
  $styleClassWidth = getBootstrapFieldWidthClass($widthName);
  
  // construct prefix and description HTML
  $prefixHtml = '';
  if ($prefixText){
    $prefixHtml = '<div class="div-inline help-block">'.$prefixText.'</div>';
  }
  $descriptionHtml = '';
  if ($description){
    $descriptionHtml  = '<div class="div-inline help-block">'.$description.'</div>';
  }
  
  // construct field addons HTML
  $beforeFieldAddonHtml = '';
  if ($fieldAddonBefore){
    $beforeFieldAddonHtml = '<span class="input-group-addon">'.$fieldAddonBefore.'</span>';
  }
  $afterFieldAddonHtml = '';
  if ($fieldAddonAfter){
    $afterFieldAddonHtml  = '<span class="input-group-addon">'.$fieldAddonAfter.'</span>';
  }

  // get field value
  if      ($record)                                 { $fieldValue = @$record[ $fieldname ]; }
  else if (array_key_exists($fieldname, $_REQUEST)) { $fieldValue = @$_REQUEST[ $fieldname ]; }
  else                                              { $fieldValue = getEvalOutput(@$fieldSchema['defaultValue']); }
  $encodedValue  = htmlencode($fieldValue);

  // My Account - old password field
  if ($isMyAccountMenu && $fieldname == 'password') {
    $encodedValue         = '';
    $fieldSchema['label'] = t('New Password');
    $oldPseudoFieldSchema = array('name' => 'password:old' , 'label' => t('Current Password')) + $fieldSchema;
    _showTextfield($oldPseudoFieldSchema, $record);
  }

  // display field
  print <<<__HTML__
    <div class="form-group $fieldRowClass">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        <div class="form-inline">
          $prefixHtml
          <div class="input-group $styleClassWidth">
            $beforeFieldAddonHtml
            <input type="$inputType" style="width: 100%" name="$fieldname$fieldnameSuffix" value="$encodedValue" $maxLengthAttr class="text-input form-control" $readOnlyAttr $autoComplete>
            $afterFieldAddonHtml
          </div>
          $descriptionHtml
        </div>
      </div>
    </div>
__HTML__;

  // My Account - new password (again)
  if ($isMyAccountMenu && $fieldname == 'password') {
    $againPseudoFieldSchema = array('name' => 'password:again', 'label' => t('New Password (again)')) + $fieldSchema;
    _showTextfield($againPseudoFieldSchema, $record);
  }

}


//
function _showTextbox($fieldSchema, $record, $fieldRowClass = null) {

  // set field attributes
  $fieldname   = $fieldSchema['name'];
  $fieldHeight = @$fieldSchema['fieldHeight'] ? $fieldSchema['fieldHeight'] : 100;
  $fieldPrefix = getEvalOutput( @$fieldSchema['fieldPrefix'] );
  if ($fieldPrefix != '') { $fieldPrefix .= "<br>\n"; }
  $description = getEvalOutput( @$fieldSchema['description'] );
  
  // construct prefix and description HTML
  $prefixHtml = '';
  if ($fieldPrefix) {
    $prefixHtml = "<p class=\"help-block\">$fieldPrefix</p>";
  }
  $descriptionHtml = '';
  if ($description) {
    $descriptionHtml = "<p class=\"help-block\">$description</p>";
  }

  // get field value
  if      ($record)                                 { $fieldValue = @$record[ $fieldname ]; }
  else if (array_key_exists($fieldname, $_REQUEST)) { $fieldValue = @$_REQUEST[ $fieldname ]; }
  else                                              { $fieldValue = getEvalOutput(@$fieldSchema['defaultContent']); }

  //
  if ($fieldSchema['autoFormat']) { $fieldValue = preg_replace("/<br\s*\/?>\r?\n/", "\n", $fieldValue??''); } // remove autoformat break tags
  $encodedValue  = htmlencode($fieldValue);

  // display field
  print <<<__HTML__
    <div class="form-group $fieldRowClass">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        $prefixHtml
        <textarea class="form-control" name="{$fieldSchema['name']}" style="width: 100%; height: {$fieldHeight}px" rows="5" cols="50">$encodedValue</textarea>
        $descriptionHtml
      </div>
    </div>
__HTML__;
}


//
function _showWysiwyg($fieldSchema, $record, $fieldRowClass = null) {

  // set field attributes
  $description = getEvalOutput( @$fieldSchema['description'] );
  $fieldHeight = @$fieldSchema['fieldHeight'] ? $fieldSchema['fieldHeight'] : 100;
  $fieldPrefix = @$fieldSchema['fieldPrefix'];
  if ($fieldPrefix != '') { $fieldPrefix .= "<br>\n"; }

  // get field value
  $fieldname = $fieldSchema['name'];
  if      ($record)                                 { $fieldValue = @$record[ $fieldname ]; }
  else if (array_key_exists($fieldname, $_REQUEST)) { $fieldValue = @$_REQUEST[ $fieldname ]; }
  else                                              { $fieldValue = getEvalOutput(@$fieldSchema['defaultContent']); }
  $encodedValue  = htmlencode($fieldValue);
  
  // construct prefix and description HTML
  $prefixHtml = '';
  if ($fieldPrefix) {
    $prefixHtml = "<p class=\"help-block\">$fieldPrefix</p>";
  }
  $descriptionHtml = '';
  if ($description) {
    $descriptionHtml = "<p class=\"help-block\">$description</p>";
  }

  // display field
  print <<<__HTML__
    <div class="form-group $fieldRowClass">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        $prefixHtml
        <textarea class="form-control" name="{$fieldSchema['name']}" id="field_{$fieldSchema['name']}" rows="5" cols="40" style="width: 100%; height: {$fieldHeight}px; visibility: hidden;">$encodedValue</textarea>
        $descriptionHtml
      </div>
    </div>
__HTML__;
}


//
function _showDateTime($fieldSchema, $record, $fieldRowClass = null) {
  global $SETTINGS;
  $mysqlDateFormat = 'Y-m-d H:i:s';
  $prefixText      = @$fieldSchema['fieldPrefix'];
  $description     = getEvalOutput( @$fieldSchema['description'] );

  // get default date
  if     (@$fieldSchema['defaultDate'] == 'none')   { $defaultDateTime = ''; }
  elseif (@$fieldSchema['defaultDate'] == 'custom') { $defaultDateTime = @date($mysqlDateFormat, strtotime($fieldSchema['defaultDateString'])); }
  else                                              { $defaultDateTime = date($mysqlDateFormat); }

  // get date value(s)
  $fieldname = $fieldSchema['name'];
  if     (isset($record[$fieldname]))   { $dateValue = $record[$fieldname]; }
  elseif (isset($_REQUEST[$fieldname])) { $dateValue = $_REQUEST[ $fieldname ]; }
  else                                  { $dateValue = $defaultDateTime; }

  //
  list($date,$time,$year,$month,$day,$hour24,$min,$sec,$amOrPm,$hour12) = array(null,null,null,null,null,null,null,null,null,null);
  if ($dateValue && $dateValue != '0000-00-00 00:00:00') { // mysql will default undefined dates to null or 0000-00-00 00:00:00
    @list($date,$time)       = explode(' ', $dateValue); // expecting: YYYY-MM-DD HH:MM:SS
    @list($year,$month,$day) = explode('-', $date);      // expecting: YYYY-MM-DD
    @list($hour24,$min,$sec) = explode(':', $time);      // expecting: HH:MM:SS
    $amOrPm = $hour24 >= 12 ? 'PM' : 'AM';
    $hour12 = (($hour24 % 12) == 0) ? 12 : $hour24 % 12;
  }

  // get month options
  $monthOptions    = "<option value=''>".t('Month')."</option>\n";
  $shortMonthNames = preg_split("/\s*,\s*/", t('Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'));
  foreach (range(1,12) as $num) {
    $selectedAttr   = selectedIf($num, $month, true);
    $shortMonthName = @$shortMonthNames[$num-1];
    $monthOptions .= "<option value=\"$num\" $selectedAttr>$shortMonthName</option>\n";
  }

  // get day options
  $dayOptions    = "<option value=''>".t('Day')."</option>\n";
  foreach (range(1,31) as $num) {
    $selectedAttr  =  selectedIf($num, $day, true);
    $dayOptions   .= "<option value=\"$num\" $selectedAttr>$num</option>\n";
  }

  // get year options
  $yearRangeStart = $fieldSchema['yearRangeStart'] ? intval($fieldSchema['yearRangeStart']) : date('Y')-5; // v2.16 - default to 5 years previous if undefined
  $yearRangeEnd   = $fieldSchema['yearRangeEnd']   ? intval($fieldSchema['yearRangeEnd'])   : date('Y')+5; // v2.16 - default to 5 years ahead if undefined
  //Check if existing record field has a date outside the default year range.  If so, increase the year range to accommodate.
  if (isset($record[$fieldSchema['name']]) && $record[$fieldSchema['name']] !== '0000-00-00 00:00:00') {
    $recordTimeStamp = strtotime($record[$fieldSchema['name']]);
    if($recordTimeStamp) {
      $recordYear     = (int) date("Y", $recordTimeStamp);
      $yearRangeStart = min($yearRangeStart, $recordYear);
      $yearRangeEnd   = max($yearRangeEnd,   $recordYear);
    }
  }
  $yearOptions = "<option value=''>".t('Year')."</option>\n";
  foreach (range($yearRangeStart, $yearRangeEnd) as $num) { // (int) for range bug in PHP < 4.23 - see docs
    $selectedAttr  = selectedIf($num, $year, true);
    $yearOptions  .= "<option value=\"$num\" $selectedAttr>$num</option>\n";
  }

  // get hour options
  $hour24Options = "<option value=''>".t('Hour')."</option>\n";
  $hour12Options = "<option value=''>".t('Hour')."</option>\n";
  foreach (range(0,23) as $num) {
    $zeroPaddedNum  = sprintf("%02d", $num);
    $selectedAttr   = selectedIf($num, $hour24, true);
    $hour24Options .= "<option value=\"$num\" $selectedAttr>$zeroPaddedNum</option>\n";
  }
  foreach (range(1,12) as $num) {
    $selectedAttr   = selectedIf($num, $hour12, true);
    $hour12Options .= "<option value=\"$num\" $selectedAttr>$num</option>\n";
  }

  // get minute options
  $minOptions = "<option value=''>Min</option>\n";
  foreach (range(0,59) as $num) {
    $zeroPaddedNum = sprintf("%02d", $num);
    $selectedAttr  = selectedIf($num, $min, true);
    $minOptions   .= "<option value=\"$num\" $selectedAttr>$zeroPaddedNum</option>\n";
  }

  // get second options
  $secOptions    = "<option value=''>Sec</option>\n";
  foreach (range(0,59) as $num) {
    $zeroPaddedNum = sprintf("%02d", $num);
    $selectedAttr  = selectedIf($num, $sec, true);
    $secOptions   .= "<option value=\"$num\" $selectedAttr>$zeroPaddedNum</option>\n";
  }

  // get AmPm optins
  $amSelectedAttr = selectedIf($amOrPm, 'AM', true);
  $pmSelectedAttr = selectedIf($amOrPm, 'PM', true);

  // display date field
  print "<div class=\"form-group $fieldRowClass\">\n";
  print "<div class=\"col-sm-2 control-label\">{$fieldSchema['label']}</div>\n";
  print "<div class=\"col-sm-10\">";
  print "<p class=\"help-block visible-xs\">$prefixText</p>";
  print "<div class=\"form-inline\"><p style=\"display: inline\" class=\"help-block hidden-xs\">$prefixText</p>\n";

  $monthsField = "<select class=\"form-control\" name='{$fieldSchema['name']}:mon' id='{$fieldSchema['name']}:mon'>$monthOptions</select>\n";
  $daysField   = "<select class=\"form-control\" name='{$fieldSchema['name']}:day'>$dayOptions</select>\n";
  if ($SETTINGS['dateFormat'] == 'dmy') { print $daysField . $monthsField; }
  else                                  { print $monthsField . $daysField; }
  print "<select class=\"form-control\" name='{$fieldSchema['name']}:year'>$yearOptions</select>\n";

  // datepicker
  if (@$SETTINGS['advanced']['useDatepicker']):
    ?>
    &nbsp;<input type="hidden" name="<?php echo $fieldSchema['name'] ?>:string" id="<?php echo $fieldSchema['name'] ?>:string" value="<?php echo $date ?>">&nbsp;
  <script type="text/javascript">
          $(function() {
            $("#<?php echo $fieldSchema['name'] ?>\\:string").datepicker({  // this is from: lib/menus/default/edit_functions.php
              showOn: 'button',
          //yearRange: '<?php echo($yearRangeStart . ':' . $yearRangeEnd); ?>',
          //changeYear: true,
              buttonImage: '<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryUI/calendar.gif',
              buttonImageOnly: true,
              dateFormat: 'yy-mm-dd',
              onClose: function(date) {
                var dateElements = date.split('-', 3);
            if (dateElements.length != 3) { dateElements = ['', '', '']; }
                var year  = dateElements[0];
                var month = dateElements[1].replace(/^[0]+/g, '');
                var day   = dateElements[2].replace(/^[0]+/g, '');

                $('[name=<?php echo $fieldSchema['name'] ?>\\:year]').val( year );
                $('[name=<?php echo $fieldSchema['name'] ?>\\:mon]').val( month );
                $('[name=<?php echo $fieldSchema['name'] ?>\\:day]').val( day );
              }
            });

            // update hidden date field on date change
            $('[name^=<?php echo $fieldSchema['name'] ?>\\:]').change(function() {
              setTimeout(function() { // wait 1/4 second, so updates to dropdowns can be completed before their change event firsts and updates the hidden date field again
                var date = $('[name=<?php echo $fieldSchema['name'] ?>\\:year]').val() +'-'
                         + $('[name=<?php echo $fieldSchema['name'] ?>\\:mon]').val() +'-'
                         + $('[name=<?php echo $fieldSchema['name'] ?>\\:day]').val();
                $('[name=<?php echo $fieldSchema['name'] ?>\\:string]').val(date);
              },250);
            });

          });
  </script>
  <?php
  endif;

  if ($fieldSchema['showTime']) {
    if (!@$SETTINGS['advanced']['useDatepicker']) {
      print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
    }
    if ($fieldSchema['use24HourFormat']) { // show 24 hour time
      print "<select class=\"form-control\" name='{$fieldSchema['name']}:hour24'>$hour24Options</select>\n";
      print "<select class=\"form-control\" name='{$fieldSchema['name']}:min'>$minOptions</select>\n";
      if ($fieldSchema['showSeconds']) { print "<select class=\"form-control\" name='{$fieldSchema['name']}:sec'>$secOptions</select>\n"; }
    }
    else {                                              // show 12 hour time
      print "<select class=\"form-control\" name='{$fieldSchema['name']}:hour12'>$hour12Options</select>\n";
      print "<select class=\"form-control\" name='{$fieldSchema['name']}:min'>$minOptions</select>\n";
      if ($fieldSchema['showSeconds']) { print "<select class=\"form-control\" name='{$fieldSchema['name']}:sec'>$secOptions</select>\n"; }
      print "<select class=\"form-control\" name='{$fieldSchema['name']}:isPM'>\n";
      print "<option value=''>AM/PM</option>\n";
      print "<option value='0' $amSelectedAttr>AM</option>\n";
      print "<option value='1' $pmSelectedAttr>PM</option>\n";
      print "</select>\n";
    }
  }

  print "<p style=\"display: inline\" class=\"help-block hidden-xs hidden-sm hidden-md\">$description</p></div>\n";
  print "<p class=\"help-block visible-xs visible-sm visible-md\">$description</p>";
  print "</div></div>\n";

}

//
function _showList($fieldSchema, $record, $fieldRowClass = null) {

  // set field attributes
  $recordArrayWithFilterFieldOnly = []; // on record add we want chained-selects to show subsets of data based on prepoulated data for parent fields that is passed in urls
  $recordArrayWithFilterFieldOnly[ @$fieldSchema['filterField'] ] = @$record[@$fieldSchema['filterField']] ?: @$_REQUEST[@$fieldSchema['filterField']];
  
  $listOptions = getListOptionsFromSchema($fieldSchema, $recordArrayWithFilterFieldOnly);
  $listOptions = applyFilters('_showList_listOptions', $listOptions, $fieldSchema);
  $valignTop   = ($fieldSchema['listType'] != 'pulldown') ? 'style="vertical-align: top;"' : '';
  $prefixText  = @$fieldSchema['fieldPrefix'];
  $description = getEvalOutput( @$fieldSchema['description'] );
  
  // construct prefix and description HTML
  $prefixHtml = '';
  if ($prefixText){
    $prefixHtml = '<span class="input-group-addon">'.$prefixText.'</span>';
  }
  $descriptionHtml = '';
  if ($description){
    $descriptionHtml = '<span class="input-group-addon">'.$description.'</span>';
  }

  // get field value
  $fieldname   = $fieldSchema['name'];
  if      ($record)                                 { $fieldValue = @$record[ $fieldname ]; }
  else if (array_key_exists($fieldname, $_REQUEST)) { $fieldValue = join("\t", (array) @$_REQUEST[ $fieldname ]); }
  else                                              { $fieldValue = getEvalOutput(@$fieldSchema['defaultValue']); }
  $fieldValue = $fieldValue ?? ''; // cannot be null as of PHP 8.1
  $fieldValues = preg_split("/\t/", $fieldValue, -1, PREG_SPLIT_NO_EMPTY); // for multi value fields
  $encodedValue  = htmlencode($fieldValue);
  
  // check if the list options are pulled from another table
  // ... and if the options count are greater than or equal to the max list options results set in getListOptionsFromSchema_maxResults()
  // ... if true, add a notice text if the list options as there may be more records that aren't loaded
  $maxListOptionsReturnedText = '';
  $listOptionsCount           = count($listOptions);
  $listOptionsMaxResults      = getListOptionsFromSchema_maxResults();
  if ($fieldSchema['optionsType'] == 'table' && $listOptionsCount >= $listOptionsMaxResults) {
    $maxListOptionsReturnedText = sprintf(t("Only the first %d options are shown"), $listOptionsCount);
  }

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
  print "<div class=\"form-group $fieldRowClass\">\n";
  print "<div class=\"col-sm-2 control-label\">{$fieldSchema['label']}</div>\n";
  print "<div class=\"col-sm-10\">\n";
  
  // pulldown
  if($fieldSchema['listType'] == 'pulldown') {
    
    if ($prefixText) { print "<p class=\"help-block\">$prefixText</p>\n"; }
    print "<div class=\"input-group col-xs-12 col-sm-8 col-md-6 col-lg-4\">\n";  
    print "<select class=\"form-control\" name='{$fieldSchema['name']}'>\n";
    print "<option value=''>&lt;select&gt;</option>\n";
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $selectedAttr = selectedIf($value, $fieldValue, true);
      $encodedLabel = htmlencode($label);
      print "<option value=\"$encodedValue\" $selectedAttr>$encodedLabel</option>\n";
    }
    
    // display notice that we're displaying only the first batch of list options
    if ($maxListOptionsReturnedText) {
      print "<optgroup label='$maxListOptionsReturnedText'></optgroup>\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "<optgroup label='$noLongerInListText'>\n";
      foreach ($fieldValuesNotInList as $value) {
        print "<option value=\"" .htmlencode($value). "\" selected='selected'>" .htmlencode($value). "</option>\n";
      }
      print "</optgroup>\n";
    }

    print "</select>\n";
    
    print "</div>\n";
    if ($description) { print "<p class=\"help-block\">$description</p>\n"; }
    
  }

  // multi pulldown
  else if ($fieldSchema['listType'] == 'pulldownMulti') {
    if ($prefixText) { print "<p class=\"help-block\">$prefixText</p>\n"; }
    print "<select class=\"form-control js-basic-multiple\" name='{$fieldSchema['name']}[]' multiple='multiple' size='5'>\n";
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $selectedAttr = (in_array($value, $fieldValues)) ? 'selected="selected"' : '';
      $encodedLabel = htmlencode($label);
      print "<option value=\"$encodedValue\" $selectedAttr>$encodedLabel</option>\n";
    }
    
    // display notice that we're displaying only the first batch of list options
    if ($maxListOptionsReturnedText) {
      print "<optgroup label='$maxListOptionsReturnedText'></optgroup>\n";
    }

    // show database values not in current list options
    if ($fieldValuesNotInList) {
      print "<optgroup label='$noLongerInListText'>\n";
      foreach ($fieldValuesNotInList as $value) {
        print "<option value=\"" .htmlencode($value). "\" selected='selected'>" .htmlencode($value). "</option>\n";
      }
      print "</optgroup>\n";
    }

    print "</select>\n";
    if ($description) { print "<p class=\"help-block\">$description</p>\n"; }
  }

  // radios
  else if ($fieldSchema['listType'] == 'radios') {
    if ($prefixText) { print "<p class=\"help-block\">$prefixText</p>\n"; }
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $encodedLabel = $label ? htmlencode($label) : '&nbsp;';
      $checkedAttr  = ($value == $fieldValue) ? 'checked="checked"' : '';
      $idAttr       = "{$fieldSchema['name']}.$encodedValue";

      print "<div class='radio'><label>\n";
      print "<input type='radio' name='{$fieldSchema['name']}' value='$encodedValue' id='$idAttr' $checkedAttr>\n";
      print $encodedLabel;
      print "</label></div>\n";
    }
    
    // display notice that we're displaying only the first batch of list options
    // ... and show database values not in current list options
    if ($fieldValuesNotInList || $maxListOptionsReturnedText) {
      print "<div class=\"col-xs-12 col-sm-8 col-md-6 col-lg-4 nopadding\">";
      print "<div class=\"well well-sm\">\n";
      
      if ($maxListOptionsReturnedText) {
        print "<strong>$maxListOptionsReturnedText</strong><br>\n";
      }
      
      if ($fieldValuesNotInList) {
        print "<strong>$noLongerInListText</strong><br>\n";
        
        foreach ($fieldValuesNotInList as $value) {
          $encodedValue = htmlencode($value);
          $encodedLabel = htmlencode($value);
          $idAttr       = "{$fieldSchema['name']}.$encodedValue";
          
          print "<div class='radio'><label>\n";
          print "<input type='radio' name='{$fieldSchema['name']}' value='$encodedValue' id='$idAttr' checked='checked'>\n";
          print $encodedLabel;
          print "</label></div>\n";
        }
      }
      print "</div>\n";
      print "</div>\n";
      print "<div class=\"clearfix\"></div>\n";
    }
    if ($description) { print "<p class=\"help-block\">$description</p>\n"; }
  }

  // checkboxes
  else if ($fieldSchema['listType'] == 'checkboxes') {
    if ($prefixText) { print "<p class=\"help-block\">$prefixText</p>\n"; }
    foreach ($listOptions as $optionArray) {
      list($value,$label) = $optionArray;
      $encodedValue = htmlencode($value);
      $encodedLabel = $label ? htmlencode($label) : '&nbsp;';
      $checkedAttr  = in_array($value, $fieldValues) ? 'checked="checked"' : '';
      $idAttr       = "{$fieldSchema['name']}.$encodedValue";

      print "<div class='checkbox'><label>\n";
      print "<input type='checkbox' name='{$fieldSchema['name']}[]' value='$encodedValue' id='$idAttr' $checkedAttr>\n";
      print $encodedLabel;
      print "</label></div>\n";
    }

    // display notice that we're displaying only the first batch of list options
    // ... and show database values not in current list options
    if ($fieldValuesNotInList || $maxListOptionsReturnedText) {
      print "<div class=\"col-xs-12 col-sm-8 col-md-6 col-lg-4 nopadding\">";
      print "<div class=\"well well-sm\">\n";
      
      if ($maxListOptionsReturnedText) {
        print "<strong>$maxListOptionsReturnedText</strong><br>\n";
      }
      
      if ($fieldValuesNotInList) {
        print "<strong>$noLongerInListText</strong><br>\n";
        foreach ($fieldValuesNotInList as $value) {
          $encodedValue = htmlencode($value);
          $encodedLabel = htmlencode($value);
          $idAttr       = "{$fieldSchema['name']}.$encodedValue";
          
          print "<div class='checkbox'><label>\n";
          print "<input type='checkbox' name='{$fieldSchema['name']}[]' value='$encodedValue' id='$idAttr' checked='checked'>\n";
          print $encodedLabel;
          print "</label></div>\n";
        }
      }
      print "</div>\n";
      print "</div>\n";
      print "<div class=\"clearfix\"></div>\n";
    }
    if ($description) { print "<p class=\"help-block\">$description</p>\n"; }
  }

  //
  else { die("Unknown listType '{$fieldSchema['listType']}'!"); }


  // list fields w/ advanced filters - add onchange event handler to local filter field
  if (@$fieldSchema['filterField']) {
    $targetListField = $fieldSchema['name'];
    $filterSchema    = $GLOBALS['schema'][$fieldSchema['filterField']];
    $sourceField     = (!empty($filterSchema['listType']) && ($filterSchema['listType'] == 'checkboxes' || $filterSchema['listType'] == 'pulldownMulti'))? $filterSchema['name']."[]" : $filterSchema['name'];
    ?>
    <script type="text/javascript"><!--
      $("[name='<?php echo $sourceField; ?>']").change(function () {
        <?php if(!empty($filterSchema['listType']) && $filterSchema['listType'] == 'pulldownMulti'): ?>
          newFilterValue = "\t";
          $.each($("[name='<?php echo $sourceField ?>']").select2('data'), function( key, selectedOption ) {
            newFilterValue = newFilterValue + selectedOption.id + "\t" ;
          });
        <?php elseif(!empty($filterSchema['listType']) && $filterSchema['listType'] == 'checkboxes') : ?>
          newFilterValue = "\t";
          $("[name='<?php echo $sourceField ?>']:checked").map(function(){
            newFilterValue = newFilterValue + $(this).val()+ "\t" ;
          });
        <?php else: ?>
          var newFilterValue  = this.value;
        <?php endif; ?>

        var targetListField = '<?php echo $targetListField ?>';
        updateListFieldOptions(targetListField, newFilterValue);
      });
    // --></script>
    <?php
  }

  //
  print "</div>\n";
  print "</div>\n";

}


//
function _showCheckbox($fieldSchema, $record, $fieldRowClass = null) {

  // set field attributes
  $checkedAttr = '';
  if      (array_key_exists($fieldSchema['name'], $_REQUEST))    { $checkedAttr = (@$_REQUEST[$fieldSchema['name']]) ? 'checked="checked"' : ''; } // v2.60
  else if ($record && !empty($record[$fieldSchema['name']]))     { $checkedAttr = 'checked="checked"'; }
  else if (!@$record['num'] && $fieldSchema['checkedByDefault']) { $checkedAttr = 'checked="checked"'; }
  $prefixText  = @$fieldSchema['fieldPrefix'];
  $description = @$fieldSchema['description'] ? getEvalOutput($fieldSchema['description']) : '&nbsp;'; // v2.52
  
  // construct prefix HTML
  $prefixHtml = '';
  if ($prefixText){
    $prefixHtml = '<p class="help-block">'.$prefixText.'</p>';
  }

  // display field
  print <<<__HTML__
    <div class="form-group $fieldRowClass">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        $prefixHtml
        <div class="checkbox">
          <input type="hidden" name="{$fieldSchema['name']}" value="0">
          <label>
            <input type="checkbox" name="{$fieldSchema['name']}" value="1" id="{$fieldSchema['name']}" $checkedAttr>
            $description
          </label>
        </div>
      </div>
    </div>
__HTML__;
}


//
function _showUpload($fieldSchema, $record, $fieldRowClass = null) {
  global $preSaveTempId, $SETTINGS, $menu;

  $prefixText  = @$fieldSchema['fieldPrefix'];
  $description = @$fieldSchema['description'];
  if ($prefixText) { $prefixText .= "<br>"; }

  // create uploadList url
  $uploadList = "?"
              . "menu=" . urlencode($menu)
              . "&amp;action=uploadList"
              . "&amp;fieldName=" . urlencode($fieldSchema['name'])
              . "&amp;num=" . urlencode(@$_REQUEST['num']??'')
              . "&amp;preSaveTempId=" . urlencode($preSaveTempId);

  // create uploadLink url
  $uploadLink = "?menu=" . urlencode($menu)
              . "&amp;action=uploadForm"
              . "&amp;fieldName=" . urlencode($fieldSchema['name'])
              . "&amp;num=" . urlencode(@$_REQUEST['num']??'')
              . "&amp;preSaveTempId=" . urlencode($preSaveTempId);

  // error checking
  $errors = '';
  list($uploadDir, $uploadUrl) = getUploadDirAndUrl( $fieldSchema );
  if     (!file_exists($uploadDir)) { mkdir_recursive($uploadDir); }  // create upload dir (if not possible, dir not exists error will show below)
  if     (!file_exists($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' doesn't exist!.<br>\n"; }
  elseif (!is_writable($uploadDir)) { $errors .= "Upload directory '" .htmlencode($uploadDir). "' isn't writable!.<br>\n"; }

  // display errors
  if ($errors) { print <<<__HTML__
    <div class="form-group $fieldRowClass">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        <div id='alert'><span>$errors</span></div>
      </div>
    </div>
__HTML__;
  return;
  }

  // display field
  ?>
    <div class="form-group <?php echo $fieldRowClass; ?>">
      <div class="col-sm-2 control-label">
        <?php echo $fieldSchema['label'] ?>
      </div>
      <div class="col-sm-10">
        <?php if ($prefixText) { print "<p class=\"help-block\">$prefixText</p>\n"; } ?>
        <iframe id="<?php echo $fieldSchema['name'] ?>_iframe" src="<?php echo $uploadList ?>" height="100" style="width:100%;" class="uploadIframe"></iframe><br>

        <?php $displayDefaultLink = applyFilters('edit_show_upload_link', true, $fieldSchema, $record); ?>
        <?php if ($displayDefaultLink): ?>

          <?php if(!$GLOBALS['SETTINGS']['advanced']['disableHTML5Uploader'] && !inDemoMode()): ?>

            <!-- Uploadifive -->
            <style>
              .uploadifive-button {
                margin: auto;
                padding: 0;
                display: block;
                max-width: 250px;
                width: 100%;
                font-weight: bold;
                color: #428bca;
                cursor: pointer;
              }
              .uploadifive-button input[type="file"] {
                cursor: pointer;
              }
            </style>
            <div class="uploadifyQueue" id="<?php echo $fieldSchema['name'] ?>_uploadQueue"></div>
            <input id="<?php echo $fieldSchema['name'] ?>_file_upload" name="file_upload" type="file" multiple="true">
            
            <?php media_showUploadifyLink($menu, $fieldSchema['name'], @$_REQUEST['num']); ?>
            
            <?php
              $isMac = (preg_match('/macintosh|mac os x/i', @$_SERVER['HTTP_USER_AGENT']));
              $key   = $isMac ? '<Command>' : '<Ctrl>';
              
              if (@$fieldSchema['checkMaxUploads'] == 0 || @$fieldSchema['maxUploads'] != 1) {
                echo '<div class="text-center">' . htmlencode( t("Tip: hold $key to select multiple files") ) . '</div>';
              }
            ?>
            <?php if ($description) { print "<p class=\"help-block\">$description</p>\n"; } ?>
            <!-- Uploadifive -->

            <script type="text/javascript">
                $(function(){
                  <?php
                    $timestamp = time();
                    
                    /** Dec 17 2018 * disable front-end file-type checking due to extension/mime type mismatches */
                    $fileTypeArray = ''; //preg_split("/\s*\,\s*/", strtolower($fieldSchema['allowedExtensions']));

                    // implement wildcard with empty fileType parameter
                    // if (in_array( '*', $fileTypeArray )) {
                    //   $fileTypeArray = [''];
                    // }

                    // // uploadifive checks file mime type, so jpg must also include jpeg ('image/jpg' is not a valid mime type)
                    // if (in_array('jpg', $fileTypeArray) && !in_array('jpeg', $fileTypeArray)) {
                    //   $fileTypeArray[] = 'jpeg';
                    // }
                    // if(in_array('docx', $fileTypeArray)) {
                    //   $fileTypeArray[] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                    // }
                    
                  ?>
                  $('#<?php echo $fieldSchema['name'] ?>_file_upload').uploadifive(generateUploadifiveOptions({
                    'uploadScript'           : <?php echo json_encode( basename(@$_SERVER['SCRIPT_NAME'] ) ); ?>,
                    'fileType' : <?php echo json_encode($fileTypeArray) ?>,
                    'modifyAfterSave'  : <?php echo count(getUploadInfoFields($fieldSchema['name'])); ?>,
                    'menu'             : <?php echo json_encode($menu); ?>,
                    'fieldName'        : <?php echo json_encode($fieldSchema['name']) ?>,
                    'num'              : <?php echo json_encode(@$_REQUEST['num'] ? $_REQUEST['num'] : '') ?>,
                    'preSaveTempId'    : <?php echo json_encode($preSaveTempId) ?>,
                    'buttonText'       : <?php echo json_encode(t('Add or Upload File(s)'));?>,
                    'buttonClass'      : 'uploadifive-button',
                    'removeCompleted'  : true,
                    'maxUploadSizeKB'  : <?php echo json_encode($fieldSchema['checkMaxUploadSize'] ? $fieldSchema['maxUploadSizeKB'] : 0) ?>,
                    'loginDataEncoded' : <?php echo json_encode( @$_COOKIE[loginCookie_name(true)] ) ?>,
                    'queueID'          : <?php echo json_encode($fieldSchema['name'] . "_uploadQueue") ?>,
                    'fileTypeExts'     : <?php echo json_encode(schemaFileExtForUploadify($fieldSchema['allowedExtensions'])); ?>,
                  }));
                });
            </script>

          <?php else: ?>

            <!-- Basic Uploader -->
            <div style="position: relative; height: 24px;">
              <div style="position: absolute; top: 6px; width: 100%; text-align: center;">
                <?php if (inDemoMode()): ?>
                  <a href="javascript:alert('<?php echo jsEncode(t('This feature is disabled in demo mode.')) ?>')"><b><?php echo t('Add or Upload File(s)') ?></b></a>
                <?php else: ?>
                  <a href="javascript:showModal('<?php echo jsEncode($uploadLink) ?>')"><b><?php echo t('Add or Upload File(s)') ?></b></a>
                <?php endif ?>
              </div>
              <div style="position: absolute; z-index: 1; width: 100%; text-align: center;">
                <div id="<?php echo $fieldSchema['name'] ?>_uploadButton"></div>
              </div>
            </div>
            <!-- Basic Uploader -->

          <?php endif ?>
        <?php endif ?>
      </div>
    </div>
  <?php
}

//
function _showSeparator($fieldSchema, $record) {
  $field = createFieldObject_fromSchema($fieldSchema);
  $html  = $field->getTableRow($record, '', 'edit');
  print $html;
}

//
function _showHidden($fieldSchema, $record) {
  // set field attributes
  $fieldname   = $fieldSchema['name'];

  // get field value
  if      ($record)                                 { $fieldValue = @$record[ $fieldname ]; }
  else if (array_key_exists($fieldname, $_REQUEST)) { $fieldValue = @$_REQUEST[ $fieldname ]; }
  else                                              { $fieldValue = getEvalOutput(@$fieldSchema['defaultContent']); }

  $encodedValue  = htmlencode($fieldValue);

  // display field
  print <<<__HTML__

        <input type="hidden" value="{$encodedValue}" name="{$fieldSchema['name']}">
__HTML__;
}

// open HTML for tab group, plus tab navigation
function _showTabGroupStart($fieldSchema, $tableSchema) {
  $field = createFieldObject_fromSchema($fieldSchema);
  print $field->tabGroupStart($tableSchema);
}

// close HTML for tab group
function _showTabGroupEnd($fieldSchema) {
  $field = createFieldObject_fromSchema($fieldSchema);
  print $field->tabGroupEnd();
}

// open HTML for tab panel
function _showTabPanelStart($fieldSchema, $first = false) {
  $field = createFieldObject_fromSchema($fieldSchema);
  print $field->tabPanelStart($first);
}

// close HTML for tab panel
function _showTabPanelEnd($fieldSchema) {
  $field = createFieldObject_fromSchema($fieldSchema);
  print $field->tabPanelEnd();
}



//
function _showAccessList($fieldSchema, $record) {
  $field = createFieldObject_fromSchema($fieldSchema);
  $value = '';
  print $field->getTableRow($record, $value, 'edit');
}


//
function _showrelatedRecords($fieldSchema, $record) {
  $field = createFieldObject_fromSchema($fieldSchema);
  $value = @$record[$fieldSchema['name']];
  print $field->getTableRow($record, $value, 'edit');
}

//
function _showParentCategory($fieldSchema, $record, $schema) {
  global $escapedTableName, $CURRENT_USER;

  // set field attributes
  $fieldValue  = $record ? @$record[$fieldSchema['name']] : '';

  // load categories
  $categoriesByNum = [];
  $query = "SELECT * FROM `$escapedTableName` ORDER BY globalOrder";
  $result = mysqli()->query($query) or die("MySQL Error: " .mysqli()->error. "\n");
  while ($row = $result->fetch_assoc()) {
    $isOwner = @$row['createdByUserNum'] == $CURRENT_USER['num'];
    if (@$row['createdByUserNum'] && (!$isOwner && !$GLOBALS['hasEditorAccess'])) { continue; }
    $categoriesByNum[ $row['num'] ] = $row;
  }
  if (is_resource($result)) { mysqli_free_result($result); }
  
  // get the current depth and the deepest sub-category depth for determining if a parent category would cause the branch depth to exceed the max depth
  $escapedRecordNum   = mysql_escape(@$record['num']);
  $deepestSubCategory = mysql_get($schema['_tableName'], null, "lineage LIKE '%:$escapedRecordNum:%' AND num != '$escapedRecordNum' ORDER BY depth DESC"); // get the deepest sub-category
  $currentDepth       = $record ? $record['depth'] : 0; // get the depth level of the current category record
  $deepestDept        = $deepestSubCategory ? $deepestSubCategory['depth'] : $currentDepth; // if there is no deepest depth, the current one is the deepest
  
  //
  print "<div class=\"form-group\">";
  print "<div class=\"col-sm-2 control-label\">{$fieldSchema['label']}</div>\n";
  print "<div class=\"col-sm-10\">\n";

  print "  <select class=\"form-control\" name='{$fieldSchema['name']}'>\n";
  print "  <option value='0'>" . t('None (top level)') . "</option>\n";
  foreach ($categoriesByNum as $num => $category) {
    $value           = $category['num'];
    $selectedAttr    = selectedIf($value, $fieldValue, true);
    $encodedLabel    = htmlencode($category['breadcrumb']);
    $exceedsMaxDepth = category_exceedMaxDepth($currentDepth, $deepestDept, $category, @$schema['_maxDepth']);
    $isUnavailable   = $exceedsMaxDepth || preg_match("/:" .@$record['num']. ":/", $category['lineage']);
    $extraAttr       = $isUnavailable ? "style='color: #AAA' disabled='disabled' " : '';

    print "<option value=\"$value\" $extraAttr $selectedAttr>$encodedLabel</option>\n";
  }
  print "  </select>\n";

  print "</div>\n";
  print "</div>\n";

}


//
function _showDateCalendar($fieldSchema, $record) {
  global $TABLE_PREFIX, $tableName;
  $calendarTable = $TABLE_PREFIX . "_datecalendar";

  // get dates
  $dates      = [];
  $date       = getdate();
  $monthNum   = $date['mon'];
  $year       = $date['year'];
  $firstMonth = sprintf("%04d%02d%02d", $year, $monthNum, '01');
  for ($i=1; $i<=12; $i++) {
    $dates[] = array('year' => $year, 'monthNum' => $monthNum);
    if (++$monthNum > 12) { $year++; $monthNum = 1; }
  }
  $lastMonth  = sprintf("%04d%02d%02d", $year, $monthNum, '01');

  // load dates from database
  $selectedDates = [];
  $query  = "SELECT DATE_FORMAT(date, '%Y%m%d') as date FROM `$calendarTable` ";
  $query .= "WHERE `tablename` = '$tableName' ";
  $query .= "  AND `fieldname` = '{$fieldSchema['name']}' ";
  $query .= "  AND `recordNum` = '".mysql_escape($_REQUEST['num'])."' ";
  $query .= "  AND '$firstMonth' <= `date` AND `date` <= '$lastMonth'";
  $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
  while ($row = $result->fetch_assoc()) {
    $selectedDates[ $row['date'] ] = 1;
  }
  if (is_resource($result)) { mysqli_free_result($result); }

  // get calendar HTML
  $calendarHtml = '';
  foreach ($dates as $date) {
    $calendarHtml .= _createEditCalendar($fieldSchema['name'], $date['monthNum'], $date['year'], $selectedDates);
  }

  // display field
  print <<<__HTML__
    <div class="form-group">
      <div class="col-sm-2 control-label">
        {$fieldSchema['label']}
      </div>
      <div class="col-sm-10">
        $calendarHtml
      </div>
    </div>
__HTML__;

}


//
function _createEditCalendar($fieldname, $monthNum, $year, $selectedDates) {
  global $TABLE_PREFIX;
  $html = '';

  // display header
  static $monthNames = array('null','January','February','March','April','May','June','July','August','September','October','November','December');
  $monthName = $monthNames[$monthNum];
  $html .= "<table border='1' cellspacing='0' cellpadding='2' style='float: left; margin: 10px'>\n";
  $html .= "<tr><th colspan='7' class='mo'>$monthName $year</th></tr>\n";
  $html .= "<tr><th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th></tr>\n";
  $html .= "\n<tr>\n";

  // display leading blank days
  $dayOfWeekCount = 1;
  $firstDayTime   = mktime(0, 0, 0, $monthNum, 1, $year);
  $firstDayOffset = date('w', $firstDayTime);
  for ($i=1; $i <= $firstDayOffset; $i++) {
    $html .= "<td><span class='dte0'>&nbsp;</span></td>\n";
    $dayOfWeekCount++;
  }

  // print days of month
  $rows = 1;
  $daysInMonth = cal_days_in_month(0, $monthNum, $year);
  foreach (range(1,$daysInMonth) as $dayNum) {
    $dateString  = sprintf("%04d%02d%02d", $year, $monthNum, $dayNum);
    $checkedAttr = @$selectedDates[$dateString] ? 'checked="checked"' : '';

    $html .= "<td>";
    $html .= "<label for='$fieldname:$dateString'>&nbsp;$dayNum&nbsp;</label>";
    $html .= "<input type='hidden'   name='$fieldname:$dateString' value='0'>";
    $html .= "<input type='checkbox' name='$fieldname:$dateString' id='$fieldname:$dateString' value='1' $checkedAttr style='margin: 0px'>";

    $html .= "</td>\n";
    if ($dayOfWeekCount == 7) {
      $html .= "</tr>\n\n<tr>\n";
      $dayOfWeekCount = 0;
      $rows++;
    }
    $dayOfWeekCount++;
  }

  // display trailing blank days
  while ($dayOfWeekCount <= 7) {
    $html .= "<td><span class='dte0'>&nbsp;</span></td>\n";
    $dayOfWeekCount++;
  }
  $html .= "</tr>\n";

  // display 6 rows (even if last row is all blank)
  while ($rows < 6) {
    $html .= "<tr>\n";
    foreach (range(1,7) as $n) {
      $html .= "<td><span class='dte0'>&nbsp;</span></td>\n";
    }
    $html .= "</tr>\n";
    $rows++;
  }

  // display footer
  $html .= "</table>\n\n";

  //
  return $html;
}

//
function showWysiwygGeneratorCode() {
  global $schema, $SETTINGS;

  // get wysiwyg list
  $wysiwygListAsCSV = '';

  foreach ($schema as $name => $fieldHash) {
    if (!is_array($fieldHash))            { continue; } // fields are stored as arrays, other entries are table metadata
    if (@$fieldHash['type'] != 'wysiwyg') { continue; } // skip all but wysiwyg fields

    $fieldSchema           = array('name' => $name) + $fieldHash;
    $fieldname             = $fieldSchema['name'];
    $uploadBrowserCallback = @$fieldSchema['allowUploads'] ? "wysiwygUploadBrowser" : '';
    initWysiwyg("field_$fieldname", $uploadBrowserCallback);
  }
}

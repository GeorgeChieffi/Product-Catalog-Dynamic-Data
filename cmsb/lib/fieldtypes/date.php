<?php

class DateField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}


//
function getDisplayValue($record) {

  // XXX: this is also called statically by NoneField->getDisplayValue!

  // get date format
  if     (@$SETTINGS['dateFormat'] == 'dmy') { $dateFormat  = "jS M, Y - h:i:s A"; }
  elseif (@$SETTINGS['dateFormat'] == 'mdy') { $dateFormat  = "M jS, Y - h:i:s A"; }
  else                                       { $dateFormat  = "M jS, Y - h:i:s A"; }

  // format date
  $value = parent::getDatabaseValue($record);
  if      (!$value && @$record['num'])      { $value = ''; } // for existing-records with empty or null value for date
  elseif  (!$value && !@$record['num'])     { $value = date($dateFormat); } // for new unsaved records, show current time
  else if ($value == '0000-00-00 00:00:00') { $value = ''; } // for records with no previous value
  else                                      { $value = date($dateFormat, strtotime($value)); }

  //
  return $value;
}


// editFormHtml
function editFormHtml($record) {
  global $SETTINGS;
  $mysqlDateFormat = 'Y-m-d H:i:s';
  $prefixText      = @$this->fieldPrefix;
  $description     = getEvalOutput( @$this->description );

  // get default date
  if     (@$this->defaultDate == 'none')   { $defaultDateTime = ''; }
  elseif (@$this->defaultDate == 'custom') { $defaultDateTime = @date($mysqlDateFormat, strtotime($this->defaultDateString)); }
  else                                     { $defaultDateTime = date($mysqlDateFormat); }

  // get date value(s)
  $dateValue  = @$record[$this->name] ? $record[$this->name] : $defaultDateTime;
  list($date,$time,$year,$month,$day,$hour24,$min,$sec,$amOrPm,$hour12) = array(null,null,null,null,null,null,null,null,null,null);
  if ($dateValue && $dateValue != '0000-00-00 00:00:00') { // mysql will default undefined dates to null or 0000-00-00 00:00:00
    list($date,$time)       = explode(' ', $dateValue); // expecting: YYYY-MM-DD HH:MM:SS
    list($year,$month,$day) = explode('-', $date);      // expecting: YYYY-MM-DD
    list($hour24,$min,$sec) = explode(':', $time);      // expecting: HH:MM:SS
    $amOrPm = $hour24 >= 12 ? 'PM' : 'AM';
    $hour12 = (($hour24 % 12) == 0) ? 12 : $hour24 % 12;
  }

  // get month options
  $monthOptions    = "<option value=''><!-- --></option>\n";
  $shortMonthNames = preg_split("/\s*,\s*/", t('Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec'));
  foreach (range(1,12) as $num) {
    $selectedAttr   = selectedIf($num, $month, true);
    $shortMonthName = @$shortMonthNames[$num-1];
    $monthOptions .= "<option value=\"$num\" $selectedAttr>$shortMonthName</option>\n";
  }

  // get day options
  $dayOptions    = "<option value=''><!-- --></option>\n";
  foreach (range(1,31) as $num) {
    $selectedAttr  =  selectedIf($num, $day, true);
    $dayOptions   .= "<option value=\"$num\" $selectedAttr>$num</option>\n";
  }

  // get year options
  $yearOptions    = "<option value=''><!-- --></option>\n";
  if (!$this->yearRangeStart) { $this->yearRangeStart = date('Y')-5; }  // v2.16 - default to 5 years previous if undefined
  if (!$this->yearRangeEnd)   { $this->yearRangeEnd   = date('Y')+5; }  // v2.16 - default to 5 years ahead if undefined
  foreach (range((int) $this->yearRangeStart, (int) $this->yearRangeEnd) as $num) { // (int) for range bug in PHP < 4.23 - see docs
    $selectedAttr  = selectedIf($num, $year, true);
    $yearOptions  .= "<option value=\"$num\" $selectedAttr>$num</option>\n";
  }

  // get hour options
  $hour24Options = "<option value=''><!-- --></option>\n";
  $hour12Options = "<option value=''><!-- --></option>\n";
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
  $minOptions = "<option value=''><!-- --></option>\n";
  foreach (range(0,59) as $num) {
    $zeroPaddedNum = sprintf("%02d", $num);
    $selectedAttr  = selectedIf($num, $min, true);
    $minOptions   .= "<option value=\"$num\" $selectedAttr>$zeroPaddedNum</option>\n";
  }

  // get second options
  $secOptions    = "<option value=''><!-- --></option>\n";
  foreach (range(0,59) as $num) {
    $zeroPaddedNum = sprintf("%02d", $num);
    $selectedAttr  = selectedIf($num, $sec, true);
    $secOptions   .= "<option value=\"$num\" $selectedAttr>$zeroPaddedNum</option>\n";
  }

  // get AmPm optins
  $amSelectedAttr = selectedIf($amOrPm, 'AM', true);
  $pmSelectedAttr = selectedIf($amOrPm, 'PM', true);

  // display date field
  print "<div class=\"form-group\">\n";
  print "<div class=\"col-sm-2\">{$this->label}</div>\n";
  print "<div class=\"col-sm-9\">$prefixText\n";

  $monthsField = "<select class=\"form-control\" name='{$this->name}:mon'>$monthOptions</select>\n";
  $daysField   = "<select class=\"form-control\" name='{$this->name}:day'>$dayOptions</select>\n";
  if ($SETTINGS['dateFormat'] == 'dmy') { print $daysField . $monthsField; }
  else                                  { print $monthsField . $daysField; }
  print "<label><select class=\"form-control\" name='{$this->name}:year'>$yearOptions</select></label>\n";

  if ($this->showTime) {
    print "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\n";
    if ($this->use24HourFormat) { // show 24 hour time
      print "<select class=\"form-control\" name='{$this->name}:hour24'>$hour24Options</select>\n";
      print "<select class=\"form-control\" name='{$this->name}:min'>$minOptions</select>\n";
      if ($this->showSeconds) { print "<select class=\"form-control\" name='{$this->name}:sec'>$secOptions</select>\n"; }
    }
    else {                                              // show 12 hour time
      print "<select class=\"form-control\" name='{$this->name}:hour12'>$hour12Options</select>\n";
      print "<select class=\"form-control\" name='{$this->name}:min'>$minOptions</select>\n";
      if ($this->showSeconds) { print "<select class=\"form-control\" name='{$this->name}:sec'>$secOptions</select>\n"; }
      print "<select class=\"form-control\" name='{$this->name}:isPM'>\n";
      print "<option value=''><!-- --></option>\n";
      print "<option value='0' $amSelectedAttr>AM</option>\n";
      print "<option value='1' $pmSelectedAttr>PM</option>\n";
      print "</select>\n";
    }
  }

  print "$description</div>\n";
  print "</div>\n";
}

} // end of class

?>

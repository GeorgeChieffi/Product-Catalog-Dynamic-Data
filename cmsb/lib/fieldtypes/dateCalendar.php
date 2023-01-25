<?php

class DateCalendarField extends Field {
  
  function __construct($fieldSchema) {
    parent::__construct($fieldSchema);
  }
  
  // editFormHtml
  function editFormHtml($record) {
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
    $query .= "  AND `fieldname` = '{$this->name}' ";
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
      $calendarHtml .= _createEditCalendar($date['monthNum'], $date['year'], $selectedDates);
    }
  
    // display field
    print <<<__HTML__
      <div class="form-group">
        <div class="col-sm-2">{$this->label}</div>
        <div class="col-sm-9">$calendarHtml</div>
      </div>
__HTML__;

  }
  
  
  //
  function _createEditCalendar($monthNum, $year, $selectedDates) {
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
      $html .= "<label for='$this->name:$dateString'>&nbsp;$dayNum&nbsp;</label>";
      $html .= "<input type='hidden'   name='$this->name:$dateString' value='0'>";
      $html .= "<input type='checkbox' name='$this->name:$dateString' id='$this->name:$dateString' value='1' $checkedAttr style='margin: 0px'>";
  
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
  
}

?>
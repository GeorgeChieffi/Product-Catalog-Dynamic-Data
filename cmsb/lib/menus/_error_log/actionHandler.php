<?php
// PHP Error Log Menu

// define globals
$GLOBALS['APP']['selectedMenu'] = 'admin'; // show admin menu as selected

// check access level - admin only!
if (!$GLOBALS['CURRENT_USER']['isAdmin']) {
  alert(t("You don't have permissions to access this menu."));
  showInterface('');
}

// menu plugin hooks
addAction('section_preDispatch',     '_pel_showModeNotice',  null, 2);
addFilter('listHeader_displayLabel', '_pel_cmsList_messageColumn', null, 3);
addFilter('listRow_displayValue',    '_pel_cmsList_messageColumn', null, 4);

// Prefix Menu with "Admin >"
addFilter('adminUI_args', function($adminUI) {
  array_unshift($adminUI['PAGE_TITLE'], t('Admin'));
  return $adminUI;
});

// Dispatch Actions
if ($GLOBALS['action'] == 'clearLog') { // clear error log
  mysql_delete($GLOBALS['schema']['_tableName'], null, 'true');
  redirectBrowserToURL("?menu=" . $GLOBALS['schema']['_tableName']);
}

// Let regular actionHandler run
$REDIRECT_FOR_CUSTOM_MENUS_DONT_EXIT = true;
return;

//
function _pel_showModeNotice($tableName, $action) {
  if ($action != 'list') { return; }

  #$notice = sprintf(t("Send &amp; Log - Send mail and save copies under <a href='%s'>Outgoing Mail</a>"), "?menu=_outgoing_mail");
  $notice = t("Any PHP errors or warnings from the website or CMS will be logged here.");
  $notice = t("Developer Log"). ": " . $notice . " (<a href='?menu=$tableName&action=clearLog'>" .t("Clear Log"). "</a>)";
  notice($notice);
}


//
function _pel_cmsList_messageColumn($displayValue, $tableName, $fieldname, $record = []) {
  if ($tableName != '_error_log')    { return $displayValue; } // skip all by our table

  //
  if ($fieldname == 'dateLogged') {
    if (!$record) { return str_replace(' ', '&nbsp;', t("Date / When")); } // header - we detect the header hook by checking if the 4th argument is set
    $displayValue = "<div title='" .htmlencode($record['dateLogged']). "'>"; 
    $displayValue .= str_replace(' ', '&nbsp;', prettyDate( $record['dateLogged']));  // row cell - we detect the row cell by checking if $record is set
    $displayValue .= "</div>"; 
  }

  //
  if ($fieldname == '_error_summary_') {
    if (!$record) { return t("Error Details"); } // header - we detect the header hook by checking if the 4th argument is set
    // row cell - we detect the row cell by checking if $record is set
    
    // get truncated url
    $truncatedUrl = $record['url'];
    $maxLength    = 90;
    if (preg_match("/^(.{0,$maxLength})(\s|$)/s", $truncatedUrl, $matches)) { $truncatedUrl = $matches[1]; } // chop at first whitespace break before max chars
    else { $truncatedUrl = mb_substr($truncatedUrl, 0, $maxLength); } // otherwise force cut at maxlength (for content with no whitespace such as malicious or non-english)
    if (strlen($truncatedUrl) < strlen($record['url'])) { $truncatedUrl .= " ..."; }
    
    //
    $lineNumSuffix = $record['line_num'] ? " (line {$record['line_num']})" : "";
    $displayValue  = "<div style='line-height:1.5em'>\n";
    $displayValue .= nl2br(htmlencode("#{$record['num']} - {$record['error']}\n{$record['filepath']} $lineNumSuffix\n$truncatedUrl"));
    $displayValue .= "</div>"; 
    
    //$displayValue  = "<table border='0' cellspacing='0' cellpadding='0' class='spacedTable'>\n"; 
    //                           $displayValue .= "  <tr><td>" .t('Error').    "</td><td>&nbsp:&nbsp;</td><td>" .htmlencode($record['error']).    "</div></td></tr>\n"; 
    //if ($record['url'])      { $displayValue .= "  <tr><td>" .t('URL').      "</td><td>&nbsp:&nbsp;</td><td>" .htmlencode($record['url']).      "</div></td></tr>\n"; }
    //if ($record['filepath']) { $displayValue .= "  <tr><td>" .t('Filepath'). "</td><td>&nbsp:&nbsp;</td><td>" .htmlencode($record['filepath']). "</div></td></tr>\n";   }
    //$displayValue .= "  </table>\n"; 

  }


  return $displayValue;
}


//eof

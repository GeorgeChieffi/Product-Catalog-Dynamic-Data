<?php


// send an email message with attachments.
// If text _and_ html are specified the email client will decide what to display.
/*
  // Minimal Example:
  $errors = sendMessage(array(
    'from'    => "from@example.com",
    'to'      => "to@example.com",
    'subject' => "Enter subject here, supports utf-8 content",
    'text'    => "Text message content",
  ));
  if ($errors) { die($errors); }


  // Full Featured Example:
  $errors = sendMessage(array(
    'from'    => "from@example.com",
    'to'      => "to@example.com",
    'subject' => "Enter subject here, supports utf-8 content",
    'text'    => "Text message content",
    'html'    => "<b>HTML</b> message content",
   'headers' => array(
      "CC"              => "cc@example.com",
      "BCC"             => "bcc@example.com",
      "Reply-To"        => "rt@example.com",
      "Return-Path"     => "rp@example.com",
      "x-custom-header" => "12345",
    ),
    'attachments' => array(
      'simple.txt'  => 'A simple text file',
      'dynamic.csv' => $csvData,
      'archive.zip' => $binaryData,
      'image.jpg'   => file_get_contents($imagePath),
    ),
    //'disabled' => false, // set to true to disable sending of message
    //'logging' => false, // set to false to disable logging (if logging is already enabled) - used for background mailers
  ));
  if ($errors) { die($errors); }

*/
function sendMessage($options = []) {

  // allow hooks to override
  $eventState = array('cancelEvent' => false, 'returnValue' => null);
  $eventState = applyFilters('sendMessage', $eventState, $options);
  if ($eventState['cancelEvent']) { return $eventState['returnValue']; }

  // allow hooks to override
  $options = applyFilters('sendMessage_options', $options);

  // don't send if 'disabled' option set
  if (@$options['disabled']) { return; }

  ### v2.52 Notes: PHP's mail() function is very broken, on windows it talks directly to a SMTP server, or linux it talks to sendmail/qmail/postfix, etc.
  ### ... The PHP docs and RFCs say to use CRLF as an EOL in messages, but QMail and other Mail Transfer Agents (MTA) assume input is using the
  ### ... OS's default EOL char and converts LF to CRLF so it's standard compliant, which also causes CRLF to be translated to CRCRLF.  So there's
  ### ... no OS-independent way to make it work.  For these reasons, we bypass PHP mail() altogether and use the Swift Mailer library.


  // error checking
  $errors         = '';
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);
  $hasAttachments = array_key_exists('attachments', $options);
  $cc             = !empty($options['headers']['CC'])          ? $options['headers']['CC']          : false;
  $bcc            = !empty($options['headers']['BCC'])         ? $options['headers']['BCC']         : false;
  $replyTo        = !empty($options['headers']['Reply-To'])    ? $options['headers']['Reply-To']    : false;
  $returnPath     = !empty($options['headers']['Return-Path']) ? $options['headers']['Return-Path'] : false;
  if (!isValidEmail( @$options['to'], true))       { $errors .= "'to' isn't a valid email '" .htmlencode($options['to']). "'!<br>\n"; }
  if (!isValidEmail( @$options['from'] ))          { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br>\n"; }
  if ($cc && !isValidEmail( $cc, true ))           { $errors .= "'CC' isn't a valid email '" .htmlencode($cc). "'!<br>\n"; }
  if ($bcc && !isValidEmail( $bcc, true ))         { $errors .= "'BCC' isn't a valid email '" .htmlencode($bcc). "'!<br>\n"; }
  if ($replyTo && !isValidEmail( $replyTo ))       { $errors .= "'Reply-To' isn't a valid email '" .htmlencode($replyTo). "'!<br>\n"; }
  if ($returnPath && !isValidEmail( $returnPath )) { $errors .= "'Return-Path' isn't a valid email '" .htmlencode($returnPath). "'!<br>\n"; }
  if (!array_key_exists('subject', $options))      { $errors .= "'subject' must be defined!<br>\n"; }
  if (!$hasText && !$hasHTML)                      { $errors .= "Either 'text' or 'html' or both must be defined!<br>\n"; }
  if ($errors) { return $errors; }

  // optionally log message and/or skip sending (log only - if enabled)
  $mode = $GLOBALS['SETTINGS']['advanced']['outgoingMail'];
  if ($mode != 'sendOnly' && @$options['logging'] !== false) {
    $colsToValues = array_slice_keys($options, array('from','to','subject','text','html'));
    $colsToValues['createdDate='] = 'NOW()';
    $colsToValues['sent=']        = ($mode == 'logOnly') ? '""' : 'NOW()';
    $colsToValues['headers']      = '';
    if (@$options['headers']) { // v2.52 log message headers (previously died with "logging message headers not supported"
      foreach ($options['headers'] as $name => $value) { $colsToValues['headers'] .= "$name: $value\n"; }
    }

    $colsToValues['reply-to'] = $replyTo;
    $colsToValues['cc']       = $cc;
    $colsToValues['bcc']      = $bcc;
    mysql_insert('_outgoing_mail', $colsToValues, true);
  }
  if ($mode == 'logOnly') { return; }  // don't send if "log only" set

  // debug
//  showme($options);

  // send message with swift mailer
  $errors = _sendMessage_swiftMailer($options);
  return $errors;

}


// helper function for sendMessage() - takes $options array from sendMessage() and sends message using swiftMailer
function _sendMessage_swiftMailer($options) {
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);
  $hasAttachments = array_key_exists('attachments', $options);

  ### Load Swift Mailer (overriding encoding if needed)
  require_once(CMS_ASSETS_DIR."/3rdParty/SwiftMailer5/swift_required.php");
  $overrideEncoding = (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2);
  if ($overrideEncoding) { $oldEncoding = mb_internal_encoding(); mb_internal_encoding('ASCII'); } // workaround for if mbstring.func_overload is enabled - http://swiftmailer.org/docs/installing.html#troubleshooting

  ### Create message - Ref: http://swiftmailer.org/docs/messages.html
  $message = Swift_Message::newInstance();
  $message->setSubject($options['subject']);
  if     ($hasText && $hasHTML) { $message->setBody($options['html'], 'text/html')->addPart($options['text'], 'text/plain'); }
  elseif ($hasHTML)             { $message->setBody($options['html'], 'text/html'); }
  elseif ($hasText)             { $message->setBody($options['text'], 'text/plain'); }

  // Set From: email
  $emailComponents = isValidEmail($options['from'], false); // DO NOT allow multiple - returns array of: array('email@example.com', 'Full Name', "Bob Jones <this@that.com>"
  if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->setFrom($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}

  // Set To: email(s)
  $emailComponents = isValidEmail($options['to'], true); // allows multiple
  if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->addTo($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}

  // Set CC: email(s)
  if (@$options['headers']['CC']) {
    $emailComponents = isValidEmail($options['headers']['CC'], true); // allows multiple
    if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->addCc($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}
  }

  // Set BCC: email(s)
  if (@$options['headers']['BCC']) {
    $emailComponents = isValidEmail(@$options['headers']['BCC'], true); // allows multiple
    if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->addBcc($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}
  }

  // Set Reply-To: email (default to From: if not defined)
  $emailComponents = isValidEmail(@$options['headers']['Reply-To'], false) ?: isValidEmail($options['from'], false); // DO NOT allow multiple
  if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->setReplyTo($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}

  // Set Return-Path: email (default to From: if not defined) - ### This is where bounces are sent
  $emailComponents = isValidEmail(@$options['headers']['Return-Path'], false) ?: isValidEmail($options['from'], false); // DO NOT allow multiple
  if (is_array( $emailComponents )) { foreach ($emailComponents as $emailNameArray) { $message->setReturnPath($emailNameArray[0], nullIfFalse($emailNameArray[1])); }}

  // Add attachments
  if (@$options['attachments']) {
    foreach ($options['attachments'] as $filename => $filedata) {
      $message->attach( Swift_Attachment::newInstance($filedata, $filename) );
    }
  }

  //Add custom headers
  if (!empty($options['headers']) && is_array($options['headers'])) {
    $headers = $message->getHeaders();
    foreach($options['headers'] as $headerName => $headerLabel) {
      if(startsWith('x-', strtolower($headerName))) { //We only add headers that start with x-, as this is the valid prefix for a custom header: https://tools.ietf.org/html/rfc822
        $headers->addTextHeader($headerName, $headerLabel);
      }
    }
  }

  // debug
  //header("Content-type: text/plain");
  //print $message->toString();
  //exit;

  ### Define transport
  $mailingMethod = $GLOBALS['SETTINGS']['advanced']['smtp_method'] ?: 'php';
  if ($mailingMethod == 'php') {
    $transport = Swift_MailTransport::newInstance();
  }
  else {
    // get port
    $userDefinedPort = $GLOBALS['SETTINGS']['advanced']['smtp_port'];
    if     ($userDefinedPort)         { $port = $userDefinedPort; }
    elseif ($mailingMethod == 'ssl')  { $port = '587'; }
    elseif ($mailingMethod == 'tls')  { $port = '465'; }
    elseif (get_cfg_var('smtp_port')) { $port = get_cfg_var('smtp_port'); }
    else                              { $port = '25'; }

    // get transport
    $transport = Swift_SmtpTransport::newInstance();
    $transport->setHost( $GLOBALS['SETTINGS']['advanced']['smtp_hostname'] ?: get_cfg_var('SMTP') );
    $transport->setPort( $GLOBALS['SETTINGS']['advanced']['smtp_port']     ?: get_cfg_var('smtp_port') );
    if ($mailingMethod == 'ssl') { $transport->setEncryption('ssl'); } // Future: If any servers don't have these add error checking with stream_get_transports()
    if ($mailingMethod == 'tls') { $transport->setEncryption('tls'); } // Future: If any servers don't have these add error checking with stream_get_transports()
    if ($GLOBALS['SETTINGS']['advanced']['smtp_username'] != '') { $transport->setUsername( $GLOBALS['SETTINGS']['advanced']['smtp_username'] ); }
    if ($GLOBALS['SETTINGS']['advanced']['smtp_password'] != '') { $transport->setPassword( $GLOBALS['SETTINGS']['advanced']['smtp_password'] ); }
    $transport->setTimeout(4);
    //ini_set("default_socket_timeout", 3); // not used yet
  }

  ### Send Message
  $messagesSent     = 0;
  $failedRecipients = [];
  $errors           = '';
  $sendStartTime    = microtime(true);
  static $totalMessagesSent = 0;
  static $iterations = 0;
  try {
    $iterations++;
    $transport->start();  // Catch and report authentication errors early
    $mailer       = Swift_Mailer::newInstance($transport);
    $messagesSent = $mailer->send($message, $failedRecipients);  // $messagesSent adds one for each address on TO: line, CC, BCC, address etc
    $totalMessagesSent += $messagesSent;
    //$mailer->getTransport()->stop(); // Possible bug fix?  Stop mailer so it will cleanly restart on next try: http://stackoverflow.com/a/17110512
  }
  catch (Exception $e) { // catch errors
    //$mailer->getTransport()->stop(); // Stop mailer on error so it will cleanly restart on next try: http://stackoverflow.com/a/22629213
    $thisMessageTime   = sprintf("%0.2f", microtime(true) - $sendStartTime);
    $totalScriptTime   = sprintf("%0.2f", microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
    $error             = $e->getMessage() . " (total iterations: $iterations, send time: {$thisMessageTime}s, emails sent: {$messagesSent}, total emails sent: {$totalMessagesSent}, total script time: {$totalScriptTime}s)";
    @trigger_error($error, E_USER_NOTICE);   // log error
    $errors .= $e->getMessage() . "\n";      // return error
  }

  // Restore encoding
  if ($overrideEncoding) { mb_internal_encoding($oldEncoding); }

  // return errors
  $finalErrors = '';
  if ($messagesSent == 0) { $finalErrors .= t("No messages were sent, check mail server settings!") ."\n"; }
  if ($failedRecipients) {
    $emailsAsCSV = htmlencode(implode(', ', $failedRecipients));
    $finalErrors .= sprintf(t("Email delivery failed for: %s") . "\n", $emailsAsCSV);
  }
  $finalErrors .= $errors;
  return $finalErrors;

}

// Alernate to sendMessage(), puts message in _outgoing_mail with 'backgroundSend' set to 1.
// Usage: $mailErrors = sendBackground($options);
// if ($mailErrors) { die($mailErrors); }
// NOTE: Doesn't support headers or attachments yet and 'logging' setting is ignored.
// NOTE: Requires a background-mailer script that is running on a cron-job
// NOTE: For large volumes of email do a mail-merge via MySQL "INSERT FROM ... SELECT ... JOIN" to avoid timeouts
function sendBackground($options = []) {
  if (@$options['disabled']) { return; }   // don't send if 'disabled' option set
  $hasText        = array_key_exists('text', $options);
  $hasHTML        = array_key_exists('html', $options);

  // error checking
  $errors = '';
  if (!isValidEmail( @$options['to'], true))     { $errors .= "'to' isn't a valid email '" .htmlencode($options['to']). "'!<br>\n"; }
  if (!isValidEmail( @$options['from'] ))        { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br>\n"; }
  if (!array_key_exists('subject', $options))    { $errors .= "'subject' must be defined!<br>\n"; }
  if (!$hasText && !$hasHTML)                    { $errors .= "Either 'text' or 'html' or both must be defined!<br>\n"; }
  if (!isValidEmail( @$options['from'] ))        { $errors .= "'from' isn't a valid email '" .htmlencode($options['from']). "'!<br>\n"; }
  if (!array_key_exists('subject', $options))    { $errors .= "'subject' must be defined!<br>\n"; }
  if (array_key_exists('headers',     $options)) { $errors .= "'headers' not supported by sendBackground yet.<br>\n"; }
  if (array_key_exists('attachments', $options)) { $errors .= "'attachments' not supported by sendBackground yet.<br>\n"; }
  if ($errors) { return $errors; }

  // save message
  $colsToValues = array_slice_keys($options, array('from','to','subject','text','html'));
  $colsToValues['createdDate=']   = 'NOW()';
  $colsToValues['sent']           = '';
  $colsToValues['backgroundSend'] = '1';
  $colsToValues['reply-to']       = @$colsToValues['headers']['Reply-To'];
  $colsToValues['cc']             = @$colsToValues['headers']['CC'];
  $colsToValues['bcc']            = @$colsToValues['headers']['BCC'];
  mysql_insert('_outgoing_mail', $colsToValues, true);
}


/*
  $emailHeaders = emailTemplate_loadFromDB(array(
    'template_id'        => 'USER-ACTION-NOTIFICATION',
    'placeholders'       => $placeholders,
  ));
 $mailErrors = sendMessage($emailHeaders);
 if ($mailErrors) { alert("Mail Error: $mailErrors"); }

  // optional options - you can also add these if needed
  'template_table'     => '_email_templates', // or leave blank for default: _email_templates
  'addHeaderAndFooter' => true,               // (default is true) set to false to disable automatic adding of HTML header and footer to email
*/
// v2.16 added 'logging' as a pass-thru field.
// v2.50 template_table now defaults to _email_templates
// v2.50 ID with language code is checked first if language set, eg: CMS-PASSWORD-RESET-EN then CMS-PASSWORD-RESET
function emailTemplate_loadFromDB($options) {

  // set defaults
  if (!@$options['template_table']) { $options['template_table'] = '_email_templates'; } // v2.50

  // error checking
  if (!$options['template_id']) { dieAsCaller(__FUNCTION__.": No 'template_id' set in options"); }
  if (!$options['placeholders']) { dieAsCaller(__FUNCTION__.": No 'placeholders' set in options"); }

  // load template
  $template = [];
  if (!$template) { // try and load custom translated TEMPLATE-ID with language suffix first, eg: MY-TEMPLATE-FR
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id'] .'-'. strtoupper($GLOBALS['SETTINGS']['language'])));
  }
  if (!$template) { // if not found, try loading default template next
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id']));
  }
  if (!$template) { // if not found, re-add default templates and try again unless template wasn't added or was removed
    emailTemplate_addDefaults();
    $template = mysql_get($options['template_table'], null, array('template_id' => $options['template_id']));
  }
  if (!$template) { // otherwise, die with error
    dieAsCaller(__FUNCTION__.": Couldn't find email template_id '" .htmlencode($options['template_id']). "'");
  }

  // get email values
  $emailHeaders = [];
  $emailHeaders['from']     = @$options['override-from'] ?: $template['from'];
  $emailHeaders['to']       = @$options['override-to']   ?: $template['to'];

  if ($template['reply-to'] || @$options['override-reply-to']) {
    $emailHeaders['headers']['Reply-To'] = @$options['override-reply-to'] ?: $template['reply-to'];
  }
  if ($template['cc'] || @$options['override-cc']) {
    $emailHeaders['headers']['CC'] = @$options['override-cc'] ?: $template['cc'];
  }
  if ($template['bcc'] || @$options['override-bcc']) {
    $emailHeaders['headers']['BCC'] = @$options['override-bcc'] ?: $template['bcc'];
  }

  $emailHeaders['subject']  = @$options['override-subject']  ?: $template['subject'];
  $emailHeaders['disabled'] = @$options['override-disabled'] ?: @$template['disabled'];
  $emailHeaders['html']     = @$options['override-html']     ?: $template['html'];
  $passThruFields = array('attachments','headers','logging');
  foreach ($passThruFields as $field) {
    if (!array_key_exists($field, $options)) { continue; }
    $emailHeaders[$field] = $options[$field];
  }

  // replace placeholders
  list($emailHeaders, $textPlaceholderList) = emailTemplate_replacePlaceholders($emailHeaders, @$options['placeholders']);

  // update template placeholder list
  if ($template['placeholders'] != $textPlaceholderList) {
    mysql_update($options['template_table'], $template['num'], null, array('placeholders' => $textPlaceholderList));
  }

  // error checking
  if (!$emailHeaders['from'])    { die(__FUNCTION__ . ": No 'From' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['to'])      { die(__FUNCTION__ . ": No 'To' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['subject']) { die(__FUNCTION__ . ": No 'Subject' set by program or email template id '" .htmlencode($options['template_id']). "'"); }
  if (!$emailHeaders['html'])    { die(__FUNCTION__ . ": No 'Message HTML' found by program or email template id '" .htmlencode($options['template_id']). "'"); }

  // add html header/footer
  if (@$options['addHeaderAndFooter'] !== false) { // added in 2.50
    $htmlTitle  = htmlencode($emailHeaders['subject']);
    $header = <<<__HTML__
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>$htmlTitle</title>
</head>
<body>

<style type="text/css">
  p { margin-bottom: 1em; }
</style>


__HTML__;
// ***NOTE*** style tag is for Yahoo Mail which otherwise drops paragraph spacing - http://www.email-standards.org/blog/entry/yahoo-drops-paragraph-spacing/
// ... having a defined <title></title> helps get by spam filters

    $footer = <<<__HTML__
</body>
</html>
__HTML__;
  $emailHeaders['html'] = $header . $emailHeaders['html'] . $footer;
 }

  //
  return $emailHeaders;
}

// replace placeholders on specific email template fields
// Usage: list($emailHeaders, $textPlaceholderList) = emailTemplate_replacePlaceholders($emailHeaders, $placeholders);
function emailTemplate_replacePlaceholders($emailHeaders, $customPlaceholders) {
  $customPlaceholders = $customPlaceholders ?: [];
  $fieldnames = array('from','reply-to','to','cc','bcc','subject','html','headers');  // email header fields to replace placeholders in

  // set default placeholders (always available)
  $defaultPlaceholders = [];
  $defaultPlaceholders['server.http_host']         = @$_SERVER['HTTP_HOST'];
  $defaultPlaceholders['server.remote_addr']       = @$_SERVER['REMOTE_ADDR'];
  $defaultPlaceholders['settings.adminEmail']      = @$GLOBALS['SETTINGS']['adminEmail'];
  $defaultPlaceholders['settings.adminUrl']        = @$GLOBALS['SETTINGS']['adminUrl'];
  $defaultPlaceholders['settings.developerEmail']  = @$GLOBALS['SETTINGS']['developerEmail'];
  $defaultPlaceholders['settings.programName']     = @$GLOBALS['SETTINGS']['programName']; // added in 2.50

  // create text and html placeholders
  $textPlaceholders = $customPlaceholders + $defaultPlaceholders;
  $htmlPlaceholders = $customPlaceholders + array_map('nl2br', array_map('htmlencode', $defaultPlaceholders));

  // replace placeholders
  $searchPlaceholders = [];
  foreach (array_keys($textPlaceholders) as $key) { $searchPlaceholders[] = "#$key#"; }
  $replacementText    = array_values($textPlaceholders);
  $replacementHtml    = array_values($htmlPlaceholders);
  foreach ($fieldnames as $fieldname) {
    if (!array_key_exists($fieldname, $emailHeaders)) { continue; }
    if ($fieldname == 'html') { $emailHeaders[$fieldname] = str_replace($searchPlaceholders, $replacementHtml, $emailHeaders[$fieldname]); }
    else                      { $emailHeaders[$fieldname] = str_replace($searchPlaceholders, $replacementText, $emailHeaders[$fieldname]); }
  }

  // update text placeholder list
  $textPlaceholderList = '';
  foreach (array_keys($customPlaceholders)  as $placeholder) { $textPlaceholderList .= "#$placeholder#\n"; }
  foreach (array_keys($defaultPlaceholders) as $placeholder) { $textPlaceholderList .= "\n#$placeholder#"; }

  //
  return array($emailHeaders, $textPlaceholderList);
}

// Add a new template into the _email_templates table if template_id doesn't already exist
/* Usage:

  emailTemplate_addToDB(array(
    'template_id'  => 'EMAIL-TEMPLATE-NAME',
    'description'  => 'Description of what email template is used for',
    'from'         => '#settings.adminEmail#',
    'to'           => '#user.email#',
    'subject'      => '#server.http_host# Application Alert',
    'html'         => "<b>Hello World!</b>",
    'placeholders' => array('user.email', 'user.fullname'), // array of placeholder names
  ));

  // Note: Placeholders can also be text

*/
function emailTemplate_addToDB($record) {
  if (!$record['template_id']) { dieAsCaller(__FUNCTION__.": No 'template_id' set in options"); }

  // check if template id exists
  $templateExists = mysql_count('_email_templates', array('template_id' => $record['template_id']));
  if ($templateExists) { return false; }

  // get placeholder text
  $placeholderText = '';
  if (is_array($record['placeholders'])) {
    if ($record['placeholders']) { // if array isn't empty
      // hijack emailTemplate_replacePlaceholders() get us a placeholder list (including server placeholders) from placeholder array
      $placeholderText = array_value(emailTemplate_replacePlaceholders([], array_combine($record['placeholders'], $record['placeholders'])), 1);
    }
  }
  else {
    $placeholderText = $record['placeholders'];
  }

  // add template
  $colsToValues = array(
    'createdDate='     => 'NOW()',
    'createdByUserNum' => '0',
    'updatedDate='     => 'NOW()',
    'updatedByUserNum' => '0',
    'template_id'      => $record['template_id'],
    'description'      => $record['description'],
    'from'             => $record['from'],
    'reply-to'         => @$record['from'],
    'to'               => $record['to'],
    'cc'               => @$record['cc'],
    'bcc'              => @$record['bcc'],
    'subject'          => $record['subject'],
    'html'             => $record['html'],
    'placeholders'     => $placeholderText,
  );
  mysql_insert('_email_templates', $colsToValues, true);

  // set notice
  //if ($showNotice) {
  //  notice(t("Adding email template:"). htmlencode($colsToValues['template_id']). "<br>\n");
  //}
}


// Add CMS email templates -AND- add email templates used by plugins as well
// Note: Email templates are only created if they don't already exist.
// Note: This function is called on the email templates (list menu), and when sending an email
// ... and the template-id so the templates will be created just before you need them. (easier to implement that then checkin in CMS/Plugin install/upgrade/etc)
function emailTemplate_addDefaults() {

  ### Add Plugin Templates
  doAction('emailTemplate_addDefaults');

  ### NOTE: Make sure this file (admin_functions.php) is saved as UTF-8 or chars with accents may not get saved to MySQL on insert

  ### Note: If you need to update a template that already exists, have your upgrade code either:
  ###         - backup the existing template as "template-id (backup-YYYYMMDD-HHMMSS)
  ###         - or create/overwrite a new template as "template-id (ORIGINAL)"

  // debug - output current templates
  //showme(mysql_select('_email_templates')); exit;

  // CMS-PASSWORD-RESET
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-PASSWORD-RESET",
    'description'  => "Sent to users when they request to reset their password",
    'placeholders' => array('user.num','user.email','user.username','user.fullname','resetUrl'), // v3.06 - added username and fullname
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#settings.programName# Password Reset",
    'html'         => <<<__HTML__
<p>Hi #user.email#,</p>
<p>You requested a password reset for #settings.programName#.</p>
<p>To reset your password click this link:<br><a href="#resetUrl#">#resetUrl#</a></p>
<p>This request was made from IP address: #server.remote_addr#</p>
__HTML__
  ));


  // CMS-PASSWORD-RESET-FR
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-PASSWORD-RESET-FR",
    'description'  => "Sent to users when they request to reset their password (French)",
    'placeholders' => array('user.num','user.email','user.username','user.fullname','resetUrl'), // v3.06 - added username and fullname
    'from'         => "#settings.adminEmail#",
    'to'           => "#user.email#",
    'subject'      => "#settings.programName# Réinitialisation de votre mot de passe",
    'html'         => <<<__HTML__
<p>Bonjour #user.email#,</p>
<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
<p>Pour réinitialiser votre mot de passe cliquez sur le lien ci-dessous:<br><a href="#resetUrl#">#resetUrl#</a></p>
<p></p>
<p>Cette demande a été faite à partir de l'adresse d'IP : #server.remote_addr#</p>
<p>Ne soyez pas inquiet si vous n'êtes pas à l'origine de cette demande, ces informations sont envoyées uniquement à votre adresse e-mail.</p>
<p>L'administrateur</p>
<p>#settings.programName#</p>
__HTML__
  ));

  // CMS-BGTASK-ERROR
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-BGTASK-ERROR",
    'description'  => "Sent to admin when a background task fails",
    'placeholders' => array('bgtask.date','bgtask.activity','bgtask.summary','bgtask.completed','bgtask.function','bgtask.output','bgtask.runtime','bgtask.settingsUrl','bgtask.logsUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'to'           => "#settings.developerEmail#",
    'subject'      => "Background tasks did not complete",
    'html'         => <<<__HTML__
<p>The following Background Task did not complete successfully: </p>
<table border="0">
<tbody>
<tr><td>Date/Time</td><td> : </td><td>#bgtask.date#</td></tr>
<tr><td>Activity</td><td> : </td><td>#bgtask.activity#</td></tr>
<tr><td>Summary</td><td> : </td><td>#bgtask.summary#</td></tr>
<tr><td>Completed</td><td> : </td><td>#bgtask.completed#</td></tr>
<tr><td>Function</td><td> : </td><td>#bgtask.function#</td></tr>
<tr><td>Output</td><td> : </td><td>#bgtask.output#</td></tr>
<tr><td>Runtime</td><td> : </td><td>#bgtask.runtime# seconds</td></tr>
</tbody>
</table>
<p>Please check the Background Tasks logs here and check for additional errors:<br>#bgtasks.logsUrl#</p>
<p>You can review the Background Tasks status &amp; settings here: <br>#bgtasks.settingsUrl#</p>
<p>*Please note, incomplete background task alerts are only sent once an hour.</p>
__HTML__
  ));

  // CMS-ERRORLOG-ALERT
  emailTemplate_addToDB(array(
    'template_id'  => "CMS-ERRORLOG-ALERT",
    'description'  => "Sent to admin when a php error or warning is reported",
    'placeholders' => array('error.hostname','error.latestErrorsList','error.errorLogUrl'), // array of placeholder names
    'from'         => "#settings.adminEmail#",
    'reply-to'     => "#settings.adminEmail#",
    'to'           => "#settings.developerEmail#",
    'cc'           => "",
    'bcc'          => "",
    'subject'      => "Errors reported on: #error.hostname#",
    'html'         => <<<__HTML__
<p>One or more php errors have been reported on:<strong> #error.hostname# (#error.servername#)</strong></p>
<p>Check the error log for complete list and more details:<br><a href="#error.errorLogUrl#">#error.errorLogUrl#</a></p>
<p>Latest errors: </p>
<p style="padding-left: 30px;"><span style="color: #808080;">#error.latestErrorsList#</span></p>
<p><strong>*Note: Email notifications of new errors are only sent once an hour.</strong></p>
__HTML__
  ));

}

//eof
<?php


// security: reduce xsrf attack vectors by denying non-POST forms
function security_dieUnlessPostForm() {
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $error = t("Error: Form method must be POST!");
    die($error);
  }
}

// Only allow POST requests if an internal referer is sent. On error die's with error message
// Usage: alert(security_dieUnlessInternalReferer())
function security_dieUnlessInternalReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A form submission from an external source has been detected and automatically disabled.\n";
    $format .= "For security form posts are only accepted from: %1\$s\n";
    $format .= "Your browser indicated the form was sent from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
    die($error);

  }
}


// Security measures to prevent Cross-Site_Request_Forgery -
// Note: We implement both recommended and non-recommended practices as the combination further reduces possible attack vectors
// Ref: https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)_Prevention_Cheat_Sheet
function security_dieOnInvalidCsrfToken() {

  ### Validate for CSRF Token
  $errors = '';
  $token = @$_POST['_CSRFToken'];
  if     (array_key_exists('_CSRFToken', $_GET))      { $errors .= t("Security Error: _CSRFToken is not allow in GET urls, try using POST instead.") . "\n"; }
  elseif (!array_key_exists('_CSRFToken', $_SESSION)) { $errors .= t("Security Error: No _CSRFToken exists in session.  Try reloading or going back to previous page.") . "\n";
    
  }
  elseif ($token == '')                               { $errors .= t("Security Error: No _CSRFToken value was submitted.  Try reloading or going back to previous page.") . "\n";  }
  elseif ($token != $_SESSION['_CSRFToken'])          { $errors .= t("Security Error: Invalid _CSRFToken.  Try reloading or going back to previous page.") . "\n"; }
  //
  if ($errors) {
    @trigger_error($errors, E_USER_NOTICE);
    die($errors);
  }
}



// Only allow requests with no referer or referers from within program. On error returns error message and and clears all input variables
// This can be called at the start of your script
// Usage: alert(security_disableExternalReferers())
function security_disableExternalReferers() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if (!@$_SERVER['HTTP_REFERER']) { return; }

  // allowed link combinations
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!array_diff(array_keys($_REQUEST), array('menu','userNum','resetCode'))) { return; } // skip if nothing but password-reset form keys
  }

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A link from an external source has been detected and automatically disabled.\n";
    $format .= "For security links are only accepted from: %1\$s\n";
    $format .= "Your browser indicated that it linked from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
    _security_clearAllUserInput();
  }

  return $error;
}

// Only allow POST requests if an internal referer is sent. On error returns error message and and clears all input variables
// This can be called at the start of your script
// Usage: alert(security_disablePostWithoutInternalReferer())
function security_disablePostWithoutInternalReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if ($_SERVER['REQUEST_METHOD'] != 'POST' && !$_POST) { return; }

  $error             = '';
  $programBaseUrl    = _security_getProgramBaseRefererUrl();
  $isInternalReferer = startsWith($programBaseUrl, $_SERVER['HTTP_REFERER']);
  if (!$isInternalReferer) {
    $format  = "Security Warning: A form submission from an external source has been detected and automatically disabled.\n";
    $format .= "For security form posts are only accepted from: %1\$s\n";
    $format .= "Your browser indicated the form was sent from: %2\$s\n";
    $error   = nl2br(sprintf(t($format), htmlencode($programBaseUrl), htmlencode($_SERVER['HTTP_REFERER'])));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }

    _security_clearAllUserInput();
  }

  return $error;
}

// Warn if no URL is specified but input is - returns warning
// This can be called at the start of your script
function security_warnOnInputWithNoReferer() {
  if (!@$GLOBALS['SETTINGS']['advanced']['checkReferer']) { return; }
  if (@$_SERVER['HTTP_REFERER'])                          { return; }

  // allowed link combinations
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!array_diff(array_keys($_REQUEST), array('menu','userNum','resetCode'))) { return; } // skip if nothing but password-reset form keys
  }

  //
  $error     = '';
  $userInput = @$_REQUEST || @$_POST || @$_GET || @$_SERVER['QUERY_STRING'] || @$_SERVER['PATH_INFO'];
  if ($userInput) {
    $format   = "Security Warning: A manually entered link with user input was detected.\n";
    $format  .= "If you didn't type this url in yourself, please close this browser window.\n";
    $error   = nl2br(t($format));
    if (isAjaxRequest()) { $error = strip_tags(html_entity_decode($error)); }
  }

  return $error;

}



// display a hidden CSRF token field for use in validating form submissions
// is session key doesn't exist it will be created on first usage
function security_getHiddenCsrfTokenField() {

  // create token if it doesn't already exist
  if (empty($_SESSION) || !array_key_exists('_CSRFToken', $_SESSION)) {
    $_SESSION['_CSRFToken'] = sha1(uniqid('', true));
  }

  //
  $html = '<input type="hidden" name="_CSRFToken" value="' .$_SESSION['_CSRFToken']. '">';
  return $html;
}

// All referers must match this program base url to be accepted
// eg: http://www.example.com/cms/admin.php
function _security_getProgramBaseRefererUrl() {

  // Get current page URL without query string (originally based off thisPageUrl() code)
  static $programBaseUrl;
  if (!isset($programBaseUrl)) {
    $proto  = isHTTPS() ? "https://" : "http://";
    $domain = @$_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : @$_SERVER['SERVER_NAME'];
    if (preg_match('|:[0-9]+$|', $domain)) { $port = ''; } // if there is a :port on HTTP_HOST use that, otherwise...
    else                                   { $port   = (@$_SERVER['SERVER_PORT'] && @$_SERVER['SERVER_PORT'] != 80 && @$_SERVER['SERVER_PORT'] != 443) ? ":{$_SERVER['SERVER_PORT']}" : ''; }
    $path = str_replace(' ', '%20', $_SERVER['SCRIPT_NAME']); // exclude PATH_INFO
    $programBaseUrl = $proto . $domain . $port . $path;
  }

  return $programBaseUrl;
}


// clear all user input variables (except cookies)
function _security_clearAllUserInput() {
  $_REQUEST = [];
  $_POST = [];
  $_GET = [];
  $_SERVER['QUERY_STRING'] = '';
  $_SERVER['PATH_INFO']    = '';
  $_SERVER['HTTP_REFERER'] = '';
}

//eof
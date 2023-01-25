<?php


// legacy wrapper function for curl_get and curl_post.  Legacy getPage function has been renamed getPageOld
function getPageNew($url, $connectTimeout = 5, $headers = [], $POST = false, $responseBody = ''){
  die("This function is in development, don't release until we've completed and passed all testplans.");
  // When complete we'll rename this function getPage() and the old function getPageOld();
  $curl_options = [];

  // $connectTimeout
  $curl_options[CURLOPT_TIMEOUT] = $connectTimeout;

  // $headers
  foreach($headers as $key => $value) {
    $curl_options[CURLOPT_HTTPHEADER][] = "$key:$value";
  }

  // pass to curl_* function
  if ($POST) {
    $response = curl_post($url, null, $requestInfo, $curl_options);
  }
  else {
    $response = curl_get($url, null, $requestInfo, $curl_options);
  }

  // return response
  return [$response, $requestInfo['http_code'], $requestInfo['response_header'], $requestInfo['request_header']];
}

// download a web page
// list($html, $httpStatusCode, $headers, $request) = getPage($url);
function getPage($url, $connectTimeout = 5, $headers = [], $POST = false, $responseBody = '') {
  global $SETTINGS;

  $html             = null;
  $httpStatusCode   = null;
  $httpHeaders      = null;
  if (!$connectTimeout) { $connectTimeout = 5; }
  if (!$headers)        { $headers = []; }

  // use an http proxy server?
  $httpProxy = @$SETTINGS['advanced']['httpProxyServer'];
  $targetUrl = '';
  if ($httpProxy) {
    $targetUrl = $url;
    $url       = $httpProxy;
  }

  // is secure connection?
  $parsedUrl = parse_url($url);
  if     (@$parsedUrl['scheme'] == 'http')  { $isSSL = false; }
  elseif (@$parsedUrl['scheme'] == 'https') { $isSSL = true; }
  else { die(__FUNCTION__ . ": Url must start with http:// or https://!\n"); }
  if ($isSSL && !function_exists('openssl_open')) { die(__FUNCTION__ .": You must install the php openssl extension to be able to access https:// urls"); }

  // get port
  if (@$parsedUrl['port']) { $port = $parsedUrl['port']; }
  elseif ($isSSL)          { $port = 443; }
  else                     { $port = 80; }

  // open socket
  $scheme = $isSSL ? 'ssl://' : 'tcp://';

  //$ip  = gethostbyname($parsedUrl['host']); // get around PHP IPv6 bugs by connecting to IPv4 IP directly, Host: header is sent below regardless.
  //$handle = @fsockopen("$scheme$ip", $port, $errno, $errstr, $connectTimeout);
  // v2.64 - We can't do this anymore PHP 5.6+ now fails on invalid certificates: http://php.net/manual/en/migration56.openssl.php
  // ... and sometimes valid certificates: https://bugs.php.net/bug.php?id=68265
  // ... which causes fsockopen to fail if cert is invalid or hostname dosn't match: http://php.net/manual/fr/function.fsockopen.php#115405
  // So while connecting to the IP forces the server to use IPv4 and bypasses any potential IPv6 misconfigurations, it causes fsockopen to
  // fail on SSL sites because the IP won't match the certificate name

  // v2.64 - instead of using @ we disable 'display_errors' to cause errors to get logged because fsockopen can return multiple errors related to SSL certificate issues that aren't all captured by error_get_last/@$php_errormsg
  // FUTURE: Consider switching to stream_socket_client from fsockopen so we can specify stream context options related to certificate checking when needed
  $old_display_errors = ini_set('display_errors', '0');
  $handle = fsockopen($scheme . $parsedUrl['host'], $port, $errno, $errstr, $connectTimeout);
  ini_set('display_errors', $old_display_errors);

  if (!$handle) {
    $isSocketsDisabled = ($errno == 0   || $errstr == "The operation completed successfully." ||
                          $errno == 1   || $errstr == "Operation not permitted" ||
                          $errno == 11  || $errstr == "Resource temporarily unavailable" ||
                          $errno == 13  || $errstr == "Permission denied" ||
                          $errno == 22  || $errstr == "Invalid argument" ||
                          $errno == 60  || $errstr == "Operation timed out" ||
                          $errno == 110 || $errstr == "Connection timed out" ||
                          $errno == 111 || $errstr == "Connection refused" ||
                          $errno == 10060 || $errstr == "A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond.");
    if ($isSocketsDisabled) { return(array(NULL,NULL,NULL,NULL)); }
    die(__FUNCTION__ . ": Error opening socket: $errstr ($errno)\n");
  }

  // set timeout
  $readWriteTimeout = 15;
  stream_set_timeout($handle, $readWriteTimeout);

  // if we're using an http proxy...
  if ($httpProxy) {
    $url       = $targetUrl;
    $parsedUrl = parse_url($url);
  }

  // determine request path (default to /, add the query string if this is a GET request, or if there was a query string and response body supplied)
  $path = @$parsedUrl['path'] ? $parsedUrl['path'] : '/';
  if (@$parsedUrl['query'] && (!$POST || $responseBody)) { $path .= "?{$parsedUrl['query']}"; }

  // set default headers
  $headers['Connection'] = 'close'; // Force HTTP/1.1 connection to close after request
  if (@$parsedUrl['user'] && @$parsedUrl['pass'] &&
      !@$headers['Authorization'])           { $headers['Authorization']  = "Basic " .base64_encode("{$parsedUrl['user']}:{$parsedUrl['pass']}"); };
  if (!@$headers['User-Agent'])              { $headers['User-Agent']     = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)"; } // ref: http://blogs.msdn.com/ie/archive/2009/01/09/the-internet-explorer-8-user-agent-string-updated-edition.aspx
  if (!@$headers['Host'])                    { $headers['Host']           = $parsedUrl['host']; }
  if ($POST && !strlen($responseBody))       { $responseBody = @$parsedUrl['query']; }
  if ($POST && !@$headers['Content-Type'])   { $headers['Content-Type']   = 'application/x-www-form-urlencoded'; }
  if ($POST && !@$headers['Content-Length']) { $headers['Content-Length'] = strlen( $responseBody ); }

  // send request
  $method      = $POST ? 'POST' : 'GET';
  $httpVersion = "HTTP/1.1"; // Note: Paypal requires HTTP/1.1 headers: https://www.paypal-knowledge.com/infocenter/index?page=content&id=FAQ1488&pmv=print&impressions=false&viewlocale=en_US
  $request     = "$method $path $httpVersion\r\n";
  foreach ($headers as $name => $value) { $request .= "$name: $value\r\n"; }
  $request .= "\r\n";
  if ($POST) { $request .= $responseBody . "\r\n\r\n"; }
  fwrite($handle, $request);

  // get response
  $header         = null;
  $html           = null;
  $httpStatusCode = null;
  $response       = '';
  socket_set_blocking($handle, false); // use this or fgets() will block forever if no data available - even ignoring set_time_limit(###) limits - this happens when the other side doesn't close the connection or we send HTTP/1.1 by accident
  while (!feof($handle)) {
    $buffer = @fgets($handle, 128); // hide errors from: http://bugs.php.net/23220 and also see "Warning" box on http://php.net/file-get-contents
    if (!isset($buffer)) { break; } // prevent infinite loops on fgets errors
    $response .= $buffer;
  }

  if ($response) {
    list($header, $html) = preg_split("/(\r?\n){2}/", $response, 2);
    if (preg_match("/^HTTP\S+ (\d+) /", $header, $matches)) { $httpStatusCode = $matches[1]; }
  }

  // close socket
  fclose($handle);

  // decode chunked response body if Transfer-Encoding = chunked
  if (preg_match("/Transfer-Encoding:(\s*)chunked/i", $header)) {
    $html = _getPage_chunkedDecode($html);
  }

  //
  return(array($html, $httpStatusCode, $header, $request));
}

// decode chunked response: HTTP/1.1 requires support for chunked response: see end of section 3.6.1 of http://www.ietf.org/rfc/rfc2616.txt
// .. reference: http://stackoverflow.com/a/10859409
function _getPage_chunkedDecode($reponseBody) {
  for ($decodedResponse = ''; !empty($reponseBody); $reponseBody = trim($reponseBody)) {
    $lineEndingPosition = strpos($reponseBody, "\r\n");
    $lineLength         = hexdec(substr($reponseBody, 0, $lineEndingPosition));
    $decodedResponse   .= substr($reponseBody, $lineEndingPosition + 2, $lineLength);
    $reponseBody        = substr($reponseBody, $lineEndingPosition + 2 + $lineLength);
  }
  return $decodedResponse;
}

// return HTTP response for GET request using cURL library
// $url          - url to request
// $queryParams  - (optional) lists any query string values to be sent, set to null for none
// $requestInfo  - (optional) gets set by the function and contains info about the request
// $curl_options - (optional) lets you pass or override cURL options
/*
  // Simple Usage
  $response = curl_get("http://www.example.com/", [
    'id'     => $id,
    'token'  => $token,
  ], $requestInfo);
  if ($requestInfo['error'])              { die("Error loading page: {$requestInfo['error']}"); }
  if ($requestInfo['http_code'] != '200') { die("Unexpected server response (HTTP {$requestInfo['http_code']})"); }

  // Advanced Usage
  $queryParams  = ['id' => $id, 'token' => $token];
  $curl_options = [CURLOPT_USERAGENT => "API v1.00", CURLOPT_TIMEOUT => 10];
  $response     = curl_get("http://www.example.com/", $queryParams, $requestInfo, $curl_options);
  if ($requestInfo['error'])              { die("Error loading page: {$requestInfo['error']}"); }
  if ($requestInfo['http_code'] != '200') { die("Unexpected server response (HTTP {$requestInfo['http_code']})"); }
*/
function curl_get($url, $queryParams = [], &$requestInfo = [], $curl_options = []) {

  // add query string to url
  $queryString = "";
  if (!empty($queryParams)) {
    if (!is_array($queryParams))  { dieAsCaller("queryParams must be empty or an array!"); }
    if (preg_match("/\?/", $url)) { dieAsCaller("URL cannot contain ? if queryParams are specified!"); }
    $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    if ($queryString) { $queryString = "?$queryString"; }
  }

  // CURL_OPTIONS -  Reference: http://php.net/manual/en/function.curl-setopt.php
  $curl_defaults = [ // you can override these with the $curl_options argument
    CURLOPT_URL            => "$url$queryString",
    CURLOPT_RETURNTRANSFER => 1,                       // curl_exec should return HTTP body (instead of sending it to STDOUT)
    CURLOPT_HEADER         => 1,                       // curl_exec should return HTTP header
    CURLOPT_CONNECTTIMEOUT => 2,                       // seconds to wait while connecting
    CURLOPT_TIMEOUT        => 4,                       // total max seconds for cURL execution
    CURLINFO_HEADER_OUT    => 1,                       // adds 'request_header' to curl_getinfo() output

    // SSL Settings:
    CURLOPT_SSL_VERIFYPEER => true,                    // verify peer's SSL certificate is valid - set to false if you need to disable this for debugging/development: http://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-certificatecertificate-verify-failed-ca-is-ok
    CURLOPT_SSL_VERIFYHOST => 2,                       // verify peer's hostname is name on certificate - set to false if you need to disable this for debugging/development: http://stackoverflow.com/questions/6400300/https-and-ssl3-get-server-certificatecertificate-verify-failed-ca-is-ok
    CURLOPT_CAINFO         => CMS_ASSETS_DIR . "/3rdParty/cacert.pem", // cURL doesn't include a library of trusted root certification authorities, latest version can be downloaded as cacert.pem here: https://curl.haxx.se/docs/caextract.html

    // Cookie options - listed here for reference.
    //CURLOPT_COOKIEFILE     => $cookieJarPath,        // load cookies from this file, eg: $cookieJarPath = __DIR__ . "/_curl_cookies.txt";
    //CURLOPT_COOKIEJAR      => $cookieJarPath,        // save cookies to this file on script end or curl_close().  Setting this to any value resends received cookies
    //CURLOPT_COOKIEJAR      => '-',                   // setting this to '-' curl save cookies internally and resend them until curl_close is called. Don't this unless you write custom curl code.  Since curl_close() is called every time it discards cookes after each request

    // Advanced Settings - listed here for reference
    //CURLOPT_CUSTOMREQUEST  => 'OPTIONS',             // for sending custom method headers, eg: DELETE, OPTIONS, ETC
    //CURLOPT_USERAGENT      => "API Client v0.01",    // eg: ExampleBot/1.0 (+http://www.example.com/) -OR- Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36"); // Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36
    //CURLOPT_USERPWD        => "username:password",
    //CURLOPT_ENCODING       => '',                    // Sends Accept-Encoding: gzip AND has CURL automatically ungzip response, blank value defaults to all accepted encodings
    //CURLOPT_HTTPHEADER     => array('Expect:'),      // Disable HTTP 100-Continue responses, see: https://gms.tf/when-curl-sends-100-continue.html and https://www.google.com/search?q=curl+100+continue
    //CURLOPT_FOLLOWLOCATION => false,                 // Follow any "Location: " header redirects (or false to not follow any)
    //CURLOPT_MAXREDIRS      => 6,                     // max redirects to follow
    //CURLOPT_AUTOREFERER    => true,                  // Automatically set the Referer: field in requests when following a Location: redirect.
  ];

  // make request
  $ch            = curl_init();
  $mergedOptions = $curl_options + $curl_defaults; // first array (supplied parameters) overrides first - note we need to maintain numeric key values, don't use array_merge() as it does not preserve numeric key values
  curl_setopt_array($ch, $mergedOptions);
  $curl_result   = curl_exec($ch);
  $curl_getinfo  = curl_getinfo($ch);
  if ($curl_result === false) { // error checking
    $curl_error = curl_error($ch) . " (cURL error #" .curl_errno($ch). ")";
  }
  curl_close($ch);

  // split headers from body
  $parts   = preg_split("/\r?\n\r?\n/", $curl_result, 2);
  $headers = array_key_exists(0, $parts) ? $parts[0] : '';
  $body    = array_key_exists(1, $parts) ? $parts[1] : '';

  // create requestInfo (add custom fields and re-order for easier debugging)
  $requestInfo = [
    'error'           => isset($curl_error) ? $curl_error : '',
    'http_code'       => $curl_getinfo['http_code'],
    'url'             => $curl_getinfo['url'],
    'request_header'  => isset($curl_getinfo['request_header']) ? $curl_getinfo['request_header'] : '',
    'response_header' => $headers,
  ] + $curl_getinfo;

  //
  return $body;
}


// return HTTP response for POST request using cURL library, this is a shortcut function for sending
// POST requests with curl_get(), the main difference is the second argument of this function is
// passed as post key/value fields in the body of the HTTP request instead of being appended to the url query
// string.  See curl_get() for more details.
//
// CONTENT_TYPE NOTE: How to submit POST requests with different CONTENT_TYPE formats
// $postFields = ['this'=>'that', 'foo'=>'bar'];         // default, sends with CONTENT_TYPE: multipart/form-data;
// $postFields = http_build_query($postFields, '', '&'); // alternate, sends with CONTENT_TYPE: application/x-www-form-urlencoded
//
function curl_post($url, $postFields = [], &$requestInfo = [], $curl_options = []) {
    if (isset($curl_options[CURLOPT_POST]))        { dieAsCaller("Remove CURLOPT_POST, it's set by the function!"); }
    if (isset($curl_options[CURLOPT_POSTFIELDS]))  { dieAsCaller("Pass postFields as argument, not CURLOPT_POSTFIELDS!"); }

  // add/override curl options for sending post request and data
  $curl_options[CURLOPT_POST]       = true;
  $curl_options[CURLOPT_POSTFIELDS] = $postFields;

  // Disable HTTP 100-Continue responses, or we often only get partial server headers back
  // See: https://gms.tf/when-curl-sends-100-continue.html and https://www.google.com/search?q=curl+100+continue
  if (!isset($curl_options[CURLOPT_HTTPHEADER])) { $curl_options[CURLOPT_HTTPHEADER] = []; } // create array if it doesn't exist
  $curl_options[CURLOPT_HTTPHEADER][] = 'Expect:'; // add header to any previously existing headers

  //
  return curl_get($url, null, $requestInfo, $curl_options);
}


// set a cookie with a unique prefix string (eg: 4df8fa515a2ad_ ) so cookies don't override/conflict with cookies used by other 3rd party applications on the same site
// Defaults to session cookies that are erased when browser is closed, to expire in one hour use time()+(60*60*1),
// ... or for never-expires use a time in the far future such as 2146176000 (2038, don't set past this to avoid: https://google.com/search?q=2038+cookie+bug )
function setPrefixedCookie($unprefixedName, $cookieValue, $cookieExpires = 0, $allowHttpAccessToHttpsCookies = false, $allowJavascriptAccess = false) {
  if (headers_sent($file, $line)) {
    if (errorlog_inCallerStack()) { return; } // if headers sent and being called from errorlog functions don't log further errors
    $error = __FUNCTION__ . ": Can't set cookie($unprefixedName, $cookieValue), headers already sent!  Output started in $file line $line.\n";
    trigger_error($error, E_USER_ERROR);
  }

  // set cookie
  $cookieName   = cookiePrefix() . $unprefixedName;
  
  // cannot use null cookie value as of PHP 8.1
  $cookieValue  = $cookieValue ?? '';
  
  $cookiePath   = '/';        // make the cookie available to any path on domain (unique cookie name avoid collisions)
  $cookieDomain = preg_replace('/^www\./i', '', array_first(explode(':', @$_SERVER['HTTP_HOST']))); // get hostname without :port or www. so cookie works on www.example.com and example.com
  if (substr_count($cookieDomain, '.') <= 1) { $cookieDomain = ''; } // many browsers require a minimum of one dots in the domain name or they won't set the cookie (which is a problem for "localhost" and internal domain), setting to blank uses current domain

  // v2.62 - If true, cookies can only be set/read over HTTPS (based on browser implementation)
  // ... so we default this to true if we're on a HTTPS connection and allow an override via function args
  // ... OLD behaviour was to only set 'secure' cookies if isHTTPS() AND $GLOBALS['SETTINGS']['advanced']['requireHTTPS'] was set
  $cookieSecure   = $allowHttpAccessToHttpsCookies ? false : isHTTPS();
  $cookieHttpOnly = $allowJavascriptAccess ? false : true;  // v2.62 - added function option: prevent accessing of the cookie via javascript
  setcookie($cookieName, $cookieValue, $cookieExpires, $cookiePath, '', $cookieSecure, $cookieHttpOnly);
  $_COOKIE[$cookieName] = $cookieValue;
}

//
function removePrefixedCookie($unprefixedName) {
  if (headers_sent($file, $line)) {
    if (errorlog_inCallerStack()) { return; } // if headers sent and being called from errorlog functions don't log further errors
    $error = __FUNCTION__ . ": Can't remove cookie($unprefixedName), headers already sent!  Output started in $file line $line.\n";
    trigger_error($error, E_USER_ERROR);
  }

  setPrefixedCookie($unprefixedName, null, 252720000); // set expire date to the past (1978) so cookie is removed right away
  $cookieName = cookiePrefix() . $unprefixedName;
  unset($_COOKIE[$cookieName]);
}

//
function getPrefixedCookie($unprefixedName) {
  $cookieName = cookiePrefix() . $unprefixedName;
  return @$_COOKIE[$cookieName];
}

// get prefix used by all cookies.  Pass an argument to create a unique cookie space, eg: cookiePrefix('frontend');
// cookiePrefix();          // return cookie prefix, eg: cms_9b4bf_
// cookiePrefix('custom');  // set leading text to create custom cookie-space, eg: custom_9b4bf_
// list($leadingText, $defaultPrefix) = cookiePrefix(false, true); // return prefix parts as array
function cookiePrefix($setLeadingText = '', $returnArray = false) {
  static $leadingText = '';

  // allow override so plugins (membership?) can use their own cookie space
  if ($setLeadingText) { $leadingText = $setLeadingText; }

  // set default
  if (!$leadingText) {
    if (!empty($GLOBALS['SESSION_NAME_SUFFIX'])) {
      // backwards compatability with <= 2.50 and older membership plugins, if code tried to use alternate PHP SESSIONID, use a different cookie space for logins
      $leadingText = 'wsm';
    }
    else {
      // NOTE: Don't change "cms_" default cookie prefix "prefix", as it's referenced in getCurrentUserFromCMS()
      $leadingText  = 'cms';
    }
  }

  //
  if ($returnArray) { return array($leadingText, $GLOBALS['SETTINGS']['cookiePrefix']); }
  else              { return $leadingText .'_'. $GLOBALS['SETTINGS']['cookiePrefix']; }
}

// starts with http, https, etc
function isAbsoluteUrl($url) {
  $isAbsoluteUrl = false;
  if (preg_match("|^\w+:|", $url)) { $isAbsoluteUrl = true; }
  if (preg_match("|^//|", $url)) { $isAbsoluteUrl = true; }
  return $isAbsoluteUrl;
}

// make relative urls absolute - works with /absolute/filepath.php, ?query=string, and relative/../filepaths.php
// Note: If baseUrl is a directory it must end in a slash (or it will be mistaken for a file and may be trimmed)
function realUrl($targetUrl, $baseUrl = null) {
  if (isAbsoluteUrl($targetUrl)) { return $targetUrl; }

  ### get baseUrl
  if (!$baseUrl) {  // default baseUrl to currently running web script
    $baseUrl  =  isHTTPS() ? 'https://' : 'http://';
    $baseUrl .= @$_SERVER['HTTP_HOST'] ?: parse_url(@$GLOBALS['SETTINGS']['adminUrl'], PHP_URL_HOST);
    $baseUrl .= inCLI() ? '/' : $_SERVER['SCRIPT_NAME']; // SCRIPT_NAME has filepath not web path for command line scripts so just set to /
    $baseUrl  = str_replace(' ', '%20', $baseUrl);
  }
  if (!isAbsoluteUrl($baseUrl)) { $baseUrl = realUrl($baseUrl); }  // make sure supplied $baseUrl is absolute. if it's not, make it so, relative to currently running script

  // parse baseurl into parts
  $baseUrlParts  = parse_url($baseUrl);
  $baseUrlDomain = $baseUrlParts['scheme'] . '://';  // figure out $baseUrlDomain (e.g. http://domain)
  if (@$baseUrlParts['user']) {
    $baseUrlDomain .= $baseUrlParts['user'];
    if (@$baseUrlParts['pass']) { $baseUrlDomain .= ':' . $baseUrlParts['pass']; }
    $baseUrlDomain .= '@';
  }
  $baseUrlDomain .= $baseUrlParts['host'];
  if (@$baseUrlParts['port']) { $baseUrlDomain .= ':' . $baseUrlParts['port']; }


  // if no target,  nothing specified for target, use baseUrl
  if ($targetUrl == '') {
    $absoluteUrl = $baseUrl;
  }

   // for absolute filepaths - eg: /file.php
  elseif (strpos($targetUrl, '/') === 0) {
    $absoluteUrl = $baseUrlDomain . $targetUrl;
  }

  // for query strings - eg: ?this=that, etc
  else if (strpos($targetUrl, '?') === 0) {
    $absoluteUrl = $baseUrlDomain . $baseUrlParts['path'] . $targetUrl;
  }

  // for fragments - eg: #fragment, etc
  else if (strpos($targetUrl, '#') === 0) {
    $absoluteUrl = $baseUrlDomain . $baseUrlParts['path'];
    if (@$baseUrlParts['query']) { $absoluteUrl .= '?' . $baseUrlParts['query']; }
    $absoluteUrl = $absoluteUrl . $targetUrl;
  }

  // for relative filepaths - eg: dir/file.php
  else {
    $basePath = $baseUrlParts['path'];
    if (!$basePath) { trigger_error("Error getting path from realUrl('$targetUrl', '$baseUrl')!\n"); }

    // if the baseUrl includes a file (e.g. 'dir/admin.php'), strip it (e.g. 'dir/')
    if (!endsWith('/', $basePath)) {
      $basePath = dirname($basePath) . '/';
      $basePath = fixSlashes($basePath); // root paths return / only, so prevent // (or \/ on windows) from the above code
    }

    // normalize path components (replace backslashes, remove double-slashes, resolve . and ..)
    $inputUrlComponents  = preg_split("|[\\\/]|" , $targetUrl, 0, PREG_SPLIT_NO_EMPTY);
    $outputUrlComponents = preg_split("|[\\\/]|" , $basePath,  0, PREG_SPLIT_NO_EMPTY);
    foreach ($inputUrlComponents as $component) {
      if     ($component == '.')  { /* do nothing */ }                  // skip current dir references
      elseif ($component == '..') { array_pop($outputUrlComponents); } // remove last path component for .. parent dir references
      else                        { array_push($outputUrlComponents, $component); }
    }

    // construct URL from output
    $absoluteUrl = $baseUrlDomain;
    if (!empty($outputUrlComponents)) { $absoluteUrl .= '/' . implode('/', $outputUrlComponents); }
    if (endsWith('/', $targetUrl))    { $absoluteUrl .= '/'; }
  }

  // collapse "dir/../"s
  $madeReplacement = true;
  while ($madeReplacement) { // keep making replacements until we can't anymore
    $absoluteUrl = preg_replace('@[^/]+/\.\./@', '', $absoluteUrl, 1, $madeReplacement);
  }

  // collapse "/./"s
  $absoluteUrl = preg_replace('@/\./@', '/', $absoluteUrl, -1);

  // url encode spaces
  $absoluteUrl = str_replace(' ', '%20', $absoluteUrl); // url encoded spaces

  return $absoluteUrl;
}

// redirect the browser using one or more methods.  If all the headers haven't
// been sent yet a HTTP redirect header is sent.  In addition to that, a meta
// refresh tag and javascript redirect are output.
// NOTE: Cookies may be ignored on redirect! http://stackoverflow.com/questions/1621499/why-cant-i-set-a-cookie-and-redirect
function redirectBrowserToURL($url, $queryStringsOnly = false) {
  if (!$url) { die(__FUNCTION__ . ": No url specified!"); }

  // make relative urls absolute - works with /absolute/filepath.php, ?query=string, and relative/filepaths.php
  $url = realUrl($url);

  // force query string only urls (remove anything but query string and then query realUrl from that)
  if ($queryStringsOnly) {
    $url = realUrl( '?'.parse_url($url, PHP_URL_QUERY) );
  }

  //
  $url            = str_replace(' ', '%20', $url); // url encoded spaces
  $htmlEncodedUrl = htmlencode($url);

  ### if content headers haven't been send yet sent http redirect and html content header
  if (!headers_sent()) {

    # Fix IIS/5.0 bug "Set-Cookie Is Ignored in CGI When Combined With Location" described here: http://support.microsoft.com/kb/q176113/
    $isIIS5 = ($_SERVER['SERVER_SOFTWARE'] == 'Microsoft-IIS/5.0');  # IIS 5.1 doesn't seem effected
    if ($isIIS5) {
      print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";
      exit;
    }

    header("Location: $url"); // defaults to 302 redirect
    print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";
    print "This page has moved. If you aren't automatically forwarded to the new location ";
    print "then click the following link: <a href='$htmlEncodedUrl'>$url</a>.\n";
    exit;
  }

  ### if content headers have already been sent use javascript and/or meta refresh to redirect user

  // use javascript to redirect user
  print "\n\n<script>window.location = '" .addslashes($url). "';</script>\n";

  // use meta refresh to redirect url
  print "<meta http-equiv='refresh' content='0;URL=$htmlEncodedUrl'>\n";

  // print redirect message with link (in case other methods fail)
  print "\n<p>Redirecting to <a href='$htmlEncodedUrl'>$url</a>.</p>\n";
  exit;
}

// Send 301 redirect header and no-caching headers
// Search Engines: For SEO a 301 (permanent) is better than a 302 (temporary) as search engines update their index and transfer any pagerank
// Browsers: However Chrome and Firefox can cache 301s indefinitely prevent the url from being reused in future and making mistakes permanent
// To address both issues we sent a 301 redirect with no-cache headers.
// Reference: https://blog.mythic-beasts.com/2015/06/15/the-hazards-of-301-permanent-redirects/
function safe301Redirect($url) {
  header('Cache-Control: no-store, no-cache, must-revalidate');  // HTTP 1.1 method
  header('Pragma: no-cache');                                    // HTTP 1.0 method
  header('Expires: Thu, 01 Jan 1970 00:00:00 GMT'); 
  header("Location: $url", true, 301);
  exit;
}


//
function dieWith404($message = "Page not found!") {
  header("HTTP/1.0 404 Not Found");
  print $message;
  exit;
}

//
function isHTTPS() {
  
  if (isset($_SERVER['HTTPS'])       && $_SERVER['HTTPS']       == 'on') { return true; }
  if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)  { return true; }
  
  // added conditions for load balanced servers
  if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')  { return true; }
  if (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)  { return true; }
  
  // check for "FORWARDED" header - currently untested, working to spec (https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Forwarded)
  // eg: Forwarded: by=<identifier>;for=<identifier>;host=<host>;proto=<http|https>
  if (!empty($_SERVER['FORWARDED'])) {
    
    // loop through header parts
    $parts = explode(';', $_SERVER['FORWARDED']);
    foreach ($parts as $part) {
      
      // find key and values per part
      list($key, $values) = explode('=', $part);
      
      // only care about protocol
      if ($key != 'proto') { continue; }
      
      $values = explode(',', $values);
      foreach ($values as $value) {
        
        $value = trim($value);
        if ($value == 'https') { return true; }
        
      }
    }
    
  }
  
  return false;
}

// Check if current users IP is in the list of allowed IPs for CMS access
function isIpAllowed($forceCheck = false, $allowedIPsAsCSV = '') {
  if (!$GLOBALS['SETTINGS']['advanced']['restrictByIP'] && !$forceCheck) { return true; } // if not restricted, always allow

  if (!$allowedIPsAsCSV) { $allowedIPsAsCSV = $GLOBALS['SETTINGS']['advanced']['restrictByIP_allowed']; }
  $allowedIPs = preg_split("/[\s,]+/", $allowedIPsAsCSV);
  $isAllowed  = in_array($_SERVER['REMOTE_ADDR'], $allowedIPs);

  return $isAllowed;
}


// if you set $newQueryValues keys to NULL they will removed
function thisPageUrl($newQueryValues = [], $queryStringOnly = false) { // $queryStringOnly added in v2.15
  $proto  = isHTTPS() ? "https://" : "http://";

  if     (isset($_SERVER['HTTP_HOST']))   { $domain = $_SERVER['HTTP_HOST']; }
  elseif (isset($_SERVER['SERVER_NAME'])) { $domain = $_SERVER['SERVER_NAME']; }
  else                                    { $domain = ''; }

  if (preg_match('|:[0-9]+$|', $domain)) { $port = ''; } // if there is a :port on HTTP_HOST use that, otherwise...
  else                                   { $port   = (@$_SERVER['SERVER_PORT'] && @$_SERVER['SERVER_PORT'] != 80 && @$_SERVER['SERVER_PORT'] != 443) ? ":{$_SERVER['SERVER_PORT']}" : ''; }
  
  // sanitize based on valid "path" characters: https://stackoverflow.com/a/4669750
  $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
  $pathInfo = preg_replace("/[^a-zA-Z0-9\-._~!$&'()*+,;=:@\/]/", "", $pathInfo);
  
  $path   = str_replace(' ', '%20', $_SERVER['SCRIPT_NAME']) . $pathInfo;

  //
  $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
  if ($newQueryValues) { // just use the existing query_string unless we need to modify it
    parse_str($query, $oldRequestKeysToValues);

    // 2.54 - merge manually adding new keys to beginning of query and don't break urls that need trailing numbers such as ?new=value&page-name=123
    //        ... don't use array_merge() as it re-numbers numeric keys which will break urls
    $requestKeysToValues = $newQueryValues;
    foreach ($oldRequestKeysToValues as $key => $value) {
      if (!array_key_exists($key, $requestKeysToValues)) { $requestKeysToValues[$key] = $oldRequestKeysToValues[$key]; }
    }

    $query = http_build_query($requestKeysToValues, '', '&');
  }
  if ($query != '') { $query = "?$query"; }
  $query = rtrim($query, '='); //v2.51 remove trailing = so urls that start like this stay like this: ?page-name-123 and not ?page-name-123=

  //
  if ($queryStringOnly) { return $query; }
  else                  { return $proto . $domain . $port . $path . $query; } // full url
}


//
function isAjaxRequest() {
  $isAjaxRequest = strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'') == 'xmlhttprequest';
  return $isAjaxRequest;
}

//eof
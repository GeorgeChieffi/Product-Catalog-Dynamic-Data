<?php

// for checkboxes, prints checked="checked" if fieldValue matches testValue
function checkedIf($fieldValue, $testValue, $returnValue = 0) {
  $attr = 'checked="checked"';
  if (strval($fieldValue) == strval($testValue)) { // use strval so: 0 != ''
    if ($returnValue) { return $attr; }
    else              { echo $attr; }
  }
}


// full pulldowns and radios, prints selected="selected" if fieldValue matches testValue
// v3.16 $testValue can now be an array where true is returned is any element matches
function selectedIf($fieldValue, $testValue, $returnValue = 0) {

  // check for match
  $match = false;
  if     (is_array($fieldValue)) { dieAsCaller("First argument cannot be array!"); }
  elseif (is_array($testValue) && in_array($fieldValue, $testValue)) { $match = true; } // added in v3.16
  elseif (strval($fieldValue) == strval($testValue))                 { $match = true; } // use strval so: 0 != ''

  // Output attribute on match
  if ($match) {
    $attr = 'selected="selected"';
    if ($returnValue) { return $attr; }
    else              { echo $attr; }
  }
}


// This function generates the <option> list HTML for a <select> pulldown list
// Note: labels is options and will default to values
// Note: $selectedValue can be a string or an array (to support multiple selected values for multi-select lists)
// v3.08: You can now specify text for $showEmptyOptionFirst to have that show instead of <select>
function getSelectOptions($selectedValue, $values, $labels = '', $showEmptyOptionFirst = false, $htmlEncodeInput = true) {
  if (!is_array($selectedValue)) { $selectedValue = (array) $selectedValue; } // v2.60 force to array interally for simpler code to test single or multiple selected values

  if (!$labels) { $labels = $values; }
  $output = '';

  // error checking
  if (!count($values) || !count($labels)) { return ''; } // if no values or labels return blank

  //
  if ($showEmptyOptionFirst) {
    $label   = is_string($showEmptyOptionFirst) ? $showEmptyOptionFirst : t('&lt;select&gt;'); // v2.07 Allow specifying of label text
    $output .= "<option value=''>$label</option>\n";
  }

  //
  $valuesAndLabels = array_combine($values, $labels);
  foreach ($valuesAndLabels as $inputValue => $inputLabel) {
    $selectedAttr = (in_array(strval($inputValue), $selectedValue)) ? " selected='selected'" : ''; // use strval or values of 0 will match $selectedValue of array('')

    $outputValue  = $htmlEncodeInput ? htmlencode($inputValue) : $inputValue;
    $outputLabel  = $htmlEncodeInput ? htmlencode($inputLabel) : $inputLabel;
    if ($outputLabel == '') { $outputLabel = '&nbsp;'; } // insert blank value to avoid XHTML validation errors

    $output .= "<option value='$outputValue' $selectedAttr>$outputLabel</option>\n";
  }

  return $output;
}


// <select name="country"><?php echo getSelectOptionsFromTable('countries','num','name', @$_REQUEST['country'], true) ? ></select>
function getSelectOptionsFromTable($tableName, $valueField, $labelField, $selectedValue, $showEmptyOptionFirst) {
  if (!is_array($selectedValue)) { $selectedValue = (array) $selectedValue; } // v2.60 force to array interally for simpler code to test single or multiple selected values

  // load options
  $escapedLabelField = mysql_escape($labelField);
  $escapedValueField = mysql_escape($valueField);
  $escapedTableName  = $GLOBALS['TABLE_PREFIX'] . mysql_escape($tableName);

  // get records
  $schema  = loadSchema($tableName);
  $query   = "SELECT `$escapedLabelField`, `$escapedValueField` FROM `$escapedTableName`";
  if (@$schema['listPageOrder']) { $query .= " ORDER BY {$schema['listPageOrder']}"; } // v2.14 - sort by schema sort order if available
  $records = mysql_select_query($query);

  // create html
  $html = '';
  if ($showEmptyOptionFirst)    { $html .= "<option value=''>" .t('&lt;select&gt;'). "</option>\n"; }
  foreach ($records as $record) {
    $label        = $record[$labelField];
    $value        = $record[$valueField];
    $selectedAttr = (in_array($value, $selectedValue)) ? " selected='selected'" : '';
    $html        .= "<option value='" .htmlencode($value) ."'$selectedAttr>" .htmlencode($label). "</option>\n";
  }

  //
  return $html;
}


// escape javascript code
function jsEncode($str) {
    return addcslashes($str,"\\\'\"&\n\r<>");
}

// Automatically espaces and quotes input values and inserts them into code, like mysql_escapef
// Example: onlclick="<?php echo js_escapef("jsFunction(?,?)",$num, $name); ...
function js_escapef() {
  $args         = func_get_args();
  $queryFormat  = array_shift($args);
  $replacements = $args;

  // make replacements
  $escapedQuery = '';
  $queryParts   = explode('?', $queryFormat);
  $lastPart     = array_pop($queryParts); // don't add escaped value on end of query
  foreach ($queryParts as $part) {
    $escapedQuery .= $part;
    $escapedQuery .= "'" . jsEncode( array_shift($replacements) ) . "'";
  }
  $escapedQuery .= $lastPart;

  //
  return $escapedQuery;
}

// just like htmlspec ialchars() but encodes ' as well and doesn't double encode <br> (to make encoding textboxes with auto-formatting easy)
// set $double_encode to false to not encode htmlentities that already exist in content
function htmlencode($string, $encodeBr = false, $double_encode = true) { // $double_encode added in 3.11

  // v3.16 error checking: return "invalid input" on arrays|objects input instead of calling dieAsCaller()
  // Developers often pass _REQUEST values to htmlencode, eg: htmlencode($_REQUEST['name']) and security scanners
  // often pass arrays values ?name[]=test, which results in errors and false positives.  So instead of returning
  // an error we'll return a common string to avoid errors and false positives caused by scanners
  if (is_array($string) || is_object($string)) { return "invalid input"; }

  //
  $flags    = ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5;
  $encoding = 'UTF-8';
  $string = $string ?? ''; // cannot be null as of PHP 8.1
  $encoded  = call_user_func_array('htmlspe'.'cialchars', array($string, $flags, $encoding, $double_encode)); // encode & " ' < > and use call_user_func_array to bypass invalid keywords scanner in script builder

  // don't encode <br>'s
  if (!$encodeBr) {
    $encoded = preg_replace("|&lt;(br\s*/?)&gt;|i", "<$1>", $encoded); // don't double encode <br> tags (to make encoding textboxes with auto-formatting easy)
  }

  //
  return $encoded;
}

// htmlencode multiple values replacing placeholders ('?') with htmlencoded values
// echo htmlencodef("City of ? with a population of ?", $city, $population);
function htmlencodef() {
  $args         = func_get_args();
  $formatString = array_shift($args);
  $replacements = $args;

  // error checking
  $totalReplacements = count($replacements);
  $totalPlaceholders = substr_count($formatString, '?');
  if ($totalPlaceholders > $totalReplacements) { dieAsCaller("htmlencodef: Not enough arguments!"); }
  if ($totalReplacements > $totalPlaceholders) { dieAsCaller("htmlencodef: Not enough placeholders!"); }

  // replace placeholders
  $index = 0;
  $encodedString = preg_replace_callback('/\?/', function($match) use($replacements, &$index) {
    return htmlencode($replacements[$index++]);
  }, $formatString);

  //
  return $encodedString;
}


// for displaying urls in html, encode spaces (which aren't valid xhtml in URIs), and html encode &
function urlencodeSpaces($value) {
  return str_replace(' ', '%20', htmlencode($value));
}


// Strip tags/attrs out of html using 3rd party HTMLPurifier (http://htmlpurifier.org) - added in 2.51
function htmlPurify($html, $config = []) {
  // load library if it's not already loaded
  require_once(CMS_ASSETS_DIR."/3rdParty/HTMLPurifier/HTMLPurifier.standalone.php");

  // default config
  if (!$config) {  //
    $config['HTML.Allowed'] = 'a[href],b,strong,i,em,u,strike,br,p';  // Strip everything except listed attributes out of HTML
  }

  // set cache settings
  $config['Cache.DefinitionImpl'] = null;                            // disable - http://htmlpurifier.org/live/configdoc/plain.html#Cache.DefinitionImpl
  $config['Cache.SerializerPath'] = DATA_DIR . "/cache/htmlPurify";  // http://htmlpurifier.org/live/configdoc/plain.html#Cache.SerializerPath

  // configure HTMLPurifier
  $configObject = HTMLPurifier_Config::createDefault();
  foreach ($config as $key => $value) {
    $configObject->set($key, $value);
  }

  // purify and return the result
  $purifier = new HTMLPurifier($configObject);
  return $purifier->purify($html);
}


// Programmatically output an HTML tag and attributes.  Attributes values will be html encoded.
// Attributes with null values will always be skipped, and those with blank values will be
// skipped by default unless $skipEmptyAttr is set to false
// $html = tag('img', ['src' => 'http://example.com/']);
// $html = tag('div', ['id' => 'mydiv'], "Hello World"); // Outputs: <div id="mydiv">Hello World</div>
// $html = tag('div', ['class' => ''], null, false); // Outputs: <div class="">
// FUTURE: Support $tagName suffix-char of / to create empty element tags for XML, eg: tag('element/', ['selected'=>'true']) outputs <element selected='true'>
function tag($tagName, $attrs = [], $content = null, $skipEmptyAttr = true) {
  $html = '<' . $tagName;
  foreach ($attrs as $key => $value) {
    if (is_null($value)) { continue; } // pass null to skip attr, pass empty string to output attribute with empty string value, eg: attr=''
    if ($skipEmptyAttr && $value == '') { continue; }
    $html .= htmlencodef(' ?="?"', $key, $value);
  }
  $html .= '>';

  // add content and closing tag
  if (!is_null($content)) {
    $html .= $content;
    $html .= "</$tagName>";
  }

  return $html;
}

//eof
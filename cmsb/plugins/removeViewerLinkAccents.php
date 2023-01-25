<?php
/*
Plugin Name: Remove Accents From Viewer Links
Plugin URI:
Description: Remove accents from characters in viewer links instead of replacing them with a dash.  So é or è becomes e instead of -
Author:
Version: 1.0
Author URI:
*/

addFilter('viewer_link_field_content', 'plugin_rewriteViewerLinks', null, 2);

//
function plugin_rewriteViewerLinks($defaultOutput, $fieldValue) {

  // reference: http://php.net/manual/en/function.str-replace.php#85431
  $charsToEntities = array(
    'A'   => '/&Agrave;|&Aacute;|&Acirc;|&Atilde;|&Auml;|&Aring;/',
    'a'   => '/&agrave;|&aacute;|&acirc;|&atilde;|&auml;|&aring;/',
    'C'   => '/&Ccedil;/',
    'c'   => '/&ccedil;/',
    'E'   => '/&Egrave;|&Eacute;|&Ecirc;|&Euml;|&AElig;/',
    'e'   => '/&egrave;|&eacute;|&ecirc;|&euml;|&aelig;/',
    'I'   => '/&Igrave;|&Iacute;|&Icirc;|&Iuml;/',
    'i'   => '/&igrave;|&iacute;|&icirc;|&iuml;/',
    'N'   => '/&Ntilde;/',
    'n'   => '/&ntilde;/',
    'O'   => '/&Ograve;|&Oacute;|&Ocirc;|&Otilde;|&Ouml;|&Oslash;/',
    'o'   => '/&ograve;|&oacute;|&ocirc;|&otilde;|&ouml;|&oslash;/',
    'U'   => '/&Ugrave;|&Uacute;|&Ucirc;|&Uuml;/',
    'u'   => '/&ugrave;|&uacute;|&ucirc;|&uuml;/',
    'Y'   => '/&Yacute;/',
    'y'   => '/&yacute;|&yuml;/',
    'a.'  => '/&ordf;/',
    'o.'  => '/&ordm;/',
    'and' => '/&amp;/',
  );

  // remove accents from characters
  $htmlEncodedFieldValue = htmlentities($fieldValue, ENT_NOQUOTES, 'UTF-8');
  $output                = preg_replace($charsToEntities, array_keys($charsToEntities), $htmlEncodedFieldValue);

  // perform other formatting
  $output = preg_replace('/[^a-z0-9\.\-\_]+/i', '-', $output); // replace any remaining characters with -
  $output = preg_replace('/(^-+|-+$)/', '', $output);  // remove leading and trailing dashes
  if ($output) { $output .= '-'; }  // add trailing dash to content

  //
  return $output;
}

?>

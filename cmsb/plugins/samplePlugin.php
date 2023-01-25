<?php
/*
Plugin Name: !Sample Plugin
Description: Example of a plugin file provided for reference.
Version: 1.00
CMS Version Required: 3.54
*/

// Don't run from command-line (cron scripts can error when certain CGI env vars aren't set)
if (inCLI()) { return; }

// Plugin Actions
pluginAction_addHandlerAndLink(t('Short page example'), 'samplePlugin_short_page_example', 'admins');
pluginAction_addHandlerAndLink(t('Long page example'),  'samplePlugin_long_page_example', 'admins');
//pluginAction_addHandlerAndLink(t('Sample Page 3'), 'samplePlugin_page3', 'admins');

// short example of creating admin page
function samplePlugin_short_page_example() {

  //
  adminUI([
    'PAGE_TITLE'       => [
                            t("Plugins") => '?menu=admin&action=plugins',
                            t("Sample Plugin"),
                          ],
    'FORM'             => [ 'name' => 'sampleForm', 'autocomplete' => 'off' ],
    'HIDDEN_FIELDS'    => [ [ 'name' => 'submitForm', 'value' => '1' ] ],
    'BUTTONS'          => [
      [ 'name' => '_action=save', 'label' => 'Save',                                  ],
      [ 'name' => 'cancel',       'label' => 'Cancel',  'onclick' => 'editCancel();', ],
    ],
    'ADVANCED_ACTIONS' => ['Example: 123' => '?example=123', 'Example: 456' => '?example=456'],
    'CONTENT'          => "<p>This example pages demonstrates how to display admin pages from a plugin.<br>Note, that not all the features work as they are for code demonstration purposes.",
  ]);
  exit;
}


// expanded example of creating admin page
function samplePlugin_long_page_example() {

  // prepare adminUI() placeholders
  $adminUI = [];

  // page title
  $adminUI['PAGE_TITLE'] = [
    t("Plugins") => '?menu=admin&action=plugins',
    t("Sample Plugin"),
    t("Sample Page 2") => '?_pluginAction=' . __FUNCTION__,
  ];

  // form & form fields
  $adminUI['FORM'] = [
    'name' => 'sampleForm',
    'autocomplete' => 'off'
  ];
  $adminUI['HIDDEN_FIELDS'] = [
    [ 'name' => 'submitForm', 'value' => '1' ],
  ];

  // buttons
  $adminUI['BUTTONS'] = [
    [ 'name' => '_action=save', 'label' => 'Save',                                  ],
    [ 'name' => 'cancel',       'label' => 'Cancel',  'onclick' => 'editCancel();', ],
  ];

  // advanced actions
  $adminUI['ADVANCED_ACTIONS'] = [
    'Example: 123' => '?example=123',
    'Example: 456' => '?example=456',
  ];

  ### content
  $adminUI['CONTENT'] = "";

  $adminUI['CONTENT'] .= "<p>This example pages demonstrates how to display admin pages from a plugin.<br>\n";
  $adminUI['CONTENT'] .= "Note, that not all the features work as they are for code demonstration purposes.<br>\n";
  $adminUI['CONTENT'] .= "<p>You can find the source code for this plugin here:<br>\n" .__FILE__. "</p>\n";

  $adminUI['CONTENT'] .= <<<__HTML__
  <p>This is an example of adding content with a PHP Heredoc</p>
__HTML__;

  // EXAMPLE 1: get content from an existing function(s)
  $adminUI['CONTENT'] .= _my_content_returned_example("example 1"); // for functions that "RETURN" content

  // EXAMPLE 2: get content from an existing function(s)
  $adminUI['CONTENT'] .= ob_capture('_my_content_printed_example', "example 2"); // for functions that ECHO/PRINT content: ob_capture captures and returns output from echo/print

  // EXAMPLE 3: get content from an existing function(s)
  $adminUI['CONTENT'] .= ob_capture(function() { ?>
    <?php $place = "World"; $greeting = "Hello"; ?>
    <p>Here's an example of capturing the output from existing blocks of PHP.
    <?php echo $greeting; ?> <?php echo $place; ?></p>
<?php });

  // show page
  adminUI($adminUI);
  exit;
}


//
function _my_content_returned_example($arg1 = "test") {
  $html = "<p>This is an example of getting content that is RETURNED from a PHP function ($arg1)</p>";
  return $html;
}
//
function _my_content_printed_example($arg1 = "test") {
  $html = "<p>This is an example of getting content that is PRINTED from a PHP function ($arg1).  This can be useful when you need to capture output from a function that echo/prints it.</p>";
  print $html;
}


// eof

<?php

// NOTE: THIS FILE IS NOT USED BY ANY PROGRAM.  IT IS JUST USED FOR COMPARING TO OLD CUSTOM WYSIWYG FILES TO
// ... DETERMINE WHAT WAS CHANGED WHEN UPGRADING.

// NOTE: If you want to make changes to this file save it as
// wysiwyg_custom.php and it will get loaded instead of this
// file and won't get overwritten when you upgrade.

// NOTE: You can find the CSS for the wysiwyg in /lib/wysiwyg.css
// and also create a /lib/wysiwyg_custom.css file as well.

// this is called once at the top of the page
function loadWysiwygJavascript() {
  global $SETTINGS;

  // Renders script tag with compressed scripts
  // Note: We're using the direct method here: http://www.tinymce.com/wiki.php/Compressors:PHP
  require_once("{$GLOBALS['CMS_ASSETS_DIR']}/3rdParty/tiny_mce/tiny_mce_gzip.php");
  TinyMCE_Compressor::renderTag(array(
      // Load Plugins: ***NOTE*** plugins must be added both loadWysiwygJavascript() and initWysiwyg() (twice in this page, search for "plugins:")
     'plugins'   => 'inlinepopups,contextmenu,table,fullscreen,paste,media,spellchecker,lists',
     'languages' => $SETTINGS['wysiwyg']['wysiwygLang'],
     'themes'    => 'advanced',
     'url'       => "{$GLOBALS['CMS_ASSETS_URL']}/3rdParty/tiny_mce/tiny_mce_gzip.php",
  ));

}

// this is called once for each wysiwyg editor on the page
function initWysiwyg($fieldname, $uploadBrowserCallback) {
  global $SETTINGS;
  $includeDomainsInLinks = $SETTINGS['wysiwyg']['includeDomainInLinks'] ? "remove_script_host : false, // domain name won't be removed from absolute links" : '';
  $programUrl            = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
  $programUrl            = preg_replace("/ /", "%20", $programUrl);
  
  // load either wysiwyg_custom.css (if exists) or wysiwyg.css
  $wysiwygCssFilename    = file_exists( __DIR__ .'/wysiwyg_custom.css' ) ? 'wysiwyg_custom.css' : 'wysiwyg.css';
  $wysiwygCssUrl         = noCacheUrlForCmsFile("lib/$wysiwygCssFilename");

  // call custom wysiwyg functions named: initWysiwyg_sectionName_fieldName() or initWysiwyg_sectionName()
  if (__FUNCTION__ == 'initWysiwyg') {
    $fieldnameWithoutPrefix = preg_replace("/^field_/", '', $fieldname);
    $fieldSpecificFunction   = "initWysiwyg_{$GLOBALS['tableName']}_$fieldnameWithoutPrefix";
    $sectionSpecificFunction = "initWysiwyg_{$GLOBALS['tableName']}";

    if (function_exists($fieldSpecificFunction))   { return call_user_func($fieldSpecificFunction, $fieldname, $uploadBrowserCallback); }
    if (function_exists($sectionSpecificFunction)) { return call_user_func($sectionSpecificFunction, $fieldname, $uploadBrowserCallback); }
  }

  // display field
  print <<<__HTML__

  <script language="javascript" type="text/javascript"><!--
  tinyMCE.init({
    mode : "exact",
    theme : "advanced",
    language: "{$SETTINGS['wysiwyg']['wysiwygLang']}",

    // Define buttons: Button codes: http://www.tinymce.com/wiki.php/Buttons/controls
    theme_advanced_buttons1 : "formatselect,fontsizeselect,bold,italic,underline,|,justifyleft,justifycenter,justifyright,|,bullist,numlist,|,outdent,indent,|,sub,sup,charmap,|,removeformat,fullscreen",
    theme_advanced_buttons2 : "forecolor,backcolor,|,link,unlink,anchor,|,blockquote,hr,image,media,table,visualaid,|,pastetext,pasteword,|,code,|,spellchecker",
    theme_advanced_buttons3 : '',

    // set formats to show with formatselect
    theme_advanced_blockformats : "p,address,pre,h1,h2,h3,h4,h5,h6",

    // set classes to show with styleselect - if you don't set this classes will be imported from the file loaded by 'content_css' below (usually wysiwyg.css).
    //theme_advanced_styles : "Header 1=header1;Header 2=header2;Header 3=header3;Table Row=tableRow1",

    // Load Plugins: ***NOTE*** plugins must be added both loadWysiwygJavascript() and initWysiwyg() (twice in this page, search for "plugins:")
    plugins: "inlinepopups,contextmenu,table,fullscreen,paste,media,spellchecker,lists",

    // Paste Settings - Docs: http://www.tinymce.com/wiki.php/Plugin:paste
    paste_text_sticky: true, // if user clicks "paste as text" don't unselect it after they paste, wait for them to click it again
    setup: function(ed) {
      ed.onInit.add(function(ed) { // from: http://tinymce.moxiecode.net/punbb/viewtopic.php?pid=73053#p73053
       ed.pasteAsPlainText = true;
       ed.controlManager.setActive("pastetext", true);
      });
    },

    // v2.50 - allow style in body (invalid XHTML but required to style html emails since many email clients won't display remote styles or styles from head)
    // Reference: http://www.tinymce.com/wiki.php/Configuration:valid_children
    valid_children : "+body[style]",

    // Spellchecker settings - see: http://wiki.moxiecode.com/index.php/TinyMCE:Plugins/spellchecker
    spellchecker_languages: "+{$SETTINGS['wysiwyg']['wysiwygLang']}={$SETTINGS['wysiwyg']['wysiwygLang']}",
    spellchecker_rpc_url:   "{$GLOBALS['CMS_ASSETS_URL']}/3rdParty/tiny_mce/plugins/spellchecker/rpc.php",

    // Force <br> instead of <p> - see: http://www.tinymce.com/wiki.php/TinyMCE_FAQ#TinyMCE_produce_P_elements_on_enter.2Freturn_instead_of_BR_elements.3F
    // Uncomment these lines to enable this for new records
    //forced_root_block : false,

    // Allow user to drag the editor to resize it - see "theme_advanced_resizing*" options here: http://wiki.moxiecode.com/index.php/TinyMCE:Configuration
    // Uncommon these lines to enable this.
    theme_advanced_statusbar_location : 'none',
    //theme_advanced_statusbar_location : 'bottom',
    //theme_advanced_resizing : true,

    //
    theme_advanced_toolbar_location : "top",
    theme_advanced_toolbar_align : "left",
    elements: '$fieldname',
    file_browser_callback : "$uploadBrowserCallback",
    relative_urls : false,
    document_base_url: "/",

    $includeDomainsInLinks
    entity_encoding : "raw", // don't store extended chars as entities (&ntilde) or keyword searching won't match them

    verify_html : false, // allow all tags and attributes

    // add file modified time on end of url so updated files won't be cached by the browser
    content_css : "$wysiwygCssUrl"
  });

  //--></script>

__HTML__;
}

?>
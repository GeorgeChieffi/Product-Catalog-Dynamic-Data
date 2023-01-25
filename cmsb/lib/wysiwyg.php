<?php

// NOTE: If you want to make changes to this file save it as
// wysiwyg_custom.php and it will get loaded instead of this
// file and won't get overwritten when you upgrade.

// NOTE: You can find the CSS for the wysiwyg in /lib/wysiwyg.css
// save wysiwyg.css file as wysiwyg_custom.css file as well if you want to
// make changes to the wysiwyg stylesheet

// this is called once at the top of the page
function loadWysiwygJavascript() {

  // uncomment the library source you want to use
  $stableVersion = "4.6.7";  // for CDNs only - Latest "stable" version defined here: https://www.tiny.cloud/docs-4x/get-started-cloud/editor-plugin-version/#stablereleasestream
  $tinymceJS     = "{$GLOBALS['CMS_ASSETS_URL']}/3rdParty/TinyMCE4/tinymce.gzip.js";                // Local CMS version (default, creates compressed output)
  //$tinymceJS   = "{$GLOBALS['CMS_ASSETS_URL']}/3rdParty/TinyMCE4/tinymce.min.js";                 // Local CMS version (uncompressed)
  //$tinymceJS   = "https://cdnjs.cloudflare.com/ajax/libs/tinymce/$stableVersion/tinymce.min.js";  // Cloudflare CDN

  // output tag
  print "<script src='$tinymceJS'></script>";
  
}

// this is called once for each wysiwyg editor on the page
function initWysiwyg($fieldname, $uploadBrowserCallback) {
  global $SETTINGS;
  $includeDomainsInLinks = $SETTINGS['wysiwyg']['includeDomainInLinks'] ? "remove_script_host: false, // domain name won't be removed from absolute links" : '';
  $programUrl            = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
  $programUrl            = preg_replace("/ /", "%20", $programUrl);

  // load either wysiwyg_custom.css (if exists) or wysiwyg.css
  $wysiwygCssFilename    = file_exists( __DIR__ .'/wysiwyg_custom.css' ) ? 'wysiwyg_custom.css' : 'wysiwyg.css';
  $wysiwygCssUrl         = noCacheUrlForCmsFile("lib/$wysiwygCssFilename");  // add file modified time on end of url so updated files won't be cached by the browser

  // call custom wysiwyg functions named: initWysiwyg_sectionName_fieldName() or initWysiwyg_sectionName()
  if (__FUNCTION__ == 'initWysiwyg') {
    $fieldnameWithoutPrefix = preg_replace("/^field_/", '', $fieldname);
    $fieldSpecificFunction   = "initWysiwyg_{$GLOBALS['tableName']}_$fieldnameWithoutPrefix";
    $sectionSpecificFunction = "initWysiwyg_{$GLOBALS['tableName']}";

    if (function_exists($fieldSpecificFunction))   { return call_user_func($fieldSpecificFunction, $fieldname, $uploadBrowserCallback); }
    if (function_exists($sectionSpecificFunction)) { return call_user_func($sectionSpecificFunction, $fieldname, $uploadBrowserCallback); }
  }

  // display field
  $uploadsNotEnabled = jsEncode(t('Uploads are not enabled for this field.'));
  print <<<__HTML__

  <script language="javascript" type="text/javascript"><!--
  tinyMCE.init({
    mode:    "exact",
    theme:   "modern",
    branding: false,
    language: "{$SETTINGS['wysiwyg']['wysiwygLang']}",

    // Menubar: set to true to display the menus on top of the editor buttons. To configure the menu items, see: https://www.tiny.cloud/docs-4x/configure/editor-appearance/#menu
    menubar: false,

    // Define toolbar buttons. See list of toolbar buttons here: https://www.tiny.cloud/docs-4x/advanced/editor-control-identifiers/#toolbarcontrols
    toolbar1: "formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | superscript subscript charmap | removeformat fullscreen",
    toolbar2: "forecolor backcolor | link anchor | blockquote hr image media table | pastetext paste | customcodesample code",
    toolbar3: '',

    // formatselect options - reference: https://www.tiny.cloud/docs-4x/configure/content-formatting/#block_formats
    block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre',

    // fontsizeselect options - reference: https://www.tiny.cloud/docs-4x/configure/content-formatting/#fontsize_formats
    fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',

    // codesample languages config
    codesample_languages: [
      {text: 'Code', value: 'php'}, // php also provides HTML/CSS/JS formatting
    ],
    
    // customcodesample button setup 
    // NOTE: We're replacing the "codesample" button to make functionality more consistent and enable wrapping text selections with formatted code blocks.
    // - To display full formatting on front-end, download and include JS and CSS from https://prismjs.com/ on website. Requires (at minimum): Core, Markup, CSS, C-like, JavaScript, Markup Templating, and PHP
    // - To restore default functionality (add plain <code></code> around selections), replace "customcodesample" in toolbar2 line above with "codesample"
    setup: function (editor) {
      editor.addButton('customcodesample', {
        tooltip: 'Insert/Edit code sample',
        icon: 'mce-ico mce-i-codesample',
        onclick: function (e) {
          
          // get the HTML and text of the current editor selection
          var contentHTML = editor.selection.getContent();
          var contentText = editor.selection.getContent({format: 'text'});
          
          // if selection is empty or starts with a <pre> tag, use codesample plugin functionality (insert/edit code block)
          if (contentHTML.indexOf('<pre') === 0 || contentHTML == '') { editor.execCommand('codesample', false); }
          
          // otherwise wrap selection in prism-formatted code block
          else { editor.insertContent('<pre class="language-php"><code>' + contentText + '</code></pre>'); }
          
        }
      });
    },

    // styleselect options - reference: https://www.tiny.cloud/docs-4x/configure/content-formatting/#style_formats
    // Note: Selecting a 'style format' from the 'Formats' dropdown adds whatever classes and styles are listed below
    // ... to the selected content and surrounds that content with the tag specified in inline, block, or selector.
    // ... If using classes, make sure they're defined in both lib/wysiwyg.css and your website CSS files.
    // style_formats: [
    //   { title: 'Example Class', selector: 'p',  classes: 'exampleClass' },
    //   { title: 'Red header',    block: 'h1',    styles: {color: '#ff0000'} },
    //   { title: 'Red text',      inline: 'span', styles: {color: '#ff0000'} },
    //   { title: 'Bold text',     inline: 'b'},
    //   { title: 'Example 1',     inline: 'span', classes: 'example1' },
    //   { title: 'Example 2',     inline: 'span', classes: 'example2' }
    // ],

    // Toolbar buttons size
    toolbar_items_size: 'small',

    // Statusbar: set to true to display status bar with editor resize handle at the bottom. See: https://www.tiny.cloud/docs-4x/configure/editor-appearance/#statusbar
    statusbar: false,

    // Load Plugins - list of available plugins can be found here: https://www.tiny.cloud/docs-4x/plugins/
    plugins: "table,fullscreen,paste,media,lists,charmap,textcolor,link,anchor,hr,paste,image,code,codesample",
    // NOTE: contextmenu, temporarily removed as it was prevent browser-based spellchecks from being accessed with right click (unless ctrl-right click was clicked)
    
    // Paste Settings - Docs: https://www.tiny.cloud/docs-4x/plugins/paste/
    paste_as_text: true, // enabled paste as text by default: https://www.tiny.cloud/docs-4x/plugins/paste/#paste_as_text

    // v2.50 - allow style in body (invalid XHTML but required to style html emails since many email clients won't display remote styles or styles from head)
    valid_children: "+body[style]", // docs: https://www.tiny.cloud/docs-4x/configure/content-filtering/#valid_children

    // Spellchecker plugin - No longer supported as Google no longer has a Public API.  Now using built in browser spellchecks
    browser_spellcheck: true,

    // Force <br> instead of <p> - see: https://www.tiny.cloud/docs-4x/configure/content-filtering/#forced_root_block
    // Uncomment these lines to enable this for new records
    //forced_root_block: false,

    //
    elements: '$fieldname',
    file_picker_callback: function(callback, value, meta) {
        if ('$uploadBrowserCallback') { $uploadBrowserCallback(callback, value, meta); }
        else                          { alert("$uploadsNotEnabled"); }
    },
    relative_urls: false,
    document_base_url: "/",

    $includeDomainsInLinks
    entity_encoding: "raw", // don't store extended chars as entities (&ntilde) or keyword searching won't match them

    verify_html: false, // allow all tags and attributes

    // reference: https://www.tiny.cloud/docs-4x/configure/content-appearance/#content_css
    content_css: "$wysiwygCssUrl"
    
  });

  //--></script>

__HTML__;
}

// eof
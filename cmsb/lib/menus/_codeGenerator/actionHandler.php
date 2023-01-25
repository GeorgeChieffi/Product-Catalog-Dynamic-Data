<?php
// define globals
global $APP; //, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX;
$APP['selectedMenu'] = 'admin'; // show admin menu as selected

// for debugging
$GLOBALS['CG2_DEBUG'] = false; // add "Debug: Run Viewer >>" button on code page that runs PHP code

### check access level
if (!$GLOBALS['CURRENT_USER']['isAdmin']) {
  alert(t("You don't have permissions to access this menu."));
  showInterface('');
}

// load common generator functions
require_once "generator_functions.php";

// register internal generators
$newApiGenerators = ['api_listPage','api_detailPage']; //, Not yet supported: 'api_comboPage', 'api_rssFeed', 'api_categoryMenu'
$legacyGenerators = ['listPage','detailPage', 'comboPage', 'rssFeed', 'categoryMenu']; // this order is maintained
$internalGenerators = array_merge($legacyGenerators, $newApiGenerators);
foreach ($internalGenerators as $file) { require_once($file .".php"); }



// Dispatch generators
if (@$_REQUEST['_generator']) {
  $generator = array_where(getGenerators('all'), array('function' => $_REQUEST['_generator']));
  $generator = array_shift($generator);
  $error     = sprintf("Unknown generator '%s'!", htmlencode($_REQUEST['_generator']) );
  if ($generator) { call_user_func($generator['function'], $generator['function'], $generator['name'], $generator['description'], $generator['type']); }
  else            { cg2_homepage($error); }
  exit;
}

// show menu (if no generator specified)
cg2_homepage();
exit;


// Show menu of code generators
function cg2_homepage($error = '') {
  if ($error) { alert($error); }

  // prepare adminUI() placeholders
  $adminUI = [];
  
  // main content
  $adminUI['CONTENT'] = '';
  
  // list internal generators
  $adminUI['CONTENT'] .= _cg2_getGeneratorList(
    t("Create a Viewer"),
    t("'Viewers' are PHP files that display the data from the CMS in all the different 'views' you might have on your site."),
    "private"
  );

  // list other generators (added by plugins)
  $adminUI['CONTENT'] .= _cg2_getGeneratorList(
    t("Other Generators"),
    t("Plugins can add their own code generators here"),
    "public"
  );

  // list legacy internal generators
  $adminUI['CONTENT'] .= _cg2_getGeneratorList(
    t("Legacy Code Generators"),
    t("These code generators shipped with earlier versions of the software.  The viewers they create will work forever, but this section will be removed in future."),
    "legacy"
  );
  
  // list experimental generators
  //$adminUI['CONTENT'] .= _cg2_getGeneratorList(
  //  t("Experimental Code Generators"),
  //  t("These are code generators we're working on for upcoming versions.  These might change without notice.  Feel free to experiment with these and provide feedback.  "),
  //  "experimental"
  //);

  // compose and output the page
  cg_adminUI($adminUI);
  exit;
}

// show list of generators for generator homepage
function _cg2_getGeneratorList($heading, $description, $type) {
  $html       = '';

  // get generators
  $generators = getGenerators($type);
  if (!$generators) { return; } 

  // separator
  $separatorAttr = [];
  $separatorAttr['label'] = t($heading);
  if (in_array($type, ['legacy', 'experimental'])) {
    $separatorAttr['class'] = 'clear separator-collapsible separator-collapsed';
  }
  
  // list header
  $html .= adminUI_separator($separatorAttr);
  $html .= "<div>\n";
  $html .= "<p>" .htmlencode($description). "</p>\n";
  $html .= "<table class='data table table-striped table-hover'>\n";

  // list rows
  $rows = '';
  foreach ($generators as $generator) {
    $link     = "?menu=" .urlencode(@$_REQUEST['menu']). "&amp;_generator=" .urlencode($generator['function']);
    if (@$_REQUEST['tableName']) { $link .= "&amp;tableName=" . urlencode($_REQUEST['tableName']); }
    $rows    .= "<tr class='listRow'>\n";
    $rows    .= " <td><a href='$link'>" .htmlencode(t($generator['name'])). "</a></td>\n";
    $rows    .= " <td>" .htmlencode(t($generator['description'])). "</td>\n";
    $rows    .= "</tr>\n";
  }
  if (!$rows) {
    $rows    .= "<tr class='listRow'>\n";
    $rows    .= " <td style='color: #999'>".t('There are current no generators in this category.')."</td>\n";
    $rows    .= "</tr>\n";
  }
  $html .= $rows;

  // list footer
  $html .= "</table>\n";
  $html .= "<br>\n";
  $html .= "</div>\n";

  
  //
  return $html;
}



//eof
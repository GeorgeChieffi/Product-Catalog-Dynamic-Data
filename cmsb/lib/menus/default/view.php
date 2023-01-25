<?php
global $CURRENT_USER, $tableName, $escapedTableName, $schema, $menu, $isSingleMenu, $preSaveTempId;
require_once "lib/menus/default/view_functions.php";
require_once "lib/viewer_functions.php";

// always edit record 1 for single menu
if ($isSingleMenu) { $_REQUEST['num'] = 1; }

### load record
$num = (int) @$_REQUEST['num'];

// error checking
if ($escapedTableName == '') { die("no tablename specified!"); }
if ($num != (int) $num)      { die("record number value must be an integer!"); }

// load record
$GLOBALS['RECORD'] = [];
if ($num) {
  list($records) = getRecords(array(
    'tableName'               => $tableName,
    'where'                   => mysql_escapef(" num = ? ", $num),
    'limit'                   => '1',
    'loadCreatedBy'           => false,
    'allowSearch'             => false,
    'loadUploads'             => false,
    'loadPseudoFields'        => true,  // This is needed to display list labels on "view" menu
    'ignoreHidden'            => true,  // ... the rest of these settings are needed to show "all" records
    'ignorePublishDate'       => true,  // ... even if they are hidden, etc. since this is the backend we
    'ignoreRemoveDate'        => true,  // ... want to show everything.
    'includeDisabledAccounts' => true,  //
  ));
  $GLOBALS['RECORD'] = @$records[0]; // get first record
}
if (!$GLOBALS['RECORD']) {
  alert(t("Couldn't view record (record no longer exists)!"));
  include('lib/menus/default/list.php');
  exit;
}

//
//doAction('record_preedit', $tableName, @$_REQUEST['num']);


// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ $schema['menuName'] => '?menu=' . $tableName ];

// page description
if (!empty($schema['_description'])) { $adminUI['DESCRIPTION'] = $schema['_description']; }

// buttons
// .. check if we should display the edit button
$displayEditButton  = !@$schema['_disableModify'];
$userAccessAll      = @$CURRENT_USER['accessList']['all']['accessLevel'];
$userAccessSection  = @$CURRENT_USER['accessList'][$tableName]['accessLevel'];
if ($userAccessAll <= 3 && ($userAccessAll == 1 && $userAccessSection <= 3)){
  // don't show edit button if user access is 'viewer' or below (acceess level 3 or below)
  $displayEditButton = false;
}
//
$adminUI['BUTTONS'] = [];
if ($displayEditButton) {
  $onClick = 'parent.location=\'?menu='.urlencode($menu).'&action=edit&num='.urlencode($_REQUEST['num']).'\'';
  $adminUI['BUTTONS'][] = ['type' => 'button', 'btn-type' => 'default', 'label' => t('Edit'), 'onclick' => $onClick, 'value' => '1'];
}
$adminUI['BUTTONS'][] = [ 'name' => 'cancel', 'label' => t('Cancel'), 'onclick' => 'viewCancel(); return false;', ];

// advanced actions
$adminUI['ADVANCED_ACTIONS'] = [];
if ($GLOBALS['CURRENT_USER']['isAdmin']) {
  $adminUI['ADVANCED_ACTIONS']['Admin: Edit Section']   = '?menu=database&amp;action=editTable&amp;tableName=' . urlencode($tableName);
  $adminUI['ADVANCED_ACTIONS']['Admin: Code Generator'] = '?menu=_codeGenerator&amp;tableName=' . urlencode($tableName);
}

// form tag and hidden fields
$adminUI['FORM'] = [ 'onsubmit' => 'if (typeof tinyMCE.triggerSave == "function") { tinyMCE.triggerSave(); }', 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => $menu,                       'id' => 'menu',          ],
  [ 'name' => '_returnUrl',     'value' => @$_REQUEST['returnUrl'],     'id' => 'returnUrl',     ],
  [ 'name' => '_defaultAction', 'value' => 'list',                                               ],
  [ 'name' => 'num',            'value' => @$_REQUEST['num'],           'id' => 'num',           ],
  [ 'name' => 'preSaveTempId',  'value' => $preSaveTempId,              'id' => 'preSaveTempId', ],
  [ 'name' => 'dragSortOrder',  'value' => @$_REQUEST['dragSortOrder'],                          ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  <div class="form-horizontal">
    <?php
    doAction('viewPage_preShowViewFormRows');
    showViewFormRows($GLOBALS['RECORD']);
    doAction('viewPage_postShowViewFormRows');
    ?>
  </div>
<?php });

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture(function() { ?>
  <script type="text/javascript" src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/jqueryForm.js"></script>
  <script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/menus/default/view_functions.js"); ?>"></script>
  <script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/menus/default/common.js"); ?>"></script>
  <script type="text/javascript" src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/uploadifive/jquery.uploadifive.min.js"></script>
<?php });

// compose and output the page
adminUI($adminUI);

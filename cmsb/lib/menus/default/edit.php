<?php

global $tableName, $escapedTableName, $schema, $menu, $isSingleMenu, $preSaveTempId;
require_once "lib/menus/default/edit_functions.php";

// always edit record 1 for single menu
if ($isSingleMenu) { $_REQUEST['num'] = 1; }

// new records - generate $preSaveTempId for uploads if no record number
$preSaveTempId = '';
if (!@$_REQUEST['num']) { $preSaveTempId = uniqid('x'); }

### load record
$num = (int) @$_REQUEST['num'];

// error checking
if ($escapedTableName == '') { die("no tablename specified!"); }
if ($num != (int) $num)      { die("record number value must be an integer!"); }

// load record
$GLOBALS['RECORD'] = [];
if ($num) {
  $GLOBALS['RECORD'] = mysql_get($tableName, $num);
}

//
doAction('record_preedit', $tableName, @$_REQUEST['num']);

//
$previewUrl = @$schema['_previewPage'] ?: @$schema['_detailPage'];
if ($previewUrl) { $previewUrl = PREFIX_URL .$previewUrl. '?' .urlencode(t('preview')). '-9999999999'; } // note that 9999999999 is a special number which getRecords() uses to know this is a preview request
$showPreviewButton = !@$schema['_disablePreview'] && $previewUrl;
$showEraseButton  = !@$schema['_disableErase'] && !@$schema['_disableEraseFromModify'];


// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ $schema['menuName'] => '?menu=' . $tableName ];

// page description
if (!empty($schema['_description'])) { $adminUI['DESCRIPTION'] = $schema['_description']; }

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][]   = [ 'name' => '_action=save', 'label' => t('Save'),                                   ];
if ($showPreviewButton) {
  $adminUI['BUTTONS'][] = [ 'name' => 'preview',      'label' => t('Preview'), 'onclick' => 'editPreview(); return false;', ];
}
if ($showEraseButton) {
  $adminUI['BUTTONS'][]   = [ 'name' => 'Erase',      'label' => t('Erase'), 'onclick' => "confirmEraseRecord('" .htmlencode($tableName). "','" .$num. "');return false;", ];
}
$adminUI['BUTTONS'][]   = [ 'name' => 'cancel',       'label' => t('Cancel'),  'onclick' => 'editCancel(); return false;',  ];

// advanced actions
$adminUI['ADVANCED_ACTIONS'] = [];
if ($GLOBALS['CURRENT_USER']['isAdmin']) {
  $adminUI['ADVANCED_ACTIONS']['Admin: Edit Section']   = '?menu=database&action=editTable&tableName=' . urlencode($tableName);
  $adminUI['ADVANCED_ACTIONS']['Admin: Code Generator'] = '?menu=_codeGenerator&tableName='            . urlencode($tableName);
}
$adminUI['ADVANCED_ACTIONS'] = applyFilters('edit_advancedCommands', $adminUI['ADVANCED_ACTIONS']);

// form tag and hidden fields
$adminUI['FORM'] = [ 'onsubmit' => 'if (typeof tinyMCE.triggerSave == "function") { tinyMCE.triggerSave(); }', 'autocomplete' => 'off', 'class' => 'preventLeavingOnChange'];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => $menu,                       'id' => 'menu',          ],
  [ 'name' => '_returnUrl',     'value' => @$_REQUEST['returnUrl'],     'id' => 'returnUrl',     ],
  [ 'name' => '_previewUrl',    'value' => $previewUrl,                 'id' => 'previewUrl',    ],
  [ 'name' => '_defaultAction', 'value' => 'save',                                               ],
  [ 'name' => 'num',            'value' => @$_REQUEST['num'],           'id' => 'num',           ],
  [ 'name' => 'preSaveTempId',  'value' => $preSaveTempId,              'id' => 'preSaveTempId', ],
  [ 'name' => 'dragSortOrder',  'value' => @$_REQUEST['dragSortOrder'],                          ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  <div class="form-horizontal">
    <?php showFields($GLOBALS['RECORD']); ?>
  </div>
<?php });

// add extra scripts before form
$adminUI['PRE_FORM_HTML'] = ob_capture(function() { ?>
  <script type="text/javascript" src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/jqueryForm.js"></script>
  <script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/menus/default/edit_functions.js"); ?>"></script>
  <script type="text/javascript" src="<?php echo noCacheUrlForCmsFile("lib/menus/default/common.js"); ?>"></script>
  <script type="text/javascript" src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/uploadifive/jquery.uploadifive.min.js"></script>
  <?php loadWysiwygJavascript(); ?>
<?php });

// add modal and some javascript after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  <div id="iframeModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <iframe id="iframeModal-iframe" style="width: 100%; border: 0;"></iframe>
      </div>
    </div>
  </div>

  <script type="text/javascript"><!--
    $(document).ready(function(){
      initSortable(null, updateDragSortOrder_forList);
    });
  //--></script>
  <?php showWysiwygGeneratorCode() ?>
<?php });

// compose and output the page
adminUI($adminUI);

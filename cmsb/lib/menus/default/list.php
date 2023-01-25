<?php
global $TABLE_PREFIX, $CURRENT_USER, $tableName, $schema, $hasEditorAccess, $hasAuthorAccess, $hasViewerAccessOnly, $isMyAccountMenu, $menu;
if ($isMyAccountMenu) { die("Access not permitted for My Account menu!"); }

require_once "lib/menus/default/list_functions.php";
require_once "lib/viewer_functions.php";

//
redirectSingleRecordAuthorsToEditPage();

//
list($listFields, $records, $metaData) = list_functions_init();


//
doAction('list_postselect', $records, $listFields, $metaData);


// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ $schema['menuName'] => '?menu=' . $tableName ];

// page description
if (!empty($schema['_description'])) { $adminUI['DESCRIPTION'] = $schema['_description']; }

// buttons
$adminUI['BUTTONS'] = [];
$showCreateButton  = !@$schema['_disableAdd'] && !$hasViewerAccessOnly;
$showPreviewButton = !@$schema['_disablePreview'] && @$schema['_listPage'];
if ($showCreateButton) {
  $createUrl      = "?menu=" .urlencode($menu). "&action=add"; 
  $createOnClick  = "window.location='$createUrl'";
  $adminUI['BUTTONS'][] = [ 'name' => 'null', 'label' => t('Create'), 'onclick' => $createOnClick, 'type' => 'button' ];
}
if ($showPreviewButton) {
  $previewOnClick = 'document.forms.preview.submit();';
  $adminUI['BUTTONS'][] = [ 'name' => 'null', 'label' => t('Preview'), 'type' => 'button', 'onclick' => $previewOnClick, ];
}

// advanced actions
$adminUI['ADVANCED_ACTIONS'] = [];
$allowEraseSelected = !@$schema['_disableErase'] && !$hasViewerAccessOnly;
if ($allowEraseSelected)      { $adminUI['ADVANCED_ACTIONS']['Erase selected']        = 'eraseRecords';  }
if ($CURRENT_USER['isAdmin']) { $adminUI['ADVANCED_ACTIONS']['Admin: Edit Section']   = 'editSection';   }
if ($CURRENT_USER['isAdmin']) { $adminUI['ADVANCED_ACTIONS']['Admin: Code Generator'] = '?menu=_codeGenerator&tableName=' . $GLOBALS['tableName']; }
$adminUI['ADVANCED_ACTIONS'] = applyFilters('list_advancedCommands', $adminUI['ADVANCED_ACTIONS']);

// form tag and hidden fields
$adminUI['FORM'] = [ 'name' => 'searchForm', 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => $menu,             'id' => 'menu', ],
  [ 'name' => '_defaultAction', 'value' => 'list',                            ],
  [ 'name' => 'page',           'value' => $metaData['page'],                 ],
  [ 'name' => 'search',         'value' => 1,                                 ],
];

// main content
$adminUI['CONTENT'] = ob_capture('listPageContent', $listFields, $records, $metaData);

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture(function() use($schema) { ?>
  <form method="get" name="preview" action="<?php echo (PREFIX_URL.@$schema['_listPage'])?PREFIX_URL.@$schema['_listPage']:'?'; ?>" target="_blank" autocomplete="off">
  </form>
<?php });

// add modal and some javascript after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  <script src="<?php echo noCacheUrlForCmsFile("lib/menus/default/list_functions.js"); ?>"></script>
  <script>
  var showAdvancedSearch = <?php echo @$_REQUEST['showAdvancedSearch'] ? 'true' : 'false' ?>;
  $(document).ready(function(){
  <?php if (@$GLOBALS['schema']['menuType'] == 'category'): ?>
    initSortable(markSiblings, updateCategoryDragSortOrder);
  <?php else:?>
    initSortable(null, updateDragSortOrder_forList);
  <?php endif; ?>
  });
  
  function jumpToPage(currentPage, totalPages, menu) {
    var chosenPage = prompt('<?php echo t("Go to page") ?> (1 - ' + totalPages + ')', currentPage);
    if (chosenPage !== null) {
      var menu = $('#menu').val();
      chosenPage = parseInt(chosenPage, 10);
      if (chosenPage != currentPage) {
        if (isNaN(chosenPage) || chosenPage < 1) { chosenPage = 1; }
        if (chosenPage > totalPages)             { chosenPage = totalPages; }
        location.href = '?menu=' + menu + '&page=' + chosenPage;
      }
    }
    return false;
  }
  </script>
<?php });

// compose and output the page
adminUI($adminUI);

//
function listPageContent($listFields, $records, $metaData) {
  global $schema, $menu;
  ?>
    <!-- search -->
    <div class="form-horizontal">
      <?php
        $listPageSearchFields = isset($schema['listPageSearchFields']) ? $schema['listPageSearchFields'] : '';
        $searchRows           = _parseSearchFieldsFormat($listPageSearchFields);
        $primarySearchRow     = array_shift($searchRows);
        $secondarySearchRows  = $searchRows;
      ?>
      
      <!-- simple search -->
      <?php $searchField = getSearchField($primarySearchRow, 'PRIMARY'); ?>
      <?php if (!empty($searchField)): ?>
        <?php if ($searchField['requiresLabel']): ?>
          <div class="row" style="margin-bottom: 5px;">
            <div class="col-md-2">
              <label class="control-label">
                <?php echo $searchField['label'] ?>
              </label>
            </div>
            <div class="col-md-10">
              <?php echo $searchField['html'] ?>
            </div>
          </div>
        <?php else: ?>
          <div class="row" style="margin-bottom: 5px;">
            <div class="col-md-12">
              <?php echo $searchField['html'] ?>
            </div>
            <?php if ($searchField['description']): ?>
              <div class="help-block col-md-12">
                <?php echo $searchField['description']; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif ?>
      <?php endif; ?>
      
      <!-- advanced search -->
      <div class="hideShowSecondarySearchFields" data-animate="slide" style="<?php if (!@$_REQUEST['showAdvancedSearch']) { echo 'display: none'; } ?>">
        <?php foreach ($secondarySearchRows as $searchRow): ?>
          <?php $searchField = getSearchField($searchRow); ?>
          <div class="row" style="margin-bottom: 5px;">
            <div class="col-md-2">
              <label class="control-label">
                <?php echo $searchField['label'] ?>
              </label>
            </div>
            <div class="col-md-10">
              <div class="col-xs-12 col-sm-6 col-md-4 col-lg-3" style="padding: 0 6px 0 0">
              <?php echo $searchField['html'] ?>
              </div>
              <?php if ($searchField['description']): ?>
                <div class="help-block col-xs-12 col-sm-6 col-md-8 col-lg-9 nopadding">
                  <?php echo $searchField['description']; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach ?>
        
        <div class="row">
          <?php doAction('list_postAdvancedSearch', @$_REQUEST['menu']); ?>
          <div class="col-md-2">
            <label class="control-label" for="perPage">
              <?php et('Per page'); ?>
            </label>
          </div>
          <div class="col-md-10 form-inline">
            <select name="perPage" class="form-control">
              <?php echo getSelectOptions($metaData['perPage'], array(5, 10, 25, 50, 100, 250, 1000)); ?>
            </select>
            &nbsp;
            <?php
              echo adminUI_button(['label' => t('Update'), 'name' => 'search', 'btn-type' => 'default', 'value' => 1,]);
            ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- results and buttons row -->
    <div>
      <div class="pull-left" style="height: 54px;">
        <p style="position: relative; top: 50%; transform: translateY(-50%);">
          <?php if (@$_REQUEST['search'] || $metaData['totalMatches'] != $metaData['totalRecords']): ?>
            <?php printf(t('<b>Search</b> returned %s of %s records'), number_format($metaData['totalMatches']), number_format($metaData['totalRecords'])) ?>
            (<a href="?menu=<?php echo urlencode($menu) ?>&amp;_resetSearch=1" class="text-danger"><?php et('show all') ?></a>)



          <?php else: ?>
            <?php printf(t('Showing all %s records'), number_format($metaData['totalRecords'])) ?>
          <?php endif ?>
          
          <?php if (@$_REQUEST['sortDir']): ?>
            <br>
            <?php
              $sortFieldLabel = _getFieldLabel(@$_REQUEST['sortBy']);
              $phrase         = (@$_REQUEST['sortDir'] === 'asc') ? 'Sorting by %s' : 'Sorting by %s';
              printf(t($phrase), $sortFieldLabel);
            ?>
            (<a href="?menu=<?php echo urlencode($menu) ?>&amp;sortBy=&amp;sortDir="><?php et('reset') ?></a>)
          <?php endif ?>
          
        </p>
      </div>
      <div class="pull-right" style="margin: 10px 0;">

        
  <?php
    // create buttons
    $buttons = [[
      'label'    => t('More...'),
      'onclick'  => 'return toggleAdvancedSearchOptions();',
      'class'    => 'hideShowSecondarySearchFields',
      'style'    => @$_REQUEST['showAdvancedSearch'] ? 'display: none' : '',
      'type'     => 'button',
      'btn-type' => 'default',
    ],[
      'label'    => t('Less...'),
      'onclick'  => 'return toggleAdvancedSearchOptions();',
      'class'    => 'hideShowSecondarySearchFields',
      'style'    => !@$_REQUEST['showAdvancedSearch'] ? 'display: none' : '',
      'type'     => 'button',
      'btn-type' => 'default',
    ],[
      'label'    => t('Search'),
      'name'     => 'search',
      'value'    => 1,
    ]];

    foreach ($buttons as $buttonArgs) {
      if (!$primarySearchRow && !$secondarySearchRows && @$buttonArgs['name'] == 'search') {
        continue; // if there is no search fields, do not display the search button
      }
      echo adminUI_button($buttonArgs) . " "; 
    }

  ?>
        
      </div>
      <div class="clear"></div>
    </div>
    
  <?php
  doAction('list_preListTable', $records, $schema);
  
  echo getPaginationHTML($metaData, 'top');
  
  // list column headings
  showListTable($listFields, $records);
  
  doAction('list_postListTable_inner', $records, $schema);
  
  echo getPaginationHTML($metaData, 'bottom');
  
  doAction('list_postListTable', $records, $schema);
  
}


// eof
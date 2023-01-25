<?php

class RelatedRecordsField extends Field {

function __construct($fieldSchema) {
  parent::__construct($fieldSchema);
}

//
function getTableRow($record, $value, $formType) {
  global $isMyAccountMenu;
  $parentTable = $GLOBALS['menu'];

  // set field attributes
  $relatedTable = $this->relatedTable;
  $relatedWhere = getEvalOutput( @$this->relatedWhere );
  $seeMoreLink  = @$this->relatedMoreLink ? "?menu=$relatedTable&amp;search=1&amp;_ignoreSavedSearch=1&amp;" . getEvalOutput($this->relatedMoreLink) : '';


  // load list functions
  require_once "lib/menus/default/list_functions.php";
  require_once "lib/viewer_functions.php";

  // save and update globals
  list($originalMenu, $originalTableName, $originalSchema) = array($GLOBALS['menu'], $GLOBALS['tableName'], $GLOBALS['schema']);
  $GLOBALS['menu']      = $relatedTable;
  $GLOBALS['tableName'] = $relatedTable;
  $GLOBALS['schema']    = loadSchema( $relatedTable );
  
  // load list data
  list($listFields, $records, $metaData) = list_functions_init(array(
    'isRelatedRecords' => true,
    'tableName'      => $relatedTable,
    'where'          => $relatedWhere,
    'perPage'        => @$this->relatedLimit,
  ));


  ### show header
  $html = '';

  $recordCount = count($records);
  $oneOrZero   = ($recordCount > 0) ? 1 : 0;
  $seeMoreHTML = $seeMoreLink ? "<br><a href='$seeMoreLink'>" .htmlencode(t("see related records >>")). "</a>" : '';
  $showingText = sprintf(t('Showing %1$s - %2$s of %3$s related records'), $oneOrZero, $recordCount, $metaData['totalRecords']);
  ob_start(); ?>
  <div class="clear"></div>
  <div class="panel panel-default">
    <div class="panel-heading">
      <div class="row">
        <div class="col-sm-6">
          <h3><?php echo $this->label ?></h3>
        </div>
        <div class="col-sm-6 text-right">
          <?php echo $showingText ?>
          <?php echo $seeMoreHTML ?>
        </div>
      </div>
    </div>
    <div class="panel-body">

<?php $html .= ob_get_clean();

### show body

// show list
ob_start();
showListTable($listFields, $records, array(
  'isRelatedRecords' => true,
  'showView'         => @$this->relatedView,
  'showModify'       => @$this->relatedModify,
  'showErase'        => @$this->relatedErase,
  'showCreate'       => @$this->relatedCreate,
));
$html .= ob_get_clean();

### get footer
  $buttonsArray = [];
  if (@$this->relatedCreate) { // show "create" button for related records
    $buttonsArray[] = relatedRecordsButton(t('Create'), "?menu={$relatedTable}&action=edit&{$parentTable}Num=###");
  }
  $tableName      = $relatedTable;
  $buttonsArray   = applyFilters('relatedRecords_buttons', $buttonsArray, $tableName);

  $buttonsHTML    = '';
  foreach ($buttonsArray as $buttonArray) { $buttonsHTML .= adminUI_button($buttonArray); }

$html .= <<<__FOOTER__

    <div style='float:right; padding-top: 3px'>
      $buttonsHTML
    </div>
    <div class='clear'></div>

    </div><!-- End .panel-body -->
  </div><!-- End .panel panel-default -->
__FOOTER__;

  // reset globals
  list($GLOBALS['menu'], $GLOBALS['tableName'], $GLOBALS['schema']) = array($originalMenu, $originalTableName, $originalSchema);

  //
  return $html;

}

} // end of class

// relatedRecordsButton(t('Create'), ?menu=tableName&action=add&relatedNum=###"); // ### gets replaced with record number
// note: this can be called by this lib as well as by plugins
function relatedRecordsButton($label, $url, $addReturnUrl = true) {

  // add return url
  $returnUrl = thisPageUrl(array('num' => '###'), true);                        // ### gets replaced by saveRedirectAndReturn() in edit_functions.js or manually below in view block
  $returnUrl = str_replace('&action=add', '&action=edit&num=###', $returnUrl); // When clicking relatedRecords->Create on an add page it saves the record, so this returns user to saved record
  if (@$GLOBALS['action'] == 'view' || $addReturnUrl) {
    $url .= "&returnUrl=" .urlencode($returnUrl);
  }
  
  //
  if(@$GLOBALS['action'] == 'view') {
    $url = str_replace('###', $_REQUEST['num'], $url);
    $button = [
      'label'   => $label,
      'type'    => 'button',
      'name'    => '_null_',
      'value'   => '1',
      'onclick' => 'self.location = "'.$url.'"; return true;',
    ];
    return $button;
  }

  //
  $button = [
    'label'   => $label,
    'type'    => 'button',
    'name'    => '_null_',
    'value'   => '1',
    'onclick' => 'saveRedirectAndReturn("' .jsEncode($url). '"); return false;',
  ];

  return $button;
}

// eof
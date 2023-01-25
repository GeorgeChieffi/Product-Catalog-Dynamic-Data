<?php

require_once "lib/menus/database/listTables_functions.php";

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('CMS Setup'), t('Section Editors') => '?menu=database&action=listTables' ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'null', 'label' => t('Add New Editor'), 'onclick' => 'addNewMenu(); return false;', ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'database', 'id' => 'menu', ],
  [ 'name' => '_defaultAction', 'value' => '',                         ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  <!-- Modal for "Add New Editor" -->
  <div id="addEditorModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">

      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><?php echo t('Add New Editor'); ?>...</h4>
        </div>
        <?php include("addTable.php"); ?>
      </div>

    </div>
  </div>

  <script src="<?php echo noCacheUrlForCmsFile("lib/menus/database/listTables_functions.js"); ?>"></script>
  <script src="<?php echo noCacheUrlForCmsFile("lib/menus/database/addTable_functions.js"); ?>"></script>

<?php });

// compose and output the page
adminUI($adminUI);


function _getContent() {
  $tableList = getTableList();

  // get prefixed table names as mysql escaped CSV
  $tableNames = array_column($tableList, 'tableName');
  $prefixedTableNames = [];
  foreach ($tableNames as $tableName) { $prefixedTableNames[] = getTableNameWithPrefix($tableName); }
  $prefixedTableNamesAsCSV = mysql_escapeCSV($prefixedTableNames);

  // get tableNames to Bytes
  // Note: Looking for TABLE_SCHEMA and TABLE_NAME explicitly saves us 4+ seconds on servers with many tables
  // Ref: http://dev.mysql.com/doc/refman/5.5/en/information-schema-optimization.html
  $where        = "WHERE TABLE_SCHEMA='{$GLOBALS['SETTINGS']['mysql']['database']}' AND TABLE_NAME IN ($prefixedTableNamesAsCSV)";
  $query        = "SELECT TABLE_NAME, (data_length+index_length) as 'TABLE_SIZE' FROM information_schema.tables $where";
  $resultRows   = mysql_select_query($query, true);
  $tablesToBytes = array_column($resultRows, 1, 0);

  ?>
    <table class="data sortable table table-striped table-hover">
     <thead>
      <tr class="nodrag nodrop">
        <th class="hidden-xs text-center" style="width:40px;"><?php et('Drag') ?></th>
        <th><?php et('Menu Name') ?></th>
        <th class="min-tablet-p"><?php et('Menu Type') ?></th>
        <th class="min-tablet-p"><?php et('MySQL Table') ?></th>
        <th class="all text-center"><?php et('Action') ?></th>
       </tr>
     </thead>
     <tbody>
     <?php
      $menuCount = 0;
      foreach ($tableList as $row):
          if (@$row['tableHidden']) { continue; };
          $trClass = 'listRow' . ($row['menuHidden'] ? ' text-muted' : '');
          $menuCount++;

          $leftPadding = 0;
          if ($row['menuType'] != 'menugroup') { $leftPadding += 1; }
          if (@$row['_indent'])                { $leftPadding += $row['_indent']; }
          // id="table_<?php echo ()

          //
          $nameColStyle = "padding-left: {$leftPadding}em; ";
          if ($row['menuType'] == 'menugroup') { $nameColStyle .= "font-weight: bold; padding: 10px 5px 10px 0px"; }

      ?>
      <tr class="<?php echo $trClass ?>">
        <td style="vertical-align: middle" class="dragger hidden-xs">
         <input type='hidden' name='_tableName' value='<?php echo $row['tableName'] ?>' class='_tableName'>
         <span    class="fa fa-chevron-down" aria-hidden="true" title="<?php et('Click and drag to change order.') ?>"></span><!--
         --><span class="fa fa-chevron-up"   aria-hidden="true" title="<?php et('Click and drag to change order.') ?>"></span>
        </td>
        <td style="<?php echo $nameColStyle ?>">
          <?php echo htmlencode($row['menuName']); ?>
          <div style="float: right; padding-right: 5px;"><?php if ($row['menuHidden']) { print ' (' .t('hidden'). ')'; } ?></div>
        </td>
        <td>
          <?php echo htmlencode($row['menuType']) ?>
        </td>
        <td>
          <?php echo htmlencode($row['tableName']) . " ({$row['recordCount']})" ?>

          <span class="text-muted pull-right">
          <?php // show table size on disk
            $bytes = @$tablesToBytes[ getTableNameWithPrefix($row['tableName']) ];
            //echo number_format($bytes/pow(1024,2),2) . " MB";
            echo formatBytes($bytes, 2);
          ?>
          </span>

        </td>
        <td class="text-center">
          <a href="?menu=database&amp;action=editTable&amp;tableName=<?php echo urlencode($row['tableName']) ?>"><?php et('modify') ?></a>
          <?php if ($row['tableName'] == 'accounts'): ?>
            <?php echo t('erase'); ?>
          <?php else: ?>
            <a href="javascript:confirmEraseTable('<?php echo urlencode($row['tableName']) ?>')"><?php et('erase') ?></a>
          <?php endif ?>
        </td>
       </tr>
    <?php endforeach ?>
    </tbody>
    </table>

    <?php if ($menuCount == 0): ?>
      <table style="margin-right: 1px">
       <tr>
        <td class="listRowNotfound"><?php echo t('There are no menus.  Try adding one below.'); ?></td>
       </tr>
      </table>
    <?php endif ?>

  <?php
}

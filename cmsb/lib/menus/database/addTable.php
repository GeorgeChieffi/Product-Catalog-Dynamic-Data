<?php // this is included in listTables.php as the modal popup

function showPresetOptions() {

  $skipTables = array('custom','customSingle','customMulti','customCategory','customTextLink','customMenuGroup');
  foreach (getSchemaPresets() as $tableName => $menuName) {
    if (in_array($tableName, $skipTables)) { continue; }

    $encodedValue = htmlencode($tableName);
    $encodedLabel = htmlencode($menuName);
    print "<option value='$encodedValue'>$encodedLabel</option>\n";
  }
}

function showCopyOptions() {

  $includedTypes = array('single', 'multi', 'category');
  $skippedTables = array('accounts');
  foreach (getSortedSchemas() as $tableName => $schema) {
    if (preg_match("/^_/", $tableName))                  { continue; } // skip private tables
    if (in_array($tableName, $skippedTables))            { continue; } // skip system tables
    if (!in_array(@$schema['menuType'], $includedTypes)) { continue; } // skip unknown menu types

    $encodedValue = htmlencode($tableName);
    $encodedLabel = htmlencode(@$schema['menuName'] ?: $tableName);
    print "<option value='$encodedValue'>$encodedLabel</option>\n";
  }
}

?>

<form method="post" action="?" id="addTableForm" onsubmit="return false;" autocomplete="off">
  <input type="hidden" name="menu" value="database">
  <input type="hidden" name="_defaultAction" value="addTable_save">
  <?php echo security_getHiddenCsrfTokenField(); ?>
  <div class="modal-body">

    <div class="container form-horizontal">

      <div class="form-group">
        <div class="col-xs-2 control-label">
          <?php echo t('Menu Type'); ?>
        </div>
        <div class="col-xs-10">

          <label for="menuType-multi">
            <input type="radio" name="menuType" id="menuType-multi" value="multi">
            <?php echo t('<b>Multi Record</b> - multi record menus can have many records and are for sections such as News, FAQs, Jobs, Events, etc.'); ?>
          </label>
          <br>

          <label for="menuType-single">
            <input type="radio" name="menuType" id="menuType-single" value="single">
            <b><?php echo t('Single Record</b> - single record menus have only one record and are for single page sections such as About Us, or Contact Us.'); ?>
          </label>
          <br>

          <label for="menuType-preset" class="form-inline">
            <input type="radio" name="menuType" id="menuType-preset" value="preset">
            <select name="preset" id="preset" class="form-control">
              <option value=''>&lt;<?php echo t('Select Preset'); ?>&gt;</option>
              <?php showPresetOptions() ?>
            </select> - <?php echo t('pre-configured menus and fields for common websites sections.'); ?>
          </label>
          <br>

          <label for="menuType-copy" class="form-inline">
            <input type="radio" name="menuType" id="menuType-copy" value="copy">
            <select name="copy" id="copy" class="form-control">
              <option value=''>&lt;<?php echo t('Select Existing Section'); ?>&gt;</option>
              <?php showCopyOptions() ?>
            </select> - <?php echo t('copy an existing section.') ?>
          </label>
          <br>

          <label for="menuType-advanced" class="form-inline">
            <input type="radio" name="menuType" id="menuType-advanced" value="advanced">
            <select name="advancedType" id="advancedType" class="form-control" onchange="autoFillTableName()">
              <option value=''>&lt;<?php echo t('Advanced Menus'); ?>&gt;</option>
              <option value='category'><?php echo t('Category Menu'); ?></option>
              <option value='menugroup'><?php echo t('Menu Group'); ?></option>
              <option value='textlink'><?php echo t('Text Link'); ?></option>
            </select> - <span id="advancedDescription">...</span>
          </label>

        </div>
      </div>

      <div class="form-group">
        <label class="col-xs-2 control-label" for="menuName">
          <?php echo t('Menu Name'); ?>
        </label>
        <div class="col-xs-10">
          <input class="form-control" type="text" name="menuName" id="menuName" onkeyup="autoFillTableName()" onchange="autoFillTableName()">
        </div>
      </div>

      <div class="form-group">
        <label class="col-xs-2 control-label" for="tableName">
          <?php echo t('Table Name'); ?>
        </label>
        <div class="col-xs-10">
          <input class="form-control" type="text" name="tableName" id="tableName">
        </div>
      </div>

      <div class="form-group">
        <div class="col-xs-12">
          <p><?php echo t('Tip: Your table name is used in your viewer code so choose a short but meaningful name such as: news, articles, jobs, etc.'); ?></p>
        </div>
      </div>

    </div>

    <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/jqueryForm.js"></script>
    <script src="<?php echo noCacheUrlForCmsFile("lib/menus/database/addTable_functions.js"); ?>"></script>
    <script>
      function _updateAdvancedDescription(advancedType) {
        var description  = '';
        if      (advancedType == '')          { description = '<?php echo jsEncode(t("select an advanced menu type to see the description.")); ?>'; }
        else if (advancedType == 'category')  { description = '<?php echo jsEncode(t("category menus let you organize records in a tree structure and are for creating website menus and navigation.")); ?>'; }
        else if (advancedType == 'menugroup') { description = '<?php echo jsEncode(t("menu groups let you create menu headers to group related menu options under.")); ?>'; }
        else if (advancedType == 'textlink')  { description = '<?php echo jsEncode(t("text links let you add an external link to your menu that looks the same as a regular menu item.")); ?>'; }
        else                                  { description = "<?php echo jsEncode(t("Unknown advanced type")); ?> '" +advancedType+ "'"; }
        $('#advancedDescription').html( description );

      }
    </script>

    <!--[if lt IE 9]>
    <script src="3rdParty/clipone/plugins/respond.min.js"></script>
    <script src="3rdParty/clipone/plugins/excanvas.min.js"></script>
    <![endif]-->
    <script src="3rdParty/clipone/plugins/bootstrap/js/bootstrap.min.js"></script>

  </div>
  <div class="modal-footer">
    <?php echo adminUI_button(['label' => t('Cancel'), 'type' => 'button', 'data-dismiss' => 'modal' ]); ?>
    <?php echo adminUI_button(['label' => t('Create New Menu'), 'type' => 'submit']); ?>
  </div>
</form>

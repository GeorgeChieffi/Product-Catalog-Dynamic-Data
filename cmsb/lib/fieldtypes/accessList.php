<?php

class AccessListField extends Field {

  function __construct($fieldSchema) {
    parent::__construct($fieldSchema);
  }

// editFormHtml
function getTableRow($record, $value, $formType) {
  global $TABLE_PREFIX;

  // load access list
  $accessList = [];
  if (@$_REQUEST['num']) {
    $query  = "SELECT * FROM `{$TABLE_PREFIX}_accesslist` WHERE userNum = '" . mysql_escape($_REQUEST['num']) . "'";
    $result = mysqli()->query($query) or die("MySQL Error: ". htmlencode(mysqli()->error) . "\n");
    while ($record = $result->fetch_assoc()) {
      $accessList[ $record['tableName'] ] = $record;
    }
  }

  // get section list
  $sectionList = [];
  foreach (getSchemaTables() as $tableName) {
    $schema = loadSchema($tableName);
    
    // skip tables with menu type that isn't in the allowed list
    // .. or tables with tableHidden = 1, ie: Developer log
    $allowedMenuTypes = array('single','multi','category','menugroup','link','custom');
    if (!in_array(@$schema['menuType'], $allowedMenuTypes) || @$schema['tableHidden']) { continue; }
    
    $thisMenu = [];
    $thisMenu['menuName']  = htmlencode($schema['menuName']);

    $leftPadding = 1;
    if     (@$schema['menuType'] == 'menugroup') { $leftPadding = 0; }
    elseif (@$schema['_indent'])                 { $leftPadding += $schema['_indent']; }
    $thisMenu['menuName'] = "<span style='padding-left: {$leftPadding}em;'>". $thisMenu['menuName'] . "</span>\n";

    $thisMenu['menuOrder'] = $schema['menuOrder'];
    $thisMenu['tableName'] = $tableName;
    $thisMenu['menuType']  = $schema['menuType'];
    array_push($sectionList, $thisMenu);
    }
  uasort($sectionList, '_sortMenusByOrder');  // sort menus by order value

  // display field
  $allAccessLevel     = @$accessList['all']['accessLevel'];
  $sectionsDivStyle   = ($allAccessLevel != 1) ? "display: none;" : '';

  //
  ob_start();
?>
  <div class="form-group">
    <div class="col-sm-2 control-label"><?php echo $this->label; ?></div>
    <div class="col-sm-9">
      <div class="text-left">
        <table border="0" cellspacing="1" cellpadding="0">
          <thead>
            <tr>
              <th width="305"><?php et('Section Name') ?></th>
              <th width="115" style="text-align: center"><?php et('Access') ?></th>
              <th width="100" style="text-align: center"><?php et('Max Records') ?></th>
            </tr>
          </thead>
          <tr>
            <td class="listRow"><?php et('All Sections') ?></td>
            <td class="listRow" style="text-align: center">
              <select name="accessList[all][accessLevel]" class="form-control" onchange="(this.value=='1') ? $('.sectionAccessList').slideDown() : $('.sectionAccessList').slideUp();">
                <option value="0" <?php selectedIf($allAccessLevel, '0'); ?>><?php et('None') ?></option>
                <option value="3" <?php selectedIf($allAccessLevel, '3'); ?>><?php et('Viewer') ?></option>
                <option value="6" <?php selectedIf($allAccessLevel, '6'); ?>><?php et('Author') ?></option>
                <option value="7" <?php selectedIf($allAccessLevel, '7'); ?>><?php eht('Author & Viewer') ?></option>
                <option value="9" <?php selectedIf($allAccessLevel, '9'); ?>><?php et('Editor') ?></option>
                <option value="1" <?php selectedIf($allAccessLevel, '1'); ?>><?php et('By Section') ?></option>
              </select>
            </td>
            <td class="listRow" style="text-align: center"><?php et('No Limit') ?></td>
          </tr>
        </table>
        <script type="text/javascript">
        function toggleDisabledForAccessListMaxRecords(tablename) {
        var accessLevel = $("#accesslevel_"+tablename).val();
        var disableMaxRecords = (accessLevel == 9 || accessLevel == 3);
        if (disableMaxRecords) { $("#maxRecords_"+tablename).attr("disabled", true).css("background-color","#DDD");  }
        else                   { $("#maxRecords_"+tablename).removeAttr("disabled").css("background-color","#FFF");  }
        }
        </script>
        <div class="sectionAccessList" style="<?php echo $sectionsDivStyle; ?>">
          <div style="width: 0px; height: 0px;"></div><?php /* fixes IE7 issue which caused table to get no layout space, causing overlap and missing table content. the issue seems to be caused by a div with only a table inside. adding anything else inside the div seems to fix it, including &nbsp, but that adds extra whitespace, hence the div with no area */ ?>
          <table border="0" cellspacing="1" cellpadding="0" class="table-striped">
          <tbody>
          <?php
          // list sections
          foreach ($sectionList as $section) {
          $fieldnamePrefix   = "accessList[{$section['tableName']}]";
          $accessLevel       = @$accessList[ $section['tableName'] ]['accessLevel'];
          $maxRecords        = @$accessList[ $section['tableName'] ]['maxRecords'];
          $disableMaxRecords = ($accessLevel == 9 || $accessLevel == 3);
          $maxRecordsAttr    = $disableMaxRecords ? 'style="text-align: center; background-color: #DDD;" disabled="disabled"'
                                                  : 'style="text-align: center;"';

          ?>
          <tr>
            <td class="listRow" width="305">&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $section['menuName']; ?></td>
            <td class="listRow" width="115" style="text-align: center">
              <?php if ($section['menuType'] == 'single' || $section['tableName'] == 'accounts' || $section['menuType'] == 'menugroup' || $section['menuType'] == 'link'): ?>
                <input type="hidden"   name="<?php echo $fieldnamePrefix; ?>[accessLevel]" value="0">
                <input type="checkbox" name="<?php echo $fieldnamePrefix; ?>[accessLevel]" value="9" <?php checkedIf($accessLevel, '9'); ?>>
              <?php elseif ($section['menuType'] == 'multi'): ?>
                <select name="<?php echo $fieldnamePrefix; ?>[accessLevel]" class="form-control" id="accesslevel_<?php echo $section['tableName']; ?>" style="width: 140px" onchange="toggleDisabledForAccessListMaxRecords('<?php echo $section['tableName']; ?>')">
                  <option value="0" <?php selectedIf($accessLevel, '0'); ?>><?php et('None') ?></option>
                  <option value="3" <?php selectedIf($accessLevel, '3'); ?>><?php et('Viewer') ?></option>
                  <option value="6" <?php selectedIf($accessLevel, '6'); ?>><?php et('Author') ?></option>
                  <option value="7" <?php selectedIf($accessLevel, '7'); ?>><?php eht('Author & Viewer') ?></option>
                <option value="9" <?php selectedIf($accessLevel, '9'); ?>><?php et('Editor') ?></option>
                </select>
              <?php elseif ($section['menuType'] == 'category'): ?>
                <select class="form-control" name="<?php echo $fieldnamePrefix; ?>[accessLevel]" id="accesslevel_<?php echo $section['tableName']; ?>" style="width: 140px" onchange="toggleDisabledForAccessListMaxRecords('<?php echo $section['tableName']; ?>')">
                  <option value="0" <?php selectedIf($accessLevel, '0'); ?>><?php et('None') ?></option>
                  <option value="3" <?php selectedIf($accessLevel, '3'); ?>><?php et('Viewer') ?></option>
                  <option value="6" <?php selectedIf($accessLevel, '6'); ?>><?php et('Author') ?></option>
                  <option value="7" <?php selectedIf($accessLevel, '7'); ?>><?php eht('Author & Viewer') ?></option>
                  <option value="9" <?php selectedIf($accessLevel, '9'); ?>><?php et('Editor') ?></option>
                </select>
              <?php endif ?>
            </td>
            <td width="100" style="text-align: center">
              <?php if ($section['menuType'] == 'single'): ?>
              <?php printf(t("Single Page")); ?>
              <?php elseif ($section['tableName'] == 'accounts'): ?>
              <?php elseif ($section['menuType'] == 'multi'): ?>
                <input class="form-control" type="text" name="<?php echo $fieldnamePrefix; ?>[maxRecords]" id="maxRecords_<?php echo $section['tableName']; ?>" value="<?php echo $maxRecords; ?>" size="6" maxlength="6" <?php echo $maxRecordsAttr; ?>>
              <?php endif ?>
            </td>
          </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <br>
      <div style="font-size: 11px">
        <b><?php et('Access Levels:') ?></b><br>
        <div style="padding-left: 20px;">
          <?php et('None - Don\'t allow user to access this section') ?><br>
          <?php et('Viewer - User can view any record in this section (must also be enabled in section editor)') ?><br>
          <?php et('Author - User can only access records they have created') ?><br>
          <?php eht("Author & Viewer - User can view any record and modify records they've created") ?><br>
          <?php et('Editor - User can access any records in this section') ?><br>
        </div>
        <?php et('Max Records: Max records user is allowed to create (for regular users only - leave blank for unlimited)') ?>
      </div>
    </div>
  </div>
 </div>

<?php

  $html = ob_get_clean();
  return $html;

}

} // end of class

?>

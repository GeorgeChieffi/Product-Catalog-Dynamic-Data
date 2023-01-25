<?php
require_once "lib/menus/database/editField_functions.php";

$tableName = @$_REQUEST['tableName'];

$field = getFieldAttributes($_REQUEST['fieldname']);

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [
  t('CMS Setup'),
  t('Section Editors') => '?menu=database&action=listTables',
  @$schema['menuName'] => '?menu=database&action=editTable&tableName=' . urlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])),
  t('Field Editor'),
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => '', 'label' => t('Save'), 'onclick' => '', ];
$adminUI['BUTTONS'][] = [ 'name' => '', 'label' => t('Save & Copy'), 'onclick' => "$('#saveAndCopy').val(1); $('form').submit(); return false;", ];
$adminUI['BUTTONS'][] = [ 'name' => '', 'label' => t('Cancel'), 'onclick' => 'window.location="?menu=database&action=editTable&tableName=' . htmlencode( getTableNameWithoutPrefix($_REQUEST['tableName'])) . '"; return false;', ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off', 'id' => 'editFieldForm', 'onsubmit' => 'return false;' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'database',                                                               ],
  [ 'name' => '_defaultAction', 'value' => 'editField',                                                              ],
  [ 'name' => 'tableName',      'value' => getTableNameWithoutPrefix($_REQUEST['tableName']), 'id' => 'tableName',   ],
  [ 'name' => 'fieldname',      'value' => $_REQUEST['fieldname'],                            'id' => 'fieldname',   ],
  [ 'name' => 'order',          'value' => @$field['order'],                                  'id' => 'order',       ],
  [ 'name' => 'editField',      'value' => '1',                                                                      ],
  [ 'name' => 'save',           'value' => '1',                                                                      ],
  [ 'name' => 'saveAndCopy',    'value' => '0',                                               'id' => 'saveAndCopy', ],
];

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/jqueryForm.js"></script>
  <script src="<?php echo noCacheUrlForCmsFile("lib/menus/database/editField_functions.js"); ?>"></script>

<?php });

// main content
$adminUI['CONTENT'] = ob_capture('_getContent', $field);

function _getContent($field) {
  global $tableName, $TABLE_PREFIX;
  
  $showValidation = @$_COOKIE['showAdvancedFieldEditorOptions'];
  $showAdvanced   = @$_COOKIE['showAdvancedFieldEditorOptions'];

  $tablesAndFieldnames  = getTablesAndFieldnames();
  $optionFieldnames     = @$tablesAndFieldnames[@$field['optionsTablename']];
  if (!$optionFieldnames) { $optionFieldnames = []; }

  $thisTablesFieldnames = $tablesAndFieldnames[ getTableNameWithoutPrefix($tableName) ];

  ?>
  
  <script>
  var tablesAndFieldnames = <?php echo json_encode($tablesAndFieldnames); ?>;
  </script>

  <div class="form-horizontal">
    <div class="form-group">
      <label class="col-xs-2" class="control-label" for="label"><?php echo t('Field Label');?></label>
      <div class="col-xs-9">
        <input class="form-control" name="label" id="label" size="30" value="<?php echo htmlencode($field['label']) ?>" onkeyup="autoFillBlankFieldName()" onchange="autoFillBlankFieldName()">
        <p class="help-block" id="label-help"><?php echo t('(displayed beside field in editor)'); ?></p>
        <p class="help-block" id="label-help-separator"><?php echo t('(displayed in section editor field list only)'); ?></p>
      </div>
    </div>
    <div class="form-group">
      <label class="col-xs-2" class="control-label" for="newFieldname"><?php echo t('Field Name');?></label>
      <div class="col-xs-9">
        <input class="form-control" name="newFieldname" id="newFieldname" size="30" value="<?php echo htmlencode(@$field['newFieldname']) ?>" onkeyup="showSpecialFieldDescription();">
        <p class="help-block"><?php echo t('(used in PHP viewer code)');?></p>
        <div id="specialFieldDescription"></div>
      </div>
    </div>
    <div class="form-group">
      <label class="col-xs-2" class="control-label" for="fieldType"><?php echo t('Field Type');?></label>
      <div class="col-xs-9">
        <select class="form-control" name="type" id="fieldType" onchange="displayOptionsForFieldType(0)" onkeyup="displayOptionsForFieldType(0)">
          <optgroup label="<?php eht('Basic Field Types'); ?>">
          <option value="none"      <?php selectedIf($field['type'], 'none') ?>><?php echo t('none'); ?></option>
          <option value="textfield" <?php selectedIf($field['type'], 'textfield') ?>><?php echo t('text field');?></option>
          <option value="textbox"   <?php selectedIf($field['type'], 'textbox') ?>><?php echo t('text box');?></option>
          <option value="wysiwyg"   <?php selectedIf($field['type'], 'wysiwyg') ?>><?php echo t('wysiwyg');?></option>
          <option value="date"      <?php selectedIf($field['type'], 'date') ?>><?php echo t('date/time');?></option>
          <option value="list"      <?php selectedIf($field['type'], 'list') ?>><?php echo t('list');?></option>
          <option value="checkbox"  <?php selectedIf($field['type'], 'checkbox') ?>><?php echo t('checkbox');?></option>
          <option value="upload"    <?php selectedIf($field['type'], 'upload') ?>><?php echo t('upload');?></option>
          <option value="separator" <?php selectedIf($field['type'], 'separator') ?>><?php echo t('--- separator ---');?></option>
          <option value="tabGroup"  <?php selectedIf($field['type'], 'tabGroup') ?>><?php echo t('--- tab group ---');?></option>
          <optgroup label="<?php eht('Advanced Field Types'); ?>">
          <option value="relatedRecords" <?php selectedIf($field['type'], 'relatedRecords') ?>><?php echo t('Related Records');?></option>
          <option value="hidden"    <?php selectedIf($field['type'], 'hidden') ?>><?php echo htmlencode(t('<input type="hidden">'));?></option>
          <?php if ($field['type'] == 'parentCategory'): ?>
          <option value="<?php echo $field['type'] ?>" <?php selectedIf(1,1) ?>><?php echo $field['type'] ?></option>
          <?php endif; ?>
          
          <?php
          $fieldTypeOptions = ['none', 'textfield', 'textbox', 'wysiwyg', 'date', 'list', 'checkbox', 'upload', 'hidden', 'separator', 'tabGroup', 'relatedRecords', 'parentCategory'];
          if (!in_array($field['type'], $fieldTypeOptions)):
          ?>
          <optgroup label="<?php eht('Previous Selection (not in list)'); ?>">
          <option value="<?php echo htmlencode($field['type']) ?>" selected="selected"><?php echo htmlencode($field['type']) ?></option>
          </optgroup>
          <?php endif; ?>
          
        </select>
      </div>
    </div>
    <?php if (@$field['customColumnType']): ?>
    <div class="form-group">
      <div class="col-xs-2" class="control-label"><?php echo t('Custom Column Type');?></div>
      <div class="col-xs-9">
        <?php echo htmlencode(@$field['customColumnType']); ?>
      </div>
    </div>
    <?php endif; ?>


    <?php echo adminUI_separator(t('Field Options')); ?>

    <div id="fieldOptionsContainer" class="container row">

      <div style="display: none" class="fieldOption noOptions" align="center">
        <?php echo t('There are no options for this field type.');?><br><br>
      </div>

      <div style="display: none" class="fieldOption defaultValue form-group">
        <div class="col-xs-2"><?php echo t('Default Value');?></div>
        <div class="col-xs-9">
          <input class="form-control" name="defaultValue" size="40" value="<?php echo htmlencode($field['defaultValue']) ?>">
          <?php if (@$_REQUEST['addField']): ?>
          <br>
          <input type="checkbox" name="defaultUpdateExisting" id="defaultUpdateExistingTextField" value="1"> Update all existing records with this value
          <?php endif; ?>
        </div>
      </div>

      <div style="display: none;" class="fieldOption defaultContent form-group">
        <div class="col-xs-2"><?php echo t('Default Value');?></div>
        <div class="col-xs-9">
          <textarea name="defaultContent" class="form-control textareaGrow" cols="60" rows="3" data-growsize="10"><?php echo htmlencode($field['defaultContent']) ?></textarea>
          <?php if (@$_REQUEST['addField']): ?>
          <br>
          <input type="checkbox" name="defaultUpdateExisting" id="defaultUpdateExistingTextArea" value="1"> Update all existing records with this value
          <?php endif; ?>
        </div>
      </div>

      <div style="display: none" class="fieldOption checkboxOptions form-group">
        <div class="col-xs-2"><?php echo t('Default State');?></div>
        <div class="col-xs-9">
          <input type="radio" id="checkedByDefault.1" name="checkedByDefault" value="1" <?php checkedIf($field['checkedByDefault'], '1') ?>> <label for="checkedByDefault.1"><?php echo t('Checked'); ?></label>
          <input type="radio" id="checkedByDefault.0" name="checkedByDefault" value="0" <?php checkedIf($field['checkedByDefault'], '0') ?>> <label for="checkedByDefault.0"><?php echo t('Unchecked'); ?></label>
          <?php if (@$_REQUEST['addField']): ?>
          <br>
          <input type="checkbox" name="defaultUpdateExisting" id="defaultUpdateExistingCheckbox" value="1"> Update all existing records with this value
          <?php endif; ?>
        </div>
      </div>

      <div style="display: none" class="fieldOption fieldPrefix clear form-group">
        <div class="col-xs-2"><?php echo t('Field Prefix'); ?></div>
        <div class="col-xs-9">
          <input class="form-control" name="fieldPrefix" size="40" value="<?php echo htmlencode($field['fieldPrefix']) ?>">
          <?php echo t('displayed before or above field');?>
        </div>
      </div>

      <div style="display: none" class="fieldOption description clear form-group">
        <div class="col-xs-2"><?php echo t('Field Description'); ?></div>
        <div class="col-xs-9">
          <input class="form-control" name="description" size="40" value="<?php echo htmlencode($field['description']) ?>">
          <?php echo t('displayed after or below field');?>
        </div>
      </div>

      <div style="display: none" class="fieldOption checkboxOptions form-group">
        <div class="col-xs-2"><?php echo t('Checked Value');?></div>
        <div class="col-xs-9"><input class="form-control" name="checkedValue" size="40" value="<?php echo htmlencode($field['checkedValue']) ?>"></div>
      </div>

      <div style="display: none" class="fieldOption checkboxOptions form-group">
        <div class="col-xs-2"><?php echo t('Unchecked Value');?></div>
        <div class="col-xs-9"><input class="form-control" name="uncheckedValue" size="40" value="<?php echo htmlencode($field['uncheckedValue']) ?>"></div>
      </div>


      <div style="display: none" class="fieldOption textboxHeight form-group">
        <div class="col-xs-2"><?php echo t('Field Height');?></div>
        <div class="col-xs-9"><input class="form-control" type="text" name="fieldHeight" value="<?php echo htmlencode($field['fieldHeight']) ?>" size="4"> <?php echo t('pixels (leave blank for default height)');?></div>
      </div>
      
      <div style="display: none" class="fieldOption fieldAddons form-group">
        <div class="col-xs-2"><?php echo t('Field Addons');?></div>
        <div class="col-xs-9">
          <div class="form-inline">
            <div class="form-group" style="margin: 0px 20px 0px 0px">
              <label><?php echo t('Before');?></label>
              <input type="text" class="form-control" name="fieldAddonBefore" value="<?php echo htmlencode($field['fieldAddonBefore']) ?>">
            </div>
            <div class="form-group">
              <label><?php echo t('After');?></label>
              <input type="text" class="form-control" name="fieldAddonAfter" value="<?php echo htmlencode($field['fieldAddonAfter']) ?>">
            </div>
          </div>
        </div>
      </div>

      <div style="display: none" class="fieldOption fieldWidth form-group">
        <div class="col-xs-2"><?php echo t('Field Width');?></div>
        <div class="col-xs-9">
          <div class="input-group">
            <select class="form-control" name="fieldWidth" onchange="fieldWidthChangeWidth(this);">
              <option value="">&lt;select width&gt;</option>
              <option data-class = "<?php echo getBootstrapFieldWidthClass("tiny") ?>"   value="tiny"   <?php selectedIf($field['fieldWidth'], 'tiny') ?>><?php echo t('Tiny'); ?></option>
              <option data-class = "<?php echo getBootstrapFieldWidthClass("small") ?>"  value="small"  <?php selectedIf($field['fieldWidth'], 'small') ?>><?php echo t('Small'); ?></option>
              <option data-class = "<?php echo getBootstrapFieldWidthClass("medium") ?>" value="medium" <?php selectedIf($field['fieldWidth'], 'medium') ?>><?php echo t('Medium'); ?></option>
              <option data-class = "<?php echo getBootstrapFieldWidthClass("large") ?>"  value="large"  <?php selectedIf($field['fieldWidth'], 'large') ?>><?php echo t('Large'); ?></option>
              <option data-class = "<?php echo getBootstrapFieldWidthClass("full") ?>"   value="full"   <?php selectedIf($field['fieldWidth'], 'full') ?>><?php echo t('Full Width'); ?></option>
            </select>
          </div>
        </div>
      </div>

      <div style="display: none" class="fieldOption allowUploads form-group">
        <div class="col-xs-2"><?php echo t('Allow Uploads');?></div>
        <div class="col-xs-9">
          <input type="hidden" name="allowUploads" value="0">
          <input type="checkbox"  id="allowUploads" name="allowUploads" value="1" <?php checkedIf($field['allowUploads'], '1') ?>>
          <label for="allowUploads"><?php echo t('Allow uploads for this field');?></label>
        </div>
      </div>

      <div style="display: none" class="fieldOption dateOptions">
        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Default Value');?></div>
          <div class="col-xs-9">
            <select class="form-control" name="defaultDate" id="defaultDate" onchange="updateDefaultDateFields()">
              <option value=''       <?php selectedIf($field['defaultDate'], ''); ?>><?php echo t('Current Date');?></option>
              <option value="none"   <?php selectedIf($field['defaultDate'], 'none'); ?>><?php echo t('None / Blank');?></option>
              <option value="custom" <?php selectedIf($field['defaultDate'], 'custom'); ?>><?php echo t('Specify custom date (or strtotime value) below:');?></option>
            </select><br>

            <span id="defaultDateStringAndLink" style="display: none">
              <input class="form-control" name="defaultDateString" id="defaultDateString"  size="40" value="<?php echo htmlencode($field['defaultDateString']) ?>" onkeyup="updateDefaultDateFields()">&nbsp;<a href="http://php.net/manual/en/function.strtotime.php#function.strtotime.examples" target="_blank">strtotime() examples &gt;&gt;</a><br>
            </span>

            <?php echo t('Preview:'); ?> <span id="defaultDatePreview"></span><br>

            <?php if (@$_REQUEST['addField']): ?>
            <br>
            <input type="checkbox" name="defaultUpdateExisting" id="defaultUpdateExistingDate" value="1"> Update all existing records with this value
            <?php endif; ?>
            
          </div>
        </div>

        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Specify Time');?></div>
          <div class="col-xs-9">
            <input type="hidden" name="showTime" value="0">
            <input type="checkbox"  id="date_showHourMinFields" name="showTime" value="1" <?php checkedIf($field['showTime'], '1') ?>>
            <label for="date_showHourMinFields"><?php echo t('user specifies time (hour, minutes, and optionally seconds)');?></label>
          </div>
        </div>

        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Specify Seconds');?></div>
          <div class="col-xs-9">
            <span class="indent">
            <input type="hidden" name="showSeconds" value="0">
            <input type="checkbox" class="inputSubfield"  id="date_showSecondsField" name="showSeconds" value="1" <?php checkedIf($field['showSeconds'], '1') ?>>
            <label for="date_showSecondsField"><?php echo t('user specifies seconds (requires &quot;Specify Time&quot; to be enabled)'); ?></label>
            </span>
          </div>
        </div>

        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Use 24 Hour Time');?></div>
          <div class="col-xs-9">
            <span class="indent">
            <input type="hidden" name="use24HourFormat" value="0">
            <input type="checkbox" class="inputSubfield"  id="date_use24HourFormat" name="use24HourFormat" value="1" <?php checkedIf($field['use24HourFormat'], '1') ?>>
            <label for="date_use24HourFormat"><?php echo t('use 24 hour time when specifying hours');?></label>
            </span>
          </div>
        </div>

        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Year Range');?></div>
          <div class="col-xs-9 form-inline">
            <input class="form-control" type="text" name="yearRangeStart" value="<?php echo htmlencode($field['yearRangeStart']); ?>" size="4" maxlength="4"> to
            <input class="form-control" type="text" name="yearRangeEnd" value="<?php echo htmlencode($field['yearRangeEnd']); ?>" size="4" maxlength="4">
            <?php echo t('Leave blank for 5 years before and after current year');?>
          </div>
        </div>

      </div>

      <div style="display: none" class="fieldOption listOptions">
        <div class="form-group">
          <div class="col-xs-2"><?php echo t('Display As');?></div>
          <div class="col-xs-10">
            
            <div class="radio">
              <label for="listType.pulldown">
              <input type="radio"  id="listType.pulldown" name="listType" value="pulldown" <?php checkedIf($field['listType'], 'pulldown') ?>>
              <?php echo t('pulldown');?></label><br>
              
              
              <label for="listType.radios">
              <input type="radio"   id="listType.radios" name="listType" value="radios" <?php checkedIf($field['listType'], 'radios') ?>>
              <?php echo t('radio buttons');?></label><br>
              
              
              <label for="listType.pulldownMulti">
              <input type="radio"  id="listType.pulldownMulti" name="listType" value="pulldownMulti" <?php checkedIf($field['listType'], 'pulldownMulti') ?>>
              <?php echo t('pillbox (multi value)');?></label><br>
              
              
              <label for="listType.checkboxes">
              <input type="radio"   id="listType.checkboxes" name="listType" value="checkboxes" <?php checkedIf($field['listType'], 'checkboxes') ?>>
              <?php echo t('checkboxes (multi value)');?></label><br>
            </div>

          </div>
        </div>

        <div class="form-group">
          <div class="col-xs-2"><?php echo t('List Options');?></div>
          <div class="col-xs-9">
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
    
                  <select class="form-control" name="optionsType" id="optionsType" onchange="displayListTypeOptions()">
                    <option value="text"  <?php selectedIf($field['optionsType'], 'text'); ?>><?php echo t('Use options listed below');?></option>
                    <option value="table" <?php selectedIf($field['optionsType'], 'table'); ?>><?php echo t('Get options from database (advanced)');?></option>
                    <option value="query" <?php selectedIf($field['optionsType'], 'query'); ?>><?php echo t('Get options from MySQL query (advanced)');?></option>
                  </select><br>
    
    
                  <div id="optionsTextDiv" style="display: none;">
                    <textarea name="optionsText" cols="60" rows="3" class="textareaGrow" data-growsize="10"><?php echo htmlencode($field['optionsText']) ?></textarea><br>
                    <?php echo t('<b>Tip:</b> To save and display different values enter them like this: CD|Compact Disc');?>
                  </div>
    
                  <table border="0" cellspacing="0" cellpadding="0" id="optionsTable" style="display: none;">
                    <tr>
                      <td><?php echo t('Section Tablename');?> &nbsp;</td>
                      <td>
                        <select class="form-control" name="optionsTablename" id="optionsTablename" onchange="updateListOptionsFieldnames( this.value )">
                        <option value=''>&lt;<?php echo t('select table');?>&gt;</option>
                        <?php
                          foreach ($tablesAndFieldnames as $optionTableName => $fields) {
                            $selectedAttr = selectedIf($optionTableName, $field['optionsTablename'], true);
                            print "<option value=\"$optionTableName\" $selectedAttr>$optionTableName</option>\n";
                          }
                        ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><?php echo t('Use this field for option values');?> &nbsp;</td>
                      <td>
                        <select class="form-control" name="optionsValueField" id="optionsValueField">
                        <option value=''>&lt;<?php echo t('select field');?>&gt;</option>
                        <?php echo getSelectOptions($field['optionsValueField'], $optionFieldnames); ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><?php echo t('Use this field for option labels');?> &nbsp;</td>
                      <td>
                        <select class="form-control" name="optionsLabelField" id="optionsLabelField">
                        <option value=''>&lt;<?php echo t('select field');?>&gt;</option>
                        <?php echo getSelectOptions($field['optionsLabelField'], $optionFieldnames); ?>
                        </select>
                      </td>
                    </tr>
                  </table>
    
                  <div id="optionsQueryDiv" style="display: none;">
                    <textarea name="optionsQuery" cols="60" rows="3" class="setAttr-spellcheck-false textareaGrow" data-growsize="10"><?php echo htmlencode($field['optionsQuery']) ?></textarea><br>
                    <?php echo t('<b>Tip: &nbsp;</b> Insert table prefix like this: &lt;?php echo $TABLE_PREFIX ?&gt;<br>'); ?>
                    <br>
    
    
                    <?php echo t('<b>Advanced Filter:</b> Refresh list when this field changes:'); ?>
    
                    <select class="form-control" name="filterField">
                    <option value=''>&lt;<?php echo t('select field'); ?>&gt;</option>
                    <?php echo getSelectOptions($field['filterField'], $thisTablesFieldnames); ?>
                    </select><br>
                    <?php echo t('<b>Advanced Filter:</b> Insert <i>escaped</i> filter field value like this: &lt;?php echo $ESCAPED_FILTER_VALUE ?&gt;<br>'); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>



      <div style="display: none" class="fieldOption uploadOptions">
      </div>

      <div style="display: none" class="fieldOption separatorOptions">
        <div class="col-xs-2"><?php echo t('Separator Type'); ?></div>
        <div class="col-xs-9">
          
          <label><input type="radio" id="separatorType.blankLine" name="separatorType" value="blank line" <?php checkedIf($field['separatorType'], 'blank line') ?>> <?php echo t('Blank Line'); ?></label><br>
          
          <label><input type="radio" id="separatorType.headerBar" name="separatorType" value="header bar" <?php checkedIf($field['separatorType'], 'header bar') ?>> <?php echo t('Header Bar'); ?></label> &nbsp;<br>
          <div class="col-xs-12">
            <input class="form-control" type="text" name="separatorHeader" value="<?php echo htmlencode($field['separatorHeader']) ?>" size="75" onfocus="$('#separatorType\\.headerBar').attr('checked', true);">
            <div class="checkbox">
              <label><input type="checkbox" value="1" name="isCollapsible" <?php checkedIf($field['isCollapsible'], '1') ?> onfocus="$('#separatorType\\.headerBar').attr('checked', true);">Collapsible</label>&nbsp;&nbsp;
              <label><input type="checkbox" value="1" name="isCollapsed"   <?php checkedIf($field['isCollapsed'], '1') ?> disabled>Closed By Default</label>
            </div>
          </div>
          <br>
          
          <label><input type="radio" id="separatorType.html" name="separatorType" value="html" <?php checkedIf($field['separatorType'], 'html') ?>> <?php echo t('HTML'); ?></label><br>
          <div class="col-xs-12">
            <textarea name="separatorHTML" cols="60" rows="3" onfocus="$('#separatorType\\.html').attr('checked', true);" data-growsize="12" class="form-control setAttr-wrap-off textareaGrow"><?php echo htmlencode($field['separatorHTML']) ?></textarea><br><br>
          </div>
        </div>
      </div>

      <div style="display: none" class="fieldOption relatedRecordsOptions">

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('Related Table'); ?></label>
          <div class="col-xs-9">
            <select class="form-control" name="relatedTable">
            <option value=''>&lt;<?php echo t('select table'); ?>&gt;</option>
            <?php
              foreach ($tablesAndFieldnames as $optionTableName => $fields) {
                $selectedAttr = selectedIf($optionTableName, $field['relatedTable'], true);
                print "<option value=\"$optionTableName\" $selectedAttr>$optionTableName</option>\n";
              }
            ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('Max Records'); ?></label>
          <div class="col-xs-9">
            <?php echo t('Show the first '); ?>
            <input class="text-input" type="text" name="relatedLimit" value="<?php echo htmlencode($field['relatedLimit']) ?>" size="3">
            <?php echo t('records only (leave blank for all)'); ?>
          </div>
        </div>

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('List Actions'); ?></label>
          <div class="col-xs-9">
            <label><input type="checkbox" name="relatedView"   value="1" <?php checkedIf($field['relatedView'], '1') ?>> <?php echo t('View'); ?></label>&nbsp;
            <label><input type="checkbox" name="relatedModify" value="1" <?php checkedIf($field['relatedModify'], '1') ?>> <?php echo t('Modify'); ?></label>&nbsp;
            <label><input type="checkbox" name="relatedErase"  value="1" <?php checkedIf($field['relatedErase'], '1') ?>> <?php echo t('Erase'); ?></label>&nbsp;
            <label><input type="checkbox" name="relatedCreate"  value="1" <?php checkedIf($field['relatedCreate'], '1') ?>> <?php echo t('Create'); ?></label>
          </div>
        </div>

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('MySQL Where'); ?></label>
          <div class="col-xs-9">
            <textarea name="relatedWhere" cols="60" rows="3" class="setAttr-spellcheck-false textareaGrow" data-growsize="10"><?php echo htmlencode($field['relatedWhere']) ?></textarea>
          </div>
        </div>

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('More "Search" Link'); ?></label>
          <div class="col-xs-9">
            <input class="form-control text-input inputSubfield" type="text" name="relatedMoreLink" value="<?php echo htmlencode($field['relatedMoreLink']) ?>">
            <p class="help-block">
              <?php echo t('Enter a standard url search in this field, example: fieldA_match=value1&amp;fieldB_keyword=value2<br>
              <b>Note:</b> Field search suffixes are required.  Use field_match=, not field=.'); ?>
            </p>
          </div>
        </div>

        <div class="form-group">
          <label class="col-xs-2" class="control-label"><?php echo t('PHP Reference'); ?></label>
          <div class="col-xs-9">
            <p class="help-block">
              <?php echo sprintf('<b>Tip:</b> You can use &lt;?php ?&gt; code in both of the above fields.  Available variables:'); ?>
            </p>
            <table border="0" cellspacing="1" cellpadding="1">
              <tr><td>$TABLE_PREFIX &nbsp;</td><td><?php echo t('Database table prefix '); ?>(<?php echo $TABLE_PREFIX ?>)</td></tr>
              <tr><td>$RECORD &nbsp;</td><td><?php echo t('Associative array of record being edited'); ?></td></tr>
            </table>
          </div>
        </div>

      </div>
    
    </div>
    

    <?php echo adminUI_separator(t('Input Validation')); ?>

    <div id="validationRulesContainer" class="row container">

      <div style="display: none" class="fieldOption noValidationRules" align="center">
        <?php echo t('There are no validation rules for this field type.'); ?><br><br>
      </div>

      <div style="display: none" class="fieldOption requiredValue clear form-group">
        <div class="col-xs-2"><?php echo t('Required'); ?></div>
        <div class="col-xs-9">
          <input type="hidden" name="isRequired" value="0">
          <input type="checkbox"  id="valueIsRequired" name="isRequired" value="1" <?php checkedIf($field['isRequired'], '1') ?>>
          <label for="valueIsRequired"><?php echo t('user may not leave field blank'); ?></label>
        </div>
      </div>

      <div class="clear"></div>

      <div style="display: none" class="fieldOption uniqueValue clear form-group">
        <div class="col-xs-2"><?php echo t('Unique'); ?></div>
        <div class="col-xs-9">
          <input type="hidden" name="isUnique" value="0">
          <input type="checkbox"  id="valueMustBeUnique" name="isUnique" value="1" <?php checkedIf($field['isUnique'], '1') ?>>
          <label for="valueMustBeUnique"><?php echo t('user may not enter the same value as another record (not case-sensitive)'); ?></label>
        </div>
      </div>

      <div style="display: none" class="fieldOption minMaxLength form-group">
        <div class="col-xs-2"><?php echo t('Min Length'); ?></div>
        <div class="col-xs-9 form-inline">
          <input class="form-control" name="minLength" size="4" value="<?php echo htmlencode(@$field['minLength']) ?>"> <?php echo t('characters'); ?>
        </div>
        <div class="col-xs-2"><?php echo t('Max Length'); ?></div>
        <div class="col-xs-9 form-inline">
          <input class="form-control" name="maxLength" size="4" value="<?php echo htmlencode(@$field['maxLength']) ?>"> <?php echo t('characters'); ?>
        </div>
      </div>

      <div style="display: none" class="fieldOption validationRule form-group">
        <div class="col-xs-2"><?php echo t('Allowed Content'); ?></div>
        <div class="col-xs-9">
          <select name="charsetRule" class="form-control">
            <option value=''><?php eht('Allow all characters'); ?></option>
            <option value="allow"    <?php selectedIf($field['charsetRule'], 'allow') ?>><?php echo t('Only allow characters:'); ?></option>
            <option value="disallow" <?php selectedIf($field['charsetRule'], 'disallow') ?>><?php echo t('Disallow characters:'); ?></option>
          </select><br>
          <input class="form-control" type="text" name="charset" value="<?php echo htmlencode(@$field['charset']) ?>" size="60">
        </div>
      </div>

      <div style="display: none" class="fieldOption uploadValidationFields form-group">
        <div class="form-inline">
          <div class="col-xs-2"><?php echo t('Upload Settings'); ?></div>
          <div class="col-xs-9">
            <table border="0" cellspacing="0" cellpadding="0" class="table-condensed">
              <tr>
                <td><?php echo t('File extensions allowed: '); ?>&nbsp;</td>
                <td><input class="form-control" type="text" name="allowedExtensions" value="<?php echo htmlencode($field['allowedExtensions']) ?>" size="35"><br></td>
              </tr>
              <tr>
                <td>
                  <input type="hidden"   name="checkMaxUploads" value="0">
                  <input type="checkbox" name="checkMaxUploads" id="checkMaxUploads" value="1"  <?php checkedIf(@$field['checkMaxUploads'], '1'); ?>>
                  <label for="checkMaxUploads"><?php echo t('Maximum uploads:'); ?></label> &nbsp;
                </td>
                <td>
                  <input class="form-control" type="text" name="maxUploads" value="<?php echo htmlencode($field['maxUploads']) ?>" size="5">
                  <?php echo t('files (uncheck for unlimited, set to 0 for none)'); ?>
                </td>
              </tr>
              <tr>
                <td>
                  <input type="hidden"   name="checkMaxUploadSize" value="0">
                  <input type="checkbox" name="checkMaxUploadSize" id="checkMaxUploadSize" value="1"  <?php checkedIf(@$field['checkMaxUploadSize'], '1'); ?>>
                  <label for="checkMaxUploadSize"><?php echo t('Maximum upload size:'); ?></label> &nbsp;
                </td>
                <td>
                  <input class="form-control" type="text" name="maxUploadSizeKB" value="<?php echo htmlencode($field['maxUploadSizeKB']) ?>" size="5">
                  <?php echo t('Kbytes (uncheck for unlimited)'); ?>
                </td>
              </tr>
              <tr>
                <td>
                  <input type="hidden"   name="resizeOversizedImages" value="0">
                  <input type="checkbox" name="resizeOversizedImages" id="resizeOversizedImages" value="1"  <?php checkedIf(@$field['resizeOversizedImages'], '1'); ?>>
                  <label for="resizeOversizedImages"><?php echo t('Resize images larger than:'); ?></label> &nbsp;
                </td>
                <td>
                  <?php echo t('width'); ?> <input class="form-control" type="text" size="4" name="maxImageWidth" value="<?php echo htmlencode($field['maxImageWidth']) ?>">
                  &nbsp;
                  <?php echo t('height'); ?> <input class="form-control" type="text" size="4" name="maxImageHeight" value="<?php echo htmlencode($field['maxImageHeight']) ?>">
                </td>
              </tr>

              <?php foreach(array('',2,3,4) as $num): ?>
              <tr>
                <td>
                  <input type="hidden" name="createThumbnails<?php echo $num ?>" value="0">
                  <label for="createThumbnails<?php echo $num ?>">
                    <input type="checkbox" name="createThumbnails<?php echo $num ?>" id="createThumbnails<?php echo $num ?>" value="1"  <?php checkedIf(@$field["createThumbnails$num"], '1'); ?>>
                    <?php echo t('Create thumbnail'); ?> <?php if ($num) { echo "($num)"; }?> :
                  </label>
                </td>
                <td>
                  <?php echo t('width'); ?> <input class="form-control" type="text" size="4" name="maxThumbnailWidth<?php echo $num ?>"  id="maxThumbnailWidth<?php echo $num ?>"  value="<?php echo htmlencode($field["maxThumbnailWidth$num"]) ?>">
                  &nbsp;
                  <?php echo t('height'); ?> <input class="form-control" type="text" size="4" name="maxThumbnailHeight<?php echo $num ?>" id="maxThumbnailHeight<?php echo $num ?>" value="<?php echo htmlencode($field["maxThumbnailHeight$num"]) ?>">
                  &nbsp;
                  <input type="hidden" name="cropThumbnails<?php echo $num ?>" value="0">
                  <label for="cropThumbnails<?php echo $num ?>">
                    <input type="checkbox" name="cropThumbnails<?php echo $num ?>" id="cropThumbnails<?php echo $num ?>" value="1"  <?php checkedIf(@$field["cropThumbnails$num"], '1'); ?>>
                    <a title="Crop to fill height and width"><i><?php echo t('crop'); ?></i></a>
                  </label>
                  &nbsp;|&nbsp;
                  <a href="#" onclick="recreateThumbnails('<?php echo $num ?>'); return false;" id="recreateThumbnailsLink<?php echo $num ?>"><?php echo t('recreate'); ?></a>
                  <span id="recreateThumbnailsStatus<?php echo $num ?>"></span>
                  
                </td>
              </tr>
              <tr><td colspan="2" id="recreateThumbnailsErrors<?php echo $num ?>"></td></tr>

              <?php endforeach ?>
              <tr>
                <td colspan="2">
                  <i><?php echo t('Resized images are proportionally scaled down versions of the original images'); ?>
                  <br><?php echo t('If cropped, the images are resized to fill up the selected width and height'); ?></i>
                  <input type="checkbox" name="null" value='' style="visibility: hidden;">
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>
    </div>


    <?php echo adminUI_separator(t('Advanced Options')); ?>

    <div id="advancedOptionsContainer" class="container row">
      
      <div style="display: none" class="fieldOption noAdvancedOptions" align="center">
        <?php echo t('There are no advanced options for this field type.');?><br><br>
      </div>
      
      <!-- Field Attributes -->

      <div style="display: none" class="fieldOption adminOnly form-group">
        <div class="col-xs-2"><?php echo t('Field Attributes'); ?></div>
        <div class="col-xs-9 form-inline">
          <?php echo t('Access Level'); ?>
          <select class="form-control" name="adminOnly" id="adminOnly">
            <option value='0' <?php selectedIf($field['adminOnly'], '0'); ?>> <?php echo t('Everyone'); ?> </option>
            <option value='1' <?php selectedIf($field['adminOnly'], '1'); ?>> <?php echo t('Editor Only'); ?> </option>
            <option value='2' <?php selectedIf($field['adminOnly'], '2'); ?>> <?php echo t('Admin Only'); ?> </option>
          </select>
          - 
          <?php echo t('choose if field has edit access restrictions.'); ?>
        </div>
      </div>

      
      <div style="display: none" class="fieldOption isSystemField form-group">
        <div class="col-xs-2">&nbsp;</div>
        <div class="col-xs-9">
          <input type="hidden" name="isSystemField" value="0">
          <input type="checkbox" name="isSystemField" value="1" id="isSystemField" <?php checkedIf(@$field['isSystemField'], '1') ?>>
          <label for="isSystemField"><?php echo t('System Field - restrict field editor access to this field'); ?></label>
        </div>
      </div>

      <div style="display: none" class="fieldOption isPasswordField form-group">
        <div class="col-xs-2">&nbsp;</div>
        <div class="col-xs-9">
          <input type="hidden" name="isPasswordField" value="0">
          <input type="checkbox"  id="textfield_isPasswordField" name="isPasswordField" value="1" <?php checkedIf($field['isPasswordField'], '1') ?>>
          <label for="textfield_isPasswordField"><?php eht("Password Field - hide text that users enter (show values as *****)"); ?></label>
        </div>
      </div>
      
      <div style="display: none" class="fieldOption disableAutoFormat clear form-group">
        <div class="col-xs-2">&nbsp;</div>
        <div class="col-xs-9">
          <input type="hidden" name="autoFormat" value="1">
          <input type="checkbox"  id="textbox_autoFormat" name="autoFormat" value="0" <?php checkedIf($field['autoFormat'], '0') ?>>
          <label for="textbox_autoFormat"><?php echo t('Disable auto-formatting (don\'t add break tags to content)'); ?></label>
        </div>
      </div>

      <div style="display: none" class="fieldOption myAccountField clear form-group">
        <div class="col-xs-2">&nbsp;</div>
        <div class="col-xs-9">
          <input type="hidden" name="myAccountField" value="0">
          <input type="checkbox" name="myAccountField" id="myAccountField" value="1" <?php checkedIf($field['myAccountField'], '1') ?>>
          <label for="myAccountField"><?php echo t('My Account - Show this field in "My Account" section'); ?></label>
        </div>
      </div>

      <!-- advanced upload options -->
      <div style="display: none" class="fieldOption advancedUploadFields clear form-group">
        <div class="col-xs-2"><?php echo t('Upload Fields'); ?></div>
        <div class="col-xs-9">
          <table class="table">
            <tr>
              <td><?php echo t('These extra fields will be displayed on the upload form and available in viewers.'); ?></td>
            </tr>
            <tr>
              <td><?php echo t('info1'); ?> &nbsp; <input class="form-control" type="text" name="infoField1" value="<?php echo htmlencode(@$field['infoField1']) ?>" size="20"></td>
            </tr>
            <tr>
              <td><?php echo t('info2'); ?> &nbsp; <input class="form-control" type="text" name="infoField2" value="<?php echo htmlencode(@$field['infoField2']) ?>" size="20"></td>
            </tr>
            <tr>
              <td><?php echo t('info3'); ?> &nbsp; <input class="form-control" type="text" name="infoField3" value="<?php echo htmlencode(@$field['infoField3']) ?>" size="20"></td>
            </tr>
            <tr>
              <td><?php echo t('info4'); ?> &nbsp; <input class="form-control" type="text" name="infoField4" value="<?php echo htmlencode(@$field['infoField4']) ?>" size="20"></td>
            </tr>
            <tr>
              <td><?php echo t('info5'); ?> &nbsp; <input class="form-control" type="text" name="infoField5" value="<?php echo htmlencode(@$field['infoField5']) ?>" size="20"></td>
            </tr>
          </table>
        </div>
      </div>

      <div style="display: none" class="fieldOption advancedUploadDir form-group">
        <div class="col-xs-2"><?php echo t('Upload Directory'); ?></div>
        <div class="col-xs-9">
          <div style="width: 100%;">
            <div>
                <input type="hidden"   name="useCustomUploadDir" value="0">
                <input type="checkbox" name="useCustomUploadDir" id="useCustomUploadDir" value="1"  <?php checkedIf(@$field['useCustomUploadDir'], '1'); ?>
                       onclick="if (this.checked) { $('.customUploadRow').show(); } else { $('.customUploadRow').hide(); }">
                <label for="useCustomUploadDir"><?php et('Use custom upload directory') ?></label>
            </div>

            <?php $customUploadRowDisplay = (@$field['useCustomUploadDir']) ? "inherit" : "none"; ?>
            <div class="customUploadRow" style="display: <?php echo $customUploadRowDisplay ?>">

              <div>&nbsp;</div>

              <div>
                <div class="col-sm-2"><?php echo t('Directory Path'); ?>&nbsp;</div>
                <div class="col-sm-10">
                  <input class="form-control" type="text" name="customUploadDir" id="customUploadDir" value="<?php echo htmlencode(@$field['customUploadDir']) ?>" size="50" onkeyup="updateUploadPathPreviews('dir', this.value, 1)" onchange="updateUploadPathPreviews('dir', this.value, 1)">
                  
                  <small>
                    <?php et('Preview:'); ?> <span id="uploadDirPreview" style="word-break: break-all"><?php echo htmlencode(getUploadPathPreview('dir', @$field['customUploadDir'], true, false)); ?></span><br>
                    <?php et('Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload Dir</a> in General Settings)'); ?><br>
                  </small>
                </div>
              </div>

              <div>&nbsp;</div>

              <div>
                <div class="col-sm-2">Folder Url</div>
                <div class="col-sm-10">
                  <input class="form-control" type="text" name="customUploadUrl" id="customUploadUrl" value="<?php echo htmlencode(@$field['customUploadUrl']) ?>" size="50" onkeyup="updateUploadPathPreviews('url', this.value, 1)" onchange="updateUploadPathPreviews('url', this.value, 1)"><br>
                  <small>
                    <?php et('Preview:'); ?> <span id="uploadUrlPreview" style="word-break: break-all"><?php echo htmlencode(getUploadPathPreview('url', @$field['customUploadUrl'], true, false)); ?></span><br>
                    <?php et('Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload URL</a> in General Settings)'); ?><br>
                    
                  </small>
                  
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <div style="display: none" class="fieldOption customColumnType clear form-group">
        <div class="col-xs-2"><?php echo t('MySQL Column Type'); ?></div>
        <div class="col-xs-9">
          <?php
            $columnTypesAuto = array(
              ''                   => t("Auto"),
            );
            $columnTypesNumeric = array(        
              'INT'                => t("INT (max: +/- 2 billion, doesn't support decimals)"),
              'BIGINT'             => t("BIGINT (max: +/- 9 billion billion, doesn't support decimals)"),
              'DECIMAL(14,2)'      => t("DECIMAL(14,2) (max: +/- 999 billion, supports 2 decimal places)"),
            );
            $columnTypesString = array(        
              'VARCHAR(255)'       => t("VARCHAR(255) (max: 255 chars)"),
              'MEDIUMTEXT'         => t("MEDIUMTEXT (max: 16 megs)"),
            );
            $columnTypesBinary = array(        
              'MEDIUMBLOB'         => t("MEDIUMBLOB (max: 16 megs)"),
            );
            $columnTypesCustom = array(        
              '_customColumnType_' => t("Other/Custom (enter MySQL column type below)"),
            );
            $columnTypes = $columnTypesAuto + $columnTypesNumeric + $columnTypesString + $columnTypesBinary + $columnTypesCustom;
            $selectedOption = $field['customColumnType'];
            $textfieldValue = '';
            if (!@$columnTypes[$selectedOption]) {
              $selectedOption = '_customColumnType_';
              $textfieldValue = $field['customColumnType'];
            }
            
            function showCustomColumnTypeOption() {
            }
            
          ?>
          <select class="form-control" name="customColumnType-select" onchange="$('.customColumnRow').toggle( $(this).val() === '_customColumnType_' );">

            <?php echo getSelectOptions($selectedOption, array_keys($columnTypesAuto), array_values($columnTypesAuto)) ?>
            
            <optgroup label="Numeric Types">
              <?php echo getSelectOptions($selectedOption, array_keys($columnTypesNumeric), array_values($columnTypesNumeric)) ?>        
            </optgroup>

            <optgroup label="Text/String Types">
              <?php echo getSelectOptions($selectedOption, array_keys($columnTypesString), array_values($columnTypesString)) ?>        
            </optgroup>

            <optgroup label="Binary Types">
              <?php echo getSelectOptions($selectedOption, array_keys($columnTypesBinary), array_values($columnTypesBinary)) ?>        
            </optgroup>
            
            <optgroup label="Custom Types">
              <?php echo getSelectOptions($selectedOption, array_keys($columnTypesCustom), array_values($columnTypesCustom)) ?>        
            </optgroup>
            
          </select>
          <div class="customColumnRow" <?php echo $textfieldValue ? '' : 'style="display: none;"' ?>>
            <input class="form-control" name="customColumnType" size="60" value="<?php echo htmlencode($textfieldValue) ?>">
            <a href="http://dev.mysql.com/doc/refman/5.0/en/data-type-overview.html" target="_blank">MySQL Data Types &gt;&gt;</a>
          </div>
        </div>
      </div>

      <div style="display: none" class="fieldOption indexed clear form-group">
        <div class="col-xs-2"><?php echo t('MySQL Indexing'); ?></div>
        <div class="col-xs-9">
          <input type="hidden" name="indexed" value="0">
          <label>
            <input type="checkbox" name="indexed" value="1" <?php checkedIf(@$field['indexed'], '1') ?>>
            <?php
              $format = t('Create %scolumn index%s - speeds up sorting and some searches but slows down adding and saving records.');
              echo sprintf($format, '<a href="http://dev.mysql.com/doc/refman/5.0/en/column-indexes.html" target="_blank">', '</a>');
            ?>
          </label>
        </div>
      </div>
      <div style="display: none" class="fieldOption fieldPrefix clear form-group isEncrypted ">
        <?php
          $isGeneralQueryLogEnabled = mysql_get_query("SHOW VARIABLES WHERE Variable_name = 'general_log'")['Value'] == 'ON';
          $checkboxDisabled         = !_mysql_encryption_key() || (empty($field['isEncrypted']) && $isGeneralQueryLogEnabled);
          $checkboxDisabledAttr     = $checkboxDisabled ? "disabled='disabled'" : "";
        ?>
        <div class="col-xs-2">Data Encryption</div>
        <div class="col-xs-9">
          <?php if ($isGeneralQueryLogEnabled || !_mysql_encryption_key()): // We do this so AES_ENCRYPT passwords don't get logged  ?> 
            <div class='text-danger'>

              <?php if ($isGeneralQueryLogEnabled): // We do this so AES_ENCRYPT passwords don't get logged  ?> 
                Note: To use this feature first disable <a href='?menu=admin&action=general#server-info' class='text-danger'><u>MySQL General Query Log</u></a> so passwords don't get logged!<br>
              <?php endif; ?>
              
              <?php if (!_mysql_encryption_key()): ?>
                Note: To use this feature first set an <a href="?menu=admin&action=general#database-encryption">database encryption key</a>!
              <?php endif ?>
            </div>
            <?php endif ?>
          <input type="hidden" name="isEncrypted" value="0">
          <input type="checkbox" name="isEncrypted" value="1" id="isEncrypted" <?php checkedIf(@$field['isEncrypted'], '1') ?> <?php echo $checkboxDisabledAttr ?>>
          <label for="isEncrypted"><?php echo t('Automatically encrypt data stored in database'); ?></label>
          <div>
            <input type='checkbox' name='_forSpacing_' style='visibility: hidden'>
            Tip: <a href="?menu=admin&action=backuprestore">Backup database</a> before encrypting so you have an unencrypted copy of your data.<br>
            <input type='checkbox' name='_forSpacing_' style='visibility: hidden'>
            Tip: Backup <a href="?menu=admin&action=general#database-encryption">encryption key</a> without it your data will be unrecoverable.<br>
          </div>

        </div>
      </div>      
      
      

    </div>
  </div>
  <?php
}

// compose and output the page
adminUI($adminUI);


<?php
/*
  Plugin Name: List Page Generator
  Description: Adds "List Page" to Code Generator
  Version: 1.00
  Requires at least: 2.61
 */

// Note: This library is automatically included by /lib/menus/_codeGenerator/actionHandler.php
// ... but can be duplicated and added to the /plugins/ folder to create a new code generator.
// ... Just be sure to change the function names or you'll get errors about duplicate functions.
// register generator
addGenerator('cg3_categorypage', 'Category Menu', 'Show a hierarchy of records from a category section.', cg_typeByVersion(3));

// dispatch function
function cg3_categorypage($function, $name, $description, $type) {
  
  // security/error checking
  if (@$_REQUEST['tableName'] && !loadSchema($_REQUEST['tableName'])) { die("Invalid tablename!");  }

  // call ajax code
  cg3_categorypage_ajaxPhpCode();

  // show options menu, and errors on submit
  cg3_categorypage_getOptions($function, $name, $description, $type);

  // show code
  $instructions = []; // show as bullet points
  $filenameSuffix = 'list'; // eg: tablename-FILENAMESUFFIX.php
  $code = cg3_categorypage_getCode();
  cg2_showCode($function, $name, $instructions, $filenameSuffix, $code);
  exit;
}

// user specified options
function cg3_categorypage_getOptions($function, $name, $description, $type) {

  // error checking
  if (@$_REQUEST['_showCode']) {
    $errorsAndAlerts = '';
    if (!@$_REQUEST['tableName']) { alert("Please select a section!<br>\n"); }
    if (!alert())                 { return; } // if form submitted and no errors than return and generate code
  }

  // set form defaults (for future use)
  $defaults                    = [];
  $defaults['defaultCategory'] = 'first';
  foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $_REQUEST)) {
      $_REQUEST[$key] = $value;
    }
  }
  
  // prepare adminUI() placeholders
  $adminUI = [];
  
  // form tag and hidden fields
  $adminUI['HIDDEN_FIELDS'] = [ [ 'name' => '_showCode', 'value' => '1' ] ];

  $adminUI['PRE_FORM_HTML'] = ob_capture('cg3_categorypage_ajaxJsCode'); // show ajax js code
  
  // main content
  $adminUI['CONTENT'] = ob_capture(function() { ?>

    <div class="form-horizontal">
    
      <?php echo adminUI_separator(t('Viewer Options')); ?>

      <?php cg2_option_selectSection(array('category')); ?>

      <div class="form-group">
        <label class="col-sm-2 control-label" for="categoryFormat"><?php et('Category Format')?></label>
        <div class="col-sm-10">
          <div class="form-inline">
            <?php
            $valuesToLabels = array(
              'showall'    => t('Show All'),
              'onelevel'   => t('One Level'),
              'twolevel'   => t('Two Level'),
              'breadcrumb' => t('Breadcrumb'),
            );
            $optionsHTML = getSelectOptions(@$_REQUEST['categoryFormat'], array_keys($valuesToLabels), array_values($valuesToLabels));
            ?>
            <select name="categoryFormat" id="categoryFormat" class="form-control">
              <?php echo $optionsHTML; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label" for="outputHtmlStyle"><?php et('Output HTML Style')?></label>
        <div class="col-sm-10">
          <div class="form-inline">
            <?php
            $valuesToLabels = array(
              'list'   => t('Unordered List (UL, LI tags)'),
              'indent' => t('Indented Text'),
            );
            $optionsHTML = getSelectOptions(@$_REQUEST['outputHtmlStyle'], array_keys($valuesToLabels), array_values($valuesToLabels));
            ?>
            <select name="outputHtmlStyle" id="outputHtmlStyle" class="form-control">
              <?php echo $optionsHTML; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-2 control-label"><?php et('Default Category')?></label>
        <div class="col-sm-10">
          <div class="radio">
            <label>
              <?php echo cg2_inputRadio('defaultCategory', ''); ?>
              <?php et("None - No category will be selected")?>
            </label>
          </div>
          <div class="radio">
            <label>
              <?php echo cg2_inputRadio('defaultCategory', 'first'); ?>
              <?php et('First - The first category will be selected')?>
            </label>
          </div>
            <div class="radio">
              <label>
                <?php echo cg2_inputRadio('defaultCategory', 'num'); ?>
                <?php et('Category - The following category will be selected'); ?>&nbsp;
              </label>
              <select name="defaultCategoryNum" style="height: 20px; vertical-align: middle">
                <?php echo cg3__getCategoryNames_asSelectOptions('defaultCategoryNum'); ?>
              </select>
            </div>
        </div>
      </div>

      <?php echo adminUI_separator(t('Advanced Options')); ?>

      <div class="form-group">
        <label class="col-sm-2 control-label" for="rootCategoryNum"><?php et('Category Root') ?></label>
        <div class="col-sm-10">
          <div class="form-inline">
            <select name="rootCategoryNum" id="rootCategoryNum" class="form-control">
              <?php echo cg3__getCategoryNames_asSelectOptions('rootCategoryNum'); ?>
            </select>
            <?php et("For menu branches - only categories below this one will be displayed"); ?>
          </div>
        </div>
      </div>

      <div class="center">
        <?php echo adminUI_button(['label' => t('Show Code') ]); ?>
      </div>

    </div>

  <?php });
  
  // compose and output the page
  cg_adminUI($adminUI, $name, $function);
  exit;
}

//
function cg3_categorypage_ajaxJsCode() {
  $ajaxUrl = "?menu=" . @$_REQUEST['menu'] . "&_generator=" . @$_REQUEST['_generator'] . "&_ajax=schemaFields";
  ?><script type="text/javascript">

    $(document).ready(function() {

      // register change event
      $(document).on('change', 'select[name=tableName]', function() {
        cg2_updateSchemaFieldPulldowns('defaultCategoryNum');
        cg2_updateSchemaFieldPulldowns('rootCategoryNum');
      });
    });

    //
    function cg2_updateSchemaFieldPulldowns(fieldname) {
      var tableName = $('select[name=tableName]').val(); // get tableName
      var jSelector = 'select[name='+fieldname+']';

      // show loading... for all pulldowns
      $(jSelector).html("<option value=''><?php et('loading...'); ?></option>");
      
      // load schema fields
      var ajaxUrl = '<?php echo $ajaxUrl ?>&tableName=' + tableName + '&fieldname=' + fieldname;
      $.ajax({
        url: ajaxUrl,
        cache: false,
        dataType: 'html',
        error: function(XMLHttpRequest, textStatus, errorThrown) {
          alert("There was an error sending the request! (" + XMLHttpRequest['status'] + " " + XMLHttpRequest['statusText'] + ")\n" + errorThrown);
        },
        success: function(optionsHTML) {
          console.log(fieldname);
          if (optionsHTML != '' && !optionsHTML.match(/^<option/)) { return alert("Error loading field list!\n" + optionsHTML); }
          $(jSelector).html(optionsHTML);
        }
      });
    }

  </script>
  <?php
}

//
function cg3_categorypage_ajaxPhpCode() {
  if (@$_REQUEST['_ajax'] == 'schemaFields') {
    $htmlOptions = cg3__getCategoryNames_asSelectOptions( $_REQUEST['fieldname'] );
    print $htmlOptions;
    exit;
  }
}


//
function cg3_categorypage_getCode() {
  $tableName = @$_REQUEST['tableName'];
  $schema    = loadSchema($tableName);
  $menuName  = @$schema['menuName'] ?: $tableName;

  // define variable names
  $categoryRecordsVar  = '$' . preg_replace("/[^\w]/", '_', $tableName) . "Records";
  $selectedCategoryVar = '$selected' . ucfirst( preg_replace("/[^\w]/", '_', $tableName) );
  $categoryRecordVar   = '$categoryRecord';

  ### generate code
  ob_start();
?><#php header('Content-type: text/html; charset=utf-8'); #>
<#php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */
<?php cg3_code_loadLibraries(); ?>

  // load records from '<?php echo $tableName ?>'
  list(<?php echo $categoryRecordsVar ?>, <?php echo $selectedCategoryVar ?>) = getCategories(array(
    'tableName'            => '<?php echo $tableName ?>', //
    'categoryFormat'       => '<?php echo $_REQUEST['categoryFormat'] ?>',  // showall, onelevel, twolevel, breadcrumb
    'defaultCategory'      => '<?php echo ($_REQUEST['defaultCategory'] == 'num') ? $_REQUEST['defaultCategoryNum'] : $_REQUEST['defaultCategory']; ?>',    // Enter 'first', a category number, or leave blank '' for none
    
    // advanced options (you can safely ignore these)
    'rootCategoryNum'      => '<?php echo $_REQUEST['rootCategoryNum'] ?>',      // Only categories _below_ this one will be shown (defaults to blank or 0 for all)
    'ulAttributes'         => '',      // add html attributes to <ul> tags, eg: 'class="menuUL"' would output <ul class="menuUL">
    'selectedCategoryNum'  => '',      // this record number is returned as the "selected category", defaults to getLastNumberInUrl()
    'ulAttributesCallback' => '',      // ADVANCED: custom function to return ul attributes, eg: 'myUlAttr' and function myUlAttr($category) { return "id='ul_uniqueId_{$category['num']}'"; }
    'liAttributesCallback' => '',      // ADVANCED: custom function to return li attributes, eg: 'myLiAttr' and function myLiAttr($category) { return "id='li_uniqueId_{$category['num']}'"; }
    'loadCreatedBy'        => false,   // loads createdBy.* fields for user who created category record (false is faster)
    'loadUploads'          => true,    // loads upload fields, eg: $category['photos'] gets defined with array of uploads (false is faster)
    'ignoreHidden'         => false,   // false = hide records with 'hidden' flag set, true = ignore status of hidden flag when loading records
    'debugSql'             => false,   // display the MySQL query being used to load records (for debugging)
  ));

#><?php cg2_code_header(); ?>
<?php cg2_code_instructions('Category'); ?>

<table border="1" cellspacing="0" cellpadding="2" width="100%">
  <tr>
    <td valign="top" width="200">

    <?php if (@$_REQUEST['outputHtmlStyle'] == 'list') : ?>
    
      <h3>Category Menu</h3>
      <ul>
        <#php foreach (<?php echo $categoryRecordsVar ?> as <?php echo $categoryRecordVar ?>): #>
          <#php echo <?php echo $categoryRecordVar ?>['_listItemStart'] #>
      
          <#php if (<?php echo $categoryRecordVar ?>['_isSelected']): #>
            <b><a href="<#php echo <?php echo $categoryRecordVar ?>['_link'] #>"><#php echo <?php echo $categoryRecordVar ?>['name'] #></a></b>
          <#php else: #>
            <a href="<#php echo <?php echo $categoryRecordVar ?>['_link'] #>"><#php echo <?php echo $categoryRecordVar ?>['name'] #></a>
          <#php endif; #>
      
          <#php echo <?php echo $categoryRecordVar ?>['_listItemEnd'] #>
        <#php endforeach; #>
      </ul>
    <?php else: ?>
    
      <h3>Category Menu</h3>
      <#php foreach (<?php echo $categoryRecordsVar ?> as <?php echo $categoryRecordVar ?>): #>
        <#php echo str_repeat("&nbsp; &nbsp; &nbsp;", <?php echo $categoryRecordVar ?>['depth']); #>
      
        <#php if (<?php echo $categoryRecordVar ?>['_isSelected']): #><b><#php endif; #>
        <a href="<#php echo <?php echo $categoryRecordVar ?>['_link'] #>"><#php echo <?php echo $categoryRecordVar ?>['name'] #></a>
        <#php if (<?php echo $categoryRecordVar ?>['_isSelected']): #></b><#php endif; #>
      
        <br>
      <#php endforeach; #>
    
    <?php endif; ?>

    </td>
    <td valign="top">
      
      <h3>Selected Category</h3>
      
    <#php if (!<?php echo $selectedCategoryVar ?>): #>
      <?php echo t('No category is selected!'); ?><br>
    <#php endif #>

    <#php if (<?php echo $selectedCategoryVar ?>): #>
<?php cg2_code_schemaFields($schema, $selectedCategoryVar, $tableName); ?>
<?php cg2_code_uploads($schema, $selectedCategoryVar); ?>
    <#php endif #>

    <#php if (<?php echo $selectedCategoryVar ?>): #>
    <div class="instructions">
      <b>Advanced Code Snippets and Field List</b> (you can safely remove this section)</b><br>
      <#php
        $selectedNum     = intval($selectedCategory['num']);
        $recordsOnBranch = mysql_select('category', "lineage LIKE '%:$selectedNum:%'");
        $branchNums      = array_pluck($recordsOnBranch, 'num');
        $branchNumsAsCSV = mysql_getValuesAsCSV($branchNums);
      #>
      Selected category num: <#php echo $selectedCategory['num']; #><br>
      All nums in branch: <#php echo $branchNumsAsCSV; #><br>
      All fields available for the selected record:<br>
      <div style="margin-left: 25px; font-family: monospace">
        <#php echo nl2br(str_replace('  ', ' &nbsp;', htmlencode(print_r($selectedCategory, true)))); #>
      </div>
    </div>
    <#php endif #>

      
      <br><br>
    </td>
  </tr>
</table>


<?php cg2_code_footer(); ?>

<?php
  // return code
  $code = ob_get_clean();
  return $code;
}


// get a column of categories from a table as a list of options
function cg3__getCategoryNames_asSelectOptions($fieldname) {
  $tableName = @$_REQUEST['tableName'];

  // options
  $showRootOption       = ($fieldname == 'rootCategoryNum');
  $showEmptyOptionFirst = ($fieldname == 'defaultCategoryNum'); // eg: <select>
  
  // if no table...
  if (!$tableName) {
    $htmlOptions = "<option value=''>" . htmlencode(t('<select section above>')). "</option>"; 
  }
  
  // otherwise load categories from selected table
  else {
    
    // load categories
    $query      = "SELECT num, name, breadcrumb, depth
                     FROM `{$GLOBALS['TABLE_PREFIX']}$tableName` ORDER BY `globalOrder`";
    $categories = mysql_select_query($query);
    
    // load option values
    $selectedValues  = @$_REQUEST[$fieldname];
    $optionValues    = array_pluck($categories, 'num');
    $optionLabels    = [];
    foreach ($categories as $category) {
      //$optionLabels[] = htmlencode($category['breadcrumb']);
      $optionLabels[] = str_repeat("&nbsp; &nbsp;", $category['depth']) . htmlencode($category['name']);
    }
    
    // get html
    $htmlOptions = ''; 
    if ($showRootOption)    { $htmlOptions  .= "<option value=''>". t('Root (show all categories)'). "</option>\n"; }
    $htmlOptions   .= getSelectOptions($selectedValues, $optionValues, $optionLabels, $showEmptyOptionFirst, false);
    if (!$optionValues)      { $htmlOptions  .= "<option value=''>". htmlencode(t('<no records found>')). "</option>\n"; }
  }
  
  //
  return $htmlOptions;
}

// eof
<?php
/*
Plugin Name: Combo Page Generator
Description: Adds "Combo Page" to Code Generator
Version: 1.00
Requires at least: 2.15
*/

// Note: This library is automatically included by /lib/menus/_codeGenerator/actionHandler.php
// ... but can be duplicated and added to the /plugins/ folder to create a new code generator.
// ... Just be sure to change the function names or you'll get errors about duplicate functions.

require_once SCRIPT_DIR . '/lib/menus/_codeGenerator/generator_functions.php';

// register generator
addGenerator('cg2_combopage', 'Combo Page', 'Combination List and Detail Page, show links to many records and full details on one record.', cg_typeByVersion(2));

// dispatch function
function cg2_combopage($function, $name, $description, $type) {

  // call ajax code
  cg2_combopage_ajaxPhpCode();

  // show options menu, and errors on submit
  cg2_combopage_getOptions($function, $name, $description, $type);

  // show code
  $instructions   = []; // show as bullet points
  $filenameSuffix = 'combo'; // eg: tablename-FILENAMESUFFIX.php
  $code           = cg2_combopage_getCode();
  cg2_showCode($function, $name, $instructions, $filenameSuffix, $code);
  exit;
}


// user specified options
function cg2_combopage_getOptions($function, $name, $description, $type) {

  // error checking
  if (@$_REQUEST['_showCode']) {
    $errorsAndAlerts = '';
    if (!@$_REQUEST['tableName'])  { alert("Please select a section!<br>\n"); }
    if (!@$_REQUEST['howMany'])    { alert("Please select 'How Many'!<br>\n"); }
    if (!@$_REQUEST['titleField']) { alert("Please select a Title field!<br>\n"); }

    if (!alert()) { // if no other errors, check fields exist in schema
      $schema = loadSchema($_REQUEST['tableName']);
      if (!in_array($_REQUEST['titleField'], array_keys($schema)))       { alert("Invalid field '" .htmlencode($_REQUEST['titleField']). "' selected!<br>\n"); }
    }

    if (!alert()) { return; } // if form submitted and no errors than return and generate code
  }

  // set form defaults
  $defaults['howMany']          = 'all';
  $defaults['limit']            = '5';
  $defaults['showUploads']      = 'all';
  $defaults['showUploadsCount'] = '1';
  $defaults['titleField']       = 'title';
  foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $_REQUEST)) { $_REQUEST[$key] = $value; }
  }

  // prepare adminUI() placeholders
  $adminUI = [];
  
  // form tag and hidden fields
  $adminUI['HIDDEN_FIELDS'] = [ [ 'name' => '_showCode', 'value' => '1' ] ];

  $adminUI['PRE_FORM_HTML'] = ob_capture('cg2_combopage_ajaxJsCode'); // show ajax js code
  
  // main content
  $adminUI['CONTENT'] = ob_capture(function() { ?>

    <div class="form-horizontal">
    
      <?php echo adminUI_separator(t('Viewer Options')); ?>
      
      <?php cg2_option_selectSection(); ?>

      <div class="form-group">
        <div class="col-sm-2 control-label"><?php et('How Many')?></div>
        <div class="col-sm-10">
          <div class="radio">
            <label>
              <?php echo cg2_inputRadio('howMany', 'all'); ?>
              <?php et('Show all records')?>
            </label>
          </div>
          <div class="radio">
            <label>
              <?php echo cg2_inputRadio('howMany', 'firstN'); ?>
              <?php echo sprintf(t('Show the first %s records only'), cg2_inputText('limit', 3)); ?>
            </label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-2 control-label"><?php et('Title/Name field')?></div>
        <div class="col-sm-10">
          <div class="form-inline">
            <?php echo cg2_inputSchemaField('titleField'); ?>
          </div>
        </div>
      </div>

      <?php echo adminUI_separator(t('Detail Viewer Options')); ?>

      <?php cg2_option_uploads() ?>

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
function cg2_combopage_getCode() {
  $tableName  = @$_REQUEST['tableName'];
  $schema     = loadSchema($tableName);
  $menuName   = @$schema['menuName'] ?: $tableName;

  // define variable names
  $tableRecordsVar = '$' .preg_replace("/[^\w]/", '_', $tableName). "Records";
  $metaDataVar     = '$' .preg_replace("/[^\w]/", '_', $tableName). "MetaData";
  $listRecordVar   = '$listRecord';
  $detailRecordVar = '$detailRecord';

  // list records - define getRecords() options
  $options = [];
  $options[] = "'tableName'   => '$tableName',";
  if      (@$_REQUEST['howMany'] == 'firstN')    { $options[] = "'limit'       => '{$_REQUEST['limit']}',"; }
  $options[] = "'loadUploads' => false,";
  $options[] = "'allowSearch' => false,";
  $padding   = "    ";
  $listRecordsOptions = "\n$padding" . implode("\n$padding", $options) . "\n  ";

  // detail record - define getRecords() options
  $options = [];
  $options[] = "'tableName'   => '$tableName',";
  $options[] = "'where'       => whereRecordNumberInUrl(1), // If no record # is specified then latest record is shown";
  if      (@$_REQUEST['showUploads'] == 'all')    { $options[] = "'loadUploads' => true,"; }
  elseif  (@$_REQUEST['showUploads'] == 'limit')  { $options[] = "'loadUploads' => true,"; }
  else                                            { $options[] = "'loadUploads' => false,"; }
  $options[] = "'allowSearch' => false,";
  $options[] = "'limit'       => '1',";
  $detailRecordOptions = "\n$padding" . implode("\n$padding", $options) . "\n  ";

  ### generate code
  ob_start();
?><#php header('Content-type: text/html; charset=utf-8'); #>
<#php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */
  <?php cg2_code_loadLibraries(); ?>

  // load detail record from '<?php echo $tableName ?>'
  list(<?php echo $tableRecordsVar ?>, <?php echo $metaDataVar ?>) = getRecords(array(<?php echo $detailRecordOptions; ?>));
  <?php echo $detailRecordVar ?> = @<?php echo $tableRecordsVar ?>[0]; // get first record
  if (!<?php echo $detailRecordVar ?>) { dieWith404("Record not found!"); } // show error message if no record found

  // load list records from '<?php echo $tableName ?>'
  list(<?php echo $tableRecordsVar ?>, <?php echo $metaDataVar ?>) = getRecords(array(<?php echo $listRecordsOptions; ?>));

#><?php cg2_code_header(); ?>
<?php cg2_code_instructions('Combo'); ?>

<h1><?php echo $menuName ?> - <?php echo t('Combo Page Viewer'); ?></h1>

<table border="1" cellspacing="2" cellpadding="4">
 <tr>
  <td valign="top">

  <!-- STEP2: Display Record List (Paste this where you want your record list) -->
    <b>Record List</b><br>

    <#php foreach (<?php echo $tableRecordsVar ?> as <?php echo $listRecordVar ?>): #>
      <#php $isSelected = (<?php echo $listRecordVar ?>['num'] == <?php echo $detailRecordVar ?>['num']); #>
      <#php if ($isSelected) { print "<b>"; } #>
      <a href="<#php echo htmlencode(<?php echo $listRecordVar ?>['_link']) #>"><#php echo htmlencode(<?php echo $listRecordVar ?>['<?php echo @$_REQUEST['titleField'] ?>']) #></a><br>
      <#php if ($isSelected) { print "</b>"; } #>
    <#php endforeach #>

    <#php if (!<?php echo $tableRecordsVar ?>): #>
      No records were found!<br><br>
    <#php endif #>
  <!-- /STEP2: Display Record List -->

  </td>
  <td valign="top">

  <!-- STEP2: Display Record Detail (Paste this where you want your record details) -->
    <b>Record Detail</b><br>
<?php cg2_code_schemaFields($schema, $detailRecordVar, $tableName); ?>
<?php if (@$_REQUEST['showUploads']) { cg2_code_uploads($schema, $detailRecordVar); } ?>

  <a href="mailto:?subject=<#php echo urlencode(thisPageUrl()) #>">Email this Page</a>
  <!-- /STEP2: Display Record Detail -->

  </td>
 </tr>
</table>

<?php cg2_code_footer(); ?>
<?php
  // return code
  $code = ob_get_clean();
  return $code;
}


//
function cg2_combopage_ajaxJsCode() {
  $ajaxUrl = "?menu=" .@$_REQUEST['menu']. "&_generator=" .@$_REQUEST['_generator']. "&_ajax=schemaFields";
?><script type="text/javascript"><!--

$(document).ready(function(){

  // register change event
  $(document).on('change', 'select[name=tableName]', function() {
    cg2_updateSchemaFieldPulldowns();
  });
});

//
function cg2_updateSchemaFieldPulldowns() {
  var tableName = $('select[name=tableName]').val(); // get tableName

  // error checking
  if ($('select.ajax-schema-fields').length == 0) { return; } // skip if there are no schema field pulldowns to update
  if (tableName == '') { return; } // return if no table selected


  // show loading... for all pulldowns
  $('select.ajax-schema-fields').html("<option value=''>loading...</option>");

  // load schema fields

  var ajaxUrl   = "<?php echo $ajaxUrl ?>&tableName=" + tableName;
  $.ajax({
    url: ajaxUrl,
    dataType: 'html',
    error:   function(XMLHttpRequest, textStatus, errorThrown){
      alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] + ")\n" + errorThrown);
    },
    success: function(optionsHTML){
      if (!optionsHTML.match(/^<option/)) { return alert("Error loading schema options!\n" + optionsHTML); }
      $('select.ajax-schema-fields').html(optionsHTML);
    }
  });
}
//--></script>
<?php
}

//
function cg2_combopage_ajaxPhpCode() {
  if (@$_REQUEST['_ajax'] == 'schemaFields') {
    $htmlOptions   = cg2_inputSchemaField_getOptions( @$_REQUEST['tableName'] );
    print $htmlOptions;
    exit;
  }
}

?>
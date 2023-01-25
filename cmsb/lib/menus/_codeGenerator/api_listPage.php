<?php // API List Page Generator - Adds "List Page" to Code Generator

// register generator
addGenerator('cg3_listpage', 'List Page', 'Show many records.', cg_typeByVersion(3));

// dispatch function
function cg3_listpage($function, $name, $description, $type) {

  // show options menu, and errors on submit
  cg3_listpage_getOptions($function, $name, $description, $type);

  // show code
  $instructions   = []; // show as bullet points
  $filenameSuffix = 'list'; // eg: tablename-FILENAMESUFFIX.php
  $code           = cg3_listpage_getCode();
  cg2_showCode($function, $name, $instructions, $filenameSuffix, $code);
  exit;
}


// user specified options
function cg3_listpage_getOptions($function, $name, $description, $type) {

  // error checking
  if (@$_REQUEST['_showCode']) {
    $errorsAndAlerts = '';
    if (!@$_REQUEST['tableName']) { alert("Please select a section!<br>\n"); }
    if (!alert()) { return; } // if form submitted and no errors than return and generate code
  }

  // set form defaults
  $defaults['howMany']          = 'all';
  $defaults['orderBy']          = 'default';
  $defaults['showUploads']      = 'all';
  $defaults['showUploadsCount'] = '1';
  $defaults['allowSearching']   = 0;
  $defaults['limit']            = 5;
  $defaults['perPage']          = 10;
  foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $_REQUEST)) { $_REQUEST[$key] = $value; }
  }

  // prepare adminUI() placeholders
  $adminUI = [];
  
  // form tag and hidden fields
  $adminUI['HIDDEN_FIELDS'] = [ [ 'name' => '_showCode', 'value' => '1' ] ];
  
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
          
          <div class="radio">
            <label>
              <?php echo cg2_inputRadio('howMany', 'paged'); ?>
              <?php echo sprintf(t('Show %s records per page with prev &amp; next page links'), cg2_inputText('perPage', 3)); ?>
            </label>
          </div>
          
        </div>
      </div>

      <?php cg2_option_sorting() ?>
      <?php cg2_option_uploads() ?>

      <div class="form-group">
        <div class="col-sm-2 control-label"><?php et('Allow Searching')?></div>
        <div class="col-sm-10 control-label">
          <div class="text-left">
            <?php echo cg2_inputCheckbox('allowSearching'); ?>
            <label for="allowSearching">
              <?php et('Filter results based on search form input (disable for multiple viewers on one page)')?>
            </label>
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
function cg3_listpage_getCode() {
  $tableName  = @$_REQUEST['tableName'];
  $schema     = loadSchema($tableName);
  $menuName   = @$schema['menuName'] ?: $tableName;

  // define variable names
  $tableRecordsVar = '$' .preg_replace("/[^\w]/", '_', $tableName). "Records";
  $metaDataVar     = '$' .preg_replace("/[^\w]/", '_', $tableName). "MetaData";
  $recordVar       = '$record';

  // define getRecordsAPI() options
  $options = [];
  $options[] = "'tableName'   => '$tableName',";
  if      (@$_REQUEST['howMany'] == 'firstN')    { $options[] = "'limit'       => '{$_REQUEST['limit']}',"; }
  else if (@$_REQUEST['howMany'] == 'paged')     { $options[] = "'perPage'     => '{$_REQUEST['perPage']}',"; }
  else                                           { /* default to showing all */ }
  if      (@$_REQUEST['orderBy'] == 'random')    { $options[] = "'orderBy'     => 'RAND()',"; }
  if      (@$_REQUEST['showUploads'] == 'all')   { $options[] = "'loadUploads' => true,"; }
  elseif  (@$_REQUEST['showUploads'] == 'limit') { $options[] = "'loadUploads' => true,"; }
  else                                           { $options[] = "'loadUploads' => false,"; }
  if      (@$_REQUEST['allowSearching'])         { $options[] = "'allowSearch' => true,"; }
  else                                           { $options[] = "'allowSearch' => false,"; }
  $padding           = "    ";
  $getRecordsOptions = "\n$padding" . implode("\n$padding", $options) . "\n  ";

  ### generate code
  ob_start();
?><#php header('Content-type: text/html; charset=utf-8'); #>
<#php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */
  <?php cg3_code_loadLibraries(); ?>

  // load records from '<?php echo $tableName ?>'
  list(<?php echo $tableRecordsVar ?>, <?php echo $metaDataVar ?>) = getRecordsAPI(array(<?php echo $getRecordsOptions; ?>));

#><?php cg2_code_header(); ?>
<?php cg2_code_instructions('List'); ?>

  <!-- STEP2: Display Records (Paste this where you want your records to be listed) -->
    <h1><?php echo $menuName ?> - <?php echo t('List Page Viewer'); ?></h1>
    <#php foreach (<?php echo $tableRecordsVar ?> as <?php echo $recordVar ?>): #>
<?php cg2_code_schemaFields($schema, $recordVar, $tableName); ?>
<?php if (@$_REQUEST['showUploads']) { cg2_code_uploads($schema, $recordVar); } ?>
      <hr>
    <#php endforeach #>

    <#php if (!<?php echo $tableRecordsVar ?>): #>
      <?php echo t('No records were found!'); ?><br><br>
    <#php endif #>
  <!-- /STEP2: Display Records -->

<?php if (@$_REQUEST['howMany'] == 'paged'): ?>
  <!-- STEP3: Display Page Links (Paste anywhere below "Load Record List") -->
    <#php if (<?php echo $metaDataVar ?>['prevPage']): #>
      <a href="<#php echo <?php echo $metaDataVar ?>['prevPageLink'] #>"><?php echo t('&lt;&lt;  prev'); ?></a>
    <#php else: #>
      &lt;&lt; prev
    <#php endif #>

    - page <#php echo <?php echo $metaDataVar ?>['page'] #> of <#php echo <?php echo $metaDataVar ?>['totalPages'] #> -

    <#php if (<?php echo $metaDataVar ?>['nextPage']): #>
      <a href="<#php echo <?php echo $metaDataVar ?>['nextPageLink'] #>"><?php echo t('next'); ?> &gt;&gt;</a>
    <#php else: #>
      next &gt;&gt;
    <#php endif #>
  <!-- /STEP3: Display Page Links -->
<?php endif ?>
<?php cg2_code_footer(); ?>

<?php
  // return code
  $code = ob_get_clean();
  return $code;
}

// eof
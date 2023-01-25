<?php

  ### set globals
  if (@$_REQUEST['tableName'] == '') { die("no tableName specified!"); }
  $_REQUEST['tableName'] = getTableNameWithPrefix($_REQUEST['tableName']);  # force add table prefix (if not specified)

  // default field values (used creating fields)
  global $defaultValue;
  $defaultValue = [];
  $defaultValue['order']               = $_REQUEST['order'] ?? time(); //Get the order from the request, or sort to the bottom
  $defaultValue['label']               = '';
  $defaultValue['type']                = 'none';
  $defaultValue['adminOnly']           = '0';
  $defaultValue['isSystemField']       = '0';
  $defaultValue['isEncrypted']         = '0';
  $defaultValue['defaultValue']        = '';
  $defaultValue['isPasswordField']     = '0';
  $defaultValue['isRequired']          = '0';
  $defaultValue['isUnique']            = '0';
  $defaultValue['minLength']           = '';
  $defaultValue['maxLength']           = '';
  $defaultValue['charsetRule']         = '';
  $defaultValue['charset']             = '';
  $defaultValue['autoFormat']          = '1';
  $defaultValue['fieldPrefix']         = '';
  $defaultValue['description']         = '';
  $defaultValue['fieldWidth']          = '';
  $defaultValue['fieldAddonBefore']    = '';
  $defaultValue['fieldAddonAfter']     = '';
  $defaultValue['myAccountField']      = '0';

  // date options
  $defaultValue['defaultDate']         = '';                            // '' for currentDate, none for no date, or 'custom'
  $defaultValue['defaultDateString']   = date('Y') . '-01-01 00:00:00'; // if custom use this date (or strtotime value)
  $defaultValue['showTime']            = '1';
  $defaultValue['showSeconds']         = '1';
  $defaultValue['use24HourFormat']     = '0';
  $defaultValue['yearRangeStart']      = ''; // v2.16 use dynamic defaults // date('Y') - 2;
  $defaultValue['yearRangeEnd']        = ''; // v2.16 use dynamic defaults // date('Y') + 8;

  // checkbox options
  $defaultValue['checkedByDefault']  = '0';
  $defaultValue['checkedValue']      = t('Yes');
  $defaultValue['uncheckedValue']    = t('No');

  // list options
  $defaultValue['listType']           = 'pulldown';
  $defaultValue['optionsType']        = "text";  // text, table, sql
  $defaultValue['optionsText']        = "option one\noption two\noption three";
  $defaultValue['optionsTablename']   = '';
  $defaultValue['optionsValueField']  = '';
  $defaultValue['optionsLabelField']  = '';
  $defaultValue['optionsQuery']       = "SELECT fieldname1, fieldname2\n  FROM `<?php echo \$TABLE_PREFIX ?>tableName`";
  $defaultValue['filterField']        = '';

  // wysiwyg options
  $defaultValue['defaultContent']      = '';
  $defaultValue['fieldHeight']         = '';   // also used by textboxes
  $defaultValue['allowUploads']        = '1';
  $defaultValue['wysiwygCode']         = 'default wysiwyg code';

  // upload options
  $defaultValue['allowedExtensions']     = 'gif,jpg,jpeg,png,svg,webp';
  $defaultValue['checkMaxUploadSize']    = '1';
  $defaultValue['maxUploadSizeKB']       = '5120';
  $defaultValue['checkMaxUploads']       = '1';
  $defaultValue['maxUploads']            = '25';
  $defaultValue['resizeOversizedImages'] = '1';
  $defaultValue['maxImageHeight']        = '800';
  $defaultValue['maxImageWidth']         = '600';
  $defaultValue['createThumbnails']      = '1';
  $defaultValue['maxThumbnailHeight']    = '150';
  $defaultValue['maxThumbnailWidth']     = '150';
  $defaultValue['cropThumbnails']        = '0';
  $defaultValue['createThumbnails2']     = '0';
  $defaultValue['maxThumbnailHeight2']   = '150';
  $defaultValue['maxThumbnailWidth2']    = '150';
  $defaultValue['cropThumbnails2']       = '0';
  $defaultValue['createThumbnails3']     = '0';
  $defaultValue['maxThumbnailHeight3']   = '150';
  $defaultValue['maxThumbnailWidth3']    = '150';
  $defaultValue['cropThumbnails3']       = '0';
  $defaultValue['createThumbnails4']     = '0';
  $defaultValue['maxThumbnailHeight4']   = '150';
  $defaultValue['maxThumbnailWidth4']    = '150';
  $defaultValue['cropThumbnails4']       = '0';
  $defaultValue['useCustomUploadDir']    = '0';
  $defaultValue['customUploadDir']       = $SETTINGS['uploadDir'];
  $defaultValue['customUploadUrl']       = $SETTINGS['uploadUrl'];
  $defaultValue['infoField1']            = "Title";
  $defaultValue['infoField2']            = "Caption";
  $defaultValue['infoField3']            = '';
  $defaultValue['infoField4']            = '';
  $defaultValue['infoField5']            = '';

  // separator options
  $defaultValue['separatorType']       = 'blank line';
  $defaultValue['separatorHeader']     = '';
  $defaultValue['separatorHTML']       = "<div class='col-sm-2'>\n  Column 1\n</div>\n<div class='col-sm-10'>\n  Column 2\n</div>\n";
  $defaultValue['isCollapsible']       = '';
  $defaultValue['isCollapsed']         = '';
  
  // Related Table options
  $defaultValue['relatedTable']        = '';
  $defaultValue['relatedLimit']        = '25';
  $defaultValue['relatedView']         = '';
  $defaultValue['relatedModify']       = '';
  $defaultValue['relatedErase']        = '';
  $defaultValue['relatedCreate']       = '';
  $defaultValue['relatedWhere']        = 'foreignFieldNum=\'<?php echo mysql_escape(@$RECORD[\'num\']) ?'.'>\'';
  $defaultValue['relatedMoreLink']     = 'foreignFieldNum_match=<?php echo htmlencode(@$RECORD[\'num\']) ?'.'>';


  // get column type
  $defaultValue['customColumnType']  = '';
  
  // mysql column index and advanced features
  $defaultValue['indexed']             = '';
  $defaultValue['isEncrypted']         = '';

  // error checking
  if (@$_REQUEST['tableName'] == '') { die("no 'tableName' specified!"); }

  // load schema
  global $schema;
  $schema = loadSchema($_REQUEST['tableName']);
  if (empty($schema)) {
    $error  = "Can't find schema file for table '" . htmlencode($_REQUEST['tableName']) . "'. ";
    $error .= "Check the table details, click 'Save Details', and try again.<br>\n";
    $error .= "(Reload your browser to close this dialog).<br>\n";
    die($error);
  }

  // dispatch actions
  if (@$_REQUEST['save']) {
    submitFormViaAjax();
    exit;
  }


//
function getTablesAndFieldnames() {
  $tablesAndFields = [];

  //
  foreach (getSchemaTables() as $tableName) {
    $schema = loadSchema($tableName);
    foreach ($schema as $fieldname => $fieldSchema) {
      if (!is_array($fieldSchema)) { continue; }  // skip table metadata - fields are arrays
      if (@$fieldSchema['type'] == 'separator') { continue; }  // skip separators
      if (@$fieldSchema['type'] == 'tabGroup')  { continue; }  // skip separators
      if (@$fieldSchema['type'] == 'relatedRecords') { continue; }  // skip

      $tablesAndFields[$tableName][] = $fieldname;
    }
  }

  // sort tablenames (fieldnames are already sorted by saveSchema)
  ksort($tablesAndFields);

  //
  return $tablesAndFields;
}

// load field attributes (or defaults)
function getFieldAttributes($fieldname) {
  global $schema, $defaultValue;

  // get field schema
  $fieldSchema = [];
  if ($fieldname && array_key_exists($fieldname, $schema)) {
    $fieldSchema = $schema[$fieldname];
  }

  ### get field values (or defaults)
  $field = [];
  foreach (array_keys($defaultValue) as $key) { // set defaults if no value defined
    $field[$key] = isset($fieldSchema[$key]) ? $fieldSchema[$key] : $defaultValue[$key];
  }
  $field['newFieldname'] = $fieldname;

  //
  return $field;
}




//
function submitFormViaAjax() {
  global $schema;

  //
  security_dieUnlessPostForm();
  security_dieUnlessInternalReferer();
  security_dieOnInvalidCsrfToken();
  
  //
  disableInDemoMode('', 'ajax');

  // auto-assign separator and relatedRecords fieldnames
  if ($_REQUEST['type'] == 'separator' || $_REQUEST['type'] == 'relatedRecords' || $_REQUEST['type'] == 'tabGroup') {
    if ($_REQUEST['fieldname'] == '') { // new field
      $newFieldname = '';
      $count = '001';
      while (!$newFieldname || array_key_exists($newFieldname, $schema)) {
        $newFieldname = "__{$_REQUEST['type']}{$count}__";
        $count = str_pad(++$count, 3, '0', STR_PAD_LEFT);
      }
      $_REQUEST['newFieldname'] = $newFieldname;
    }
    else {
      $_REQUEST['newFieldname'] = $_REQUEST['fieldname'];
    }
  }
  
  // support MySQL Column Type dropdown supplying a value
  if (@$_REQUEST['customColumnType-select'] !== '_customColumnType_') {
    $_REQUEST['customColumnType'] = @$_REQUEST['customColumnType-select'];
  }

  // Separator - Use label for header
  if ($_REQUEST['type'] == 'separator' && !@$_REQUEST['separatorType']) { // separatorType is only undefined when being called from quickadd
    $_REQUEST['separatorType']   = ($_REQUEST['label'] == '') ? 'blank line' : 'header bar';
    $_REQUEST['separatorHeader'] = $_REQUEST['label'];
    $_REQUEST['label']           = ''; // blank out label on quick add, require users to manually add it.
  }

  // Reserved fieldnames (from MySQL 5.5, 5.6, 5.7, 8.0[beta]) - https://dev.mysql.com/doc/refman/8.0/en/keywords.html
  // v3.11 - Removed 'name', it's only used in stored procedures by mysql
  $mysqlReserved = ['accessible','account','action','add','admin','after','against','aggregate','algorithm','all','alter','always','analyse','analyze','and','any','as','asc',
  'ascii','asensitive','at','authors','autoextend_size','auto_increment','avg','avg_row_length','backup','before','begin','between','bigint','binary','binlog','bit','blob','block',
  'bool','boolean','both','btree','by','byte','cache','call','cascade','cascaded','case','catalog_name','chain','change','changed','channel','char','character','charset','check',
  'checksum','cipher','class_origin','client','close','coalesce','code','collate','collation','column','columns','column_format','column_name','comment','commit','committed','compact',
  'completion','component','compressed','compression','concurrent','condition','connection','consistent','constraint','constraint_catalog','constraint_name','constraint_schema','contains',
  'context','continue','contributors','convert','cpu','create','cross','cube','current','current_date','current_time','current_timestamp','current_user','cursor','cursor_name','data',
  'database','databases','datafile','date','datetime','day','day_hour','day_microsecond','day_minute','day_second','deallocate','dec','decimal','declare','default','default_auth',
  'definer','delayed','delay_key_write','delete','desc','describe','des_key_file','deterministic','diagnostics','directory','disable','discard','disk','distinct','distinctrow','div',
  'do','double','drop','dual','dumpfile','duplicate','dynamic','each','else','elseif','enable','enclosed','encryption','end','ends','engine','engines','enum','error','errors',
  'escape','escaped','event','events','every','except','exchange','execute','exists','exit','expansion','expire','explain','export','extended','extent_size','false','fast','faults',
  'fetch','fields','file','file_block_size','filter','first','fixed','float','float4','float8','flush','follows','for','force','foreign','format','found','frac_second','from','full',
  'fulltext','function','general','generated','geometry','geometrycollection','get','get_format','global','grant','grants','group','grouping','group_replication','handler','hash',
  'having','help','high_priority','host','hosts','hour','hour_microsecond','hour_minute','hour_second','identified','if','ignore','ignore_server_ids','import','in','index','indexes',
  'infile','initial_size','inner','innobase','innodb','inout','insensitive','insert','insert_method','install','instance','int','int1','int2','int3','int4','int8','integer','interval',
  'into','invisible','invoker','io','io_after_gtids','io_before_gtids','io_thread','ipc','is','isolation','issuer','iterate','join','json','key','keys','key_block_size','kill',
  'language','last','leading','leave','leaves','left','less','level','like','limit','linear','lines','linestring','list','load','local','localtime','localtimestamp','lock','locked',
  'locks','logfile','logs','long','longblob','longtext','loop','low_priority','master','master_auto_position','master_bind','master_connect_retry','master_delay','master_heartbeat_period',
  'master_host','master_log_file','master_log_pos','master_password','master_port','master_retry_count','master_server_id','master_ssl','master_ssl_ca','master_ssl_capath','master_ssl_cert',
  'master_ssl_cipher','master_ssl_crl','master_ssl_crlpath','master_ssl_key','master_ssl_verify_server_cert','master_tls_version','master_user','match','maxvalue','max_connections_per_hour',
  'max_queries_per_hour','max_rows','max_size','max_statement_time','max_updates_per_hour','max_user_connections','medium','mediumblob','mediumint','mediumtext','memory','merge',
  'message_text','microsecond','middleint','migrate','minute','minute_microsecond','minute_second','min_rows','mod','mode','modifies','modify','month','multilinestring','multipoint',
  'multipolygon','mutex','mysql_errno','names','national','natural','nchar','ndb','ndbcluster','never','new','next','no','nodegroup','nonblocking','none','not','nowait',
  'no_wait','no_write_to_binlog','null','number','numeric','nvarchar','of','offset','old_password','on','one','one_shot','only','open','optimize','optimizer_costs','option',
  'optionally','options','or','order','out','outer','outfile','owner','pack_keys','page','parser','parse_gcol_expr','partial','partition','partitioning','partitions','password',
  'persist','phase','plugin','plugins','plugin_dir','point','polygon','port','precedes','precision','prepare','preserve','prev','primary','privileges','procedure','processlist',
  'profile','profiles','proxy','purge','quarter','query','quick','range','read','reads','read_only','read_write','real','rebuild','recover','recursive','redofile','redo_buffer_size',
  'redundant','references','regexp','relay','relaylog','relay_log_file','relay_log_pos','relay_thread','release','reload','remove','rename','reorganize','repair','repeat','repeatable',
  'replace','replicate_do_db','replicate_do_table','replicate_ignore_db','replicate_ignore_table','replicate_rewrite_db','replicate_wild_do_table','replicate_wild_ignore_table','replication',
  'require','reset','resignal','restore','restrict','resume','return','returned_sqlstate','returns','reverse','revoke','right','rlike','role','rollback','rollup','rotate','routine',
  'row','rows','row_count','row_format','rtree','savepoint','schedule','schema','schemas','schema_name','second','second_microsecond','security','select','sensitive','separator',
  'serial','serializable','server','session','set','share','show','shutdown','signal','signed','simple','skip','slave','slow','smallint','snapshot','socket','some','soname','sounds',
  'source','spatial','specific','sql','sqlexception','sqlstate','sqlwarning','sql_after_gtids','sql_after_mts_gaps','sql_before_gtids','sql_big_result','sql_buffer_result','sql_cache',
  'sql_calc_found_rows','sql_no_cache','sql_small_result','sql_thread','sql_tsi_day','sql_tsi_frac_second','sql_tsi_hour','sql_tsi_minute','sql_tsi_month','sql_tsi_quarter','sql_tsi_second',
  'sql_tsi_week','sql_tsi_year','ssl','stacked','start','starting','starts','stats_auto_recalc','stats_persistent','stats_sample_pages','status','stop','storage','stored','straight_join',
  'string','subclass_origin','subject','subpartition','subpartitions','super','suspend','swaps','switches','table','tables','tablespace','table_checksum','table_name','temporary',
  'temptable','terminated','text','than','then','time','timestamp','timestampadd','timestampdiff','tinyblob','tinyint','tinytext','to','trailing','transaction','trigger','triggers',
  'true','truncate','type','types','uncommitted','undefined','undo','undofile','undo_buffer_size','unicode','uninstall','union','unique','unknown','unlock','unsigned','until','update',
  'upgrade','usage','use','user','user_resources','use_frm','using','utc_date','utc_time','utc_timestamp','validation','value','values','varbinary','varchar','varcharacter','variables',
  'varying','view','virtual','visible','wait','warnings','week','weight_string','when','where','while','with','without','work','wrapper','write','x509','xa','xid','xml','xor',
  'year','year_month','zerofill'];
  $cmsReserved = ['menu','menuName','menuType','menuOrder','menuHidden','tableHidden','listPageFields','listPageOrder','listPageSearchFields','length','order','action','page']; // _fields (leading underscore) aren't allow by default so we don't need exclude them by name 
  $reservedFieldnames = array_merge($mysqlReserved, $cmsReserved);
  $reservedFieldnames = array_map('strtolower', $reservedFieldnames); // lowercase all keywords
    
  $isAdding            = !$_REQUEST['fieldname'] && $_REQUEST['newFieldname'];
  $isQuickAdd          = !empty($_REQUEST['quickadd']) ?: false;
  $isRenaming          = $_REQUEST['fieldname'] && $_REQUEST['fieldname'] != $_REQUEST['newFieldname'];
  $isAddingOrRenaming  = $isAdding || $isRenaming;
  $isFieldnameReserved = in_array( strtolower($_REQUEST['newFieldname']), $reservedFieldnames );
  $typeNoneFields      = array('num', 'createdDate', 'createdByUserNum', 'updatedDate', 'updatedByUserNum', 'dragSortOrder');
  $typeDateFields      = array('publishDate', 'removeDate');
  $typeCheckboxFields  = array('neverRemove', 'hidden');

  // error checking
  $errors = '';
  if (@$_REQUEST['tableName'] == '')                                 { $errors .= "no 'tableName' specified!\n"; }
  if (@$_REQUEST['type']      == '')                                 { $errors .= "no field 'type' specified!\n"; }
  if (!$_REQUEST['type'])                                            { $errors .= "You must enter a value for 'Field Type'\n"; }
  if     (!@$_REQUEST['newFieldname'])                               { $errors .= "You must enter a value for 'Field Name'\n"; }
  elseif (preg_match('/[^a-z0-9\_\-]/i', $_REQUEST['newFieldname'])) { $errors .= "'Field Name' can only contain the following characters (a-z, A-Z, 0-9, - and _)\n"; }
  elseif (preg_match('/^\d+$/', $_REQUEST['newFieldname']))          { $errors .= "'Field Name' cannot contain numbers only\n"; }
  elseif ($isAdding &&
          $_REQUEST['type'] != 'separator' &&
          $_REQUEST['type'] != 'tabGroup' &&
          $_REQUEST['type'] != 'relatedRecords' &&
          preg_match('/^_/i', $_REQUEST['newFieldname']))             { $errors .= "'Field Name' cannot start with an underscore\n"; }
  elseif ($isAddingOrRenaming && $isFieldnameReserved)                { $errors .= "Selected fieldname is reserved, please choose another.\n"; }
  elseif ($isAddingOrRenaming && @$schema[$_REQUEST['newFieldname']]) { $errors .= "Selected fieldname is already in use, please choose another.\n"; }
  if (@$_REQUEST['useCustomUploadDir']) {
#    if (!preg_match('/\/$/', $_REQUEST['customUploadDir']))          { $errors .= "Upload Directory Path must end with a slash! (eg: products/ or /www/htdocs/uploads/products/)\n"; }
#    if (!preg_match('/\/$/', $_REQUEST['customUploadUrl']))          { $errors .= "Upload Folder Url must end with a slash! (eg: products/ or /www/htdocs/uploads/products/)\n"; }
  }
  if (in_array($_REQUEST['newFieldname'], $typeNoneFields)     && $_REQUEST['type'] != 'none')     { $errors .= "Field '{$_REQUEST['newFieldname']}' must be set to type 'none'\n"; }
  if (in_array($_REQUEST['newFieldname'], $typeDateFields)     && $_REQUEST['type'] != 'date')     { $errors .= "Field '{$_REQUEST['newFieldname']}' must be set to type 'date'\n"; }
  if (in_array($_REQUEST['newFieldname'], $typeCheckboxFields) && $_REQUEST['type'] != 'checkbox') { $errors .= "Field '{$_REQUEST['newFieldname']}' must be set to type 'checkbox'\n"; }

  if ($_REQUEST['type'] == 'textfield' && @$_REQUEST['charsetRule'] && preg_match("/\-./", @$_REQUEST['charset'])) {
    $errors .= "Allowed Content: If character list contains a dash it must be the last character!\n";
  }

  if($_REQUEST['type'] == 'list' && isset($_REQUEST['optionsType']) && $_REQUEST['optionsType'] == 'table') {
    if (!@$_REQUEST['optionsTablename'])  { $errors .= "You must select a Section Tablename.\n"; }
    if (!@$_REQUEST['optionsValueField']) { $errors .= "You must select a field for option values.\n"; }
    if (!@$_REQUEST['optionsLabelField']) { $errors .= "You must select a field for option labels.\n"; }
  }

  if ($_REQUEST['type'] == 'upload' || $_REQUEST['type'] == 'wysiwyg') { // 
    if (!$isQuickAdd && !empty($_REQUEST['allowUploads']) && empty($_REQUEST['allowedExtensions']))    { $errors .= "Allowed File extensions have not been specified.\n"; }
    if (@$_REQUEST['resizeOversizedImages'])  {
      if ($_REQUEST['maxImageHeight'] == '')                      { $errors .= "Resize images: Please specify a value for Max Image Height!\n"; }
      if (preg_match('/[^0-9\_]/i', $_REQUEST['maxImageHeight'])) { $errors .= "Resize images: Max Image Height must be a numeric value!\n"; }
      if ($_REQUEST['maxImageWidth'] == '')                       { $errors .= "Resize images: Please specify a value for Max Image Width!\n"; }
      if (preg_match('/[^0-9\_]/i', $_REQUEST['maxImageWidth']))  { $errors .= "Resize images: Max Image Width must be a numeric value!\n"; }
    }
    foreach (array('',2,3,4) as $num) {
      if (@$_REQUEST["createThumbnails$num"]) {
        $fieldLabel = "Create thumbnail" . (($num) ? "($num)" : '');
        if ($_REQUEST["maxThumbnailHeight$num"] == '')                      { $errors .= "$fieldLabel: Please specify a value for Max Image Height!\n"; }
        if (preg_match('/[^0-9\_]/i', $_REQUEST["maxThumbnailHeight$num"])) { $errors .= "$fieldLabel: Max Image Height must be a numeric value!\n"; }
        if ($_REQUEST["maxThumbnailWidth$num"] == '')                       { $errors .= "$fieldLabel: Please specify a value for Max Image Width!\n"; }
        if (preg_match('/[^0-9\_]/i', $_REQUEST["maxThumbnailWidth$num"]))  { $errors .= "$fieldLabel: Max Image Width must be a numeric value!\n"; }
      }
    }
  }
  
  // encrypted fields
  $wasEncrypted = !empty($schema[ $_REQUEST['newFieldname'] ]['isEncrypted']);
  $isEncrypted  = !empty($_REQUEST['isEncrypted']);
  if ($isEncrypted) { 
    $isGeneralQueryLogEnabled = mysql_get_query("SHOW VARIABLES WHERE Variable_name = 'general_log'")['Value'] == 'ON';
    if ($isGeneralQueryLogEnabled) { $errors .= "Data Encryption: You must disable the 'MySQL General Query Log' to use this feature!\n"; }
  }
  if ($wasEncrypted) { 
    // check user isn't trying to change column type on encrypted field (it gets changed automatically when encryption is first enabled)
    if ($_REQUEST['customColumnType'] != _mysql_encryption_colType()) { $errors .= "MySQL Column Type: You must use column type '" ._mysql_encryption_colType(). "' when Data Encryption is enabled.\n"; }
  }
  if ($isEncrypted && !mysql_isSupportedColumn($_REQUEST['tableName'], $_REQUEST['newFieldname'])) {
    $errors .= "Data Encryption: Encryption is not supported for this column.\n";
  }

  if ($errors) {
    print $errors;
    exit;
  }
  
  // update mysql first to get any MySQL errors before updating schema
  _updateMySQL();

  // update schema
  _updateSchema($schema);

  // implement field encryption/decryption
  $tableName = $_REQUEST['tableName'];
  $colName   = $_REQUEST['newFieldname'];
  if (!$wasEncrypted && $isEncrypted) { mysql_encrypt_column($tableName, $colName); }
  if ($wasEncrypted && !$isEncrypted) { mysql_decrypt_column($tableName, $colName); }

}



//
function _updateSchema(&$schema) {
  $oldColumnName  = $_REQUEST['fieldname'];
  $newColumnName  = $_REQUEST['newFieldname'];
  $oldFieldSchema = !empty($schema[$oldColumnName]) ? $schema[$oldColumnName] : [];

  // remove old column
  unset($schema[$oldColumnName]);

  // create new column (duplicate fieldname check already done in submitFormViaAjax)
  $schema[$newColumnName] = $oldFieldSchema; // v3.14 copy old fieldschema so we preserve keys not set by this code

  // ignore fields
  if (@$_REQUEST['optionsType'] != 'text' &&
      @$_REQUEST['optionsText'] != '')             { $_REQUEST['optionsText'] = ''; }
  if (@$_REQUEST['optionsType'] != 'table')        { $_REQUEST['optionsTablename'] = ''; $_REQUEST['optionsValueField'] = ''; $_REQUEST['optionsLabelField'] = '';  }
  if (@$_REQUEST['optionsType'] != 'query')        { $_REQUEST['optionsQuery'] = ''; $_REQUEST['filterField'] = ''; }
  if (@!$_REQUEST['useCustomUploadDir'])           { $_REQUEST['customUploadDir'] = ''; $_REQUEST['customUploadUrl'] = ''; }

  // update field schema (save these fields)
  $fieldsIgnoredIfEmpty  = array('customColumnType', 'isSystemField', 'isEncrypted', 'adminOnly',
                                 'optionsText', 'optionsTablename', 'optionsValueField', 'optionsLabelField', 'optionsQuery', 'filterField',
                                 'myAccountField' // this field is only shown when editing the accounts section
                                 ); // these fields aren't saved if they are blank or zero
  $fieldAttributesByType = array(    // these fields are saved for each field type
    'none'      => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed'),
    'textfield' => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'defaultValue', 'fieldPrefix', 'description', 'fieldAddonBefore', 'fieldAddonAfter', 'fieldWidth', 'isPasswordField', 'isRequired', 'isUnique', 'minLength', 'maxLength', 'charsetRule', 'charset', 'isEncrypted'),
    'textbox'   => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'defaultContent', 'fieldPrefix', 'description', 'isRequired', 'isUnique', 'minLength', 'maxLength', 'fieldHeight', 'autoFormat', 'isEncrypted'),
    'date'      => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'fieldPrefix', 'description', 'isRequired', 'isUnique', 'defaultDate', 'defaultDateString', 'showTime', 'showSeconds', 'use24HourFormat', 'yearRangeStart', 'yearRangeEnd', 'isEncrypted'),
    'list'      => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'defaultValue', 'fieldPrefix', 'description', 'isRequired', 'isUnique', 'listType', 'optionsType', 'optionsText', 'optionsTablename', 'optionsValueField', 'optionsLabelField', 'optionsQuery', 'filterField', 'isEncrypted'),
    'checkbox'  => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'fieldPrefix', 'checkedByDefault', 'description', 'checkedValue', 'uncheckedValue', 'isEncrypted'),
    'upload'    => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'fieldPrefix', 'description', 'isRequired',
                         'allowedExtensions', 'checkMaxUploadSize', 'maxUploadSizeKB',
                         'checkMaxUploads', 'maxUploads',
                         'resizeOversizedImages', 'maxImageHeight', 'maxImageWidth',
                         'createThumbnails',  'maxThumbnailHeight',  'maxThumbnailWidth',  'cropThumbnails',
                         'createThumbnails2', 'maxThumbnailHeight2', 'maxThumbnailWidth2', 'cropThumbnails2',
                         'createThumbnails3', 'maxThumbnailHeight3', 'maxThumbnailWidth3', 'cropThumbnails3',
                         'createThumbnails4', 'maxThumbnailHeight4', 'maxThumbnailWidth4', 'cropThumbnails4',
                         'useCustomUploadDir', 'customUploadDir', 'customUploadUrl',
                         'infoField1', 'infoField2', 'infoField3', 'infoField4', 'infoField5'),
    'wysiwyg'   => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed', 'fieldPrefix', 'description', 'defaultContent', 'allowUploads', 'isRequired', 'isUnique', 'minLength', 'maxLength', 'fieldHeight',
                         'allowedExtensions', 'checkMaxUploadSize', 'maxUploadSizeKB',
                         'checkMaxUploads', 'maxUploads',
                         'resizeOversizedImages', 'maxImageHeight', 'maxImageWidth',
                         'createThumbnails', 'maxThumbnailHeight', 'maxThumbnailWidth',    'cropThumbnails',
                         'createThumbnails2', 'maxThumbnailHeight2', 'maxThumbnailWidth2', 'cropThumbnails2',
                         'createThumbnails3', 'maxThumbnailHeight3', 'maxThumbnailWidth3', 'cropThumbnails3',
                         'createThumbnails4', 'maxThumbnailHeight4', 'maxThumbnailWidth4', 'cropThumbnails4',
                         'useCustomUploadDir', 'customUploadDir', 'customUploadUrl'
                       , 'isEncrypted'),
    'separator' => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'separatorType', 'separatorHeader', 'separatorHTML', 'isCollapsible', 'isCollapsed'),
    'tabGroup' => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField'),
    'hidden' => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField','defaultValue'),

    /* Advanced Fields */

    'relatedRecords' => array('order', 'label', 'type', 'relatedTable', 'relatedLimit', 'relatedView', 'relatedModify', 'relatedErase', 'relatedCreate', 'relatedWhere', 'relatedMoreLink', 'isSystemField', 'adminOnly', 'myAccountField'),

    'parentCategory' => array('customColumnType', 'order', 'label', 'type', 'isSystemField', 'adminOnly', 'myAccountField', 'indexed'),
  );
  
  if (!in_array($_REQUEST['type'], array_keys($fieldAttributesByType))){
    die("Save not supported for '{$_REQUEST['type']}'");
  }
  
  foreach ($fieldAttributesByType[$_REQUEST['type']] as $name) {
    $value     = array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $GLOBALS['defaultValue'][$name]; // use default value if no form value defined (such as with quick add)
    $skipField = empty($value) && in_array($name, $fieldsIgnoredIfEmpty); // don't record empty values for these fields
    if ($skipField) { unset( $schema[$newColumnName][$name] ); } // unset skipFields when they're blank
    else            { $schema[$newColumnName][$name] = $value; }
  }

  //Update the fields orders to ensure they're all integers and have the correct value
  uasort($schema, '__sortSchemaFieldsByOrder');
  $order = 1;
  foreach($schema as $key => $field) {
    if(is_array($field)) {
      $schema[$key]['order'] = $order;
      $order++;
    }
  }

  // save field schema
  saveSchema($_REQUEST['tableName'], $schema);
}





//
function _updateMySQL() {
  global $TABLE_PREFIX, $schema, $SETTINGS;
  $escapedTableName = mysql_escape($_REQUEST['tableName']);
  
  // get current column name and type
  $oldColumnName = $_REQUEST['fieldname'];
  $newColumnName = $_REQUEST['newFieldname'];
  $oldColumnType = getMysqlColumnType($_REQUEST['tableName'], $oldColumnName);
  $newColumnType = getColumnTypeFor($newColumnName, $_REQUEST['type'], @$_REQUEST['customColumnType']);

  // create/alter/remove MySQL columns
  $isOldColumn       = $oldColumnType;
  $isNewColumn       = ($newColumnType != 'none' && $newColumnType != '');
  $doEraseColumn     = $isOldColumn    && !$isNewColumn;
  $doCreateColumn    = !$oldColumnType && $isNewColumn;
  $doAlterColumn     = $isOldColumn    && $isNewColumn;
  
  // error check for adding a new field with field type 'none'
  // ... we don't create a table column if the field type is 'none'
  // ... so we need to check if the fieldname is already in the schema to check if it already exist
  // ... instead of letting the mysql query to die with duplicate column name error
  $isFieldTypeNoneAndInSchema = $_REQUEST['type'] == 'none' && array_key_exists($newColumnName, $schema);
  if (!$isOldColumn && $isFieldTypeNoneAndInSchema) {
    // die to prevent existing field with the same fieldname to get overwritten
    dieAsCaller("Fieldname '$newColumnName' already exists.");
  }
  
  // remove existing index (if any) - always dropping/recreating indexes ensure they match renamed fields, etc
  list($oldIndexName, $oldIndexColList) = getIndexNameAndColumnListForField($oldColumnName, $oldColumnType);
  $indexExists = (bool) mysql_get_query("SHOW INDEX FROM `$escapedTableName` WHERE Key_name = '$oldIndexName'");
  if ($indexExists) {
    mysqli()->query("DROP INDEX `$oldIndexName` ON `$escapedTableName`")
    or dieAsCaller("Error dropping index `$newIndexName`\n\n". htmlencode(mysqli()->error));
  }

  // update table: create, alter, or erase field
  if ($doCreateColumn) {   // create field
    $tableInfo = mysql_get_query("SHOW TABLE STATUS WHERE Name = '" . $escapedTableName . "' ");
    if($tableInfo['Engine'] == 'InnoDB') {
      if(mysql_getRemainingInnoDBRowSize($_REQUEST['tableName']) < 40) {
        die("There was an error creating the MySQL Column.  This table can't support any more fields.");  
      }
    }
    
    $query  = "ALTER TABLE `".mysql_escape($_REQUEST['tableName'])."`
                              ADD COLUMN  `".mysql_escape($newColumnName)."` $newColumnType";
    
    $result = mysqli()->query($query)
              or dieAsCaller("There was an error creating the MySQL Column, the error was:\n\n". htmlencode(mysqli()->error));
              
    // do we have a default value that should be updated?
    if (!empty( $_REQUEST['defaultUpdateExisting'] )) {
      
      // find the default value
      $defaultValue = @$_REQUEST['defaultValue'] ?: @$_REQUEST['defaultContent'] ?: @$_REQUEST['checkedByDefault'] ?: @$_REQUEST['defaultDate'];
      
      // determine default date
      if ($_REQUEST['type'] === 'date') {
        $defaultDate       = $_REQUEST['defaultDate'] ?? false;
        $defaultDateString = $_REQUEST['defaultDateString'] ?? false;
        $format            = "Y-m-d H:i:s";
        
        if     (empty($defaultDate)) { $defaultValue = date($format); }
        elseif ($defaultDate == 'custom') {
          if (!empty($defaultDateString)) { 
            $defaultValue = @date($format, strtotime($defaultDateString)); 
          }
        }
        elseif ($defaultDate == 'none') {
          $defaultValue = '';
        }
        
      }
      
      if (!empty( $defaultValue )) { 
        $updateQuery  = "UPDATE `".mysql_escape($_REQUEST['tableName'])."`
                         SET    `".mysql_escape($newColumnName)."` = '" . mysql_escape($defaultValue) . "'";
        
        $result = mysqli()->query($updateQuery)
                or dieAsCaller("There was an error adding the default value, the error was:\n\n". htmlencode(mysqli()->error));
      }
    }
              
  }
  else if ($doAlterColumn) {    // change field type
    $result = mysqli()->query("ALTER TABLE `".mysql_escape($_REQUEST['tableName'])."`
                         CHANGE COLUMN `".mysql_escape($oldColumnName)."`
                                       `".mysql_escape($newColumnName)."` $newColumnType")
              or dieAsCaller("There was an error changing the MySQL Column, the error was:\n\n". htmlencode(mysqli()->error));
  }
  else if ($doEraseColumn) {    // erase mysql field
    $query  = "ALTER TABLE `".mysql_escape($_REQUEST['tableName'])."`
               DROP COLUMN `".mysql_escape($oldColumnName)."`";
    $result = mysqli()->query($query)
              or dieAsCaller("There was an error removing the MySQL Column, the error was:\n\n". htmlencode(mysqli()->error));
  }
  
  // add/re-create index if required
  if (@$_REQUEST['indexed']) {
    list($newIndexName, $newIndexColList) = getIndexNameAndColumnListForField($newColumnName, $newColumnType);
    $result = mysqli()->query("CREATE INDEX `$newIndexName` ON `$escapedTableName` $newIndexColList")
              or dieAsCaller("Error creating index `$newIndexName`:\n\n". htmlencode(mysqli()->error));
  }

  // update uploads table (rename upload field if it was changed)
  $uploadFieldRenamed = $_REQUEST['type'] == 'upload' && $oldColumnName && $oldColumnName != $newColumnName;
  if ($uploadFieldRenamed) {
    $tableNameWithoutPrefix = getTableNameWithoutPrefix($_REQUEST['tableName']);
    $query  = "UPDATE `{$TABLE_PREFIX}uploads`";
    $query .= "   SET fieldName='".mysql_escape($newColumnName)."'";
    $query .= " WHERE fieldName='".mysql_escape($oldColumnName)."' AND";
    $query .= "       tableName='".mysql_escape($tableNameWithoutPrefix)."'";
    mysqli()->query($query)
    or dieAsCaller("There was an error updating the uploads database:\n\n". htmlencode(mysqli()->error));
  }
}

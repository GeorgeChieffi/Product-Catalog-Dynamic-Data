<?php

// accepted options: isRelatedRecords, tableName, where
function list_functions_init($options = []) {
  global $CURRENT_USER,$isRelatedRecords;
  // set defaults
  $isRelatedRecords = @$options['isRelatedRecords'];
  $tableName      = @$options['tableName']      ? $options['tableName']              : $GLOBALS['tableName'];
  $schema         = @$options['tableName']      ? loadSchema(@$options['tableName']) : $GLOBALS['schema'];
  $accessWhere    = @$options['where']          ? $options['where']                  : 'true';
  $perPage        = @$options['perPage'];

  // handle ajax call to update showAdvancedSearch in session
  if (@$_REQUEST['_updateShowAdvancedSearch']) {
    $_SESSION['lastRequest'][$tableName]['showAdvancedSearch'] = (@$_REQUEST['value'] === 'true');
    echo json_encode(['success' => 1]);
    exit;
  }

  // Perform search

  // if the search form was submitted, we need to reset page=1
  if (@$_REQUEST['_defaultAction']) {
    $_REQUEST['page'] = 1;
  }

  // Reset Search (and clear saved editor state)
  if (@$_REQUEST['_resetSearch'] || @$_REQUEST['_resetSavedSearch']) { // clear last state and request on resetSearch.  _resetSavedSearch is for custom links where you don't want previously saved search info to interfere
    $_SESSION['lastRequest'][$tableName] = array( // don't reset these values
      'showAdvancedSearch' => @$_SESSION['lastRequest'][$tableName]['showAdvancedSearch'],
      'perPage'            => @$_SESSION['lastRequest'][$tableName]['perPage'],
      'page'               => 1,
    );
  }
  if (@$_REQUEST['_resetSearch']) { // clear last state and request on resetSearch
    $_REQUEST = array( // don't reset these values
      'menu'    => @$_REQUEST['menu'],
      'perPage' => @$_SESSION['lastRequest'][$tableName]['perPage'], // pull value from the session variable
      'page'    => 1,
    );
  }

  // Load last _REQUEST from _SESSION (current _REQUEST values override old ones)
  if (@$_SESSION['lastRequest'][$tableName] && !$isRelatedRecords && !@$_REQUEST['_ignoreSavedSearch']) {
    $sortByField        = @$_SESSION['lastRequest'][$tableName]['sortBy'];
    $invalidSortByField = $sortByField && !@$schema[$sortByField];
    if ($invalidSortByField) { unset($_SESSION['lastRequest'][$tableName]['sortBy']); } // v2.52 remove invalid sort by fields
    $_REQUEST += $_SESSION['lastRequest'][$tableName];
  };

  // get user where (to limit to records user has access to)
  $showAllRecords = false;
  if     (!@$schema['createdByUserNum'])        { $showAllRecords = true; } // can't filter by user if no createdByUserNum field exists
  elseif ($GLOBALS['hasEditorAccess'])          { $showAllRecords = true; } // editors can see all records
  elseif ($GLOBALS['hasViewerAccessOnly'])      { $showAllRecords = true; } // viewers can see all records
  elseif ($GLOBALS['hasAuthorViewerAccess'])    { $showAllRecords = true; } // viewers can see all records

  if (!$showAllRecords) {
    $accessWhere = "($accessWhere) AND `createdByUserNum` = '{$CURRENT_USER['num']}'";
  }
  if ($tableName == 'accounts' && !@$CURRENT_USER['isAdmin']) { $accessWhere = "($accessWhere) AND `isAdmin` = '0'"; }

  // get ORDER BY
  $orderBy = isset($schema['listPageOrder']) ? $schema['listPageOrder'] : '';
  if (@$_REQUEST['sortBy']) {
    if (!@$schema[$_REQUEST['sortBy']]) { die("Can't sortBy '" .htmlencode($_REQUEST['sortBy']). "'.  Not a valid field!"); }
    $orderBy = "`{$_REQUEST['sortBy']}` ";
    if (@$_REQUEST['sortDir'] == 'desc') { $orderBy .= " DESC"; }
  }

  // $accessWhere -  This is for access control, records filtered out here aren't included in the record count (Total Record: 123)
  $accessWhere = applyFilters('list_where',          $accessWhere, $tableName); // This is for searching, records filtered out here _are_ included in the record count (Total Record: 123)
  $accessWhere = applyFilters('record_access_where', $accessWhere, $tableName); // same as above, but this filter is also called in _displayRecordAccessErrors()
  $orderBy     = applyFilters('list_orderBy',        $orderBy,     $tableName); // This is for modifying the orderBy option
  $searchWhere = $accessWhere;
  $listFields  = getSchemaListPageFields($schema);
  $loadUploads = listPageFieldsHaveUploadField($listFields, $schema);

  // load records
  list($records, $metaData) = getRecords(array(
    'tableName'               => $tableName,
    'perPage'                 => $isRelatedRecords ? $perPage : ($_REQUEST['perPage'] ?? @$schema['_perPageDefault'] ?? 25),
    'pageNum'                 => $isRelatedRecords ? 1        : intval(@$_REQUEST['page']),
    'orderBy'                 => $orderBy,
    'where'                   => $searchWhere,
    'loadUploads'             => $loadUploads,

    'allowSearch'             => $isRelatedRecords ? false : true,
    'requireSearchSuffix'     => 'true',

    'ignoreHidden'            => true,
    'ignorePublishDate'       => true,
    'ignoreRemoveDate'        => true,
    'includeDisabledAccounts' => true,

    'loadPseudoFields'        => false,
    'addSelectExpr'           => applyFilters('list_addSelectExpr', '', $tableName),
    'having'                  => applyFilters('list_having', '', $tableName),

    //'debugSql'          => true,
  ));
  

  $metaData['totalMatches'] = $metaData['totalRecords'];
  $metaData['totalRecords'] = mysql_count($tableName, $accessWhere);

  // save _REQUEST to _SESSION (this is how we maintain state when user returns to list page)
  if (!$isRelatedRecords) {
    $skipFields = array('menu','action');
    foreach ($_REQUEST as $key => $value) { // save all submitted values
      if (preg_match('/^_/', $key)) { continue; }    // skip program command fields: _defaultAction, _advancedAction, etc
      if (in_array($key, $skipFields)) { continue; } //
      $_SESSION['lastRequest'][$tableName][$key] = $value;
    }
    $_SESSION['lastRequest'][$tableName]['page']    = $metaData['page'];     // override page with calculated actual page from getRecords()
    $_SESSION['lastRequest'][$tableName]['perPage'] = $metaData['perPage'];  // override perPage with actual perPage from getRecords()
  }

  return array($listFields, $records, $metaData);
}

//This will return an array of the fields displayed on the list page
function getSchemaListPageFields($schema) {
  $listFieldsCSV = isset($schema['listPageFields']) ? $schema['listPageFields'] : '';
  $listFields    = preg_split("/\s*,\s*/", $listFieldsCSV);  // fields to show on list page
  $listFields    = array_filter($listFields); // remove empty elements
  return $listFields;
}

//Checks if the schema list page fields contain an upload.
function listPageFieldsHaveUploadField($listFields, $schema) {
  foreach($listFields as $field) {
    if( isset($schema[$field]['type']) && $schema[$field]['type'] == 'upload') { return true; }
  }
  return false;
}

//
function showListTable($listFields, $records, $options = []) {
  global $tableName, $schema;
  
  $schema  = applyFilters('list_showListSchema', $schema, $tableName, $options);
  $records = applyFilters('list_showListRecords',$records, $tableName, $schema, $options);

?>
  <input type='hidden' name='_tableName' class='_tableName' value='<?php echo htmlencode($tableName) ?>'>
  <div class="horizontal-autoscroll">
    <table class="data sortable table table-striped table-hover" data-table="<?php echo htmlencode($tableName) ?>">
      <thead>
        <tr class="nodrag nodrop">
          <?php displayColumnHeaders($listFields, @$options['isRelatedRecords']); ?>
        </tr>
      </thead>
      <tbody>
        <?php
          foreach ($records as $record) {
            $record  = applyFilters('listRow_record', $record, $tableName);
            $trStyle = applyFilters('listRow_trStyle', '', $tableName, $record);
            
            $trClass = 'draggable droppable';
            if (@$schema['menuType'] == 'category') {  // v2.60 add CSS classes with category data for filtering categories with jquery.  
              $trClass .= ' category_row';
              $trClass .= ' category_num_'    . $record['num'];
              $trClass .= ' category_parent_' . $record['parentNum'];
              $trClass .= ' category_depth_'  . $record['depth'];
              $trClass .= ' category_lineage' . str_replace(':','_',$record['lineage']); // eg: lineage_6_13_14_
            }
            $trClass = applyFilters('listRow_trClass', $trClass, $tableName, $record); // v2.60
            
            ob_start();
            print "<tr class='$trClass' style='$trStyle'>\n";
            displayListColumns($listFields, $record, $options);
            print "</tr>\n";
            $listRow_html = ob_get_clean();
            echo applyFilters('listRow_html', $listRow_html, $listFields, $record, $options, $tableName);
          }
        ?>
        <?php if (count($records) == 0):
          $listFieldCount = count($listFields) + 3; // for checkbox, modify, and erase
          if (@$schema['menuType'] == 'category') { $listFieldCount++; } // for extra order field
        ?>
          <tr>
           <td class="listRowNoResults" colspan="<?php echo $listFieldCount ?>">
           <?php if (!@$_REQUEST['search']): ?>  <?php et('Sorry, no records were found!') ?>  <?php endif ?>
           <?php if (@$_REQUEST['search']): ?>  <?php et('Sorry, the <b>search</b> returned no results!') ?> <?php endif ?>
           </td>
          </tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
<?php
}

// redirect authors with single record limit to edit page
function redirectSingleRecordAuthorsToEditPage() {
  global $CURRENT_USER, $hasEditorAccess, $hasAuthorAccess, $hasAuthorViewerAccess, $schema, $tableName, $escapedTableName;

  $isAuthorOnly         = !$CURRENT_USER['isAdmin'] && !$hasEditorAccess && !$hasAuthorViewerAccess && $hasAuthorAccess;
  $onlyAllowedOneRecord = (@$schema['_maxRecordsPerUser'] == 1 || @$CURRENT_USER['accessList'][$tableName]['maxRecords'] == 1);

  if ($isAuthorOnly && $onlyAllowedOneRecord) {
    $query = "SELECT * FROM `$escapedTableName` WHERE createdByUserNum = '{$CURRENT_USER['num']}' LIMIT 1";
    $record = mysql_get_query($query);

    $_REQUEST['num'] = $record['num'];  // fake the record num being requested
    showInterface('default/edit.php');
  }

}

// 
function getSearchField($searchRow, $isPrimarySearchField = false) {
  global $schema, $tableName;
  
  if (!$searchRow){ return; }
  
  list($label, $fieldnames, $displayAs, $searchFieldSuffix) = $searchRow;
  
  // primary-field related variables
  $extraClasses  = $isPrimarySearchField ? ' input-lg' : '';
  $requiresLabel = true;
  
  $isMulti = false;
  if (count($fieldnames) === 1 && preg_match('/^\w+\[\]$/', $fieldnames[0])) {
    $fieldnames[0] = trim(@$fieldnames[0], '[]');
    $isMulti = true;
  }
  
  $fieldsAsCSV = join(',', $fieldnames);
  $name        = "{$fieldsAsCSV}_$searchFieldSuffix";
  $lastValue   = @$_REQUEST[$name];

  // get fieldschema for single field searches:
  $fieldSchema   = @$schema[ $fieldnames[0] ];
  if ($fieldSchema) { $fieldSchema['name'] = $fieldnames[0]; }

  // special case: add createdBy.fieldname search functionality
  if (preg_match("/^createdBy\.(.*?)$/i", $fieldsAsCSV, $matches)) {
    $name        = 'createdByUserNum_match';
    $labelField  = $matches[1];
    $lastValue   = @$_REQUEST[$name];
    $displayAs   = 'dropdown';
    $fieldSchema = array(
      'name'              => '_not_used_',
      'optionsType'       => 'table',
      'optionsTablename'  => 'accounts',
      'optionsValueField' => 'num',
      'optionsLabelField' => $matches[1],
    );
    
    // list all users who have records in this section
    $fieldSchema = array(
      'name'              => '_not_used_',
      'optionsType'       => 'query',
      'optionsQuery'      => "SELECT a.num, a.`$labelField`
                                FROM {$GLOBALS['TABLE_PREFIX']}accounts a
                                JOIN `{$GLOBALS['TABLE_PREFIX']}$tableName` f ON a.num = f.createdByUserNum
                            ORDER BY a.`$labelField`",
    );
    
    /*
      // list all users who have access to create records in this section
      $fieldSchema = array(
        'name'              => '_not_used_',
        'optionsType'       => 'query',
        'optionsQuery'      => "SELECT a.num, a.`$labelField`
                                  FROM {$GLOBALS['TABLE_PREFIX']}accounts a
                                  JOIN `{$GLOBALS['TABLE_PREFIX']}_accesslist` al ON a.num = al.userNum
                                 WHERE al.accessLevel > 1 AND al.tableName IN ('all','$tableName')
                              ORDER BY a.`$labelField`",
      );
    */
  }

  // generate html
  $html = '';
  if ($displayAs == 'textfield') {
    $extraAttrs    =  $isPrimarySearchField ? ' placeholder="'.htmlencode($label).'"' : '';
    $html          .= "<input class='text-input form-control$extraClasses' type='text' name='$name' value='" .htmlencode($lastValue). "'$extraAttrs>";
    $requiresLabel =  false;
  }

  else if ($displayAs == 'checkbox') {
    $checkedValue   = $fieldSchema['checkedValue']   ?? t('Checked');
    $uncheckedValue = $fieldSchema['uncheckedValue'] ?? t('Unchecked');

    $optionValues   = array('1', '0');
    $optionLabels   = array($checkedValue, $uncheckedValue);
    $optionsHTML    = getSelectOptions($lastValue, $optionValues, $optionLabels, false);
    $html        .= "<select name='$name' class='form-control$extraClasses'>";
    $html        .= "<option value=''>" .htmlencode(t("<any>")). "</option>\n";
    $html        .= $optionsHTML;
    $html        .= "</select>";
  }
  else if ($displayAs == 'dropdown') {
    $optionsHTML  = getSelectOptionsFromSchema($lastValue, $fieldSchema, false);
    if ($isMulti) { $html .= "<select name='{$name}[]' multiple class='form-control$extraClasses'>"; }
    else          { $html .= "<select name='$name' class='form-control$extraClasses'>"; }
    $html        .= "<option value=''>" .htmlencode(t("<any>")). "</option>\n";
    $html        .= $optionsHTML;
    $html        .= "</select>";
  }
  else if ($displayAs == 'custom') {
    $functionName = $searchFieldSuffix;
    if (!function_exists($functionName)) { die("Search function '" . htmlencode($functionName). "' doesn't exist!<br>Check: Admin &gt; Section Editors &gt; Search &gt; Search Fields"); }
    $html        .= call_user_func($functionName);
    if (!$html) { return ''; } // return nothing from function to not display search field
  }
  else { die("Unknown search field type '" .htmlencode($displayAs). "'!"); }

  // add description - displayed after the field
  $description = '';
  // .. date field format and datepicker button
  if (@$fieldSchema['type'] == 'date' && count($fieldnames) == 1) {
    
    // custom datepicker code - this is only called is jqueryUI datepicker is loaded
    $jsDateFormat   = @$fieldSchema['showTime'] ? "yy-mm-dd 00:00:00" : "yy-mm-dd";
    $buttonImageUrl = CMS_ASSETS_URL . "/3rdParty/jqueryUI/calendar.gif"; 
    $description   .= <<<__HTML__
      <input type="hidden" name="{$name}_datepicker">
      <script>
        $(function() {
          if ($.datepicker != undefined) {
            $('[name={$name}_datepicker]').datepicker({
              showOn: 'button',
              buttonImage: '$buttonImageUrl',
              buttonImageOnly: true,
              buttonText: '',
              dateFormat: '$jsDateFormat',
              onClose: function(date) { // pass the value to the real date field
                $('[name={$name}').val(date);
              }
            });
          }
        });
      </script>
__HTML__;

    $description .= '&nbsp;';
    $description .= t('Format:');
    $description .= ' YYYY-MM-DD ';
    if (@$fieldSchema['showTime']) { $description .= ' HH:MM:SS '; }
  }
  
  return [ 'html' => $html, 'description' => $description, 'requiresLabel' => $requiresLabel, 'label' => $label ];
}

// $searchRows = _parseSearchFieldsFormat( $schema['listPageSearchFields'] );
// parse the format entered in $schema['listPageSearchFields'] and return as array of
// arrays with (label, fieldNames, searchFieldType)
function _parseSearchFieldsFormat($searchFieldsFormat) {
  global $schema;

  // Example search formats:

  // fieldsAsCSV                                     // search query on fieldsAsCSV fields
  // label|fieldsAsCSV                               // as above but with label displayed in front of search fields
  // label|fieldsAsCSV|searchFieldSuffix             // as above but using searchType defined by searchFieldSuffix, eg: match, query, etc (see search docs)
  // label|fieldsAsCSV|searchFieldSuffix|displayedAs // (undocumented) as above but using searchType defined by searchFieldSuffix, eg: match, query, etc (see search docs)
  // label||custom|functionName                      // (undocumented) display label and call custom function functionName() to generate search input field.

  // NOTE: If "_all_" is found in fieldsAsCSV then all fields are searched

  //
  $searchRows = [];
  $rowFormats = preg_split("/\r?\n/", $searchFieldsFormat, -1, PREG_SPLIT_NO_EMPTY); // 2.17 remove \r chars if they're specified
  foreach ($rowFormats as $rowFormat) { // format is: field, field, field _OR_ label|fieldList
    $values = preg_split("/\|/", $rowFormat);
    if (count($values) <= 1) { @list($label, $fieldsAsCSV, $searchType, $displayAs) = array( t('Search'), $values[0], 'query', ''); }
    else                     { @list($label, $fieldsAsCSV, $searchType, $displayAs) = $values; }

    if (preg_match("/\b_all_\b/i", $fieldsAsCSV)) { $fieldsAsCSV = __getAllSearchableFieldsAsCSV($schema); }

    $fieldsAsCSV = preg_replace('/\s*/', '', $fieldsAsCSV);
    $fieldnames  = preg_split("/,/", $fieldsAsCSV, -1, PREG_SPLIT_NO_EMPTY);

    // figure out "displayAs" search field type
    if (!$displayAs) {
      $displayAs = 'textfield'; // default search field type

      if (count($fieldnames) == 1 && in_array($searchType, ['', 'match'])) { // if single field
        $fieldName   = $fieldnames[0];
        $fieldSchema = @$schema[ trim($fieldName, '[]') ];
        if      ($fieldName           == 'hidden')   { $displayAs = 'checkbox'; }
        else if (!empty($fieldSchema['type']) && $fieldSchema['type'] == 'checkbox') { $displayAs = 'checkbox'; }
        else if (!empty($fieldSchema['type']) && $fieldSchema['type'] == 'list')     { $displayAs = 'dropdown'; }
      }
    }

    //
    if (!$searchType) { $searchType = 'query'; }
    $searchRows[] = array($label, $fieldnames, $displayAs, $searchType);
  }

  //
  return $searchRows;
}

// get schema fieldlist for _all_ fields wildcard
function __getAllSearchableFieldsAsCSV(&$schema) {

  $allSearchableFieldsAsCSV = '';
  $skippedFieldTypes        = array('','upload','separator','relatedRecords','none','accessList','tabGroup');

  foreach ($schema as $name => $fieldSchema) {
    if (!is_array($fieldSchema)) { continue; }  // only fields have arrays as values, other values are table metadata
    if (in_array(@$fieldSchema['type'], $skippedFieldTypes)) { continue; }
    $allSearchableFieldsAsCSV .= "$name,";
  }
  $allSearchableFieldsAsCSV = chop($allSearchableFieldsAsCSV, ',');

  //
  return $allSearchableFieldsAsCSV;
}


//
function displayColumnHeaders($listFields, $isRelatedRecords = false) {
  global $schema, $ACCOUNT_SCHEMA, $tableName;

  // checkboxes - for "Advanced Commands" pulldown
  if (!$isRelatedRecords) {
    print "<th class='no-sort text-center' style='width:1px'>";
    if (@$schema['num']) {
      $html = "<input type='checkbox' name='null' value='1' onclick='toggleCheckboxes(this.checked)'>";
      $html = applyFilters('listHeader_checkAll', $html, $tableName);
      print $html;
    }
    print "</th>\n";
  }

  // category sections - add up/down sorting links and drag column
  if (!$isRelatedRecords) {
    $showCategorySort = (@$schema['menuType'] == 'category');
      
    $hasAuthorAccessOnly       = (userSectionAccess($tableName) == 7);
    $hasAuthorViewerAccessOnly = (userSectionAccess($tableName) == 6);
    $hasViewerAccessOnly       = (userSectionAccess($tableName) == 3);
  
    if (!$hasAuthorViewerAccessOnly && !$hasAuthorAccessOnly && !$hasViewerAccessOnly && $showCategorySort) {
      print "<th class='text-center hidden-xs' style='width:40px'>" .t('Drag'). "</th>\n";
    }
  }

  // show column headers
  foreach ($listFields as $fieldnameWithSuffix) {
    @list($fieldname,$suffix) = explode(":", $fieldnameWithSuffix); // to support fieldname:label
    if (empty($fieldname)) { continue; }

    $thAttrs = '';
    $label   = _getFieldLabel($fieldname);

    // drag sort fields
    if ($fieldname == 'dragSortOrder') {
      $hasViewerAccessOnly = (userSectionAccess($tableName) == 3);
      if ($isRelatedRecords && !@$GLOBALS['SETTINGS']['advanced']['allowRelatedRecordsDragSorting']) { continue; }
      if (!userHasFieldAccess($schema[$fieldname])) { continue; } // skip fields that the user has no access to
      if ($hasViewerAccessOnly) { continue; }
      $thAttrs = "style='width:40px;' class='no-sort text-center hidden-xs'";
      $label = t('Drag');
    }

    else {

      // create sort links
      $sortingArrow    = '';
      $isSortableField = @$schema[$fieldname] && @$schema[$fieldname]['type'] != 'upload'; // don't allow sorting on upload or createBy.* fields
      if ($isSortableField && !$isRelatedRecords) {
        $nextDir   = 'asc';
        $sortField = $fieldname;
        if (@$_REQUEST['sortBy'] == $fieldname) { // if sorting by field

          if      (@$_REQUEST['sortDir'] == 'asc')  { $nextDir = 'desc'; $sortGlyphClass = 'fa fa-caret-down';   }
          else if (@$_REQUEST['sortDir'] == 'desc') { $nextDir = '';     $sortGlyphClass = 'fa fa-caret-up'; $sortField = ''; }

          $sortingArrow = '<div style="float:right; font-size: 16px;"><span class="' . $sortGlyphClass . '" aria-hidden="true"></span></div>';
        }
        $thAttrs = "onclick='window.location=\"?menu=$tableName&amp;sortBy=$sortField&amp;sortDir=$nextDir\"'";
        $label   = "$sortingArrow<u style='cursor: pointer'>$label<!-- --></u>";
      }

    }

    //
    $label   = applyFilters('listHeader_displayLabel', $label, $tableName, $fieldname);
    $thAttrs = applyFilters('listHeader_thAttributes', $thAttrs, $tableName, $fieldname);

    // display all other fields
    print "<th $thAttrs>$label</th>\n";
  }

  //
  print "<th style='padding: 0px; text-align: center' class='no-sort'>" .t('Action'). "</th>\n";

}


// return field label for fieldname in format: articles.title, title, createdBy.username
function _getFieldLabel($fullFieldname) {
  @list($fieldname, $tableName) = array_reverse(explode('.', $fullFieldname));

  // get schema
  $schema = [];
  if  (!$tableName && $GLOBALS['schema']) {
    $schema = &$GLOBALS['schema'];
  }
  else {
    if ($tableName == 'createdBy') { $tableName = 'accounts'; } // workaround for legacy 'createdBy.fieldname' fieldnames
    $schema = loadSchema($tableName);
  }

  // get label
  $label = @$schema[$fieldname]['label'];
  return $label;
}

//
function displayListColumns($listFields, $record, $options = []) {
  global $CURRENT_USER, $tableName, $schema;

  $showView   = @$options['isRelatedRecords'] ? @$options['showView']   : !@$schema['_disableView'];
  $showModify = @$options['isRelatedRecords'] ? @$options['showModify'] : !@$schema['_disableModify'];
  $showErase  = @$options['isRelatedRecords'] ? @$options['showErase']  : !@$schema['_disableErase'];

  $hasAuthorViewerAccessOnly = (userSectionAccess($tableName) == 7);
  $hasAuthorAccessOnly       = (userSectionAccess($tableName) == 6);
  $hasViewerAccessOnly       = (userSectionAccess($tableName) == 3);

  // remove modify/erase for users with view only access -OR- with Author/Viewer access who don't own the record
  if ($hasViewerAccessOnly)       { $showModify = false; $showErase = false; }
  if ($hasAuthorViewerAccessOnly) {
    $showModify = $showModify && ($record['createdByUserNum'] && $record['createdByUserNum'] == $CURRENT_USER['num']);
    $showErase  = $showErase  && ($record['createdByUserNum'] && $record['createdByUserNum'] == $CURRENT_USER['num']);
  }

  // checkboxes - for "Advanced Commands" pulldown
  if (empty($options['isRelatedRecords'])) {
    print "<td>";
    if (!empty($schema['num'])) {
      print "<input type='checkbox' name='selectedRecords[]' value='{$record['num']}' class='selectRecordCheckbox'>";
    }
    print "</td>\n";
  }

  // category sections - add up/down sorting links and drag field
  if (!$hasAuthorViewerAccessOnly && !$hasViewerAccessOnly && !$hasAuthorAccessOnly && @$schema['menuType'] == 'category' && !@$options['isRelatedRecords']) {

    //
    $tableNameJsEncoded = jsencode($tableName);
    $upClick = "return redirectWithPost('?', {menu:'$tableNameJsEncoded', _action:'categoryMove', 'direction':'up', 'num':'{$record['num']}', '_CSRFToken': $('[name=_CSRFToken]').val()});";
    $dnClick = "return redirectWithPost('?', {menu:'$tableNameJsEncoded', _action:'categoryMove', 'direction':'down', 'num':'{$record['num']}', '_CSRFToken': $('[name=_CSRFToken]').val()});";
    
    //
    print "<td class='dragger hidden-xs'>";
    //print   "<img src='lib/images/drag.gif' height='6' width='19' title='" .t('Click and drag to change order.'). "' alt=''>";
    //print   "<a href='#' onclick=\"$upClick\"><!-- ".t('UP').' --></a>';
    //print   "<a href='#' onclick=\"$dnClick\"><!-- ".t('DN').' --></a>';
    print '<span class="fa fa-chevron-down" aria-hidden="true" title="' . t('Click and drag to change order.') . '"></span>';
    print '<span class="fa fa-chevron-up"   aria-hidden="true" title="' . t('Click and drag to change order.') . '"></span>';
    print "<input type='hidden' value='{$record["parentNum"]}' class='_categoryParent'>";
    print "</td>";
  }

  // display all other fields
  foreach ($listFields as $fieldnameWithSuffix) {
    @list($fieldname,$suffix) = explode(":", $fieldnameWithSuffix); // to support fieldname:label

    if ($fieldnameWithSuffix == 'dragSortOrder') {
      if (@$options['isRelatedRecords'] && !@$GLOBALS['SETTINGS']['advanced']['allowRelatedRecordsDragSorting']) { continue; }
      if ($hasViewerAccessOnly) { continue; }
      if (!userHasFieldAccess($schema[$fieldname])) { continue; } // skip fields that the user has no access to
    }

    list($displayValue, $tdAttributes) = _getColumnDisplayValueAndAttributes($fieldname, $record);
    $displayValue = applyFilters('listRow_displayValue', $displayValue, $tableName, $fieldname, $record);
    $tdAttributes = applyFilters('listRow_tdAttributes', $tdAttributes, $tableName, $fieldname, $record);
    print "<td $tdAttributes>$displayValue</td>\n";
  }

  ### display actions
  $actionLinks = '';

  // view
  $showView = applyFilters('listRow_showView', $showView, $tableName, $record);
  if ($showView) {
    $viewLink     = '?menu=' .htmlencode($tableName). "&amp;action=view&amp;num=" . @$record['num'];
    if (@$options['isRelatedRecords']) { $viewLink .= "&amp;returnUrl=". urlencode('?'.$_SERVER['QUERY_STRING']); }
    $actionLinks .= "<a href='$viewLink'>" .t('view'). "</a>\n";
  }

  // modify
  $showModify = applyFilters('listRow_showModify', $showModify, $tableName, $record);
  if ($showModify) {
    $modifyLink   = '?menu=' .htmlencode($tableName). "&amp;action=edit&amp;num=" . @$record['num'];
    if (@$options['isRelatedRecords']) { $modifyLink .= "&amp;returnUrl=". urlencode('?'.$_SERVER['QUERY_STRING']); }
    $actionLinks .= "<a href='$modifyLink'>" .t('modify'). "</a>\n";
  }

  // erase
  $showErase = applyFilters('listRow_showErase', $showErase, $tableName, $record);
  if ($showErase) {
    $returnArg = @$options['isRelatedRecords'] ? (',' . htmlencode(json_encode('?'.urlencode($_SERVER['QUERY_STRING'])))) : '';
    $disableErase = ($tableName == 'accounts' && $CURRENT_USER['num'] == $record['num']);
    $eraseLink    = "javascript:confirmEraseRecord('" .htmlencode($tableName). "','" .@$record['num']. "'$returnArg);";
    if ($disableErase)    { $actionLinks .= "<span class='disabled'>" .t('erase'). "</span>\n"; }
    else                  { $actionLinks .= "<a href=\"$eraseLink\">"   .t('erase'). "</a>\n"; }
  }

  //
  $actionLinks = applyFilters('listRow_actionLinks', $actionLinks, $tableName, $record);

  // show erase link
  print "<td class='listActions'>$actionLinks</td>";

}

//
function _getColumnDisplayValueAndAttributes($fieldname, &$record) {
  global $schema, $tableName;
  $fieldValue  = @$record[$fieldname];
  $fieldSchema = @$schema[ $fieldname ];
  if ($fieldSchema) { $fieldSchema['name'] = $fieldname; }

  // default display value and attribute
  if (!is_array($fieldValue)) { $fieldValue = htmlencode($fieldValue); }
  $displayValue = $fieldValue;

  $tdAttributes = "style='text-align:left'";

  // date fields
  $isSpecialDatefield = in_array($fieldname, array('createdDate', 'updatedDate'));
  if (@$fieldSchema['type'] == 'date' || $isSpecialDatefield) {
    
    $showSeconds = @$fieldSchema['showSeconds'];
    $showTime    = @$fieldSchema['showTime'];
    $use24Hour   = @$fieldSchema['use24HourFormat'];
    
    // settings for createdDate and updatedDate
    if ($isSpecialDatefield) {
      $showSeconds = true;
      $showTime    = true;
      $use24Hour   = true;
    }
    
    $secondsFormat = '';
    if ($showSeconds) { $secondsFormat = ':s'; }
    
    $timeFormat = '';
    if ($showTime) {
      if ($use24Hour) { $timeFormat = " - H:i$secondsFormat"; }
      else            { $timeFormat = " - h:i$secondsFormat A"; }
    }
    
    $dateFormat    = '';
    $dayMonthOrder = @$GLOBALS['SETTINGS']['dateFormat'];
    if     ($dayMonthOrder == 'dmy') { $dateFormat = "jS M, Y" . $timeFormat; }
    elseif ($dayMonthOrder == 'mdy') { $dateFormat = "M jS, Y" . $timeFormat; }
    else                             { $dateFormat = "Y-m-d"   . $timeFormat; }
    
    $displayValue = date($dateFormat, strtotime($fieldValue));
    if (!$fieldValue || $fieldValue == '0000-00-00 00:00:00') { $displayValue = ''; }
  }

  // dragSortOrder fields
  if ($fieldname == 'dragSortOrder') {
    if (!userHasFieldAccess($schema[$fieldname])) { return; } // skip fields that the user has no access to

    $tdAttributes  = "class='dragger hidden-xs'";
    $displayValue  = "<input type='hidden' name='_recordNum' value='{$record['num']}' class='_recordNum'>";
    $displayValue .= '<span class="fa fa-chevron-down" aria-hidden="true" title="' . t('Click and drag to change order.') . '"></span>';
    $displayValue .= '<span class="fa fa-chevron-up"   aria-hidden="true" title="' . t('Click and drag to change order.') . '"></span>';
  }

  // Category Section: name fields - pad category names to their depth
  $isCategorySection = @$schema['menuType'] == 'category' && $fieldname == 'name';
  if ($isCategorySection) {
    $depth = @$record["depth"];
    $parentNum = @$record["parentNum"];
    $displayValue = "";
    //$displayValue  = "<input type='hidden' name='_recordNum' value='{$record['num']}' class='_recordNum'>";
    //$displayValue .= "<input type='hidden' value='$fieldValue' class='_categoryName'>";
    //$displayValue .= "<input type='hidden' value='$depth' class='_categoryDepth'>";
      // $displayValue = "<input type='hidden' value='$parentNum' class='_categoryParent'>";
    //$displayValue .= "<img style='float:left' src='lib/images/drag.gif' height='6' width='19' class='dragHandle' title='" .
    //                t('Click and drag to change order.').
    //                "' alt=''>";
    if (@$record['depth']){
      $padding      = str_repeat("&nbsp; &nbsp; &nbsp;", @$record['depth']);
      $displayValue .= $padding . ' - ';
    }
    $displayValue .= $fieldValue;
  }

  // display first thumbnail for upload fields
  if (@$fieldSchema['type'] == 'upload') {
    $displayValue  = '';
    $upload = @$record[$fieldname][0];
    if ($upload) {
      ob_start();
      showUploadPreview($upload, 50);
      $displayValue = ob_get_clean();
    }
  }

  // display labels for list fields
  #if (@$fieldSchema['type'] == 'list' && $suffix == 'label') { // require ":label" field suffix in future to show labels, just do it automatic for now though.
  if (@$fieldSchema['type'] == 'list') {
    $displayValue = _getListOptionLabelByValue($fieldSchema, $record);
  }

  // display labels for checkboxes
  if (@$fieldSchema['type'] == 'checkbox') {
    if (@$fieldSchema['checkedValue'] || @$fieldSchema['uncheckedValue']) {
      $displayValue = $fieldValue ? @$fieldSchema['checkedValue'] : @$fieldSchema['uncheckedValue'];
    }
  }

  // v2.50 - display formatted textbox content
  if (@$fieldSchema['type'] == 'textbox') {
    if ($fieldSchema['autoFormat']) {
      $displayValue = @$record[$fieldname]; // overwrite previous htmlencoded value
      $displayValue = preg_replace("/<br\s*\/?>\r?\n/", "\n", $displayValue);  // remove autoformat break tags
      $displayValue = htmlencode($displayValue); // html encode content
    }
    $displayValue = nl2br($displayValue); // re-add break tags after nextlines
  }

  // return display value
  return array($displayValue, $tdAttributes);
}

//
function _getListOptionLabelByValue($fieldSchema, $record) {
  global $TABLE_PREFIX, $tableName;

  $fieldname  = $fieldSchema['name'];
  $fieldValue = @$record[ @$fieldname ];
  $output     = '';

  // build value to label map
  $listOptions = getListOptionsFromSchema($fieldSchema, $record);
  $valuesToLabels = [];
  foreach ($listOptions as $valueAndLabel) {
    list($value, $label) = $valueAndLabel;
    $valuesToLabels[$value] = $label;
  }

  // if this is a multi-value list field, look up each value and comma separate them
  if (@$fieldSchema['listType'] == 'checkboxes' || @$fieldSchema['listType'] == 'pulldownMulti') {
    $labels = [];
    foreach ( preg_split('/\t/', trim($fieldValue)) as $value ) {
      $labels[] = @$valuesToLabels[$value] ? $valuesToLabels[$value] : $value; // if lookup fails, use value
    }
    return join(', ', $labels);
  }
  // if this is a single-value list field, look up our single value
  else {
    return array_key_exists($fieldValue, $valuesToLabels) ? $valuesToLabels[$fieldValue] : $fieldValue; // if lookup fails, use value
  }
}

// echo getPaginationHTML($metaData, 'top');
// construct pagination HTML (or empty string if only one page exists)
function getPaginationHTML($metaData, $position = null) {
  if ($metaData['totalPages'] < 2) { return ''; }
  $nearbyRadius               = 3; // e.g. 1 => [ 1, ..., 4, (5), 6, ..., 9 ], 2 => [ 1, ..., 3, 4, (5), 6, 7, ..., 9 ]
  $showFirstLastAsPageNumbers = false;
  
  $containerStyle             = '';
  if ($position == 'top') {
    $containerStyle = 'style="margin: 0px auto 14px"';
  }
  elseif ($position == 'bottom') {
    $containerStyle = 'style="margin: 14px auto 0px"';
  }
  
  ob_start();
  ?>
    <div class="center" <?php echo $containerStyle ?>>
      <ul class="pagination" style="margin: 0">
        <?php
          $outputPaginationItem = function($pageNum, $class = '', $label = '', $pointer = false) {
            global $tableName;
            if ($label === '') { $label = $pageNum; }
            $attrs = $class ? ' class="' . $class . '"' : '';
            $url   = "?menu=" . htmlencode($tableName) . "&_action=list&page=" . $pageNum;
            ?><li<?php echo $attrs ?>><a href="<?php echo htmlencode($url) ?>"<?php echo $pointer ? ' style="cursor: pointer;"' : '' ?>><?php echo $label ?></a></li><?php
          };
          $outputEllipsisItem = function() use($metaData) {
            global $tableName;
            ?><li><a href="#" onclick="return jumpToPage(<?php echo $metaData['page'] ?>, <?php echo $metaData['totalPages'] ?>);" title="Go to page...">...</a></li><?php
          };
          $paginateMin = max($metaData['page'] - $nearbyRadius, 1);
          $paginateMax = min($metaData['page'] + $nearbyRadius, $metaData['totalPages']);
          // our rules imply the max number of links (5+2*$nearbyRadius), so now we extend the range for situations near the ends to always show the same number of links
          // example with $nearbyRadius === 1:
          // [ >1<,  2 ,  3 ,  4 ,  5 , ...,  9  ]
          // [  1 , >2<,  3 ,  4 ,  5 , ...,  9  ]
          // [  1 ,  2 , >3<,  4 ,  5 , ...,  9  ]
          // [  1 ,  2 ,  3 , >4<,  5 , ...,  9  ]
          // [  1 , ...,  4 , >5<,  6 , ...,  9  ]
          // [  1 , ...,  5 , >6<,  7 ,  8 ,  9  ]
          // [  1 , ...,  5 ,  6 , >7<,  8 ,  9  ]
          // [  1 , ...,  5 ,  6 ,  7 , >8<,  9  ]
          // [  1 , ...,  5 ,  6 ,  7 ,  8 , >9< ]
          if ($showFirstLastAsPageNumbers) {
            if ($metaData['page'] < $nearbyRadius + 4) { $paginateMax = min($metaData['totalPages'], $nearbyRadius * 2 + 3); }
            if ($metaData['page'] > $metaData['totalPages'] - $nearbyRadius - 3) { $paginateMin = max(1, $metaData['totalPages'] - $nearbyRadius * 2 - 2); }
          }
          // [ >1<,  2 ,  3 ,  4 , ... ]          // [ >1<,  2 ,  3 ,  4 ,  5 ,  6 , ... ]
          // [  1 , >2<,  3 ,  4 , ... ]          // [  1 , >2<,  3 ,  4 ,  5 ,  6 , ... ]
          // [  1 ,  2 , >3<,  4 , ... ]          // [  1 ,  2 , >3<,  4 ,  5 ,  6 , ... ]
          // [ ...,  3 , >4<,  5 , ... ]          // [  1 ,  2 ,  3 , >4<,  5 ,  6 , ... ]
          // [ ...,  4 , >5<,  6 , ... ]          // [ ...,  3 ,  4 , >5<,  6 ,  7 , ... ]
          // [ ...,  5 , >6<,  7 , ... ]          // [ ...,  4 ,  5 , >6<,  7 ,  8 ,  9  ]
          // [ ...,  6 , >7<,  8 ,  9  ]          // [ ...,  4 ,  5 ,  6 , >7<,  8 ,  9  ]
          // [ ...,  6 ,  7 , >8<,  9  ]          // [ ...,  4 ,  5 ,  6 ,  7 , >8<,  9  ]
          // [ ...,  6 ,  7 ,  8 , >9< ]          // [ ...,  4 ,  5 ,  6 ,  7 ,  8 , >9< ]
          else {
            if ($metaData['page'] < $nearbyRadius + 2) { $paginateMax = min($metaData['totalPages'], $nearbyRadius * 2 + 2); }
            if ($metaData['page'] > $metaData['totalPages'] - $nearbyRadius - 2) { $paginateMin = max(1, $metaData['totalPages'] - $nearbyRadius * 2 - 1); }
          }
          // avoid showing silly contractions such as "[1] [...] [2]" or even "[1] [...] [3]" (and the analogs on the other end of the number range)
          if ($showFirstLastAsPageNumbers) {
            if ($paginateMin <= 3)                           { $paginateMin = 1; }
            if ($paginateMax >= $metaData['totalPages'] - 2) { $paginateMax = $metaData['totalPages']; }
          }
          // avoid showing silly contractions such as "[...] [2]" (and the analog on the other end of the number range)
          else {
            if ($paginateMin <= 2)                           { $paginateMin = 1; }
            if ($paginateMax >= $metaData['totalPages'] - 1) { $paginateMax = $metaData['totalPages']; }
          }
          
          // show first page link
          if (!$showFirstLastAsPageNumbers) {
            $outputPaginationItem(1, 'text-muted firstLast', t('first'));
          }
          // show prev page link
          if ($metaData['page'] > 1) {
            $outputPaginationItem($metaData['page'] - 1, 'text-muted', t('prev'), true);
          }
          else {
            $outputPaginationItem($metaData['page'], 'text-muted', t('prev'));
          }
          // show first page link
          if ($paginateMin > 1) {
            if ($showFirstLastAsPageNumbers) {
              $outputPaginationItem(1);
            }
            $outputEllipsisItem();
          }
          // show nearby (and current) pages links
          foreach (range($paginateMin, $paginateMax) as $pageNum) {
            $class = $pageNum == $metaData['page'] ? 'active' : '';
            $outputPaginationItem($pageNum, $class);
          }
          // show last page link
          if ($paginateMax < $metaData['totalPages']) {
            $outputEllipsisItem();
            if ($showFirstLastAsPageNumbers) {
              $outputPaginationItem($metaData['totalPages']);
            }
          }
          // show next page link
          if ($metaData['page'] < $metaData['totalPages']) {
            $outputPaginationItem($metaData['page'] + 1, 'text-muted', t('next'), true);
          }
          else {
            $outputPaginationItem($metaData['page'], 'text-muted', t('next'));
          }
          // show last page link
          if (!$showFirstLastAsPageNumbers) {
            $outputPaginationItem($metaData['totalPages'], 'text-muted firstLast', t('last'));
          }
        ?>
      </ul>
    </div>
  <?php
  return ob_get_clean();
}

?>

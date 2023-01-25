<?php

require_once "lib/menus/database/editTable_functions.php"; // note: this will set $schema
global $schema;

$hasFields = (@$schema['menuType'] != 'link' && @$schema['menuType'] != 'menugroup');


// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [
  t('CMS Setup'),
  t('Section Editors') => '?menu=database&action=listTables',
  @$schema['menuName'] => '?menu=database&action=editTable&tableName=' . urlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])),
];

// buttons
$adminUI['BUTTONS'] = [];
if ($hasFields) {
  $adminUI['BUTTONS'][] = [ 'name' => 'action=listTables', 'label' => t('Back'), ];
  $adminUI['BUTTONS'][] = [ 'name' => 'null',              'label' => t('Add Field'), 'onclick' => "window.location = '?menu=database&action=editField&addField=1&tableName=" . urlencode($tableName) . "&fieldname='; return false;", ];
}            

// advanced actions
$adminUI['ADVANCED_ACTIONS'] = [];
if ($hasFields) {
  $adminUI['ADVANCED_ACTIONS']['Enable System Field Editing']  = 'editTable_enableSystemFieldEditing';
  $adminUI['ADVANCED_ACTIONS']['Disable System Field Editing'] = 'editTable_disableSystemFieldEditing';
}

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',             'value' => 'database',                                                          ],
  [ 'name' => '_defaultAction',   'value' => 'editTable',                                                         ],
  [ 'name' => 'saveTableDetails', 'value' => '1',                                                                 ],
  [ 'name' => 'tableName',        'value' => getTableNameWithPrefix($_REQUEST['tableName']), 'id' => 'tableName', ],
  [ 'name' => 'menuOrder',        'value' => @$schema['menuOrder'],                                               ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture(function() { ?>
  <script src="<?php echo noCacheUrlForCmsFile("lib/menus/database/editTable_functions.js"); ?>"></script>
<?php });

// compose and output the page
adminUI($adminUI);




function _getContent() {
  global $SETTINGS, $schema, $tableName;
  
  $tableDetails = getTableDetails();

  $errors = getTableDetailErrors($schema);
  if ($errors) { alert($errors); }

  if (@$_REQUEST['fieldsaved']) {
    $message   = t("Field saved.");
    notice($message);
  }

?>
  <div class="list-tables">
    <div role="tabpanel">

      <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#generalTab" aria-controls="generalTab" role="tab" data-toggle="tab"><?php et('General'); ?></a></li>
        <?php if (@$schema['menuType'] != 'link' && @$schema['menuType'] != 'menugroup'): ?>
          <li role="presentation"><a href="#viewerTab" aria-controls="viewerTab" role="tab" data-toggle="tab"><?php echo t('Viewer Urls'); ?></a></li>
          <?php if (@$schema['menuType'] != 'single'): ?>
            <li role="presentation"><a href="#searchTab" aria-controls="searchTab" role="tab" data-toggle="tab"><?php echo t('Searching'); ?></a></li>
            <li role="presentation"><a href="#sortingTab" aria-controls="sortingTab" role="tab" data-toggle="tab"><?php echo t('Sorting'); ?></a></li>
            <li role="presentation"><a href="#advancedTab" aria-controls="advancedTab" role="tab" data-toggle="tab"><?php echo t('Advanced'); ?></a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <div class="tab-content">
        <div role="tabpanel" class="tab-pane active" id="generalTab">
          <div class="form-horizontal">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="menuName"><?php et('Section Name') ?></label>
              <div class="col-sm-9">
                
                
                <div class="form-inline">

                  <input class="form-control" type="text" name="menuName" id="menuName" value="<?php echo htmlencode(@$schema['menuName']) ?>">
                  <?php if (@$schema['menuType'] != 'menugroup'): ?>

                    <?php
                      $options = [ '0' => "Don't indent menu" ];
                      foreach (range(1,5) as $num) { $options[$num] = "Indent Menu x$num"; }
                      $optionsHTML = getSelectOptions(@$schema['_indent'], array_keys($options), array_values($options));
                      print "<select name='_indent' class='form-control'>$optionsHTML</select>\n";
                    ?>

                  <?php endif ?>
                </div>
                
                <?php
                  // if media library not enabled, tell user how to enable it
                  if (getTableNameWithoutPrefix($_REQUEST['tableName']) == "_media" && empty($SETTINGS['advanced']['useMediaLibrary'])) {
                    print "<b>Media library can be enabled under: <a href='?menu=admin&amp;action=general#advanced-settings'> Admin Settings &gt; General Settings &gt; Advanced Settings</a></b>";
                  }
                ?>
                
              </div>
            </div>
            
            <?php $forSectionTypes = ['single','multi','category']; ?>
            <?php if (in_array(@$schema['menuType'], $forSectionTypes)): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="sectionDescription"><?php echo t('Section Description'); ?></label>
              <div class="col-sm-9">
                <textarea name="_description" id="sectionDescription" class="form-control textareaGrow" rows="3" data-growsize="10" cols="50"><?php echo htmlencode(@$schema['_description']) ?></textarea>
              </div>
            </div>
            <?php endif ?>
            
            <div class="form-group">
              <label class="col-sm-2 control-label" for="newTableName"><?php echo t('Table Name'); ?></label>
              <div class="col-sm-9">
                <div class="col-xs-12 col-sm-10 col-md-8 col-lg-6 nopadding">
                  <div class="input-group">
                    <span class="input-group-addon">
                      <?php echo $SETTINGS['mysql']['tablePrefix']; ?>
                    </span>
                    <input class="form-control text-input setAttr-spellcheck-false" type="text" id="newTableName" name="newTableName" value="<?php echo htmlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])) ?>">
                  </div>
                  <div class="clearfix">
                    <p class="help-block">
                      <!-- (<?php echo $tableDetails['rowCount'] ?> records) <br> -->
                      <?php echo t('Database table name used by PHP and MySQL'); ?>
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2 control-label"><?php echo t('Section Type'); ?></div>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="menuType" value="<?php echo htmlencode(@$schema['menuType']); ?>">
                  <?php if     (@$schema['menuType'] == ''):         ?> <?php echo t('None'); ?>
                  <?php elseif (@$schema['menuType'] == 'single'):   ?> <?php echo t('Single Record'); ?>
                  <?php elseif (@$schema['menuType'] == 'multi'):    ?> <?php echo t('Multi Record'); ?>
                  <?php elseif (@$schema['menuType'] == 'category'): ?> <?php echo t('Category Menu'); ?>
                  <?php else:                                        ?> <?php echo @$schema['menuType']; ?>
                  <?php endif ?>
                </div>
              </div>
            </div>
            <?php if (@$schema['menuType'] != 'single' && @$schema['menuType'] != 'link' && @$schema['menuType'] != 'menugroup'): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="listPageFields"><?php echo t('ListPage Fields'); ?></label>
              <div class="col-sm-9">
                <?php echo sprintf(t('These fields are displayed on the <a href="?menu=%s">editor list page</a> (beside modify and erase)<br>
                <input class="form-control setAttr-spellcheck-false" type="text" name="listPageFields" id="listPageFields" value="%s" size="75"><br>
                example: field1, field2'),urlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])), htmlencode(@$schema['listPageFields'])); ?>
              </div>
            </div>
            <?php endif; ?>
            <?php if (@$schema['menuType'] == 'link'): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_url"><?php echo t('Link Url'); ?></label>
              <div class="col-sm-9">
                <div class="col-xs-12 col-sm-10 col-md-8 col-lg-6 nopadding">
                  <input class="form-control text-input setAttr-spellcheck-false" type="text" name="_url" id="_url" value="<?php echo htmlencode(@$schema['_url']) ?>" size="75">
                  <div class="clearfix">
                    <p class="help-block">example: http://www.example.com/</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="newTableName">&nbsp;</label>
              <div class="col-sm-9">
                <?php
                  if (!@$schema['_linkTarget'] && @$schema['_targetBlank']) { $schema['_linkTarget'] = 'new'; } // set default for old schemas using _targetBlank options
                  $valuesToLabels = array(
                    ''           => t('same window'),
                    'new'        => t('new window or tab'),
                    //'lightbox' => "lightbox popup",
                    'iframe'     => t('inline iframe'),
                  );
                  $htmlOptions = getSelectOptions(@$schema['_linkTarget'], array_keys($valuesToLabels), array_values($valuesToLabels));
                ?>
                <div class="form-group">
                  <label class="col-md-2 control-label"><?php echo t('Open link in'); ?></label>
                  <div class="col-md-9">
                    <select class="form-control" name="_linkTarget"><?php echo $htmlOptions ?></select>
                  </div>
                </div>
                  
                <div id="iframeHeightSpan" class="form-group" style="display: none;">
                  <label class="col-md-2 control-label"><?php echo t('Iframe Height'); ?></label>
                  <div class="col-md-2">
                    <div class="input-group">
                      <input class="text-input form-control" type="text" name="_iframeHeight" value="<?php echo htmlencode(@$schema['_iframeHeight']) ?>">
                      <span class="input-group-addon">px</span>
                    </div>
                  </div>
                </div>
                <div class="clearfix"></div>
                
                <div class="form-group">
                  <label class="col-md-2 control-label"><?php echo t('Display Message'); ?></label>
                  <div class="col-md-9">
                    <input class="text-input form-control" type="text" name="_linkMessage" value="<?php echo htmlencode(@$schema['_linkMessage']) ?>"  size="75">
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php if (@$schema['menuType'] != 'menugroup'): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="listPageFields"><?php echo t('Menu Icon'); ?></label>
              <div class="col-sm-9">
                
                <div class="col-xs-12 col-sm-8 col-md-6 col-lg-3 nopadding">
                  <div class="input-group">
                    
                    <div class="input-group-addon text-center" style="min-width: 43px;">
                      <i id="menuIconPreview" style="display: none;" class="fa <?php echo htmlencode(@$schema['menuPrefixIcon']) ?>"></i>
                    </div>
                    
                    <input class="text-input form-control" type="text" name="menuPrefixIcon" id="menuPrefixIcon" value="<?php echo htmlencode(@$schema['menuPrefixIcon']) ?>">
                    
                    <div class="input-group-btn">
                      <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="caret"></span>
                        <span class="sr-only">Select Icon</span>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-right menu-icon-select" style="-moz-column-count:4; -webkit-column-count:4; column-count:4; -webkit-column-gap: 0; -moz-column-gap: 0; column-gap: 0;">
                        <?php
                        // example icons from http://fontawesome.io/icons/
                        $menuPrefixIcons = [
                                            'fas fa-chart-bar',
                                            'fas fa-calendar',
                                            'fas fa-calendar-check',
                                            'fas fa-code',
                                            'fas fa-cog',
                                            'fas fa-database',
                                            'fas fa-download',
                                            'fas fa-envelope',
                                            'far fa-envelope',
                                            'fas fa-exclamation-triangle',
                                            'fas fa-external-link-alt',
                                            'fas fa-bolt',
                                            'fas fa-handshake',
                                            'fas fa-history',
                                            'fas fa-home',
                                            'fas fa-image',
                                            'fas fa-info',
                                            'fas fa-id-badge',
                                            'fas fa-list',
                                            'fas fa-map-marker',
                                            'fas fa-newspaper',
                                            'fas fa-phone',
                                            'fas fa-puzzle-piece',
                                            'fas fa-rss',
                                            'fas fa-shopping-cart',
                                            'fas fa-sliders-h',
                                            'fas fa-tag',
                                            'fas fa-terminal',
                                            'fas fa-trash',
                                            'fas fa-user',
                                            'fas fa-users',
                                            'fas fa-video',
                                            ];
                        
                        foreach ($menuPrefixIcons as $iconClass) {
                          echo "<li>
                                    <a onclick='$(\"#menuPrefixIcon\").val(\"$iconClass\"); changeIcon(\"$iconClass\");' style='cursor: pointer;'>
                                      <i aria-hidden='true' aria-label='$iconClass' class='$iconClass'></i>
                                    </a>
                                </li>";
                        }
                        ?>
                      </ul>
                      <script>
                        $(document).ready(function(){
                          // apply the same color of the menu to the menu icon selector
                          var bgColor   = $('.main-navigation').css("backgroundColor");
                          
                          $('.menu-icon-select').css("backgroundColor", bgColor);
                          $('.menu-icon-select li a').css("color", $('ul.main-navigation-menu li > ul > li > a').css("color"));
                          
                          // hover color
                          $('.menu-icon-select li a').hover(function() {
                            $('.menu-icon-select li a').css("backgroundColor", bgColor); // same as .main-navigation bg color
                          });
                          
                          $('#menuPrefixIcon').keyup(function() {
                            changeIcon(this.value);
                          });
                          
                          changeIcon($('#menuPrefixIcon').val());
                        });
                          
                        var changeIcon = function(iconClass) {
                          
                          // initial values
                          var $iconPreview    = $('#menuIconPreview');
                          var prefixRegex     = /(fa.?) /;           // match fa/far/fas/fab prefixes
                          var iconNameRegex   = /fa-([^\s\\]+)/;     // match icon name without fa-
                          var iconPrefix      = 'fa';                // default prefix
                          
                          // hide if class is empty
                          if (iconClass == '') { $iconPreview.hide(); return false; }
                          
                          // check for explicit icon prefix
                          var prefixMatches = prefixRegex.exec(iconClass);
                          if (prefixMatches) { iconPrefix = prefixMatches[1]; }
                          
                          // remove prefix if it exists
                          iconClass = iconClass.replace(prefixRegex, '');
                          
                          // find base icon name
                          var nameMatches = iconNameRegex.exec(iconClass);
                          if (nameMatches) { iconClass = nameMatches[1]; }
                          else             { iconClass = ''; }
                          
                          // update data attributes
                          $iconPreview.show();
                          $iconPreview.attr('class', iconPrefix + ' fa-' + iconClass);
                          
                        }
                        
                      </script>
                    </div> <!--input-group-btn-->
                  </div> <!--input-group-->
                </div>
                <div class="col-xs-12 col-sm-4 col-md-6 col-lg-3 nopadding">
                  <p class="help-block">
                    &nbsp;<a target="_blank" href="https://fontawesome.com/icons?m=free">Find more <i aria-hidden="true" class="fa fa-external-link-alt"></i></a>
                  </p>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="menuHidden"><?php echo t('Hide Menu'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="menuHidden" value="0">
                  <input type="checkbox" name="menuHidden" id="menuHidden" value="1" <?php checkedIf(@$schema['menuHidden'], '1') ?>>
                  <label for="menuHidden"><?php echo t('Don\'t show this section on the menu bar'); ?></label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="viewerTab">
          <div class="form-horizontal">
            <div class="form-group">
              <div class="col-sm-2"></div>
              <div class="col-sm-9">
                <?php echo t('These urls are used when creating links to viewers for this section.'); ?>
                <?php echo t('Update the urls and filenames to match the viewers you create.'); ?>
              </div>
            </div>
            <?php if (@$schema['menuType'] != 'single'): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_listPage"><?php echo t('List Page Url'); ?></label>
              <div class="col-sm-9">
                <?php echo htmlencode(PREFIX_URL); ?><input class="form-control setAttr-spellcheck-false" type="text" name="_listPage" id="_listPage" value="<?php echo htmlencode(@$schema['_listPage']) ?>" size="75">
                <code><?php echo t('example: /news/newsList.php'); ?></code>
              </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_detailPage"><?php echo t('Detail Page Url'); ?></label>
              <div class="col-sm-9">
                <?php echo htmlencode(PREFIX_URL) ?><input class="form-control setAttr-spellcheck-false" type="text" name="_detailPage" id="_detailPage" value="<?php echo htmlencode(@$schema['_detailPage']) ?>" size="75">
                <code><?php echo t('example: /news/newsDetail.php'); ?></code>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_previewPage"><?php echo t('Preview Page Url'); ?></label>
              <div class="col-sm-9">
                <?php echo htmlencode(PREFIX_URL) ?><input class="form-control setAttr-spellcheck-false" type="text" name="_previewPage" id="_previewPage" value="<?php echo htmlencode(@$schema['_previewPage']) ?>" size="75">
                <code><?php echo t('leave blank to use Detail Page Url'); ?></code>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2 control-label"></div>
              <div class="col-sm-9">
                <?php echo t("<b>Tip:</b> Use a <a href='?menu=admin&amp;action=general#websitePrefixUrl'>Website Prefix Url</a> for development servers with temporary urls such as <code>/~username/</code> or <code>/client-name/</code>."); ?>
              </div>
            </div>
            <?php if (@$schema['menuType'] != 'single'): ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_filenameFields"><?php et("Filename Fields"); ?></label>
              <div class="col-sm-9">
                <input class="form-control setAttr-spellcheck-false" type="text" name="_filenameFields" id="_filenameFields" value="<?php echo htmlencode(@$schema['_filenameFields']) ?>" size="75">
                <?php
                $content = <<<__HTML__
                              example: field1, field2<br>
                              These fields are added to viewer links to create more descriptive urls for users<br>and search engines. The first field value that isn't blank is used.<br>
                              <code>Example Url: viewer.php?record_title_goes_here-123</code>
__HTML__;
                print t($content);
                ?>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="searchTab">
          <div class="form-horizontal">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="listPageSearchFields"><?php echo t('Search Fields'); ?></label>
              <div class="col-sm-9">
                <textarea name="listPageSearchFields" id="listPageSearchFields" class="form-control setAttr-spellcheck-false textareaGrow" rows="4" cols="50" data-growsize="10"><?php echo htmlencode(@$schema['listPageSearchFields']) ?></textarea>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2"></div>
              <div class="col-sm-9">
                <?php
                $content = <<<__HTML__
                <p>This section lets you control what search options are available on the <a href="?menu=%s">editor list page</a>.</p>
                <dl>
                  <dt><b>Disabling Search</b></dt>
                  <dd>If you don't want any search options displayed just leave the box above blank.<br><br></dd>

                  <dt><b>Simple Search</b></dt>
                  <dd>For basic search functionality enter a list of comma separated fieldnames on the first line only. The editor list will display a single keyword search field that allows your users to search on those fields. Example: <i>title, content</i><br><br></dd>

                  <dt><b>Advanced Search</b></dt>
                  <dd>For each search field you want to appear enter the following: Label|FieldList|searchType<br><br>
                  <b>label</b> - This is displayed before the search field, such as: Category, Hidden, etc<br>
                  <b>fieldlist</b> - This is the fieldname or fieldnames (comma separated) to be searched. If there are multiple fieldnames a text field will be displayed. If there is only one fieldname and it is a list or checkbox field a dropdown list of options will be displayed (Add [] after list fieldnames to allow users to search by multiple values as once). (Tip: enter _all_ for all fields).<br>
                  <b>searchType</b> - This can be one of: match, keyword, prefix, query, min, max, year, month or day. See the docs for more details on search types. Example:<br><br>
                  <i>Keyword|title,content|query</i><br>
                  <i>Status|product_status|match</i><br>
                  <i>Hidden|hidden|match</i><br>
                  <i>Full Name|createdBy.fullname</i>(special case for searching by record owner)<br><br>
                  This example search might look something like this:<br><br>

                  <div class="form-group">
                    <div class="col-sm-2 control-label">Keyword</div>
                    <div class="col-sm-9"><input class="form-control" type="text" name="null" value=""></div>
                  </div>
                  <div class="form-group">
                    <div class="col-sm-2 control-label">Status</div>
                    <div class="col-sm-9">
                      <select class="form-control">
                        <option value=''>&lt;select&gt;</option>
                        <option value='Active' >Active</option>
                        <option value='On Sale' >On Sale</option>
                        <option value='Discontinued' >Discontinued</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group">
                    <div class="col-sm-2 control-label">Checkbox</div>
                    <div class="col-sm-9">
                      <select class="form-control">
                        <option value=''>&lt;select&gt;</option>
                        <option value='Checked'>Checked</option>
                        <option value='Unchecked'>Unchecked</option>
                      </select>
                    </div>
                  </div>

                  <p><b>Tip:</b> Use your own fieldnames instead of the examples.</p>
                  </dd>
                </dl>
__HTML__;
                echo sprintf(t($content), htmlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])));
                ?>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="sortingTab">
          <div class="form-horizontal">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="listPageOrder"><?php echo t('Order By'); ?></label>
              <div class="col-sm-9">
                <input class="form-control setAttr-spellcheck-false" type="text" name="listPageOrder" id="listPageOrder" value="<?php echo htmlencode(@$schema['listPageOrder']) ?>" size="75">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2"></div>
              <div class="col-sm-9">
                <?php
                $content = <<<__HTML__
                These sorting settings are used for both the <a href="?menu=%s">editor</a> and the viewers (although viewers can override them).
                Here are some common sorting values that can be used here and in the viewers. Use your own fieldnames instead of the <i>examples</i>.<br>
                <dl>
                  <dt><i>title, author</i></dt>
                  <dd>sort by title, then author<br><br></dd>

                  <dt><i>date, DESC, title</i></dt>
                  <dt><i>date</i> DESC, <i>title</i></dt>
                  <dd>sort by date in descending order (newest first), then by title<br><br></dd>

                  <dt><i>price</i>+0, <i>date</i></dt>
                  <dd>sort by price numerically, then by date (oldest first)<br><br></dd>

                  <dt><i>featured DESC</i>, <i>date DESC</i></dt>
                  <dd>sort featured (checkbox field) records first, then order by date (newest first)<br><br></dd>

                  <dt><i>RAND()</i></dt>
                  <dd>sort in random order (for viewers only)<br><br></dd>
                </dl>
                <b>Tip:</b>  The "Order By" field is actually just the standard MySQL ORDER BY clause of a SELECT statement.  So if you're familiar with MySQL you can enter any ORDER BY clause you want here.  Otherwise, congratulations! You just learned some MySQL!
__HTML__;
                echo sprintf(t($content), htmlencode(getTableNameWithoutPrefix($_REQUEST['tableName'])));
                ?>
              </div>
            </div>
          </div>
        </div>

        <div role="tabpanel" class="tab-pane" id="advancedTab">
          <div class="form-horizontal">
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_perPageDefault"><?php echo t('Per Page'); ?></label>
              <div class="col-sm-9">
                <div class="form-inline">
                  <?php $optionsHTML = getSelectOptions((@$schema['_perPageDefault'] ?: 25), array(5, 10, 25, 50, 100, 250, 1000)); ?>
                  <select name="_perPageDefault" id="_perPageDefault" class="form-control"><?php echo $optionsHTML; ?></select>
                  <?php echo t('Default number of records to show per page'); ?>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_maxRecords"><?php echo t('Max Records'); ?></label>
              <div class="col-sm-9">
                <div class="form-inline">
                  <input class="form-control" type="text" name="_maxRecords" id="_maxRecords" value="<?php echo htmlencode(@$schema['_maxRecords']) ?>" size="4">
                  <?php echo t('Max records for this section (leave blank for unlimited)'); ?>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2 control-label"></div>
              <div class="col-sm-9">
                <div class="form-inline">
                  <input class="form-control" type="text" name="_maxRecordsPerUser" id="_maxRecordsPerUser" value="<?php echo htmlencode(@$schema['_maxRecordsPerUser']) ?>" size="4">
                  <?php echo t('Max records per user (leave blank for unlimited)'); ?>
                </div>
              </div>
            </div>
            <?php if (@$schema['menuType'] == 'category'): ?>
            <div class="form-group">
              <div class="col-sm-2 control-label"><?php echo t('Max Depth'); ?></div>
              <div class="col-sm-9">
                <div class="form-inline">
                  <input class="form-control" type="text" name="_maxDepth" id="_maxDepth" value="<?php echo htmlencode(@$schema['_maxDepth']) ?>" size="4">
                  <?php echo t('Max level of depth for categories (leave blank for unlimited)'); ?>
                </div>
              </div>
            </div>
            <?php endif ?>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="disableAdd"><?php echo t('Disable Add'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disableAdd" value="0">
                  <input type="checkbox" name="_disableAdd" id="disableAdd" value="1" <?php checkedIf(@$schema['_disableAdd'], '1') ?>>
                  <label for="disableAdd"><?php echo t('Don\'t allow adding records'); ?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_disableView"><?php echo t('Disable View'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disableView" value="0">
                  <label>
                    <input type="checkbox" name="_disableView" id="_disableView" value="1" <?php checkedIf(@$schema['_disableView'], '1') ?>>
                    <?php echo t('Don\'t allow viewing of records through "view" menu'); ?>
                  </label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="_disableView"><?php echo t('Disable Modify'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disableModify" value="0">
                  <input type="checkbox" name="_disableModify" id="disableModify" value="1" <?php checkedIf(@$schema['_disableModify'], '1') ?>>
                  <label for="disableModify"><?php echo t('Don\'t allow modifying records'); ?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="disableErase"><?php echo t('Disable Erase'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disableErase" value="0">
                  <input type="checkbox" name="_disableErase" id="disableErase" value="1" <?php checkedIf(@$schema['_disableErase'], '1') ?>>
                  <label for="disableErase"><?php echo t('Don\'t allow removing records'); ?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="disableEraseFromModify"><?php echo t('Disable Erase From Modify Page'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disableEraseFromModify" value="0">
                  <input type="checkbox" name="_disableEraseFromModify" id="disableEraseFromModify" value="1" <?php checkedIf(@$schema['_disableEraseFromModify'], '1') ?>>
                  <label for="disableEraseFromModify"><?php echo t('Don\'t allow removing records from the modify page'); ?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2 control-label"><?php echo t('Disable Preview'); ?></div>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_disablePreview" value="0">
                  <label>
                    <input type="checkbox" name="_disablePreview" value="1" <?php checkedIf(@$schema['_disablePreview'], '1') ?>>
                    <?php echo t("Don't allow previewing records"); ?>
                  </label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="hideRecordsFromDisabledAccounts"><?php echo t('Disable Accounts'); ?></label>
              <div class="col-sm-9 control-label">
                <div class="text-left">
                  <input type="hidden" name="_hideRecordsFromDisabledAccounts" value="0">
                  <input type="checkbox" name="_hideRecordsFromDisabledAccounts" id="hideRecordsFromDisabledAccounts" value="1" <?php checkedIf(@$schema['_hideRecordsFromDisabledAccounts'], '1') ?>>
                  <label for="hideRecordsFromDisabledAccounts"><?php echo t('Viewers: Hide records that are "Created By" a user who is: deleted, disabled, or expired'); ?></label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-2 control-label" for="menuHidden"><?php echo t('Required Plugins'); ?></label>
              <div class="col-sm-9">
                <input class="form-control" type="text" name="_requiredPlugins" value="<?php echo htmlencode(@$schema['_requiredPlugins']) ?>" size="75">
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2"></div>
              <div class="col-sm-9">
                <code><?php echo t('Example: plugin1.php, plugin2.php'); ?></code>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-2">
                <a href="http://dev.mysql.com/doc/refman/5.0/en/show-index.html" target="_blank"><?php echo t("MySQL Indexes"); ?></a>
              </div>
              <div class="col-sm-9">
                <?php
                  $indexKeys = array('Key_name','Column_name','Non_unique','Seq_in_index','Collation','Cardinality','Sub_part','Packed','Null','Index_type','Comment');
                  $indexQuery   = "SHOW INDEXES FROM `" .mysql_escape($tableName). "`";
                  $indexDetails = mysql_select_query($indexQuery);
                ?>
                <table style="padding: 2px 0px 1px 0px;" class="data table table-striped table-hover">
                  <?php
                  print "<tr style='font-weight: bold'>\n";
                  foreach ($indexKeys as $label) { print "<td>" .htmlencode($label). "</td>\n"; }
                  print "</tr>\n";

                  foreach ($indexDetails as $indexRow) {
                    print "<tr>\n";
                    foreach ($indexKeys as $key) { print "<td>" .htmlencode(@$indexRow[$key]). "</td>\n"; }
                    print "</tr>\n";
                  }

                  if(!$indexDetails) {
                    print "<tr><td colspan=''>" .t('None'). "</td></tr>\n";
                  }
                  ?>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- END .tab-content -->
    </div> <!-- END .tabpanel -->

    <div class="float-clear-children">
      <div>
      <?php if (@$schema['menuType'] != 'link' && @$schema['menuType'] != 'menugroup'): ?>
        <?php
          echo adminUI_button([
            'label'   => t('Code Generator'),
            'type'    => 'button',
            'onclick' => "window.location='?menu=_codeGenerator&tableName=" .urlencode(getTableNameWithoutPrefix(@$_REQUEST['tableName'])). "'",
          ]);
          echo adminUI_button([
            'label'   => t('Editor'),
            'type'    => 'button',
            'onclick' => "window.location='?menu=" .urlencode(getTableNameWithoutPrefix(@$_REQUEST['tableName'])). "'",
          ]);
        ?>
      <?php endif ?>
      </div>
      <div>
        <?php echo adminUI_button(['label'   => t('Back'), 'name' => 'action=listTables', ]); ?>
        <?php echo adminUI_button(['label' => t('Save Details') ]); ?>
      </div>
    
    </div> <!-- END .float-clear-children -->
  </div> <!-- END .list-tables -->
  
  <div class="clearfix" style="margin-bottom: 20px"></div>

  <?php if (@$schema['menuType'] != 'link' && @$schema['menuType'] != 'menugroup'): ?>

  <?php if ($SETTINGS['mysql']['allowSystemFieldEditing'] == 1): ?>
    <div class="alert alert-danger">
      <i class="fa fa-exclamation-triangle"></i>&nbsp;
      <?php et('<b>Warning:</b> System field editing is currently <i>enabled</i> <span class="text-muted">(system fields are shown in gray).</span>'); ?><br>
      <?php et('Modifying system fields may cause your program to stop working correctly!'); ?>
    </div>
  <?php endif ?>

  <!-- list column headings -->
  <table style="padding: 2px 0px 1px 0px;" class="data sortable table table-striped table-hover">
    <thead>   
      <tr class="nodrag nodrop">
        <th class="text-center hidden-xs"><?php et('Drag') ?></th>
        <th><?php et('Field Label') ?></th>
        <th class="min-tablet-p"><?php et('Field Type') ?></th>
        <th class="min-tablet-l"><?php et('Field Name') ?></th>
        <th class="text-center all"><?php et('Action') ?></th>
      </tr>
    </thead>    
    <tbody id="fieldlistContainer">
      <?php displayFieldList(); ?>
    </tbody>
  </table>

  <div class="quickadd">
    <div class="row separator hidden-sm hidden-md hidden-lg"><h4><?php et('Quick Add a Field') ?></h4></div>
    <div class="row">
      <div class="col-sm-4">
        <label for="fieldLabel"><?php et('Field Label') ?></label>
        <input class="form-control" type="text" name="fieldLabel" id="fieldLabel" value="" onkeyup="autoFillQuickAddFieldName()" onchange="autoFillQuickAddFieldName()">
      </div>
      <div class="col-sm-2">
        <label for="fieldType"><?php et('Field Type') ?></label>
        <select name="type" id="fieldType" class="form-control">
          <option value="none"     ><?php et('none'); ?></option>
          <option value="textfield" selected="selected"><?php et('text field'); ?></option>
          <option value="textbox"  ><?php et('text box'); ?></option>
          <option value="wysiwyg"  ><?php et('wysiwyg'); ?></option>
          <option value="date"     ><?php et('date/time'); ?></option>
          <option value="list"     ><?php et('list'); ?></option>
          <option value="checkbox" ><?php et('checkbox'); ?></option>
          <option value="upload"   ><?php et('upload'); ?></option>
          <option value="separator"><?php et('separator'); ?></option>
          <option value="tabGroup" ><?php et('tab group'); ?></option>
        </select>
      </div>
      <div class="col-sm-4">
        <label for="fieldName"><?php et('Field Name') ?></label>
        <input class="form-control" type="text" name="fieldName" id="fieldName">
      </div>
      <div class="col-sm-2">
        <?php
          echo adminUI_button([
            'label'   => t('Quick Add'),
            'name'    => 'quickAddButton',
            'type'    => 'button',
            'onclick' => 'quickAddField()',
          ]);
        ?>
      </div>
    </div>
  </div> <!-- END .quickadd -->

  <?php endif ?>
  <?php
}

<?php /* This is a PHP data file */ if (!@$LOADSTRUCT) { die("This is not a program file."); }
/*
### NOTE: This file was last updated: 2016-09-13 15:24:22
### NOTE: Changes to this file will be overwritten next time you upgrade!
### NOTE: Either send us your changes or create a new language called custom.php to avoid this
### Default Text => Translation Text
*/
return array (
  '%s seconds' => '%s seconds',
  '&lt;&lt;  prev' => '&lt;&lt;  prev',
  '&lt;select&gt;' => '&lt;select&gt;',
  '\'View Website\' URL' => '\'View Website\' URL',
  '\'Viewers\' are PHP files that display the data from the CMS in all the different \'views\' you might have on your site.' => '\'Viewers\' are PHP files that display the data from the CMS in all the different \'views\' you might have on your site.',
  '(displayed beside field in editor)' => '(displayed beside field in editor)',
  '(used in PHP viewer code)' => '(used in PHP viewer code)',
  '--- separator ---' => '--- separator ---',
  '<a href="http://www.php.net/manual/en/ini.core.php#ini.expose-php">expose_php</a> is currently enabled, disable it in php.ini.' => '<a href="http://www.php.net/manual/en/ini.core.php#ini.expose-php">expose_php</a> is currently enabled, disable it in php.ini.',
  '<b>Advanced Filter:</b> Insert <i>escaped</i> filter field value like this: &lt;?php echo $ESCAPED_FILTER_VALUE ?&gt;<br>' => '<b>Advanced Filter:</b> Insert <i>escaped</i> filter field value like this: &lt;?php echo $ESCAPED_FILTER_VALUE ?&gt;<br>',
  '<b>Advanced Filter:</b> Refresh list when this field changes:' => '<b>Advanced Filter:</b> Refresh list when this field changes:',
  '<b>Multi Record</b> - multi record menus can have many records and
    are for sections such as News, FAQs, Jobs, Events, etc.' => '<b>Multi Record</b> - multi record menus can have many records and
    are for sections such as News, FAQs, Jobs, Events, etc.',
  '<b>Multi Record</b> - multi record menus can have many records and are for sections such as News, FAQs, Jobs, Events, etc.' => '<b>Multi Record</b> - multi record menus can have many records and are for sections such as News, FAQs, Jobs, Events, etc.',
  '<b>Tip: &nbsp;</b> Insert table prefix like this: &lt;?php echo $TABLE_PREFIX ?&gt;<br>' => '<b>Tip: &nbsp;</b> Insert table prefix like this: &lt;?php echo $TABLE_PREFIX ?&gt;<br>',
  '<b>Tip:</b> To save and display different values enter them like this: CD|Compact Disc' => '<b>Tip:</b> To save and display different values enter them like this: CD|Compact Disc',
  '<b>Tip:</b> Use a <a href=\'?menu=admin&amp;action=general#websitePrefixUrl\'>Website Prefix Url</a> for development servers with temporary urls such as <code>/~username/</code> or <code>/client-name/</code>.' => '<b>Tip:</b> Use a <a href=\'?menu=admin&amp;action=general#websitePrefixUrl\'>Website Prefix Url</a> for development servers with temporary urls such as <code>/~username/</code> or <code>/client-name/</code>.',
  '<b>Warning:</b> System field editing is currently <i>enabled</i> <span class="text-muted">(system fields are shown in gray).</span>' => '<b>Warning:</b> System field editing is currently <i>enabled</i> <span class="text-muted">(system fields are shown in gray).</span>',
  '<p><b style="color: #CC0000">And remember...</b> once you change the Vendor Name the private label link won\'t be displayed anymore.  So bookmark this page or copy down the url: <a href="?menu=admin&amp;action=vendor">Private Label Url</a></p>' => '<p><b style="color: #CC0000">And remember...</b> once you change the Vendor Name the private label link won\'t be displayed anymore.  So bookmark this page or copy down the url: <a href="?menu=admin&amp;action=vendor">Private Label Url</a></p>',
  '<p><b style="color: #CC0000">Finally...</b> if you accidentally uploaded any text files from the program zip, delete those now.  Those are: \'changelog.txt\', \'how to upgrade.txt\', and \'upload instructions (READ FIRST).txt\'.</p>' => '<p><b style="color: #CC0000">Finally...</b> if you accidentally uploaded any text files from the program zip, delete those now.  Those are: \'changelog.txt\', \'how to upgrade.txt\', and \'upload instructions (READ FIRST).txt\'.</p>',
  '<p><b>Vendor Fields</b> are displayed in <a href=\'?menu=admin&amp;action=general\'>General Settings</a> and in the <a href=\'?menu=license\'>License Agreement</a>. Make sure Vendor Location is in this format \'State or Province, Country\' because it\'s listed as the jurisdiction in the license agreement.</p>' => '<p><b>Vendor Fields</b> are displayed in <a href=\'?menu=admin&amp;action=general\'>General Settings</a> and in the <a href=\'?menu=license\'>License Agreement</a>. Make sure Vendor Location is in this format \'State or Province, Country\' because it\'s listed as the jurisdiction in the license agreement.</p>',
  '<p>This section lets you control what search options are available on the <a href="?menu=every_field_multi">editor list page</a>.</p>

<dl>
   <dt><b>Disabling Search</b></dt>
   <dd>If you don\'t want any search options displayed just leave the box above blank.<br><br></dd>

   <dt><b>Simple Search</b></dt>
   <dd>For basic search functionality enter a list of comma separated fieldnames on the first line only.          The editor list will display a single keyword search field that allows your users to search on those          fields. Example: <i>title, content</i><br><br>
   </dd>

   <dt><b>Advanced Search</b></dt>
   <dd>For each search field you want to appear enter the following: Label|FieldList|searchType<br><br>

   <b>label</b> - This is displayed before the search field, such as: Category, Hidden, etc<br>
   <b>fieldlist</b> - This is the fieldname or fieldnames (comma separated) to be searched.  If there are multiple fieldnames a text field will be displayed.  If there is only one fieldname and it is a list or checkbox field a dropdown list of options will be displayed (Add [] after list fieldnames to allow users to search by multiple values as once). (Tip: enter _all_ for all fields).<br>
   <b>searchType</b> - This can be one of: match, keyword, prefix, query, min, max, year, month or day. See the docs for more details on search types. Example: <br><br>

   <i>Keyword|title,content|query</i><br>
   <i>Status|product_status|match</i><br>
   <i>Hidden|hidden|match</i><br>
   <i>Full Name|createdBy.fullname</i>(special case for searching by record owner)<br>
   <br>This example search might look something like this:<br><br>

   <table border="0" cellspacing="0" cellpadding="0" style="width: 400px;">
    <tr>
      <td>Keyword &nbsp;</td>
      <td><input class="text-input wide-input" type="text" name="null" value=\'\'></td>
    </tr>
    <tr>
      <td>Status&nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Active\' >Active</option>
<option value=\'On Sale\' >On Sale</option>
<option value=\'Discontinued\' >Discontinued</option>
</select></td>
    </tr>
    <tr>
      <td>Checkbox &nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Checked\' >Checked</option>
<option value=\'Unchecked\' >Unchecked</option>
</select><br><br>
</td>
</tr>
</table>

<p><b>Tip:</b> Use your own fieldnames instead of the examples.</p>


  </dd>
 </dl><br>


' => '<p>This section lets you control what search options are available on the <a href="?menu=every_field_multi">editor list page</a>.</p>

<dl>
   <dt><b>Disabling Search</b></dt>
   <dd>If you don\'t want any search options displayed just leave the box above blank.<br><br></dd>

   <dt><b>Simple Search</b></dt>
   <dd>For basic search functionality enter a list of comma separated fieldnames on the first line only.          The editor list will display a single keyword search field that allows your users to search on those          fields. Example: <i>title, content</i><br><br>
   </dd>

   <dt><b>Advanced Search</b></dt>
   <dd>For each search field you want to appear enter the following: Label|FieldList|searchType<br><br>

   <b>label</b> - This is displayed before the search field, such as: Category, Hidden, etc<br>
   <b>fieldlist</b> - This is the fieldname or fieldnames (comma separated) to be searched.  If there are multiple fieldnames a text field will be displayed.  If there is only one fieldname and it is a list or checkbox field a dropdown list of options will be displayed (Add [] after list fieldnames to allow users to search by multiple values as once). (Tip: enter _all_ for all fields).<br>
   <b>searchType</b> - This can be one of: match, keyword, prefix, query, min, max, year, month or day. See the docs for more details on search types. Example: <br><br>

   <i>Keyword|title,content|query</i><br>
   <i>Status|product_status|match</i><br>
   <i>Hidden|hidden|match</i><br>
   <i>Full Name|createdBy.fullname</i>(special case for searching by record owner)<br>
   <br>This example search might look something like this:<br><br>

   <table border="0" cellspacing="0" cellpadding="0" style="width: 400px;">
    <tr>
      <td>Keyword &nbsp;</td>
      <td><input class="text-input wide-input" type="text" name="null" value=\'\'></td>
    </tr>
    <tr>
      <td>Status&nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Active\' >Active</option>
<option value=\'On Sale\' >On Sale</option>
<option value=\'Discontinued\' >Discontinued</option>
</select></td>
    </tr>
    <tr>
      <td>Checkbox &nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Checked\' >Checked</option>
<option value=\'Unchecked\' >Unchecked</option>
</select><br><br>
</td>
</tr>
</table>

<p><b>Tip:</b> Use your own fieldnames instead of the examples.</p>


  </dd>
 </dl><br>


',
  '<p>This section lets you control what search options are available on the <a href="?menu=every_field_multi">editor list page</a>.</p>

<dl>
   <dt><b>Disabling Search</b></dt>
   <dd>If you don\'t want any search options displayed just leave the box above blank.<br><br></dd>

   <dt><b>Simple Search</b></dt>
   <dd>For basic search functionality enter a list of comma separated fieldnames on the first line only.          The editor list will display a single keyword search field that allows your users to search on those          fields. Example: <i>title, content</i><br><br>
   </dd>

   <dt><b>Advanced Search</b></dt>
   <dd>For each search field you want to appear enter the following: Label|FieldList|searchType<br><br>

   <b>label</b> - This is displayed before the search field, such as: Category, Hidden, etc<br>
   <b>fieldlist</b> - This is the fieldname or fieldnames (comma separated) to be searched.  If there are multiple fieldnames a text field will be displayed.  If there is only one fieldname and it is a list or checkbox field a dropdown list of options will be displayed. (Tip: enter _all_ for all fields).<br>
   <b>searchType</b> - This can be one of: match, keyword, prefix, query, min, max, year, month or day. See the docs for more details on search types. Example: <br><br>

   <i>Keyword|title,content|query</i><br>
   <i>Status|product_status|match</i><br>
   <i>Hidden|hidden|match</i><br>
   <i>Full Name|createdBy.fullname</i>(special case for searching by record owner)<br>
   <br>This example search might look something like this:<br><br>

   <table border="0" cellspacing="0" cellpadding="0" style="width: 400px;">
    <tr>
      <td>Keyword &nbsp;</td>
      <td><input class="text-input wide-input" type="text" name="null" value=\'\'></td>
    </tr>
    <tr>
      <td>Status&nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Active\' >Active</option>
<option value=\'On Sale\' >On Sale</option>
<option value=\'Discontinued\' >Discontinued</option>
</select></td>
    </tr>
    <tr>
      <td>Checkbox &nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Checked\' >Checked</option>
<option value=\'Unchecked\' >Unchecked</option>
</select><br><br>
</td>
</tr>
</table>

<p><b>Tip:</b> Use your own fieldnames instead of the examples.</p>


  </dd>
 </dl><br>


' => '<p>This section lets you control what search options are available on the <a href="?menu=every_field_multi">editor list page</a>.</p>

<dl>
   <dt><b>Disabling Search</b></dt>
   <dd>If you don\'t want any search options displayed just leave the box above blank.<br><br></dd>

   <dt><b>Simple Search</b></dt>
   <dd>For basic search functionality enter a list of comma separated fieldnames on the first line only.          The editor list will display a single keyword search field that allows your users to search on those          fields. Example: <i>title, content</i><br><br>
   </dd>

   <dt><b>Advanced Search</b></dt>
   <dd>For each search field you want to appear enter the following: Label|FieldList|searchType<br><br>

   <b>label</b> - This is displayed before the search field, such as: Category, Hidden, etc<br>
   <b>fieldlist</b> - This is the fieldname or fieldnames (comma separated) to be searched.  If there are multiple fieldnames a text field will be displayed.  If there is only one fieldname and it is a list or checkbox field a dropdown list of options will be displayed. (Tip: enter _all_ for all fields).<br>
   <b>searchType</b> - This can be one of: match, keyword, prefix, query, min, max, year, month or day. See the docs for more details on search types. Example: <br><br>

   <i>Keyword|title,content|query</i><br>
   <i>Status|product_status|match</i><br>
   <i>Hidden|hidden|match</i><br>
   <i>Full Name|createdBy.fullname</i>(special case for searching by record owner)<br>
   <br>This example search might look something like this:<br><br>

   <table border="0" cellspacing="0" cellpadding="0" style="width: 400px;">
    <tr>
      <td>Keyword &nbsp;</td>
      <td><input class="text-input wide-input" type="text" name="null" value=\'\'></td>
    </tr>
    <tr>
      <td>Status&nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Active\' >Active</option>
<option value=\'On Sale\' >On Sale</option>
<option value=\'Discontinued\' >Discontinued</option>
</select></td>
    </tr>
    <tr>
      <td>Checkbox &nbsp;</td>
      <td><select><option value=\'\'>&lt;select&gt;</option>
<option value=\'Checked\' >Checked</option>
<option value=\'Unchecked\' >Unchecked</option>
</select><br><br>
</td>
</tr>
</table>

<p><b>Tip:</b> Use your own fieldnames instead of the examples.</p>


  </dd>
 </dl><br>


',
  '<select section above>' => '<select section above>',
  '<select section first>' => '<select section first>',
  'A form for adding records.' => 'A form for adding records.',
  'Access Level - Choose if field has edit access restrictions.' => 'Access Level - Choose if field has edit access restrictions.',
  'Action' => 'Action',
  'Activate' => 'Activate',
  'Active Plugins' => 'Active Plugins',
  'Activity' => 'Activity',
  'Add Field' => 'Add Field',
  'Add Form' => 'Add Form',
  'Add New Editor' => 'Add New Editor',
  'Adding MySQL fields for schema table:' => 'Adding MySQL fields for schema table:',
  'Admin' => 'Admin',
  'Admin Email' => 'Admin Email',
  'Admin Menu' => 'Admin Menu',
  'Admin Only' => 'Admin Only',
  'Advanced' => 'Advanced',
  'Advanced Commands...' => 'Advanced Commands...',
  'Advanced Field Types' => 'Advanced Field Types',
  'Advanced Menus' => 'Advanced Menus',
  'Advanced Options' => 'Advanced Options',
  'Advanced Settings' => 'Advanced Settings',
  'After' => 'After',
  'Allow Searching' => 'Allow Searching',
  'Allow Uploads' => 'Allow Uploads',
  'Allow all characters' => 'Allow all characters',
  'Allow uploads for this field' => 'Allow uploads for this field',
  'Allowed Content' => 'Allowed Content',
  'Always show expanded menu (don\'t hide unselected menu groups)' => 'Always show expanded menu (don\'t hide unselected menu groups)',
  'Associative array of record being edited' => 'Associative array of record being edited',
  'Attempt to disable autocomplete functionality in browsers to prevent storing of usernames and passwords.' => 'Attempt to disable autocomplete functionality in browsers to prevent storing of usernames and passwords.',
  'Auto' => 'Auto',
  'Automatically add new language strings to language files' => 'Automatically add new language strings to language files',
  'Automatically expire login sessions after' => 'Automatically expire login sessions after',
  'BIGINT (max: +/- 9 billion billion, doesn\'t support decimals)' => 'BIGINT (max: +/- 9 billion billion, doesn\'t support decimals)',
  'Back' => 'Back',
  'Back to Plugins' => 'Back to Plugins',
  'Back to list page' => 'Back to list page',
  'Background Task logs have been cleared.' => 'Background Task logs have been cleared.',
  'Background Tasks' => 'Background Tasks',
  'Background Tasks Log >>' => 'Background Tasks Log >>',
  'Background Tasks logs have been cleared.' => 'Background Tasks logs have been cleared.',
  'Background tasks allow programs to run in the background at specific times for tasks such as maintenance, email alerts, etc.
You don\'t need to enable this feature unless you have a plugin that requires it.' => 'Background tasks allow programs to run in the background at specific times for tasks such as maintenance, email alerts, etc.
You don\'t need to enable this feature unless you have a plugin that requires it.',
  'Backup' => 'Backup',
  'Backup & Restore' => 'Backup & Restore',
  'Backup file will be stored in %s' => 'Backup file will be stored in %s',
  'Basic Field Types' => 'Basic Field Types',
  'Before' => 'Before',
  'Blank Line' => 'Blank Line',
  'Branding' => 'Branding',
  'Branding Menu' => 'Branding Menu',
  'Breadcrumb' => 'Breadcrumb',
  'By' => 'By',
  'CMS Setup' => 'CMS Setup',
  'Cancel' => 'Cancel',
  'Category - The following category will be selected' => 'Category - The following category will be selected',
  'Category Format' => 'Category Format',
  'Category Menu' => 'Category Menu',
  'Category Root' => 'Category Root',
  'Check Referer' => 'Check Referer',
  'Checked' => 'Checked',
  'Checked Value' => 'Checked Value',
  'Click and drag to change order.' => 'Click and drag to change order.',
  'Click here to display' => 'Click here to display',
  'Code Generator' => 'Code Generator',
  'Color / Theme' => 'Color / Theme',
  'Combination List and Detail Page, show links to many records and full details on one record.' => 'Combination List and Detail Page, show links to many records and full details on one record.',
  'Combo Page' => 'Combo Page',
  'Combo Page Viewer' => 'Combo Page Viewer',
  'Completed' => 'Completed',
  'Core' => 'Core',
  'Couldn\'t load viewer library, check filepath in sourcecode.' => 'Couldn\'t load viewer library, check filepath in sourcecode.',
  'Create' => 'Create',
  'Create %scolumn index%s - speeds up sorting and some searches but slows down adding and saving records.' => 'Create %scolumn index%s - speeds up sorting and some searches but slows down adding and saving records.',
  'Create New Menu' => 'Create New Menu',
  'Create a Viewer' => 'Create a Viewer',
  'Create add forms (official).' => 'Create add forms (official).',
  'Create archive viewer to view current and previous newsletters.' => 'Create archive viewer to view current and previous newsletters.',
  'Create subscriber manage page for subscribe, confirm and unsubscribe functions.' => 'Create subscriber manage page for subscribe, confirm and unsubscribe functions.',
  'Create thumbnail' => 'Create thumbnail',
  'Created backup file %1$s (%2$s seconds)' => 'Created backup file %1$s (%2$s seconds)',
  'Creating MySQL table for schema table: ' => 'Creating MySQL table for schema table: ',
  'Current Date' => 'Current Date',
  'Current Value' => 'Current Value',
  'Custom Column Type' => 'Custom Column Type',
  'Custom: Load record # %s' => 'Custom: Load record # %s',
  'DECIMAL(14,2) (max: +/- 999 billion, supports 2 decimal places)' => 'DECIMAL(14,2) (max: +/- 999 billion, supports 2 decimal places)',
  'Database' => 'Database',
  'Database Backup' => 'Database Backup',
  'Database Restore' => 'Database Restore',
  'Database Server' => 'Database Server',
  'Database table name used by PHP and MySQL' => 'Database table name used by PHP and MySQL',
  'Database table prefix ' => 'Database table prefix ',
  'Date' => 'Date',
  'Date Field Format' => 'Date Field Format',
  'Days' => 'Days',
  'Deactivate' => 'Deactivate',
  'Default - use same sorting as editor (recommended)' => 'Default - use same sorting as editor (recommended)',
  'Default Category' => 'Default Category',
  'Default State' => 'Default State',
  'Default Value' => 'Default Value',
  'Default number of records to show per page' => 'Default number of records to show per page',
  'Default:' => 'Default:',
  'Delete this record? Are you sure?' => 'Delete this record? Are you sure?',
  'Deprecated' => 'Deprecated',
  'Description' => 'Description',
  'Description field' => 'Description field',
  'Detail Page' => 'Detail Page',
  'Detail Page Url' => 'Detail Page Url',
  'Detail Viewer Options' => 'Detail Viewer Options',
  'Developer Log' => 'Developer Log',
  'Developer Mode' => 'Developer Mode',
  'Developer\'s Plugin Hook List' => 'Developer\'s Plugin Hook List',
  'Directories & URLs' => 'Directories & URLs',
  'Directory Path' => 'Directory Path',
  'Disable Accounts' => 'Disable Accounts',
  'Disable Add' => 'Disable Add',
  'Disable Autocomplete' => 'Disable Autocomplete',
  'Disable Background Tasks' => 'Disable Background Tasks',
  'Disable Erase' => 'Disable Erase',
  'Disable Modify' => 'Disable Modify',
  'Disable Preview' => 'Disable Preview',
  'Disable System Field Editing' => 'Disable System Field Editing',
  'Disable View' => 'Disable View',
  'Disable auto-formatting (don\'t add break tags to content)' => 'Disable auto-formatting (don\'t add break tags to content)',
  'Disabled Accounts' => 'Disabled Accounts',
  'Disallow characters:' => 'Disallow characters:',
  'Disk Space' => 'Disk Space',
  'Display As' => 'Display As',
  'Display datepicker icon and popup calendar beside date fields' => 'Display datepicker icon and popup calendar beside date fields',
  'Don\'t allow adding records' => 'Don\'t allow adding records',
  'Don\'t allow modifying records' => 'Don\'t allow modifying records',
  'Don\'t allow previewing records' => 'Don\'t allow previewing records',
  'Don\'t allow removing records' => 'Don\'t allow removing records',
  'Don\'t allow viewing of records through "view" menu' => 'Don\'t allow viewing of records through "view" menu',
  'Don\'t show this section on the menu bar' => 'Don\'t show this section on the menu bar',
  'Don\'t show uploads' => 'Don\'t show uploads',
  'Download more languages...' => 'Download more languages...',
  'Drag' => 'Drag',
  'Editor' => 'Editor',
  'Editor Only' => 'Editor Only',
  'Email Alerts: If tasks fail an email alert will be sent to admin (max once an hour).' => 'Email Alerts: If tasks fail an email alert will be sent to admin (max once an hour).',
  'Email PHP Errors' => 'Email PHP Errors',
  'Email Settings' => 'Email Settings',
  'Email Templates' => 'Email Templates',
  'Email this Page' => 'Email this Page',
  'Enable \'Email PHP Errors\' to be notified of PHP errors on website.' => 'Enable \'Email PHP Errors\' to be notified of PHP errors on website.',
  'Enable \'Require HTTPS\' above to disallow insecure connections.' => 'Enable \'Require HTTPS\' above to disallow insecure connections.',
  'Enable System Field Editing' => 'Enable System Field Editing',
  'Encrypt Passwords' => 'Encrypt Passwords',
  'Enter a standard url search in this field, example: fieldA_match=value1&amp;fieldB_keyword=value2<br>
              <b>Note:</b> Field search suffixes are required.  Use field_match=, not field=.' => 'Enter a standard url search in this field, example: fieldA_match=value1&amp;fieldB_keyword=value2<br>
              <b>Note:</b> Field search suffixes are required.  Use field_match=, not field=.',
  'Erase' => 'Erase',
  'Everyone' => 'Everyone',
  'Example:' => 'Example:',
  'Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload Dir</a> in General Settings)' => 'Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload Dir</a> in General Settings)',
  'Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload URL</a> in General Settings)' => 'Example: custom or ../custom (relative to <a href="?menu=admin&action=general" target="_top">Upload URL</a> in General Settings)',
  'Example: plugin1.php, plugin2.php' => 'Example: plugin1.php, plugin2.php',
  'Expert mode - don\'t show instructions or extra html in Code Generator output' => 'Expert mode - don\'t show instructions or extra html in Code Generator output',
  'Feed Description' => 'Feed Description',
  'Feed Language' => 'Feed Language',
  'Feed Link' => 'Feed Link',
  'Feed Options' => 'Feed Options',
  'Feed Title' => 'Feed Title',
  'Field Addons' => 'Field Addons',
  'Field Attributes' => 'Field Attributes',
  'Field Description' => 'Field Description',
  'Field Editor' => 'Field Editor',
  'Field Height' => 'Field Height',
  'Field Label' => 'Field Label',
  'Field Name' => 'Field Name',
  'Field Options' => 'Field Options',
  'Field Prefix' => 'Field Prefix',
  'Field Type' => 'Field Type',
  'Field Width' => 'Field Width',
  'File Uploads' => 'File Uploads',
  'File extensions allowed: ' => 'File extensions allowed: ',
  'File size limits' => 'File size limits',
  'Filename Fields' => 'Filename Fields',
  'Filter results based on search form input (disable for multiple viewers on one page)' => 'Filter results based on search form input (disable for multiple viewers on one page)',
  'First - The first category will be selected' => 'First - The first category will be selected',
  'Footer HTML' => 'Footer HTML',
  'For Debugging' => 'For Debugging',
  'For menu branches - only categories below this one will be displayed' => 'For menu branches - only categories below this one will be displayed',
  'Forgot your password?' => 'Forgot your password?',
  'Form Builder' => 'Form Builder',
  'Form Generator' => 'Form Generator',
  'Free: %1$s, Total: %2$s' => 'Free: %1$s, Total: %2$s',
  'Frequency' => 'Frequency',
  'Full Width' => 'Full Width',
  'Function' => 'Function',
  'General' => 'General',
  'General Settings' => 'General Settings',
  'Generate an RSS Feed for vistors to subscribe to.' => 'Generate an RSS Feed for vistors to subscribe to.',
  'Get options from MySQL query (advanced)' => 'Get options from MySQL query (advanced)',
  'Get options from database (advanced)' => 'Get options from database (advanced)',
  'Go Back' => 'Go Back',
  'HTML' => 'HTML',
  'Header Bar' => 'Header Bar',
  'Header Image URL' => 'Header Image URL',
  'Help' => 'Help',
  'Help (?) URL' => 'Help (?) URL',
  'Help URL' => 'Help URL',
  'Hide Menu' => 'Hide Menu',
  'Hide PHP Errors' => 'Hide PHP Errors',
  'Hide PHP Errors (for production and live web servers).' => 'Hide PHP Errors (for production and live web servers).',
  'Hide all PHP errors and warnings (still logged to <a href=\'?menu=_error_log\'>developer log</a>)' => 'Hide all PHP errors and warnings (still logged to <a href=\'?menu=_error_log\'>developer log</a>)',
  'High - Larger file size, high quality' => 'High - Larger file size, high quality',
  'Hook Name' => 'Hook Name',
  'Hostname' => 'Hostname',
  'Hours' => 'Hours',
  'How Many' => 'How Many',
  'How to configure PHP uploads' => 'How to configure PHP uploads',
  'How to send mail' => 'How to send mail',
  'INT (max: +/- 2 billion, doesn\'t support decimals)' => 'INT (max: +/- 2 billion, doesn\'t support decimals)',
  'If cropped, the images are resized to fill up the selected width and height' => 'If cropped, the images are resized to fill up the selected width and height',
  'If your server is expiring login sessions too quickly set this to a new directory outside of your web root or leave blank for default value of:' => 'If your server is expiring login sessions too quickly set this to a new directory outside of your web root or leave blank for default value of:',
  'Image Resizing Quality' => 'Image Resizing Quality',
  'Inactive Plugins' => 'Inactive Plugins',
  'Indent on menubar' => 'Indent on menubar',
  'Indented Text' => 'Indented Text',
  'Input Validation' => 'Input Validation',
  'Instructions' => 'Instructions',
  'Invalid value' => 'Invalid value',
  'Invalid value, check dir exists!' => 'Invalid value, check dir exists!',
  'Javascript is disabled or is not supported by your browser. Please upgrade your browser or enable Javascript to navigate the interface properly.' => 'Javascript is disabled or is not supported by your browser. Please upgrade your browser or enable Javascript to navigate the interface properly.',
  'Kbytes (uncheck for unlimited)' => 'Kbytes (uncheck for unlimited)',
  'Languages are in %s' => 'Languages are in %s',
  'Large' => 'Large',
  'Last Run' => 'Last Run',
  'Leave blank for 5 years before and after current year' => 'Leave blank for 5 years before and after current year',
  'License' => 'License',
  'License Agreement' => 'License Agreement',
  'License Company Name' => 'License Company Name',
  'License Domain Name' => 'License Domain Name',
  'License Information' => 'License Information',
  'License Product ID' => 'License Product ID',
  'List Actions' => 'List Actions',
  'List Options' => 'List Options',
  'List Page' => 'List Page',
  'List Page Url' => 'List Page Url',
  'List Page Viewer' => 'List Page Viewer',
  'List Viewer Options' => 'List Viewer Options',
  'ListPage Fields' => 'ListPage Fields',
  'Loading...' => 'Loading...',
  'Local Time' => 'Local Time',
  'Log Only - Log messages but don\'t send them (debug mode)' => 'Log Only - Log messages but don\'t send them (debug mode)',
  'Log Summary: ' => 'Log Summary: ',
  'Login' => 'Login',
  'Login Timeouts' => 'Login Timeouts',
  'Logoff (%s)' => 'Logoff (%s)',
  'MEDIUMTEXT (max: 16 megs)' => 'MEDIUMTEXT (max: 16 megs)',
  'Max Connections' => 'Max Connections',
  'Max Depth' => 'Max Depth',
  'Max Length' => 'Max Length',
  'Max Records' => 'Max Records',
  'Max level of depth for categories (leave blank for unlimited)' => 'Max level of depth for categories (leave blank for unlimited)',
  'Max records for this section (leave blank for unlimited)' => 'Max records for this section (leave blank for unlimited)',
  'Max records per user (leave blank for unlimited)' => 'Max records per user (leave blank for unlimited)',
  'Maximum - Very large file size, best quality' => 'Maximum - Very large file size, best quality',
  'Maximum upload size:' => 'Maximum upload size:',
  'Maximum uploads:' => 'Maximum uploads:',
  'Medium' => 'Medium',
  'Menu' => 'Menu',
  'Menu Group' => 'Menu Group',
  'Menu Icon' => 'Menu Icon',
  'Menu Name' => 'Menu Name',
  'Menu Options' => 'Menu Options',
  'Menu Type' => 'Menu Type',
  'Min Length' => 'Min Length',
  'Minimum - Smallest file size, some quality loss' => 'Minimum - Smallest file size, some quality loss',
  'Minutes' => 'Minutes',
  'Modify' => 'Modify',
  'Modifying system fields may cause your program to stop working correctly!' => 'Modifying system fields may cause your program to stop working correctly!',
  'More "Search" Link' => 'More "Search" Link',
  'Multi Record' => 'Multi Record',
  'Multi record sections: Get record # from end of url. eg: viewer.php?record_title-3' => 'Multi record sections: Get record # from end of url. eg: viewer.php?record_title-3',
  'My Account' => 'My Account',
  'My Account - Show this field in "My Account" section' => 'My Account - Show this field in "My Account" section',
  'MySQL Column Type' => 'MySQL Column Type',
  'MySQL Indexes' => 'MySQL Indexes',
  'MySQL Indexing' => 'MySQL Indexing',
  'MySQL Table' => 'MySQL Table',
  'MySQL Time' => 'MySQL Time',
  'MySQL Where' => 'MySQL Where',
  'MySQL v%s' => 'MySQL v%s',
  'Need a custom plugin? Contact your vendor at %s' => 'Need a custom plugin? Contact your vendor at %s',
  'Never' => 'Never',
  'Newsletter Builder' => 'Newsletter Builder',
  'No' => 'No',
  'No backup file selected!' => 'No backup file selected!',
  'No category is selected!' => 'No category is selected!',
  'No records were found!' => 'No records were found!',
  'None' => 'None',
  'None - No category will be selected' => 'None - No category will be selected',
  'None / Blank' => 'None / Blank',
  'None detected' => 'None detected',
  'Normal - Good balance of quality and file size' => 'Normal - Good balance of quality and file size',
  'One Level' => 'One Level',
  'Only allow characters:' => 'Only allow characters:',
  'Only allow users to login from these IP addresses.  eg: 1.2.3.4, 4.4.4.4 (Your IP is: %s)' => 'Only allow users to login from these IP addresses.  eg: 1.2.3.4, 4.4.4.4 (Your IP is: %s)',
  'Only allow users to login via secure HTTPS connections' => 'Only allow users to login via secure HTTPS connections',
  'Operating System' => 'Operating System',
  'Order By' => 'Order By',
  'Other Generators' => 'Other Generators',
  'Other/Custom (enter MySQL column type below)' => 'Other/Custom (enter MySQL column type below)',
  'Outgoing Mail' => 'Outgoing Mail',
  'Output HTML Style' => 'Output HTML Style',
  'Overview' => 'Overview',
  'Overview & Setup' => 'Overview & Setup',
  'PHP Reference' => 'PHP Reference',
  'PHP Version' => 'PHP Version',
  'PHP disabled functions' => 'PHP disabled functions',
  'PHP is running as user' => 'PHP is running as user',
  'Password' => 'Password',
  'Password Field - hide text that users enter (show values as *****)' => 'Password Field - hide text that users enter (show values as *****)',
  'Per Page' => 'Per Page',
  'Please select a section!' => 'Please select a section!',
  'Please select a value for \'which record\'' => 'Please select a value for \'which record\'',
  'Plugin Developers! <a href="%s">Click here</a> for a list of plugin hooks' => 'Plugin Developers! <a href="%s">Click here</a> for a list of plugin hooks',
  'Plugin Hooks' => 'Plugin Hooks',
  'Plugin-created' => 'Plugin-created',
  'Plugins' => 'Plugins',
  'Plugins can add their own code generators here' => 'Plugins can add their own code generators here',
  'Powered By HTML' => 'Powered By HTML',
  'Preview Page Url' => 'Preview Page Url',
  'Preview:' => 'Preview:',
  'Private Label Branding' => 'Private Label Branding',
  'Private Labeling' => 'Private Labeling',
  'Program Directory' => 'Program Directory',
  'Program Language' => 'Program Language',
  'Program Name' => 'Program Name',
  'Program Name / Titlebar' => 'Program Name / Titlebar',
  'Program Url' => 'Program Url',
  'Program Version' => 'Program Version',
  'Quick Add' => 'Quick Add',
  'Quick Add a Field' => 'Quick Add a Field',
  'RSS Feed' => 'RSS Feed',
  'Random - show records in random order' => 'Random - show records in random order',
  'Recent Activity' => 'Recent Activity',
  'Record Sorting' => 'Record Sorting',
  'Regional Settings' => 'Regional Settings',
  'Related Records' => 'Related Records',
  'Related Table' => 'Related Table',
  'Rename admin.php to something unique such as admin_1223f91eb75e4304e9cd.php' => 'Rename admin.php to something unique such as admin_1223f91eb75e4304e9cd.php',
  'Reported by' => 'Reported by',
  'Require HTTPS' => 'Require HTTPS',
  'Required' => 'Required',
  'Required Plugins' => 'Required Plugins',
  'Resize images larger than:' => 'Resize images larger than:',
  'Resized images are proportionally scaled down versions of the original images' => 'Resized images are proportionally scaled down versions of the original images',
  'Resized images maintain proportions, height and width are "maximum" values.' => 'Resized images maintain proportions, height and width are "maximum" values.',
  'Restore' => 'Restore',
  'Restore data from this backup file?' => 'Restore data from this backup file?',
  'Restrict IP Access' => 'Restrict IP Access',
  'Root (show all categories)' => 'Root (show all categories)',
  'SMTP Hostname & Port' => 'SMTP Hostname & Port',
  'SMTP Password' => 'SMTP Password',
  'SMTP Server - Secured connection using SSL' => 'SMTP Server - Secured connection using SSL',
  'SMTP Server - Secured connection using TLS' => 'SMTP Server - Secured connection using TLS',
  'SMTP Server - Unsecured connection' => 'SMTP Server - Unsecured connection',
  'SMTP Username' => 'SMTP Username',
  'Save' => 'Save',
  'Save Details' => 'Save Details',
  'Save full URL for local links and images (for viewers on other domains)' => 'Save full URL for local links and images (for viewers on other domains)',
  'Save this code as' => 'Save this code as',
  'Save this code as <b>.htaccess</b> (or choose your own name)' => 'Save this code as <b>.htaccess</b> (or choose your own name)',
  'Scheduled Tasks' => 'Scheduled Tasks',
  'Search Fields' => 'Search Fields',
  'Searching' => 'Searching',
  'Section Editors' => 'Section Editors',
  'Section Name' => 'Section Name',
  'Section Tablename' => 'Section Tablename',
  'Section Type' => 'Section Type',
  'Security Modules' => 'Security Modules',
  'Security Settings' => 'Security Settings',
  'Security Tips' => 'Security Tips',
  'Security Warning: A manually entered link with user input was detected.
If you didn\'t type this url in yourself, please close this browser window.
' => 'Security Warning: A manually entered link with user input was detected.
If you didn\'t type this url in yourself, please close this browser window.
',
  'Select Existing Section' => 'Select Existing Section',
  'Select Preset' => 'Select Preset',
  'Select Section' => 'Select Section',
  'Select version to restore' => 'Select version to restore',
  'Send &amp; Log - Send mail and save copies under <a href=\'%s\'>Outgoing Mail</a>' => 'Send &amp; Log - Send mail and save copies under <a href=\'%s\'>Outgoing Mail</a>',
  'Send Only - Send mail without keeping a copy (default)' => 'Send Only - Send mail without keeping a copy (default)',
  'Separator Type' => 'Separator Type',
  'Server Info' => 'Server Info',
  'Server Resource Limits' => 'Server Resource Limits',
  'Set login timeout to 30 minutes or less.' => 'Set login timeout to 30 minutes or less.',
  'Setup' => 'Setup',
  'Show %s records per page with prev &amp; next page links' => 'Show %s records per page with prev &amp; next page links',
  'Show %s uploads' => 'Show %s uploads',
  'Show All' => 'Show All',
  'Show Code' => 'Show Code',
  'Show Code  &gt;&gt;' => 'Show Code  &gt;&gt;',
  'Show Code &gt;&gt;' => 'Show Code &gt;&gt;',
  'Show Uploads' => 'Show Uploads',
  'Show a hierarchy of records from a category section.' => 'Show a hierarchy of records from a category section.',
  'Show all records' => 'Show all records',
  'Show all uploads' => 'Show all uploads',
  'Show many records.' => 'Show many records.',
  'Show one record on the page.' => 'Show one record on the page.',
  'Show server upload limits' => 'Show server upload limits',
  'Show the first ' => 'Show the first ',
  'Show the first %s records only' => 'Show the first %s records only',
  'Single Record' => 'Single Record',
  'Single Record</b> - single record menus have only one record
    and are for single page sections such as About Us, or Contact Us.' => 'Single Record</b> - single record menus have only one record
    and are for single page sections such as About Us, or Contact Us.',
  'Single Record</b> - single record menus have only one record and are for single page sections such as About Us, or Contact Us.' => 'Single Record</b> - single record menus have only one record and are for single page sections such as About Us, or Contact Us.',
  'Single record sections: Load first record in database' => 'Single record sections: Load first record in database',
  'Small' => 'Small',
  'Sorting' => 'Sorting',
  'Source Code' => 'Source Code',
  'Specify Seconds' => 'Specify Seconds',
  'Specify Time' => 'Specify Time',
  'Specify custom date (or strtotime value) below:' => 'Specify custom date (or strtotime value) below:',
  'Status' => 'Status',
  'Sublicensing' => 'Sublicensing',
  'Summary' => 'Summary',
  'System Field - restrict field editor access to this field' => 'System Field - restrict field editor access to this field',
  'System Plugin' => 'System Plugin',
  'System Plugins' => 'System Plugins',
  'TINYTEXT (max: 255 chars)' => 'TINYTEXT (max: 255 chars)',
  'Table Name' => 'Table Name',
  'Table Prefix' => 'Table Prefix',
  'Task Log' => 'Task Log',
  'Temporarily disable background tasks (for debugging or maintenance)' => 'Temporarily disable background tasks (for debugging or maintenance)',
  'Text Link' => 'Text Link',
  'There are PHP errors in the <a href=\'?menu=_error_log\'>developer log</a>.  Review them and then clear the developer log.' => 'There are PHP errors in the <a href=\'?menu=_error_log\'>developer log</a>.  Review them and then clear the developer log.',
  'There are current no generators in this category.' => 'There are current no generators in this category.',
  'There are currently no %s.' => 'There are currently no %s.',
  'There are no advanced options for this field type.' => 'There are no advanced options for this field type.',
  'There are no backups available' => 'There are no backups available',
  'There are no options for this field type.' => 'There are no options for this field type.',
  'There are no validation rules for this field type.' => 'There are no validation rules for this field type.',
  'These are older hooks which should no longer be used. Plugins using these hooks will likely need to be updated. More information about the deprecation of specific hooks may be found in the software\'s changelog.' => 'These are older hooks which should no longer be used. Plugins using these hooks will likely need to be updated. More information about the deprecation of specific hooks may be found in the software\'s changelog.',
  'These are the default hooks provided by the software.' => 'These are the default hooks provided by the software.',
  'These extra fields will be displayed on the upload form and available in viewers.' => 'These extra fields will be displayed on the upload form and available in viewers.',
  'These fields are displayed on the <a href="?menu=%s">editor list page</a> (beside modify and erase)<br>
                <input class="form-control setAttr-spellcheck-false" type="text" name="listPageFields" id="listPageFields" value="%s" size="75"><br>
                example: field1, field2' => 'These fields are displayed on the <a href="?menu=%s">editor list page</a> (beside modify and erase)<br>
                <input class="form-control setAttr-spellcheck-false" type="text" name="listPageFields" id="listPageFields" value="%s" size="75"><br>
                example: field1, field2',
  'These fields are displayed on the <a href="?menu=%s">editor list page</a> (beside modify and erase)<br>
        <input class="text-input wide-input setAttr-spellcheck-false" type="text" name="listPageFields" value="%s" size="75" style="width: 300px;"><br>
        example: field1, field2' => 'These fields are displayed on the <a href="?menu=%s">editor list page</a> (beside modify and erase)<br>
        <input class="text-input wide-input setAttr-spellcheck-false" type="text" name="listPageFields" value="%s" size="75" style="width: 300px;"><br>
        example: field1, field2',
  'These fields are displayed on the<a href="?menu=%s">editor list page</a>(beside modify and erase)<br>
        <input class="text-input wide-input setAttr-spellcheck-false" type="text" name="listPageFields" value="%s" size="75" style="width: 300px;"><br>
        example: field1, field2' => 'These fields are displayed on the<a href="?menu=%s">editor list page</a>(beside modify and erase)<br>
        <input class="text-input wide-input setAttr-spellcheck-false" type="text" name="listPageFields" value="%s" size="75" style="width: 300px;"><br>
        example: field1, field2',
  'These hooks are added by plugins. This can be done to allow interaction between different plugins, or to allow easier customization of plugin behaviour by developers.' => 'These hooks are added by plugins. This can be done to allow interaction between different plugins, or to allow easier customization of plugin behaviour by developers.',
  'These hooks are registered, but never called. They may be older hooks which are no longer used (but were not added to the deprecated list,) or they may have belonged to plugins which are no longer installed. Although using these hooks will have no effect, plugins calling these hooks should be checked over to make sure they are still working correctly.' => 'These hooks are registered, but never called. They may be older hooks which are no longer used (but were not added to the deprecated list,) or they may have belonged to plugins which are no longer installed. Although using these hooks will have no effect, plugins calling these hooks should be checked over to make sure they are still working correctly.',
  'These sorting settings are used for both the <a href="?menu=every_field_multi">editor</a> and the viewers (although viewers can override them).
Here are some common sorting values that can be used here and in the viewers.  Use your         own fieldnames instead of the <i>examples</i>.<br>

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

<b>Tip:</b>  The "Order By" field is actually just the standard MySQL ORDER BY clause of a SELECT statement.  So if you\'re familiar with MySQL you can enter any ORDER BY clause you want here.  Otherwise, congratulations! You just learned some MySQL!' => 'These sorting settings are used for both the <a href="?menu=every_field_multi">editor</a> and the viewers (although viewers can override them).
Here are some common sorting values that can be used here and in the viewers.  Use your         own fieldnames instead of the <i>examples</i>.<br>

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

<b>Tip:</b>  The "Order By" field is actually just the standard MySQL ORDER BY clause of a SELECT statement.  So if you\'re familiar with MySQL you can enter any ORDER BY clause you want here.  Otherwise, congratulations! You just learned some MySQL!',
  'These tips are custom generated and apply to the current server and connection:' => 'These tips are custom generated and apply to the current server and connection:',
  'These urls are used when creating links to viewers for this section.' => 'These urls are used when creating links to viewers for this section.',
  'This email is used as the "From:" address on password reminder emails.' => 'This email is used as the "From:" address on password reminder emails.',
  'This page lists where Plugin Hooks are called and used. To learn more about a hook, open a file where it\'s called and search for the Hook Name.' => 'This page lists where Plugin Hooks are called and used. To learn more about a hook, open a file where it\'s called and search for the Hook Name.',
  'This should be a valid email address that is checked for email.' => 'This should be a valid email address that is checked for email.',
  'Timezone Name' => 'Timezone Name',
  'Tiny' => 'Tiny',
  'Tip: Your table name is used in your viewer code so choose a short but meaningful name such as: news, articles, jobs, etc.' => 'Tip: Your table name is used in your viewer code so choose a short but meaningful name such as: news, articles, jobs, etc.',
  'Title field' => 'Title field',
  'Title/Name field' => 'Title/Name field',
  'To change %1$s settings edit %2$s' => 'To change %1$s settings edit %2$s',
  'To setup Background Tasks, add a server cronjob or \'scheduled task\' to execute the following command every minute:' => 'To setup Background Tasks, add a server cronjob or \'scheduled task\' to execute the following command every minute:',
  'To support multiple subdomains set to parent domain (eg: example.com), or leave blank to default to current domain.' => 'To support multiple subdomains set to parent domain (eg: example.com), or leave blank to default to current domain.',
  'Two Level' => 'Two Level',
  'Type' => 'Type',
  'Unavailable' => 'Unavailable',
  'Unchecked' => 'Unchecked',
  'Unchecked Value' => 'Unchecked Value',
  'Unique' => 'Unique',
  'Unknown advanced type' => 'Unknown advanced type',
  'Unordered List (UL, LI tags)' => 'Unordered List (UL, LI tags)',
  'Update the ' => 'Update the ',
  'Update the urls and filenames to match the viewers you create.' => 'Update the urls and filenames to match the viewers you create.',
  'Update these fields to reflect your company information.' => 'Update these fields to reflect your company information.',
  'Upload Directory' => 'Upload Directory',
  'Upload Fields' => 'Upload Fields',
  'Upload Folder URL' => 'Upload Folder URL',
  'Upload Settings' => 'Upload Settings',
  'Upload settings' => 'Upload settings',
  'Upload time limits' => 'Upload time limits',
  'Use 24 Hour Time' => 'Use 24 Hour Time',
  'Use Datepicker' => 'Use Datepicker',
  'Use PHP\'s built-in mail() function (default)' => 'Use PHP\'s built-in mail() function (default)',
  'Use a secure https:// url to access this program.  You are currently using an insecure connection.' => 'Use a secure https:// url to access this program.  You are currently using an insecure connection.',
  'Use custom upload directory' => 'Use custom upload directory',
  'Use options listed below' => 'Use options listed below',
  'Use this field for option labels' => 'Use this field for option labels',
  'Use this field for option values' => 'Use this field for option values',
  'Username' => 'Username',
  'VARCHAR(255) (max: 255 chars)' => 'VARCHAR(255) (max: 255 chars)',
  'Vendor' => 'Vendor',
  'Vendor Location' => 'Vendor Location',
  'Vendor Name' => 'Vendor Name',
  'Vendor Url' => 'Vendor Url',
  'Version' => 'Version',
  'View' => 'View',
  'View PHP Errors and Warnings' => 'View PHP Errors and Warnings',
  'View Website &gt;&gt;' => 'View Website &gt;&gt;',
  'View Website >>' => 'View Website >>',
  'Viewer Options' => 'Viewer Options',
  'Viewer Urls' => 'Viewer Urls',
  'Viewers: Hide records that are "Created By" a user who is: deleted, disabled, or expired' => 'Viewers: Hide records that are "Created By" a user who is: deleted, disabled, or expired',
  'WARNING: BACKUP DATA WILL OVERWRITE EXISTING DATA!' => 'WARNING: BACKUP DATA WILL OVERWRITE EXISTING DATA!',
  'WYSIWYG Language' => 'WYSIWYG Language',
  'WYSIWYG Options' => 'WYSIWYG Options',
  'Warn on external referers/links and require internal referer to submit data to CMS.' => 'Warn on external referers/links and require internal referer to submit data to CMS.',
  'Warning: Background tasks have not run in the last hour, please follow the instructions below to enable background tasks.' => 'Warning: Background tasks have not run in the last hour, please follow the instructions below to enable background tasks.',
  'Warning: Outgoing Mail is currently set to \'Log Only\'.' => 'Warning: Outgoing Mail is currently set to \'Log Only\'.',
  'Web Server' => 'Web Server',
  'Website Prefix URL (optional)' => 'Website Prefix URL (optional)',
  'Website Root Directory' => 'Website Root Directory',
  'When <a href=\'?menu=_error_log\'>php errors</a> are detected send admin a <a href=\'?menu=_email_templates\'>notification email</a>' => 'When <a href=\'?menu=_error_log\'>php errors</a> are detected send admin a <a href=\'?menu=_email_templates\'>notification email</a>',
  'Where it\'s called...' => 'Where it\'s called...',
  'Where it\'s used...' => 'Where it\'s used...',
  'Which Record' => 'Which Record',
  'Year Range' => 'Year Range',
  'Yes' => 'Yes',
  'You can add CSS themes in %s' => 'You can add CSS themes in %s',
  'You can add CSS themes in <code>%s</code>' => 'You can add CSS themes in <code>%s</code>',
  'all database tables' => 'all database tables',
  'author' => 'author',
  'category menus let you organize records in a tree structure and are for creating website menus and navigation.' => 'category menus let you organize records in a tree structure and are for creating website menus and navigation.',
  'characters' => 'characters',
  'checkbox' => 'checkbox',
  'checkboxes (multi value)' => 'checkboxes (multi value)',
  'clear all' => 'clear all',
  'copy an existing section.' => 'copy an existing section.',
  'crop' => 'crop',
  'date' => 'date',
  'date DESC' => 'date DESC',
  'date/time' => 'date/time',
  'days' => 'days',
  'displayed after or below field' => 'displayed after or below field',
  'displayed before or above field' => 'displayed before or above field',
  'eg: /~username or /development/client-name' => 'eg: /~username or /development/client-name',
  'enabled' => 'enabled',
  'entries' => 'entries',
  'erase' => 'erase',
  'errors' => 'errors',
  'example: /news/newsDetail.php' => 'example: /news/newsDetail.php',
  'example: /news/newsList.php' => 'example: /news/newsList.php',
  'example: field1, field2<br>
These fields are added to viewer links to create more descriptive urls for users<br>
and search engines. The first field value that isn\'t blank is used.<br>
Example Url: viewer.php?record_title_goes_here-123<br>' => 'example: field1, field2<br>
These fields are added to viewer links to create more descriptive urls for users<br>
and search engines. The first field value that isn\'t blank is used.<br>
Example Url: viewer.php?record_title_goes_here-123<br>',
  'files (uncheck for unlimited, set to 0 for none)' => 'files (uncheck for unlimited, set to 0 for none)',
  'for Server Admins' => 'for Server Admins',
  'for server admins' => 'for server admins',
  'for this section with the new url' => 'for this section with the new url',
  'go' => 'go',
  'height' => 'height',
  'hidden' => 'hidden',
  'hide all' => 'hide all',
  'hours' => 'hours',
  'indexed' => 'indexed',
  'info1' => 'info1',
  'info2' => 'info2',
  'info3' => 'info3',
  'info4' => 'info4',
  'info5' => 'info5',
  'label' => 'label',
  'leave blank to use Detail Page Url' => 'leave blank to use Detail Page Url',
  'license agreement' => 'license agreement',
  'list' => 'list',
  'loading...' => 'loading...',
  'menu groups let you create menu headers to group related menu options under.' => 'menu groups let you create menu headers to group related menu options under.',
  'minutes' => 'minutes',
  'modify' => 'modify',
  'months' => 'months',
  'next' => 'next',
  'none' => 'none',
  'open_basedir restrictions' => 'open_basedir restrictions',
  'or choose your own name' => 'or choose your own name',
  'pixels (leave blank for default height)' => 'pixels (leave blank for default height)',
  'pixels (leave blank for default width)' => 'pixels (leave blank for default width)',
  'pre-configured menus and fields for common websites sections.' => 'pre-configured menus and fields for common websites sections.',
  'price' => 'price',
  'private' => 'private',
  'pulldown' => 'pulldown',
  'pulldown (multi value)' => 'pulldown (multi value)',
  'radio buttons' => 'radio buttons',
  'records only (leave blank for all)' => 'records only (leave blank for all)',
  'recreate' => 'recreate',
  'run now >>' => 'run now >>',
  'select an advanced menu type to see the description.' => 'select an advanced menu type to see the description.',
  'select field' => 'select field',
  'select table' => 'select table',
  'separator' => 'separator',
  'session.cookie_domain' => 'session.cookie_domain',
  'session.save_path' => 'session.save_path',
  'show all' => 'show all',
  'show all options' => 'show all options',
  'text box' => 'text box',
  'text field' => 'text field',
  'text links let you add an external link to your menu that looks the same as a regular menu item.' => 'text links let you add an external link to your menu that looks the same as a regular menu item.',
  'title' => 'title',
  'title, content' => 'title, content',
  'total' => 'total',
  'upload' => 'upload',
  'use 24 hour time when specifying hours' => 'use 24 hour time when specifying hours',
  'user may not enter the same value as another record (not case-sensitive)' => 'user may not enter the same value as another record (not case-sensitive)',
  'user may not leave field blank' => 'user may not leave field blank',
  'user specifies seconds (requires &quot;Specify Time&quot; to be enabled)' => 'user specifies seconds (requires &quot;Specify Time&quot; to be enabled)',
  'user specifies time (hour, minutes, and optionally seconds)' => 'user specifies time (hour, minutes, and optionally seconds)',
  'width' => 'width',
  'wysiwyg' => 'wysiwyg',
);
?>
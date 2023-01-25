<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('General Settings') => '?menu=admin&action=general' ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=adminSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=general',   'label' => t('Cancel'), ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'adminSave', ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// add extra html before form
$adminUI['PRE_FORM_HTML'] = ob_capture('_getPreFormContent');

// add extra html after the form
$adminUI['POST_FORM_HTML'] = ob_capture('_getPostFormContent');

// compose and output the page
adminUI($adminUI);


// check is Suhosin is detected on server.  Returns version found or false
function _suhosinVersionDetected() {
  static $suhosin_detected, $suhosin_detected_version, $isCached;
  if (isset($isCached)) { return $suhosin_detected_version; } // caching
  $isCached = true;

  // get phpinfo() content
  ob_start(); @phpinfo(INFO_GENERAL); $phpinfo_general = ob_get_contents(); ob_end_clean();
  ob_start(); @phpinfo(INFO_MODULES); $phpinfo_modules = ob_get_contents(); ob_end_clean();
  $phpinfo_general      = preg_replace("/&nbsp;/i", " ", $phpinfo_general);
  $phpinfo_modules_text = strip_tags($phpinfo_modules);

  // suhosin detection
  $suhosin_in_phpinfo_general = (preg_match("/(Suhosin( Patch)? [\d\.]+)/i", $phpinfo_general, $matches) ? $matches[0] : '');
  $suhosin_in_phpinfo_modules = (preg_match("/(Suhosin.*?[0-9][\d\.]+)/i", $phpinfo_modules_text, $matches) ? $matches[0] : '');
  $suhosin_ini                = @ini_get_all('suhosin');
  $suhosin_ini_get_all_count  = $suhosin_ini ? count($suhosin_ini) : 0;
  $suhosin_funcs_as_csv       = get_extension_funcs('suhosin') !== false ? implode(', ', get_extension_funcs('suhosin')) : '';
  $suhosin_extension_loaded   = extension_loaded('suhosin');   // http://stackoverflow.com/questions/3383916/how-to-check-whether-suhosin-is-installed
  $suhosin_patch_constant     = defined('SUHOSIN_PATCH') ? constant('SUHOSIN_PATCH') : false;    // http://stackoverflow.com/questions/3383916/how-to-check-whether-suhosin-is-installed
  // future: Check for Suhosin easter egg image: any_php_file.php?=SUHO8567F54-D428-14d2-A769-00DA302A5F18
  $suhosin_detected           = $suhosin_in_phpinfo_general || $suhosin_in_phpinfo_modules || $suhosin_ini_get_all_count || $suhosin_extension_loaded || $suhosin_patch_constant;
  $suhosin_detected_version   = $suhosin_in_phpinfo_general ?: $suhosin_in_phpinfo_modules ?: 'Suhosin';

  // print suhosin debug data
  $debug = false;
  if ($debug) {
    print "phpinfo(INFO_GENERAL) found string: $suhosin_in_phpinfo_general\n";
    print "phpinfo(INFO_MODULES) found string: $suhosin_in_phpinfo_modules\n";
    print "ini_get_all('suhosin'): $suhosin_ini_get_all_count values\n";
    print "get_extension_funcs('suhosin'): $suhosin_funcs_as_csv\n";
    print "extension_loaded('suhosin'): $suhosin_extension_loaded\n";
    print "defined('SUHOSIN_PATCH'): " . defined('SUHOSIN_PATCH') . "\n";
    print "constant('SUHOSIN_PATCH'): $suhosin_patch_constant\n";
  }

  return $suhosin_detected ? $suhosin_detected_version : false;
}

//
function _getPreFormContent() {
  
  ### SHOW OLD PHP/MYSQL WARNINGS
  $currentPhpVersion   = phpversion();
  $currentMySqlVersion = preg_replace("/[^0-9\.]/", '', mysqli()->server_info);
 
  // Reference - PHP Installed Versions: https://wordpress.org/about/stats/
  // Reference - PHP Installed Versions: https://w3techs.com/technologies/details/pl-php/all/all
  $nextPhpRequired = '7.2'; // Default to minimum version to recommend
  if (time() > strtotime('2020-11-30')) { $nextPhpRequired = '7.3'; } // Security support for previous version ends on this date: http://php.net/supported-versions.php
  if (time() > strtotime('2021-12-06')) { $nextPhpRequired = '7.4'; } // Security support for previous version ends on this date: http://php.net/supported-versions.php
  // not yet announced // if (time() > strtotime('2021-12-06')) { $nextPhpRequired = '7.5'; } // Security support for previous version ends on this date: http://php.net/supported-versions.php

  $nextMySqlRequired   = '5.5'; // to support utf8mb4 : http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html
  $isPhpUnsupported    = version_compare($currentPhpVersion, $nextPhpRequired) < 0;
  $isMySqlUnsupported  = version_compare($currentMySqlVersion, $nextMySqlRequired) < 0;
  $isSecurityIssue     = ($isPhpUnsupported || $isMySqlUnsupported);

  // Check for missing or soon to be required extensions
  $missingExtensions   = [];
  foreach (array('mysqli','openssl','curl') as $extension) {
    if (!extension_loaded($extension)) { $missingExtensions[] = $extension; }
  }
  
  if ($isSecurityIssue || $missingExtensions) {
    ?>
    <div style='color: #C00; border: solid 2px #C00; padding: 8px; background: #FFF; font-size: 14px; '>
      <?php if ($isSecurityIssue): ?>
        <b>Security Notice:</b>
        You are currently running old and unsupported server software that <b>no longer receives security updates</b>.
        To avoid being exposed to unpatched security vulnerabilities and to ensure compatibility with future CMS releases, please upgrade at your earliest convenience.<br>
      <?php else: ?>
        <b>Upgrade Warning:</b>
        You are currently missing some required PHP extensions.
        To ensure compatibility with future CMS releases, please have these extensions installed at your earliest convenience.<br>
      <?php endif ?>

      <div style="padding: 5px 5px 5px 25px;">
        <?php if ($isPhpUnsupported): ?>
          <li>Upgrade to <b>PHP v<?php echo $nextPhpRequired ?></b> or newer (Your server is running PHP v<?php echo $currentPhpVersion ?>)
        <?php endif ?>
        <?php if ($isMySqlUnsupported): ?>
          <li>Upgrade to <b>MySQL v<?php echo $nextMySqlRequired ?></b> or newer (Your server is running MySQL v<?php echo $currentMySqlVersion ?>)
        <?php endif ?>
        <?php foreach ($missingExtensions as $extension): ?>
          <li>Install missing PHP extension: <b><?php echo htmlencode($extension); ?></b> (required for future updates)
        <?php endforeach ?>
      </div>

      <?php if ($isSecurityIssue): ?>
        More information:
        <a href="http://php.net/supported-versions.php" target="_blank">PHP Supported Versions</a>,
        <a href="https://en.wikipedia.org/wiki/MySQL#Release_history" target="_blank">MySQL Supported Versions</a>
      <?php endif ?>
    </div><br>
    <?php
  }

}

function _getPostFormContent() {
  ?>
    <script>
    
      // 
      function updateDatePreviews() {
        var url = "?menu=admin&action=updateDate";
        url    += "&timezone=" + escape( $('#timezone').val() );

        $.ajax({
          url: url,
          dataType: 'json',
          error:   function(XMLHttpRequest, textStatus, errorThrown){
            alert("There was an error sending the request! (" +XMLHttpRequest['status']+" "+XMLHttpRequest['statusText'] + ")\n" + errorThrown);
          },
          success: function(json){
            var error = json[2];
            if (error) { return alert(error); }
            $('#localDate').html(json[0]);
            $('#mysqlDate').html(json[1]);
            //$('#localDate, #mysqlDate').attr('style', 'background-color: #FFFFCC');
          }
        });
      }

    </script>
  <?php
}


function _getContent() {
  global $SETTINGS, $APP, $CURRENT_USER, $TABLE_PREFIX;
  
  // get ulimit limits
  list($maxCpuSeconds, $memoryLimitKbytes, $maxProcessLimit, $ulimitOutput) = getUlimitValues('soft');
  if     ($maxCpuSeconds == '')          { $maxCpuSeconds_formatted = t('none'); }
  elseif ($maxCpuSeconds == 'unlimited') { $maxCpuSeconds_formatted = t('unlimited'); }
  elseif ($maxCpuSeconds == 'unknown')   { $maxCpuSeconds_formatted = t('unknown'); }
  else                                   { $maxCpuSeconds_formatted = "$maxCpuSeconds " . t('seconds'); }
  if     ($memoryLimitKbytes == '')          { $memoryLimit_formatted = t('none'); }
  elseif ($memoryLimitKbytes == 'unlimited') { $memoryLimit_formatted = t('unlimited'); }
  elseif ($memoryLimitKbytes == 'unknown')   { $memoryLimit_formatted = t('unknown'); }
  else                                       { $memoryLimit_formatted = formatBytes($memoryLimitKbytes*1024); }
  $ulimitLink = "?menu=admin&amp;action=ulimit"; 
  
  // calculate disk space
  $totalBytes = @disk_total_space(__DIR__);
  $freeBytes  = @disk_free_space(__DIR__);

  ?>
  <script>
    // redirect old links to sections that have moved elsewhere
    if (location.hash == '#background-tasks')  { window.location = '?menu=admin&action=bgtasks'; }        // redirect ?menu=admin&action=general#background-tasks
    if (location.hash == '#email-settings')    { window.location = '?menu=admin&action=email'; }          // redirect ?menu=admin&action=general#email-settings
    if (location.hash == '#backup-restore')    { window.location = '?menu=admin&action=backuprestore'; }  // redirect ?menu=admin&action=general#backup-restore
    if (location.hash == '#security-settings') { window.location = '?menu=admin&action=security'; }       // redirect ?menu=admin&action=general#security-settings
  </script>



      <?php echo adminUI_separator([
          'label' => t('Program Information'),
          'href'  => "?menu=admin&action=general#program-info",
          'id'    => "program-info",
        ]);
      ?>

    <div class="form-horizontal">

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php et('Program Name');?></div>
        <div class="col-sm-8 form-control-static">
          <?php echo htmlencode($SETTINGS['programName']) ?>
          v<?php echo htmlencode($APP['version']) ?> (Build <?php echo htmlencode($APP['build']) ?>)
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php et('Vendor'); ?></div>
        <div class="col-sm-8 form-control-static">
          <a href="<?php echo htmlencode($SETTINGS['vendorUrl']) ?>"><?php echo htmlencode($SETTINGS['vendorName']) ?></a>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label"><?php et('License Agreement');?></label>
        <div class="col-sm-8 form-control-static">
            <a href="?menu=license"><?php et('License Agreement');?> &gt;&gt;</a>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Directories & URLs'),
          'href'  => "?menu=admin&action=general#dirs-urls",
          'id'    => "dirs-urls",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="null">
          <?php et('Program Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="null" id="null" value="<?php echo htmlencode($GLOBALS['PROGRAM_DIR']) ?>/">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="adminUrl">
          <?php et('Program Url');?>
        </label>
        <div class="col-sm-8">
            <input class="form-control" type="text" name="adminUrl" id="adminUrl" value="<?php echo htmlencode(@$SETTINGS['adminUrl']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="webRootDir">
          <?php et('Website Root Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="webRootDir" id="webRootDir" value="<?php echo htmlencode(@$SETTINGS['webRootDir']) ?>">
        </div>
      </div>
      
      <div class="form-group">
        <label class="col-sm-3 control-label" for="uploadDir">
          <?php et('Upload Directory');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="uploadDir" id="uploadDir" value="<?php echo htmlencode(@$SETTINGS['uploadDir']) ?>" onkeyup="updateUploadPathPreviews('dir', this.value, 0)" onchange="updateUploadPathPreviews('dir', this.value, 0)">
          <p><?php et('Preview:'); ?> <code id="uploadDirPreview"><?php echo htmlencode(getUploadPathPreview('dir', $SETTINGS['uploadDir'], false, false)); ?></code></p>
          <p><?php et('Example:'); ?> <code>uploads</code> or <code>../uploads</code> (relative to program directory)</p>
        </div>
      </div>
      
      <div class="form-group">
        <label class="col-sm-3 control-label" for="uploadUrl">
          <?php et('Upload Folder URL');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="uploadUrl" id="uploadUrl" value="<?php echo htmlencode(@$SETTINGS['uploadUrl']) ?>" onkeyup="updateUploadPathPreviews('url', this.value, 0)" onchange="updateUploadPathPreviews('url', this.value, 0)">
          <p><?php et('Preview:'); ?> <code id="uploadUrlPreview"><?php echo htmlencode(getUploadPathPreview('url', $SETTINGS['uploadUrl'], false, false)); ?></code></p>
          <p><?php et('Example:'); ?> <code>uploads</code> or <code>../uploads</code> (relative to current URL)</p>
        </div>
      </div>
      
      <div class="form-group">
        <div class="col-sm-3 control-label">
          Server Upload Settings
        </div>
        <div class="col-sm-8">
          <div class="table-wrap">
          <table class="table table-bordered" id="sample-table-1">
            <thead>
              <tr>
                <th><?php et("Upload settings"); ?></th>
                <th><?php et("Upload time limits"); ?></th>
                <th><?php et("File size limits") ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.file-uploads" target="_blank">file_uploads</a>: <?php echo ini_get('file_uploads') ? t('enabled') : t('disabled'); ?></td>
                <td><a href="http://php.net/manual/en/info.configuration.php#ini.max-input-time" target="_blank">max_input_time</a>: <?php echo ini_get('max_input_time') ?></td>
                <td><a href="http://php.net/manual/en/function.disk-free-space.php" target="_blank">free disk space</a>: <?php echo $freeBytes ? formatBytes($freeBytes, 0) : t("Unavailable"); ?></td>
              </tr>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.max-file-uploads" target="_blank">max_file_uploads</a>: <?php echo ini_get('max_file_uploads') ?></td>
                <td><a href="http://php.net/manual/en/info.configuration.php#ini.max-execution-time" target="_blank">max_execution_time</a>: <?php echo ini_get('max_execution_time') ?></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.post-max-size" target="_blank">post_max_size</a>: <?php echo ini_get('post_max_size') ?></td>
              </tr>
              <tr>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.upload-tmp-dir" target="_blank">upload_tmp_dir</a>: <?php echo ini_get('upload_tmp_dir'); ?></td>
                <td><a href="<?php echo $ulimitLink ?>" target="_blank">ulimit max cpu seconds</a>: <?php echo $maxCpuSeconds_formatted; ?></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.upload-max-filesize" target="_blank">upload_max_filesize</a>: <?php echo ini_get('upload_max_filesize') ?></td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td><a href="http://php.net/manual/en/ini.core.php#ini.memory-limit" target="_blank">memory_limit</a>: <?php echo ini_get('memory_limit') ?></td>
              </tr>
              <tr>
                <td></td>
                <td></td>
                <td><a href="<?php echo $ulimitLink ?>" target="_blank">ulimit memory limit</a>: <?php echo $memoryLimit_formatted; ?></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3">
                  <a href="http://www.php.net/manual/en/features.file-upload.php" target="_blank"><?php et('How to configure PHP uploads')?></a>
                  (<?php et('for server admins')?>)
                </th>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="webPrefixUrl">
          <?php et('Website Prefix URL (optional)');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="webPrefixUrl" id="webPrefixUrl" value="<?php echo htmlencode(@$SETTINGS['webPrefixUrl']) ?>">
          eg: <code><?php eht("eg: /~username or /development/client-name"); ?></code>
          <p>If your development server uses a different URL prefix than your live server you can specify it here. This prefix will be automatically added to Viewer URLs and can be displayed with <code>&lt;?php echo PREFIX_URL ?&gt;</code> for other urls. This will allow you to easily move files between a development and live server, even if they have different URL prefixes.</p>
        </div>
      </div>


      <div class="form-group">
        <label class="col-sm-3 control-label" for="helpUrl">
          <?php et('Help (?) URL') ?>
        </label>
        <div class="col-sm-8">
          <input name="helpUrl" type="text" id="helpUrl" class="form-control" value="<?php echo htmlencode($SETTINGS['helpUrl']); ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="websiteUrl">
          <?php et("'View Website' URL") ?>
        </label>
        <div class="col-sm-8">
          <input name="websiteUrl" type="text" id="websiteUrl" class="form-control" value="<?php echo htmlencode($SETTINGS['websiteUrl']) ?>">
        </div>
      </div>



      <?php echo adminUI_separator([
          'label' => t('Regional Settings'),
          'href'  => "?menu=admin&action=general#regional-settings",
          'id'    => "regional-settings",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="timezone">
          <?php et('Timezone Name');?>
        </label>
        <div class="col-sm-8">
          <?php $timeZoneOptions = getTimeZoneOptions($SETTINGS['timezone']); ?>
          <select name="timezone" id="timezone" class="form-control" onchange="updateDatePreviews();">
            <?php echo $timeZoneOptions; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Local Time');?>
        </div>
        <div class="col-sm-8">
          <div class="form-control" id="localDate" >
            <?php
            $offsetSeconds = date("Z");
            $offsetString  = convertSecondsToTimezoneOffset($offsetSeconds);
            $localDate = date("D, M j, Y - g:i:s A") . " ($offsetString)";
            echo $localDate;
            ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('MySQL Time');?>
        </div>
        <div class="col-sm-8">
          <div class="form-control"  id="mysqlDate" >
            <?php
            list($mySqlDate, $mySqlOffset) = mysql_get_query("SELECT NOW(), @@session.time_zone", true);
            echo date("D, M j, Y - g:i:s A", strtotime($mySqlDate)) . " ($mySqlOffset)";
            ?>
          </div>
        </div>
      </div>
      <?php if (!@$SETTINGS['advanced']['hideLanguageSettings']): ?>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="language">
          <?php et('Program Language');?>
        </label>
        <div class="col-sm-8">
          <?php // load language file names - do this here so errors are visible and not hidden in select tags
            $programLange   = []; // key = filename without ext, value = selected boolean
            $programLangDir = "{$GLOBALS['PROGRAM_DIR']}/lib/languages/";
            foreach (scandir($programLangDir) as $filename) {
              @list($basename, $ext) = explode(".", $filename, 2);
              if ($ext != 'php') { continue; }
              if (preg_match("/^_/", $basename)) { continue; } // skip internal scripts
              $programLangs[$basename] = 1;
            }
          ?>
          <select name="language" id="language" class="form-control"><?php // 2.50 the ID is used for direct a-name links ?>
          <option value=''>&lt;select&gt;</option>
          <option value='' <?php selectedIf($SETTINGS['language'], ''); ?>>default</option>
            <?php
              foreach (array_keys($programLangs) as $lang) {
                $selectedAttr = $lang == $SETTINGS['language'] ? 'selected="selected"' : '';
                print "<option value=\"$lang\" $selectedAttr>$lang</option>\n";
              }
            ?>
          </select>
          <?php print sprintf(t('Languages are in %s'),'<code>/lib/languages/</code> or <code>/plugins/.../languages/</code>') ?>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="wysiwygLang">
          <?php et('WYSIWYG Language');?>
        </label>
        <div class="col-sm-8">
          <?php // load language file names - do this here so errors are visible and not hidden in select tags
            $wysiwygLangs   = []; // key = filename without ext, value = selected boolean
            $wysiwygLangDir = "{$GLOBALS['CMS_ASSETS_DIR']}/3rdParty/TinyMCE4/langs/";
            foreach (scandir($wysiwygLangDir) as $filename) {
              @list($basename, $ext) = explode(".", $filename, 2);
              if ($ext != 'js') { continue; }
              $wysiwygLangs[$basename] = 1;
            }
          ?>
          <select name="wysiwygLang" id="wysiwygLang" class="form-control">
          <option value="en">&lt;select&gt;</option>
            <?php
              foreach (array_keys($wysiwygLangs) as $lang) {
                $selectedAttr = $lang == $SETTINGS['wysiwyg']['wysiwygLang'] ? 'selected="selected"' : '';
                print "<option value=\"$lang\" $selectedAttr>$lang</option>\n";
              }
            ?>
          </select>
          <a href="http://tinymce.moxiecode.com/download_i18n.php" target="_BLANK"><?php eht("Download more languages..."); ?></a>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Developer Mode');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'languageDeveloperMode',
            'label'   => t("Automatically add new language strings to language files"),
            'checked' => $SETTINGS['advanced']['languageDeveloperMode'],
          ]) ?>
        </div>
      </div>
      <?php endif ?>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="dateFormat">
          <?php et('Date Field Format');?>
        </label>
        <div class="col-sm-8">
          <select name="dateFormat" id="dateFormat" class="form-control">
            <option value=''>&lt;select&gt;</option>
            <option value='' <?php selectedIf($SETTINGS['dateFormat'], '') ?>>default</option>
            <option value="dmy" <?php selectedIf($SETTINGS['dateFormat'], 'dmy') ?>>Day Month Year</option>
            <option value="mdy" <?php selectedIf($SETTINGS['dateFormat'], 'mdy') ?>>Month Day Year</option>
          </select>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Advanced Settings'),
          'href'  => "?menu=admin&action=general#advanced-settings",
          'id'    => "advanced-settings",
        ]);
      ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="imageResizeQuality">
          <?php et('Image Resizing Quality');?>
        </label>
        <div class="col-sm-8">
          <select name="imageResizeQuality" id="imageResizeQuality" class="form-control">
            <option value="65" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '65'); ?>><?php et('Minimum - Smallest file size, some quality loss')?></option>
            <option value="80" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '80'); ?>><?php et('Normal - Good balance of quality and file size')?></option>
            <option value="90" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '90'); ?>><?php et('High - Larger file size, high quality')?></option>
            <option value="100" <?php selectedIf($SETTINGS['advanced']['imageResizeQuality'], '100'); ?>><?php et('Maximum - Very large file size, best quality')?></option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('WYSIWYG Options');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'includeDomainInLinks',
            'label'   => t('Save full URL for local links and images (for viewers on other domains)'),
            'checked' => $SETTINGS['wysiwyg']['includeDomainInLinks'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Use WebP');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'convertUploadsToWebp',
            'label'   => t('Automatically convert image uploads to WebP format'),
            'checked' => $SETTINGS['advanced']['convertUploadsToWebp'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Code Generator');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'codeGeneratorExpertMode',
            'label'   => t('Expert mode - don\'t show instructions or extra html in Code Generator output'),
            'checked' => @$SETTINGS['advanced']['codeGeneratorExpertMode'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Disable HTML5 Uploader');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'disableHTML5Uploader',
            'label'   => t('Disable HTML5 Uploader - attach one file at a time (doesn\'t require html5 support)'),
            'checked' => @$SETTINGS['advanced']['disableHTML5Uploader'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Menu Options');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'showExpandedMenu',
            'label'   => t("Always show expanded menu (don't hide unselected menu groups)"),
            'checked' => $SETTINGS['advanced']['showExpandedMenu'],
          ]) ?>
        </div>

        <?php if (array_key_exists('showExpandedMenu', $CURRENT_USER)): ?>
          <div class="col-sm-3 control-label">
            <?php et('Updated');?>
          </div>
          <div class="col-sm-8">
            <?php et("This option is now being ignored and being set on a per user basis with the 'showExpandedMenu' field in")?> <a href="?menu=accounts"><?php et('User Accounts')?></a>.
          </div>
        <?php endif ?>

        <div class="col-sm-3 control-label">
          <?php et('Use Datepicker');?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'useDatepicker',
            'label'   => t("Display datepicker icon and popup calendar beside date fields"),
            'checked' => $SETTINGS['advanced']['useDatepicker'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php et('Use Media Library'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'useMediaLibrary',
            'label'   => t("Enable Media Library that allows using uploads in multiple sections") . " (BETA)",
            'checked' => $SETTINGS['advanced']['useMediaLibrary'],
          ]) ?>
        </div>

        <?php
          //              
          $isLegacyMysqlAvailable = extension_loaded('mysql');
          $legacyMySQLClass       = $isLegacyMysqlAvailable ? '' : 'text-muted form-control-static';
        ?>

        <div class="col-sm-3 control-label <?php echo $legacyMySQLClass ?>">
          <?php et('Legacy MySQL Support');?>
        </div>
        <div class="col-sm-8  <?php echo $legacyMySQLClass ?>">
          <?php if ($isLegacyMysqlAvailable): ?>
            <?php echo adminUI_checkbox([
              'name'    => 'legacy_mysql_support',
              'label'   => t("Connect to legacy PHP MySQL library to support old code (doubles required MySQL connections)"),
              'checked' => $SETTINGS['advanced']['legacy_mysql_support'],
            ]); ?>
          <?php else: ?>
            <?php echo t("This feature is not available because your server doesn't have the PHP MySQL extension loaded."); ?>
          <?php endif ?>
        </div>
    

      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="session_save_path">
          <?php et('session.save_path');?>
        </label>
        <div class="col-sm-8">
          <input class="text-input wide-input form-control" type="text" name="session_save_path" id="session_save_path" value="<?php echo htmlencode(@$SETTINGS['advanced']['session_save_path']) ?>" size="60">
          <?php et("If your server is expiring login sessions too quickly set this to a new directory outside of your web root or leave blank for default value of:"); ?> <code><?php echo htmlencode(get_cfg_var('session.save_path')); ?></code>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="session_cookie_domain">
          <?php et('session.cookie_domain');?>
        </label>
        <div class="col-sm-8">
          <input class="text-input wide-input form-control" type="text" name="session_cookie_domain" id="session_cookie_domain" value="<?php echo htmlencode(@$SETTINGS['advanced']['session_cookie_domain']) ?>" size="60">
          <?php et("To support multiple subdomains set to parent domain (eg: example.com), or leave blank to default to current domain."); ?>
        </div>
      </div>

      <?php echo adminUI_separator([
          'label' => t('Server Info'),
          'href'  => "?menu=admin&action=general#server-info",
          'id'    => "server-info",
        ]);
      ?>



      <div class="form-group">


        <div class="col-sm-3 control-label">
          <?php eht('Control Panel'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              $controlPanelHTML = ""; 
            
              // Detect WampServer - use known path difference from PHP_BINARY constant to config file
              $wampServerConfigPath = realpath(dirname(PHP_BINARY) . "/../../../../wampmanager.conf"); // eg: C:\wamp64\bin\apache\apache2.4.27\bin\httpd.exe to C:\wamp64\wampmanager.conf
              if ($wampServerConfigPath) {
                $wampConfig = parse_ini_file($wampServerConfigPath, true);
                $controlPanelHTML .= "WampServer v" .$wampConfig['main']['wampserverVersion']. "\n";
                $controlPanelHTML .= "(<a href='http://www.wampserver.com/en/'>vendor</a>, <a href='http://wampserver.aviatechno.net/?lang=en'>updates</a>)<br>\n";
              }

              // Detect Plesk
              $pleskVersionData  = uber_file_get_contents("/usr/local/psa/version");
              if ($pleskVersionData) { 
                $pleskVersionShort = $pleskVersionData; // eg: 17.8.11 CentOS 7 1708180920.15
                $pleskVersionShort = preg_replace("/ .*$/", "", $pleskVersionShort); // remove first space and everything after (leaving just version)
                $pleskVersionLong  = htmlencode(preg_replace("/^\s+/m", "", '/usr/sbin/plesk version')); // remove leading spaces 
                $controlPanelHTML .= "Plesk v$pleskVersionShort\n";
                $controlPanelHTML .= "(<u title='$pleskVersionLong'>details</u>, <a href='https://{$_SERVER['HTTP_HOST']}:8443/'>login</a>)";
              }
              
              // Detect cPanel
              $cpanelVersionData  = uber_file_get_contents("/usr/local/cpanel/version");
              if ($cpanelVersionData) { 
                list($parent,$major,$minor,$build) = explode('.', $cpanelVersionData); // eg: 11.76.0.18
                $controlPanelHTML .= "cPanel v$major.$minor.$build\n";
                $controlPanelHTML .= "(<a href='https://{$_SERVER['HTTP_HOST']}:2083/'>cPanel login</a>, <a href='https://{$_SERVER['HTTP_HOST']}:2087/'>WHM login</a>)";
              }
              /*
              /usr/local/cpanel/cpanel -V // 76.0 (build 18)
              cat /usr/local/cpanel/version
              grep CPANEL /etc/cpupdate.conf
              
              */
              
              //
              if (!$controlPanelHTML) { $controlPanelHTML = "None detected"; }
              
              // 
              echo $controlPanelHTML;
            ?>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Operating System'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              $osName = "";
              
              $releaseData = uber_file_get_contents("/etc/system-release");
              if ($releaseData) {
                $osName = $releaseData;
              } 
              else { 
                $server  = @php_uname('s'); // Operating system name, eg:
                $release = @php_uname('r'); // Release name,          eg:
                $version = @php_uname('v'); // Version info (varies),
                $machine = @php_uname('m'); // Machine type. eg. i386, x86_64
                $osName = "$server $release";
            
              }
              print $osName;

							//
							if (isWindows())  { print " (<a href='?menu=admin&action=ver'>ver</a>, <a href='?menu=admin&action=systeminfo'>systeminfo</a>)"; }
							if (!isWindows()) { print " (<a href='?menu=admin&action=releases'>release</a>)"; }
            ?>
            <!--
              php_uname('s'): <?php echo @php_uname('s'); ?> - Operating system name. eg. Windows NT, Linux, FreeBSD.
              php_uname('n'): <?php echo @php_uname('n'); ?> - Host name. eg. localhost.example.com.
              php_uname('r'): <?php echo @php_uname('r'); ?> - Release name. eg. 5.1, 2.6.18-164.11.1.el5, 5.1.2-RELEASE.
              php_uname('v'): <?php echo @php_uname('v'); ?> - Version information. Varies a lot between operating systems, eg: build 2600 (Windows XP Professional Service Pack 3), #1 SMP Wed Dec 17 11:42:39 EST 2008 i686
              php_uname('m'): <?php echo @php_uname('m'); ?> - Machine type. eg. i386, x86_64
            -->
          </div>
        </div>


        <div class="col-sm-3 control-label">
          <?php eht('Web Server'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              // WEB SERVER 
              $serverSoftware = [];
              $serverSoftware[ $_SERVER['SERVER_SOFTWARE'] ] = "\$_SERVER['SERVER_SOFTWARE']";
              if (function_exists('apache_get_version')) {  $serverSoftware[ apache_get_version() ] = "apache_get_version()"; }
              foreach ($serverSoftware as $software => $reportedBy) {
                print htmlencodef("<span title='Reported by: ?'>?</span><br>", $reportedBy, $software); 
              }
            ?>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('PHP Version'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            PHP v<?php echo phpversion() ?> (<a href='?menu=admin&amp;action=phpinfo'>phpinfo</a>)

            <ul>
              <?php
              
                // PHP CONFIG FILES 
                // future: show additional .php files load with? php_ini_scanned_files?
                $localHtaccess = absPath('.htaccess', SCRIPT_DIR);
                $localUserIni  = absPath(ini_get('user_ini.filename'), SCRIPT_DIR);
                $localPhpIni   = absPath('php.ini', SCRIPT_DIR);
                $loadedPhpIni  = absPath(php_ini_loaded_file(), SCRIPT_DIR);
                $configFiles = [  // check which local config files are in use
                  $localHtaccess => contains('CMSB_CONFIG_HTACCESS', ini_get('highlight.html')  .ini_get('date.default_latitude')) || !empty($_SERVER['CMSB_APACHE_HTACCESS']), // || in_array('101M', $_maxSizes)
                  $localUserIni  => contains('CMSB_CONFIG_USER_INI', ini_get('highlight.string').ini_get('date.default_longitude')),    // || in_array('102M', $_maxSizes)
                  $localPhpIni   => contains('CMSB_CONFIG_PHP_INI',  ini_get('highlight.comment').ini_get('date.sunrise_zenith')),      // || in_array('103M', $_maxSizes)
                  $loadedPhpIni  => 1, // add last as it will overwrite previous entry if they're the same and in that case we want isLoaded to be true
                ];
                $configFilesList = ''; 
                foreach ($configFiles as $configFile => $isLoaded) {
                  $filePath           = absPath($configFile, SCRIPT_DIR); 
                  $fileExists         = @file_exists($filePath);
                  $isOpenBaseDirError = preg_match("/open_basedir restriction in effect/i", error_get_last()['message']);
                  if     (!$fileExists && !$isOpenBaseDirError) { $configFilesList .= "<li style='color: #C00;'>$filePath (file not found)</li>\n"; }
                  elseif ($isLoaded)                            { $configFilesList .= "<li>$filePath</li>\n"; }
                }
                print "<li>using config files:\n";
                if ($configFilesList) { print "<ul>$configFilesList</ul>"; }
                else                  { print t('none'); }
                print "</li>\n"; 

              
                // DISABLED FUNCTIONS
                $disabledFunctions = str_replace(',', ', ', ini_get('disable_functions'));
                $suhosinDisabled   = str_replace(',', ', ', ini_get('suhosin.executor.func.blacklist'));
                if ($suhosinDisabled) { $disabledFunctions .= " - " . t("Suhosin disabled") . ": $suhosinDisabled"; }
                if ($disabledFunctions) { 
                  print "<li style='color: #C00;'>" .t('Disabled functions'). ": $disabledFunctions</li>\n";
                }

                
                // OPEN_BASEDIR RESTRICTIONS
                $open_basedir = ini_get('open_basedir');
                if ($open_basedir) { 
                  print htmlencodef("<li style='color: #C00;'>?: ?</li>\n", t('open_basedir restrictions'), $open_basedir);
                }
                
                
                // SECURITY MODULES - check for common security modules that interfere
                $securityModulesAsArray = [];
                $suhosinNameAndVersion = _suhosinVersionDetected();
                if ($suhosinNameAndVersion)                           { $securityModulesAsArray[] = $suhosinNameAndVersion; }
                if (array_key_exists('CMSB_MOD_SECURITY1', $_SERVER)) { $securityModulesAsArray[] = "ModSecurity 1"; }
                if (array_key_exists('CMSB_MOD_SECURITY2', $_SERVER)) { $securityModulesAsArray[] = "ModSecurity 2"; }
                $securityModules     = implode(', ', $securityModulesAsArray);
                if ($securityModules) { 
                  print htmlencodef("<li style='color: #C00;'>?: ?</li>\n", t('Security Modules'), $securityModules);
                }

                // IMAGE MODULES
                $modulesToCheck  = ['gd','imagick'];
                $imageModulesCSV = '';
                foreach ($modulesToCheck as $module) {
                  if ($imageModulesCSV) { $imageModulesCSV .= ", "; }
                  if (extension_loaded($module)) { $imageModulesCSV .= "$module"; }
                  else                           { $imageModulesCSV .= "<strike class='text-muted'>$module</strike>"; }
                }
                print sprintf("<li>%s: %s</li>\n", t('Image Modules'), $imageModulesCSV);
                
                // CACHING MODULES
                // Notes: We've seen servers where WinCache caching the PHP "output" of schema files, so they just return "Not a PHP file".
                // This can be resolved by disabling WinCache in .user.ini with: wincache.fcenabled = Off
                // Future: Look into exact cause and workarounds that let us continue using WinCache but avoid issue
                $cachingModules = [];
                if (ini_get('wincache.fcenabled') == '1') { $cachingModules[] = "WinCache"; }
                if (ini_get('opcache.enable') == '1')     { $cachingModules[] = "Zend OPCache"; }
                $cachingModulesCSV = implode(", ", $cachingModules);
                if ($cachingModulesCSV) { 
                  print htmlencodef("<li style='color: #C00;'>?: ?</li>\n", t('Caching Modules'), $cachingModulesCSV);
                }

                // PHP ERRORS AND WARNINGS
                print "<li><a href='?menu=_error_log'>" .t('View PHP Errors and Warnings'). " &gt;&gt;</a></li>\n"; 

              ?>
            </ul>
          </div>
        </div>

        <label class="col-sm-3 control-label">
          <?php eht('Database Server'); ?>
        </label>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              // get database server details
              $databaseServer  = mysql_get_query("SHOW VARIABLES LIKE 'version_comment'")['Value'];
              $databaseVersion = mysqli()->server_info; // to remove non-numeric chars: preg_replace("/[^0-9\.]/
              list($maxConnectionsTotal, $maxConnectionsPerUser) = mysql_get_query("SELECT @@max_connections, @@max_user_connections", true); // returns the session value if it exists and the global value otherwise
              $maxConnections  = !empty($maxUserConnections) ? min($maxConnectionsTotal, $maxConnectionsPerUser) : $maxConnectionsTotal;

              // get db encryption status and support - UPDATE COPIES IN security.php and general.php
              $dbStatusRows      = mysql_select_query("SHOW STATUS WHERE Variable_name IN ('Ssl_cipher','Ssl_version')");
              $dbStatusVars      = array_column($dbStatusRows, 'Value', 'Variable_name');
              $dbUsingEncryption = $dbStatusVars['Ssl_cipher'] || $dbStatusVars['Ssl_version'];
              $dbHaveSSL         = mysql_get_query("SHOW VARIABLES WHERE Variable_name IN ('have_ssl')")['Value'];

              // get HTML for: encryption status and support
              $dbUsingEncryptionYes  = htmlencodef("<u title='?'>?</u>", $dbStatusVars['Ssl_version'] .':  '. $dbStatusVars['Ssl_cipher'], t("Yes"));
              $dbUsingEncryptionText = $dbUsingEncryption ? $dbUsingEncryptionYes : "<span style='color: #C00;'>" .t("No"). "</span>";
              $dbHaveSSLText         = ucfirst(strtolower($dbHaveSSL)); // Disabled, Yes, No
              $dbHaveSSLHTML         = htmlencodef("<a href='https://dev.mysql.com/doc/refman/5.5/en/server-system-variables.html#sysvar_have_ssl' target='_blank'>?</a>", $dbHaveSSL);
            ?>

            <?php echo htmlencodef("? v? (?: ?)", $databaseServer, $databaseVersion, t('Max Connections'), $maxConnections); ?>
            <ul>
              <li><?php echo t('Hostname'); ?>: <?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['hostname']) ?>,
              <?php echo t('Database'); ?>: <b><?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['database']) ?></b>, 
              <?php echo t('Username'); ?>: <?php echo inDemoMode() ? 'demo' : htmlencode($SETTINGS['mysql']['username']) ?>,
              <?php echo t('Table Prefix'); ?>: <b><?php echo htmlencode($TABLE_PREFIX) ?></b></li>
              <li><?php printf(t('To change %1$s settings edit: %2$s'), 'database', DATA_DIR. '/'.SETTINGS_FILENAME); ?></li>
              <li><?php et('Encrypted connection'); ?>: <?php echo $dbUsingEncryptionText; ?>,&nbsp;
                  <?php et('Encrypted connection support'); ?>: <?php echo $dbHaveSSLHTML; ?></li>

              <!-- general_log, general_log_file, sql_log_off, -->
              <li>
                <?php
                  // enable/disable with: mysql_do("SET GLOBAL general_log = 1"); // requires SUPER permissions to set GLOBAL
                  // check if enabled with: $isGeneralQueryLogEnabled = mysql_get_query("SHOW VARIABLES WHERE Variable_name = 'general_log'")['Value'] == 'ON';
                  echo t('General Query Log') . ': ';
                  $rows            = mysql_select_query("SHOW VARIABLES WHERE Variable_name IN ('general_log', 'general_log_file', 'sql_log_off', 'log_output')");
                  $mysqlVars       = array_column($rows, 'Value', 'Variable_name');
                  $mysqlVarsAsText = print_r($mysqlVars, true);
                  $isEnabledText   = ($mysqlVars['general_log'] == 'ON') ? 'enabled' : 'disabled';
                  echo htmlencodef("<u title='?'>?</u>", $mysqlVarsAsText, $isEnabledText);



                ?>
              </li>
              
              <li>
                <?php
                  echo t('Slow Query Log') . ': ';
                  $rows            = mysql_select_query("SHOW VARIABLES WHERE Variable_name IN ('log_queries_not_using_indexes','log_slow_queries','long_query_time','slow_query_log','slow_query_log_file', 'log_output')");
                  $mysqlVars       = array_column($rows, 'Value', 'Variable_name');
                  $mysqlVarsAsText = print_r($mysqlVars, true);
                  $isEnabledText   = ($mysqlVars['slow_query_log'] == 'ON') ? 'enabled' : 'disabled';
                  echo htmlencodef("<u title='?'>?</u>", $mysqlVarsAsText, $isEnabledText);
                ?>
              </li>
              
            </ul>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Disk Space'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
              if ($totalBytes) {
                printf(t('Free: %1$s, Total: %2$s'), formatBytes($freeBytes), formatBytes($totalBytes));
              }
              else {  // for servers that return 0 and "Warning: Value too large for defined data type" on big ints
                et("Unavailable");
              }
            ?>
            <?php if (!isWindows()): ?>
            - <a href="?menu=admin&action=du" target="_blank">largest dirs &gt;&gt;</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Server Resource Limits'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
            if ($maxCpuSeconds || $memoryLimitKbytes || $maxProcessLimit) {
              print "CPU Time: $maxCpuSeconds_formatted, Memory Limit: $memoryLimit_formatted, Processes: $maxProcessLimit - <a href='$ulimitLink'>ulimit &gt;&gt;</a>";
            }
            else {
              et("Unavailable");
            }
            ?>
            <?php
            /*
            <table>
              <tr><td colspan="2">&nbsp;</td></tr>
               <tr>
                <td><?php et('Outgoing Mail Server IP') ?>&nbsp;</td>
                <td><?php
                  $smtp = ini_get('SMTP');
                  if (!$smtp) { $smtp = $_SERVER['SERVER_ADDR']; }
                  if (!$smtp) { $smtp = $_SERVER['HTTP_HOST'];   }
                  $smtp_ip = @gethostbyname($smtp);
                  if ($smtp_ip)                         { $smtp = $smtp_ip;    }
                  if (!$smtp || $smtp == '127.0.0.1')   { $smtp = '(unknown)'; }
                  ?>
                  <input type="text" readonly="readonly" value="<?php echo $smtp ?>" onclick="this.focus(); this.select();">
                  -
                  <a href="http://www.google.com/search?q=blacklist+ip+check" target="_blank">check blacklists &gt;&gt;</a>
                </td>
              </tr>
               <tr><td colspan="2">&nbsp;</td></tr>
               <tr>
                <td>Max Concurrent Users&nbsp;</td>
                <td>
                <?php
                  if ($maxProcessLimit && $maxConnections) {
                    print min($maxProcessLimit, $maxConnections);
                    print " - Based on Max MySQL Connections and Max Processes (other limits may affect total as well)<br>\n";
                  }
                  else {
                    et("Unavailable");
                  }
                 ?>
                </td>
              </tr>
            </table>
            */
            ?>
          </div>
        </div>
      </div>
    </div>
  <?php
}

<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Security Settings') => '?menu=admin&action=security' ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=securitySave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=general',   'label' => t('Cancel'), ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'securitySave', ],
];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);

function _getContent() {
  global $SETTINGS, $APP, $CURRENT_USER, $TABLE_PREFIX;
  
  echo adminUI_separator([
    'label' => t('Security Settings'),
    'href'  => "?menu=admin&action=general#security-settings",
    'id'    => "security-settings",
  ]);


?>
    <div class="form-horizontal">

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Login Timeouts'); ?>
        </div>
        <div class="col-sm-8">
          <div class="form-inline">
            <div class="form-group">
              <?php et("Automatically expire login sessions after"); ?>
              <input type="text" class="form-control" name="login_expiry_limit" value="<?php echo htmlencode(@$SETTINGS['advanced']['login_expiry_limit']) ?>" maxlength="4">
              <select name="login_expiry_unit" class="form-control"><?php echo getSelectOptions(@$SETTINGS['advanced']['login_expiry_unit'], array('minutes','hours','days','months'), array(t('minutes'),t('hours'),t('days'),t('months'))); ?></select>
            </div>
          </div>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Hide PHP Errors'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'phpHideErrors',
            'label'   => t("Hide all warnings and show \"An unexpected error occurred #\" for errors.  <a href='?menu=_error_log'>View error log &gt;&gt;</a>"),
            'checked' => $SETTINGS['advanced']['phpHideErrors'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Email PHP Errors'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'phpEmailErrors',
            'label'   => t("When <a href='?menu=_error_log'>php errors</a> are detected send admin a <a href='?menu=_email_templates'>notification email</a>"),
            'checked' => $SETTINGS['advanced']['phpEmailErrors'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Check Referer'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'checkReferer',
            'label'   => t("Warn on external referers/links and require internal referer to submit data to CMS."),
            'checked' => $SETTINGS['advanced']['checkReferer'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Disable Autocomplete'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'disableAutocomplete',
            'label'   => t("Attempt to disable autocomplete functionality in browsers to prevent storing of usernames and passwords."),
            'checked' => $SETTINGS['advanced']['disableAutocomplete'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Require HTTPS'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'requireHTTPS',
            'label'   => t("Only allow users to login via secure HTTPS connections"),
            'checked' => $SETTINGS['advanced']['requireHTTPS'],
          ]) ?>
        </div>

        <div class="col-sm-3 control-label">
          <?php eht('Restrict IP Access'); ?>
        </div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'    => 'restrictByIP',
            'label'   => sprintf(t("Only allow users to login from these IP addresses.  eg: 1.2.3.4, 4.4.4.4 (Your IP is: %s)"), $_SERVER['REMOTE_ADDR']),
            'checked' => $SETTINGS['advanced']['restrictByIP'],
          ]) ?>
          <div style="padding-left: 25px">
            <input class="text-input form-control" type="text" name="restrictByIP_allowed" value="<?php echo htmlencode(@$SETTINGS['advanced']['restrictByIP_allowed']) ?>" size="30">
          </div>
        </div>
        
        <div class="col-sm-3 control-label control-label">&nbsp;</div>
        <div class="col-sm-8 form-control-static">&nbsp;</div>
        
        <div class="col-sm-3 control-label control-label" id="database-encryption">
          <a href="?menu=admin&action=general#database-encryption" style="color: #000"><?php et('Database Encryption'); ?></a>
        </div>
        <?php
          // get db encryption status and support - UPDATE COPIES IN security.php and general.php
          $dbStatusRows      = mysql_select_query("SHOW STATUS WHERE Variable_name IN ('Ssl_cipher','Ssl_version')");
          $dbStatusVars      = array_column($dbStatusRows, 'Value', 'Variable_name');
          $dbUsingEncryption = $dbStatusVars['Ssl_cipher'] || $dbStatusVars['Ssl_version'];
          $dbHaveSSL         = mysql_get_query("SHOW VARIABLES WHERE Variable_name IN ('have_ssl')")['Value'];
          
          // disable encrypted connections when using "localhost" hostname
          $mysqlLocalhost = $SETTINGS['mysql']['hostname'] == "localhost";

          //
          $isEncryptionSupported = ($dbHaveSSL == 'YES');
          $dbEncryptionClass     = $isEncryptionSupported && !$mysqlLocalhost ? '' : 'text-muted';
          
        ?>
        <div class="col-sm-8 <?php echo $dbEncryptionClass ?>">
          <?php if ($isEncryptionSupported && !$mysqlLocalhost): ?>
            <?php echo adminUI_checkbox([
              'name'    => 'requireSSL',
              'label'   => t("Connections: Encrypt the connection between PHP and the database server"),
              'checked' => $SETTINGS['mysql']['requireSSL'],
            ]); ?>
          <?php else: ?>
            <?php 
            
            $label = t("Connections: Your database server doesn't support encrypted connections.");
            
            if ($mysqlLocalhost && $isEncryptionSupported) {
              $label = t("Connections: Encrypted connections are unsupported with the MySQL hostname 'localhost'. Encrypting local connections is generally unnecessary but can be enabled by configuring a hostname or IP.");
            }
            
            echo adminUI_checkbox([
              'name'     => '_unsupported_',
              'label'    => $label,
              'checked' => $SETTINGS['mysql']['requireSSL'],
              'disabled' => 1,
            ]); 
            
            ?>

          <?php endif ?>
        </div>
        
        <div class="col-sm-3 control-label control-label">&nbsp;</div>
        <div class="col-sm-8">
          <?php echo adminUI_checkbox([
            'name'     => '_unsupported_',
            'label'    => t("Database Data: Allow columns to be encrypted using this key: "),
            'checked'  => 1,
            'disabled' => 1,
          ]); ?>
          
          <div style="padding-left: 25px">
            <?php
              $encryptedColumnList  = mysql_encrypt_listColumns();
              $encryptedColumnCount = count($encryptedColumnList);
              
              // disable editing encryption key if there are existing encrypted columns and key isn't empty
              $inputDisabledAttr = $encryptedColumnCount && !empty($SETTINGS['mysql']['columnEncryptionKey']) ? 'disabled="disabled"' : '';
            ?>
            <input type="text" class="form-control" name="columnEncryptionKey" value="<?php echo htmlencode(@$SETTINGS['mysql']['columnEncryptionKey']) ?>" <?php echo $inputDisabledAttr ?>>
            <?php if ($encryptedColumnCount): ?>
              <li class='text-danger'>
                <?php
                  echo t("Tip: You can't change the key while any fields are using it.");
                  echo " <span style='text-decoration: underline' title='" .htmlencode(implode("\n", $encryptedColumnList)). "'>";
                  echo sprintf(t("There are %d encrypted fields"), $encryptedColumnCount);
                  echo "</span>";
                ?>
              </li>
            <?php else: ?>
              <?php /*
                <li><?php echo t("Tip: Randomly generated password suggestion: ") . base64_encode(random_bytes(30)); ?><br>
              */ ?>
            <?php endif; ?>
            
            <li><?php echo t("Tip: This encryption key is only used when you enable 'data encryption' on specific fields in the field editor."); ?><br>
            <li><?php echo t("Tip: <a href='?menu=admin&action=backuprestore'>Backup database</a> before encrypting so you have an unencrypted copy of your data."); ?><br>
            <li><?php echo t("Tip: Use the same encryption key on all development and production copies of a website."); ?><br>
            <li><?php echo t("Tip: Backup this key, without it your data will be unrecoverable!"); ?><br>
            &nbsp;
          </div>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php eht('Security Tips'); ?>
        </div>
        <div class="col-sm-8 form-control-static">
          <div class="text-left">
            <?php
            
              //
              $tips = [];
              $errorLogCount = mysql_count('_error_log');
              if (!isHTTPS())                                       { $tips[] = t("Use a secure https:// url to access this program.  You are currently using an insecure connection."); }
              if (!$SETTINGS['advanced']['requireHTTPS'])           { $tips[] = t("Enable 'Require HTTPS' above to disallow insecure connections."); }
              if (!$SETTINGS['advanced']['phpHideErrors'])          { $tips[] = t("Hide PHP Errors (for production and live web servers)."); }
              if (!$SETTINGS['advanced']['phpEmailErrors'])         { $tips[] = t("Enable 'Email PHP Errors' to be notified of PHP errors on website."); }
              //if (ini_get('expose_php'))                            { $tips[] = t(sprintf("%s is currently enabled, disable it in php.ini.", '<a href="http://www.php.net/manual/en/ini.core.php#ini.expose-php">expose_php</a>')); }
              if ($errorLogCount)                                   { $tips[] = t("There are PHP errors in the <a href='?menu=_error_log'>developer log</a>.  Review them and then clear the developer log."); }
              //if (!$dbUsingEncryption)                              { $tips[] = t("Enable 'Encrypt Database Conenctions' to encrypt database connections."); }
              //if (loginExpirySeconds() > (60*30))                   { $tips[] = t("Set login timeout to 30 minutes or less."); }
              if (!array_key_exists('CMSB_MOD_SECURITY2', $_SERVER)) { // mod_security2 reports false positives that are excluded for scripts named admin.php, so don't recommend this setting for hosts mod_security2 hosts
                //if (basename($_SERVER['SCRIPT_NAME']) == 'admin.php') { $tips[] = t(sprintf("Rename admin.php to something unique such as admin_%s.php", substr(sha1(uniqid(null, true)), 0, 20) )); }
              }
              $oldFilesAndDirs = []; // ask user to remove outdated files
              $oldFilesAndDirs[] = '/3rdParty/SwiftMailer';
              $oldFilesAndDirs[] = '/3rdParty/SimplaAdmin';
              $oldFilesAndDirs[] = '/3rdParty/thickbox';
              $oldFilesAndDirs[] = '/3rdParty/tiny_mce';
              $oldFilesAndDirs[] = '/css';
              $oldFilesAndDirs[] = '/images';
              $oldFilesAndDirs[] = '/js';
              $oldFilesAndDirs[] = '/lib/compat';
              $oldFilesAndDirs[] = '/lib/images/loadingBar.gif';
              $oldFilesAndDirs[] = '/lib/jquery.js';
              $oldFilesAndDirs[] = '/lib/jquery.tablednd.js';
              $oldFilesAndDirs[] = '/lib/jquery1.2.js';
              $oldFilesAndDirs[] = '/lib/jquery1.3.2.js';
              $oldFilesAndDirs[] = '/lib/jqueryForm.js';
              $oldFilesAndDirs[] = '/lib/jqueryInterfaceSortables.js';
              $oldFilesAndDirs[] = '/lib/jqueryThickbox.js';
              $oldFilesAndDirs[] = '/lib/tinyMCE';
              $oldFilesAndDirs[] = '/lib/viewer_turboCache.php';
              $oldFilesAndDirs[] = '/lib/website_functions.php';
              $oldFilesAndDirs[] = '/lib/website_functions2.js';
              $oldFilesAndDirs[] = '/lib/website_functions2.php';
              $oldFilesAndDirs[] = '/lib/website_functions_notes.txt';
              $oldFilesAndDirs[] = '/tinyMCE';
              $oldFilesAndDirs[] = '/tinymce3';
              $oldFilesAndDirs[] = '/style.css';
              $oldFilesAndDirs[] = '/style_ie6.css';
              foreach ($oldFilesAndDirs as $relativePath) {
                if     (is_dir(SCRIPT_DIR.'/'.$relativePath))        { $tips[] = t(sprintf("Remove old folder: %s", SCRIPT_DIR . $relativePath )); }
                elseif (is_file(SCRIPT_DIR.'/'.$relativePath))       { $tips[] = t(sprintf("Remove old file: %s", SCRIPT_DIR . $relativePath )); }
              }
              if ($tips) {
                echo "<div class='text-danger'>";
                echo "  <b>" .t('These tips are custom generated and apply to the current server and connection:'). "</b>";
                echo "<ul>";
                foreach ($tips as $tip) { print "<li>$tip</li>\n"; }
                echo "</ul>";
                echo "</div>";
              }
              if (!$tips) {
                print t('None');
              }
            ?>
          </div>
        </div>
      </div>
    </div>

  <?php
}

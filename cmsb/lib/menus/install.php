<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('One Minute Install') ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'class' => 'setAttr-spellcheck-false', 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'save', 'value' => '1' ],
];

// main content
$adminUI['CONTENT'] = ob_capture('getContent');


// compose and output the page
adminUI($adminUI);




function getContent() {
  global $SETTINGS, $APP;
  ?>
    <div class="form-horizontal">
      
      <p>
        <?php et("We'll have you up in running in just a minute."); ?>
      </p>
      
      <?php echo adminUI_separator(t("License Information")); ?>

      <div class="form-group">
        <div class="col-sm-3 control-label"></div>
        <div class="col-sm-8">

          <input type="hidden" name="agreeToLicense" value="0">
            <label>
              <input type="checkbox" name="agreeToLicense" id="agreeToLicense" value="1" <?php checkedIf(@$_REQUEST['agreeToLicense'], '1') ?>>
              <?php et('I accept the terms of the <a href="?menu=license" target="_blank">License Agreement</a>')?>
            </label>

        </div>
      </div>

      <?php echo adminUI_separator(t("Database Location")); ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="infotext"></label>
        <div class="col-sm-8">
          <p><?php et("Next, tell us your MySQL settings. If you don't know these you can ask your web host."); ?></p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="mysqlHostname">
          <?php et('MySQL Hostname');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="mysqlHostname" id="mysqlHostname" value="<?php echo htmlencode(@$_REQUEST['mysqlHostname']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="mysqlDatabase">
          <?php et('MySQL Database');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="mysqlDatabase" id="mysqlDatabase" value="<?php echo htmlencode(@$_REQUEST['mysqlDatabase']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="mysqlUsername">
          <?php et('MySQL Username');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="mysqlUsername" id="mysqlUsername" value="<?php echo htmlencode(@$_REQUEST['mysqlUsername']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="mysqlPassword">
          <?php et('MySQL Password');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="mysqlPassword" id="mysqlPassword" value="<?php echo htmlencode(@$_REQUEST['mysqlPassword']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="mysqlTablePrefix">
          <?php et('MySQL Table Prefix');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="mysqlTablePrefix" id="mysqlTablePrefix" value="<?php echo htmlencode(@$_REQUEST['mysqlTablePrefix']) ?>">
        </div>
      </div>

      <?php echo adminUI_separator(t("Getting Started")); ?>
      
      <?php
        $tabNew = '';
        $tabRestore = '';
        if (@$_REQUEST['restoreFromBackup']) { $tabRestore = ' active'; }
        else                                 { $tabNew = ' active'; }
      ?>
      <div style="visibility: hidden; position: absolute">
          <input type="checkbox" name="restoreFromBackup" id="restoreFromBackup" value="1" <?php checkedIf(@$_REQUEST['restoreFromBackup'], '1') ?>>
          Restore from Backup
      </div>
      
          <ul id="myTab" class="nav nav-tabs">
            <li class="<?php echo $tabNew; ?>">
              <a href="#newinstall" data-toggle="tab" onclick="$('#restoreFromBackup').attr('checked', false);">
                <i class="fa fa-home"></i> <?php et('New Installation'); ?>
              </a>
            </li>
            <li class="<?php echo $tabRestore; ?>">
              <a href="#restore" data-toggle="tab" onclick="$('#restoreFromBackup').attr('checked', true);"><?php et("Restore From Backup"); ?></a>
            </li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane in<?php echo $tabNew; ?>" id="newinstall">
              <div class="form-group">
                <label class="col-sm-3 control-label" for="infotext"></label>
                <div class="col-sm-8">
                  <p><?php et("For new installations, please select an administrator username and password and write them down in a safe place."); ?></p>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="adminFullname">
                  <?php et('Admin Full Name');?>
                </label>
                <div class="col-sm-8">
                  <input class="form-control" type="text" name="adminFullname" id="adminFullname" value="<?php echo htmlencode(@$_REQUEST['adminFullname']) ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="adminEmail">
                  <?php et('Admin Email');?>
                </label>
                <div class="col-sm-8">
                  <input class="form-control" type="text" name="adminEmail" id="adminEmail" value="<?php echo htmlencode(@$_REQUEST['adminEmail']) ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="adminUsername">
                  <?php et('Admin Username');?>
                </label>
                <div class="col-sm-8">
                  <input class="form-control" type="text" name="adminUsername" id="adminUsername" value="<?php echo htmlencode(@$_REQUEST['adminUsername']) ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="adminPassword1">
                  <?php et('Admin Password');?>
                </label>
                <div class="col-sm-8">
                  <input class="form-control" type="password" name="adminPassword1" id="adminPassword1" value="<?php echo htmlencode(@$_REQUEST['adminPassword1']) ?>" autocomplete="off">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="adminPassword2">
                  <?php et('Admin Password (again)');?>
                </label>
                <div class="col-sm-8">
                  <input class="form-control" type="password" name="adminPassword2" id="adminPassword2" value="<?php echo htmlencode(@$_REQUEST['adminPassword2']) ?>" autocomplete="off">
                </div>
              </div>
              
              <?php et("Once you have created your username and password click \"Finish\" and login to the program."); ?>
              <br><br>

              <div align="center">
                <?php echo adminUI_button(['name' => 'null', 'label' => t(' Finish &gt;&gt; ')]); ?>
              </div>
              
            </div>
            <div class="tab-pane <?php echo $tabRestore; ?>" id="restore">

              <?php et("To restore from a backup, select the backup file below:"); ?>
              <br><br>

              <?php $options = getBackupFiles_asOptions( @$_REQUEST['restore'] ); ?>
              <div class="form-inline">
                <select class="form-control" name="restore" id="restore"><?php echo $options ?></select>


                <?php echo adminUI_button(['label' => t('Restore'), 'name' => 'null', 'value' => '1']); ?><br><br>
              </div>
              <?php et("NOTE: To prevent data loss you can only restore to a database location with no pre-existing user accounts."); ?>
            </div>
          </div>

      <?php echo adminUI_separator(t("Advanced Options")); ?>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="infotext"></label>
        <div class="col-sm-8">
          <p><?php et("You can safely ignore these options if you don't need them."); ?></p>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="useCustomSettingsFile">
          <?php et('Use Custom Settings File');?>
        </label>
        <div class="col-sm-8">
          <input type="hidden" name="useCustomSettingsFile" value="0">
          <input type="checkbox" name="useCustomSettingsFile" value="1" <?php checkedIf(@$_REQUEST['useCustomSettingsFile'], '1') ?> >
          <?php printf(t("For this domain name only (%s) use this setting file: <b>/data/%s</b>.<br>
          Using a separate settings file for development servers ensures you never accidentally overwrite your live server settings
          when uploading CMS /data/ files.  Always use custom settings files for development servers only, not your live servers."),
          @$_SERVER['HTTP_HOST'], SETTINGS_DEV_FILENAME); ?>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label" for="webPrefixUrl">
          <?php et('Website Prefix URL');?>
        </label>
        <div class="col-sm-8">
          <?php $webPrefixUrl = array_key_exists('webPrefixUrl', $_REQUEST) ? $_REQUEST['webPrefixUrl'] : $SETTINGS['webPrefixUrl']; ?>
           <input class="form-control" type="text" name="webPrefixUrl" id="webPrefixUrl" value="<?php echo htmlencode($webPrefixUrl) ?>" >
          <?php et("eg: /~username or /development/client-name"); ?><br>
          <?php et("If your development server uses a different URL prefix than your live server you can specify it here.  This prefix can be changed
          in the Admin Menu and will be automatically added to Viewer URLs and can be displayed with &lt;?php echo PREFIX_URL ?&gt;.
          This will allow you to easily move files between a development and live server, even if they have different URL prefixes."); ?>
        </div>
      </div>
      
    </div>
  <?php
}

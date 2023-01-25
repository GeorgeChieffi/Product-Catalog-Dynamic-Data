<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('Admin'), t('Branding') => '?menu=admin&action=branding' ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin', ],
  [ 'name' => '_defaultAction', 'value' => 'brandingSave', ],
  [ 'name' => 'action',         'value' => 'branding', ],
];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'action=brandingSave', 'label' => t('Save'),   ];
$adminUI['BUTTONS'][] = [ 'name' => 'action=branding', 'label' => t('Cancel'), ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');

// compose and output the page
adminUI($adminUI);

function _getContent() {
  global $SETTINGS, $APP;

  //
  $brandingLink = $SETTINGS['adminUrl'] . "?menu=admin&action=branding";

?>
    <div class="form-horizontal">
      <div class="form-group">
        <div class="col-sm-3 control-label">
          <?php et('Branding Menu');?>
        </div>
        <div class="col-sm-8 form-control-static text-danger">
          <strong>NOTE:</strong> Once you change the "Vendor Name" field below, the "Branding" link will no longer be displayed on the menu when it's not selected so be sure to bookmark this URL:
          <a href="<?php echo $brandingLink ?>"><?php echo htmlencode($brandingLink); ?></a>
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label">&nbsp;</label>
        <div class="col-sm-8"><strong>These fields are displayed in the CMS.</strong></div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="programName">
          <?php et('Program Name / Titlebar');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="programName" id="programName" value="<?php echo htmlencode($SETTINGS['programName']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="headerImageUrl">
          <?php et('Header Image URL');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="headerImageUrl" id="headerImageUrl" value="<?php echo htmlencode($SETTINGS['headerImageUrl']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="helpUrl" class="col-sm-3 control-label"><?php echo t('Help URL'); ?></label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="helpUrl" id="helpUrl" value="<?php echo htmlencode($SETTINGS['helpUrl']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="footerHTML">
          <?php et('Footer HTML');?>
        </label>
        <div class="col-sm-8">
          <input class="form-control" type="text" name="footerHTML" id="footerHTML" value="<?php echo htmlencode($SETTINGS['footerHTML']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="col-sm-3 control-label" for="cssTheme">
          <?php et('Color / Theme');?>
        </label>
        <div class="col-sm-8">
          <?php // get CSS files
            $cssDirRelative = "/3rdParty/clipone/css/";
            $cssDirPath     = $GLOBALS['CMS_ASSETS_DIR'] . $cssDirRelative;
            $cssDirUrl      = $GLOBALS['CMS_ASSETS_URL'] . $cssDirRelative;
            foreach (scandir($cssDirPath) as $filename) {
              if (preg_match("|^theme\w+\.css(?:\.php)?$|i", $filename)) { $cssFiles[] = $filename; }
            }
            $optionsHTML = getSelectOptions($SETTINGS['cssTheme'], $cssFiles, $cssFiles, true);
          ?>
          <select name="cssTheme" id="cssTheme" class="form-control"><?php echo $optionsHTML; ?></select>
          <script>
            $(document).ready(function() {
              $('#cssTheme').on('change', function() {
                var cssFile = $(this).val();
                if (cssFile) {
                  var cssFileURL = '<?php echo jsEncode("$cssDirUrl"); ?>' + cssFile;
                  $('#skin_color').attr("href", cssFileURL);
                }
              });
            });
          </script>

          <?php print sprintf(t('You can add CSS themes in <code>%s</code>'), $cssDirUrl); ?>
        </div>
      </div>


      <br><br>
      <div class="form-group">
        <label class="col-sm-3 control-label">&nbsp;</label>
        <div class="col-sm-8">
          <strong>
            These fields are displayed in the <a href="?menu=license">License Agreement</a> and in <a href="?menu=admin&action=general#license-info">General Settings</a>.
          </strong>
        </div>
      </div>

      <div class="form-group">
        <label for="vendorName" class="col-sm-3 control-label"><?php echo t('Vendor Name'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorName" id="vendorName" value="<?php echo htmlencode($SETTINGS['vendorName']) ?>">
          <p class="text-danger"><strong>NOTE:</strong> Changing this hides the <a href="<?php echo $brandingLink; ?>">Branding</a> link from the menu, and
          removes <a href="?menu=license#sublicensing">sublicensing</a> permission from license agreement that has your company name on it.</p>
        </div>
      </div>

      <div class="form-group">
        <div class="col-sm-3 control-label"><?php eht('Sublicensing'); ?></div>
        <?php if (allowSublicensing()): ?>
          <div class="col-sm-8 form-control-static text-success">Licensees may sublicense the software licensed to them from: <?php echo htmlencode($SETTINGS['vendorName']) ?></div>
        <?php else: ?>
          <div class="col-sm-8 form-control-static text-danger">Licencees may not sublicense the software licensed to them from: <?php echo htmlencode($SETTINGS['vendorName']) ?>.</div>
        <?php endif ?>
      </div>
      
      <div class="form-group">
        <label for="vendorLocation" class="col-sm-3 control-label"><?php echo t('Vendor Location'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorLocation" id="vendorLocation" value="<?php echo htmlencode($SETTINGS['vendorLocation']) ?>">
          Make sure Vendor Location is in this format 'State or Province, Country' because it's listed as the jurisdiction in the license agreement.
        </div>
      </div>
      <div class="form-group">
        <label for="vendorUrl" class="col-sm-3 control-label"><?php echo t('Vendor Url'); ?></label>
        <div class="col-sm-7">
          <input class="form-control" type="text" name="vendorUrl" id="vendorUrl" value="<?php echo htmlencode($SETTINGS['vendorUrl']) ?>">
        </div>
      </div>


    </div>
  <?php
}

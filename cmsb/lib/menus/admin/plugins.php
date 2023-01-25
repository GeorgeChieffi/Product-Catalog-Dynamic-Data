<?php

global $ALL_PLUGINS;
$ALL_PLUGINS = getPluginList();
uasort($ALL_PLUGINS, '_sortPluginsByName');


// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('CMS Setup'), t('Plugins') => '?menu=admin&action=plugins' ];

// form tag and hidden fields
$adminUI['FORM'] = [ 'autocomplete' => 'off' ];
$adminUI['HIDDEN_FIELDS'] = [
  [ 'name' => 'menu',           'value' => 'admin',       ],
  [ 'name' => '_defaultAction', 'value' => 'pluginsSave', ],
];

// main content
$adminUI['CONTENT'] = ob_capture(function() { ?>
  <?php showPluginList('active'); ?>

  <?php showPluginList('inactive'); ?>

  <div class="notification information png_bg"><div>
    <?php print sprintf(t('Plugin Developers! <a href="%s">Click here</a> for a list of plugin hooks'),"?menu=admin&amp;action=pluginHooks")?>
  </div></div>

  <div class="notification information png_bg"><div>
    <?php print sprintf(t('Need a custom plugin? Contact your vendor at %s'),"<a href=\"{$GLOBALS['SETTINGS']['vendorUrl']}\">{$GLOBALS['SETTINGS']['vendorName']}</a>")?>
  </div></div>
<?php });

// compose and output the page
adminUI($adminUI);





//
function _sortPluginsByName($arrayA, $arrayB) {
  return strncasecmp($arrayA['name'], $arrayB['name'],100);
}

//
function showPluginList($listType) {
  global $ALL_PLUGINS;

  // get list name
  $listName = '';
  if     ($listType == 'active')   { $listName = 'Active Plugins'; }
  elseif ($listType == 'inactive') { $listName = 'Inactive Plugins'; }
  else { die("Unknown list type '" .htmlencode($listType). "'"); }

  // show plugin list
  ?>
    <table class="data table table-striped table-hover" style="overflow: scroll">
     <thead>
      <tr>
       <th style="white-space:nowrap; width:15%;"><?php et($listName) ?></th>
       <th style="text-align:center; padding: 0px 10px; width:8%;"><?php et('Version') ?></th>
       <th style="width:62%;"><?php et('Description') ?></th>
       <th style="text-align:center; width:15%;"><?php et('Action') ?></th>
      </tr>
     </thead>
     <tbody>
      <?php // list plugins
        $pluginCount = 0;
        foreach (array_values($ALL_PLUGINS) as $pluginData) {
          if ($listType == 'active'   && !$pluginData['isActive']) { continue; }
          if ($listType == 'inactive' && $pluginData['isActive'])  { continue; }
          $pluginCount++;
          _showPluginRow($listType, $pluginData);
        }
      ?>

      <?php if (!$pluginCount): ?>
        <tr>
          <td colspan="4" style="text-align: center; vertical-align: middle; height: 50px;">
            <?php printf(t("There are currently no %s."), t($listName) ); ?><br>
          </td>
        </tr>
      <?php endif; ?>
     </tbody>
    </table><br><br><br>
  <?php
}

//
function _showPluginRow($pluginType, $pluginData) {
  global $APP;

  // error checking
  $allowedTypes = array('active', 'inactive','system');
  if (!in_array($pluginType, $allowedTypes)) { die(__FUNCTION__ . ": Unknown plugin type '". htmlencode($pluginType)."'"); }

  // show row
  $rowClass = $pluginData['isActive'] ? '' : 'inactive';
  ?>
         <tr class="listRow <?php echo $rowClass; ?>">
           <td><?php
               if ($pluginData['uri']) { print "<a href='{$pluginData['uri']}'>"; }
               print htmlencode($pluginData['name']);
               if ($pluginData['uri']) { print "</a>"; }
             ?></td>
           <td style="text-align:center"><?php echo htmlencode($pluginData['version']) ?></td>
           <td>
             <?php echo htmlencode($pluginData['description']) ?>
             <?php
               if ($pluginData['author']) {
                 print "<br>\n" . t('By') . ' ';
                 if ($pluginData['authorUri']) { print "<a href='{$pluginData['authorUri']}'>"; }
                 print htmlencode($pluginData['author']);
                 if ($pluginData['authorUri']) { print "</a>"; }
               }
             ?>
           </td>
           <td style="text-align:center">
             <?php _showPluginActions($pluginData); ?>
           </td>
         </tr>
  <?php
}

//
function _showPluginActions($pluginData) {
  $hasRequiredCmsVersion = (@$pluginData['cmsVersionMin'] <= $GLOBALS['APP']['version']);

  // 
  if ($pluginData['isSystemPlugin'] || $pluginData['wasSystemPlugin']) {
    $style = $pluginData['wasSystemPlugin'] ? 'text-decoration: line-through;' : ''; // show strikethrough when 
    print "<span class='text-muted' style='$style'>" .t("System Plugin") . "</span><br>\n";
  }
  
  //
  if (!$hasRequiredCmsVersion)       { print t('Requires') . " v" . $pluginData['cmsVersionMin']; }

  //
  if ($hasRequiredCmsVersion) { 
    if (!$pluginData['isSystemPlugin']) {
      $actionLabel = $pluginData['isActive'] ? 'Deactivate'       : 'Activate'; 
      $actionJSON  = $pluginData['isActive'] ? 'deactivatePlugin' : 'activatePlugin'; 
      $onclick     = "return redirectWithPost('?', {menu:'admin', action:'$actionJSON', file: '".jsEncode($pluginData['filename'])."', '_CSRFToken': $('[name=_CSRFToken]').val()});";  
      $title       = htmlencode("$actionLabel plugins/" . $pluginData['filename']);
      print "<a href='#' onclick=\"$onclick\" title=\"$title\">" .t($actionLabel). "</a><br>\n";
    }
    doAction('plugin_actions', $pluginData['filename']);  
  }

}

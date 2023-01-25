<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$adminUI['PAGE_TITLE'] = [ t('CMS Setup'), t('Plugins') => '?menu=admin&action=plugins', t("Developer's Plugin Hook List") ];

// buttons
$adminUI['BUTTONS'] = [];
$adminUI['BUTTONS'][] = [ 'name' => 'null', 'label' => t('Back to Plugins'), 'onclick' => "window.location='?menu=admin&action=plugins'; return false;", ];

// main content
$adminUI['CONTENT'] = ob_capture('_getContent');



// compose and output the page
adminUI($adminUI);


function _getContent() {
  
  $hookTypes = array(
    'filter' => array( 'callerRegex' => "|applyFilters\(\s*(['\"])(.*?)\\1|", 'pluginRegex' => "|addFilter\(\s*(['\"])(.*?)\\1|" ),
    'action' => array( 'callerRegex' =>     "|doAction\(\s*(['\"])(.*?)\\1|", 'pluginRegex' => "|addAction\(\s*(['\"])(.*?)\\1|" ),
  );

  $ignoreRegexs = array(
    '|^\./data/|',
    '|^\./3rdParty/|',
    '|^\./sampleCode/|',
  );

  $hooks = [];
  $phpFiles = scandir_recursive('.', '/\.php$/');
  foreach ($phpFiles as $phpFile) {

    // ignoreRegexps
    $ignore = false;
    foreach ($ignoreRegexs as $ignoreRegex) {
      if (preg_match($ignoreRegex, $phpFile)) { $ignore = true; }
    }
    if ($ignore) { continue; }

    $phpFileNiceName = preg_replace('|^\./|', '', $phpFile);

    $content = file_get_contents($phpFile);

    foreach ($hookTypes as $hookType => $hookInfo) {
      if (preg_match_all($hookInfo['callerRegex'], $content, $matches)) {
        foreach ($matches[2] as $name) {
          if (!@$hooks[$name]) { $hooks[$name] = array('type' => $hookType, 'callers' => [], 'plugins' => []); }
          $hooks[$name]['callers'][$phpFileNiceName] = true;
        }
      }
      if (preg_match_all($hookInfo['pluginRegex'], $content, $matches)) {
        foreach ($matches[2] as $name) {
          if (!@$hooks[$name]) { $hooks[$name] = array('type' => $hookType, 'callers' => [], 'plugins' => []); }
          $hooks[$name]['plugins'][$phpFileNiceName] = true;
        }
      }
    }
  }

  $phpFiles = scandir_recursive('.', '/\.php$/');
  foreach ($phpFiles as $phpFile) {
    $phpFileNiceName = preg_replace('|^\./|', '', $phpFile);

    $content = file_get_contents($phpFile);

    foreach ($hookTypes as $hookType => $hookInfo) {
      if (preg_match_all($hookInfo['pluginRegex'], $content, $matches)) {
        foreach ($matches[2] as $name) {
          if (!@$hooks[$name]) { $hooks[$name] = array('type' => $hookType, 'callers' => [], 'plugins' => []); }
          $hooks[$name]['plugins'][$phpFileNiceName] = true;
        }
      }
    }
  }

  ksort($hooks); // sort by hook name
  
  // organize into categories
  $hookCategories = [
    'Core' => [
      'hooks' => [], 
      'desc' => "These are the default hooks provided by the software.",
    ],
    'Plugin-created' => [
      'hooks' => [], 
      'desc' => "These hooks are added by plugins. This can be done to allow interaction between different plugins, or to allow easier customization of plugin behaviour by developers.",
    ],
    'Deprecated' => [
      'hooks' => [], 
      'desc' => "These are older hooks which should no longer be used. Plugins using these hooks will likely need to be updated. More information about the deprecation of specific hooks may be found in the software's changelog.",
    ],
    'Unknown' => [
      'hooks' => [], 
      'desc' => "These hooks are registered, but never called. They may be older hooks which are no longer used (but were not added to the deprecated list,) or they may have belonged to plugins which are no longer installed. Although using these hooks will have no effect, plugins calling these hooks should be checked over to make sure they are still working correctly.",
    ],
  ];
  $deprecatedHooks = plugin_getDeprecatedHooks();
  foreach ($hooks as $hookName => $hookInfo) {
    $category = 'Core';
    if (in_array($hookName, $deprecatedHooks)) {
      $category = 'Deprecated';
    }
    elseif (!count($hookInfo['callers'])) {
      $category = 'Unknown';
    }
    else {
      $category = 'Plugin-created';
      foreach ($hookInfo['callers'] as $callerFile => $lineNum) {
        if (!startsWith('plugins/', $callerFile)) { // only one call must be made from core to qualify as a core plugin
          $category = 'Core';
        }
      }
    }
    $hookCategories[$category]['hooks'][$hookName] = $hookInfo;
  }
  
  function _sortUnderscoresLast($a, $b) {
    $a = preg_replace('|^_|', 'ZZZZZ', $a);
    $b = preg_replace('|^_|', 'ZZZZZ', $b);
    return strcasecmp($a, $b);
  }
  function showListResultsForHookKey($hookInfo, $key) {
    uksort($hookInfo[$key], '_sortUnderscoresLast');
    $i = 0;
    foreach (array_keys($hookInfo[$key]) as $callerName) {
      $i++;
      if ($i == 2) {
        echo "\n<a href=\"#\" onclick=\"$(this).hide(); $(this).closest('td').find('div').show(); return false;\">("
        . count(array_keys(array_keys($hookInfo[$key])))
        . " " . t('total') . ")</a><div style=\"display: none;\">\n";
      }
      echo htmlencode($callerName);
      if ($i != 1) { echo "<br>\n"; }
    }
    if ($i > 1) { echo "</div>\n"; }
  }
  function showHookTable($hooks) {
    ?>
      <table cellspacing="0" class="data table table-striped table-hover">
        <thead>
          <tr style="text-align: left;">
            <th>
              <?php et("Hook Name") ?>
            </th>
            <th>
              <?php et("Type") ?>
            </th>
            <th>
              <?php et("Where it's called...") ?>
            </th>
            <th>
              <?php et("Where it's used...") ?>
            </th>
          </tr>
        </thead>
        <?php $counter = 0; ?>
        <?php foreach ($hooks as $hookName => $hookInfo): ?>
          <?php $counter += 1; ?>
          <tr>
            <td>
              <?php echo htmlencode($hookName); ?>
            </td>
            <td>
              <?php echo htmlencode($hookInfo['type']); ?>
            </td>
            <td>
              <?php showListResultsForHookKey($hookInfo, 'callers'); ?>
            </td>
            <td>
              <?php showListResultsForHookKey($hookInfo, 'plugins'); ?>
            </td>
          </tr>
        <?php endforeach ?>
      </table>
    <?php
  }
  
  ?>
    
    <?php _displayNotificationType('info', t("This page lists where Plugin Hooks are called and used. To learn more about a hook, open a file where it's called and search for the Hook Name.")); ?>
    
    <?php foreach ($hookCategories as $categoryName => $hookCategory): ?>
    
      <?php if ($hookCategory['hooks']): ?>
        <br>
        <?php echo adminUI_separator(t($categoryName) . ' ' . t('Plugin Hooks')); ?>
        <p><?php et($hookCategory['desc']) ?></p>
        <?php showHookTable($hookCategory['hooks']); ?>
      <?php endif ?>
      
    <?php endforeach ?>

  <?php
}
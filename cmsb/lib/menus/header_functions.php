<?php

//
function getMenuLinks() {

  // get menu list
  $menuList = _getMenuList();
  
  // construct html
  $menuLinks   = '';
  foreach ($menuList as $row) {

    // set default keys (so we don't need to check each of them with isset())
    $requiredFields = ['menuType','tableName','linkTarget','_indent','linkMessage','spanClass','liClass', 'icon-prefix-fa', 'icon-suffix-fa'];
    foreach ($requiredFields as $key) {
      if (!array_key_exists($key, $row)) { $row[$key] = ''; }
    }
    
    // check for unknown 'visibility' value
    if (!in_array(@$row['visibility'], ['', 'showAlways', 'requireLogin', 'requireAdmin', 'requireSectionAccess'])){
      @trigger_error('CMS Menu: Unknown visibility value ('.@$row['visibility'].') is set for "'.$row['menuName'].'"', E_USER_WARNING);
    }

    // don't display if user doesn't have access
    if (!_userHasMenuAccess($row)) { continue; } 

    $rowHtml = '';

    // show menu groups
    if ($row['menuType'] == 'menugroup') {
      $rowHtml .= _openMenuGroupList($row['menuName'], $row['isSelected'], $row['liClass']);
    }

    // show menu links
    else {
      $rowHtml  .= _openMenuGroupList('', $row['isSelected'], '', true, @$row['isMyAccountMenu']);

      // <li attributes
      $liAttr = [];
      $liAttr['class'] = '';
      if ($row['liClass'])    { $liAttr['class'] .= $row['liClass'] . ' '; }
      if ($row['_indent'])    { $liAttr['class'] .= "indented_menu{$row['_indent']} "; }
      if ($row['isSelected']) { $liAttr['class'] .= 'current '; }
      $liAttr['class'] = rtrim($liAttr['class']); // remove trailing spaces

      // <a attributes
      $aAttr    = [];
      $aAttr['style']   = '';
      $aAttr['href']    = $row['link'];
      $aAttr['target']  = $row['linkTarget'];
      $aAttr['onclick'] = ($row['linkMessage']) ? "alert('" .jsEncode($row['linkMessage']). "');" : '';

      // <span attributes
      $spanAttr['class'] = $row['spanClass'];
      
      // check if icon class includes proper FA5 type
      $prefixMatches = [];
      $row['icon-prefix-fa'] = $row['icon-prefix-fa'] ?? ''; // cannot be null as of PHP 8.1
      $prefixMatch   = preg_match('/(fa.?) /', $row['icon-prefix-fa'], $prefixMatches);
      $prefixType    = 'fa';
      if ($prefixMatch) { $prefixType = ''; }
      
      // prefix icon <i attributes
      $prefixIconAttr['aria-hidden'] = 'true';
      $prefixIconAttr['class']       = $row['icon-prefix-fa']   ? $prefixType . ' ' . $row['icon-prefix-fa'] : '';
      $prefixIconHtml                = $prefixIconAttr['class'] ? tag('i', ['class'=>'menu-prefix-icon'], tag('i', $prefixIconAttr, '')) : '';
      
      // check if icon class includes proper FA5 type
      $suffixMatches = [];
      $suffixMatch   = preg_match('/(fa.?) /', $row['icon-suffix-fa'], $suffixMatches);
      $suffixType    = 'fa';
      if ($suffixMatch) { $suffixType = ''; }
      
      // suffix icon <i attributes
      $suffixIconAttr['aria-hidden'] = 'true';
      $suffixIconAttr['class']       = $row['icon-suffix-fa']   ? $suffixType . ' ' . $row['icon-suffix-fa']      : ''; 
      $suffixIconHtml                = $suffixIconAttr['class'] ? tag('i', ['class'=>'menu-suffix-icon'], tag('i', $suffixIconAttr, '')) : '';
      
      // add icons to menuName
      $menuName  = $prefixIconHtml . htmlencode($row['menuName']) . $suffixIconHtml;
      
      //
      $rowHtml .= str_repeat('  ', intval($row['_indent'])+3);
      $rowHtml .= tag('li', $liAttr,
                    tag('a', $aAttr,
                      tag('span', $spanAttr, $menuName)
                    )
                  );
      $rowHtml .= "\n"; 
    }

    $rowHtml = applyFilters('menulinks_rowHtml', $rowHtml, $row);
    $menuLinks .= $rowHtml;
  }

  //
  $menuLinks .= _closeMenuGroupList();

  //
  return $menuLinks;
}

//
function _openMenuGroupList($menuGroupName, $isSelected, $liClass, $skipIfAlreadyInGroup = false, $isMyAccountMenu = false) {
  global $SHOW_EXPANDED_MENU;
  if ($skipIfAlreadyInGroup && @$GLOBALS['IN_GROUP']) { return; }

  $aClass  = 'nav-top-item';
  $liAttr  = '';
  $ulAttr  = ' style="display: none;"';

  if ($isSelected) {
    $aClass  .= ' active';
    $liClass .= ' active';
  }
  if ($isSelected || $SHOW_EXPANDED_MENU || $menuGroupName == '') {
    $ulAttr = '';
  }
  
  // hide ungrouped menus in xs view (because they are shown in a redundant xs-only "Menu" group)
  // ... but not the My Account menu items because we didn't duplicate it to go under the "Menu" group
  if ($menuGroupName === '' && !$isMyAccountMenu) {
    $liClass .= ' hidden-xs';
  }
  
  $liAttr = $liClass ? ' class="'.$liClass.'"' : '';


  $html  = _closeMenuGroupList();
  $html .= "\n  <li$liAttr>";
  if ($menuGroupName) {
      $html .= "<a href='javascript:void(0);' class='$aClass'>" . htmlencode($menuGroupName) . "</a>";
  }
  $html .= "\n    <ul$ulAttr>\n";

  $GLOBALS['IN_GROUP'] = true;

  return $html;
}

//
function _closeMenuGroupList() {
  if (!@$GLOBALS['IN_GROUP']) { return; }

  return "    </ul>\n  </li>\n";

  $GLOBALS['IN_GROUP'] = false;
}


// check if the user has access to a menu
function _userHasMenuAccess($menu){
  global $CURRENT_USER;
  
  // legacy support
  if (!@$menu['visibility']) {
    if (@$menu['showWhenLoggedOut']) { // showWhenLoggedOut - deprecated since v3.05
      $menu['visibility'] = 'showAlways';
    }
    else {
      if     (!@$menu['tableName'] && @$menu['isMyAccountMenu'])  { $menu['visibility'] = 'requireLogin'; }         // for My Account menu that doesn't have a tableName
      elseif (!@$menu['tableName'] && !@$menu['isMyAccountMenu']) { $menu['visibility'] = 'requireAdmin'; }         // for menus that doesn't have a tableName and is not My Account menu
      else                                                        { $menu['visibility'] = 'requireSectionAccess'; } // if there's a tableName, check for user access
    }
  }
  
  /*
  $menu['visibility] options:
    'showAlways'           - menu will be displayed regardless of user section access or if the user is logged in or not
    'requireLogin'         - menu will be displayed for any logged in user
    'requireAdmin          - menu will be displayed if the user is an admin
    'requireSectionAccess' - menu will be displayed if the logged in user have access to the section (all section menus should be set to this)
  */
  
  $userHasMenuAccess = false;
  if (@$menu['visibility']     == 'showAlways')                                                         { $userHasMenuAccess = true; }
  elseif (@$menu['visibility'] == 'requireLogin' && $CURRENT_USER)                                      { $userHasMenuAccess = true; } // allow access for logged in user
  elseif (@$menu['visibility'] == 'requireAdmin' && !empty($CURRENT_USER['isAdmin']))                   { $userHasMenuAccess = true; } // allow access to admin for admin menus
  elseif (@$menu['visibility'] == 'requireSectionAccess' && userSectionAccess($menu['tableName']) >= 3) { $userHasMenuAccess = true; } // accessLevel: viewer or better
  
  return $userHasMenuAccess;
}

//
function _getMenuList() {
  $menus = [];
  $selectedMenu = $GLOBALS['APP']['selectedMenu'] ?? $_REQUEST['menu'] ?? 'home';
  $menuOrder = 0;
  
  //
  $tableNames = applyFilters('tableList', getSchemaTables());

  // get schema files
  foreach ($tableNames as $tableName) {
    if ($tableName == "_media" && empty($GLOBALS['SETTINGS']['advanced']['useMediaLibrary'])) { continue; } // hide media library if it's not enabled
    
    $schema = loadSchema($tableName);
    if (!@$schema['menuType'])  { continue; }
    if (@$schema['menuHidden']) { continue; }
    $menuOrder = max($menuOrder, @$schema['menuOrder']);

    // add menu items

    $thisMenu = [];
    $thisMenu['schema']       = $schema;
    $thisMenu['menuType']     = $schema['menuType'];
    $thisMenu['menuName']     = $schema['menuName'];
    $thisMenu['menuOrder']    = $schema['menuOrder'];
    $thisMenu['tableName']    = $tableName;
    $thisMenu['isSelected']   = ($selectedMenu == $tableName);
    $thisMenu['_indent']      = isset($schema['_indent'])      ? $schema['_indent']      : 0;
    $thisMenu['_disableView'] = isset($schema['_disableView']) ? $schema['_disableView'] : 0;
    $thisMenu['link']         = "?menu=$tableName";
    $thisMenu['visibility']   = 'requireSectionAccess';
    $thisMenu['linkTarget']   = '';
    $thisMenu['linkMessage']  = '';

    if ($schema['menuType'] == 'link') {
      $isExternalLink = (@$schema['_linkTarget'] != 'iframe');
      $setTargetBlank = $isExternalLink && (@$schema['_targetBlank'] || @$schema['_linkTarget'] == 'new'); // _targetBlank is the old schema format

      if ($isExternalLink) { $thisMenu['link']           = $schema['_url']; }
      if ($setTargetBlank) { $thisMenu['linkTarget']     = '_blank'; }
      if ($isExternalLink) { $thisMenu['linkMessage']    = @$schema['_linkMessage']; } // don't show js alert() for iframe links (show them at top of iframe page)
      if ($isExternalLink) { $thisMenu['icon-suffix-fa'] = 'fa-external-link'; } // add fa-external-link suffix icon
    }
    
    if ($schema['menuType'] != 'menugroup') {
      $thisMenu['icon-prefix-fa'] = @$schema['menuPrefixIcon']; // add prefix icon set on section editor page
    }
    
    array_push($menus, $thisMenu);
  }

  // add admin menus
  $showAdminAtTop = false;
  if ($showAdminAtTop) { $menuOrder = -100; }
  $menus = array_merge($menus, _getAdminMenus($menuOrder));

  // sort menus by order value
  uasort($menus, '_sortMenusByOrder');

  $menus = array_values($menus); // re-index elements to match sort order (for operation below)

  // allow plugins to customize the menu while it's still an easily managable array
  $menus = applyFilters('menulinks_array', $menus);
  
  // if the first menu item is not a menugroup, prepend one called "Menu"
  $menus = _fixOrphanMenuItems($menus);
  
  // prepend "My Account" menugroup and its menu items (i.e. My Account, Logoff, Help, License, View Website)
  $targetIndex = 0;  // insert before the first menugroup
  for ($i = 0; $i < count($menus); $i += 1) {
    if ($menus[$i]['menuType'] === 'menugroup') {
      $targetIndex = $i;
      break;
    }
  }
  array_splice($menus, $targetIndex, 0, _getMyAccountMenus());

  // set isSelected for menuGroups
  $groupChildSelected = false;
  for ($index=count($menus)-1; $index>=0; $index--) {
    $menu = &$menus[$index];

    // set the menu group's isSelected to true if a menu under it is selected
    // .. do not set if the menugroup is hidden so that the next visible menu group
    // .. will be selected instead
    if ($menu['menuType'] == 'menugroup' && _userHasMenuAccess($menu)) {
      if ($groupChildSelected) {
        $menu['isSelected'] = true;
        $groupChildSelected = false;
      }
    }
    else if (isset($menu['isSelected']) && $menu['isSelected']) {
      $groupChildSelected = true;
    }

    unset($menu);
  }

  //
  return $menus;
}

// add admin menu header and items
function _getAdminMenus(&$menuOrder) {
  global $SETTINGS;

  $menu   = @$_REQUEST['menu'];
  $action = getRequestedAction();

  $adminMenus = [];
  $adminMenus[] = array(
    'menuType'   => 'menugroup',
    'menuName'   => t('CMS Setup'),
    'menuOrder'  => ++$menuOrder,
    'tableName'  => '',
    'link'       => '',
    'visibility' => 'requireAdmin',
    'isSelected' => '',
  );

    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Section Editors'),
      'icon-prefix-fa' => 'fa-database',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=database',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == 'database'),
    );

    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Email Templates'),
      'icon-prefix-fa' => 'fa-envelope',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=_email_templates',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == '_email_templates'),
    );

    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Code Generator'),
      'icon-prefix-fa' => 'fa-code',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=_codeGenerator',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == '_codeGenerator'),
    );


    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Plugins'),
      'icon-prefix-fa' => 'fa-puzzle-piece',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=admin&action=plugins',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == 'admin' && $action == 'plugins'),
    );

  $adminMenus[] = array(
    'menuType'   => 'menugroup',
    'menuName'   => t('Admin Menu'),
    'menuOrder'  => ++$menuOrder,
    'tableName'  => '',
    'link'       => '',
    'visibility' => 'requireAdmin',
    'isSelected' => '',
  );

  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('General Settings'),
    'icon-prefix-fa' => 'fa-sliders-h',
    'menuOrder'      => ++$menuOrder,
    'link'           => '?menu=admin&action=general',
    'visibility'     => 'requireAdmin',
    'isSelected'     => ($menu == 'admin' && ($action == 'general' || $action == 'adminSave')),
  );




  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('Security Settings'),
    'icon-prefix-fa' => 'fa-shield-alt',
    'menuOrder'      => ++$menuOrder,
    'link'           => '?menu=admin&action=security',
    'visibility'     => 'requireAdmin',
    'isSelected'     => ($menu == 'admin' && ($action == 'security' || $action == 'securitySave')),
  );
  

  if ($menu == '_log_audit' || ($SETTINGS['advanced']['auditLog_enabled'] && $menu == 'admin' && in_array($action, ['security','securitySave']))) { 
   $adminMenus[] = array(
     'menuType'       => 'custom',
     'menuName'       => t('Audit Log'),
     'icon-prefix-fa' => 'fa-fingerprint',  // fas fa-clipboard, fingerprint, search, clipboard-list
     'menuOrder'      => ++$menuOrder,
     'link'           => '?menu=_log_audit',
     'visibility'     => 'requireAdmin',
     'isSelected'     => ($menu == '_log_audit'),
     'tableName'      => '_log_audit',
     '_indent'        => 1,
   );
  }
    


  //
  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('Backup & Restore'),
    'icon-prefix-fa' => 'fa-download',
    'menuOrder'      => ++$menuOrder,
    'link'           => '?menu=admin&action=backuprestore',
    'visibility'     => 'requireAdmin',
    'isSelected'     => ($menu == 'admin' && in_array($action, ['backuprestore', 'backup', 'restore'])),
    '_indent'        => 0,
  );

  //
  $secondsSinceLastRun = time() - intval($SETTINGS['bgtasks_lastRun']);
  $hasRunInLastHour    = $secondsSinceLastRun <= (60*60*1);
  $showBgTasksWarning  = !$hasRunInLastHour || $SETTINGS['bgtasks_disabled'];

  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('Background Tasks'),
    'icon-prefix-fa' => 'fa-calendar-check',  //
    'icon-suffix-fa' => $showBgTasksWarning ? 'fa-exclamation-triangle' : '',
    'menuOrder'      => ++$menuOrder,
    'link'           => '?menu=admin&action=bgtasks',
    'visibility'     => 'requireAdmin',
    'isSelected'     => ($menu == 'admin' && ($action == 'bgtasks' || $action == 'bgtasksSave')),
    '_indent'        => 0,
    'spanClass'      => $showBgTasksWarning ? 'text-danger' : '',
  );

  //
  if ($menu == '_cron_log' || $action == 'bgtasks' || $action == 'bgtasksSave') { // show if background tasks or task log is selected   
    $adminMenus[] = array(
      'menuType'   => 'custom',
      'menuName'   => t('Task Log') . " (" .mysql_count('_cron_log'). ")", //
      'icon-prefix-fa' => 'fa-list',
      'menuOrder'  => ++$menuOrder,
      'link'       => '?menu=_cron_log',
      'visibility' => 'requireAdmin',
      'isSelected' => ($menu == '_cron_log'),
      '_indent'    => 1,
    );
  }

  //
  $showEmailSettingsWarning = $SETTINGS['advanced']['outgoingMail'] == 'logOnly';
  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('Email Settings'),
    'icon-prefix-fa' => 'fa-envelope',
    'icon-suffix-fa' => $showEmailSettingsWarning ? 'fa-exclamation-triangle' : '',
    'menuOrder'      => ++$menuOrder,
      'link'         => '?menu=admin&action=email',
      'visibility'   => 'requireAdmin',
      'isSelected'   => ($menu == 'admin' && ($action == 'email' || $action == 'emailSave')),
    '_indent'        => 0,
    'spanClass'      => $showEmailSettingsWarning ? 'text-danger' : '',
  );

  //
  if ($menu == '_outgoing_mail' || $action == 'email' || $action == 'emailSave') { // show if email settings or outgoing mail is selected
    $count     = mysql_count('_outgoing_mail');
    $countText = $count ? " ($count)" : "";
    $adminMenus[] = array(
      'menuType'   => 'custom',
      'menuName'   => t('Outgoing Mail') . $countText, //
      'icon-prefix-fa' => 'fa-history',
      'menuOrder'  => ++$menuOrder,
      'link'       => '?menu=_outgoing_mail',
      'visibility' => 'requireAdmin',
      'isSelected' => ($menu == '_outgoing_mail'),
      '_indent'    => 1,
    );
    
    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Email Templates'),
      'icon-prefix-fa' => 'fa-envelope',
      'icon-suffix-fa' => 'fa-external-link-alt',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=_email_templates',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == '_email_templates'),
      '_indent'        => 1,
    );
  }

  // only show when sub licnencing is allowed or when the user is already on the vendor page. 
  $showPrivateLabelingMenu = allowSublicensing() || in_array($action, ['branding','brandingSave','vendor']);
  if ($showPrivateLabelingMenu) {
    $adminMenus[] = array(
      'menuType'       => 'custom',
      'menuName'       => t('Branding'),
      'icon-prefix-fa' => 'fa-tag',
      'menuOrder'      => ++$menuOrder,
      'link'           => '?menu=admin&action=branding',
      'visibility'     => 'requireAdmin',
      'isSelected'     => ($menu == 'admin' && (in_array($action, ['vendor', 'branding', 'brandingSave']))),
    );
  }


  //
  $errorCount = isInstalled() ? mysql_count('_error_log') : 0;
  $adminMenus[] = array(
    'menuType'       => 'custom',
    'menuName'       => t('Developer Log') . " ($errorCount)",
    'icon-prefix-fa' => 'fa-terminal',
    'menuOrder'      => ++$menuOrder,
    'link'           => '?menu=_error_log',
    'visibility'     => 'requireAdmin',
    'isSelected'     => ($menu == '_error_log'),
    'tableName'      => '_error_log',
    'spanClass'      => $errorCount ? 'text-danger' : '',
  );
  //array_pop($adminMenus); // remove "Error Log" from menu

  //
  return $adminMenus;
}

// adds "Menu" menu group that is visible only on mobile view
// .. so that the "orphan menus" (menus that don't have a menu group OR users don't have access to the menu's menu group)
// .. don't appear under the "My Accounts" menu group if on mobile view
function _fixOrphanMenuItems($menus) {
  
  // if there are no menu items, don't add a top level menugroup
  if (!$menus) { return $menus; }

  // if the first menu item is already a menugroup and user has access to it
  // .. we don't need to add a top level menugroup
  if ($menus[0]['menuType'] === 'menugroup' && _userHasMenuAccess($menus[0])) {
    return $menus;
  }
  
  // duplicate orphaned menu items for large screens
  $duplicatedOrphanItemsForLargeScreens = [];
  $orphanedMenuCount                    = 0;
  foreach ($menus as $row) {
    // get menu access
    $hasMenuAccess = _userHasMenuAccess($row);
    
    if (!$hasMenuAccess)                                         { continue; } // skip if user doesn't have access - no need to duplicate or add under the "Menu" menu group
    elseif ($row['menuType'] === 'menugroup' && !$hasMenuAccess) { continue; } // if this menu group is hidden, menus under it are considered orphaned
    elseif ($row['menuType'] === 'menugroup' && $hasMenuAccess)  { break; }    // stop adding orphaned menu on first unhidden menu group
    
    $dupedRow = $row + [ 'liClass' => 'hidden-xs' ]; // hide duped menu on mobile
    array_push($duplicatedOrphanItemsForLargeScreens, $dupedRow);
    $orphanedMenuCount++;
  }
  
  // don't add "Menu" if there is no orphaned menu items
  if ($orphanedMenuCount == 0) { return $menus; }

  // create menu group that is visible only on mobile view for orphaned menu items so that they will not get displayed under "My Account" group
  $topMenuGroup = [
    'menuType'          => 'menugroup',
    'menuName'          => t('Menu'),
    'isSelected'        => '',
    'liClass'           => 'hidden-sm hidden-md hidden-lg',
    'visibility'        => 'showAlways',
  ];
  array_unshift($menus, $topMenuGroup);
  
  // prepend duplicated orphaned menu items (shown only on larger screens)
  $menus = array_merge($duplicatedOrphanItemsForLargeScreens, $menus);
  
  return $menus;
}

//
function _getMyAccountMenus() {
  global $SETTINGS, $CURRENT_USER;
  $menu = @$_REQUEST['menu'];
  $menus = [];
  
  // menus for logged in user only ('visibility' = 'requireLogin')
  $menus[] = [
    'menuType'          => 'custom',  // This one shows on the main menu
    'menuName'          => t('My Account'),
    'link'              => '?menu=_myaccount',
    'visibility'        => 'requireLogin',
    'isSelected'        => ($menu === '_myaccount'),
  ];
  $menus[] = [
    'menuType'          => 'custom',
    'menuName'          => sprintf(t("Logoff (%s)"), htmlencode($CURRENT_USER['username'] ?? '')),
    'link'              => '?action=logoff',
    'visibility'        => 'requireLogin',
    'isSelected'        => false,
    'br_after'          => true,
  ];

  // menus displayed even if the user is not logged in ('visibility' = 'showAlways')
  $menus[] = [
    'menuType'          => 'custom',
    'menuName'          => t('Help'),
    'link'              => getEvalOutput( $SETTINGS['helpUrl'] ),
    'visibility'        => 'showAlways',
    'linkTarget'        => '_blank',
    'isSelected'        => false,
  ];
  $menus[] = [
    'menuType'          => 'custom',
    'menuName'          => t('License'),
    'link'              => '?menu=license',
    'visibility'        => 'showAlways',
    'isSelected'        => ($menu === 'license'),
  ];
  $menus[] = [
    'menuType'          => 'custom',
    'menuName'          => t('View Website >>'),
    'link'              => getEvalOutput( $SETTINGS['websiteUrl'] ),
    'visibility'        => 'showAlways',
    'linkTarget'        => '_blank"',
    'isSelected'        => false,
    'linkTarget'        => '_blank',
    'br_after'          => true,
  ];

  // Allow plugins to override these menus
  // Example of custom menu:
  //$menus[] = [
  //  'menuName'          => t('My Custom Menu'),
  //  'menuType'          => 'custom',
  //  'link'              => "?my_custom=link",
  //  'visibility'        => 'showAlways', // options: showAlways, requireLogin, requireSectionAccess
  //  'isSelected'        => ($menu === 'my_custom_menu'), // set to true to display this menu as selected on menu
  //  //'class'           => "",                           // optional: add this class to menu <li> tag
  //  //'linkTarget'      => '_blank',                     // optional: set this to open link in a new tab
  //  //'br_after'        => true,                         // optional: add line break after menu item instead of separator ("|")
  //];
  $menus = applyFilters('menulinks_myAccount', $menus);
  
  // add 'liClass' => 'hidden-sm hidden-md hidden-lg' to My Account menu items so that they will ONLY get added to the main menu on mobile view
  // ... on big screens, they will be displayed on the header links
  foreach ($menus as &$menu) {
    $menu['liClass'] = $menu['liClass'] ?? ''; // cannot pass null to preg_replace() as of PHP 8.1
    if (!preg_match("/hidden-sm hidden-md hidden-lg/", $menu['liClass'])) {
      $menu['liClass']         = @$menu['liClass'] ? $menu['liClass'] . ' hidden-sm hidden-md hidden-lg' : 'hidden-sm hidden-md hidden-lg';
      $menu['isMyAccountMenu'] = true;
    }
  }
  
  // add menu group at the top (beginning of the array) for mobile view
  // ... added here so that even the filter menulinks_myAccount modifies the menu order, this will always be on top
  $mobileMyAccountMenuGroup = [
    'menuType'          => 'menugroup', // This is the "My Account" that shows in the Mobile Menu on small screens
    'menuName'          => t('My Account'),
    'visibility'        => 'requireLogin',
    'liClass'           => 'hidden-sm hidden-md hidden-lg',
    'isSelected'        => false,
  ];
  array_unshift($menus, $mobileMyAccountMenuGroup);
  
  //
  return $menus;
}

function getMyAccountMenu_AsTextLinks() {
  $html = '';
  
  $myAccountMenus = _getMyAccountMenus();
  
  for ($i = 0; $i < count($myAccountMenus); $i += 1) {
    $row = $myAccountMenus[$i];
    if ($row['menuType'] === 'menugroup') { continue; } // ignore menugroup
    
    $hasMenuAccess = _userHasMenuAccess($row);
    if (!$hasMenuAccess) { continue; }
    
    $html .= tag('a', ['href' => $row['link'], 'target' => @$row['linkTarget']],
               tag('span', ['class' => 'title'], htmlencode($row['menuName']))
            );

    $isLastMenu = (($i+1) == count($myAccountMenus));

    if (!$isLastMenu) {
      $br_after = isset($row['br_after']) && $row['br_after'];
      $html .= $br_after ? "<br>\n" : " | ";
    }

  }
  return $html;
}

// eof
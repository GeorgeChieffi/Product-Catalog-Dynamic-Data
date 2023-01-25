<?php


header('Content-type: text/html; charset=utf-8');
global $APP, $SETTINGS, $CURRENT_USER, $TABLE_PREFIX, $SHOW_EXPANDED_MENU;
$SHOW_EXPANDED_MENU = isset($CURRENT_USER['showExpandedMenu']) ? $CURRENT_USER['showExpandedMenu'] : $SETTINGS['advanced']['showExpandedMenu'];
require_once "lib/menus/header_functions.php";

$menuLinks = getMenuLinks();

?><!DOCTYPE html>

<html lang="<?php echo htmlencode($SETTINGS['language'] ?: 'en') ?>">
<head>
  <meta charset="utf-8">
  <title><?php
    if (@$GLOBALS['ADMINUI_ARGS']['_PAGE_TITLE_TEXT']) {
      echo $GLOBALS['ADMINUI_ARGS']['_PAGE_TITLE_TEXT'];
      echo ' - ';
    }
    echo htmlencode($SETTINGS['programName']);
  ?></title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <?php include "lib/menus/header_css.php"; ?>

  <!-- javascript and css -->
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery.js"></script>
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jquery-ui-1.11.4.min.js"></script><?php /* for datepicker and jquery sortable */ ?>
  <link  href="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryUI/css/smoothness/jquery-ui-1.8.18.custom.css" rel="stylesheet" type="text/css">
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/json2.js"></script>
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/dirrty/jquery.dirrty.js"></script>
  <link href="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/select2/dist/css/select2.min.css" rel="stylesheet">
  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/select2/dist/js/select2.min.js"></script>
  <link href="<?php echo CMS_ASSETS_URL ?>/3rdParty/font-awesome/css/all.min.css" rel="stylesheet">
  <link href="<?php echo CMS_ASSETS_URL ?>/3rdParty/font-awesome/css/v4-shims.min.css" rel="stylesheet">
  <script src="<?php echo noCacheUrlForCmsFile("lib/admin_functions.js"); ?>"></script>
  <script src="<?php echo noCacheUrlForCmsFile("lib/dragsort.js"); ?>"></script>

  <!--[if lte IE 6]><script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/jqueryPlugins/DD_belatedPNG_0.0.7a.js"></script><![endif]-->
  <!--[if lte IE 6]><script>DD_belatedPNG.fix('#main-content ul li, #sidebar-title img')</script><![endif]-->
  <script>
  <!-- // language strings for javascript prompts
  lang_confirm_erase_record = '<?php echo addslashes(t("Delete this record? Are you sure?")) ?>';
  //-->
  </script>
  <?php if (is_file("{$GLOBALS['PROGRAM_DIR']}/custom.js")): ?>
    <script src="<?php echo noCacheUrlForCmsFile('custom.js')?>"></script>
  <?php endif ?>

  <script src="<?php echo CMS_ASSETS_URL ?>/3rdParty/clipone/plugins/bootstrap/js/bootstrap.min.js"></script>
  <!-- /javascript -->

  <!-- datepicker -->
  <?php
  // load date picker language file
  $datePickerLangRelPath = "/3rdParty/jqueryUI/i18n/jquery.ui.datepicker-{$SETTINGS['language']}.js";
  $datepickerLangUrl     = CMS_ASSETS_URL . $datePickerLangRelPath;
  $datepickerLangPath    = SCRIPT_DIR     . $datePickerLangRelPath;

  if     (!$SETTINGS['language'])       { print "<!-- datepicker language: default, no language selected -->\n"; }
  elseif (is_file($datepickerLangPath)) { print "<script src='" .htmlencode($datepickerLangUrl). "'></script>\n"; }
  else                                  { print "<!-- datepicker language: not loaded, file doesn't exist: $datePickerLangRelPath -->\n"; }
  ?>
  <!-- /datepicker -->

  <?php doAction('admin_head'); ?>

  <?php // make PHP constants available to Javascript as needed ?>
  <script>
  // Example: phpConstant('CMS_ASSETS_URL') + "/path/to/file.js";
  function phpConstant(cname) {
    if      (cname == 'PREFIX_URL')     { return "<?php echo jsencode(PREFIX_URL); ?>"; }
    else if (cname == 'CMS_ASSETS_URL') { return "<?php echo jsencode(CMS_ASSETS_URL); ?>"; }
    else {
      alert("phpConstant: Unknown constant name '" +cname+ "'!");
      return '';
    }
  }
  </script>

</head>

<body class="header-default">

<?php
  $usingDevSettingsFile = SETTINGS_FILENAME != 'settings.dat.php';
  if ($usingDevSettingsFile) {
    $message = sprintf("Using development settings file: %s", "/data/".SETTINGS_FILENAME);
    $onclick = "alert('" .jsEncode($message). "');";
    $title   = htmlencode($message);
    $text    = t('dev');
    print <<<__HTML__
      <div style="position: fixed; top: 0; left: 0; background: #F00; color: #FFF; font-weight: bold; z-index: 999; padding: 0px 12px" title="$title">
        <a style="color: #FFF;" href="#" onclick="$onclick">$text</a>
      </div>
__HTML__;
  }
?>

<!-- start: HEADER -->
<div class="navbar navbar-inverse navbar-top hidden-sm hidden-md hidden-lg">
  <!-- start: TOP NAVIGATION CONTAINER -->
  <div class="container">
    <div class="navbar-header">

      <!-- start: RESPONSIVE MENU TOGGLER -->
      <button data-target=".navbar-collapse" data-toggle="collapse" class="navbar-toggle" type="button">
        <span class="clip-list-2"></span>
      </button>
      <!-- end: RESPONSIVE MENU TOGGLER -->

      <!-- start: LOGO -->
      <div class="sidebar-title">
        <a href="?menu=home"><?php echo htmlencode($SETTINGS['programName']); ?></a>
      </div>
      <!-- end: LOGO -->
    </div>

  </div>
  <!-- end: TOP NAVIGATION CONTAINER -->
</div>
<!-- end: HEADER -->


<!-- start: MAIN CONTAINER -->
<div class="main-container">

  <div class="navbar-content">

    <!-- start: SIDEBAR -->
    <div class="main-navigation navbar-collapse collapse">
      
      <!-- PROGRAM NAME / IMAGE -->
      <div class="sidebar-title hidden-xs">
        <?php if (@$SETTINGS['headerImageUrl']): ?>
          <a href="?menu=home"><img src="<?php echo getEvalOutput($SETTINGS['headerImageUrl']) ?>" alt="<?php echo htmlencode($SETTINGS['programName']); ?>"></a>
        <?php else: // Logo (221px wide) ?>
          <a href="?menu=home"><?php echo htmlencode($SETTINGS['programName']); ?></a>
        <?php endif ?>
      </div>
      
      <div class="sidebar-myaccount hidden-xs">
        <?php echo getMyAccountMenu_AsTextLinks() ?>
      </div>
      
      <!-- start: MAIN NAVIGATION MENU -->
      <ul class="main-navigation-menu" id="main-nav">
        <?php echo $menuLinks ?>
      </ul>

      <?php if ($SHOW_EXPANDED_MENU): ?>
      <div id="jquery_showExpandedMenu" style="display: none"></div>
      <?php endif ?>
      <!-- end: MAIN NAVIGATION MENU -->

    </div>
    <!-- end: SIDEBAR -->

  </div>

  <!-- start: PAGE -->
  <div class="main-content">
    <div class="container">

      <!-- Show a notification if the user has disabled javascript -->
      <noscript>
        <div class="alert alert-warning">
          <span class="label label-warning">NOTE!</span>
          <span><?php et("Javascript is disabled or is not supported by your browser. Please upgrade your browser or enable Javascript to navigate the interface properly."); ?></span>
        </div>
      </noscript>

      <?php displayAlertsAndNotices(); ?>

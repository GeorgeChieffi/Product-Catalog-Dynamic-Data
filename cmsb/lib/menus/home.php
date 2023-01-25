<?php

// prepare adminUI() placeholders
$adminUI = [];

// page title
$pageTitle = sprintf(t('Welcome to %s'), $GLOBALS['SETTINGS']['programName']);
$pageTitle = applyFilters('home_title', $pageTitle);
$adminUI['PAGE_TITLE'] = [ $pageTitle ];

// main content
$adminUI['CONTENT'] = "<p>" . t('Please select an option from the menu.') . "</p>";
if ($GLOBALS['CURRENT_USER']['isAdmin']) {
  $adminUI['CONTENT'] .= "<p>" . t('<b>Administrators:</b> Use the <a href="?menu=database">Section Editors</a> to add sections and generate PHP viewers.') . "</p>";
}
$adminUI['CONTENT'] = applyFilters('home_content', $adminUI['CONTENT']);

// compose and output the page
adminUI($adminUI);

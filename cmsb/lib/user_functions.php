<?php
/*
  Access Levels: these are stored in the '_accesslist' by tablename or with a tablename of 'all' to apply to all tables
  Max Records: Max records user is allowed to create (for regular users only - leave blank for unlimited)

  0 - None            - Don't allow user to access this section
  1 - All:By Section  - AccessLevel used by 'all' to indicate accesslevels are set "By Section"
  3 - Viewer          - User can view any record in this section (must also be enabled in section editor)
  6 - Author          - User can only access records they have created
  7 - Author & Viewer - User can view any record and modify records they've created
  9 - Editor          - User can access any records in this section
*/

// get user access to table, 0:none, 3:viewer, 6:author, 7:author/viewer, 9:editor
/*
  $hasEditorAccess           = (userSectionAccess($tableName) >= 9);
  $hasAuthorViewerAccess     = (userSectionAccess($tableName) >= 7);
  $hasAuthorViewerAccessOnly = (userSectionAccess($tableName) == 7);
  $hasAuthorAccess           = (userSectionAccess($tableName) >= 6);
  $hasViewerAccess           = (userSectionAccess($tableName) >= 3);
  $hasViewerAccessOnly       = (userSectionAccess($tableName) == 3);
*/
function userSectionAccess($tableName) { // added in v2.16
  global $CURRENT_USER;
  $tableName = getTableNameWithoutPrefix($tableName);

  //
  // NOTE: If you call this function with a blank tablename it will return the
  // default access level that the user has for "All Sections", eg: 3 for Viewer
  //

  // get access level
  if (@$CURRENT_USER['accessList']['all']['accessLevel'] > 1)      { $accessLevel = $CURRENT_USER['accessList']['all']['accessLevel']; }
  elseif (@$CURRENT_USER['accessList'][$tableName]['accessLevel']) { $accessLevel = @$CURRENT_USER['accessList'][$tableName]['accessLevel']; }
  else                                                             { $accessLevel = 0; }

  // accounts menu (special rules)
  if ($tableName == 'accounts') {
    if     (@$CURRENT_USER['isAdmin']) { $accessLevel = 9; } // admin users can always access the accounts menu
    elseif ($accessLevel < 9)          { $accessLevel = 0; } // accounts menu requires admin or editor access
  }

  // other tables: don't allow viewer-only access unless section allows it
  if ($tableName && in_array($accessLevel, [3,7])) {
    $schema = loadSchema($tableName);
    if (@$schema['_disableView']) {
      if ($accessLevel == 7) { $accessLevel = 6; } // drop author/viewer access to author only
      else                   { $accessLevel = 0; } // drop viewer only access to no access
    }
  }

  //
  $accessLevel = applyFilters('userSectionAccess', $accessLevel, $tableName);
  return $accessLevel;
}

// returns true or false if user has access to a field.
// Note that the $accessRule value is stored in the legacy fieldname 'adminOnly' in the schemas (to be rename in a future release)
// $accessRule indicates field access level, the the values are defined with the legacy name 'adminOnly'
// in /lib/menus/database/editField.php and are as follows: 0:Everyone, 1:Editors Only, 2:Admin Only
function userHasFieldAccess($fieldSchema) { // args changed as of 2.16, used to be ($tableName, $accessRule) {
  global $CURRENT_USER;
  if (!array_key_exists('_tableName', $fieldSchema)) { dieAsCaller("Fieldschema missing '_tableName' value!"); }
  if (!array_key_exists('name',       $fieldSchema)) { dieAsCaller("Fieldschema missing 'name' value!"); }

  // check if user has access to this field
  $hasAccess       = false;
  $accessRule      = @$fieldSchema['adminOnly'];  // this schema key is a legacy fieldname that will be renamed in future
  $hasEditorAccess = (userSectionAccess($fieldSchema['_tableName']) >= 9);
  if     ($CURRENT_USER['isAdmin'])                     { $hasAccess = true; } // admins can access all fields
  elseif (!$accessRule)                                 { $hasAccess = true; } // no access rule (zero or blank) = allow everyone
  elseif ($accessRule == 1 && $hasEditorAccess)         { $hasAccess = true; } // requires editor access
  elseif ($accessRule == 2 && $CURRENT_USER['isAdmin']) { $hasAccess = true; } // requires admin access (ignored since admins can access all fields)

  $hasAccess = applyFilters('userHasFieldAccess', $hasAccess, $fieldSchema);
  return $hasAccess;
}

// eof
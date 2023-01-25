<?php
  // Viewer Functions v3.0

/* Examples: 

  // load records
  require_once "cmsb/lib/viewerAPI.php";
  list($tablename_records, $tablename_metadata) = getRecordsAPI([
    'tableName' => 'every_field_multi',
    'where'     => 'num = :num',
    'orderBy'   => '',         // optional, defaults to $_REQUEST['orderBy'], or table sort order
    'params'    => [           // params can be used in these fields: where, orderBy
      ':num' => whereRecordNumberInUrl(1),
    ],

    // OPTION REFERENCE: - You can remove this if you don't need it

    // limiting results: with paging -OR- offset/limit
    'perPage'              => '',                 // default: 10, show X records per page
    'pageNum'              => '',                 // defaults $_REQUEST['page'] or 1 (if not defined), displays specified page of results
    'offset'               => '',                 // default: 0,  skip the first Y records
    'limit'                => '',                 // default: 10, only show the first X records

    // filtering results: with MySQL WHERE queries
    'where'     => 'num = :num AND region = :region AND category = :category', // format: fieldname = :paramName
    'params'    => [                              // params can be used in these fields: where, orderBy
      ':num'      => whereRecordNumberInUrl(1),   // specify any values you want to use here
      ':region'   => 'north',
      ':category' => @$_REQUEST['category'],
    ],

    // ordering results: with MySQL ORDER queries
    'orderBy'   => 'city, eventDate DESC',        // defaults to $_REQUEST['orderBy'], or table sort order

    // filtering results: with search forms and links
    'searchEnabled'        => true,               // default: true, automatically filter results based on search terms in URL or form submission, eg: ?price_min=100 or ?category=4
    'searchMatchRequired'  => false,              // default: false, don't show any results unless search keyword submitted and matched
    'searchSuffixRequired' => false,              // default: false, search fields must end in a suffix such as _match or _keyword, shorthand field=value format is ignored

    // advanced options:
    'loadUploads'          => true,               // default: true, adds additional upload fields for displaying uploads and thumbnails
    'loadPseudoFields'     => true,               // default: true, adds additional fields with extra data for checkboxes, etc such as: :text, :label, :values, :labels, :unixtime
    'supportHidden'        => true,               // default: true, hide records with hidden flag set
    'supportPublishDate'   => true,               // default: true, hide records with publishDate > now
    'supportRemoveDate'    => true,               // default: true, hide records with removeDate < now
    'debugSql'             => false,              // default: false, for debugging: print out the actual SQL queries being generated and executed
    'outputJSON'           => false,              // default: false, for debugging: print out the actual SQL queries being generated and executed
    
    // Remote API options: (undocumented and unsupported)
    'apiVersion'           => '',
    'debugAPI'             => true,
    'useCustomPatch'       => "d41d8cd98f00b";    // not yet implemented
    '_REQUEST'             => [],

    // -----------------------------------------------------------------------
    // pending options and features:

    'includeDisabledAccounts' => true,  // include records that were created by disabled accounts.  See: Admin > Section Editors > Advanced > Disabled Accounts
    'columns'                 => '',    // optional, default to * (all)
    'loadCreatedBy'           => '',    // (find generic way to add relatedRecords - review Chris plugin) default: true, adds createdBy.* user fields for the record creator specified in createdByUserNum
    // preview record functionality //

  ));
*/

// load viewerAPI library
if     (file_exists(__DIR__."/viewerAPI_client.php"))    { require_once(__DIR__."/viewerAPI_client.php"); }
elseif (file_exists(__DIR__."/viewerAPI_functions.php")) { require_once(__DIR__."/viewerAPI_functions.php"); }
else                                                     { die("Couldn't find viewerAPI library!"); }

// eof


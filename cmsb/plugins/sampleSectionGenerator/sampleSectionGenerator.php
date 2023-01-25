<?php
/*
Plugin Name: Sample Section Generator
Description: Creates and removes sample sections to allow for testing
Version: 0.01
Requires at least: 3.00
*/

// CAUTION: because of its randomized nature, this algorithm can theoretically create "bad words"!

// example usage:
// NonsenseWordGenerator::buildWord();        // e.g. "thoreti"
// NonsenseWordGenerator::buildWord(3);       // e.g. "them"      // NOTE: arg is $maxWordParts, not "max letters"; both "xul" and "quawth" are 3 word parts long
// NonsenseWordGenerator::buildSentence();    // e.g. "Ret elaloo mawnaf asal azefat sedenath eligesoh ootisha thoreti orthanif gavoru oci."
// NonsenseWordGenerator::buildSentence(3);   // e.g. "Nor soha biguth."

class NonsenseWordGenerator {
  
  // words are composed of alternating consonant sounds and vowel sounds
  // numbers indicate weighting (i.e. a sound with a weight of 10 will appear 10x as frequently as a sound with a weight of 1)
  static $wordParts = [
    'c' => [ // consonant sounds
      'b'  => 18,
      'c'  => 15,
      'd'  => 18,
      'f'  => 21,
      'g'  => 21,
      'h'  => 18,
      'j'  => 9,
      'k'  => 9,
      'l'  => 30,
      'm'  => 9,
      'n'  => 27,
      'p'  => 3,
      'r'  => 45, // RSTLNE ftw
      's'  => 39,
      't'  => 33,
      'v'  => 6,
      'w'  => 6,
      'x'  => 1,
      'y'  => 2,
      'z'  => 2,
      'sh' => 9,
      'ch' => 9,
      'th' => 18,
      'wh' => 2,
      'qu' => 1,
    ], 
    'v' => [ // vowel sounds
      'a'  => 12,
      'e'  => 15,
      'i'  => 8,
      'o'  => 8,
      'u'  => 3,
      'oo' => 1,
      'oi' => 1,
      'ur' => 1,
      'or' => 1,
      'ou' => 1,
      'aw' => 1,
    ]
  ];
  
  static $cache = null;
  
  static function setupCache() {
    self::$cache = [];
    foreach (['c', 'v'] as $mode) {
      self::$cache[$mode] = [];
      foreach (self::$wordParts[$mode] as $wordPart => $weighting) {
        foreach (range(1, $weighting) as $i) {
          self::$cache[$mode][] = $wordPart;
        }
      }
      self::$cache[$mode . '_count'] = count(self::$cache[$mode]);
    }
  }
  
  static function swapModes($mode) {
     return ($mode === 'c') ? 'v' : 'c';
  }
  
  static function getRandomWordPart($mode) {
    if (!self::$cache) {
      self::setupCache();
    }
    return self::$cache[$mode][ rand(0, self::$cache[$mode . '_count'] - 1) ];
  }
  
  static function buildWord($maxWordParts = 0) {
    srand();
    $mode = 'c';
    if (rand(0, 1) === 0) { $mode = self::swapModes($mode); }
    if (!$maxWordParts) {
      $maxWordParts = rand(1, 3) + rand(1, 3) + rand(0, 3);
    }
    $word = '';
    foreach (range(1, $maxWordParts) as $i) {
      $word .= self::getRandomWordPart($mode);
      $mode = self::swapModes($mode);
    }
    return strtolower($word);
  }
  
  static function buildSentence($maxWords = 0) {
    srand();
    if (!$maxWords) {
      $maxWords = rand(8, 12);
    }
    $sentence = '';
    foreach (range(1, $maxWords) as $i) {
      $sentence .= ' ' . self::buildWord();
    }
    $sentence = ucfirst(trim($sentence)) . '.';
    return $sentence;
  }
  
}

function sampleSectionGenerator_multi_record_sample() {
  $tableName = 'sample_multi_record';
  
  // clear all records from table
  mysql_delete($tableName, null, 'TRUE');
  
  // options for 'status' field (weighting is achieved by specifying options multiple times)
  $statuses = ['Approved', 'Approved', 'Approved', 'Rejected', 'Pending'];
  
  // generate random record data
  $records = [];
  foreach (range(1, 2345) as $i) {
    $date = mysql_datetime(strtotime('today - ' . rand(0, 1000) . ' days'));
    $records[] = [
      'title'         => trim(NonsenseWordGenerator::buildSentence(rand(2, 4)), '.'),
      'content'       => NonsenseWordGenerator::buildSentence(rand(10, 20)),
      'createdDate'   => $date,
      'updatedDate'   => $date,
      'date'          => $date,
      'checked'       => rand(0, 1),
      'status'        => $statuses[rand(0, count($statuses) - 1)],
      'dragSortOrder' => strtotime($date),
    ];
  }
  
  // sort records by 'dragSortOrder' (same sorting as 'createdDate') so their nums will be in same order as createdDate
  array_multisort(array_pluck($records, 'dragSortOrder'), SORT_DESC, $records);
  
  // insert records
  foreach ($records as $record) {
    mysql_insert($tableName, $record, true);
  }
}

function sampleSectionGenerator_category_sample() {
  $tableName = 'sample_category';
  
  // clear all records from table
  mysql_delete($tableName, null, 'TRUE');
  
  // options for 'status' field (weighting is achieved by specifying options multiple times)
  $statuses = ['Approved', 'Approved', 'Approved', 'Rejected', 'Pending']; // 

  // generate random record data
  $records = [];
  foreach (range(1, 200) as $i) {
    $date = mysql_datetime(strtotime('today - ' . rand(0, 1000) . ' days'));
    $records[] = [
      'name'        => trim(NonsenseWordGenerator::buildSentence(rand(1, 1)), '.'),
      'content'     => NonsenseWordGenerator::buildSentence(rand(10, 20)),
      'createdDate' => $date,
      'updatedDate' => $date,
    ];
  }
  
  // insert records and randomly set category parents to previously inserted records
  $parentStack = [0];
  $resetChaos  = 0;
  $upChances   = [80, 50, 10, 0];
  $downChances = [0, 10, 50, 50];
  foreach ($records as $record) {
    // insert record
    $record['parentNum'] = $parentStack[0];
    $lastNum = mysql_insert($tableName, $record, true);
    
    // randomly choose to go up or down one level of depth 
    $r            = rand(0, 99);
    $currentDepth = count($parentStack) - 1;
    $upChance     = $upChances[$currentDepth];
    $downChance   = $downChances[$currentDepth];
    if ($r < $upChance) {
      array_unshift($parentStack, $lastNum);
    }
    elseif ($r < $upChance + $downChance) {
      array_shift($parentStack);
      if (!count($parentStack)) { $parentStack = [0]; }
    }
    
    // randomly reset back to category root
    $resetChaos += 8;
    if (rand(0, 99) < $resetChaos) {
      $parentStack = [0];
    }
    if (count($parentStack) < 2) { $resetChaos = 0; }
  }
  
  // fix category special fields (e.g. lineage, breadcrumbs, depth)
  require_once(SCRIPT_DIR . '/lib/menus/default/common.php');
  global $escapedTableName, $schema;
  $escapedTableName = $GLOBALS['TABLE_PREFIX'] . mysql_escape($tableName);
  $schema = loadSchema($tableName);
  updateCategoryMetadata();
}

addAction('plugin_actions', function($currentRowPluginPath) {
  list($pluginPath, $pluginUrl) = getPluginPathAndUrl(null);
  if ($currentRowPluginPath === $pluginPath) {
    if (file_exists(DATA_DIR . "/schema/sample_sections.ini.php")) {
      echo "<a href='" .pluginAction_getLink('sampleSectionGenerator_removeSections'). "'>" .htmlencode('Remove Sample Sections'). "</a><br>\n";
    }
    else {
      echo "<a href='" .pluginAction_getLink('sampleSectionGenerator_createSections'). "'>" .htmlencode('Create Sample Sections'). "</a><br>\n";
    }
  }
});

pluginAction_addHandler('sampleSectionGenerator_createSections', 'admins');
function sampleSectionGenerator_createSections() {
  plugin_createSchemas(['sample_sections', 'sample_multi_record', 'sample_single_record', 'sample_category']);
  notice("Generating sample records for schema table: sample_multi_record<br>\n");
  sampleSectionGenerator_multi_record_sample();
  notice("Generating sample records for schema table: sample_category<br>\n");
  sampleSectionGenerator_category_sample();
}

pluginAction_addHandler('sampleSectionGenerator_removeSections', 'admins');
function sampleSectionGenerator_removeSections() {
  foreach (['sample_sections', 'sample_multi_record', 'sample_single_record', 'sample_category'] as $tableName) {
    $tableNameWithPrefix = getTableNameWithPrefix($tableName);
    
    // drop MySQL table
    mysqli()->query("DROP TABLE IF EXISTS `".mysql_escape($tableNameWithPrefix)."`") or die("Error dropping MySQL table:\n\n". htmlencode(mysqli()->error) . "\n");
    
    // delete schema file
    $tableNameWithoutPrefix = getTableNameWithoutPrefix($tableNameWithPrefix);
    $schemaFilepath         = DATA_DIR . "/schema/$tableNameWithoutPrefix.ini.php";
    $dataFilepath           = DATA_DIR . "/schema/$tableNameWithoutPrefix.defaultSqlData.php";
    if (file_exists($schemaFilepath)) { unlink($schemaFilepath); }
    if (file_exists($dataFilepath))   { unlink($dataFilepath);   }
  }
}

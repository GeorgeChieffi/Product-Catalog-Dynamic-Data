<?php require_once "cmsb/lib/viewer_functions.php"; ?>
<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

  // load records from 'product'
  list($productRecords, $productMetaData) = getRecords(array(
    'tableName'   => 'product',
    'loadUploads' => true,
    'allowSearch' => false,
  ));

?>


<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
  </head>
    <body class="m-3">
      <!-- /INSTRUCTIONS -->
      <?php if($CURRENT_USER['isAdmin']):?>
        <div class="">
          <h3 class="text-primary">Admins may vist the admin page!</h3>
          <a class="btn btn-primary btn-lg" href="./adminAccess/adminIndex.php" role="button">Admin Page</a>
        </div>
      <?php endif; ?>
      <!-- STEP2: Display Records (Paste this where you want your records to be listed) -->
        <h1>Product Catalog</h1>
        <div class="text-center" style="display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(<?php echo sizeof($productRecords)/3 ?>, 1fr); grid-column-gap: 8px; grid-row-gap: 8px;">
        <?php foreach ($productRecords as $record): ?>
        <a href="<?php echo $record['_link'] ?>" style="text-decoration: none;">
          <div class="bg-dark text-white p-3 rounded">
            <!-- Image -->
            <?php echo $record['img'] ?><br>
            <!-- Title -->
            <h3><?php echo htmlencode($record['title']) ?></h3>
            <!-- Cost -->
            $<?php echo htmlencode($record['cost']) ?><br>
          </div>
        </a>
        <?php endforeach ?>
        </div>

        <?php if (!$productRecords): ?>
          No records were found!<br><br>
        <?php endif ?>
      <!-- /STEP2: Display Records -->
      <br>
  </body>
</html>

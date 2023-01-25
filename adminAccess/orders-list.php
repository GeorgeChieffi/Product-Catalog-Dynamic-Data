<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

  // load records from 'orders'
  list($ordersRecords, $ordersMetaData) = getRecords(array(
    'tableName'   => 'orders',
    'loadUploads' => true,
    'allowSearch' => false,
  ));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title></title>
  <style type="text/css">
    body          { font-family: arial; }
    .instructions { border: 3px solid #000; background-color: #EEE; padding: 10px; text-align: left; margin: 25px}
  </style>
</head>
<body>

  <!-- INSTRUCTIONS -->
    <div class="instructions">
      <b>Sample List Viewer - Instructions:</b>
      <ol>
        <?php /*><li style="color: red; font-weight: bold">Rename this file to have a .php extension!</li><x */ ?>
        <li><b>Remove any fields you don't want displayed.</b></li>
        <li>Rearrange remaining fields to suit your needs.</li>
        <li>Copy and paste code into previously designed page (or add design to this page).</li>
      </ol>
    </div>
  <!-- /INSTRUCTIONS -->

  <!-- STEP2: Display Records (Paste this where you want your records to be listed) -->
    <h1>orders - List Page Viewer</h1>
    <?php foreach ($ordersRecords as $record): ?>
      Record Number: <?php echo htmlencode($record['num']) ?><br>
      product (value): <?php echo $record['product'] ?><br>
      product (label): <?php echo $record['product:label'] ?><br>
      size (value): <?php echo $record['size'] ?><br>
      size (label): <?php echo $record['size:label'] ?><br>
      color (value): <?php echo $record['color'] ?><br>
      color (label): <?php echo $record['color:label'] ?><br>
      cost: <?php echo htmlencode($record['cost']) ?><br>
      quantity: <?php echo htmlencode($record['quantity']) ?><br>
      _link : <a href="<?php echo $record['_link'] ?>"><?php echo $record['_link'] ?></a><br>
      <hr>
    <?php endforeach ?>

    <?php if (!$ordersRecords): ?>
      No records were found!<br><br>
    <?php endif ?>
  <!-- /STEP2: Display Records -->

</body>
</html>

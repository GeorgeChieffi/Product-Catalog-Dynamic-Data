<?php header('Content-type: text/html; charset=utf-8'); ?>
<?php
  /* STEP 1: LOAD RECORDS - Copy this PHP code block near the TOP of your page */

  // load viewer library
  $libraryPath = 'cmsb/lib/viewer_functions.php';
  $dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
  foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
  if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

  // load record from 'product'
  list($productRecords, $productMetaData) = getRecords(array(
    'tableName'   => 'product',
    'where'       => whereRecordNumberInUrl(0),
    'loadUploads' => false,
    'allowSearch' => false,
    'limit'       => '1',
  ));
  $productRecord = @$productRecords[0]; // get first record
  if (!$productRecord) { dieWith404("Record not found!"); } // show error message if no record found

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
</head>
<body>
  <!-- /INSTRUCTIONS -->
  <!-- STEP2: Display Record (Paste this where you want your record to appear) -->
  <div class="container text-center p-3">
    <div class="row">
      <div class="col-5">
        <!-- Record Number: <?php echo htmlencode($productRecord['num']) ?><br> -->
        <?php echo $productRecord['img']; ?><br>
        <p class="text-start"><?php echo htmlencode($productRecord['description']) ?></p>
        <!-- i_type (value): <?php echo $productRecord['i_type'] ?><br> -->
        <!-- i_type (label): <?php echo $productRecord['i_type:label'] ?><br> -->
        <!-- sizes (values): <?php echo join(', ', $productRecord['sizes:values']); ?><br> -->
        <!-- sizes (labels): <?php echo join(', ', $productRecord['sizes:labels']); ?><br> -->
        <!-- colors (values): <?php echo join(', ', $productRecord['colors:values']); ?><br> -->
        <!-- colors (labels): <?php echo join(', ', $productRecord['colors:labels']); ?><br> -->
        <!-- cost: <?php echo htmlencode($productRecord['cost']) ?><br> -->
        <!-- _link : <a href="<?php echo $productRecord['_link'] ?>"><?php echo $productRecord['_link'] ?></a><br> -->
        <!-- /STEP2: Display Record -->
        <hr>
      </div>
      <div class="col align-items-center">
        <h1><?php echo htmlencode($productRecord['title']) ?></h1>
        <h3 class="text-secondary"><?php echo htmlencode($productRecord['cost']) ?></h3>


        <?php if(!$CURRENT_USER): ?>
          <h3 class="text-danger">Must be logged in to Buy Products!</h3>
          <a class="btn btn-primary btn-lg" href="../user-login.php" role="button">Login</a>
        <?php else: ?>
          <form action="process-order.php" method="post">
            <!-- Select Size -->
            <div class="row g-3 align-items-center">
              <div class="col-auto">
                <label for="size">Size:</label>
                <select name="size" id="size">
                    <option value="2">Extra Small</option>
                    <option value="3">Small</option>
                    <option value="4">Medium</option>
                    <option value="5">Large</option>
                    <option value="6">Extra Large</option>
                  </select>
                </div>
              </div>
              <!-- Select Color -->
              <div class="row g-3 align-items-center">
                <div class="col-auto">
                  <label for="color">Color:</label>
                  <select name="color" id="color">
                    <option value="1">White</option>
                    <option value="2">Black</option>
                    <option value="5">Red</option>
                    <option value="3">Blue</option>
                  </select>
                </div>
              </div>
              <!-- Select Quantity -->
              <div class="row g-3 align-items-center">
                <div class="col-auto">
                  <label for="quantity">Quantity:</label>
                </div>
                <div class="col-auto">
                  <input type="number" name="quantity" id="quantity" value="1">
                </div>
              </div>
              <!-- Invisible form transfer cost -->
              <input type="hidden" name="product_cost" value="<?php echo $productRecord['cost']; ?>">
              <!-- Invisible form transfer product data -->
              <input type="hidden" name="product_name" value="<?php echo $productRecord['num']; ?>">
              <!-- Invisible form transfering user info -->
              <input type="hidden" name="CURRENT_USER_NUM" value="<?php echo $CURRENT_USER['num']; ?>">
              <!-- Submit Form -->
              <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        <?php endif; ?>
      </div>
  </div>
  <a href="<?php echo $productMetaData['_listPage'] ?>">&lt;&lt; Back to list page</a>
  <a href="mailto:?subject=<?php echo urlencode(thisPageUrl()) ?>">Email this Page</a>

  <a href="../index.php">back to home page</a>
</body>
</html>

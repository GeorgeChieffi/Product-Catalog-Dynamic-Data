<?php

// load viewer library
$libraryPath = 'cmsb/lib/viewer_functions.php';
$dirsToCheck = ['','../','../../','../../../','../../../../']; // add if needed: '/home/www/gtc34.us.tempcloudsite.com/'
foreach ($dirsToCheck as $dir) { if (@include_once("$dir$libraryPath")) { break; }}
if (!function_exists('getRecords')) { die("Couldn't load viewer library, check filepath in sourcecode."); }

// Retrieve the size, color, and quantity from the form data
$size = $_POST['size'];
$color = $_POST['color'];
$quantity = $_POST['quantity'];
$cost = $_POST['product_cost'];
$productName = $_POST['product_name'];
$currUserNum = $_POST['CURRENT_USER_NUM'];

// Validate the form data to ensure it is valid
if (empty($size) || empty($color) || empty($quantity)) {
    // Return an error message if any of the form fields are empty
    echo "Error: All form fields are required. Please fill out the form and try again.";
    exit;
}

if (!is_numeric($quantity)) {
    // Return an error message if the quantity is not a valid number
    echo "Error: Invalid quantity. Please enter a valid number for the quantity.";
    exit;
}

mysql_insert('orders', array(
  'createdDate=' => 'NOW()',
  'updatedDate=' => 'NOW()',
  'createdByUserNum' => $currUserNum,
  'updatedByUserNum' => $currUserNum,
  'product' => $productName,
  'size' => $size,
  'color' => $color,
  'quantity' => $quantity,
  'cost' => $cost,
));

// Return a success message to the user
print("<h1>Your order was placed successfully. Thank you for your purchase!</h1>");
print('<h3><a href="index.php"> Click to return to the Catalog </a></h3> <br>');
print('<h3><a href="orders-list.php"> Click to view your orders </a></h3>');
?>

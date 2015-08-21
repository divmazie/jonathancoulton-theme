<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/19/15
 * Time: 16:52
 */

echo "<pre>";
$fetch = include_once(get_template_directory().'/config/fetch.php');
var_dump($fetch->getAccountDetails());
echo "<br /><br />";

$attachment = new \jct\WPAttachment(208);
//var_dump(file_exists($attachment->getPath()));

use FetchApp\API\Product;
use FetchApp\API\Currency;
use FetchApp\API\FileDetail;

try{
    // Let's grab our Product!
    $product = $fetch->getProduct(1234);
    $files = $product->getFiles();
} catch (Exception $e){
    // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
    echo $e->getMessage();
}
var_dump($files);
echo "<br /><br />";


try{
    $product = new Product();
    $product->setSKU(123);
    $product->setName("Test Product");
    $product->setPrice(3.00);
    $product->setCurrency(Currency::USD);

    $file = new FileDetail();
    $file->setURL($attachment->getURL());
    var_dump($file);
        //$attachment->getPath();
    $files = array($file);
    // Add files to the file array

    $response = $product->create($files);
}
catch (Exception $e){
    // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
    echo $e->getMessage();
}

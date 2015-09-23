<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/21/15
 * Time: 15:06
 */

$apiKey = get_field('shopify_api_key','options');
$apiPassword = get_field('shopify_api_password','options');
$handle = get_field('shopify_handle','options');
$shopify = new jct\Shopify($apiKey,$apiPassword,$handle);

echo "<pre>";
//print_r($shopify->getAllProducts());
//print_r( $shopify->getFetchProducts());//[0]->getFiles()[0]->getFileName());

$albums = \jct\Album::getAllAlbums();
foreach ($albums as $album) {
    print_r($album->syncToStore($shopify));
}
//$shopify->deleteUnusedProducts($albums);
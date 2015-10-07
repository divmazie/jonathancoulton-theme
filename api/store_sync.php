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

$context = Timber::get_context();
//print_r($shopify->getAllProducts());
//print_r( $shopify->getFetchProducts());//[0]->getFiles()[0]->getFileName());

$albums = \jct\Album::getAllAlbums();
$context['missing_files'] = array();
$context['album_responses'] = array();
foreach ($albums as $album) {
    $album->cleanAttachments();
    $response = $album->syncToStore($shopify);
    $context['album_responses'][] = $response;
    $context['missing_files'] = array_merge($context['missing_files'],array_values($response['missing_files']));
}
$shopify->deleteUnusedProducts($albums);
$shopify->deleteUnusedFetchProducts($albums);
$context['unused_files'] = $shopify->getUnusedFetchFiles();

Timber::render("store_sync.twig",$context);
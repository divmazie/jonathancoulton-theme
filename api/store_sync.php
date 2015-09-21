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
//print_r($shopify->getProducts());

$albums = \jct\Album::getAllAlbums();
foreach ($albums as $album) {
    $tracks = $album->getAlbumTracks();
    foreach ($tracks as $track) {
        $formats = array();
        foreach ($track->getAllChildEncodes() as $encode) {
            $formats[] = $encode->getEncodeFormat();
        }
        print_r($shopify->createProduct($track->getTrackTitle(),$track->getTrackArtObject()->getURL(),$track->getTrackPrice(),$formats));
    }
}
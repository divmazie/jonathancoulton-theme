<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/12/15
 * Time: 13:16
 */
$context = Timber::get_context();
$post_id = $_REQUEST['album_post_id'];
if ($post_id) {
    $post = get_post($post_id);
    $album = new \jct\Album($post);
    $context['albums'] = array($album->getAlbumContext());
    $context['post_id_get_string'] = "?album_post_id=$post_id";
    Timber::render("music_admin.twig",$context);
} else {
    $safe_to_shopify_sync = true;
    $albums_context = array();
    $albums = \jct\Album::getAllAlbums();
    foreach ($albums as $album) {
        if (!isset($albums_context[$album->getAlbumSortOrder()])) {
            $all_zips_present = true;
            foreach ($album->getAllChildZips() as $zip) {
                if (!$zip->fileAssetExists()) {
                    $all_zips_present = false;
                    $safe_to_shopify_sync = false;
                }
            }
            $album_context = array(
                'title' => $album->getAlbumTitle(),
                'post_id' => $album->getPostID(),
                'sort_order' => $album->getAlbumSortOrder(),
                'sort_order_conflict' => false,
                'zips_needed' => !$all_zips_present
            );
            $albums_context[$album->getAlbumSortOrder()] = $album_context;
        } else {
            $albums_context[$album->getAlbumSortOrder()]['sort_order_conflict'] = true;
            $albums_context[$album->getAlbumSortOrder()]['sort_order_conflict_album'] = $album->getTitle();
        }
    }
    krsort($albums_context);
    $context['albums'] = $albums_context;
    $context['safe_to_shopify_sync'] = $safe_to_shopify_sync;
    Timber::render("music_admin_album_select.twig", $context);
}
<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/12/15
 * Time: 13:16
 */
$context = Timber::get_context();
$album_context = array();
$albums = \jct\Album::getAllAlbums();
foreach ($albums as $album) {
    if (!isset($album_context[$album->getAlbumSortOrder()])) {
        $album_context[$album->getAlbumSortOrder()] = $album->getAlbumContext();
    } else {
        $album_context[$album->getAlbumSortOrder()]['sort_order_conflict'] = true;
        $album_context[$album->getAlbumSortOrder()]['sort_order_conflict_album'] = $album->getTitle();
    }
}
$context['albums'] = $album_context;
Timber::render("music_admin.twig",$context);
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
    $album_context[$album->getAlbumTitle()] = $album->getAlbumContext();
}
$context['albums'] = $album_context;
Timber::render("music_admin.twig",$context);
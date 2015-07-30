<?php

namespace jct;

/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/28/15
 * Time: 16:34
 */
header('Content-type: application/json');
$content = array("v" => $params['var']);
$album_posts = get_posts(array('post_type' => 'album'));
$albums = array();
foreach ($album_posts as $album_post) {
    $album = new Album($album_post);
    $album_encodes = $album->getNeededEncodes();
    if ($album_encodes) {
        $albums[] = $album_encodes;
    }
}
$content['albums'] = $albums;
echo json_encode($content);
?>

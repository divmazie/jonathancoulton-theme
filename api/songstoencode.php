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
$encodes = array();
foreach ($album_posts as $album_post) {
    $album = new Album($album_post);
    $album_encodes = $album->getNeededEncodes();
    if ($album_encodes) {
        $encodes = array_merge($encodes,$album_encodes);
    }
}
if (count($encodes)) {
    set_transient('encodes_needed',true);
}
$content['encodes'] = $encodes;
echo json_encode($content);
?>

<?php
namespace jct;
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/11/15
 * Time: 19:45
 */
echo "<pre>";
$album_posts = get_posts(array('post_type' => 'album'));
foreach ($album_posts as $album_post) {
    $album = new Album($album_post);
    $zips = $album->getAllChildZips();
    foreach ($zips as $zip) {
        echo $album->getAlbumTitle()." Format: ".$zip->getEncodeFormat()." Flags: ".$zip->getEncodeCLIFlags()."\n";
        echo $zip->createZip();
    }
}
echo "</pre>";
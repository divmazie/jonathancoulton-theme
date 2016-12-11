<?php

namespace jct;

use Timber\Timber;


$post_id = $_GET['id'];
$class = 'jct\\' . $_GET['class'];

echo "<pre>post id: $post_id
class: $class

";

$post = Timber::get_post($post_id, $class);

var_dump($post);

function new_section($header) {
    $header = mb_strtoupper($header);
    echo "
    ----------------------------\n------------------------------\n-----------------
    \n\n$header\n$header\n$header\n\n
    ----------------------------\n------------------------------\n-----------------\n\n";
}

switch($class) {

    case Album::class;
        /** @var $post Album */
        new_section('bunus asssets');
        var_dump($post->getAlbumBonusAssetObjects());

        new_section('tracks');
        var_dump($post->getAlbumTracks());

        new_section('art');
        var_dump($post->getAlbumArtObject());

        new_section('all child zips');
        var_dump($post->getAllChildZips());
        break;

    case Track::class;
        /** @var $post Track */
        new_section('track art');
        var_dump($post->getTrackArtObject());
die();
        new_section('track src');
        var_dump($post->getTrackSourceFileObject());

        break;


    case WPAttachment::class:
    case CoverArt::class:
    case BonusAsset::class:
        /** @var $post WPAttachment */
        var_dump([
                     'getPath'         => $post->getPath(),
                     'fileName'        => $post->getFilename(),
                     'getUrl'          => $post->getURL(),
                     'fileAssetExists' => $post->fileAssetExists(),
                 ]);
        break;
}

die();
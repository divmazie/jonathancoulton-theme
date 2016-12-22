<?php

namespace jct;

use Timber\Timber;


$post_id = $_GET['id'];
$class = 'jct\\' . $_GET['class'];
$prod = (bool)@$_GET['prod'];

echo "<pre>post id: $post_id
class: $class

";

$post = Timber::get_post($post_id, $class);


function new_section($header) {
    $header = mb_strtoupper($header);
    echo "
    ----------------------------\n------------------------------\n-----------------
    \n\n$header\n$header\n$header\n\n
    ----------------------------\n------------------------------\n-----------------\n\n";
}

if($prod){
    /** @var MusicStoreProductPost $post */

    echo <<<'EOT'
<form method=post>
<button type=submit name=submit value=submit>send</button>
</form>
EOT;

    if(@$_POST['submit']){
        Util::get_shopify_api_client()->putProduct($post->getShopifyProduct());
    }

    echo json_encode($post->getShopifyProduct()->putArray(), JSON_PRETTY_PRINT);
    die();
}

var_dump($post);


switch($class) {

    case Album::class;
        /** @var $post Album */
        new_section('bunus asssets');
        var_dump($post->getAlbumBonusAssetObjects());

        new_section('tracks');
        var_dump($post->getAlbumTracks());

        new_section('art');
        var_dump($post->getCoverArt());

        new_section('all child zips');
        var_dump($post->getAllChildZips());
        break;

    case Track::class;
        /** @var $post Track */
        new_section('track art');
        var_dump($post->getCoverArt());

        new_section('track src');
        var_dump($post->getTrackSourceFileObject());

        new_section('track configs');
        var_dump(EncodeConfig::getConfigsForTrack($post));

        new_section('track methods');
        var_dump($post->getPublicFilename('mp3'));

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
<?php

namespace jct;

/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/28/15
 * Time: 16:34
 */
set_transient('do_secret',randstr(40),60*60*24);

$content = ["v" => $params['var']];
$verbose = !!$_GET['verbose'];
$encodes = [];
$post_id = $_REQUEST['album_post_id'];
if ($post_id) {
    $post = get_post($post_id);
    $album = new \jct\Album($post);
    $albums = array($album);
} else {
    $albums = Album::getAllAlbums();
}
foreach($albums as $album) {
    $album_encodes = $album->getNeededEncodes();
    if($album_encodes) {
        $encodes = array_merge($encodes, $album_encodes);
    }
    if (count($encodes)>20) {
        break;
    }
}
if (count($encodes)==0) {
    $s3 = include(get_template_directory().'/config/s3.php');
    foreach ($albums as $album) {
        $zips = $album->getAllChildZips();
        foreach ($zips as $zip) {
            if (!wp_get_attachment_metadata($zip->getAttachmentID())) {
                $format = $zip->getEncodeFormat();
                $flags = $zip->getEncodeCLIFlags();
                $label = $zip->getEncodeLabel();
                $zip->createZip();
                $zip = $album->getChildZip($format,$flags,$label);
                $zip->uploadToAws($s3);
            }
        }
    }
    foreach ($albums as $album) {
        $tracks = $album->getAlbumTracks();
        foreach ($tracks as $track) {
            $encodes = $track->getAllChildEncodes();
            foreach ($encodes as $encode) {
                if ($encode->needToUpload()) $encode->uploadToAws($s3);
            }
        }
        $zips = $album->getAllChildZips();
        foreach ($zips as $zip) {
            if ($zip->needToUpload()) $zip->uploadToAws($s3);
        }
    }
} else {
    foreach ($encodes as $enc) {
        $enc->setEncodeTransient();
    }
    $content['encodes'] = array_map(function ($enc) {
        return $enc->getEncodeConfig();
    }, $encodes);

// adapted from http://www.lornajane.net/posts/2011/posting-json-data-with-php-curl
    $data_string = json_encode($content);

    $post_encodes_link = get_field('post_encodes_link', 'option');
    putenv('REMOTE_ENCODE_SECRET_URL=' . $post_encodes_link);
//echo getenv('REMOTE_ENCODE_SECRET_URL');
    $ch = curl_init(getenv('REMOTE_ENCODE_SECRET_URL'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURL_HTTP_VERSION_1_0, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
            'Expect:'
        ]
    );

    if ($verbose) {
        echo "<pre>" . htmlentities(curl_exec($ch));
    } else {
        curl_exec($ch);
    }
}
if (!$verbose) {
    $get_string = $post_id ? "?album_post_id=$post_id" : '';
    \header("Location: " . site_url() . "/custom_admin/music_admin/$get_string");
}

?>

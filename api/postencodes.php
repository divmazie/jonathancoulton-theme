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
$album_posts = get_posts(['post_type' => 'album']);
$encodes = [];
foreach($album_posts as $album_post) {
    $album = new Album($album_post);
    $album_encodes = $album->getNeededEncodes();
    if($album_encodes) {
        $encodes = array_merge($encodes, $album_encodes);
    }
}
foreach($encodes as $enc) {
    $enc->setEncodeTransient();
}
$content['encodes'] = array_map(function ($enc) {
    return $enc->getEncodeConfig();
}, $encodes);

// adapted from http://www.lornajane.net/posts/2011/posting-json-data-with-php-curl
$data_string = json_encode($content);

$post_encodes_link = get_field('post_encodes_link', 'option');
putenv('REMOTE_ENCODE_SECRET_URL='.$post_encodes_link);
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

if ($_GET['verbose']) {
    echo "<pre>" . htmlentities(curl_exec($ch));
} else {
    curl_exec($ch);
    \header("Location: ".site_url()."/music_admin");
}
?>

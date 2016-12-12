<?php

namespace jct;

use Timber\Timber;
use \GuzzleHttp\Client;

var_dump(EncodeConfig::getPending());
die();
$postArray = [];
$postArray['encodes'] = array_map(function (EncodeConfig $encodeConfig) {
    return $encodeConfig->toEncodeBotArray(EncodeConfig::getEncodeAuthCode());
}, EncodeConfig::getPending());

$client = new Client();

//var_dump($postArray);
echo json_encode($postArray, JSON_PRETTY_PRINT);

$r = $client->request('POST', Util::get_theme_option('post_encodes_link'), [
    'json' => $postArray,
]);

var_dump($r);

die();
<?php
namespace jct;

use \Routes;


Routes::map('custom_admin/:script', function ($params) {
    if(current_user_can('manage_options')) {
        Routes::load('custom_admin/' . $params['script'] . ".php");
    } else {
        Routes::load("404.php");
    }
});


Routes::map(EncodeConfig::RECEIVE_ENCODE_ROOT_REL_PATH . '/:auth_code/:encode_config_hash', function ($params) {
    $configHash = $params['encode_config_hash'];
    $authCode = $params['auth_code'];

    echo "<pre>";
    if(!EncodeConfig::isValidAuthCode($authCode)) {
        die('invalid code');
    }

    $allConfigs = EncodeConfig::getAll();

    echo "targeting $configHash\n";

    $targetConfig = isset($allConfigs[$configHash]) ? $allConfigs[$configHash] : null;
    if(!$targetConfig) {
        die("not found");
    }

    if(!(ctype_xdigit($md5 = @$_POST['md5']) && count($_FILES) === 1)) {
        die("nothing here");
    }

    if($targetConfig->getEncode() && $targetConfig->getEncode()->fileAssetExists()) {
        die("have it, thx");
    }

    $tempFilePath = @$_FILES['file']['tmp_name'];

    if(!($tempFilePath && file_exists($tempFilePath))) {
        die("no temp file");
    }

    if(!(md5_file($tempFilePath) === $md5)) {
        die("hash not matching, transport error");
    }

    $encode = $targetConfig->createEncodeFromTempFile($tempFilePath);
});


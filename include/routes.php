<?php
namespace jct;

use \Routes;

function authcode_valid($code) { // Check against transient with encoder validation
    if($code == get_transient('do_secret')) {
        return true;
    } else {
        return false;
    } // Change this to false when actually want to test!
}

Routes::map('api/:authcode/:script/:var', function ($params) {
    if(authcode_valid($params['authcode'])) {
        include get_template_directory() . "/api/" . $params['script'] . ".php";
        die();
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

Routes::map('custom_admin/:script', function ($params) {
    if(current_user_can('manage_options')) {
        Routes::load('custom_admin/' . $params['script'] . ".php");
    } else {
        Routes::load("404.php");
    }
});

Routes::map('wiki/:wikipage', function ($params) {
    $redirect_location = get_field('joco_wiki_base_url', 'options') . $params['wikipage'];
    header('Location: ' . $redirect_location, true, 301);
    die();
});
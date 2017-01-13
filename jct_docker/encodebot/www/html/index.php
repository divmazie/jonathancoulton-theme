<?php

if(!(strlen($ak = getenv('ENCODE_ACCESS_KEY')) > 10
     && isset($_GET['ak']) && $_GET['ak'] === $ak)
) {
    die('access not granted');
}

if(!($_SERVER['REQUEST_METHOD'] === 'POST' &&
     $_SERVER["CONTENT_TYPE"] === 'application/json' &&
     $json = json_decode(file_get_contents('php://input'), true))
) {
    die('no json');
}

if(!(isset($json['encodes']) &&
     is_array($encodeDirectives = $json['encodes']) &&
     count($encodeDirectives))
) {
    die('no encodes listed');
}

// from http://stackoverflow.com/questions/15273570/continue-processing-php-after-sending-http-response

ignore_user_abort(true);
set_time_limit(0);

ob_start();
// do initial processing here
print_r($json);
header('Connection: close');
header('Content-Length: '.ob_get_length());
ob_end_flush();
ob_flush();
flush();


define('CODE_DIR', __DIR__ . '/../src');
// get our objects
require_once(CODE_DIR . '/encode-chain.php');
require_once(CODE_DIR . '/encode-target.php');
$chains = [];

// now we have a json array or we are dead...
$jsonMandatoryFormat = include(CODE_DIR . '/json_mandatory_fields.php');
$mandatoryKeys = array_keys($jsonMandatoryFormat);
chdir('/var/www/dest');



foreach($encodeDirectives as $row) {
    /*
     * Check metadata format... first check each row has all the keys,
     * then check that they are the proper format
     */
    if(($presentKeys = array_keys(array_intersect_key($row, $jsonMandatoryFormat)))
       !== $mandatoryKeys
    ) {
        print_r($presentKeys);
        print_r($mandatoryKeys);
        die('json row lacks proper keys');
    } else {
        foreach($jsonMandatoryFormat as $key => $fmt) {
            $func = 'is_' . $fmt;
            if(!call_user_func($func, $row[$key])) {
                die('json row element incorrectly formatted');
            }
        }
    }

    $sourceMD5 = $row['source_md5'];
    if(!isset($chains[$sourceMD5])) {
        $chains[$sourceMD5] = $chain = new EncodeChain();
    }
    $chain->addConfig($row);
}


foreach($chains as $chain) {
    $chain->doChain();
}

print_r($json);
print_r($jsonMandatoryFormat);


?>




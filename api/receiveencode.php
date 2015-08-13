<?php
namespace jct;
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/31/15
 * Time: 17:16
 */
$transient_key = $params['var'];
$encode = Encode::recoverFromTransient($transient_key) or die("Couldn't recover encode information from transient key!");
if ($encode->getUniqueKey() == $transient_key) {
    echo $encode->saveEncodeFromUpload();
    //fastcgi_finish_request();
    echo $encode->getParentTrack()->getAlbum()->getChildZip($encode->getEncodeFormat(),$encode->getEncodeCLIFlags())->createZip();
} else {
    echo "Transient key does not match Encode!";
}

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
    $encode_result = $encode->saveEncodeFromUpload();
    echo $encode_result[1];
    if ($encode_result[0]) {
        $s3 = include(get_template_directory().'/config/s3.php');
        $s3_result = $encode->uploadToAws($s3);
        //fastcgi_finish_request();
        $zip = $encode->getParentTrack()->getAlbum()->getChildZip($encode->getEncodeFormat(), $encode->getEncodeCLIFlags(), $encode->getEncodeLabel());
        $zip_result = $zip->createZip();
        echo $zip_result[1];
        if ($zip_result[0]) {
            // Must reconstruct zip object for getPath() method to work correctly
            $zip = $encode->getParentTrack()->getAlbum()->getChildZip($encode->getEncodeFormat(), $encode->getEncodeCLIFlags(), $encode->getEncodeLabel());
            $s3_result = $zip->uploadToAws($s3);
        }
    }
} else {
    echo "Transient key does not match Encode!";
}

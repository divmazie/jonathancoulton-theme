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
        $aws_access_key_id = get_field('aws_access_key_id','options');
        $aws_secret_access_key = get_field('aws_secret_access_key','options');
        $credentials = new \Aws\Credentials\Credentials($aws_access_key_id, $aws_secret_access_key);
        $s3 = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => 'us-east-1',
            'credentials' => $credentials
        ]);
        $s3_result = $encode->uploadToAws($s3);
        //fastcgi_finish_request();
        $zip = $encode->getParentTrack()->getAlbum()->getChildZip($encode->getEncodeFormat(), $encode->getEncodeCLIFlags());
        $zip_result = $zip->createZip();
        echo $zip_result[1];
        if ($zip_result[0]) {
            $s3_result = $zip->uploadToAws($s3);
        }
    }
} else {
    echo "Transient key does not match Encode!";
}

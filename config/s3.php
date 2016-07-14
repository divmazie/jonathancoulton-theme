<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/14/16
 * Time: 16:40
 */

$aws_access_key_id = get_field('aws_access_key_id','options');
$aws_secret_access_key = get_field('aws_secret_access_key','options');
$credentials = new \Aws\Credentials\Credentials($aws_access_key_id, $aws_secret_access_key);
$s3 = new \Aws\S3\S3Client([
    'version'     => 'latest',
    'region'      => 'us-east-1',
    'credentials' => $credentials
]);

return $s3;
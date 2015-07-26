<?php

$metadataKeys = require('ffmpeg-metadata-fields.php');
$outputFormats = require('ffmpeg-output-formats.php');

$samplefile = [
    [
        'source_url'    => 'unimplemented',
        'encode_format' => 'alac',
        'ffmpeg_flags'  => '',

    ],


];

foreach($outputFormats as $of ){

}

shell_exec("ffmpeg -i /root/src/particleman.flac /root/src/particleman.wav")


?>
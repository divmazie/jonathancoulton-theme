<?php
return [
    'alac' => [
        'lib'          => 'alac',
        'defaultflags' => '',
    ],
    'aac' => [
        'lib'          => 'libfdk_aac',
        'defaultflags' => '-b:a 128k',
    ],
    'mp3' => [
        'lib'          => 'libmp3lame',
        'defaultflags' => '-q:a 1',
    ],
    'flac' => [
        'lib'          => 'flac',
        'defaultflags' => '-compression_level 8',
    ],
    'ogg' => [
        'lib'          => 'libvorbis',
        'defaultflags' => '-b:a 224',
    ],
    'opus' => [
        'lib'          => 'libopus',
        'defaultflags' => '-b:a 128',
    ],
];
?>
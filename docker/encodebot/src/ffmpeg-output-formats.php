<?php
return [
    'mp3'  => [
        'lib'      => 'libmp3lame',
        'flags'    => '-q:a 1',
        'file_ext' => 'mp3',
        'add_art'  => 'ffmpeg',
    ],
    'aac'  => [
        'lib'      => 'libfdk_aac',
        'flags'    => '-b:a 128k',
        'file_ext' => 'm4a',
        'add_art'  => 'mp4box',
    ],
    'flac' => [
        'lib'      => 'flac',
        'flags'    => '-compression_level 8',
        'file_ext' => 'flac',
        'add_art'  => 'metaflac',
    ],
    'alac' => [
        'lib'      => 'alac',
        'flags'    => '',
        'file_ext' => 'm4a',
        'add_art'  => 'mp4box',
    ],
    'opus' => [
        'lib'      => 'libopus',
        'flags'    => '-b:a 128',
        'file_ext' => 'opus',
        'add_art'  => 'not supported',
    ],
    'ogg'  => [
        'lib'      => 'libvorbis',
        'flags'    => '-b:a 224',
        'file_ext' => 'ogg',
        'add_art'  => 'ogg-cover-art',
    ],
];
?>
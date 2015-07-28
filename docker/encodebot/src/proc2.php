<?php

$metadataKeys = require('ffmpeg-metadata-fields.php');
$outputFormats = require('ffmpeg-output-formats.php');

class HashGrouping {
    public $wgetCmd = null;
    public $md5Cmd = null;
    public $wavCmd = null;
    public $encodeCmdArray = [];
}

function esa($a) {
    return escapeshellarg($a);
}


/**
 * General Idea:
 *
 * Go through the array. Group the files by the hash
 * of the source file they are to be encoded from. This
 * is the hash-grouping.
 *
 * Each hash-grouping will have every file designated
 * therein encoded serially by the shell after the source
 * file is successfully downloaded. The chain of commands
 * for each hash-grouping will be:
 *
 *   1. wget the file, capturing any error output (see errors)
 *   2. check the hash of the resultant file
 *   3. in a subshell: encode each of the files in a row
 *
 *
 *
 * download the source URLs to
 * a file named after their hash with the extension from
 * source file.
 */

$sampleConfig = [
    [
        // mandatories
        'source_url'    => 'http://dl.project-voodoo.org/killer-samples/killer-highs.flac',
        'source_md5'    => '587067eae68960e10a185a04b3f20dbd',
        'encode_format' => 'alac',
        'dest_url'      => 'not implemented',
        'art_url'       => 'https://jococruise.com/uploads/2015/03/unnamed-11-e1426704582517.jpg',
        'art_md5'       => 'dd98328e5b9734d27e86825ab8d7008b',
        'metadata'      => [
            'album'     => 'test',
            'artist'    => 'joco',
            'copyright' => 'jocoserious',
        ],

// optionals
        'ffmpeg_flags'  => '',

    ],


];

require_once('encode-chain.php');
require_once('encode-target.php');
foreach($outputFormats as $of) {


}

chdir('/root/encodes');

$chains = [];

foreach($sampleConfig as $row) {
    $sourceMD5 = $row['source_md5'];

    if(!isset($chains[$sourceMD5])) {
        $chains[$sourceMD5] = $chain = new EncodeChain();

    }
    $chain->addConfig($row);

    $chain->doChain();
    sleep(1);
    flush();

}

die('beep');
echo shell_exec("ffmpeg -y -i /root/src/particleman.flac /root/src/particleman.wav");
echo shell_exec("md5sum /root/src/particleman.flac > /root/src/particleman.md5");
echo shell_exec("md5sum particleman.flac > particleman.md5");
echo shell_exec("md5sum -c particleman.md5");
flush();

?>
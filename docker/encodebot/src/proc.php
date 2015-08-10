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
        $chains[$sourceMD5] = $chain = new HashGrouping();


        $sourceURL = $row['source_url'];
        $sourceExtension = array_pop(explode('.', explode('?', $sourceURL)[0]));
        $sourceLocalRawName = $sourceMD5 . '_source.' . $sourceExtension;
        $sourceLocalWavName = $sourceMD5 . '.wav';

        $wgetOutputFile = $sourceMD5 . '.wget';
        $errorFile = $sourceMD5 . '.errors';
        $md5file = $sourceMD5 . '.md5';
        // default to first format if illegal format given
        $destFormatDesc = isset($outputFormats[$row['encode_format']]) ?
            $outputFormats[$row['encode_format']] : $outputFormats[0];
        var_dump($destFormatDesc);
        if(isset($row['ffmpeg_flags']) &&
           preg_match('/[^a-z0-9\ :-]/i', $row['ffmpeg_flags'])
        ) {
            // only allow subset of chars to be in flags
            $destFormatDesc['flags'] = $row['ffmpeg_flags'];
        }
        $rowJSON = json_encode($row);
        $rowMD5 = md5($rowJSON);
        // start with sourceMD5 for sorting
        $destFileName = $sourceMD5 . $rowMD5 . '.' . $destFormatDesc['file_ext'];
        // for identification
        file_put_contents($sourceMD5 . $rowMD5 . '.json', $rowJSON);

        $destMetadata = $row['metadata'];
        // second arg of array combine is meaningless
        // just need only legal metadata keys
        $destMetadata = array_intersect_key(
            $destMetadata, array_combine($metadataKeys, $metadataKeys));
        $metadataFFMpegFormat = implode(' ', array_map(function ($mdKey, $mdVal) {
            return sprintf('-metadata %s=%s', $mdKey, escapeshellarg($mdVal));
        }, array_keys($destMetadata), $destMetadata));


        if(file_exists($errorFile) && count(file($errorFile)) > 15) {
            // give up... we have too many errors
            die('giving up');
        }

        file_put_contents($md5file, sprintf('%s  %s', $sourceMD5, $sourceLocalRawName));

        // no terminating operator on the commands!

        // only add an error if it actually exists
        // note that dumb wget always outputs to stderr
        // http://superuser.com/questions/420120/wget-is-silent-but-it-displays-error-messages
        $chain->wgetCmd =
            sprintf(
                '[ -s %s ] ' .
                '|| wget --no-verbose -O %s %s 1> /dev/null 2> %s ' .
                '|| cat %s >> %s;  rm %s',
                escapeshellarg($sourceLocalRawName),
                escapeshellarg($sourceLocalRawName),
                escapeshellarg($sourceURL),
                escapeshellarg($wgetOutputFile),
                escapeshellarg($wgetOutputFile),
                escapeshellarg($errorFile),
                escapeshellarg($wgetOutputFile)
            );

        // append any errors from this phase here
        $chain->md5Cmd =
            sprintf('md5sum -c %s 1> /dev/null 2>> %s',
                    escapeshellarg($md5file),
                    escapeshellarg($errorFile)
            );

        // this will strip tags from flac, give us a
        // definite filename!
        // if we redo for wav, so what
        $chain->wavCmd =
            sprintf('ffmpeg -y -i %s %s',
                    escapeshellarg($sourceLocalRawName),
                    escapeshellarg($sourceLocalWavName));
    }

    $chain->encodeCmdArray[] =
    $encmd = sprintf('ffmpeg -y -i %s %s -c:a %s %s %s',
                     escapeshellarg($sourceLocalWavName),
                     $metadataFFMpegFormat,
                     escapeshellarg($destFormatDesc['lib']),
                     $destFormatDesc['flags'],
                     escapeshellarg($destFileName)
    );
    switch($destFormatDesc['add_art']) {

        case 'ffmpeg':
            'ffmpeg -i out.mp3 -i test.png -map 0:0 -map 1:0 -c copy -id3v2_version 3 metadata:s:v title="Album cover" -metadata:s:v comment="Cover (Front)" out.mp3';

        case 'metaflac':
            sprintf('metaflac --import-picture-from=%s %s');
            break;

        case 'atomicparsley':
            sprintf('atomicparsley %s -artwork %s');
            break;

        default:
            break;
    }

    // we don't check wget's error status but it doesn't matter
    // if the checksum fails, then we don't have the file
    // we want anyway
    // next instead of echoing, put all the encode commands
    // in a subshell
    $cmd = sprintf('%s; %s && %s &&%s ',
                   $chain->wgetCmd,
                   $chain->md5Cmd,
                   $chain->wavCmd,
                   $encmd
    );
    var_dump($cmd);
    shell_exec($cmd);
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
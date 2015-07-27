<?php

$metadataKeys = require('ffmpeg-metadata-fields.php');
$outputFormats = require('ffmpeg-output-formats.php');

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


// optionals
        'ffmpeg_flags'  => '',

    ],


];


foreach($outputFormats as $of) {


}

chdir('/root/encodes');

foreach($sampleConfig as $row) {
    $sourceURL = $row['source_url'];
    $sourceMD5 = $row['source_md5'];
    $sourceExtension = array_pop(explode('.', explode('?', $sourceURL)[0]));
    $destFileName = $sourceMD5 . '.' . $sourceExtension;
    $wgetOutputFile = $sourceMD5 . '.wget';
    $errorFile = $sourceMD5 . '.errors';
    $md5file = $sourceMD5 . '.md5';

    if(file_exists($errorFile) && count(file($errorFile)) > 15) {
        // give up... we have too many errors
        die('giving up');
    }

    file_put_contents($md5file, sprintf('%s  %s', $sourceMD5, $destFileName));

    // no terminating operator on the commands!

    // only add an error if it actually exists
    // note that dumb wget always outputs to stderr
    // http://superuser.com/questions/420120/wget-is-silent-but-it-displays-error-messages
    $wgetCmd = sprintf("wget --no-verbose -O %s %s 1> /dev/null 2> %s || cat %s >> %s;  rm %s;",
                       escapeshellarg($destFileName),
                       escapeshellarg($sourceURL),
                       escapeshellarg($wgetOutputFile),
                       escapeshellarg($wgetOutputFile),
                       escapeshellarg($errorFile),
                       escapeshellarg($wgetOutputFile)
    );

    // append any errors from this phase here
    $md5Cmd = sprintf("md5sum -c %s 1> /dev/null 2>> %s",
                      escapeshellarg($md5file),
                      escapeshellarg($errorFile)
    );

    // we don't check wget's error status but it doesn't matter
    // if the checksum fails, then we don't have the file
    // we want anyway
    // next instead of echoing, put all the encode commands
    // in a subshell
    $cmd = sprintf('%s; %s && echo passed > file',
                   $wgetCmd,
                   $md5Cmd
    );
    echo $cmd;
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
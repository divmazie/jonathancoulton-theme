<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/5/15
 * Time: 12:11
 */

/*
 * KEY:    Unique human-readable label for encode option
 * VAL[0]: File format/extension
 * VAL[1]: Flags for MPEG compression options
 */
return [
    'MP3'  => ['mp3', '-q:a 2'],
    // DAVID -- please check these file sizes to make sure they are smaller than mp3... perhaps adjust the global_quality down to 4
    'MP4'  => ['aac', '-vbr 5 -afterburner 1 -cutoff 20000'],
    'FLAC' => ['flac', '-compression_level 8'],
    'ALAC' => ['alac', ''],
];

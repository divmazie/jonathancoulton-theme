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
return array(
    'MP3' => array('mp3', '-q:a 1'),
    // DAVID -- please check these file sizes to make sure they are smaller than mp3... perhaps adjust the global_quality down to 4
    'OGG' => array('aac', '-vbr 5 -afterburner 1 -cutoff 20000')
    'FLAC' => array('flac', '-compression_level 8'),
    'ALAC' => array('alac', ''),
);

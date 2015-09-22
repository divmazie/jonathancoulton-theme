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
    'FLAC' => array('flac', '-compression_level 8'),
    'OGG' => array('ogg', '')
);

<?php

namespace jct;

use GuzzleHttp\Client;

class EncodeConfig extends EncodedAssetConfig {
    const RECEIVE_ENCODE_ROOT_REL_PATH = 'receive_encode';
    const ENCODE_AUTH_CODE_TRANSIENT_NAME = 'jct_encode_secret';
    // 1 day duration for transient (60*60*24)
    const ENCODE_AUTH_CODE_TRANSIENT_DURATION_SECONDS = 86400;
    const PARENT_POST_CLASS = Track::class;

    public function __construct(Track $parentTrack, $encodeFormat, $ffmpegFlags, $configName) {
        parent::__construct($parentTrack, $encodeFormat, $ffmpegFlags, $configName);
    }

    /**
     * @return Track
     */
    public function getParentTrack() {
        return $this->getParentPost();
    }

    public function getUniqueKey() {
        return md5(serialize($this->toEncodeBotArray()));
    }

    public function getFileExtension() {
        return static::getFileExtensionForEncodeFormat($this->getEncodeFormat());
    }


    public function getConfigUniqueFilename() {
        // track number underscore track title underscore format underscore short hash dot extension
        return sprintf("%'.02d_%s_%s_%s.%s",
                       $this->getParentTrack()->getTrackNumber(),
                       $this->getParentTrack()->getFilenameFriendlyTitle(),
                       $this->getEncodeFormat(),
                       $this->getShortUniqueKey(),
                       $this->getFileExtension());
    }

    public function getUploadRelativeStorageDirectory() {
        return sprintf('%s/%s',
                       $this->getParentTrack()->getAlbum()->getFilenameFriendlyTitle(),
                       $this->getEncodeFormat());
    }

    /**
     * @param null $remoteAuthCode
     * @return array
     */
    public function toEncodeBotArray($remoteAuthCode = null) {
        $forUseInUniqueKey = is_null($remoteAuthCode);

        // what is $forUseInUniqueKey:
        // the encode config is a great determinant of whether a file is unique
        // this function both uses a file's unique key and is used to generate it
        // so this flag let's us skip the pieces that would cause infinite recursion
        $parent = $this->getParentTrack();
        $config = [
            'source_url'    =>
                $forUseInUniqueKey ?
                    // post id will not change over site url changes
                    '' :
                    ($parent->getTrackSourceFileObject() ? $parent->getTrackSourceFileObject()->getURL() : 'shit...'),
            'source_md5'    => $parent->getTrackSourceFileObject() ? $parent->getTrackSourceFileObject()->getCanonicalContentHash() : 'shit...',
            'encode_format' => $this->getEncodeFormat(),
            'dest_url'      =>
                $forUseInUniqueKey ?
                    '' :
                    (Util::get_site_url() . '/' . self::RECEIVE_ENCODE_ROOT_REL_PATH . '/' .
                     $remoteAuthCode . '/' . $this->getUniqueKey()),
            'art_url'       =>
                $forUseInUniqueKey ?
                    '' :
                    ($parent->getCoverArt() ? $parent->getCoverArt()->getURL() : 'MISSING!!!'),
            'art_md5'       => $parent->getCoverArt() ? $parent->getCoverArt()->getCanonicalContentHash() : 'MISSING!!!',
            'metadata'      => [
                'title'        => $parent->getTitle(),
                'track'        => $parent->getTrackNumber(),
                'album'        => $parent->getAlbum()->getTitle(),
                'album_artist' => $parent->getAlbum()->getAlbumArtist(),
                'artist'       => $parent->getTrackArtist(),
                'comment'      => $parent->getTrackComment(),
                'genre'        => $parent->getTrackGenre(),
                'date'         => $parent->getTrackYear(),
                'filename'     => $forUseInUniqueKey ? '' : $this->getConfigUniqueFilename(),
                'encoded_by'   => 'jonathancoulton.com',
            ],
        ];
        if($this->getFfmpegFlags()) {
            $config['ffmpeg_flags'] = $this->getFfmpegFlags();
        }
        return $config;
    }


    /**
     * @return Encode|null
     */
    public function getEncode() {
        return Encode::findByUniqueKey($this->getUniqueKey());
    }

    public function getAsset() {
        return $this->getEncode();
    }

    /** @return Encode */
    public function createEncodeFromTempFile($tempFile) {
        return Encode::createFromTempFile($tempFile, $this);
    }

    public function getProductVariantPrice() {
        return $this->getParentTrack()->getPrice();
    }

    /** @return EncodeConfig[] keyed by unique key */
    public static function getAll() {
        $allEncodeConfigs =
            // get a flat array of all the EncodeConfigs
            Util::array_merge_flatten_1L(array_map(function (Track $track) {
                return $track->getTrackEncodeConfigs();
            },
                // get a flat array of all the album tracks
                Track::getAll()));


        return array_combine(array_map(function (EncodeConfig $encodeConfig) {
            return $encodeConfig->getUniqueKey();
        }, $allEncodeConfigs), $allEncodeConfigs);
    }

    public static function getPending() {
        $allEncodeConfigs = self::getAll();
        $allEncodes = Encode::getAll();

        /** @var EncodeConfig[] $pendingEncodeConfigs */
        $pendingEncodeConfigs = array_diff_key($allEncodeConfigs, $allEncodes);

        foreach($pendingEncodeConfigs as $config) {
            // prepop null here
            Encode::findByUniqueKey($config->getUniqueKey(), null, true);
        }

        return $pendingEncodeConfigs;
    }


    /**
     * @return EncodeConfig[]
     */
    public static function getConfigsForTrack(Track $track, $keyByName = false) {
        return static::getConfigsForPost($track, $keyByName);
    }

    /**
     * @param Track $track
     * @param $configName
     * @return EncodeConfig
     */
    public static function getConfigForTrackByName(Track $track, $configName) {
        return static::getConfigForPostByConfigName($track, $configName);
    }

    public static function getFileExtensionForEncodeFormat($encodeFormat) {
        static $outputFormats = null;
        if(!$outputFormats) {
            $outputFormats = include(dirname(__DIR__) . '/config/encode_output_formats.php');
        }
        return $outputFormats[$encodeFormat]['file_ext'];
    }


    public static function getEncodeAuthCode() {
        /** @noinspection PhpUndefinedFunctionInspection */
        if(!$trans = \get_transient(self::ENCODE_AUTH_CODE_TRANSIENT_NAME)) {
            /** @noinspection PhpUndefinedFunctionInspection */
            \set_transient(self::ENCODE_AUTH_CODE_TRANSIENT_NAME, $trans =
                Util::rand_str(40), self::ENCODE_AUTH_CODE_TRANSIENT_DURATION_SECONDS);
        }

        return $trans;
    }

    public static function isValidAuthCode($authCode) {
        return self::getEncodeAuthCode() == $authCode;
    }

}
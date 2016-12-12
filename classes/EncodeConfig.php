<?php

namespace jct;

class EncodeConfig extends EncodedAssetConfig {


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
        // track number underscore track title underscore short hash dot extension
        return sprintf("%'.02d_%s_%s.%s",
                       $this->getParentTrack()->getTrackNumber(),
                       $this->getParentTrack()->getPublicFilename(),
                       $this->getShortUniqueKey(),
                       $this->getFileExtension());
    }

    public function getUploadRelativeStorageDirectory() {
        return sprintf('%s/%s', static::BASE_UPLOADS_FOLDER,
                       $this->getParentTrack()->getAlbum()->getPublicFilename());
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
                    (Util::get_site_url() . "/api/$remoteAuthCode/receiveencode/" . $this->getUniqueKey()),
            'art_url'       =>
                $forUseInUniqueKey ?
                    '' :
                    ($parent->getTrackArtObject() ? $parent->getTrackArtObject()->getURL() : 'MISSING!!!'),
            'art_md5'       => $parent->getTrackArtObject() ? $parent->getTrackArtObject()->getCanonicalContentHash() : 'MISSING!!!',
            'metadata'      => [
                'title'        => $parent->getTrackTitle(),
                'track'        => $parent->getTrackNumber(),
                'album'        => $parent->getAlbum()->getAlbumTitle(),
                'album_artist' => $parent->getAlbum()->getAlbumArtist(),
                'artist'       => $parent->getTrackArtist(),
                'comment'      => $parent->getTrackComment(),
                'genre'        => $parent->getTrackGenre(),
                'filename'     => $forUseInUniqueKey ? '' : $this->getConfigSpecificFileName(),
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

    public function createEncodeFromTempFile($tempFile) {
        return Encode::createFromTempFile(
            $tempFile, $this->getUniqueKey(),
            sprintf('%s/%s', static::BASE_UPLOADS_FOLDER,
                    $this->getParentTrack()->getAlbum()->getPublicFilename()),
            $this->getConfigUniqueFilename(),
            $this->getParentTrack()
        );
    }

    public function getEncodeStatusContext() {
        return [
            'format'         => $this->getEncodeFormat(),
            'flags'          => $this->getFfmpegFlags(),
            'exists'         => $this->getEncode() && $this->getEncode()->fileAssetExists(),
            'need_to_upload' => $this->getEncode() && $this->getEncode()->needToUpload(),
        ];
    }

    /** @return EncodeConfig[] keyed by unique key */
    public static function getAllEncodeConfigs() {
        $allEncodeConfigs =
            // get a flat array of all the EncodeConfigs
            Util::array_merge_flatten_1L(array_map(function (Track $track) {
                return $track->getTrackEncodeConfigs();
            },
                // get a flat array of all the album tracks
                $allTracks = Util::array_merge_flatten_1L(array_map(function (Album $album) {
                    return $album->getAlbumTracks();
                }, Album::getAllAlbums()))));


        return array_combine(array_map(function (EncodeConfig $encodeConfig) {
            return $encodeConfig->getUniqueKey();
        }, $allEncodeConfigs), $allEncodeConfigs);
    }


    /**
     * @param Track $track
     * @return EncodeConfig[]
     */
    public static function getConfigsForTrack(Track $track, $keyByName = false) {
        $encTypes = Util::get_encode_types();
        $configs = [];

        foreach($encTypes as $configName => $configArgs) {
            $configs[$configName] = new self($track, $configArgs[0], $configArgs[1], $configName);
        }
        return $keyByName ? $configs : array_values($configs);
    }

    /**
     * @param Track $track
     * @param $configName
     * @return EncodeConfig
     */
    public static function getConfigForTrackByName(Track $track, $configName) {
        return self::getConfigsForTrack($track, true)[$configName];
    }

    public static function getFileExtensionForEncodeFormat($encodeFormat) {
        static $outputFormats = null;
        if(!$outputFormats) {
            $outputFormats = include(dirname(__DIR__) . '/config/encode_output_formats.php');
        }
        return $outputFormats[$encodeFormat]['file_ext'];
    }


}
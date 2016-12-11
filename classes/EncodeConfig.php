<?php

namespace jct;

class EncodeConfig {

    const STORAGE_KEY_TRACK_ID = 'parentTrackID';
    const STORAGE_KEY_ENCODE_FORMAT = 'encodeFormat';
    const STORAGE_KEY_FFMPEG_FLAGS = 'ffmpegFlags';
    const STORAGE_KEY_CONFIG_NAME = 'configName';

    private $parentTrack, $encodeFormat, $ffmpegFlags, $configName;

    public function __construct(Track $parentTrack, $encodeFormat, $ffmpegFlags, $configName) {
        $this->parentTrack = $parentTrack;
        $this->encodeFormat = $encodeFormat;
        $this->ffmpegFlags = $ffmpegFlags;
        $this->configName = $configName;
    }

    /**
     * @return Track
     */
    public function getParentTrack() {
        return $this->parentTrack;
    }

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getFfmpegFlags() {
        return $this->ffmpegFlags;
    }

    public function getConfigName() {
        return $this->configName;
    }

    public function getUniqueKey() {
        return md5(serialize($this->toEncodeBotArray()));
    }

    public function getConfigSpecificFileName() {
        $title = $this->parentTrack->getTrackTitle();
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // track number underscore track title underscore short hash dot extension
        return sprintf("%'.02d_%s_%s.%s", $this->parentTrack->getTrackNumber(),
                       $title, substr($this->getUniqueKey(), 0, 7),
                       $this->encodeFormat == 'aac' || $this->encodeFormat == 'alac' ? 'm4a' : $this->encodeFormat);
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
        $parent = $this->parentTrack;
        $config = [
            'source_url'    =>
                $forUseInUniqueKey ?
                    // post id will not change over site url changes
                    '' :
                    ($parent->getTrackSourceFileObject() ? $parent->getTrackSourceFileObject()->getURL() : 'shit...'),
            'source_md5'    => $parent->getTrackSourceFileObject() ? md5_file($parent->getTrackSourceFileObject()->getPath()) : 'shit...',
            'encode_format' => $this->getEncodeFormat(),
            'dest_url'      =>
                $forUseInUniqueKey ?
                    '' :
                    (Util::get_site_url() . "/api/$remoteAuthCode/receiveencode/" . $this->getUniqueKey()),
            'art_url'       =>
                $forUseInUniqueKey ?
                    '' :
                    ($parent->getTrackArtObject() ? $parent->getTrackArtObject()->getURL() : 'MISSING!!!'),
            'art_md5'       => $parent->getTrackArtObject() ? md5_file($parent->getTrackArtObject()->getPath()) : 'MISSING!!!',
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

    public function encodeExists() {
        return (bool)$this->getEncode();
    }

    public function getEncodeStatusContext() {
        return [
            'format'         => $this->getEncodeFormat(),
            'flags'          => $this->getFfmpegFlags(),
            'exists'         => $this->encodeExists() && $this->getEncode()->fileAssetExists(),
            'need_to_upload' => $this->encodeExists() && $this->getEncode()->needToUpload(),
        ];
    }


    public function toPersistableArray() {
        return [
            self::STORAGE_KEY_TRACK_ID      => $this->parentTrack->getPostID(),
            self::STORAGE_KEY_FFMPEG_FLAGS  => $this->getFfmpegFlags(),
            self::STORAGE_KEY_ENCODE_FORMAT => $this->getEncodeFormat(),
            self::STORAGE_KEY_CONFIG_NAME   => $this->getConfigName(),
        ];
    }

    /**
     * @param Track $track
     * @return EncodeConfig[]
     */
    public static function getConfigsForTrack(Track $track) {
        $encTypes = Util::get_encode_types();
        $configs = [];

        foreach($encTypes as $configName => $configArgs) {
            $configs[$configName] = new self($track, $configArgs[0], $configArgs[1], $configName);
        }
        return $configs;
    }

    /**
     * @param Track $track
     * @param $configName
     * @return EncodeConfig
     */
    public static function getConfigForTrackByName(Track $track, $configName) {
        return self::getConfigsForTrack($track)[$configName];
    }

    /**
     * @param array $array
     * @return EncodeConfig
     */
    public static function fromPersistableArray(array $array) {
        return new EncodeConfig(
            Track::getTrackByID($array[self::STORAGE_KEY_TRACK_ID]),
            $array[self::STORAGE_KEY_ENCODE_FORMAT],
            $array[self::STORAGE_KEY_FFMPEG_FLAGS],
            $array[self::STORAGE_KEY_CONFIG_NAME]
        );
    }

}
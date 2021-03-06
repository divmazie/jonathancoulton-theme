<?php
namespace jct;

abstract class EncodedAssetConfig {
    const STORAGE_KEY_PARENT_ID = 'parentID';
    const STORAGE_KEY_ENCODE_FORMAT = 'encodeFormat';
    const STORAGE_KEY_FFMPEG_FLAGS = 'ffmpegFlags';
    const STORAGE_KEY_CONFIG_NAME = 'configName';
    const STORAGE_KEY_RECONSTITUTE_AS = 'targetClass';
    const PARENT_POST_CLASS = JCTPost::class;

    const BASE_UPLOADS_FOLDER = 'encodes';

    private $parentPost, $encodeFormat, $ffmpegFlags, $configName;

    public function __construct(MusicStoreProductPost $parentPost, $encodeFormat, $ffmpegFlags, $configName) {
        $this->parentPost = $parentPost;
        $this->encodeFormat = $encodeFormat;
        $this->ffmpegFlags = $ffmpegFlags;
        $this->configName = $configName;
    }

    /**
     * @return MusicStoreProductPost
     */
    public function getParentPost() {
        return $this->parentPost;
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

    abstract public function getUniqueKey();

    public function getShortUniqueKey() {
        return substr($this->getUniqueKey(), 0, 7);
    }

    abstract public function getConfigUniqueFilename();

    abstract public function getUploadRelativeStorageDirectory();

    abstract public function getFileExtension();


    /** @return EncodedAsset */
    abstract public function getAsset();

    public function assetExists() {
        return $this->getAsset() && $this->getAsset()->fileAssetExists();
    }


    public static function getConfigsForPost(MusicStoreProductPost $JCTPost, $keyByName = false) {
        $encTypes = Util::get_encode_types();
        $configs = [];

        foreach($encTypes as $configName => $configArgs) {
            $configs[$configName] = new static($JCTPost, $configArgs[0], $configArgs[1], $configName);
        }
        return $keyByName ? $configs : array_values($configs);
    }

    /**
     * @return static
     */
    public static function getConfigForPostByConfigName(MusicStoreProductPost $JCTPost, $configName) {
        return static::getConfigsForPost($JCTPost, true)[$configName];
    }

    public function toPersistableArray() {
        return [
            static::STORAGE_KEY_PARENT_ID       => $this->getParentPost()->getPostID(),
            static::STORAGE_KEY_FFMPEG_FLAGS    => $this->getFfmpegFlags(),
            static::STORAGE_KEY_ENCODE_FORMAT   => $this->getEncodeFormat(),
            static::STORAGE_KEY_CONFIG_NAME     => $this->getConfigName(),
            static::STORAGE_KEY_RECONSTITUTE_AS => static::class,
        ];
    }

    /**
     * @param array $array
     * @return static
     */
    public static function fromPersistableArray(array $array) {
        /** @noinspection PhpParamsInspection
         * The parent post class should be correct here, crazily
         */
        return new static(
            Util::get_posts_cached($array[static::STORAGE_KEY_PARENT_ID], static::PARENT_POST_CLASS),
            $array[static::STORAGE_KEY_ENCODE_FORMAT],
            $array[static::STORAGE_KEY_FFMPEG_FLAGS],
            $array[static::STORAGE_KEY_CONFIG_NAME]
        );
    }

}


?>
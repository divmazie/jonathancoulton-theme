<?php
namespace jct;

class AlbumZipConfig extends EncodedAssetConfig {

    const PARENT_POST_CLASS = Album::class;

    public function __construct(Album $parentAlbum, $encodeFormat, $ffmpegFlags, $configName) {
        parent::__construct($parentAlbum, $encodeFormat, $ffmpegFlags, $configName);
    }

    /**
     * @return Album
     */
    public function getParentAlbum() {
        return $this->getParentPost();
    }

    /**
     * @return EncodeConfig[]
     */
    public function getParticipatingEncodeConfigs() {
        return array_map(function (Track $track) {
            return $track->getEncodeConfigByName($this->getConfigName());
        }, $this->getParentAlbum()->getAlbumTracks());
    }

    public function getUniqueKey() {
        $album_info = [$this->getParentAlbum()->getTitle()];
        foreach($this->getParentAlbum()->getAlbumBonusAssetObjects() as $bonus_asset) {
            $album_info[] = $bonus_asset->getCanonicalContentHash();
        }
        foreach($this->getParticipatingEncodeConfigs() as $encodeConfig) {
            $album_info[] = $encodeConfig->getUniqueKey();
        }
        return md5(serialize($album_info));
    }

    public function getConfigUniqueFilename() {
        // album title underscore format underscore short hash dot extension
        return sprintf('%s_%s_%s.%s',
                       $this->getParentAlbum()->getFilenameFriendlyTitle(),
                       $this->getEncodeFormat(),
                       $this->getShortUniqueKey(),
                       $this->getFileExtension());
    }

    public function getFileExtension() {
        return 'zip';
    }

    public function getAlbumZip() {
        return AlbumZip::findByUniqueKey($this->getUniqueKey());
    }

    public function getAsset() {
        return $this->getAlbumZip();
    }

    public function getPendingEncodes() {
        return array_filter($this->getParticipatingEncodeConfigs(), function (EncodeConfig $encodeConfig) {
            return !$encodeConfig->assetExists();
        });
    }

    public function hasPendingEncodes() {
        return (bool)$this->getPendingEncodes();
    }

    public function isZipWorthy() {
        return $this->getParentAlbum()->isFilledOut() && !$this->hasPendingEncodes();
    }

    public function getUploadRelativeStorageDirectory() {
        return self::BASE_UPLOADS_FOLDER . '/' . $this->getParentAlbum()->getFilenameFriendlyTitle();
    }


    public function createZip() {
        if(!$this->isZipWorthy()) {
            throw new JCTException("Album is not zip-worthy!");
        }

        if($this->assetExists()) {
            throw new JCTException("Album is already zipped!");
        }

        $zipArchive = new \ZipArchive();
        $zipFileName = tempnam(sys_get_temp_dir(), $this->getConfigUniqueFilename());
        if($zipArchive->open($zipFileName, \ZipArchive::CREATE) !== true) {
            throw new JCTException("Cannot open zip file: <$zipFileName>!");
        }
        $zipInnerDirectoryPath = $this->getParentAlbum()->getTitle();

        // populate targetFiles with $filePath=>$zipPath
        $targetFiles = [];
        foreach($this->getParticipatingEncodeConfigs() as $encodeConfig) {
            $targetFiles[$encodeConfig->getEncode()->getPath()] =
                $zipInnerDirectoryPath . '/' . $encodeConfig->getParentTrack()->getPublicFilename(
                    $encodeConfig->getFileExtension());
        }
        foreach($this->getParentAlbum()->getAlbumBonusAssetObjects() as $bonus_asset) {
            $targetFiles[$bonus_asset->getPath()] =
                $zipInnerDirectoryPath . '/' . basename($bonus_asset->getPath());
        }

        foreach($targetFiles as $fsPath => $zipPath) {
            if(!file_exists($fsPath)) {
                throw new JCTException("nope! you're desirous of zipping a non existent fsPath [$fsPath]");
            }

            if(!$zipArchive->addFile($fsPath, $zipPath)) {
                throw new JCTException("adding [$fsPath] to zip [$zipFileName] failed!");
            }
        }

        // this is available in PHP7 and will increase speed
        if(method_exists($zipArchive, 'setCompressionIndex')) {
            for($i = 0; $i < count($targetFiles); $i++) {
                $zipArchive->setCompressionIndex($i, \ZipArchive::CM_STORE);
            }
        }

        $zipArchive->close();

        AlbumZip::createFromTempFile($zipFileName, $this);
    }

    /**
     * @return AlbumZipConfig[]
     */
    public static function getConfigsForAlbum(Album $album, $keyByName = false) {
        /** @var AlbumZipConfig[] $allZipConfigs */
        return static::getConfigsForPost($album, $keyByName);
    }

    /**
     * @return AlbumZipConfig
     */
    public static function getConfigsForAlbumByName(Album $album, $configName) {
        return static::getConfigForPostByConfigName($album, $configName);
    }

    public static function getAll() {
        /** @var AlbumZipConfig[] $allZipConfigs */
        $allZipConfigs = Util::array_merge_flatten_1L(array_map(function (Album $album) {
            return $album->getAlbumZipConfigs();
        }, Album::getAll()));

        return array_combine(array_map(function (AlbumZipConfig $zipConfig) {
            return $zipConfig->getUniqueKey();
        }, $allZipConfigs), $allZipConfigs);
    }

    /**
     * @return AlbumZipConfig[]
     */
    public static function getPending() {
        $allZipConfigs = self::getAll();
        $allZips = AlbumZip::getAll();

        /** @var AlbumZipConfig[] $pendingZipConfigs */
        $pendingZipConfigs = array_diff_key($allZipConfigs, $allZips);
        foreach($pendingZipConfigs as $config) {
            // prepop null here
            AlbumZip::findByUniqueKey($config->getUniqueKey(), null, true);
        }

        return $pendingZipConfigs;
    }

}


?>
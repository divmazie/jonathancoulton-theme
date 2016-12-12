<?php
namespace jct;

class AlbumZipConfig extends EncodedAssetConfig {

    public function __construct(Album $parentAlbum, $encodeFormat, $ffmpegFlags, $configName) {
        parent::__construct($parentAlbum, $encodeFormat, $ffmpegFlags, $configName);
    }

    /**
     * @return Album
     */
    public function getParentAlbum() {
        return $this->getParentPost();
    }

    public function getUniqueKey() {
        $album_info = [$this->getParentAlbum()->getAlbumTitle()];
        foreach($this->getParentAlbum()->getAlbumBonusAssetObjects() as $bonus_asset) {
            $album_info[] = md5_file($bonus_asset->getPath());
        }
        foreach($this->getEncodeConfigs() as $encodeConfig) {
            $album_info[] = $encodeConfig->getUniqueKey();
        }
        return md5(serialize($album_info));
    }

    public function getEncodeConfigs() {
        return $this->getParentAlbum()->getAlbumEncodeConfigs($this->getEncodeFormat());
    }

    public function getFilename() {
        // album title underscore format underscore short hash dot extension
        return sprintf('%s_%s_%s.%s',
                       $this->getParentAlbum()->getPublicFilename(),
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
        $waiting = [];
        foreach($this->getEncodeConfigs() as $encodeConfig) {
            if(!$encodeConfig->assetExists()) {
                $waiting[] = $encodeConfig;
            }
        }
        return false;
    }

    public function hasPendingEncodes() {
        return (bool)$this->getPendingEncodes();
    }

    public function isZipWorthy() {
        return $this->getParentAlbum()->isEncodeWorthy() && !$this->hasPendingEncodes();
    }

    public function createZip() {
        if(!$this->isZipWorthy()) {
            throw new JCTException("Album is not zip-worthy!");
        }

        if($this->assetExists()) {
            throw new JCTException("Album is already zipped!");
        }

        $zipArchive = new \ZipArchive();
        $zipFileName = tempnam(sys_get_temp_dir(), $this->getFilename());
        if($zipArchive->open($zipFileName, \ZipArchive::CREATE) !== true) {
            throw new JCTException("Cannot open zip file: <$zipFileName>!");
        }
        $zipInnerDirectoryPath = $this->getParentAlbum()->getAlbumTitle();

        // populate targetFiles with $filePath=>$zipPath
        $targetFiles = [];
        foreach($this->getEncodeConfigs() as $encodeConfig) {
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

        return $zipFileName;
    }

    public function getZipStatusContext() {
        return [
            'format'          => $this->getEncodeFormat(),
            'flags'           => $this->getFfmpegFlags(),
            'zip_worthy'      => $this->isZipWorthy(),
            'missing_encodes' => $this->hasPendingEncodes(),
            'exists'          => $this->assetExists(),
            'need_to_upload'  => $this->getAlbumZip()->needToUpload(),
            'path'            => $this->getAlbumZip()->getPath(),
        ];
    }

}


?>
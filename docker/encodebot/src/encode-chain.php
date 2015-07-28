<?php

class EncodeChain {

    protected $configs = [];
    protected $sourceMD5 = null;
    protected $sourceURL = null;
    protected $sourceExtension = null;
    protected $sourceLocalRawName = null;
    protected $sourceLocalWavName = null;
    protected $errorFile = null;
    protected $albumArtURL = null;
    protected $albumArtMD5 = null;
    protected $albumArtDestFile = null;
    protected $errorThreshold = null;

    protected $children = [];

    public function __construct($errorThreshold = 15) {
        $this->errorThreshold = $errorThreshold;
    }

    public function addConfig($config) {
        if($this->sourceMD5 && $this->sourceMD5 !== $config['source_md5']) {
            throw new Exception('attempt to config with different source');
        } else if(!$this->sourceMD5) {

            $extFromURL = function ($url) {
                return array_pop(explode('.', explode('?', $url)[0]));
            };

            $this->sourceMD5 = $config['source_md5'];
            $this->sourceURL = $config['source_url'];
            $this->sourceExtension = $extFromURL($this->sourceURL);
            $this->sourceLocalRawName = $this->sourceMD5 . '_source.' . $this->sourceExtension;
            $this->sourceLocalWavName = $this->sourceMD5 . '.wav';
            $this->errorFile = $this->sourceMD5 . '.errors';

            $this->albumArtURL = $config ['art_url'];
            $this->albumArtMD5 = $config ['art_md5'];
            $this->albumArtDestFile = $this->sourceMD5 . '_art.' . $extFromURL($this->albumArtURL);
        }

        $this->children[] =
            new EncodeTarget($config, $this->sourceLocalWavName,
                             $this->sourceMD5, $this->albumArtDestFile);
    }

    public function addError($singleLineError) {
        file_put_contents($this->errorFile, "$singleLineError\n", FILE_APPEND);
    }

    public function countErrors() {
        if(file_exists($this->errorFile)) {
            return count(file($this->errorFile));
        }
        return 0;
    }

    public function hasSource() {
        return self::hasAsset($this->sourceLocalRawName, $this->sourceMD5);
    }

    public function hasAlbumArt() {
        return self::hasAsset($this->albumArtDestFile, $this->albumArtMD5);
    }

    public function getSource() {
        if($this->getAsset($this->sourceURL, $this->sourceMD5, $this->sourceLocalRawName)) {
            $output = [];
            $rv = 0;

            exec(sprintf('ffmpeg -y -i %s %s 2>&1',
                         escapeshellarg($this->sourceLocalRawName),
                         escapeshellarg($this->sourceLocalWavName)),
                 $output, $rv);
            if($rv === 0) {
                return true;

            } else {
                $this->addError(
                    "FFMpeg couldn't encode raw to wav:\n" .
                    implode("\n", $output));
            }
        }
        return false;
    }

    public function getAlbumArt() {
        return $this->getAsset($this->albumArtURL, $this->albumArtMD5, $this->albumArtDestFile);
    }

    public static function hasAsset($fileName, $md5) {
        $gotmd5 = md5_file($fileName);
        error_log($gotmd5);
        return file_exists($fileName) && $gotmd5 === $md5;
    }

    public function getAsset($url, $md5, $destFile) {
        file_put_contents($destFile, file_get_contents($url));
        if(self::hasAsset($destFile, $md5)) {
            return true;
        }
        // we don't have it
        if(file_exists($destFile)) {
            $this->addError(
                sprintf('Error reading from [%s], expected md5[%s]',
                        $url, $md5));
            unlink($destFile);
        } else {
            $this->addError('No file received from [%s]', $url);
        }
        return false;
    }


    public function doChain() {
        if($this->countErrors() > $this->errorThreshold) {
            $this->addError('Already over error threshold cannot doChain()');
            return false;
        }

        if(($this->hasSource() || $this->getSource()) &&
           $this->hasAlbumArt() || $this->getAlbumArt()
        ) {
            error_log("doing encodes");
            foreach($this->children as $child) {
                $child->doEncode();
            }

        } else {
            $this->addError('Chain Prereqs Failed');
        }
    }
}
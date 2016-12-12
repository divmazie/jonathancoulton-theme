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


        try {
            $target = new EncodeTarget($config, $this->sourceLocalWavName,
                                       $this->sourceMD5, $this->albumArtDestFile);
            $this->children[] = $target;
        } catch(Exception $ex) {
            // just carry on
        }
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


            /**
             * If we've been passed a file that contains raw pcm data
             * we don't want to just blindly slap it into a 16 bit data container
             * we want to encode the full bitrate file into the various formats.
             *
             * This will satisfy the snobs if they are looking. A very few people would be happy
             * to get 24 bit flac files. The rest wouldn't know the difference.
             *
             * How do I know the resultant files don't do some weird transform? Well, I don't know
             * for sure, except that it would be extra work on FFMpeg's part... and I had two 24
             * bit files (one from before and after), put them into Audacity, put an inverted one
             * on top of the other and exported to raw 24 bit pcm. The resultant file was entirely
             * zeroes. Obv "audacity is not a scientific tool" but someone else got small amounts of
             * noise when comparing some files that weren't the same. I think they are the same.
             *
             * Note that I mean the PCM audio stream WITHIN the files is the same. The source
             * files will usually be larger as they may be gummed up with metadata and whatnot.
             * They may be flac, whatever. Just if they are already PCM (not alaw or ulaw, but regular
             * old pcm) we will use the bitstream we are given for quality. Settled? Settled.
             */
            $allowedPCMTypes = array_keys(require(__DIR__ . '/ffmpeg-pcm-allowed-formats.php'));
            $codec =
                shell_exec(sprintf("ffprobe -show_streams -select_streams 0 -i %s 2>&1 | grep codec_name", escapeshellarg($this->sourceLocalRawName)));
            $acodecLine = '';
            if(($codec = str_replace('codec_name=', '', trim($codec))) &&
               in_array($codec, $allowedPCMTypes)
            ) {
                $acodecLine = '-acodec ' . $codec;
            }
            exec(sprintf('ffmpeg -y -i %s %s %s 2>&1',
                         escapeshellarg($this->sourceLocalRawName),
                         $acodecLine,
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
           ($this->hasAlbumArt() || $this->getAlbumArt())
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
<?php

class EncodeTarget {


    protected $destFormatDesc;
    protected $fromWavFile;
    protected $nameBase;
    protected $destFileName;
    protected $destMetadata;
    protected $fromArtFile;
    protected $errorsFile;
    protected $configFile;
    protected $destPostURL;

    public function __construct($config, $fromWav, $fromNameBase, $fromArtFile) {
        // get some reference materials
        $outputFormats = require('ffmpeg-output-formats.php');
        $metadataKeys = require('ffmpeg-metadata-fields.php');

        $this->destPostURL = $config['dest_url'];

        $this->fromWavFile = $fromWav;
        $rowJSON = json_encode($config, JSON_PRETTY_PRINT);
        $this->nameBase = $fromNameBase . md5($rowJSON);
        // for debugging & tracking
        $this->configFile = $this->nameBase . '.txt';
        if(file_exists($this->configFile)) {
            throw new Exception('config exists');
        }
        file_put_contents($this->nameBase . '.txt', $rowJSON);
        $this->errorsFile = $this->nameBase . '.errors.txt';

        $this->fromArtFile = $fromArtFile;

        // default to first format if illegal format given
        $this->destFormatDesc = isset($outputFormats[$config['encode_format']]) ?
            $outputFormats[$config['encode_format']] : $outputFormats[0];

        if(isset($config['ffmpeg_flags']) &&
           isset($outputFormats[$config['encode_format']]) &&
           !preg_match('/[^a-z0-9 :-]/i', $config['ffmpeg_flags'])
        ) {
            // only allow subset of chars to be in flags
            // only use supplied flags if we have a descriptor for the format
            $this->destFormatDesc['flags'] = $config['ffmpeg_flags'];
        }

        $this->destFileName = $this->nameBase . '.' . $this->destFormatDesc['file_ext'];

        $this->destMetadata = $config['metadata'];
        // second arg of array combine is meaningless
        // just need only legal metadata keys
        $this->destMetadata = array_intersect_key(
            $this->destMetadata, array_combine($metadataKeys, $metadataKeys));
    }

    public function getFFMpegMetadataFlags() {
        return implode(' ', array_map(function ($mdKey, $mdVal) {
            return sprintf('-metadata %s=%s', $mdKey, escapeshellarg($mdVal));
        }, array_keys($this->destMetadata), $this->destMetadata));
    }

    public function doEncode() {
        $output = [];
        $rv = 0;

        $cmd = sprintf('ffmpeg -y -i %s %s -c:a %s %s %s 2>&1',
                       escapeshellarg($this->fromWavFile),
                       $this->getFFMpegMetadataFlags(),
                       escapeshellarg($this->destFormatDesc['lib']),
                       $this->destFormatDesc['flags'],
                       escapeshellarg($this->destFileName)
        );
        //error_log($cmd);
        exec($cmd, $output, $rv);

        if($rv != 0) {
            $this->putError(implode("\n", $output));
            return false;
        }

        exec($this->addAlbumArtCommand() . ' 2>&1',
             $output, $rv);
        //error_log($rv);
        //error_log(implode("\n", $output));
        if($rv != 0) {
            $this->putError(implode("\n", $output));
            return false;
        }

        $this->postEncodeFile();
        return true;
    }

    private function putError($error) {
        file_put_contents($this->errorsFile, $error, FILE_APPEND);
    }

    private function postEncodeFile() {
        // initialise the curl request
        $ch = curl_init($this->destPostURL);

// send a file
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
                'md5'  => md5_file($this->destFileName),
                'file' => '@' . realpath($this->destFileName),
            ]);

// output the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $this->putError(curl_exec($ch));

// close the session
        curl_close($ch);

    }

    private function addAlbumArtCommand() {
        switch($this->destFormatDesc['add_art']) {

            // http://ffmpeg.org/ffmpeg-formats.html#mp3
            // working
            case 'ffmpeg':
                $tempName = $this->destFileName . '.temp.' . $this->destFormatDesc['file_ext'];
                // we've gotta move the file and delete it after because otherwise... FFMpeg seems to truncate
                // or something? It makes no goddamn sense, but this does work.
                return sprintf('ffmpeg -i %s -i %s -c copy -map 0 -map 1 ' .
                               '-metadata:s:v title="Album cover" -metadata:s:v comment="Cover (Front)" %s; ' .
                               'mv %s %s',
                               escapeshellarg($this->destFileName),
                               escapeshellarg($this->fromArtFile),
                               escapeshellarg($tempName),
                               escapeshellarg($tempName),
                               escapeshellarg($this->destFileName)
                );

            // tested and working!
            case 'metaflac':
                return sprintf('metaflac --import-picture-from=%s %s',
                               escapeshellarg($this->fromArtFile),
                               escapeshellarg($this->destFileName)
                );
                break;

            case 'ogg-cover-art':
                $scriptPath = __DIR__ . '/ogg-cover-art.sh';
                return sprintf('%s %s %s',
                               $scriptPath,
                               escapeshellarg($this->fromArtFile),
                               escapeshellarg($this->destFileName)
                );
                break;

            case 'mp4box':
                return sprintf('MP4Box -itags cover=%s %s',
                               escapeshellarg($this->fromArtFile),
                               escapeshellarg($this->destFileName)
                );
                break;

            default:
                break;
        }
        return '';
    }

}

?>

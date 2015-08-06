<?php

namespace jct;

class Encode extends WordpressFileAsset {

    private $parentTrack;
    // encode format === file extension!
    private $encodeFormat, $encodeCLIFlags;

    public function __construct(Track $parentTrack, $encodeFormat, $encodeCLIFlags) {
        $this->parentTrack = $parentTrack;
        $this->parent_post_id = $parentTrack->getPostID();
        $this->encodeCLIFlags = $encodeCLIFlags;
        $this->encodeFormat = $encodeFormat;
    }

    public function getEncodeHash() {
        return md5($this->encodeFormat . ':' . $this->encodeCLIFlags . ':' . $this->parentTrack->getTrackVersionHash());
    }

    public function getShortEncodeHash() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }


    public function getFileAssetFileName() {
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $this->parentTrack->getTrackTitle());
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // track number underscore track title underscore short hash dot extension
        return sprintf('%d_%s_%s.%s', $this->parentTrack->getTrackNumber(),
                        $title,$this->getShortEncodeHash(),
                        $this->encodeFormat);
    }

    /* Saved only so I can remember how I did this for now, get_post_meta() is a tricky function
    public function getWPAttachmentID() {
        $attachment_id = get_post_meta($this->parentTrack->getPostID(),'attachment_id_'.$this->encodeFormat.$this->encodeCLIFlags,false)[0];
        if (!$attachment_id) {
            return false;
        } else {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata['encodeHash']==$this->getEncodeHash()) {
                return $attachment_id;
            } else {
                return false;
            }
        }
    }
    */

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getEncodeCLIFlags() {
        return $this->encodeCLIFlags;
    }

    public function getUniqueKey() {
        return md5(serialize($this->getEncodeConfig())); // This gets config without unique key or filename to prevent infinite loop
    }

    private function getPathFromURL($url) { // Should find a way to do this from WP database
        $wp_path = defined(ABSPATH) ? ABSPATH : explode('wp-',getcwd())[0]; // the explode wp- thing is a hack to get the root directory, if ABSPATH is not set
        return str_replace(get_site_url(),$wp_path,$url);
    }

    public function getEncodeConfig($unique_key = "", $file_name = "") {
        $authcode = get_transient('do_secret');
        $parent = $this->parentTrack;
        $config = array('source_url' => $parent->getTrackSourceFileURL(),
            'source_md5' => md5_file($this->getPathFromURL($parent->getTrackSourceFileURL())),
            'encode_format' => $this->getEncodeFormat(),
            'dest_url' => get_site_url()."/api/".$authcode."/receiveencode/".$unique_key,
            'art_url' => $parent->getTrackArtURL(),
            'art_md5' => md5_file($this->getPathFromURL($parent->getTrackArtURL())),
            'meta_data' => array('title' => $parent->getTrackTitle(),
                'track' => $parent->getTrackNumber(),
                'album' => $parent->getAlbum()->getAlbumTitle(),
                'album_artist' => $parent->getAlbum()->getAlbumArtist(),
                'artist' => $parent->getTrackArtist(),
                'comment' => $parent->getTrackComment(),
                'genre' => $parent->getTrackGenre(),
                'filename' => $file_name)
        );
        if ($this->encodeCLIFlags) {
            $config['ffmpeg_flags'] = $this->encodeCLIFlags;
        }
        return $config;
    }

    public function getEncodeConfigIfNecessary() {
        $this->setEncodeTransient();
        $unique_key = $this->getUniqueKey();
        if ($this->fileAssetExists()) {
            $config = false;
        } else {
            $config = $this->getEncodeConfig($unique_key, $this->getFileAssetFileName());
        }
        return $config;
    }

    public function setEncodeTransient() {
        $unique_key = $this->getUniqueKey();
        if (!get_transient($unique_key)) {
            set_transient($unique_key,array($this->parentTrack->getPostID(),$this->getEncodeFormat(),$this->getEncodeCLIFlags()),60*60*24);
        }
    }

}

?>
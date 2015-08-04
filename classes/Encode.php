<?php

namespace jct;

class Encode extends WordpressFileAsset {

    private $parentTrack;
    // encode format === file extension!
    private $encodeFormat, $encodeCLIFlags;

    public function __construct(Track $parentTrack, $encodeFormat, $encodeCLIFlags) {
        $this->parentTrack = $parentTrack;
        $this->encodeCLIFlags = $encodeCLIFlags;
        $this->encodeFormat = $encodeFormat;
    }

    public function getEncodeHash() {
        return md5($this->encodeFormat . ':' . $this->encodeCLIFlags . ':' . $this->parentTrack->getTrackVersionHash());
    }

    public function getShortEncodeHash() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getEncodeHash(), 0, 7);
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

    public function getURL() {
        $args = array(
            'post_parent' => $this->parentTrack->getPostID(),
            'post_type' => 'attachment',
            'post_mime_type' => 'audio',
            'meta_key' => 'encodeHash',
            'meta_value' => $this->getEncodeHash()
        );
        $attachments = get_children( $args );
        if (count($attachments)) {
            return wp_get_attachment_url($attachments[0]->ID);
        } else {
            return false;
        }
    }

    public function encodeExists() {
        if ($this->getURL()) {
            return true;
        } else {
            return false;
        }
    }

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getEncodeCLIFlags() {
        return $this->encodeCLIFlags;
    }

    private function getPathFromURL($url) {
        $wp_path = defined(ABSPATH) ? ABSPATH : explode('wp-',getcwd())[0]; // the explode wp- thing is a hack to get the root directory, if ABSPATH is not set
        return str_replace(get_site_url(),$wp_path,$url);
    }

    public function getEncodeConfig() {
        if (!get_transient($this->getEncodeHash())) {
            set_transient($this->getEncodeHash(),$this->parentTrack->getPostID()."|".$this->getEncodeFormat(),60*60*24);
        }
        $authcode = get_transient('do_secret');
        if ($this->encodeExists()) {
            $config = false;
        } else {
            $parent = $this->parentTrack;
            $config = array('source_url' => $parent->getTrackSourceFileURL(),
                            'source_md5' => md5_file($this->getPathFromURL($parent->getTrackSourceFileURL())),
                            'encode_format' => $this->getEncodeFormat(),
                            'dest_url' => get_site_url()."/api/".$authcode."/receiveencode/".$this->getEncodeHash(),
                            'art_url' => $parent->getTrackArtURL(),
                            'art_md5' => md5_file($this->getPathFromURL($parent->getTrackArtURL())),
                            'meta_data' => array('title' => $parent->getTrackTitle(),
                                                 'track' => $parent->getTrackNumber(),
                                                 'album' => $parent->getAlbum()->getAlbumTitle(),
                                                 'album_artist' => $parent->getAlbum()->getAlbumArtist(),
                                                 'artist' => $parent->getTrackArtist(),
                                                 'comment' => $parent->getTrackComment(),
                                                 'genre' => $parent->getTrackGenre(),
                                                 'filename' => $this->getFileAssetFileName())
                            );

        }
        return $config;
    }

}

?>
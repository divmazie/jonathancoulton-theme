<?php

namespace jct;

class Encode extends WordpressFileAsset {

    private $parentTrack;
    // encode format === file extension!
    private $encodeFormat, $encodeCLIFlags;

    static function recoverFromTransient($transient_key) {
        $encode_details = get_transient($transient_key);
        if (!$encode_details) { return false; }
        $track_post_id = $encode_details[0];
        $encode_format = $encode_details[1];
        $encode_flags = $encode_details[2];
        $track_post = get_post($track_post_id);
        $track = new Track($track_post);
        return new Encode($track,$encode_format,$encode_flags);
    }

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
        $title = $this->parentTrack->getTrackTitle();
        $title = iconv('UTF-8','ASCII//TRANSLIT',$title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
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

    public function saveEncodeFromUpload() {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $file_array_key = "file";
        if (!isset($_FILES[$file_array_key])) {
            return "No file!";
        }
        $attachment_id = media_handle_upload($file_array_key, $this->parentTrack->getPostID(), array(), array('test_form' => false));

        if ( is_wp_error($attachment_id) ) {
            return $attachment_id->get_error_message();
        } else {
            $return = "File is valid, and was successfully uploaded.\n";
            $old_meta = wp_get_attachment_metadata($attachment_id);
            $new_meta = array_merge($old_meta, array('unique_key' => $this->getUniqueKey()));
            $success = wp_update_attachment_metadata($attachment_id,$new_meta);
            $return .= $success ? "Updated metadata! \n" : "Failed to update metadata! \n";
            $return .= "Attachment_id = ".$attachment_id."\n";

            $this->setWPAttachmentID($attachment_id);
            return $return;
        }
    }

}

?>
<?php

namespace jct;

class Encode extends KeyedWPAttachment {

    private $parentTrack;

    static function recoverFromTransient($transient_key) {
        $encode_details = get_transient($transient_key);
        if(!$encode_details) {
            return false;
        }
        $track_post_id = $encode_details[0];
        $encode_format = $encode_details[1];
        $encode_flags = $encode_details[2];
        $encode_label = $encode_details[3];
        $track_post = get_post($track_post_id);
        $track = new Track($track_post);
        return new Encode($track, $encode_format, $encode_flags, $encode_label);
    }

    public function __construct(Track $parentTrack, $encodeFormat, $encodeCLIFlags, $encodeLabel) {
        $this->parentTrack = $parentTrack;
        $this->parent_post_id = $parentTrack->getPostID();
        $this->encodeCLIFlags = $encodeCLIFlags;
        $this->encodeFormat = $encodeFormat;
        $this->encodeLabel = $encodeLabel;
    }

    public function getFileAssetFileName() {
        $title = $this->parentTrack->getTrackTitle();
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // track number underscore track title underscore short hash dot extension
        return sprintf("%'.02d_%s_%s.%s", $this->parentTrack->getTrackNumber(),
                       $title, $this->getShortUniqueKey(),
                       $this->encodeFormat == 'aac' || $this->encodeFormat == 'alac' ? 'm4a' : $this->encodeFormat);
    }

    public function getEncodeLabel() {
        return $this->encodeLabel;
    }

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getEncodeCLIFlags() {
        return $this->encodeCLIFlags;
    }

    public function getParentTrack() {
        return $this->parentTrack;
    }

    public function getUniqueKey() {
        return md5(serialize($this->getEncodeConfig(true))); // This gets config without unique key or filename to prevent infinite loop
    }

    public function getEncodeConfig($forUseInUniqueKey = false) {
        // what is $forUseInUniqueKey:
        // the encode config is a great determinant of whether a file is unique
        // this function both uses a file's unique key and is used to generate it
        // so this flag let's us skip the pieces that would cause infinite recursion
        $authcode = get_transient('do_secret');
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
                    'n/a' :
                    (get_site_url() . "/api/$authcode/receiveencode/" . $this->getUniqueKey()),
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
                'filename'     => $forUseInUniqueKey ? '' : $this->getFileAssetFileName(),
            ],
        ];
        if($this->encodeCLIFlags) {
            $config['ffmpeg_flags'] = $this->encodeCLIFlags;
        }
        return $config;
    }

    public function encodeIsNeeded() {
        return !$this->fileAssetExists();
    }

    public function setEncodeTransient() {
        $unique_key = $this->getUniqueKey();
        if(!get_transient($unique_key)) {
            set_transient($unique_key, [
                $this->parentTrack->getPostID(), $this->getEncodeFormat(), $this->getEncodeCLIFlags(),
                $this->getEncodeLabel(),
            ], 60 * 60 * 24);
        }
    }

    public function saveEncodeFromUpload() {
        if(!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if(!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if(!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $file_array_key = "file";
        if(!isset($_FILES[$file_array_key])) {
            return [false, "No file!"];
        }
        if(md5_file($_FILES[$file_array_key]['tmp_name']) != $_POST['md5']) {
            return [false, "Uploaded file md5 does not match posted md5!"];
        }
        $_FILES[$file_array_key]['name'] = $this->getFileAssetFileName();
        $attachment_id =
            media_handle_upload($file_array_key, $this->parentTrack->getPostID(), [], ['test_form' => false]);

        if(is_wp_error($attachment_id)) {
            return [false, $attachment_id->get_error_message()];
        } else {
            $return = "File is valid, and was successfully uploaded.\n";
            $old_meta = wp_get_attachment_metadata($attachment_id);
            $new_meta = array_merge($old_meta, ['unique_key' => $this->getUniqueKey()]);
            wp_update_attachment_metadata($attachment_id, $new_meta);
            $this->completeAttaching($attachment_id, false);
            $this->setCreatedTime();
            return [true, $return];
        }
    }

    public function getEncodeContext() {
        $context = ['format' => $this->getEncodeFormat(), 'flags' => $this->getEncodeCLIFlags()];
        $context['exists'] = $this->fileAssetExists();
        $context['need_to_upload'] = $this->needToUpload();
        return $context;
    }

    public function getAwsKey() { // Same as getFileAssetFileName() without the short hash
        $title = $this->parentTrack->getTrackTitle();
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);
        $album_title = $this->parentTrack->getAlbum()->getAlbumTitle();
        $album_title = iconv('UTF-8', 'ASCII//TRANSLIT', $album_title);
        // replace spaces with underscore
        $album_title = preg_replace('/\s/u', '_', $album_title);
        // remove non ascii alnum_ with
        $album_title = preg_replace('/[^\da-z_]/i', '', $album_title);

        // track number underscore track title dot extension
        return sprintf("%s_%'.02d_%s_%s.%s", $album_title, $this->parentTrack->getTrackNumber(), $title, $this->encodeFormat,
                       $this->encodeFormat == 'aac' || $this->encodeFormat == 'alac' ? 'm4a' : $this->encodeFormat);
    }

}

?>
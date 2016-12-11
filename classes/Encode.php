<?php

namespace jct;

class Encode extends KeyedWPAttachment {

    const META_UNIQUE_KEY = 'encode_unique_key';
    const META_ENCODE_CONFIG_KEY = 'encode_config';
    const META_PARENT_TRACK_KEY = 'encode_parent_track';

    /**
     * @return Track
     */
    public function getParentTrack() {
        return $this->getParentPost(Track::class);
    }

    public function setParentTrack(Track $track) {
        $this->setParentPost($track);
    }

    /**
     * @return EncodeConfig
     */
    public function getEncodeConfig() {
        return EncodeConfig::fromPersistableArray($this->getAttachmentMetaPayloadArray());
    }

    public function setEncodeConfig(EncodeConfig $encodeConfig) {
        $this->setAttachmentMetaPayloadArray($encodeConfig->toPersistableArray());
    }

    public function getFileAssetFileName() {
        return $this->getEncodeConfig()->getConfigSpecificFileName();
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

    /**
     * @param $uniqueKey
     * @return Encode|null
     */
    public static function findByUniqueKey($uniqueKey) {
        return Util::get_posts_cached([
                                          'post_type'  => self::POST_TYPE_NAME,
                                          'meta_query' => [
                                              'key'   => self::META_UNIQUE_KEY,
                                              'value' => $uniqueKey,
                                          ],
                                      ], self::class);
    }


}

?>
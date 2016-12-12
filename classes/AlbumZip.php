<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/7/15
 * Time: 13:59
 */

namespace jct;


class AlbumZip extends KeyedWPAttachment {


    public function createZip() {
        if(!$this->isZipWorthy()) {
            return [false, "Album is not zip-worthy!\n"];
        } elseif($this->fileAssetExists()) {
            return [false, "Album is already zipped!\n"];
        } else {
            $upload_dir = wp_upload_dir();
            $zip = new \ZipArchive();
            $filecount = 0;
            $filename = $upload_dir['path'] . "/" . $this->getFileAssetFileName();
            $filetype = wp_check_filetype(basename($filename), null);
            if($zip->open($filename, \ZipArchive::CREATE) !== true) {
                return [false, "Cannot open zip file: <$filename>\n"];
            }
            $zip_dir_name = $this->parentAlbum->getAlbumTitle() . "/";
            foreach($this->getEncodesToZip() as $encode) {
                $encode_path = $encode->getPath();
                if($encode_path) {
                    $success = $zip->addFile($encode_path, $zip_dir_name . basename($encode_path));
                    $filecount++;
                    if(!$success) {
                        return [false, "Cannot find " . $encode_path . "\n"];
                    }
                } else {
                    return [false, "Cannot find path for " . $encode->getFileAssetFileName() . "\n"];
                }
            }
            foreach($this->parentAlbum->getAlbumBonusAssetObjects() as $bonus_asset) {
                $bonus_path = $bonus_asset->getPath();
                if($bonus_path) {
                    $success = $zip->addFile($bonus_path, $zip_dir_name . basename($bonus_path));
                    $filecount++;
                    if(!$success) {
                        return [false, "Cannot find " . $bonus_path . "\n"];
                    }
                }
            }
            if(method_exists($zip, 'setCompressionIndex')) {
                for($i = 0; $i < $filecount; $i++) {
                    $zip->setCompressionIndex($i, \ZipArchive::CM_STORE);
                }
            }
            $zip->close();
            $attachment = [
                'guid'           => $upload_dir['url'] . '/' . basename($filename),
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $filename, $this->parentAlbum->getPostID());
            if(!$attach_id) {
                return [false, "Zip created, but couldn't add it as attachment to WP!\n"];
            }
            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            $attach_data = array_merge($attach_data, ['unique_key' => $this->getUniqueKey()]);
            wp_update_attachment_metadata($attach_id, $attach_data);
            $this->completeAttaching($attach_id, true);
            $this->setCreatedTime();
            return [true, "Zip created successfully!\n"];
        }
    }

    public function getAwsKey() { // Same as getFileAssetFileName() without the short hash
        $title = $this->parentAlbum->getAlbumTitle();
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // album title underscore format underscore short hash dot extension
        return sprintf('%s_%s.%s', $title, $this->encodeFormat, "zip");
    }
}
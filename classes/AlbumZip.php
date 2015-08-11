<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/7/15
 * Time: 13:59
 */

namespace jct;


class AlbumZip extends WordpressFileAsset {

    private $parentAlbum;
    // encode format === file extension!
    private $encodeFormat, $encodeCLIFlags;

    public function __construct(Album $parentAlbum, $encodeFormat, $encodeCLIFlags) {
        $this->parentAlbum = $parentAlbum;
        $this->parent_post_id = $parentAlbum->getPostID();
        $this->encodeCLIFlags = $encodeCLIFlags;
        $this->encodeFormat = $encodeFormat;
    }

    public function getUniqueKey() {
        $parent_album = $this->parentAlbum;
        $album_info = array(
            $parent_album->getAlbumTitle(),
            md5_file($parent_album->getAlbumBonusAssetPath())
        );
        foreach ($this->getEncodes() as $encode) {
            $album_info[] = $encode->getUniqueKey();
        }
        return md5(serialize($album_info));
    }

    public function getFileAssetFileName() {
        //return "something.zip";
        $title = $this->parentAlbum->getAlbumTitle();
        $title = iconv('UTF-8','ASCII//TRANSLIT',$title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // album title underscore short hash dot extension
        return sprintf('%s_%s.%s',$title,$this->getShortUniqueKey(),"zip");
    }

    public function getEncodes() {
        $parent_album = $this->getParentAlbum();
        $tracks = $parent_album->getAlbumTracks();
        $encodes = array();
        foreach ($tracks as $track) {
            $encodes[] = $track->getChildEncode($this->encodeFormat,$this->encodeCLIFlags);
        }
        return $encodes;
    }

    public function createZip() {
        $upload_dir = wp_upload_dir();
        $zip = new \ZipArchive();
        $filename = $upload_dir['path']."/".$this->getFileAssetFileName();
        $filetype = wp_check_filetype( basename( $filename ), null );
        if ($zip->open($filename, \ZipArchive::CREATE)!==TRUE) {
            exit("cannot open <$filename>\n");
        }
        $zip_dir_name = $this->parentAlbum->getAlbumTitle()."/";
        //$test_filename = $upload_dir['path']."/270924_10150965531319525_1326549980_n-150x150.jpg";
        //$zip->addFile($test_filename,$zip_dir_name.basename($test_filename));
        foreach ($this->getEncodes() as $encode) {
            $encode_path = $encode->getPath();
            if ($encode_path) {
                $zip->addFile($encode_path, $zip_dir_name . basename($encode_path));
            }
        }
        $bonus_path = $this->parentAlbum->getAlbumBonusAssetPath();
        if ($bonus_path) {
            $zip->addFile($bonus_path, $zip_dir_name . basename($bonus_path));
        }
        $zip->close();
        //return $zip;
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . basename( $filename ),
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        // Insert the attachment.
        $attach_id = wp_insert_attachment( $attachment, $filename, $this->parentAlbum->getPostID() );
        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        // Generate the metadata for the attachment, and update the database record.
        $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
        $attach_data = array_merge($attach_data, array('unique_key' => $this->getUniqueKey()));
        wp_update_attachment_metadata( $attach_id, $attach_data );
        $this->setWPAttachmentID($attach_id);
    }

    public function getParentAlbum() {
        return $this->parentAlbum;
    }

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getEncodeCLIFlags() {
        return $this->encodeCLIFlags;
    }
}
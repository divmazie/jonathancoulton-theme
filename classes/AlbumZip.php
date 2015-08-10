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
        //return "before zip construct";
        $zip = new ZipArchive();
        return "after zip construct";
        $filename = "./test112.zip";
        return $filename;
        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            exit("cannot open <$filename>\n");
        }
        return $zip;
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
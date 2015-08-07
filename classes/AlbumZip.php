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

    }

    public function getFileAssetFileName() {

    }
}
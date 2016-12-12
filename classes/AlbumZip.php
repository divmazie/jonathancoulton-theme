<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/7/15
 * Time: 13:59
 */

namespace jct;


class AlbumZip extends KeyedWPAttachment {

    public function getParentAlbum() {
        return $this->getParentPost(Album::class);
    }

    public function getAlbumZipConfig() {
        return AlbumZipConfig::fromPersistableArray($this->getAttachmentMetaPayloadArray());
    }

    public function getFileAssetFileName() {
        // TODO: Implement getFileAssetFileName() method.
    }

    public function getAwsKey() {
        // TODO: Implement getAwsKey() method.
    }

}
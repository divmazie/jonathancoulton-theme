<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/7/15
 * Time: 13:59
 */

namespace jct;


class AlbumZip extends EncodedAsset {

    /** @return Album */
    public function getParentAlbum() {
        return $this->getParentPost(Album::class);
    }

    public function getAlbumZipConfig() {
        return AlbumZipConfig::fromPersistableArray($this->getConfigPayloadArray());
    }

    public function setAlbumZipConfig(AlbumZipConfig $zipConfig) {
        $this->setConfigPayloadArray($zipConfig->toPersistableArray());
    }

    public function getAwsName() {
        return $this->getParentAlbum()->getFilenameFriendlyTitle() . '/' .
               sprintf('%s (%s).%s',
                       $this->getParentAlbum()->getTitle(),
                       $this->getAlbumZipConfig()->getEncodeFormat(),
                       $this->getAlbumZipConfig()->getFileExtension());
    }
}
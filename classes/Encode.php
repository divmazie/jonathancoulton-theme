<?php

namespace jct;

class Encode extends EncodedAsset {

    /**
     * @return Track
     */
    public function getParentTrack() {
        return $this->getParentPost(Track::class);
    }

    /**
     * @return EncodeConfig
     */
    public function getEncodeConfig() {
        return EncodeConfig::fromPersistableArray($this->getConfigPayloadArray());
    }

    public function setEncodeConfig(EncodeConfig $encodeConfig) {
        $this->setConfigPayloadArray($encodeConfig->toPersistableArray());
    }

    public function getAwsName() {
        return $this->getParentTrack()->getAlbum()->getFilenameFriendlyTitle() . '/' .
               $this->getEncodeConfig()->getEncodeFormat() . '/' .
               $this->getParentTrack()->getPublicFilename($this->getEncodeConfig()->getFileExtension());
    }

}

?>
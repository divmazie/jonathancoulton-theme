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
        return EncodeConfig::fromPersistableArray($this->getAttachmentMetaPayloadArray());
    }

    public function setEncodeConfig(EncodeConfig $encodeConfig) {
        $this->setAttachmentMetaPayloadArray($encodeConfig->toPersistableArray());
    }

    public function getFileAssetFileName() {
        return $this->getEncodeConfig()->getConfigUniqueFilename();
    }

    public function getAwsKey() {
        return $this->getEncodeConfig()->getConfigUniqueFilename();
    }

}

?>
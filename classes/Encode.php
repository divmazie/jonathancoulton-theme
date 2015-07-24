<?php

namespace jct;

class Encode extends WordpressFileAsset {

    private $parentTrack;
    // encode format === file extension!
    private $encodeFormat, $encodeCLIFlags;

    public function __construct(\Track $parentTrack, $encodeFormat, $encodeCLIFlags) {
        $this->parentTrack = $parentTrack;
        $this->encodeCLIFlags = $encodeCLIFlags;
        $this->encodeFormat = $encodeFormat;
    }

    public function getFileAssetFileName() {
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $this->parentTrack->getTrackTitle());
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // track number underscore track title dot extension
        return sprintf('%d_%s.%s', $this->parentTrack->getTrackNumber(), $title, $this->encodeFormat);
    }


}

?>
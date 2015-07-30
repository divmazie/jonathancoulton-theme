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

    public function getEncodeHash() {
        return md5($this->encodeFormat . ':' . $this->encodeCLIFlags . ':' . $this->parentTrack->getTrackVersionHash());
    }

    public function getShortEncodeHash() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getEncodeHash(), 0, 7);
    }


    public function getFileAssetFileName() {
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $this->parentTrack->getTrackTitle());
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        // track number underscore track title underscore short hash dot extension
        return sprintf('%d_%s_%s.%s', $this->parentTrack->getTrackNumber(),
                        $title,$this->getShortEncodeHash(),
                        $this->encodeFormat);
    }

    public function getWPpost() {
        $args = array(
            'post_parent' => $this->parentTrack->getPostID(),
            'post_type' => 'attachment',
            'post_mime_type' => 'audio',
            'meta_key' => 'encodeHash',
            'meta_value' => $this->getEncodeHash()
        );
        $attachments = get_children( $args );
        if (count($attachments)) {
            return $attachments[0];
        } else {
            return false;
        }
    }

    public function encodeExists() {
        if ($this->getWPpost()) {
            return true;
        } else {
            return false;
        }
    }

    public function getEncodeFormat() {
        return $this->encodeFormat;
    }

    public function getEncodeCLIFlags() {
        return $this->encodeCLIFlags;
    }


}

?>
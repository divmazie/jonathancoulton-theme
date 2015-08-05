<?php

namespace jct;

abstract class WordpressFileAsset {

    abstract public function getUniqueKey();

    abstract public function getFileAssetFileName();

    public function fileAssetExists() {
        if ($this->getWPAttachment()) {
            return true;
        } else {
            return false;
        }
    }

    public function getWPAttachment($parent = null) {
        //return false;
        $query = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'post_parent' => $parent,
            'meta_query' => array(
                array(
                    'unique_key' => $this->getUniqueKey()
                )
            )));
        return $query[0] ? $query[0] : false;
    }

    public function getWPAttachmentID() {
        $attachment = $this->getWPAttachment();
        return $attachment ? $attachment->ID : false;
    }

    public function getURL() {
        $attachment_id = $this->getWPAttachmentID();
        return $attachment_id ? wp_get_attachment_url($attachment_id) : false;
    }

}


?>
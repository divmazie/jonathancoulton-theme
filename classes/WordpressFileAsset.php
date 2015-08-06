<?php

namespace jct;

abstract class WordpressFileAsset {

    public $parent_post_id;

    abstract public function getUniqueKey();

    abstract public function getFileAssetFileName();

    public function fileAssetExists() {
        if ($this->getWPAttachment()) {
            return true;
        } else {
            return false;
        }
    }

    public function getWPAttachment() {
        $attachment_id = $this->getWPAttachmentID();
        if (!$attachment_id) {
            return false;
        } else {
            return $attachment_id;
            // This block can check that the attachment also has the unique key stored, probs unnecessary
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata['unique_key'] == $this->getUniqueKey()) {
                return get_post($attachment_id);
            } else {
                return false;
            }
        }
        /* This method really should work, but I suspect WP has bugs in it
        $args = array(
            'post_type' => 'attachment',
            'post_parent' => $this->parent_post_id,
            'meta_key' => 'unique_key',
            'meta_value' => $this->getUniqueKey()
            //'meta_query' => array(
                //array(
                    //'key' => 'unique_key',
                    //'value' => $this->getUniqueKey()
                //))
            );
        //return var_dump($args);
        $posts = get_posts($args);
        return $posts[0] ? $posts[0] : false;
        */
    }

    public function setWPAttachmentID($attachment_id) {
        update_post_meta($this->parent_post_id, 'attachment_id_'.$this->getUniqueKey(), $attachment_id);
    }

    public function getWPAttachmentID() {
        $attachment_id = get_post_meta($this->parent_post_id,'attachment_id_'.$this->getUniqueKey(),false)[0];
        //$attachment = $this->getWPAttachment();
        return $attachment_id ? $attachment_id : false;
    }

    public function getURL() {
        $attachment_id = $this->getWPAttachmentID();
        return $attachment_id ? wp_get_attachment_url($attachment_id) : false;
    }

}


?>
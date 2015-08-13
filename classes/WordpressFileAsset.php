<?php

namespace jct;

abstract class WordpressFileAsset {

    public $parent_post_id;

    abstract public function getUniqueKey();

    abstract public function getFileAssetFileName();

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

    public function fileAssetExists() {
        if ($this->getWPAttachment() && file_exists($this->getPath()) && filesize($this->getPath())) {
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
        return update_post_meta($this->parent_post_id, 'attachment_id_'.$this->getUniqueKey(), $attachment_id);
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

    public function getPath() {
        $attachment_id = $this->getWPAttachmentID();
        return $attachment_id ? get_attached_file($attachment_id) : false;
    }

    public function completeAttaching($attachment_id) {
        $this->setWPAttachmentID($attachment_id);
        $this->fixAttachmentFileName($attachment_id);
    }

    public function fixAttachmentFileName($attachment_id) {
        $file = $this->getPath();
        $dir = pathinfo($file)['dirname'];
        $newfile = $dir."/".$this->getFileAssetFileName();
        rename($file, $newfile);
        update_attached_file($attachment_id,$newfile);
    }

    static function deleteOldAttachments($post_id,$goodKeys) {
        $metadata = get_post_meta($post_id);
        $deleted = array();
        foreach ($metadata as $key => $val) {
            if (substr($key,0,14)=='attachment_id_' && !in_array(substr($key,-32),$goodKeys)) {
                $deleted[] = wp_delete_attachment(intval($val[0]));
            }
        }
        return $deleted;
    }

}


?>
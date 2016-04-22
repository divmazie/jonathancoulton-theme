<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/13/15
 * Time: 12:01
 */

namespace jct;


class WPAttachment {

    public $attachment_id;
    public $parent_post_id;
    private $path;

    public function __construct($attachment) {
        if (is_array($attachment)) {
            $this->attachment_id = $attachment['id'];
        } else {
            $this->attachment_id = $attachment;
        }
        $this->parent_post_id = wp_get_post_parent_id($this->attachment_id);
    }

    public function getAttachmentID() { // must use this function instead of property because it gets redefined in KeyedWPAttachment
        return $this->attachment_id;
    }

    public function getFilename() {
        return basename($this->getPath());
    }

    public function getPath() {
        if (!isset($this->path)) $this->path = get_attached_file($this->getAttachmentID());
        return $this->path;
    }

    public function getURL() {
        return wp_get_attachment_url($this->getAttachmentID());
    }

    public function fileAssetExists() {
        if ($this->getAttachmentID() ) {// && file_exists($this->getPath()) && filesize($this->getPath())) { // Checking filesize for hundreds of files slows things down?
            return true;
        } else {
            return false;
        }
    }

}
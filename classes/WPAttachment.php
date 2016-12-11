<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/13/15
 * Time: 12:01
 */

namespace jct;


class WPAttachment extends JCTPost {

    public function __construct($id) {
        parent::__construct($id);
    }

    public function getAttachmentID() { // must use this function instead of property because it gets redefined in KeyedWPAttachment
        return $this->getPostID();
    }

    public function getFilename() {
        return basename($this->getPath());
    }

    public function getPath() {
        return get_attached_file($this->ID);
    }

    public function getURL() {
        return wp_get_attachment_url($this->ID);
    }

    public function fileAssetExists() {
        return $this->ID && file_exists($this->getPath()) && filesize($this->getPath());
    }

}
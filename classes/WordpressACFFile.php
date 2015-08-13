<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/13/15
 * Time: 12:01
 */

namespace jct;


class WordpressACFFile {

    private $attachment_id;

    public function __construct($attachment) {
        if (is_array($attachment)) {
            $this->attachment_id = $attachment['id'];
        } else {
            $this->attachment_id = $attachment;
        }
    }

    public function getAttachmentID() {
        return $this->attachment_id;
    }

    public function getPath() {
        return get_attached_file($this->attachment_id);
    }

    public function getURL() {
        return wp_get_attachment_url($this->attachment_id);
    }

}
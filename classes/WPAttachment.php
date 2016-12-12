<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/13/15
 * Time: 12:01
 */

namespace jct;


class WPAttachment extends JCTPost {

    const POST_TYPE_NAME = 'attachment';

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
        /** @noinspection PhpUndefinedFunctionInspection */
        return get_attached_file($this->ID);
    }

    public function getURL() {
        /** @noinspection PhpUndefinedFunctionInspection */
        return wp_get_attachment_url($this->ID);
    }

    public function fileAssetExists() {
        return $this->ID && file_exists($this->getPath()) && filesize($this->getPath());
    }

    public function deleteAttachment($skipTrash = false) {
        /** @noinspection PhpUndefinedFunctionInspection */
        wp_delete_attachment($this->getPostID(), $skipTrash);
    }

    public static function getWPAttachmentByID(integer $id) {
        return Util::get_posts_cached($id, static::class);
    }


}
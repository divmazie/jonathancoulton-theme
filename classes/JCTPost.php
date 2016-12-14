<?php

namespace jct;


use Timber\Post;

class JCTPost extends Post {

    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Music download';

    public function getPostID() {
        return $this->ID;
    }

    public function getFilenameFriendlyTitle() {
        return Util::filename_friendly_string($this->title());
    }


    /** @return static */
    public static function getByID($id, $prepop = null) {
        return Util::get_posts_cached($id, static::class, $prepop);
    }


}
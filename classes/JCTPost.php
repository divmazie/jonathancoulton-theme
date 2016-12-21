<?php

namespace jct;


use Timber\Post;

class JCTPost extends Post {


    public function getPostID() {
        return $this->ID;
    }

    public function getTitle() {
        return html_entity_decode($this->post_title);
    }

    public function getFilenameFriendlyTitle() {
        return Util::filename_friendly_string($this->getTitle());
    }


    /** @return static */
    public static function getByID($id, $prepop = null) {
        return Util::get_posts_cached($id, static::class, $prepop);
    }

    public static function getPostType() {
        return 'post';
    }

    public static function getAll() {
        $all = Util::get_posts_cached([
                                          'post_type'      => static::getPostType(),
                                          'posts_per_page' => -1,
                                      ], static::class);

        // prepop by item id
        foreach($all as $item) {
            /** @var JCTPost $item */
            static::getByID($item->getPostID(), $item);
        }

        return $all;
    }


}
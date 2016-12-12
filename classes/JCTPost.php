<?php

namespace jct;


use Timber\Post;

class JCTPost extends Post {

    public function getPostID() {
        return $this->ID;
    }

    public function getFilenameFriendlyTitle() {
        //return "something.zip";
        $title = $this->title();
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        // replace spaces with underscore
        $title = preg_replace('/\s/u', '_', $title);
        // remove non ascii alnum_ with
        $title = preg_replace('/[^\da-z_]/i', '', $title);

        return $title;
    }


    /** @return static */
    public static function getByID($id, $prepop = null) {
        return Util::get_posts_cached($id, static::class, $prepop);
    }


}
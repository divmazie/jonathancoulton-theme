<?php

namespace jct;


use Timber\Post;

class JCTPost extends Post {

    public function getPostID() {
        return $this->ID;
    }

}
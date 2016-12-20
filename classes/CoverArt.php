<?php

namespace jct;

use jct\Shopify\Image;

class CoverArt extends WPAttachment {

    public function getShopifyImage() {
        $image = new Image();
        // this will crash on dev is the url is not valid...
        $image->src = Util::is_dev() ?
            'https://upload.wikimedia.org/wikipedia/commons/8/8a/Laitche-P013.jpg' :
            $this->getURL();

        return $image;
    }
}

?>
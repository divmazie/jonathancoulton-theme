<?php

namespace jct\Shopify;

use jct\Shopify\Provider\ProductOptionProvider;

class ProductOption extends Struct {

    public
        // int
        $product_id,
        // strings
        $name,
        $position,
        // string[]
        $values;


    protected function postProperties() {
        return ['name'];
    }

    protected function putProperties() {
        return $this->postProperties();
    }

}

?>
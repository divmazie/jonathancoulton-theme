<?php

namespace jct\Shopify;

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
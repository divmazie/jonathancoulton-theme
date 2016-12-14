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


    public static function fromProductOptionProvider(ProductOptionProvider $optionProvider) {
        $option = new self();

        $option->name = $optionProvider->getProductOptionTitle();

        return $option;
    }

}

?>
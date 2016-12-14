<?php

namespace jct\Shopify;

use jct\Shopify\Exception\Exception;

abstract class ChildStruct extends Struct {

    private $productParent;

    public function __construct(Struct $parent) {
        $this->productParent = $parent;
    }

    /**
     * @return Product
     */
    public function getParent() {
        return $this->productParent;
    }
}
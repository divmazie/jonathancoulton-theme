<?php

namespace jct\Shopify\Provider;

interface ProductVariantProvider {

    public function getProductVariantTitle();

    public function getProductVariantPrice();

    public function getProductVariantSKU();

    public function getProductVariantOption1();

    public function getProductVariantOption2();

    public function getProductVariantOption3();

}
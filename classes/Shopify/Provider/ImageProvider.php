<?php

namespace jct\Shopify\Provider;

interface ImageProvider {
    public function getShopifyImageID();

    public function getProductImageSourceUrl();
}
<?php

namespace jct;

use jct\Shopify\Product;

interface MusicStoreProduct extends ShopifySyncable {

    /**
     * @return Product
     */
    public function getShopifyProduct();

}
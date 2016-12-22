<?php

namespace jct;

use jct\Shopify\CustomCollection;
use jct\Shopify\Product;

interface MusicStoreCollection extends ShopifySyncable {

    /**
     * @return CustomCollection
     */
    public function getShopifyCustomCollection();

    /**
     * @return Product[]
     */
    public function getShopifyCollectionProducts();

}
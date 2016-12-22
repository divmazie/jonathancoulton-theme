<?php

namespace jct;

use jct\Shopify\CustomCollection;

interface MusicStoreCollection extends ShopifySyncable {

    /**
     * @return CustomCollection
     */
    public function getShopifyCustomCollection();

}
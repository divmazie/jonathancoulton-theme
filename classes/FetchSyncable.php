<?php

namespace jct;

use FetchApp\API\Product;

interface FetchSyncable {


    public function getShopifyAndFetchSKU();

    /**
     * @return Product
     */
    public function getFetchAppProduct();

    /**
     * @return array
     */
    public function getFetchAppUrlsArray();
}
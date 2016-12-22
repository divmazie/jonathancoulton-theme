<?php

namespace jct;

use FetchApp\API\Currency;
use FetchApp\API\Product as FetchProduct;

class EverythingFetchSyncable implements FetchSyncable {

    private $configName, $parent;

    public function __construct($configName, EverythingProduct $parent) {
        $this->configName = $configName;
        $this->parent = $parent;
    }

    /**
     * @return mixed
     */
    public function getConfigName() {
        return $this->configName;
    }



    public function getShopifyAndFetchSKU() {
        return 'jct_everything:' . $this->configName;
    }

    public function getFetchAppProduct() {
        $fetchProduct = new FetchProduct();
        $fetchProduct->setProductID($this->getShopifyAndFetchSKU());
        $fetchProduct->setSKU($this->getShopifyAndFetchSKU());
        $fetchProduct->setName($this->parent->getTitle($this->configName));
        $fetchProduct->setDescription($this->parent->getCopy());
        $fetchProduct->setPrice($this->parent->getPrice());
        $fetchProduct->setCurrency(Currency::USD);
        return $fetchProduct;
    }

    public function getFetchAppUrlsArray() {
        return Util::array_merge_flatten_1L(array_map(function (Album $album) {
            return $album->getAlbumZipConfigByName($this->configName)->getAlbumZip()->getFetchAppUrlsArray();
        }, Album::getAll()));
    }


}
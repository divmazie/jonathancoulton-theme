<?php

namespace jct;


use jct\Shopify\Product;
use jct\Shopify\Provider\ProductProvider;

class ThemeObjectRepository {
    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Parallel Testing';


    public static function optimizeQueries() {
        // basically this will get everything we'll need in a few
        // bigger queries, then it will all be store.
        Album::getAll();
        Track::getAll();
        CoverArt::getAll();
        BonusAsset::getAll();
    }

    public static function getLocalShopifyProducts() {
        self::optimizeQueries();

        $productProviders = array_merge(Album::getAll(), Track::getAll());
        return array_map(function (ProductProvider $provider) {
            return Product::fromProductProvider($provider);
        }, $productProviders);
    }

}
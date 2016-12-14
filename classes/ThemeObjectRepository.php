<?php

namespace jct;


class ThemeObjectRepository {
    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Music download';


    public static function getLocalShopifyProductProviders() {
        $albums = Album::getAll();

    }

}
<?php

namespace jct\Shopify\Provider;

interface ProductProvider {

    public function getShopifyTitle();

    public function getShopifyBodyHtml();

    public function getShopifyProductType();

    public function getShopifyVendor();

    // we only use one for our implementation
    public function getShopifyTags();

    /**
     * @return ProductVariantProvider[]
     */
    public function getProductVariantProviders();

    /**
     * @return ProductOptionProvider[]
     */
    public function getProductOptionProviders();

    /**
     * @return ImageProvider[]
     */
    public function getProductImageProviders();

    public function getProductMetafieldProviders();

}
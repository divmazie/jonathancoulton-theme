<?php

namespace jct\Shopify\Provider;

use jct\Shopify\Product;

interface ProductProvider {

    public function getShopifyID();

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

    /**
     * @return MetafieldProvider[]
     */
    public function getProductMetafieldProviders();

    /**
     * @return MetafieldProvider[]
     */
    public function getProductMetafieldProvidersToUpdate();

    public function shouldUpdateProduct();


    public function remoteProductResponse(Product $product);


}
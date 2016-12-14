<?php

namespace jct\Shopify;

use jct\Shopify\Provider\ProductImageProvider;
use jct\Shopify\Provider\ProductMetafieldProvider;
use jct\Shopify\Provider\ProductOptionProvider;
use jct\Shopify\Provider\ProductProvider;
use jct\Shopify\Provider\ProductVariantProvider;
use jct\Track;

class Product extends Struct {
    //https://help.shopify.com/api/reference/product
    public
        // strings that i can POST and PUT
        $title,
        $body_html,
        $product_type,
        $vendor,
        $tags,

        // Variant[]
        $variants,
        // ProductOption[]
        $options,
        // ProductImage[]
        $images,

        // Metafield[]
        $metafields,

        // unused (default values)
        $template_suffix,
        $published_scope,

        // not updateable (do not send to the mothership)
        //A human-friendly unique string for the Product automatically generated from its title. They are used by the Liquid templating language to refer to objects.
        $handle,
        // ProductImage // the first image
        $image,
        // in as string --> DateTime
        $created_at,
        $updated_at,
        $published_at;


    protected function postProperties() {
        return ['title', 'body_html', 'product_type', 'vendor', 'tags', 'variants', 'options', 'images', 'metafields'];
    }

    protected function putProperties() {
        return array_merge(['id'], $this->postProperties());
    }


    protected function setProperty($propertyName, $property) {
        switch($propertyName) {
            case 'variants':
                $property = ProductVariant::instancesFromArray($property);
                break;
            case 'options':
                $property = ProductOption::instancesFromArray($property);
                break;
            case 'image':
                $property = ProductImage::instanceFromArray($property);
                break;
            case 'images':
                $property = ProductImage::instancesFromArray($property);
                break;
        }

        parent::setProperty($propertyName, $property);
    }


    public static function fromProductProvider(ProductProvider $productProvider) {
        $product = new self();

        $product->title = $productProvider->getShopifyTitle();
        $product->body_html = $productProvider->getShopifyBodyHtml();
        $product->product_type = $productProvider->getShopifyProductType();
        $product->vendor = $productProvider->getShopifyVendor();
        $product->tags = $productProvider->getShopifyTags();

        // fill out the whole darn tree
        $product->variants = array_map(function (ProductVariantProvider $variantProvider) {
            return ProductVariant::fromProductVariantProvider($variantProvider);
        }, $productProvider->getProductVariantProviders());

        $product->options = array_map(function (ProductOptionProvider $provider) {
            return ProductOption::fromProductOptionProvider($provider);
        }, $productProvider->getProductOptionProviders());

        $product->images = array_map(function (ProductImageProvider $provider) {
            return ProductImage::fromProductImageProvider($provider);
        }, $productProvider->getProductImageProviders());

        $product->metafields = array_map(function (ProductMetafieldProvider $provider) {
            return Metafield::fromProductMetafieldProvider($provider);
        }, $productProvider->getProductMetafieldProviders());
    }

}
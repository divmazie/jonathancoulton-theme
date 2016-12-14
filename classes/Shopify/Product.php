<?php

namespace jct\Shopify;

use jct\Shopify\Provider\ImageProvider;
use jct\Shopify\Provider\MetafieldProvider;
use jct\Shopify\Provider\ProductOptionProvider;
use jct\Shopify\Provider\ProductProvider;
use jct\Shopify\Provider\ProductVariantProvider;

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
                $property = ProductVariant::instancesFromArray($property, $this);
                break;
            case 'options':
                $property = ProductOption::instancesFromArray($property, $this);
                break;
            case 'image':
                $property = Image::instanceFromArray($property, $this);
                break;
            case 'images':
                $property = Image::instancesFromArray($property, $this);
                break;
        }

        parent::setProperty($propertyName, $property);
    }


    public static function fromProductProvider(ProductProvider $productProvider) {
        $product = new static();

        $product->title = $productProvider->getShopifyTitle();
        $product->body_html = $productProvider->getShopifyBodyHtml();
        $product->product_type = $productProvider->getShopifyProductType();
        $product->vendor = $productProvider->getShopifyVendor();
        $product->tags = $productProvider->getShopifyTags();

        // fill out the whole darn tree
        $product->variants = array_map(function (ProductVariantProvider $variantProvider) use ($product) {
            return Variant::fromProductVariantProvider($product, $variantProvider);
        }, $productProvider->getProductVariantProviders());

        $product->options = array_map(function (ProductOptionProvider $provider) use ($product) {
            return Option::fromProductOptionProvider($product, $provider);
        }, $productProvider->getProductOptionProviders());

        $product->images = array_map(function (ImageProvider $provider) use ($product) {
            return Image::fromImageProvider($product, $provider);
        }, $productProvider->getProductImageProviders());

        $product->metafields = array_map(function (MetafieldProvider $provider) use ($product) {
            return Metafield::fromMetafieldProvider($product, $provider);
        }, $productProvider->getProductMetafieldProviders());
    }

}
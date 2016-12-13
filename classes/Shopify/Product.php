<?php

namespace jct\Shopify;

class Product extends Struct {

    public
        // strings that i can POST and PUT
        $title,
        $body_html,
        $vendor,
        $product_type,
        $handle,
        // Variant[]
        $variants,
        // Option[]
        $options,
        // Image[]
        $images,
        // Image
        $image,

        // unused (default values)
        $template_suffix = null,
        $published_scope = 'global',
        $tags = '',

        // not updateable
        // in as string --> DateTime
        $created_at,
        $updated_at,
        $published_at;


    protected function propertySet($propertyName, $property) {
        switch($propertyName) {
            case 'variants':
                $property = ProductVariant::instancesFromArray($property);
                break;
            case 'options':
                $property = Option::instancesFromArray($property);
                break;
            case 'image':
                $property = ProductImage::instanceFromArray($property);
                break;
            case 'images':
                $property = ProductImage::instancesFromArray($property);
                break;
        }

        parent::propertySet($propertyName, $property);
    }

}
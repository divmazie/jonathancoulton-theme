<?php

namespace jct\Shopify;

use jct\Shopify\Provider\ProductMetafieldProvider;

class Metafield extends Struct {
    public
        $namespace,
        $key,
        $value,
        $value_type,

        // don't use any of these
        $description,
        $owner_id,
        $created_at,
        $updated_at,
        $owner_resource;

    protected function postProperties() {
        return ['namespace', 'key', 'value', 'value_type'];
    }

    protected function putProperties() {
        return $this->postProperties();
    }


    public static function fromProductMetafieldProvider(ProductMetafieldProvider $metafieldProvider) {
        $metafield = new self();

        $metafield->namespace = $metafieldProvider->getProductMetafieldNamespace();
        $metafield->key = $metafieldProvider->getProductMetafieldKey();
        $metafield->value = $metafieldProvider->getProductMetafieldValue();
        $metafield->value_type = $metafieldProvider->getProductMetafieldValueType();

        return $metafield;
    }
}
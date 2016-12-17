<?php

namespace jct\Shopify;

use jct\Shopify\Provider\MetafieldProvider;
use jct\Util;

class Metafield extends Struct {
    public
        $namespace = 'global',
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
        return array_merge(['id'], $this->postProperties());
    }

    public function useInferredValueType() {
        $this->value_type = is_string($this->value) ? 'string' : 'integer';
    }

}
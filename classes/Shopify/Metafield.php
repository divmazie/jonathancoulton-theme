<?php

namespace jct\Shopify;

use jct\Shopify\Provider\MetafieldProvider;
use jct\Util;

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
        return array_merge(['id'], $this->postProperties());
    }


    public static function fromMetafieldProvider(Struct $parent = null, MetafieldProvider $metafieldProvider) {
        $metafield = new static($parent);

        $metafield->id = $metafieldProvider->getMetafieldID();
        $metafield->namespace = $metafieldProvider->getMetafieldNamespace();
        $metafield->key = $metafieldProvider->getMetafieldKey();
        $metafield->value = $metafieldProvider->getMetafieldValue();
        $metafield->value_type = $metafieldProvider->getMetafieldValueType();

        return $metafield;
    }

    /**
     * @param $metafieldsWithoutID Metafield[]
     * @param $metafieldsWithID Metafield[]
     * @return Metafield[] metafields with id that were NOT ported
     */
    public static function portMetafieldIDs($metafieldsWithoutID, $metafieldsWithID) {
        $idDict = [];
        foreach($metafieldsWithID as $withID) {
            $idDict[$withID->namespace][$withID->key] = $withID->id;
        }

        foreach($metafieldsWithoutID as $noID) {
            $noID->id = @$idDict[$noID->namespace][$noID->key];
            unset($idDict[$noID->namespace][$noID->key]);
        }

        return Util::array_merge_flatten_1L($idDict);
    }
}
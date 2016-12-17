<?php

namespace jct;

use jct\Shopify\Provider\MetafieldProvider;

class MusicStoreMetafieldProvider implements MetafieldProvider {
    const DEFAULT_NAMESPACE = 'global';

    private $key, $value, $namespace, $parentProduct;

    public function __construct(MusicStoreProduct $parentProduct, $key, $value) {
        $this->key = $key;
        $this->value = $value;
        $this->parentProduct = $parentProduct;
        $this->namespace = self::DEFAULT_NAMESPACE;
    }

    public function getMetafieldID() {
        $id = $this->parentProduct->getIDForMetafield($this->namespace, $this->key);
        return $id;
    }


    public function getMetafieldNamespace() {
        return self::DEFAULT_NAMESPACE;
    }

    public function getMetafieldKey() {
        return $this->key;
    }

    public function getMetafieldValue() {
        return $this->value;
    }

    public function getMetafieldValueType() {
        return is_string($this->value) ? 'string' : 'integer';
    }

    public static function getForProduct(MusicStoreProduct $product) {
        $trackNumber = 0;
        if($product instanceof Track) {
            $trackNumber = $product->getTrackNumber();
        }

        $metafields = [
            new MusicStoreMetafieldProvider($product, 'track_number', $product instanceof
                                                                      Track ? $product->getTrackNumber() : 0),
            new MusicStoreMetafieldProvider($product, 'wiki_link', $product->getWikiLink()),
        ];

        if($product instanceof Track) {
            $metafields[] = new MusicStoreMetafieldProvider($product, 'music_link', $product->getMusicLink());
        }

        return $metafields;
    }
}
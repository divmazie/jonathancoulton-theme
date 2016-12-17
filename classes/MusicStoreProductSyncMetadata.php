<?php
namespace jct;

use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\ProductVariant;
use jct\Shopify\Struct;

class MusicStoreProductSyncMetadata {

    const PRODUCT = 'product';
    const OBJECT_ID = 'id';
    const VARIANTS = 'variants';
    const METAFIELDS = 'metafields';
    const IMAGE = 'image';
    const VERSION_HASH = 'version_hash';

    // a hierarchical map of the various quantities we need to track...
    private $trackingArray = [];

    public function processAPIProductReturn(Product $remoteProduct, Product $forLocalProduct) {
        $this->trackingArray[self::PRODUCT][self::OBJECT_ID] = $remoteProduct->id;
        $this->trackingArray[self::PRODUCT][self::VERSION_HASH] = self::versionHash($forLocalProduct);

        foreach($remoteProduct->metafields as $metafield) {
            $this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::OBJECT_ID] =
                $metafield->id;
            $this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::VERSION_HASH] =
                self::versionHash($metafield);
        }
        foreach($remoteProduct->variants as $variant) {
            $this->trackingArray[self::PRODUCT][self::VARIANTS][$variant->sku][self::OBJECT_ID] =
                $variant->id;
        }
    }

    public function getProductID() {
        return @$this->trackingArray[self::PRODUCT][self::OBJECT_ID];
    }

    public function productNeedsUpdate(Product $product) {
        return $this->versionHash($product) !==
               @$this->trackingArray[self::PRODUCT][self::VERSION_HASH];
    }

    public function getIDForMetafield(Metafield $metafield) {
        return @$this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::OBJECT_ID];
    }

    public function getIDForVariant(ProductVariant $variant) {
        return @$this->trackingArray[self::PRODUCT][self::VARIANTS][$variant->sku][self::OBJECT_ID];
    }

    public function metafieldNeedsUpdate(Metafield $metafield) {
        return $this->versionHash($metafield) !==
               @$this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::VERSION_HASH];
    }

    private static function versionHash(Struct $struct) {
        return md5(serialize($struct->postArray()));
    }

}


?>
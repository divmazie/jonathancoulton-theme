<?php
namespace jct;

use jct\Shopify\CustomCollection;
use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\ProductVariant;
use jct\Shopify\Struct;

// beware changing this class name... it's already serialized
class MusicStoreProductSyncMetadata {

    const PRODUCT = 'product';
    const OBJECT_ID = 'id';
    const VARIANTS = 'variants';
    const METAFIELDS = 'metafields';
    const IMAGE = 'image';
    const VERSION_HASH = 'version_hash';

    const COLLECTION = 'collection';


    // a hierarchical map of the various quantities we need to track...
    private $trackingArray = [];

    public function processMusicStoreProductReturn(MusicStoreProduct $localMusicStoreProduct, Product $returnedProduct) {
        $locallyGeneratedShopifyProductObject = $localMusicStoreProduct->getShopifyProduct();

        $this->trackingArray[self::PRODUCT][self::OBJECT_ID] = $returnedProduct->id;
        // we hash on this--there will always be unpredictable differences due to how shopify interprets our response
        // (due to image urls, etc)
        $this->trackingArray[self::PRODUCT][self::VERSION_HASH] =
            self::versionHash($locallyGeneratedShopifyProductObject);

        foreach($returnedProduct->metafields as $metafield) {
            $this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::OBJECT_ID] =
                $metafield->id;
            $this->trackingArray[self::PRODUCT][self::METAFIELDS][$metafield->namespace][$metafield->key][self::VERSION_HASH] =
                self::versionHash($metafield);
        }
        foreach($returnedProduct->variants as $variant) {
            $this->trackingArray[self::PRODUCT][self::VARIANTS][$variant->sku][self::OBJECT_ID] =
                $variant->id;
        }

        $localMusicStoreProduct->setShopifySyncMetadata($this);
    }

    public function processCollectionReturn(MusicStoreCollection $musicStoreAlbum, CustomCollection $returnedCollection) {
        $this->trackingArray[self::COLLECTION][self::OBJECT_ID] = $returnedCollection->id;
        $this->trackingArray[self::COLLECTION][self::VERSION_HASH] =
            $this->versionHash($musicStoreAlbum->getShopifyCustomCollection());

        $musicStoreAlbum->setShopifySyncMetadata($this);
    }

    public function getCustomCollectionID() {
        return @$this->trackingArray[self::COLLECTION][self::OBJECT_ID];
    }

    public function customCollectionHasChanged(MusicStoreCollection $forAlbum) {
        return $this->versionHash($forAlbum->getShopifyCustomCollection()) !==
               @$this->trackingArray[self::COLLECTION][self::VERSION_HASH];
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
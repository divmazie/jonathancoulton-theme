<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/22/15
 * Time: 16:54
 */

namespace jct;

use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\Provider\ImageProvider;
use jct\Shopify\Provider\MetafieldProvider;
use jct\Shopify\Provider\ProductProvider;
use Timber\Post;

abstract class MusicStoreProduct extends JCTPost implements ProductProvider, ImageProvider {
    public $postID;

    const META_SHOPIFY_ID_MAP = 'shopify_id_map';
    const META_WIKI_LINK = 'wiki_link';

    const ID_MAP_ID = 'id';
    const ID_MAP_VARIANT = 'variant';
    const ID_MAP_META = 'meta';
    const ID_MAP_IMAGE = 'image';
    const ID_MAP_HASH = 'shopify_version_hash';

    abstract function syncToStore($shopify);

    abstract function getTitle();

    public function getShopifyID() {
        return @$this->getShopifyIDMap()[self::ID_MAP_ID];
    }

    private function getShopifyIDMap() {
        $map = $this->get_field(self::META_SHOPIFY_ID_MAP);
        if($map) {
            return $map;
        }
        return [];
    }

    private function setShopifyIDMap(Product $product) {
        // track what we learn about shopify's names for things
        // this will allow us to do more effcient updates
        // start with what we have... if we don't hear back about thing
        // assume it's unchanged...
        $map = $this->getShopifyIDMap();
        $map[self::ID_MAP_ID] = $product->id;
        foreach($product->variants as $variant) {
            $map[self::ID_MAP_VARIANT][$variant->sku] = $variant->id;
        }
        foreach($product->metafields as $metafield) {
            $map[self::ID_MAP_META][$metafield->namespace][$metafield->key][self::ID_MAP_ID] = $metafield->id;
        }
        // store some metafield freshness info as well...
        foreach($this->getProductMetafieldProviders() as $metafieldProvider) {
            $map[self::ID_MAP_META][$metafieldProvider->getMetafieldNamespace()]
            [$metafieldProvider->getMetafieldKey()][self::ID_MAP_HASH] =
                self::metafieldProviderFreshnessHash($metafieldProvider);
        }
        if($product->image) {
            $map[self::ID_MAP_IMAGE] = $product->image->id;
        }

        $map[self::ID_MAP_HASH] = $this->getCurrentShopifyVersionHash();

        $this->update(self::META_SHOPIFY_ID_MAP, $map);
    }


    public function metafieldProviderNeedsSync(MetafieldProvider $metafieldProvider) {
        return @$this->getShopifyIDMap()[self::ID_MAP_META][$metafieldProvider->getMetafieldNamespace()]
        [$metafieldProvider->getMetafieldKey()][self::ID_MAP_HASH] !==
               static::metafieldProviderFreshnessHash($metafieldProvider);
    }


    public function getShopifyImageID() {
        return @$this->getShopifyIDMap()[self::ID_MAP_IMAGE];
    }

    public function getVariantID($variantSKU) {
        return $this->getShopifyIDMap()[self::ID_MAP_VARIANT][$variantSKU];
    }

    public function getIDForMetafield($metaNamespace, $metaKey) {
        return @$this->getShopifyIDMap()[self::ID_MAP_META][$metaNamespace][$metaKey][self::ID_MAP_ID];
    }

    private function getShopifySyncedHash() {
        return @$this->getShopifyIDMap()[self::ID_MAP_HASH];
    }

    private function getCurrentShopifyVersionHash() {
        return md5(serialize(Product::fromProductProvider($this)->postArray()));
    }

    public function getShopifyProductType() {
        return ThemeObjectRepository::DEFAULT_SHOPIFY_PRODUCT_TYPE;
    }

    public function getShopifyVendor() {
        return 'Jonathan Coulton';
    }

    public function getProductMetafieldProviders() {
        return MusicStoreMetafieldProvider::getForProduct($this);
    }

    public function getProductMetafieldProvidersToUpdate() {
        return array_filter($this->getProductMetafieldProviders(), function (MetafieldProvider $metafieldProvider) {
            return $this->metafieldProviderNeedsSync($metafieldProvider);
        });
    }

    public function remoteProductResponse(Product $product) {
        $this->setShopifyIDMap($product);
    }

    public function shouldUpdateProduct() {

        var_dump($this->getShopifySyncedHash());
        var_dump($this->getCurrentShopifyVersionHash());
        return $this->getShopifySyncedHash() !== $this->getCurrentShopifyVersionHash();
    }

    public function getWikiLink() {
        $wiki_link = $this->get_field(self::META_WIKI_LINK);
        if(!$wiki_link) {
            $wiki_link = Util::get_theme_option('joco_wiki_base_url') .
                         urlencode(preg_replace('/\s+/', '_', $this->getTitle()));
        }
        return $wiki_link;
    }

    public static function metafieldProviderFreshnessHash(MetafieldProvider $metafieldProvider) {
        return md5(serialize([
                                 $metafieldProvider->getMetafieldKey(), $metafieldProvider->getMetafieldNamespace(),
                                 $metafieldProvider->getMetafieldValue(), $metafieldProvider->getMetafieldValueType(),
                             ]));
    }
}
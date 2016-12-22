<?php

namespace jct;

use jct\Shopify\CustomCollection;
use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\ProductOption;
use jct\Shopify\ProductVariant;

class EverythingProduct implements MusicStoreProduct, MusicStoreCollection {

    const EVERYTHING_SYNC_STORE = 'jct_everything_sync';

    private static $instance = null;

    private final function __construct() {
    }

    public static function getInstance() {
        if(!self::$instance) {
            self::$instance = new EverythingProduct();
        }
        return self::$instance;
    }

    public function getShopifySyncMetadata() {
        return ($sm = get_option(self::EVERYTHING_SYNC_STORE)) ? $sm :
            new MusicStoreProductSyncMetadata();
    }

    public function setShopifySyncMetadata(MusicStoreProductSyncMetadata $syncMetadata) {
        return update_option(self::EVERYTHING_SYNC_STORE, $syncMetadata);
    }

    public function getTitle($withConfigName = null) {
        $themeOpt = Util::get_theme_option('everything_title');
        if(!$themeOpt) {
            $themeOpt = 'Everything';
        }
        return $themeOpt . ($withConfigName ? sprintf(' (%s)', $withConfigName) : '');
    }

    public function getCopy() {
        $copy = Util::get_theme_option('everything_copy');
        if(!$copy) {
            return 'Everything listed below';
        }
        return $copy;
    }

    public function getPrice() {
        return Util::get_theme_option('everything_price');
    }

    /**
     * @return EverythingFetchSyncable[]
     */
    public function getFetchSyncables() {
        return array_map(function ($configName) {
            return new EverythingFetchSyncable($configName, $this);
        }, array_keys(Util::get_encode_types()));
    }

    public function getShopifyProduct() {
        $syncMeta = $this->getShopifySyncMetadata();
        $product = new Product();

        $id = $syncMeta->getProductID();

        $product->id = $id;
        $product->title = $this->getTitle();
        $product->body_html = $this->getCopy();
        $product->product_type = SyncManager::getShopifyProductType();
        $product->vendor = SyncManager::DEFAULT_SHOPIFY_PRODUCT_VENDOR;

        $product->variants = [];
        foreach($this->getFetchSyncables() as $fetchSyncable) {
            $variant = new ProductVariant();
            $variant->title = $fetchSyncable->getConfigName();
            $variant->sku = $fetchSyncable->getShopifyAndFetchSKU();
            $variant->price = $this->getPrice();
            $variant->option1 = $fetchSyncable->getConfigName();
            $variant->id = $syncMeta->getIDForVariant($variant);

            $product->variants[] = $variant;
        }

        $formatOption = new ProductOption();
        $formatOption->name = 'Format';
        $product->options = [$formatOption];

        $wikiLink = new Metafield();
        $wikiLink->key = 'wiki_link';
        $wikiLink->value = Util::get_theme_option('joco_wiki_base_url') . '/Discography';
        $wikiLink->useInferredValueType();
        // if we have it...
        $wikiLink->id = $syncMeta->getIDForMetafield($wikiLink);

        $product->metafields = [$wikiLink];

        return $product;
    }

    public function getShopifyCustomCollection() {
        $syncMeta = $this->getShopifySyncMetadata();

        $collection = new CustomCollection();

        $collection->id = $syncMeta->getCustomCollectionID();
        $collection->title = $this->getTitle();
        $collection->body_html = $this->getCopy();
        $collection->template_suffix = SyncManager::SHOPIFY_COLLECTION_CUSTOM_SUFFIX;

        $collection->image = null;
        $collection->sort_order = 'manual';

        $collection->collects = [['product_id' => $syncMeta->getProductID(), 'sort_value' => 0, 'position' => 0]];

        return $collection;
    }

    public function getShopifyCollectionProducts() {
        return [$this->getShopifyProduct()];
    }


}

<?php

namespace jct;

use FetchApp\API\Currency;
use FetchApp\API\FetchApp;
use jct\Shopify\CustomCollection;
use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\ProductOption;
use jct\Shopify\ProductVariant;
use FetchApp\API\Product as FetchProduct;

class EverythingProduct {

    const EVERYTHING_SYNC_STORE = 'jct_everything_sync';

    public static function getSyncMeta() {
        $sm = get_option(self::EVERYTHING_SYNC_STORE);
        if(!$sm) {
            $sm = new MusicStoreProductSyncMetadata();
        }
        return $sm;
    }

    public static function setSyncMeta(MusicStoreProductSyncMetadata $syncMetadata) {
        return update_option(self::EVERYTHING_SYNC_STORE, $syncMetadata);
    }


    public static function getShopifyAndFetchSKU($configName) {
        return 'jct_everything:' . $configName;
    }

    public static function getTitle($withConfigName = null) {
        return Util::get_theme_option('everything_title') .
               ($withConfigName ? sprintf(' (%s)', $withConfigName) : '');
    }

    public static function getCopy() {
        return Util::get_theme_option('everything_copy');
    }

    public static function getPrice() {
        return Util::get_theme_option('everything_price');
    }

    public static function shouldCreateShopifyProduct() {
        $sm = self::getSyncMeta();
        // e.g. it is blank
        return !(bool)$sm->getProductID();
    }

    public static function shouldUpdateShopifyProduct() {
        $sm = self::getSyncMeta();
        // e.g. it is blank
        return $sm->productNeedsUpdate(self::getShopifyProduct());
    }

    public static function shouldCreateShopifyCollection() {
        $sm = self::getSyncMeta();
        // e.g. it is blank
        return !(bool)$sm->getCustomCollectionID();
    }

    public static function shouldUpdateShopifyCollection() {
        $sm = self::getSyncMeta();
        // e.g. it is blank
        return $sm->collectionNeedsUpdate(self::getShopifyCollection());
    }

    public static function productReturn(Product $returned) {
        $sm = self::getSyncMeta();
        $sm->processGenericProductReturn(self::getShopifyProduct(), $returned);
        self::setSyncMeta($sm);
    }

    public static function collectionReturn(CustomCollection $returned) {
        $sm = self::getSyncMeta();
        $sm->processGenericCollectionReturn(self::getShopifyCollection(), $returned);
        self::setSyncMeta($sm);
    }

    public static function createFetchProducts(FetchApp $fetchApp) {
        return self::fetchProductsRemoteAction($fetchApp, 'POST');
    }

    public static function updateFetchProducts(FetchApp $fetchApp) {
        return self::fetchProductsRemoteAction($fetchApp, 'PUT');
    }

    public static function getShopifyProduct() {
        $syncMeta = self::getSyncMeta();
        $product = new Product();

        $id = $syncMeta->getProductID();

        $product->id = $id;
        $product->title = self::getTitle();
        $product->body_html = self::getCopy();
        $product->product_type = MusicStoreProduct::getShopifyProductType();
        $product->vendor = MusicStoreProduct::DEFAULT_SHOPIFY_PRODUCT_VENDOR;

        $product->variants = [];
        foreach(Util::get_encode_types() as $configName => $encodeOpts) {
            $variant = new ProductVariant();
            $variant->title = $configName;
            $variant->sku = self::getShopifyAndFetchSKU($configName);
            $variant->price = self::getPrice();
            $variant->option1 = $configName;
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

        $product->metafields = [$wikiLink];

        return $product;
    }


    public static function getShopifyCollection() {
        $syncMeta = self::getSyncMeta();
        if(!$syncMeta->getProductID()) {
            throw new JCTException("Attempt to create collection for everything without product");
        }

        $collection = new CustomCollection();

        $collection->id = $syncMeta->getCustomCollectionID();
        $collection->title = self::getTitle();
        $collection->body_html = self::getCopy();
        $collection->template_suffix = Album::ALBUM_SHOPIFY_COLLECTION_CUSTOM_SUFFIX;

        $collection->image = null;
        $collection->sort_order = 'manual';

        $collection->collects = ['product_id' => $syncMeta->getProductID(), 'sort_value' => 0, 'position' => 0];

        return $collection;
    }

    private static function fetchProductsRemoteAction(FetchApp $fetchApp, $verb = 'POST') {
        foreach(Util::get_encode_types() as $configName => $encodeOpts) {
            $product = new FetchProduct();
            $product->setProductID(self::getShopifyAndFetchSKU($configName));
            $product->setSKU(self::getShopifyAndFetchSKU($configName));
            $product->setName(self::getTitle($configName));
            $product->setDescription(self::getCopy());
            $product->setPrice(self::getPrice());
            $product->setCurrency(Currency::USD);


            switch($verb) {
                case 'POST':
                    return $product->create([], self::getFetchAppUrlsArray($configName));
                    break;
                case 'PUT':
                    return $product->update([], self::getFetchAppUrlsArray($configName));
                    break;

                default:
                    throw new JCTException('unknown VERB');
            }
        }
    }

    public static function getFetchAppUrlsArray($forConfig) {
        return Util::array_merge_flatten_1L(array_map(function (Album $album) use ($forConfig) {
            return $album->getAlbumZipConfigByName($forConfig)->getAlbumZip()->getFetchAppUrlsArray();
        }, AlbumZip::getAll()));
    }


}
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
use jct\Shopify\ProductOption;
use jct\Shopify\ProductVariant;

abstract class MusicStoreProductPost extends JCTPost implements MusicStoreProduct {
    public $postID;

    const META_SHOPIFY_SYNC_METADATA = 'shopify_sync_metadata';
    const META_WIKI_LINK = 'wiki_link';


    abstract public function getPrice();

    abstract public function getDownloadStoreTitle();

    abstract public function getDownloadStoreBodyHtml();

    /** @return EncodedAsset[] */
    abstract public function getEncodedAssets();

    /** @return CoverArt */
    abstract public function getCoverArt();

    /**
     * @return MusicStoreProductSyncMetadata
     */
    public function getShopifySyncMetadata() {
        $syncMeta = $this->get_field(self::META_SHOPIFY_SYNC_METADATA);
        if($syncMeta) {
            return $syncMeta;
        }
        return new MusicStoreProductSyncMetadata();
    }

    public function setShopifySyncMetadata(MusicStoreProductSyncMetadata $syncMetadata) {
        $this->update(self::META_SHOPIFY_SYNC_METADATA, $syncMetadata);
    }

    public function getShopifyMetafields() {
        $trackNumber = new Metafield();
        $trackNumber->key = 'track_number';
        $trackNumber->value = $this instanceof Track ? $this->getTrackNumber() : 0;
        $trackNumber->useInferredValueType();

        $wikiLink = new Metafield();
        $wikiLink->key = 'wiki_link';
        $wikiLink->value = $this->getWikiLink();
        $wikiLink->useInferredValueType();

        return [$trackNumber, $wikiLink];
    }


    /** @return Product */
    public function getShopifyProduct() {
        $syncMeta = $this->getShopifySyncMetadata();
        $product = new Product();

        $product->id = $syncMeta->getProductID();
        $product->title = $this->getDownloadStoreTitle();
        $product->body_html = $this->getDownloadStoreBodyHtml();
        $product->product_type = SyncManager::getShopifyProductType();

        $product->vendor = SyncManager::DEFAULT_SHOPIFY_PRODUCT_VENDOR;
        $product->variants = array_map(function (EncodedAsset $assetConfig) use ($syncMeta) {
            $variant = new ProductVariant();
            $variant->title = $assetConfig->getShopifyProductVariantTitle();
            $variant->sku = $assetConfig->getShopifyAndFetchSKU();
            $variant->price = $this->getPrice();
            $variant->option1 = $assetConfig->getShopifyProductVariantTitle();

            $variant->id = $syncMeta->getIDForVariant($variant);

            return $variant;
        }, $this->getEncodedAssets());

        $formatOption = new ProductOption();
        $formatOption->name = 'Format';
        $product->options = [$formatOption];

        $product->images = [$this->getCoverArt()->getShopifyImage()];

        // pair metafields up with their ids
        $product->metafields = array_map(function (Metafield $metafield) use ($syncMeta) {
            $metafield->id = $syncMeta->getIDForMetafield($metafield);
            return $metafield;
        }, $this->getShopifyMetafields());

        return $product;
    }

    public function getWikiLink() {
        $wiki_link = $this->get_field(self::META_WIKI_LINK);
        if(!$wiki_link) {
            $wiki_link = Util::get_theme_option('joco_wiki_base_url') . '/' .
                         urlencode(preg_replace('/\s+/', '_', $this->getTitle()));
        }
        return $wiki_link;
    }
}
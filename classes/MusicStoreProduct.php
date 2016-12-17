<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/22/15
 * Time: 16:54
 */

namespace jct;

use jct\Shopify\Image;
use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\ProductOption;
use jct\Shopify\ProductVariant;

abstract class MusicStoreProduct extends JCTPost {
    public $postID;

    const META_SHOPIFY_SYNC_METADATA = 'shopify_sync_metadata';
    const META_WIKI_LINK = 'wiki_link';

    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Parallel Testing';
    const DEFAULT_SHOPIFY_PRODUCT_VENDOR = 'Jonathan Coulton';


    abstract public function getPrice();

    abstract public function getDownloadStoreTitle();

    abstract public function getDownloadStoreBodyHtml();

    /** @return EncodedAssetConfig[] */
    abstract public function getEncodedAssetConfigs();

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
        $product->product_type = self::DEFAULT_SHOPIFY_PRODUCT_TYPE;
        $product->vendor = self::DEFAULT_SHOPIFY_PRODUCT_VENDOR;
        $product->variants = array_map(function (EncodedAssetConfig $assetConfig) use ($syncMeta) {
            $variant = new ProductVariant();
            $variant->title = $assetConfig->getConfigName();
            $variant->sku = $assetConfig->getShopifyProductVariantSKU();
            $variant->price = $this->getPrice();
            $variant->option1 = $assetConfig->getConfigName();

            $variant->id = $syncMeta->getIDForVariant($variant);

            return $variant;
        }, $this->getEncodedAssetConfigs());

        $formatOption = new ProductOption();
        $formatOption->name = 'Format';
        $product->options = [$formatOption];

        $productImage = new Image();
        $productImage->src = $this->getCoverArt()->getURL();

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
            $wiki_link = Util::get_theme_option('joco_wiki_base_url') .
                         urlencode(preg_replace('/\s+/', '_', $this->getTitle()));
        }
        return $wiki_link;
    }
}
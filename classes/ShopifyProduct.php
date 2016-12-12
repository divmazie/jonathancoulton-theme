<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/22/15
 * Time: 16:54
 */

namespace jct;

use Timber\Post;

abstract class ShopifyProduct extends JCTPost {
    public $postID;

    const META_SHOPIFY_ID = 'shopify_id';
    const META_SHOPIFY_VARIANT_IDS = 'shopify_variant_ids';
    const META_SHOPIFY_VARIANT_SKUS = 'shopify_variant_skus';
    const META_WIKI_LINK = 'wiki_link';

    abstract function syncToStore($shopify);

    abstract function getTitle();

    public function getShopifyId() {
        return $this->get_field(self::META_SHOPIFY_ID);
    }

    public function setShopifyId($id) {
        $this->update(self::META_SHOPIFY_ID, $id);
    }

    public function getShopifyVariantIds() {
        return $this->get_field(self::META_SHOPIFY_VARIANT_IDS);
    }

    public function setShopifyVariantIds(array $ids) {
        $this->update(self::META_SHOPIFY_VARIANT_IDS, $ids);
    }

    public function getShopifyVariantSkus() {
        return $this->get_field(self::META_SHOPIFY_VARIANT_SKUS);
    }

    public function setShopifyVariantSkus(array $skus) {
        $this->update(self::META_SHOPIFY_VARIANT_SKUS, $skus);
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
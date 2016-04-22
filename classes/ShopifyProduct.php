<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/22/15
 * Time: 16:54
 */

namespace jct;

abstract class ShopifyProduct {
    public $postID;
    public $shopify_id, $shopify_variant_ids, $shopify_variant_skus;

    abstract function syncToStore($shopify);
    abstract function getTitle();

    public function getShopifyId() {
        if (!isset($this->shopify_id)) $this->shopify_id = get_post_meta($this->postID,'shopify_id',false)[0];
        return $this->shopify_id;
    }

    public function setShopifyId($id) {
        if (update_post_meta($this->postID,'shopify_id',$id)) {
            $this->shopify_id = $id;
        }
    }

    public function getShopifyVariantIds() {
        if (!isset($this->shopify_variant_ids)) $this->shopify_variant_ids = unserialize(get_post_meta($this->postID,'shopify_variant_ids',false)[0]);
        return $this->shopify_variant_ids;
    }

    public function setShopifyVariantIds($ids) {
        if (update_post_meta($this->postID,'shopify_variant_ids',serialize($ids))) {
            $this->shopify_variant_ids = $ids;
        }
    }

    public function getShopifyVariantSkus() {
        if (!isset($this->shopify_variant_skus)) $this->shopify_variant_skus = unserialize(get_post_meta($this->postID,'shopify_variant_skus',false)[0]);
        return $this->shopify_variant_skus;
    }

    public function setShopifyVariantSkus($skus) {
        if (update_post_meta($this->postID,'shopify_variant_skus',serialize($skus))) {
            $this->shopify_variant_skus = $skus;
        }
    }

    public function getWikiLink() {
        $wiki_link = get_field('wiki_link',$this->postID);
        if (!$wiki_link) {
            $wiki_link = get_field('joco_wiki_base_url','options').urlencode(preg_replace('/\s+/', '_',$this->getTitle()));
        }
        return $wiki_link;
    }
}
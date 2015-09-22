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

    public function getShopifyId() {
        return $this->shopify_id;
    }

    public function setShopifyId($id) {
        if (update_post_meta($this->postID,'shopify_id',$id)) {
            $this->shopify_id = $id;
        }
    }

    public function getShopifyVariantIds() {
        return $this->shopify_variant_ids;
    }

    public function setShopifyVariantIds($ids) {
        if (update_post_meta($this->postID,'shopify_variant_ids',serialize($ids))) {
            $this->shopify_variant_ids = $ids;
        }
    }

    public function getShopifyVariantSkus() {
        return $this->shopify_variant_skus;
    }

    public function setShopifyVariantSkus($skus) {
        if (update_post_meta($this->postID,'shopify_variant_skus',serialize($skus))) {
            $this->shopify_variant_skus = $skus;
        }
    }
}
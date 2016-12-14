<?php

namespace jct\Shopify;

use jct\Shopify\Provider\ProductVariantProvider;

class ProductVariant extends Struct {
    public
        // POST and PUT
        $product_id,
        $title,
        $price,
        $sku,
        $position,
        $option1,

        // default values for unused
        $grams = 0,
        $inventory_policy = 'deny',
        $compare_at_price = null,
        $fulfillment_service = 'manual',
        $inventory_management = null,
        $option2 = null,
        $option3 = null,
        $taxable = false,
        $barcode = null,
        $image_id = null,
        $inventory_quantity = 1,
        $weight = 0,
        $weight_unit = 'lb',
        $old_inventory_quantity = 1,
        $requires_shipping = false,

        // date time
        $created_at,
        $updated_at;


    public static function fromProductVariantProvider(ProductVariantProvider $variantProvider) {
        $variant = new self();

        $variant->title = $variantProvider->getProductVariantTitle();
        $variant->price = $variantProvider->getProductVariantPrice();
        $variant->sku = $variantProvider->getProductVariantSKU();
        $variant->option1 = $variantProvider->getProductVariantOption1();
        $variant->option2 = $variantProvider->getProductVariantOption2();
        $variant->option3 = $variantProvider->getProductVariantOption3();

        return $variant;
    }

}

?>
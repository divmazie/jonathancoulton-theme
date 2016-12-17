<?php

namespace jct\Shopify;

class ProductVariant extends Struct {
    public
        // POST and PUT
        $product_id,
        $title,
        $price,
        $sku,
        $option1,

        // default values or unused
        $grams = 0,
        $inventory_policy = 'deny',
        $compare_at_price = null,
        $fulfillment_service = 'manual',
        $inventory_management = null,
        $option2 = null,
        $option3 = null,
        $taxable = true,
        $barcode = null,
        $image_id = null,
        $inventory_quantity = 1,
        $weight = 0,
        $weight_unit = 'lb',
        $old_inventory_quantity = 1,
        $requires_shipping = false,

        // it infers this
        $position,

        // date time
        $created_at,
        $updated_at;

    protected function postProperties() {
        return ['product_id', 'sku', 'title', 'price', 'option1', 'option2', 'option3', 'taxable', 'require_shipping'];
    }

    protected function putProperties() {
        return array_merge(['id'], $this->postProperties());
    }

}

?>
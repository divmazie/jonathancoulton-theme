<?
namespace jct\Shopify;

class ProductImage extends Struct {
    public
        // int
        $product_id,
        // url
        $src,

        // date time
        $created_at,
        $updated_at,

        // programmatic--should increase
        $position,

        // default
        $variant_ids = [];
}

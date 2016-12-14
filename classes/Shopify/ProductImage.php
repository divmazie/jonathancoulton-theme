<?
namespace jct\Shopify;

use jct\Shopify\Provider\ProductImageProvider;

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

    protected function postProperties() {
        return ['src'];
    }

    protected function putProperties() {
        return ['id', 'src'];
    }

    public static function fromProductImageProvider(ProductImageProvider $imageProvider) {
        $image = new self();

        $image->src = $imageProvider->getProductImageSourceUrl();

        return $image;
    }
}

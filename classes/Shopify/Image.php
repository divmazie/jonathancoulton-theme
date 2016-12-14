<?
namespace jct\Shopify;

use jct\Shopify\Provider\ImageProvider;

class Image extends Struct {


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

    public static function fromImageProvider(Struct $parent, ImageProvider $imageProvider) {
        $image = new static($parent);

        $image->src = $imageProvider->getProductImageSourceUrl();

        return $image;
    }
}

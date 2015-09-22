<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/21/15
 * Time: 14:13
 */

namespace jct;


class Shopify {
    private $apiKey, $apiPassword, $handle;
    private $fetch;
    private $allProducts;

    public function __construct($apiKey, $apiPassword, $handle) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->handle = $handle;
        $this->fetch = new \FetchApp\API\FetchApp();
        $this->fetch->setAuthenticationKey(get_field('fetch_key','options'));
        $this->fetch->setAuthenticationToken(get_field('fetch_token','options'));
    }

    static function sku($album,$track,$format) {
        // replace spaces with underscore
        $album = preg_replace('/\s/u', '_', $album);
        $track = preg_replace('/\s/u', '_', $track);
        $format = preg_replace('/\s/u', '_', $format);
        // remove non ascii alnum_ with
        $album = preg_replace('/[^\da-z_]/i', '', $album);
        $track = preg_replace('/[^\da-z_]/i', '', $track);
        $format = preg_replace('/[^\da-z_]/i', '', $format);

        // track number underscore track title underscore short hash dot extension
        return strtolower(sprintf('%s-%s:%s', $album, $track, $format));
    }

    public function getAllProducts() {
        if (isset($this->allProducts)) {
            return $this->allProducts;
        } else {
            return $this->forceGetAllProducts();
        }
    }

    public function forceGetAllProducts() {
        $response = $this->makeCall("admin/products",'GET',array('product_type' => 'Music download'));
        $this->allProducts = $response->products;
        return $this->allProducts;
    }

    public function productExists($product_id) {
        $products = $this->getAllProducts();
        if (is_array($products)) {
            foreach ($products as $product) {
                if ($product->id == $product_id) {
                    return true;
                }
            }
        }
        return false;
    }

    public function makeCall($resource, $requestType = "GET", $args=array()) {
        $url = "https://".$this->apiKey.":".$this->apiPassword."@".$this->handle.".myshopify.com/".$resource.".json";
        if ($requestType == "GET") {
            $first = true;
            foreach ($args as $key => $val) {
                $key = urlencode($key);
                $val = urlencode($val);
                if ($first) {
                    $url .= "?$key=$val";
                } else {
                    $url .= "&$key=$val";
                }
                $first = false;
            }
        }
        $data_string = json_encode($args);
        //return $data_string;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($requestType != "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string),
                    'Expect:'
                ]
            );
        }
        $response = curl_exec($ch);
        return json_decode($response);
    }

    public function createProduct($object) {
        $args = $this->getProductArgs($object,false);
        $response = $this->makeCall("admin/products","POST",$args);
        if ($response->product->id) {
            $object->setShopifyId($response->product->id);
            $variant_ids = array();
            $variant_skus = array();
            foreach ($response->product->variants as $variant) {
                $variant_ids[$variant->option1] = $variant->id;
                $variant_skus[$variant->option1] = $variant->sku;
            }
            $object->setShopifyVariantIds($variant_ids);
            $object->setShopifyVariantSkus($variant_skus);
        }
        $this->forceGetAllProducts();
        return $response;
    }

    public function updateProduct($object) {
        $timber_post = new \TimberPost($object->getPostID());
        $shopify_id = $object->getShopifyId();
        $shopify_product = "";
        foreach ($this->getAllProducts() as $product) {
            if ($product->id == $shopify_id) {
                $shopify_product = $product;
                break;
            }
        }
        if (strtotime($product->updated_at) < strtotime($timber_post->get_modified_time())) {
            $args = $this->getProductArgs($object, true);
            $response = $this->makeCall("admin/products/$shopify_id", "PUT", $args);
            return $response;
        }
    }

    public function getProductArgs($object,$update) {
        $title = "";
        $body = "";
        $image = "";
        $var_vars = array();
        //die(get_class($object));
        switch (get_class($object)) {
            case "jct\\Album":
                $title = $object->getAlbumTitle()." (Full Album)";
                $body = $object->getAlbumTitle()." (Full Album) by ".$object->getAlbumArtist()." ".$object->getAlbumYear();
                $image = base64_encode(file_get_contents($object->getAlbumArtObject()->getPath()));
                foreach ($object->getAllChildZips() as $zip) {
                    $var_vars[] = array(
                        'sku' => $this::sku($object->getAlbumTitle(),"full album",$zip->getEncodeLabel()),
                        'option1' => $zip->getEncodeLabel(),
                        'price' => strval($object->getAlbumPrice()),
                    );
                }
                break;
            case "jct\\Track":
                $title = $object->getTrackTitle();
                $body = "From ".$object->getAlbum()->getAlbumTitle()." by ".$object->getTrackArtist()." ".$object->getTrackYear();
                $image = base64_encode(file_get_contents($object->getTrackArtObject()->getPath()));
                foreach ($object->getAllChildEncodes() as $encode) {
                    $var_vars[] = array(
                        'sku' => $this::sku($object->getAlbum()->getAlbumTitle(),$object->getTrackTitle(),$encode->getEncodeLabel()),
                        'option1' => $encode->getEncodeLabel(),
                        'price' => strval($object->getTrackPrice()),
                    );
                }
                break;
        }
        $variants = array();
        foreach ($var_vars as $vars) {
            $v = array(
                'option1' => $vars['option1'],
                'price' => $vars['price'],
                'sku' => $vars['sku'],
                'taxable' => false,
                'requires_shipping' => false
            );
            if ($update) {
                $v['id'] = $object->getShopifyVariantIds()[$vars['option1']];
                $v['sku'] = $object->getShopifyVariantSkus()[$vars['option1']];
            }
            $variants[] = $v;
        }
        $args = array("product" => array(
            'title' => $title,
            'body_html' => $body,
            'vendor' => "Jonathan Coulton",
            'product_type' => 'Music download',
            'images' => array(
                array('attachment' => $image)
            ),
            'variants' => $variants
        ));
        if ($update) {
            $args['product']['id'] = $object->getShopifyId();
        }
        return $args;
    }

    public function deleteUnusedProducts($allAlbums = false) {
        $this->forceGetAllProducts();
        if (!$allAlbums) {
            $allAlbums = \jct\Album::getAllAlbums();
        }
        $usedIds = array();
        foreach ($allAlbums as $album) {
            $usedIds[] = $album->getShopifyId();
            foreach ($album->getAlbumTracks() as $track) {
                $usedIds[] = $track->getShopifyId();
            }
        }
        foreach ($this->allProducts as $product) {
            if (!in_array($product->id,$usedIds)) {
                $this->makeCall("admin/products/$product->id","DELETE");
            }
        }
    }

    public function getFetchFiles() {
        try{
            $files = $this->fetch->getFiles(); // Grabs all files
            return $files;
        }
        catch (Exception $e){
            // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
            return $e->getMessage();
        }
    }

}
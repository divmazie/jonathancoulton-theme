<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/21/15
 * Time: 14:13
 */

namespace jct;
use FetchApp\API\Currency;
use FetchApp\API\FetchApp;
use FetchApp\API\Product;


class Shopify {
    private $apiKey, $apiPassword, $handle;
    private $allProducts;
    private $fetch, $allFetchProducts, $allFetchFiles;

    public function __construct($apiKey, $apiPassword, $handle) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->handle = $handle;
        $this->fetch = new FetchApp();
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

    public function syncProduct($object) {
        $response = false;
        if (!$object->getShopifyId() || !$this->productExists($object->getShopifyId())) {
            $response = $this->createProduct($object);
        } else {
            $response = $this->updateProduct($object);
        }
        if ($response && is_array($response->product->variants)) {
            $object_variants = array();
            $title = "";
            switch (get_class($object)) {
                case "jct\\Album": $object_variants = $object->getAllChildZips(); $title = $object->getAlbumTitle(); break;
                case "jct\\Track": $object_variants = $object->getAllChildEncodes(); $title = $object->getTrackTitle(); break;
            }
            $missing_files = array();
            foreach ($response->product->variants as $variant) {
                if ($missing = $this->syncFetchProduct($variant->sku,$title." ".$variant->option1,$variant->price,$object_variants[$variant->option1]->getFileAssetFileName())) {
                    $missing_files[] = array('format'=>$variant->option1,'filename'=>$missing);
                }
            }
        }
        return array('missing_files'=>$missing_files,'response'=>$response);
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
        $wp_time = strtotime($timber_post->post_modified);
        if (get_class($object)=="jct\\Track") {
            $timber_post = new \TimberPost($object->getAlbum()->getPostID());
            $wp_time = max($wp_time,strtotime($timber_post->post_modified));
        }
        if (strtotime($shopify_product->updated_at) < $wp_time) {
            $args = $this->getProductArgs($object, true);
            $response = $this->makeCall("admin/products/$shopify_id", "PUT", $args);
            return $response;
        } else {
            $fake_response = new \stdClass();
            $fake_response->product = $shopify_product;
            return $fake_response;
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
                        'metafields' => array(array(
                            'key'=>'filename','value_type'=>'string','namespace'=>'filenames',
                            'value'=>$zip->getFilename()))
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
                        'metafields' => array(array(
                            'key'=>'filename','value_type'=>'string','namespace'=>'filenames',
                            'value'=>$encode->getFilename()))
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
        if (isset($this->allFetchFiles)) {
            return $this->allFetchFiles;
        }
        try{
            $fetch_files = $this->fetch->getFiles(); // Grabs all files
            return $fetch_files;
            /*
            $fetch_files_array = array();
            foreach ($fetch_files as $file) {
                $fetch_files_array[(int) $file->getFileID()] = (string) $file->getFileName();
            }
            $this->allFetchFiles = $fetch_files_array;
            return $this->allFetchFiles;
            */
        }
        catch (Exception $e){
            // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
            return $e->getMessage();
        }
    }

    public function getFetchProducts() {
        if (isset($this->allFetchProducts)) {
            return $this->allFetchProducts;
        }
        return $this->forceGetFetchProducts();
    }

    public function forceGetFetchProducts() {
        try{
            $this->allFetchProducts = $this->fetch->getProducts(); // Grabs all products (potentially HUGE!)
            return $this->allFetchProducts;
        }
        catch (Exception $e){
            // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
            return $e->getMessage();
        }
    }

    public function syncFetchProduct($sku,$name,$price,$filename) {
        $fetch_product = false;
        foreach ($this->getFetchProducts() as $product) {
            if ((string) $product->getSKU() == $sku) {
                $fetch_product = $product;
            }
        }
        $files = array();
        if (!$fetch_product) {
            $fetch_product = new Product();
            $fetch_product->setSKU($sku);
            $fetch_product->setName($name);
            $fetch_product->setPrice($price);
            $fetch_product->setCurrency(Currency::USD);
            if ($file = $this->getFetchFile($filename)) {
                $files[] = $file;
            }
            try {
                $fetch_product->create($files, false);
            } catch (Exception $e) {
                return $e->getMessage();
            }
        } else {
            $file_object = $this->getFetchFile($filename);
            if ($name != (string) $fetch_product->getName()
                    || strval($price) != (string) $fetch_product->getPrice()
                    || ($file_object && $file_object != $fetch_product->getFiles()[0]) ) {
                $fetch_product->setName($name);
                $fetch_product->setPrice($price);
                if ($file = $file_object) {
                    $files[] = $file;
                }
                try {
                    $fetch_product->update($files);
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }
        }
        if (count($files)<1) {
            return $filename;
        } else {
            return false;
        }
    }

    public function getFetchFile($filename) {
        foreach ($this->getFetchFiles() as $file) {
            if ((string) $file->getFileName() == $filename) {
                return $file;
            }
        }
        return false;
    }

    public function deleteUnusedFetchProducts($allAlbums = false) {
        $this->forceGetFetchProducts();
        $used_skus = array();
        foreach ($allAlbums as $album) {
            $used_skus = array_merge($used_skus,array_values($album->getShopifyVariantSkus()));
            foreach ($album->getAlbumTracks() as $track) {
                $used_skus = array_merge($used_skus,array_values($track->getShopifyVariantSkus()));
            }
        }
        foreach ($this->getFetchProducts() as $product) {
            if (!in_array((string) $product->getSKU(),$used_skus)) {
                $product->delete();
            }
        }
    }

    public function getUnusedFetchFiles() { // Must be called after all products are synced
        $this->forceGetFetchProducts();
        $unused = array();
        foreach ($this->getFetchFiles() as $file) {
            $matching_product = false;
            foreach ($this->getFetchProducts() as $product) {
                if (in_array($file,$product->getFiles())) {
                    $matching_product = $product;
                }
            }
            if (!$matching_product) {
                $unused[] = (string) $file->getFileName();
            }
        }
        return $unused;
    }

}
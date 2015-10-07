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
    private $allProducts, $allCollections;
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

    public function getAllCollections() {
        if (isset($this->allCollections)) {
            return $this->allCollections;
        } else {
            return $this->forceGetAllCollections();
        }
    }

    public function forceGetAllCollections() {
        $response = $this->makeCall('admin/custom_collections');
        $this->allCollections = $response->custom_collections;
        return $this->allCollections;
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

    public function collectionExists($collection_id) {
        $collections = $this->getAllCollections();
        if (is_array($collections)) {
            foreach ($collections as $collection) {
                if ($collection->id == $collection_id) {
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
        $missing_files = array();
        if ($response && is_array($response->product->variants)) {
            $object_variants = array();
            $title = "";
            switch (get_class($object)) {
                case "jct\\Album": $object_variants = $object->getAllChildZips(); $title = $object->getAlbumTitle(); break;
                case "jct\\Track": $object_variants = $object->getAllChildEncodes(); $title = $object->getTrackTitle(); break;
            }
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
            $metafields = $this->makeCall('admin/products/'.$shopify_id.'/metafields');
            foreach ($metafields->metafields as $metafield) {
                switch ($metafield->key) {
                    case 'track_number':
                        $track_number = get_class($object)=="jct\\Track"?$object->getTrackNumber():0;
                        if ($metafield->value != $track_number) {
                            $args = array('metafield' => array('id' => $metafield->id, 'value' => $track_number, 'value_type' => 'string'));
                            $this->makeCall('admin/metafields/'.$metafield->id,'PUT',$args);
                        }
                        break;
                    case 'wiki_link':
                        $wiki_link = $object->getWikiLink();
                        if ($metafield->value != $wiki_link) {
                            $args = array('metafield' => array('id' => $metafield->id, 'value' => $wiki_link, 'value_type' => 'string'));
                            $this->makeCall('admin/metafields/'.$metafield->id,'PUT',$args);
                        }
                        break;
                    default: break;
                }
            }
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
        $wiki_link = $object->getWikiLink();
        $args = array("product" => array(
            'title' => $title,
            'body_html' => $body,
            'vendor' => "Jonathan Coulton",
            'product_type' => 'Music download',
            'images' => array(
                array('attachment' => $image)
            ),
            //'metafields' => array(
                //array('key'=>'track_number','value_type'=>'string','namespace'=>'global',
                    //'value'=>get_class($object)=="jct\\Track"?$object->getTrackNumber():0),
                //array('key'=>'wiki_link','value_type'=>'string','namespace'=>'global',
                    //'value'=>$wiki_link)),
            'variants' => $variants
        ));
        if ($update) {
            $args['product']['id'] = $object->getShopifyId();
        } else {
            $args['product']['metafields'] = array(
                array('key'=>'track_number','value_type'=>'string','namespace'=>'global',
                    'value'=>get_class($object)=="jct\\Track"?$object->getTrackNumber():0),
                array('key'=>'wiki_link','value_type'=>'string','namespace'=>'global',
                    'value'=>$wiki_link));
        }
        return $args;
    }

    public function syncAlbumCollection($album,$ids) {
        $image = base64_encode(file_get_contents($album->getAlbumArtObject()->getPath()));
        $collects = array();
        foreach ($ids as $num => $id) {
            $collects[] = array('product_id' => $id, 'sort_value' => $num);
        }
        $args = array('custom_collection'=>array(
            'title' => $album->getAlbumTitle(),
            'body_html' => $album->getAlbumYear(),
            'sort_order' => 'manual',
            //'image' => array('attachment' => $image),
            //'metafields' => array(array(
                //'key'=>'album_collection','value_type'=>'string','namespace'=>'global',
                //'value'=>'true')),
            'collects' => $collects
        ));
        $collection_id = $album->getShopifyCollectionId();
        if ($collection_id && $this->collectionExists($collection_id)) {
            $collect_dict = array();
            $existing_collects = $this->makeCall('admin/collects','GET',array('collection_id'=>$collection_id));
            foreach ($existing_collects->collects as $collect) {
                $collect_dict[$collect->product_id] = $collect->id;
                if (!in_array($collect->product_id,$ids)) {
                    $this->makeCall('admin/collects/'.$collect->product_id,"DELETE");
                }
            }
            foreach ($collects as $key => $collect) {
                if ($collect_dict[$collect['product_id']]) {
                    $collects[$key]['id'] = $collect_dict[$collect['product_id']];
                }
            }
            //$args['custom_collection']['image'] = array('attachment' => $image);
            $args['custom_collection']['collects'] = $collects;
            $args['custom_collection']['id'] = $collection_id;
            $response = $this->makeCall('admin/custom_collections/'.$collection_id,'PUT',$args);
            return $response;
        } else {
            $args['custom_collection']['image'] = array('attachment' => $image);
            $args['custom_collection']['metafields'] = array(array(
                'key'=>'album_collection','value_type'=>'string','namespace'=>'global',
                'value'=>'true'));
            $response = $this->makeCall('admin/custom_collections','POST',$args);
            if ($response->custom_collection->id) {
                $album->setShopifyCollectionId($response->custom_collection->id);
            }
            return $response;
        }
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
            if ($file_object // Only update Fetch product if file asset is ready to deliver
                    && ($name != (string) $fetch_product->getName()
                        || strval($price) != (string) $fetch_product->getPrice()
                        || $file_object != $fetch_product->getFiles()[0]) ) {
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
            $filename = (string) $file->getFileName();
            $matching_product = false;
            foreach ($this->getFetchProducts() as $product) {
                foreach ($product->getFiles() as $product_file) {
                    if ($filename == (string) $product_file->getFileName()) {
                        $matching_product = true;
                    }
                }
            }
            if (!$matching_product) {
                $unused[] = $filename;
            }
        }
        return $unused;
    }

    public function getStoreContext() {
        $context = array();
        $categories = get_field('store_categories','options');
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if ($category['display']) {
                    $html_name = htmlentities(strtolower(preg_replace('/\s+/', '_',$category['display_name'])));
                    if ($category['shopify_type'] == 'Music download') {
                        $albums = array();
                        foreach ($this->getAllCollections() as $collection) {
                            $metafields = $this->makeCall('admin/custom_collections/' . $collection->id . '/metafields');
                            foreach ($metafields->metafields as $metafield) {
                                if ($metafield->key == 'album_collection' && $metafield->value) {
                                    $products = $this->makeCall('admin/products', 'GET', array('collection_id' => $collection->id));
                                    $products_context = array();
                                    foreach ($products->products as $product) {
                                        $metafields = $this->makeCall('admin/products/' . $product->id . '/metafields');
                                        $metafields_context = array();
                                        $track_number = 0;
                                        foreach ($metafields->metafields as $field) {
                                            $metafields_context[$field->key] = $field->value;
                                            if ($field->key == "track_number") {
                                                $track_number = $field->value;
                                            }
                                        }
                                        $product->metafields = $metafields_context;
                                        $products_context[$track_number] = $product;
                                    }
                                    ksort($products_context);
                                    $albums[] = array('collection' => $collection, 'products' => $products_context);
                                }
                            }
                        }
                        $products = $albums;
                    } else {
                        $products = $this->makeCall("admin/products", 'GET', array('product_type' => $category['shopify_type']));
                        $products = $products->products;
                    }
                    $context[] = array('name' => $category['display_name'], 'html_name' => $html_name, 'shopify_type' => $category['shopify_type'], 'products' => $products);
                }
            }
        }
        return $context;
    }

}
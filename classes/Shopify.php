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
    private $last_api_call_time;

    public function __construct($apiKey, $apiPassword, $handle) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->handle = $handle;
        $this->fetch = new FetchApp();
        $this->fetch->setAuthenticationKey(get_field('fetch_key', 'options'));
        $this->fetch->setAuthenticationToken(get_field('fetch_token', 'options'));
    }

    static function sku($album, $track, $format) {
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
        if(isset($this->allProducts)) {
            return $this->allProducts;
        } else {
            return $this->forceGetAllProducts();
        }
    }

    public function forceGetAllProducts() {
        $response = $this->makeCall('admin/products/count', 'GET', ['product_type' => 'Music download']);
        $num_products = $response->count;
        $products = [];
        for($i = 0; $i < $num_products / 250; $i++) {
            $response = $this->makeCall("admin/products", 'GET', [
                'product_type' => 'Music download', 'limit' => 250, 'page' => $i + 1,
            ]);
            $products = array_merge($products, $response->products);
        }
        $this->allProducts = $products;
        return $this->allProducts;
    }

    public function getAllCollections() {
        if(isset($this->allCollections)) {
            return $this->allCollections;
        } else {
            return $this->forceGetAllCollections();
        }
    }

    public function forceGetAllCollections() {
        $collections = [];
        $response =
            $this->makeCall('admin/custom_collections', 'GET', ['limit' => 250]); // This will cause problems if we have >250 albums
        foreach($response->custom_collections as $collection) {
            $album_collection = false;
            $metafields = $this->makeCall("admin/custom_collections/$collection->id/metafields");
            foreach($metafields->metafields as $metafield) {
                if($metafield->key == 'album_collection') {
                    $album_collection = true;
                }
            }
            if($album_collection) {
                $collections[] = $collection;
            }
        }
        $this->allCollections = $collections;
        return $this->allCollections;
    }

    public function productExists($product_id) {
        $products = $this->getAllProducts();
        if(is_array($products)) {
            foreach($products as $product) {
                if($product->id == $product_id) {
                    return true;
                }
            }
        }
        return false;
    }

    public function collectionExists($collection_id) {
        $collections = $this->getAllCollections();
        if(is_array($collections)) {
            foreach($collections as $collection) {
                if($collection->id == $collection_id) {
                    return true;
                }
            }
        }
        return false;
    }

    public function makeCall($resource, $requestType = "GET", $args = []) {
        $url = "https://" . $this->apiKey . ":" . $this->apiPassword . "@" . $this->handle . ".myshopify.com/" .
               $resource . ".json";
        if($requestType == "GET") {
            $first = true;
            foreach($args as $key => $val) {
                $key = urlencode($key);
                $val = urlencode($val);
                if($first) {
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        if($requestType != "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                               'Content-Type: application/json',
                               'Content-Length: ' . strlen($data_string),
                               'Expect:',
                           ]
            );
        }
        if($this->last_api_call_time == time()) {
            usleep(500000);
        }
        $this->last_api_call_time = time();
        $response = curl_exec($ch);
        return json_decode($response);
    }

    public function syncProduct($object) {
        $response = false;
        if(!$object->getShopifyId() || !$this->productExists($object->getShopifyId())) {
            $response = $this->createProduct($object);
        } else {
            $response = $this->updateProduct($object);
        }
        $missing_files = [];
        if($response && is_array($response->product->variants)) {
            $object_variants = [];
            $title = "";
            switch(get_class($object)) {
                case "jct\\Album":
                    $object_variants = $object->getAllChildZips();
                    $title = $object->getAlbumTitle();
                    break;
                case "jct\\Track":
                    $object_variants = $object->getAllChildEncodes();
                    $title = $object->getTrackTitle();
                    break;
            }
            foreach($response->product->variants as $variant) {
                $fetch_name = $title . " " . $variant->option1;
                $aws_links = [$object_variants[$variant->option1]->getAwsUrl()];
                if($missings = $this->syncFetchProduct($variant->sku, $fetch_name, $variant->price, $aws_links)) {
                    foreach($missings as $missing) {
                        $missing_files[] = ['fetch_name' => $fetch_name, 'url' => $missing];
                    }
                }
            }
        }
        return ['missing_files' => $missing_files, 'response' => $response];
    }

    public function createProduct($object) {
        $args = $this->getProductArgs($object, false);
        $response = $this->makeCall("admin/products", "POST", $args);
        if($response->product->id) {
            $object->setShopifyId($response->product->id);
            $variant_ids = [];
            $variant_skus = [];
            foreach($response->product->variants as $variant) {
                $variant_ids[$variant->option1] = $variant->id;
                $variant_skus[$variant->option1] = $variant->sku;
            }
            $object->setShopifyVariantIds($variant_ids);
            $object->setShopifyVariantSkus($variant_skus);
        }
        //$this->forceGetAllProducts();
        return $response;
    }

    public function updateProduct($object) {
        $timber_post = new \TimberPost($object->getPostID());
        $shopify_id = $object->getShopifyId();
        $shopify_product = "";
        foreach($this->getAllProducts() as $product) {
            if($product->id == $shopify_id) {
                $shopify_product = $product;
                break;
            }
        }
        $wp_time = strtotime($timber_post->post_modified);
        if(get_class($object) == "jct\\Track") {
            $timber_post = new \TimberPost($object->getAlbum()->getPostID());
            $wp_time = max($wp_time, strtotime($timber_post->post_modified));
        }
        if(strtotime($shopify_product->updated_at) < $wp_time) {
            $args = $this->getProductArgs($object, true);
            $response = $this->makeCall("admin/products/$shopify_id", "PUT", $args);
            $metafields = $this->makeCall('admin/products/' . $shopify_id . '/metafields');
            foreach($metafields->metafields as $metafield) {
                switch($metafield->key) {
                    case 'track_number':
                        $track_number = get_class($object) == "jct\\Track" ? $object->getTrackNumber() : 0;
                        $this->updateMetafield($metafield, $track_number);
                        break;
                    case 'wiki_link':
                        $wiki_link = $object->getWikiLink();
                        $this->updateMetafield($metafield, $wiki_link);
                        break;
                    case 'music_link':
                        $music_link = $object->getMusicLink();
                        $this->updateMetafield($metafield, $music_link);
                        break;
                    default:
                        break;
                }
            }
            return $response;
        } else {
            $fake_response = new \stdClass();
            $fake_response->product = $shopify_product;
            return $fake_response;
        }
    }

    public function updateMetafield($old_metafield, $new_metafield_value) {
        if($old_metafield->value != $new_metafield_value) {
            $args = [
                'metafield' => [
                    'id' => $old_metafield->id, 'value' => $new_metafield_value, 'value_type' => 'string',
                ],
            ];
            $this->makeCall('admin/metafields/' . $old_metafield->id, 'PUT', $args);
        }
    }

    public function getProductArgs($object, $update) {
        $title = "";
        $body = "";
        $image = "";
        $var_vars = [];
        //die(get_class($object));
        switch(get_class($object)) {
            case "jct\\Album":
                $title = $object->getAlbumTitle() . " (Full Album)";
                $body = $object->getAlbumTitle() . " (Full Album) by " . $object->getAlbumArtist() . " " .
                        $object->getAlbumYear();
                $image = base64_encode(file_get_contents($object->getAlbumArtObject()->getPath()));
                foreach($object->getAllChildZips() as $zip) {
                    $var_vars[] = [
                        'sku'        => $this::sku($object->getAlbumTitle(), "full album", $zip->getEncodeLabel()),
                        'option1'    => $zip->getEncodeLabel(),
                        'price'      => strval($object->getAlbumPrice()),
                        'metafields' => [
                            [
                                'key'   => 'filename', 'value_type' => 'string', 'namespace' => 'filenames',
                                'value' => $zip->getFilename(),
                            ],
                        ],
                    ];
                }
                break;
            case "jct\\Track":
                $title = $object->getTrackTitle();
                $body = "From " . $object->getAlbum()->getAlbumTitle() . " by " . $object->getTrackArtist() . " " .
                        $object->getTrackYear();
                $image = base64_encode(file_get_contents($object->getTrackArtObject()->getPath()));
                foreach($object->getAllChildEncodes() as $encode) {
                    $var_vars[] = [
                        'sku'        => $this::sku($object->getAlbum()->getAlbumTitle(), $object->getTrackTitle(), $encode->getEncodeLabel()),
                        'option1'    => $encode->getEncodeLabel(),
                        'price'      => strval($object->getTrackPrice()),
                        'metafields' => [
                            [
                                'key'   => 'filename', 'value_type' => 'string', 'namespace' => 'filenames',
                                'value' => $encode->getFilename(),
                            ],
                        ],
                    ];
                }
                break;
        }
        $variants = [];
        foreach($var_vars as $vars) {
            $v = [
                'option1'           => $vars['option1'],
                'price'             => $vars['price'],
                'sku'               => $vars['sku'],
                'taxable'           => false,
                'requires_shipping' => false,
            ];
            if($update) {
                $v['id'] = $object->getShopifyVariantIds()[$vars['option1']];
                $v['sku'] = $object->getShopifyVariantSkus()[$vars['option1']];
            }
            $variants[] = $v;
        }
        $wiki_link = $object->getWikiLink();
        $args = [
            "product" => [
                'title'        => $title,
                'body_html'    => $body,
                'vendor'       => "Jonathan Coulton",
                'product_type' => 'Music download',
                'images'       => [
                    ['attachment' => $image],
                ],
                'variants'     => $variants,
            ],
        ];
        if($update) {
            $args['product']['id'] = $object->getShopifyId();
        } else {
            $args['product']['metafields'] = [
                [
                    'key'   => 'track_number', 'value_type' => 'string', 'namespace' => 'global',
                    'value' => get_class($object) == "jct\\Track" ? $object->getTrackNumber() : 0,
                ],
                [
                    'key'   => 'wiki_link', 'value_type' => 'string', 'namespace' => 'global',
                    'value' => $wiki_link,
                ],
            ];
            if(get_class($object) == "jct\\Track") {
                $args['product']['metafields'][] = [
                    'key'   => 'music_link', 'value_type' => 'string', 'namespace' => 'global',
                    'value' => $object->getMusicLink(),
                ];
            }
        }
        return $args;
    }

    public function syncEverythingProduct($allAlbums = false) {
        if(!$allAlbums) {
            $allAlbums = \jct\Album::getAllAlbums();
        }
        $everything_shopify_details = get_transient('everything_shopify_details');
        $update = false;
        if($everything_shopify_details && $this->productExists($everything_shopify_details['id'])) {
            $update = true;
        }
        $everything_price = get_field('everything_price', 'options');
        $encode_types = include(get_template_directory() . '/config/encode_types.php');
        $variants = [];
        foreach($encode_types as $encode_type => $encode_details) {
            $v = [
                'option1'           => $encode_type,
                'price'             => $everything_price,
                'sku'               => $this->sku('everything', 'everything', $encode_type),
                'taxable'           => false,
                'requires_shipping' => false,
            ];
            if($update) {
                $v['sku'] = $everything_shopify_details['skus'][$encode_type];
                $v['id'] = $everything_shopify_details['ids'][$encode_type];
            }
            $variants[] = $v;
        }
        $args = [
            "product" => [
                'title'        => "Everything",
                'body_html'    => 'Everything listed below',
                'vendor'       => "Jonathan Coulton",
                'product_type' => 'Music download',
                'variants'     => $variants,
            ],
        ];
        if($update) {
            $args['product']['id'] = $everything_shopify_details['id'];
        } else {
            $args['product']['metafields'] = [
                ['key' => 'track_number', 'value_type' => 'string', 'namespace' => 'global', 'value' => 0],
                [
                    'key'   => 'wiki_link', 'value_type' => 'string', 'namespace' => 'global',
                    'value' => get_field('joco_wiki_base_url', 'options'),
                ],
            ];
        }
        if($update) {
            $response = $this->makeCall("admin/products/" . $everything_shopify_details['id'], "PUT", $args);
        } else {
            $response = $this->makeCall("admin/products", "POST", $args);
            //$this->forceGetAllProducts();
        }
        if($response->product->id) {
            $everything_id = $response->product->id;
            $variant_ids = [];
            $variant_skus = [];
            foreach($response->product->variants as $variant) {
                $variant_ids[$variant->option1] = $variant->id;
                $variant_skus[$variant->option1] = $variant->sku;
            }
            $everything_ids = $variant_ids;
            $everything_skus = $variant_skus;
            $everything_shopify_details =
                ['id' => $everything_id, 'ids' => $everything_ids, 'skus' => $everything_skus];
            set_transient('everything_shopify_details', $everything_shopify_details);
        }
        $missing_files = [];
        foreach($encode_types as $encode_type => $encode_details) {
            $aws_links = [];
            foreach($allAlbums as $album) {
                $aws_links[] = $album->getChildZip($encode_details[0], $encode_details[1], $encode_type)->getAwsUrl();
            }
            $sku = $this->sku('everything', 'everything', $encode_type);
            $fetch_name = 'Everything ' . $encode_type;
            $missings = $this->syncFetchProduct($sku, $fetch_name, $everything_price, $aws_links);
            foreach($missings as $missing) {
                $missing_files[] = ['fetch_name' => $fetch_name, 'url' => $missing];
            }
        }
        return ['missing_files' => $missing_files];
    }

    public function syncAlbumCollection($album, $ids) {
        $image = base64_encode(file_get_contents($album->getAlbumArtObject()->getPath()));
        $collects = [];
        foreach($ids as $num => $id) {
            $collects[] = ['product_id' => $id, 'sort_value' => $num];
        }
        $args = [
            'custom_collection' => [
                'title'      => $album->getAlbumTitle(),
                'body_html'  => $album->getAlbumYear(),
                'sort_order' => 'manual',
                //'image' => array('attachment' => $image),
                //'metafields' => array(array(
                //'key'=>'album_collection','value_type'=>'string','namespace'=>'global',
                //'value'=>'true')),
                'collects'   => $collects,
            ],
        ];
        $collection_id = $album->getShopifyCollectionId();
        if($collection_id && $this->collectionExists($collection_id)) {
            $collect_dict = [];
            $existing_collects = $this->makeCall('admin/collects', 'GET', ['collection_id' => $collection_id]);
            foreach($existing_collects->collects as $collect) {
                $collect_dict[$collect->product_id] = $collect->id;
                if(!in_array($collect->product_id, $ids)) {
                    $this->makeCall('admin/collects/' . $collect->product_id, "DELETE");
                }
            }
            foreach($collects as $key => $collect) {
                if($collect_dict[$collect['product_id']]) {
                    $collects[$key]['id'] = $collect_dict[$collect['product_id']];
                }
            }
            //$args['custom_collection']['image'] = array('attachment' => $image);
            $args['custom_collection']['collects'] = $collects;
            $args['custom_collection']['id'] = $collection_id;
            $response = $this->makeCall('admin/custom_collections/' . $collection_id, 'PUT', $args);
            $metafields = $this->makeCall('admin/custom_collections/' . $collection_id . '/metafields');
            foreach($metafields->metafields as $metafield) {
                if($metafield->key == 'album_sort_order') {
                    $this->updateMetafield($metafield, $album->getAlbumSortOrder());
                }
                if($metafield->key == 'album_description') {
                    $this->updateMetafield($metafield, $album->getAlbumDescription());
                }
            }
            return $response;
        } else {
            $args['custom_collection']['image'] = ['attachment' => $image];
            $args['custom_collection']['metafields'] = [
                ['key' => 'album_collection', 'value_type' => 'string', 'namespace' => 'global', 'value' => 'true'],
                ['key'   => 'album_sort_order', 'value_type' => 'integer', 'namespace' => 'global',
                 'value' => $album->getAlbumSortOrder(),
                ],
                ['key'   => 'album_description', 'value_type' => 'string', 'namespace' => 'global',
                 'value' => $album->getAlbumDescription(),
                ],
            ];
            $response = $this->makeCall('admin/custom_collections', 'POST', $args);
            if($response->custom_collection->id) {
                $album->setShopifyCollectionId($response->custom_collection->id);
            }
            return $response;
        }
    }

    public function deleteUnusedProducts($allAlbums = false) {
        $this->forceGetAllProducts();
        if(!$allAlbums) {
            $allAlbums = \jct\Album::getAllAlbums();
        }
        $usedIds = [];
        if($everything_shopify_details = get_transient('everything_shopify_details')) {
            $usedIds = [$everything_shopify_details['id']];
        }
        foreach($allAlbums as $album) {
            $usedIds[] = $album->getShopifyId();
            foreach($album->getAlbumTracks() as $track) {
                $usedIds[] = $track->getShopifyId();
            }
        }
        foreach($this->allProducts as $product) {
            if(!in_array($product->id, $usedIds)) {
                $this->makeCall("admin/products/$product->id", "DELETE");
            }
        }
    }

    public function deleteUnusedCollections($allAlbums = false) {
        $this->forceGetAllCollections();
        if(!$allAlbums) {
            $allAlbums = \jct\Album::getAllAlbums();
        }
        $usedIds = [];
        foreach($allAlbums as $album) {
            $usedIds[] = $album->getShopifyCollectionId();
        }
        foreach($this->allCollections as $collection) {
            if(!in_array($collection->id, $usedIds)) {
                $this->makeCall("admin/custom_collections/$collection->id", "DELETE");
            }
        }
    }

    public function getFetchProducts() {
        if(isset($this->allFetchProducts)) {
            return $this->allFetchProducts;
        }
        return $this->forceGetFetchProducts();
    }

    public function forceGetFetchProducts() {
        try {
            $this->allFetchProducts = $this->fetch->getProducts(10000, 1); // Grabs all products (potentially HUGE!)
            return $this->allFetchProducts;
        } catch(Exception $e) {
            // This will occur on any call if the AuthenticationKey and AuthenticationToken are not set.
            return $e->getMessage();
        }
    }

    public function syncFetchProduct($sku, $name, $price, $aws_links) {
        $fetch_product = false;
        foreach($this->getFetchProducts() as $product) {
            if((string)$product->getSKU() == $sku) {
                $fetch_product = $product;
            }
        }
        $files = [];
        if(!$fetch_product) {
            $fetch_product = new Product();
            $fetch_product->setSKU($sku);
            $fetch_product->setName($name);
            $fetch_product->setPrice($price);
            $fetch_product->setCurrency(Currency::USD);
            $missing_files = $aws_links;
            try {
                $fetch_product->create($files, false);
            } catch(Exception $e) {
                return $e->getMessage();
            }
        } else {
            $fetch_product->setName($name);
            $fetch_product->setPrice($price);
            $existing_files = $fetch_product->getFiles();
            $used_links = [];
            foreach($existing_files as $file) {
                $link = $file->getURL();
                if(in_array($link, $aws_links)) {
                    $files[] = $file;
                    $used_links[] = $link;
                }
            }
            $missing_files = array_diff($aws_links, $used_links);
            try {
                $fetch_product->update($files);
            } catch(Exception $e) {
                return $e->getMessage();
            }
        }
        if(count($missing_files)) {
            return $missing_files;
        } else {
            return false;
        }
    }

    public function deleteUnusedFetchProducts($allAlbums = false) {
        $this->forceGetFetchProducts();
        $used_skus = [];
        if($everything_shopify_details = get_transient('everything_shopify_details')) {
            $used_skus = $everything_shopify_details['skus'];
        }
        $used_skus = array_merge($used_skus, $this->getKaraokeSkus());
        foreach($allAlbums as $album) {
            $used_skus = array_merge($used_skus, array_values($album->getShopifyVariantSkus()));
            foreach($album->getAlbumTracks() as $track) {
                $used_skus = array_merge($used_skus, array_values($track->getShopifyVariantSkus()));
            }
        }
        foreach($this->getFetchProducts() as $product) {
            if(!in_array((string)$product->getSKU(), $used_skus)) {
                $product->delete();
            }
        }
    }

    public function getKaraokeSkus() {
        $response = $this->makeCall("admin/products", 'GET', ['product_type' => 'Karaoke', 'limit' => 250]);
        $skus = [];
        foreach($response->products as $product) {
            $skus[] = $product->variants[0]->sku;
        }
        return $skus;
    }

    public function getStoreContext() {
        $file_name = get_template_directory() . '/cache/cached_store_context.php';
        if(file_exists($file_name)) {
            include($file_name);
        }
        if(isset($cached_store_context)) {
            return $cached_store_context;
        } else {
            return false;
        }
    }

    // Gets one album at a time and saves working arrays in transients
    // Call this function once per page load and then reload until it returns zero
    // Then hit getAllShopifyContext()
    public function getAlbumsFromShopify() {
        $collections_to_go = get_transient('collections_to_go');
        $albums = get_transient('temporary_albums_context');
        if(!is_array($collections_to_go) || !is_array($albums)) {
            $collections_to_go = $this->getAllCollections();
            $albums = [];
            if($everything_shopify_details = get_transient('everything_shopify_details')) {
                $shopify_request = 'admin/products/' . $everything_shopify_details['id'];
                $products = $this->makeCall($shopify_request, 'GET');
                $product = $products->product;
                $collection_context = [
                    'title' => $product->title, 'body_html' => $product->body_html,
                    'image' => ['src' => $product->image->src],
                ];
                $product->metafields = ['wiki_link' => get_field('joco_wiki_base_url', 'options') . 'Discography'];
                $albums[300099] = ['collection' => $collection_context, 'products' => [$product]];
            }
        } else {
            $collection = array_pop($collections_to_go);
            $metafields = $this->makeCall('admin/custom_collections/' . $collection->id . '/metafields');
            $album_to_add = false;
            $album_sort_order = false;
            $collection->metafields = [];
            foreach($metafields->metafields as $metafield) {
                $collection->metafields[$metafield->key] = $metafield->value;
                if($metafield->key == 'album_collection' && $metafield->value) {
                    $products = $this->makeCall('admin/products', 'GET', ['collection_id' => $collection->id]);
                    $products_context = [];
                    foreach($products->products as $product) {
                        $metafields = $this->makeCall('admin/products/' . $product->id . '/metafields');
                        $metafields_context = [];
                        $track_number = 0;
                        foreach($metafields->metafields as $field) {
                            $metafields_context[$field->key] = $field->value;
                            if($field->key == "track_number") {
                                $track_number = $field->value;
                            }
                        }
                        $product->metafields = $metafields_context;
                        if(!isset($products_context[$track_number])) {
                            $products_context[$track_number] = $product;
                        }
                    }
                    ksort($products_context);
                    $album_to_add = ['collection' => $collection, 'products' => $products_context];
                }
                if($metafield->key == 'album_sort_order') {
                    $album_sort_order = $metafield->value;
                }
            }
            if($album_sort_order && $album_to_add) {
                $albums[$album_sort_order] = $album_to_add;
            }
        }
        set_transient('collections_to_go', $collections_to_go);
        set_transient('temporary_albums_context', $albums);
        return count($collections_to_go);
    }

    public function getAllShopifyContext() {
        $context = [];
        $categories = get_field('store_categories', 'options');
        if(is_array($categories)) {
            foreach($categories as $category) {
                if($category['display']) {
                    $html_name = htmlentities(strtolower(preg_replace('/\s+/', '_', $category['display_name'])));
                    if($category['shopify_type'] == 'Music download') {
                        $albums = get_transient('temporary_albums_context');
                        krsort($albums);
                        $products = $albums;
                    } else {
                        $response = $this->makeCall("admin/products", 'GET', [
                            'product_type' => $category['shopify_type'], 'limit' => 250,
                        ]);
                        $products = [];
                        foreach($response->products as $product) {
                            $products[strtotime($product->created_at)] = $product;
                        }
                        krsort($products);
                    }
                    $context[] = [
                        'name'         => $category['display_name'], 'html_name' => $html_name,
                        'shopify_type' => $category['shopify_type'], 'products' => $products,
                    ];
                }
            }
        }
        // Write store context to disk
        $strFileContent =
            "<" . "?php" . PHP_EOL . "$" . "cached_store_context = " . var_export($context, true) . PHP_EOL . "?" . ">";
        $strFileContent =
            str_replace("stdClass::__set_state", "(object)", $strFileContent); // I don't know why var_export() uses a non-existent method...
        file_put_contents(get_template_directory() . '/cache/cached_store_context.php', $strFileContent);
        return $context;
    }

}
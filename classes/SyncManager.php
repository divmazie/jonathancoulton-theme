<?php

namespace jct;

use FetchApp\API\FetchApp;
use GuzzleHttp\Client;
use jct\Shopify\CustomCollection;
use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\SynchronousAPIClient;
use FetchApp\API\Product as FetchProduct;

class SyncManager {
    const SHOPIFY_REMOTE_PRODUCT_CACHE_PREFIX = 'shopify_remote_products_cache';
    const SHOPIFY_REMOTE_COLLECTION_CACHE_PREFIX = 'shopify_remote_collections_cache';
    const FETCH_CACHE_PREFIX = 'fetch_remote_products_cache';
    const FETCH_PAGE_SIZE = 10000;
    const SHOPIFY_COLLECTION_CUSTOM_SUFFIX = 'jct_collection';

    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Download Store';
    const DEFAULT_SHOPIFY_PRODUCT_TYPE_TESTING = 'Download Store Testing';
    const DEFAULT_SHOPIFY_PRODUCT_VENDOR = 'Jonathan Coulton';
    const MAX_NUMBER_FEATURED_PRODUCTS = 4;

    private $shopifyApiClient, $fetchAppApiClient;

    public
        /**
         * @var MusicStoreCollection[]
         */
        $collection_products,
        $all_albums,
        $all_tracks,
        $everything_product,
        $all_encodes,
        $pending_encodes,
        $all_zips,
        $pending_zips,
        $uploadable_assets,
        $unuploaded_assets,
        $uploaded_assets,
        $garbage_attachments,
        $remote_shopify_products_filename,
        $remote_shopify_products_mtime,
        $remote_shopify_products,
        $local_music_store_products,
        $music_store_products_to_create,
        $music_store_products_to_update,
        $music_store_products_to_skip,
        $shopify_products_to_delete,
        $fetch_remote_products_filename,
        $fetch_remote_products_mtime,
        $fetch_remote_products,
        $local_fetch_syncables,
        $local_fetch_create_products,
        $local_fetch_update_products,
        // currently unused... no good way to sort
        // fetch products that are generated vs not
        $remote_fetch_delete_products = [],
        $remote_shopify_collections_filename,
        $remote_shopify_collections_mtime,
        $remote_shopify_collections,
        $local_shopify_create_collections,
        $local_shopify_recreate_collections,
        $local_shopify_skip_collections,
        $remote_shopify_delete_collections,
        $store_headers_all,
        $store_headers_to_display,
        $store_headers_to_hide,
        $store_category_freshness_indicator;

    public function __construct(SynchronousAPIClient $shopifyApiClient, FetchApp $fetchApp, $tz = 'America/New_York') {

        $this->shopifyApiClient = $shopifyApiClient;
        $this->fetchAppApiClient = $fetchApp;

        // display mtimes in nyc\
        date_default_timezone_set($tz);

        self::optimizeQueries();

        $this->everything_product = EverythingProduct::getInstance();
        $this->all_albums = Album::getAll();
        $this->collection_products = array_merge([$this->everything_product], $this->all_albums);
        $this->all_tracks = Track::getAll();

        $this->all_encodes = EncodeConfig::getAll();
        $this->pending_encodes = EncodeConfig::getPending();

        $this->all_zips = AlbumZipConfig::getAll();
        $this->pending_zips = AlbumZipConfig::getPending();

        $this->uploadable_assets = array_merge(Encode::getAll(), AlbumZip::getAll());
        foreach($this->uploadable_assets as $uploadable_asset) {
            /** @var EncodedAsset $uploadable_asset */
            if($uploadable_asset->isUploadedToS3()) {
                $this->uploaded_assets[] = $uploadable_asset;
            } else {
                $this->unuploaded_assets[] = $uploadable_asset;
            }
        }

        $this->local_fetch_syncables =
            array_merge($this->everything_product->getFetchSyncables(), $this->uploadable_assets);

        // key by SKU
        $this->local_fetch_syncables = array_combine(array_map(function (FetchSyncable $syncable) {
            return $syncable->getShopifyAndFetchSKU();
        }, $this->local_fetch_syncables), $this->local_fetch_syncables);

        $this->garbage_attachments =
            array_diff_key($this->uploadable_assets, array_merge($this->all_zips, $this->all_encodes));

        $this->local_music_store_products =
            array_merge($this->collection_products, $this->all_tracks);

        if($this->can_sync_remote()) {
            $this->loadShopifyProductCache();
            if($this->remote_shopify_products_filename) {
                $this->sortShopifyProducts();
            }

            $this->loadShopifyCollectionsCache();
            if($this->remote_shopify_collections_filename) {
                $this->sortShopifyCollections();
            }

            $this->loadFetchRemoteCache();
            if($this->fetch_remote_products_filename) {
                $this->sortFetchProducts();
            }
        } else {
            self::clearCaches();
        }
        $this->sortStoreHeaders();
    }


    public function can_sync_remote() {
        return !$this->pending_zips &&
               !$this->pending_encodes &&
               !$this->unuploaded_assets;
    }

    public static function optimizeQueries() {
        // basically this will get everything we'll need in a few
        // bigger queries, then it will all be store.
        Album::getAll();
        Track::getAll();
        Encode::getAll();
        AlbumZip::getAll();
        CoverArt::getAll();
        BonusAsset::getAll();
        SourceTrack::getAll();
    }

    private function loopForX(callable $loopFunction, $array, $xSeconds, $counterStart) {
        $endAt = time() + $xSeconds;
        $counter = $counterStart;
        // needs to be numerically addressasble
        $array = array_values($array);

        while($counter < count($array) && time() < $endAt) {
            $item = $array[$counter];
            $loopFunction($item);
            $counter++;
        }
        return $counter;
    }

    private function maintenanceRedirect() {
        Util::redirect('./?' . $_SERVER['QUERY_STRING']);
        exit();
    }

    private function loopTilDoneStaticArray(callable $loopFunction, $array, $finishedLoc, $loopXSec = 44) {
        $idx = intval(@$_GET['idx']);
        $loopXSec = intval(@$_GET['xsec']) > 0 ? intval(@$_GET['xsec']) : $loopXSec;

        if($array && $idx < count($array)) {
            $params = $_GET;
            $params['idx'] = $this->loopForX($loopFunction, $array, $loopXSec, $idx);
            Util::redirect('./?' . \http_build_query($params));
            exit();
        } else {
            Util::redirect($finishedLoc);
            exit();
        }
    }

    private function loopTilDone(callable $loopFunction, $array, $finishedLoc, $loopXSec = 44) {
        if($array) {
            $this->loopForX($loopFunction, $array, $loopXSec, 0);
            $this->maintenanceRedirect();
            exit();
        } else {
            Util::redirect($finishedLoc);
            exit();
        }
    }

    public function doEncodes() {
        $postArray = [];
        $postArray['encodes'] = array_map(function (EncodeConfig $encodeConfig) {
            return $encodeConfig->toEncodeBotArray(EncodeConfig::getEncodeAuthCode());
        }, $this->pending_encodes);

        $client = new Client();

        $r = $client->request('POST', Util::get_theme_option('post_encodes_link'), [
            'json' => $postArray,
        ]);

        if($r->getStatusCode() === 200) {
            return true;
        }

        echo "<pre>";
        print_r($r);
        print_r((string)$r->getBody());
        die();
    }

    public function doZips($finishedUrl) {
        $this->loopTilDone(function (AlbumZipConfig $zipConfig) {
            $zipConfig->createZip();
        }, $this->pending_zips, $finishedUrl);
    }

    public function doS3($finishedUrl) {
        $this->loopTilDone(function (EncodedAsset $encodedAsset) {
            $encodedAsset->uploadToS3();
        }, $this->unuploaded_assets, $finishedUrl);
    }

    public function cacheRemoteShopifyProducts() {
        $this->remote_shopify_products =
            $this->shopifyApiClient->getAllProducts(['product_type' => self::getShopifyProductType()]);
        // key remote products by id
        $this->remote_shopify_products = array_combine(array_map(function (Product $product) {
            return $product->id;
        }, $this->remote_shopify_products), $this->remote_shopify_products);

        return static::setFileCache($this->remote_shopify_products, self::SHOPIFY_REMOTE_PRODUCT_CACHE_PREFIX);
    }

    private function updateShopifyProductCache() {
        static::setFileCache($this->remote_shopify_products, self::SHOPIFY_REMOTE_PRODUCT_CACHE_PREFIX);
    }

    private function loadShopifyProductCache() {
        $fileName = '';
        $this->remote_shopify_products =
            static::getFileArrayCache(self::SHOPIFY_REMOTE_PRODUCT_CACHE_PREFIX, $fileName);

        if($fileName) {
            $this->remote_shopify_products_filename = $fileName;
            $this->remote_shopify_products_mtime = self::formattedMTime($this->remote_shopify_products_filename);
        }
    }

    private function sortShopifyProducts() {

        if($this->local_music_store_products && $this->remote_shopify_products_filename) {
            foreach($this->local_music_store_products as $musicStoreProduct) {
                /** @var MusicStoreProduct $musicStoreProduct */
                /** @var Product $shopifyProduct */
                $shopifyProduct = $musicStoreProduct->getShopifyProduct();
                $syncMeta = $musicStoreProduct->getShopifySyncMetadata();

                // if we have an ID && it exists remotely...
                // this product has been synced before
                if($shopifyProduct->id &&
                   ($remoteProduct = @$this->remote_shopify_products[$shopifyProduct->id])
                ) {
                    if($syncMeta->productNeedsUpdate($shopifyProduct)) {
                        $this->music_store_products_to_update[] = $musicStoreProduct;
                    } else {
                        $this->music_store_products_to_skip[] = $musicStoreProduct;
                    }
                } else {
                    $this->music_store_products_to_create[] = $musicStoreProduct;
                }
            }

            // anything local that has an id
            $spareTheirLives = array_filter(array_map(function (MusicStoreProduct $musicStoreProduct) {
                $shopifyProduct = $musicStoreProduct->getShopifyProduct();
                return $shopifyProduct->id;
            }, $this->local_music_store_products));

            // anything that's not supposed to get spare, we'll delete
            $this->shopify_products_to_delete =
                array_diff_key($this->remote_shopify_products, array_combine($spareTheirLives, $spareTheirLives));

        }
    }

    public function recordReturnedProduct(MusicStoreProduct $musicStoreProduct, Product $returnedProduct) {
        $musicStoreProduct->getShopifySyncMetadata()->processMusicStoreProductReturn($musicStoreProduct, $returnedProduct);
    }

    public function recordReturnedCollection(MusicStoreCollection $album, CustomCollection $customCollection) {
        $album->getShopifySyncMetadata()->processCollectionReturn($album, $customCollection);
    }

    public function doShopifyProductCreates($finishedUrl) {
        $this->loopTilDone(function (MusicStoreProduct $musicStoreProduct) {
            $returnedProduct = $this->shopifyApiClient->postProduct($musicStoreProduct->getShopifyProduct());
            $this->remote_shopify_products[$returnedProduct->id] = $returnedProduct;
            $this->updateShopifyProductCache();
            $this->recordReturnedProduct($musicStoreProduct, $returnedProduct);
        }, $this->music_store_products_to_create, $finishedUrl);
    }

    public function doShopifyProductUpdates($finishedUrl) {
        $this->loopTilDone(function (MusicStoreProduct $musicStoreProduct) {
            $this->recordReturnedProduct($musicStoreProduct, $this->shopifyApiClient->putProduct($musicStoreProduct->getShopifyProduct()));
        }, $this->music_store_products_to_update, $finishedUrl);
    }

    public function forceShopifyProductUpdates($finishedUrl) {
        $this->loopTilDoneStaticArray(function (MusicStoreProduct $musicStoreProduct) {
            $this->recordReturnedProduct($musicStoreProduct, $this->shopifyApiClient->putProduct($musicStoreProduct->getShopifyProduct()));
        }, $this->music_store_products_to_skip, $finishedUrl);
    }

    public function doShopifyProductDeletes($finishedUrl) {
        $this->loopTilDone(function (Product $product) {
            $this->shopifyApiClient->deleteProduct($product);
            unset($this->remote_shopify_products[$product->id]);
            $this->updateShopifyProductCache();
        }, $this->shopify_products_to_delete, $finishedUrl);
    }

    public function cacheRemoteShopifyCustomCollections() {
        $this->remote_shopify_collections =
            $this->shopifyApiClient->getAllCustomCollections();

        // we only want ones with the custom suffix we are targetting
        $this->remote_shopify_collections =
            array_filter($this->remote_shopify_collections, function (CustomCollection $collection) {
                return $collection->template_suffix === SyncManager::SHOPIFY_COLLECTION_CUSTOM_SUFFIX;
            });

        // key by id
        $this->remote_shopify_collections = array_combine(array_map(function (CustomCollection $collection) {
            return $collection->id;
        }, $this->remote_shopify_collections), $this->remote_shopify_collections);

        return static::setFileCache($this->remote_shopify_collections, self::SHOPIFY_REMOTE_COLLECTION_CACHE_PREFIX);
    }

    private function updateShopifyCollectionsCache() {
        static::setFileCache($this->remote_shopify_collections, self::SHOPIFY_REMOTE_COLLECTION_CACHE_PREFIX);
    }

    private function loadShopifyCollectionsCache() {
        $fileName = '';
        $this->remote_shopify_collections =
            static::getFileArrayCache(self::SHOPIFY_REMOTE_COLLECTION_CACHE_PREFIX, $fileName);

        if($fileName) {
            $this->remote_shopify_collections_filename = $fileName;
            $this->remote_shopify_collections_mtime = self::formattedMTime($this->remote_shopify_collections_filename);
        }
    }

    public function sortShopifyCollections() {
        // copy all to delete collection
        // we'll unset the ones that have matches from here soon
        $deleteCollection = $this->remote_shopify_collections;

        foreach($this->collection_products as $album) {
            /** @var Album $album */
            $collection = $album->getShopifyCustomCollection();
            if($collection->id && isset($this->remote_shopify_collections[$collection->id])) {
                // if it has an id it has been synced before
                if($album->getShopifySyncMetadata()->customCollectionHasChanged($album)) {
                    $this->local_shopify_recreate_collections[] = $album;
                } else {
                    $this->local_shopify_skip_collections[] = $album;
                }

                unset($deleteCollection[$collection->id]);
            } else {
                // it does not exist and needs to be created
                $this->local_shopify_create_collections[] = $album;
            }
        }

        $this->remote_shopify_delete_collections = &$deleteCollection;
    }

    public function doCollectionPostAction($finishedUrl, $albumArray, $deleteFirst = false, $staticArray = false) {
        $callable = function (MusicStoreCollection $collection) use ($deleteFirst) {
            if($deleteFirst) {
                $this->shopifyApiClient->deleteCollection($collection->getShopifyCustomCollection());
                unset($this->remote_shopify_collections[$collection->getShopifyCustomCollection()->id]);
            }
            $returnedCollection =
                $this->shopifyApiClient->postCustomCollection($collection->getShopifyCustomCollection());
            $this->recordReturnedCollection($collection, $returnedCollection);

            $this->remote_shopify_collections[$returnedCollection->id] = $returnedCollection;
            $this->updateShopifyCollectionsCache();
        };

        if($staticArray) {
            // this option will be used for force update, where the list of products
            // won't change
            $this->loopTilDoneStaticArray($callable, $albumArray, $finishedUrl);
        } else {
            $this->loopTilDone($callable, $albumArray, $finishedUrl);
        }
    }


    public function doShopifyCollectionDeletes($finishedUrl) {
        $this->loopTilDone(function (CustomCollection $customCollection) {
            $this->shopifyApiClient->deleteCollection($customCollection);
            unset($this->remote_shopify_collections[$customCollection->id]);
            $this->updateShopifyCollectionsCache();
        }, $this->remote_shopify_delete_collections, $finishedUrl);
    }


    public function cacheRemoteFetchProducts() {
        $all = $this->fetchAppApiClient->getProducts(self::FETCH_PAGE_SIZE, 1);

        // key by sku
        $cacheArray = [];
        foreach($all as &$product) {
            // if this is one of ours, include it
            if(isset($this->local_fetch_syncables[$product->getSKU()])) {
                $cacheArray[$product->getSKU()] = $product;
            }
        }

        $this->fetch_remote_products = &$cacheArray;
        return $this->fetch_remote_products_filename =
            $this->setFileCache($this->fetch_remote_products, self::FETCH_CACHE_PREFIX);
    }

    private function loadFetchRemoteCache() {
        $fileName = '';
        $this->fetch_remote_products = static::getFileArrayCache(self::FETCH_CACHE_PREFIX, $fileName);

        if($fileName) {
            $this->fetch_remote_products_filename = $fileName;
            $this->fetch_remote_products_mtime = self::formattedMTime($this->fetch_remote_products_filename);
        }
    }

    private function updateFetchCache() {
        static::setFileCache($this->fetch_remote_products, self::FETCH_CACHE_PREFIX);
    }


    private function sortFetchProducts() {
        if($this->fetch_remote_products_filename) {
            $local_skus = [];
            foreach($this->local_fetch_syncables as $uploadable_asset) {
                /** @var FetchSyncable $uploadable_asset */
                $sku = $uploadable_asset->getShopifyAndFetchSKU();
                $local_skus[] = $sku;
                if(isset($this->fetch_remote_products[$sku])) {
                    // exists... update it
                    $this->local_fetch_update_products[$sku] = $uploadable_asset;
                } else {
                    // not exist... create it
                    $this->local_fetch_create_products[$sku] = $uploadable_asset;
                }
            }

            //   $this->remote_fetch_delete_products =
            //     array_diff_key($this->fetch_remote_products, array_combine($local_skus, $local_skus));
        }
    }

    public function doFetchCreates($finishedUrl) {
        $this->loopTilDone(function (FetchSyncable $encodedAsset) {
            $fetch_product = $encodedAsset->getFetchAppProduct();
            $rv = $fetch_product->create([], $encodedAsset->getFetchAppUrlsArray());
            if($rv === true) {
                $this->fetch_remote_products[$fetch_product->getSKU()] = $fetch_product;
            } else {
                var_dump($rv);
                die();
            }
            $this->updateFetchCache();
        }, $this->local_fetch_create_products, $finishedUrl);
    }

    public function doFetchUpdates($finishedUrl) {
        $this->loopTilDoneStaticArray(function (FetchSyncable $encodedAsset) {
            $fetch_product = $encodedAsset->getFetchAppProduct();
            //var_dump($fetch_product);
            //var_dump($this->fetch_remote_products[$fetch_product->getSKU()]);
            $rv = $fetch_product->update([], $encodedAsset->getFetchAppUrlsArray());
            if($rv === true) {
                $this->fetch_remote_products[$fetch_product->getSKU()] = $fetch_product;
            } else {
                var_dump($rv);
                die();
            }
            $this->updateFetchCache();
        }, $this->local_fetch_update_products, $finishedUrl);
    }

    public function doFetchDeletes($finishedUrl) {
        $this->loopTilDone(function (FetchProduct $fetch_product) {
            $rv = $fetch_product->delete();
            unset($this->fetch_remote_products[$fetch_product->getSKU()]);
        }, $this->remote_fetch_delete_products, $finishedUrl);
    }

    public function should_create_lock_file() {
        return $this->can_sync_remote() &&
               !$this->music_store_products_to_create &&
               !$this->music_store_products_to_update &&
               !$this->local_shopify_create_collections &&
               !$this->local_shopify_recreate_collections &&
               !$this->local_fetch_create_products;
    }

    public function deleteGarbage($finishedUrl) {
        $this->loopTilDone(function (EncodedAsset $encodedAsset) {
            $encodedAsset->deleteAttachment(true);
        }, $this->garbage_attachments, $finishedUrl);
    }

    public function sortStoreHeaders() {
        $this->store_headers_all = Util::get_theme_option('store_categories');
        $store_types_to_fetch = [];
        // only get the store headers we are supposed to display
        for($i = 0; $i < count($this->store_headers_all); $i++) {
            $store_header = $this->store_headers_all[$i];
            // add in some keys that markup uses
            $store_header['slug'] = $i === 0 ? 'downloads' : Util::slugify($store_header['display_name']);
            $store_header['name'] = $store_header['display_name'];
            if($i === 0 || $store_header['display']) {
                $this->store_headers_to_display[] = $store_header;
                if($i > 0) {
                    // we don't fetch download store information--just for other
                    // categories
                    $store_types_to_fetch[] = $store_header['shopify_type'];
                }
            } else {
                $this->store_headers_to_hide[] = $store_header;
            }
        }
        $this->store_category_freshness_indicator = md5(serialize($store_types_to_fetch));
    }

    public function buildMusicStoreLockArrays(&$lockArray, &$featuredAlbumArray) {
        $featuredAlbumArray = array_map(function ($acfArray) {
            return $acfArray['spotlight_album'];
        }, Util::get_theme_option('spotlight_albums'));
        $featuredAlbumArray = array_combine($featuredAlbumArray, array_fill(0, count($featuredAlbumArray), null));

        $lockArray = [];
        $lockArray['category_freshness'] = $this->store_category_freshness_indicator;
        $lockArray['store_headers'] = $this->store_headers_to_display;

        $lockArray['download_store']['header'] = $this->store_headers_to_display[0];
        // e.g. {id: 9146558214, url: 'http://192.168....mp3', title: 'Test Track'}
        $lockArray['download_store']['playlist'] = array_map(function (Track $track) {
            $product = $track->getShopifyProduct();
            return [
                'id'    => $product->id,
                'url'   => $track->getEncodeConfigByName(Track::PLAYER_ENCODE_CONFIG_NAME)->getEncode()->getURL(),
                'title' => $product->title,
            ];
        }, $this->all_tracks);


        $lockArray['download_store']['playlist'] = [];
        $playlist = &$lockArray['download_store']['playlist'];
        foreach($this->collection_products as $collection_product) {
            $thisCollection = $lockArray['download_store']['collections'][] = [
                'collection' => $collection_product->getShopifyCustomCollection()->putArray(),
                'products'   => array_map(function (Product $product) {
                    // key these by metafield key

                    $product->metafields = array_combine(array_map(function (Metafield $metafield) {
                        return $metafield->key;
                    }, $product->metafields), $product->metafields);

                    return $product->putArray();
                }, $collection_product->getShopifyCollectionProducts()),
            ];

            if($collection_product instanceof Album) {
                $playlist[] = array_map(function (Track $track) {
                    $product = $track->getShopifyProduct();
                    return [
                        'id'    => $product->id,
                        'url'   => $track->getEncodeConfigByName(Track::PLAYER_ENCODE_CONFIG_NAME)->getEncode()->getURL(),
                        'title' => $product->title,
                    ];
                }, $collection_product->getAlbumTracks());

                if(array_key_exists($collection_product->getPostID(), $featuredAlbumArray)) {
                    $featuredAlbumArray[$collection_product->getPostID()] = $thisCollection;
                }
            }

        }

        $playlist = Util::array_merge_flatten_1L($playlist);

        // kill any still null albums if somehow those have come to be here
        $featuredAlbumArray =
            array_slice(array_filter($featuredAlbumArray), 0, SyncManager::MAX_NUMBER_FEATURED_PRODUCTS);

        for($i = 1; $i < count($this->store_headers_to_display); $i++) {
            $header = $this->store_headers_to_display[$i];
            $lockArray['other_stores'][] = [
                'header'   => $header,
                'products' => array_map(function (Product $product) {
                    return $product->putArray();
                }, $this->shopifyApiClient->getAllProducts(['product_type' => $header['shopify_type']])),
            ];
        }

        return;

        //die();
    }

    public function createMusicStoreLockFile() {

        $lockArray = null;
        $featuredAlbumArray = null;

        $this->buildMusicStoreLockArrays($lockArray, $featuredAlbumArray);

        file_put_contents(self::music_lock_file_path(),
                          json_encode($lockArray, JSON_PRETTY_PRINT |
                                                  JSON_OBJECT_AS_ARRAY));

        file_put_contents(self::featured_albums_lock_file_path(),
                          json_encode($featuredAlbumArray, JSON_PRETTY_PRINT |
                                                           JSON_OBJECT_AS_ARRAY));

    }


    private static function formattedMTime($filename) {
        return date('F d Y h:i a e', filemtime($filename));
    }


    private static function getFileArrayCache($uniqueFilePrefix, &$cacheFileName = '') {
        $cacheFileName = static::chooseCacheFile($uniqueFilePrefix);
        if($cacheFileName) {
            return unserialize(file_get_contents($cacheFileName));
        }
        return null;
    }

    private static function setFileCache($serializable, $uniqueFilePrefix) {
        $file = static::chooseCacheFile($uniqueFilePrefix, true);
        file_put_contents($file, serialize($serializable));
        return $file;
    }

    private static function chooseCacheFile($uniqueFilePrefix, $create = false) {
        $chosenMTime = 0;
        $chosenFile = null;
        foreach(glob(static::getTempBaseDir() . '/' . $uniqueFilePrefix .
                     '*', GLOB_NOSORT) as $cacheFile) {
            if(($mtime = filemtime($cacheFile)) > $chosenMTime) {
                $chosenMTime = $mtime;
                $chosenFile = $cacheFile;
            }
        }

        if($create && !$chosenFile) {
            if(!file_exists(static::getTempBaseDir())) {
                wp_mkdir_p(static::getTempBaseDir());
            }
            $chosenFile = tempnam(static::getTempBaseDir(), $uniqueFilePrefix);
        }

        @chmod($chosenFile, 0664);
        return $chosenFile;
    }

    private static function getTempBaseDir() {
        return Util::cache_dir_path();
    }

    private static function clearCaches() {
        foreach([
                    self::SHOPIFY_REMOTE_COLLECTION_CACHE_PREFIX,
                    self::SHOPIFY_REMOTE_PRODUCT_CACHE_PREFIX,
                    self::FETCH_CACHE_PREFIX,
                ]
                as $cachePrefix) {

            while($file = self::chooseCacheFile($cachePrefix)) {
                unlink($file);
            }
        }
    }

    public static function music_lock_file_path() {
        return Util::cache_dir_path() . '/' . 'store_lock_file.json';
    }

    public static function featured_albums_lock_file_path() {
        return Util::cache_dir_path() . '/' . 'featured_albums_lock_file.json';
    }

    public static function get_store() {
        return file_exists(self::music_lock_file_path()) ?
            json_decode(file_get_contents(self::music_lock_file_path()), true) :
            false;
    }

    public static function get_featured_albums() {
        return file_exists(self::featured_albums_lock_file_path()) ?
            json_decode(file_get_contents(self::featured_albums_lock_file_path()), true) :
            false;
    }

    public static function getShopifyProductType() {
        return Util::is_dev() ? self::DEFAULT_SHOPIFY_PRODUCT_TYPE_TESTING : self::DEFAULT_SHOPIFY_PRODUCT_TYPE;
    }


}
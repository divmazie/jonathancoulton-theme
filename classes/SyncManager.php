<?php

namespace jct;

use FetchApp\API\FetchApp;
use GuzzleHttp\Client;
use jct\Shopify\Product;
use jct\Shopify\SynchronousAPIClient;

class SyncManager {
    const SHOPIFY_REMOTE_CACHE_PREFIX = 'shopify_remote_products_cache';

    private $shopifyApiClient, $fetchAppApiClient;

    public
        $all_albums,
        $all_tracks,
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
        $shopify_products_to_delete;

    public function __construct(SynchronousAPIClient $shopifyApiClient, FetchApp $fetchApp) {
        $this->shopifyApiClient = $shopifyApiClient;
        $this->fetchAppApiClient = $fetchApp;

        self::optimizeQueries();

        $this->all_albums = Album::getAll();
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

        $this->garbage_attachments =
            array_diff_key($this->uploadable_assets, array_merge($this->all_zips, $this->all_encodes));

        $this->loadShopifyRemoteCache();
        if($this->remote_shopify_products_mtime) {
            $this->local_music_store_products = array_merge($this->all_albums, $this->all_tracks);
            $this->sortShopifyProducts();
        }
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
        }, EncodeConfig::getAll());

        $client = new Client();

        $r = $client->request('POST', Util::get_theme_option('post_encodes_link'), [
            'json' => $postArray,
        ]);

        if($r->getStatusCode() === 200) {
            return true;
        }

        echo "<pre>";
        print_r($r);
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

    public function cacheRemoteProducts() {
        $this->remote_shopify_products =
            $this->shopifyApiClient->getAllProducts(['product_type' => MusicStoreProduct::DEFAULT_SHOPIFY_PRODUCT_TYPE]);
        // key remote products by id
        $this->remote_shopify_products = array_combine(array_map(function (Product $product) {
            return $product->id;
        }, $this->remote_shopify_products), $this->remote_shopify_products);

        return static::setFileCache($this->remote_shopify_products, self::SHOPIFY_REMOTE_CACHE_PREFIX);
    }

    private function updateShopifyCache() {
        static::setFileCache($this->remote_shopify_products, self::SHOPIFY_REMOTE_CACHE_PREFIX);
    }

    public function deleteGarbage($finishedUrl) {
        $this->loopTilDone(function (EncodedAsset $encodedAsset) {
            $encodedAsset->deleteAttachment(true);
        }, $this->garbage_attachments, $finishedUrl);
    }

    private function loadShopifyRemoteCache() {
        $fileName = '';
        $this->remote_shopify_products = static::getFileArrayCache(self::SHOPIFY_REMOTE_CACHE_PREFIX, $fileName);

        if($fileName) {
            $this->remote_shopify_products_filename = $fileName;
            $this->remote_shopify_products_mtime = date('F d Y h:i a e', filemtime($fileName));
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
        $musicStoreProduct->getShopifySyncMetadata()->processAPIProductReturn($musicStoreProduct, $returnedProduct);
    }

    public function doShopifyCreates($finishedUrl) {
        $this->loopTilDone(function (MusicStoreProduct $musicStoreProduct) {
            $returnedProduct = $this->shopifyApiClient->postProduct($musicStoreProduct->getShopifyProduct());
            $this->remote_shopify_products[$returnedProduct->id] = $returnedProduct;
            $this->updateShopifyCache();
            $this->recordReturnedProduct($musicStoreProduct, $returnedProduct);
        }, $this->music_store_products_to_create, $finishedUrl);
    }

    public function doShopifyUpdates($finishedUrl) {
        $this->loopTilDone(function (MusicStoreProduct $musicStoreProduct) {
            $this->recordReturnedProduct($musicStoreProduct, $this->shopifyApiClient->putProduct($musicStoreProduct->getShopifyProduct()));
        }, $this->music_store_products_to_update, $finishedUrl);
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
            $chosenFile = tempnam(static::getTempBaseDir(), $uniqueFilePrefix);
        }

        return $chosenFile;
    }

    private static function getTempBaseDir() {
        return sys_get_temp_dir();
    }


}
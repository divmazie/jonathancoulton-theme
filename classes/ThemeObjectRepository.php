<?php

namespace jct;

use jct\Shopify\Product;
use jct\Shopify\SynchronousAPIClient;

class ThemeObjectRepository {
    const DEFAULT_SHOPIFY_PRODUCT_TYPE = 'Parallel Testing';


    public static function optimizeQueries() {
        // basically this will get everything we'll need in a few
        // bigger queries, then it will all be store.
        Album::getAll();
        Track::getAll();
        CoverArt::getAll();
        BonusAsset::getAll();
        SourceTrack::getAll();
    }


    public static function getMusicStoreProducts() {
        return array_merge(Album::getAll(), Track::getAll());
    }

    /**
     * @param $remoteProducts Product[]
     * @param $musicStoreProducts MusicStoreProduct[]
     */
    public static function sync(SynchronousAPIClient $APIClient, $musicStoreProducts, $remoteProducts) {

        // key remote products by id
        $remoteProducts = array_combine(array_map(function (Product $product) {
            return $product->id;
        }, $remoteProducts), $remoteProducts);

        $returnedProduct = null;
        foreach($musicStoreProducts as $musicStoreProduct) {
            $shopifyProduct = $musicStoreProduct->getShopifyProduct();
            $syncMeta = $musicStoreProduct->getShopifySyncMetadata();

            // if we have an ID && it exists remotely...
            // this product has been synced before
            if($shopifyProduct->id &&
               ($remoteProduct = @$remoteProducts[$shopifyProduct->id])
            ) {

                if($syncMeta->productNeedsUpdate($shopifyProduct)) {
                    echo "need to update " . $shopifyProduct->id . "\n";
                    $returnedProduct = $APIClient->putProduct($shopifyProduct);

                } else {
                    $localSkip[] = $musicStoreProduct;
                }
            } else {
                // we don't have an ID || the id does not exist remotely
                $returnedProduct = $APIClient->postProduct($shopifyProduct);
                echo json_encode($shopifyProduct->putArray(), JSON_PRETTY_PRINT);
                echo json_encode($returnedProduct->putArray(), JSON_PRETTY_PRINT);
            }

            if($returnedProduct) {
                $syncMeta->processAPIProductReturn($returnedProduct, $shopifyProduct);
                $musicStoreProduct->setShopifySyncMetadata($syncMeta);
            }

        }


        $spareTheirLives = array_map(function (MusicStoreProduct $musicStoreProduct) {
            $shopifyProduct = $musicStoreProduct->getShopifyProduct();
            return $shopifyProduct->id;
        }, $musicStoreProducts);

        $remoteDelete = array_diff_key($remoteProducts, array_combine($spareTheirLives, $spareTheirLives));

    }

}
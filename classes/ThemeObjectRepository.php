<?php

namespace jct;


use jct\Shopify\Metafield;
use jct\Shopify\Product;
use jct\Shopify\Provider\MetafieldProvider;
use jct\Shopify\Provider\ProductProvider;
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

    /**  @return ProductProvider[] */
    public static function getLocalProductProviders() {
        self::optimizeQueries();

        return array_merge(Album::getAll(), Track::getAll());
    }


    /**
     * @param $remoteProducts Product[]
     * @param $localProviders ProductProvider[]
     */
    public static function sync(SynchronousAPIClient $APIClient, $remoteProducts, $localProviders) {

        // key remote products by id
        $remoteProducts = array_combine(array_map(function (Product $product) {
            return $product->id;
        }, $remoteProducts), $remoteProducts);

        $localCreate = [];
        $localUpdate = [];
        $localSkip = [];

        foreach($localProviders as $provider) {
            // if we have an ID && it exists remotely
            if($provider->getShopifyID() && ($remoteProduct = @$remoteProducts[$provider->getShopifyID()])) {

                if($provider->shouldUpdateProduct()) {
                    // these products just need a little update
                    echo "need to update " . $provider->getShopifyID() . "\n";
                    $productToUpdate = Product::fromProductProvider($provider);
//                    Metafield::portMetafieldIDs($productToUpdate->metafields, $remoteProduct->metafields);
                    $metafieldsToUpdate =
                        array_map(function (MetafieldProvider $metafieldProvider) {
                            return Metafield::fromMetafieldProvider(null, $metafieldProvider);
                        }, $provider->getProductMetafieldProvidersToUpdate());
                    $provider->remoteProductResponse($APIClient->putProduct($productToUpdate, $metafieldsToUpdate));


                } else {
                    $localSkip[] = $provider;
                }
            } else {

                // we don't have an ID || the id does not exist remotely
                $provider->remoteProductResponse($APIClient->postProduct(Product::fromProductProvider($provider)));
            }
        }

        $spareTheirLives = array_map(function (ProductProvider $provider) {
            return $provider->getShopifyID();
        }, array_merge($localCreate, $localSkip));

        $remoteDelete = array_diff_key($remoteProducts, array_combine($spareTheirLives, $spareTheirLives));

    }

}
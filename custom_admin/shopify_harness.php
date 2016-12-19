<?php

namespace jct;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use jct\Shopify\SynchronousAPIClient;
use jct\Shopify\Product;

/**
 * Sync plan
 * Use products for monolithic updates. Don't update stuff outside of the
 * product flow, which is the fastest.
 *
 * Either products
 *  - $onLocal && !$onShopify: POST
 *  - $onLocal && $onShopify: PUT
 *      We PUT for EVERYTHING because the single query is cheaper than
 *      actually fetching the stuff we'd need to fetch
 *
 *      (OR we use a local approximation of syncedness
 *      and have a force option, splitting this into two... )
 *
 *  - !$onLocal && $onShopify: DELETE
 *
 * In order to do this we need to be able to tell shopify WTF
 * we are talking about, i.e. we need to correlate the stuff from our
 * database and shopify's. Preferably without storing a bunch of nasty
 * keys everywhere. For any objects we want to actually UPDATE vs overwrite
 * we need a way to get their ID and put it into the object coming out of
 * OUR system.
 *
 * Products (backtrack from variants)
 *  - variants (the SKU is the Post ID and configName... we can key an array with this)
 *  - images (OVERWRITE EM WHO CARES--we upload a key, not the base64)
 *  - options (SAME... NO IMPACT)
 *  - metafields (I hope they are the same and that the key is enough to overwrite them)
 *
 * Do fetch later... use the trick from github to actually upload the s3 urls... that one
 * should be relatively quick (I hope)
 */
echo "<pre>";

$apiClient = Util::get_shopify_api_client();


//var_dump($otherClient->makeCall('admin/custom_collections'));
//$response = $apiClient->shopifyPagedGet('admin/products.json');

//$this->shopifyPagedGet('admin/products.json')
//var_dump($response);
//var_dump($apiClient->getAllProducts());

//var_dump($apiClient->shopifyPagedGet('/admin/products/9133128710/metafields.json', ['metafield[owner_resource]' => 'product']));


//var_dump($apiClient->shopifyPagedGet('/admin/custom_collections.json'));


$remotepord = $apiClient->getAllProducts(['product_type' => 'Karaoke']);
var_dump($remotepord);
//var_dump($remotepord);
die();
//var_dump($remotepord);
//die();
//$prod0 = $products[0];11
$lcoals = SyncManager::getMusicStoreProducts();

//var_dump(Product::fromProductProvider($lcoals[1])->putArray());
//die();
SyncManager::sync($apiClient, $lcoals, $remotepord);
die();
//var_dump($products);
echo "beep";
//var_dump($pro1->postArray());

$toPost->shopifyAPIResponse($apiClient->postProduct(Product::fromProductProvider($toPost)));

die();

$prod0 = Product::instancesFromArray($response->getResponseArray()['products']);
var_dump($prod0);
//echo implode("\n", array_keys($prod0->variants[0]));

echo implode("\n", array_keys($response->getResponseArray()['products'][0]));

//$response->debugPrint();

?>
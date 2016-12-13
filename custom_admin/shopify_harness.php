<?php

namespace jct;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use jct\Shopify\APIClient;
use jct\Shopify\Product;


$apiClient = new APIClient(Util::get_theme_option('shopify_api_key'),
                           Util::get_theme_option('shopify_api_password'),
                           Util::get_theme_option('shopify_handle'));


//var_dump($otherClient->makeCall('admin/custom_collections'));
$response = $apiClient->shopifyGet('admin/products.json');

echo "<pre>";

$prod0 = Product::instancesFromArray($response->getResponseArray()['products']);
var_dump($prod0);
//echo implode("\n", array_keys($prod0->variants[0]));

echo implode("\n", array_keys($response->getResponseArray()['products'][0]));

//$response->debugPrint();

?>
<?php

namespace jct\Shopify;


use GuzzleHttp\Client;

class APIClient extends Client {
    private $apiKey, $apiPassword, $storeHandle;

    public function __construct($apiKey, $apiPassword, $shopifyStoreHandle, $timeout = 2.0) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->storeHandle = $shopifyStoreHandle;
        $base_url = "https://$this->storeHandle.myshopify.com/";
        var_dump($base_url);

        parent::__construct(
            [    // Base URI is used with relative requests
                'base_uri' => $base_url,
                // You can set any number of default request options.
                'timeout'  => $timeout,
                'auth'     => [$this->apiKey, $this->apiPassword],
            ]
        );
    }


    public function shopifyGet($endPoint, $queryVars = []) {
        return new APIResponse($this->request('GET', $endPoint, ['query' => $queryVars]));
    }


}

?>
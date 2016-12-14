<?php

namespace jct\Shopify;


use GuzzleHttp\Client;
use jct\Util;

class APIClient extends Client {

    const DEFAULT_PAGE_SIZE = 250;
    const DEFAULT_TIMEOUT = 5.0;

    private $apiKey, $apiPassword, $storeHandle;

    public function __construct($apiKey, $apiPassword, $shopifyStoreHandle, $timeout = self::DEFAULT_TIMEOUT) {
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


    public function shopifyPagedGet($endPoint, $queryVars = [], $pageSize = self::DEFAULT_PAGE_SIZE, $specificPageOnly = null) {
        $queryVars['limit'] = $pageSize;

        $pagedResponse = [];
        $page = $specificPageOnly ? $specificPageOnly : 1;

        /** @var APIResponse $response */
        while(true) {
            $queryVars['page'] = $page;
            $response = $this->shopifyGet($endPoint, $queryVars);

            $pagedResponse[] = $response->getResponseArray();

            if($specificPageOnly || $response->countResponseElements() < $pageSize) {
                break;
            }

            $page++;
        }

        return Util::array_merge_flatten_1L($pagedResponse);
    }


    /**
     * @return APIResponse
     */
    public function shopifyGet($endPoint, $queryVars = []) {
        return new APIResponse($this->request('GET', $endPoint, ['query' => $queryVars]));
    }


}

?>
<?php

namespace jct\Shopify;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use jct\Shopify\Exception\APIResponseException;
use jct\Shopify\Exception\RateLimitException;
use jct\Util;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SynchronousAPIClient extends Client {

    const DEFAULT_PAGE_SIZE = 250;
    const DEFAULT_TIMEOUT = 5.0;
    const MAX_TRIES_REQUEST = 8;
    const CALL_LIMIT_HEADER = 'X-Shopify-Shop-Api-Call-Limit';
    const MINIMUM_CALL_LIMIT_HEAD_ROOM = 1;
    const RATE_LIMIT_SLEEP_MICROSECONDS = 1.1 * 1000000;

    const PRODUCTS_BULK_GET_ENDPOINT = 'admin/products.json';
    const PRODUCTS_CREATE_ENDPOINT = self::PRODUCTS_BULK_GET_ENDPOINT;

    private $apiKey, $apiPassword, $storeHandle, $lastCallLimitResponse;

    public function __construct($apiKey, $apiPassword, $shopifyStoreHandle, $timeout = self::DEFAULT_TIMEOUT) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->storeHandle = $shopifyStoreHandle;
        $base_url = "https://$this->storeHandle.myshopify.com/";

        // it is not that I did not read and spend an hour with this
        // http://docs.guzzlephp.org/en/latest/handlers-and-middleware.html
        // it is just that, after that hour, I had a function I could start testing that would
        // *maybe* work and that I knew I couldn't really debug... so we'll just
        // NOT deal with response exceptions inside guzzle and instead deal with them
        // in our own wrapper class, capiche?
        parent::__construct(
            [    // Base URI is used with relative requests
                'base_uri' => $base_url,
                // You can set any number of default request options.
                'timeout'  => $timeout,
                'auth'     => [$this->apiKey, $this->apiPassword],
            ]
        );
    }


    /** @return Product[] */
    public function getAllProducts() {
        $allProducts = $this->shopifyPagedGet(self::PRODUCTS_BULK_GET_ENDPOINT);
        //var_dump($allProducts);
        return Product::instancesFromArray($allProducts);
    }

    public function postProduct(Product $product) {
        return $this->shopifyPost(self::PRODUCTS_CREATE_ENDPOINT, ['product' => $product->postArray()]);
    }

    /**
     * @param $endPoint
     * @param array $queryVars
     * @param int $pageSize
     * @param null $specificPageOnly
     * @return Struct[]
     */
    public function shopifyPagedGet($endPoint, $queryVars = [], $pageSize = self::DEFAULT_PAGE_SIZE, $specificPageOnly = null) {
        $queryVars['limit'] = $pageSize;

        $pagedResponse = [];
        $page = $specificPageOnly ? $specificPageOnly : 1;

        /** @var SynchronousAPIResponse $response */
        while(true) {
            $queryVars['page'] = $page;
            $response = $this->shopifyGet($endPoint, $queryVars);

            $pagedResponse[] = $response;

            if($specificPageOnly || count($response) < $pageSize) {
                break;
            }

            $page++;
        }

        return Util::array_merge_flatten_1L($pagedResponse);
    }


    private function shopifyGet($endPoint, $queryVars = []) {
        return $this->shopifyRequest('GET', $endPoint, ['query' => $queryVars]);
    }

    private function shopifyPost($endPoint, $postArray) {
        return $this->shopifyRequest('POST', $endPoint, ['json' => $postArray]);
    }

    private function shopifyRequest($method, $uri = [], $options = []) {
        $options['headers'] = ['Content-Type' => 'application/json'];
        $options['allow_redirects'] = false;
        $options['http_errors'] = false;

        $tries = 0;
        while(true) {
            $this->preemptiveSleep();
            try {
                $response = $this->request($method, $uri, $options);
                break;

            } catch(RateLimitException $ex) {
                if($tries < self::MAX_TRIES_REQUEST) {
                    $this->rateLimitSleep();
                } else {
                    throw $ex;
                }
            }
            $tries++;
        }

        /** @noinspection PhpUndefinedVariableInspection */
        switch($response->getStatusCode()) {
            case 200:
            case 201:
                break;

            case 429:
                throw new RateLimitException();
                break;

            default:
                echo (string)$response->getBody();
                throw new APIResponseException();
                break;
        }

        $this->lastCallLimitResponse = $response->getHeaderLine(self::CALL_LIMIT_HEADER);

        return current(\json_decode((string)$response->getBody(), JSON_OBJECT_AS_ARRAY));
    }


    private function preemptiveSleep() {
        // e.g. X-Shopify-Shop-Api-Call-Limit: 32/40
        // https://help.shopify.com/api/guides/api-call-limit
        list($x, $ofY) = array_map('intval', explode('/', $this->lastCallLimitResponse));
        if(self::MINIMUM_CALL_LIMIT_HEAD_ROOM > ($ofY - $x)) {
            self::rateLimitSleep();
        }
    }

    private function rateLimitSleep() {
        usleep(self::RATE_LIMIT_SLEEP_MICROSECONDS);
    }

}

?>
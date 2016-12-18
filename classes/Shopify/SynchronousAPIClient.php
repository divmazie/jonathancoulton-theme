<?php

namespace jct\Shopify;


use GuzzleHttp\Client;
use jct\Shopify\Exception\APIResponseException;
use jct\Shopify\Exception\RateLimitException;
use jct\Util;

class SynchronousAPIClient extends Client {

    const DEFAULT_PAGE_SIZE = 250;
    const DEFAULT_TIMEOUT = 5.0;
    const MAX_TRIES_REQUEST = 1;
    const CALL_LIMIT_HEADER = 'X-Shopify-Shop-Api-Call-Limit';
    const MINIMUM_CALL_LIMIT_HEAD_ROOM = 4;
    // https://ecommerce.shopify.com/c/api-announcements/t/upcoming-change-in-api-limit-calculations-159710
    const RATE_LIMIT_SLEEP_MICROSECONDS = 505 * 1000;

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

    public function getMetafieldsForProduct(Product $product) {
        return Metafield::instancesFromArray($this->shopifyPagedGet(sprintf('/admin/products/%d/metafields.json', $product->id)));
    }

    public function putMetafield(Metafield $metafield) {
        $putR = $this->shopifyPut(sprintf('/admin/metafields/%s.json', $metafield->id),
                                  ['metafield' => $metafield->putArray()]);

        return Metafield::instanceFromArray($putR);
    }

    /**
     * @return Product
     */
    public function putProduct(Product $product) {
        $putProduct =
            Product::instanceFromArray($this->shopifyPut(sprintf('admin/products/%d.json', $product->id), ['product' => $product->putArray()]));

        return $putProduct;
    }

    /**
     * @return Product
     */
    public function deleteProduct(Product $product) {
        $this->shopifyRateLimitedRequest('DELETE', sprintf('admin/products/%d.json', $product->id));
    }


    /** @return Product */
    public function postProduct(Product $product) {
        $postedProduct =
            Product::instanceFromArray($this->shopifyPost('admin/products.json', ['product' => $product->postArray()]));
        $postedProduct->metafields = $this->getMetafieldsForProduct($postedProduct);
        return $postedProduct;
    }

    private function shopifyPost($endPoint, $array) {
        return $this->shopifyRateLimitedRequest('POST', $endPoint, ['json' => $array]);
    }

    /** @return Product */
    private function shopifyPut($endPoint, $array) {
        return $this->shopifyRateLimitedRequest('PUT', $endPoint, ['json' => $array]);
    }


    /** @return Product[] */
    public function getAllProducts($queryVars = [], $limitNumber = null) {
        $allProducts =
            $this->shopifyPagedGet('admin/products.json', $queryVars, $limitNumber, $limitNumber ? 1 : null);

        return Product::instancesFromArray($allProducts);
    }

    public function getAllCustomCollections($queryVars = [], $limitNumber = null) {
        $allCollections =
            $this->shopifyPagedGet('/admin/custom_collections.json', $queryVars, $limitNumber, $limitNumber ? 1 : null);

        return CustomCollection::instancesFromArray($allCollections);
    }

    /**
     * @param $endPoint
     * @param array $queryVars
     * @param int $pageSize
     * @param null $specificPageOnly
     * @return Struct[]
     */
    public function shopifyPagedGet($endPoint, $queryVars = [], $pageSize = null, $specificPageOnly = null) {
        $queryVars['limit'] = $pageSize;

        $pagedResponse = [];
        $pageSize = $pageSize ? $pageSize : self::DEFAULT_PAGE_SIZE;
        $page = $specificPageOnly ? $specificPageOnly : 1;

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
        return $this->shopifyRateLimitedRequest('GET', $endPoint, ['query' => $queryVars]);
    }

    private function shopifyRateLimitedRequest($method, $uri = [], $options = []) {
        $tries = 0;
        while(true) {
            $this->preemptiveSleep();
            try {
                return $this->shopifyRequest_impl($method, $uri, $options);
            } catch(RateLimitException $ex) {
                if($tries < self::MAX_TRIES_REQUEST) {
                    $this->rateLimitSleep();
                    $tries++;
                } else {
                    throw $ex;
                }
            }
        }
        return null;
    }

    private function shopifyRequest_impl($method, $uri = [], $options = []) {
        $options['headers'] = ['Content-Type' => 'application/json'];
        $options['allow_redirects'] = false;
        $options['http_errors'] = false;

        $response = $this->request($method, $uri, $options);

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

        if($this->lastCallLimitResponse &&
           self::MINIMUM_CALL_LIMIT_HEAD_ROOM >= ($ofY - $x)
        ) {
            var_dump(list($x, $ofY) = array_map('intval', explode('/', $this->lastCallLimitResponse)));
            var_dump($ofY - $x);
            self::rateLimitSleep();
        }
    }

    private function rateLimitSleep() {
        echo "rate limit sleep\n";
        usleep(self::RATE_LIMIT_SLEEP_MICROSECONDS);
    }

}

?>
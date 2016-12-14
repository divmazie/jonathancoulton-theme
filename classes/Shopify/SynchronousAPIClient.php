<?php

namespace jct\Shopify;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use jct\Shopify\Exception\RateLimitException;
use jct\Util;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SynchronousAPIClient extends Client {

    const DEFAULT_PAGE_SIZE = 250;
    const DEFAULT_TIMEOUT = 5.0;
    const MAX_TRIES_REQUEST = 8;

    private $apiKey, $apiPassword, $storeHandle;

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
        $stack = new HandlerStack();
        $stack->setHandler(\GuzzleHttp\choose_handler());
        $stack->remove('http_errors');

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
        $allProducts = $this->shopifyPagedGet('admin/products.json');
        //var_dump($allProducts);
        return Product::instancesFromArray($allProducts);
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

            $pagedResponse[] = $response->getResponseArray();

            if($specificPageOnly || $response->countResponseElements() < $pageSize) {
                break;
            }

            $page++;
        }

        return Util::array_merge_flatten_1L($pagedResponse);
    }


    /**
     * @return SynchronousAPIResponse
     */
    private function shopifyGet($endPoint, $queryVars = []) {
        return $this->shopifyRequest('GET', $endPoint, ['query' => $queryVars]);
    }

    /**
     * @return SynchronousAPIResponse
     */
    private function shopifyRequest($method, $uri = [], $options = []) {
        $tries = 0;
        while(true) {
            SynchronousAPIResponse::preemptiveSleep();
            try {
                return new SynchronousAPIResponse($this->request($method, $uri, $options));
            } catch(RateLimitException $ex) {
                if($tries < self::MAX_TRIES_REQUEST) {
                    SynchronousAPIResponse::rateLimitSleep();
                } else {
                    throw $ex;
                }
            }
            $tries++;
        }
        // should throw exception or return a response--should never reach here
        return null;
    }


}

?>
<?php

namespace jct\Shopify;

use jct\Shopify\Exception\APIResponseException;
use jct\Shopify\Exception\RateLimitException;
use Psr\Http\Message\ResponseInterface;

class SynchronousAPIResponse {

    const CALL_LIMIT_HEADER = 'X-Shopify-Shop-Api-Call-Limit';
    const MINIMUM_CALL_LIMIT_HEAD_ROOM = 1;
    // from inspecting responses
    const PROPER_CONTENT_TYPE = 'application/json; charset=utf-8';
    const RATE_LIMIT_SLEEP_MICROSECONDS = 1.1 * 1000000;

    static $lastCallLimitResponse;
    private $baseResponse;

    public function __construct(ResponseInterface $responseInterface) {
        $this->baseResponse = $responseInterface;

        $this->updateCallLimit();
        $this->handleResponseStatus();
    }

    private function updateCallLimit() {
        if($this->baseResponse->hasHeader(self::CALL_LIMIT_HEADER)) {
            self::$lastCallLimitResponse = $this->baseResponse->getHeaderLine(self::CALL_LIMIT_HEADER);
        }
    }

    /**
     * Basically do nothing if the request went through. Otherwise, throw an
     * exception and blow up the assumptions we'd make about this calss.
     *
     * @throws APIResponseException
     * @throws RateLimitException
     */
    private function handleResponseStatus() {
        //https://help.shopify.com/api/guides/response-status-codes
        switch($this->baseResponse->getStatusCode()) {
            case 200:
            case 201:
                if(@$this->baseResponse->getHeaderLine('Content-Type') !== self::PROPER_CONTENT_TYPE) {
                    throw new APIResponseException();
                }
                break;

            case 429:
                throw new RateLimitException();
                break;

            default:
                throw new APIResponseException();
                break;
        }
    }

    public function getResponseArray($unwrap = true) {
        $array = \json_decode($this->baseResponse->getBody(), JSON_OBJECT_AS_ARRAY);
        // shopify puts everything in a wrapper... usually there is just single wrapper, then
        // a bunch of sub elements in it
        if($unwrap) {
            $array = current($array);
        }
        return $array;
    }

    public function countResponseElements($unwrap = true) {
        return count($this->getResponseArray($unwrap));
    }

    public function debugPrint() {
        echo "<pre>";
        var_dump($this->getResponseArray());
        var_dump($this->baseResponse);
    }

    public static function preemptiveSleep() {
        // e.g. X-Shopify-Shop-Api-Call-Limit: 32/40
        // https://help.shopify.com/api/guides/api-call-limit
        list($x, $ofY) = array_map('intval', explode('/', self::$lastCallLimitResponse));
        if(self::MINIMUM_CALL_LIMIT_HEAD_ROOM > ($ofY - $x)) {
            self::rateLimitSleep();
        }
    }

    public static function rateLimitSleep() {
        usleep(self::RATE_LIMIT_SLEEP_MICROSECONDS);
    }


}
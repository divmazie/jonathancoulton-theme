<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/21/15
 * Time: 14:13
 */

namespace jct;


class Shopify {
    private $apiKey, $apiPassword, $handle;

    public function __construct($apiKey, $apiPassword, $handle) {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->handle = $handle;
    }

    public function makeCall($resource, $requestType = "GET", $args=array()) {
        $url = "https://".$this->apiKey.":".$this->apiPassword."@".$this->handle.".myshopify.com/".$resource.".json";
        if ($requestType == "GET") {
            $first = true;
            foreach ($args as $key => $val) {
                $key = urlencode($key);
                $val = urlencode($val);
                if ($first) {
                    $url .= "?$key=$val";
                } else {
                    $url .= "&$key=$val";
                }
                $first = false;
            }
        }
        $data_string = json_encode($args);
        //return $data_string;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Expect:'
            ]
        );
        if ($requestType != "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        $response = curl_exec($ch);
        return json_decode($response);
    }

    public function getProducts() {
        return $this->makeCall("admin/products");
    }

    public function createProduct($title,$image_encoded,$price,$formats) {
        $sku = "999";
        $variants = array();
        foreach ($formats as $format) {
            $variants[] = array(
                'option1' => $format,
                'price' => strval($price),
                'sku' => $sku,
                'taxable' => false,
                'requires_shipping' => false
            );
        }
        $args = array("product" => array(
            'title' => $title,
            'body_html' => "$title by Jonathan Coulton",
            'vendor' => "Jonathan Coulton",
            'product_type' => 'Music download',
            'images' => array(
                array('attachment' => $image_encoded)
            ),
            'variants' => $variants
        ));
        $response = $this->makeCall("admin/products","POST",$args);
        return $response;
    }

    public function attachImage($productId,$image_src) {
        $args = array('image' => array('src' => $image_src));
        return $this->makeCall("admin/products/#$productId/images","POST",$args);
    }

}
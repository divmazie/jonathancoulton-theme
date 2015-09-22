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

    static function sku($album,$track,$format) {
        // replace spaces with underscore
        $album = preg_replace('/\s/u', '_', $album);
        $track = preg_replace('/\s/u', '_', $track);
        $format = preg_replace('/\s/u', '_', $format);
        // remove non ascii alnum_ with
        $album = preg_replace('/[^\da-z_]/i', '', $album);
        $track = preg_replace('/[^\da-z_]/i', '', $track);
        $format = preg_replace('/[^\da-z_]/i', '', $format);

        // track number underscore track title underscore short hash dot extension
        return strtolower(sprintf('%s-%s:%s', $album, $track, $format));
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

    public function createProduct($args) {
        $response = $this->makeCall("admin/products","POST",$args);
        return $response;
    }

    public function createAlbumProduct($album) {
        $variants = array();
        foreach ($album->getAllChildZips() as $zip) {
            $sku = $this::sku($album->getAlbumTitle(),"full album",$zip->getEncodeLabel());
            $variants[] = array(
                //'title' => $album->getAlbumTitle()." (Full Album) ".$zip->getEncodeLabel(),
                'option1' => $zip->getEncodeLabel(),
                'price' => strval($album->getAlbumPrice()),
                'sku' => $sku,
                'taxable' => false,
                'requires_shipping' => false
            );
        }
        $args = array("product" => array(
            'title' => $album->getAlbumTitle()." (Full Album)",
            'body_html' => $album->getAlbumTitle()." (Full Album) by ".$album->getAlbumArtist()." ".$album->getAlbumYear(),
            'vendor' => "Jonathan Coulton",
            'product_type' => 'Music download',
            'images' => array(
                array('attachment' => base64_encode(file_get_contents($album->getAlbumArtObject()->getPath())))
            ),
            'variants' => $variants
        ));
        return $this->createProduct($args);
    }

    public function createTrackProduct($track) {
        $variants = array();
        foreach ($track->getAllChildEncodes() as $encode) {
            $sku = $this::sku($track->getAlbum()->getAlbumTitle(),$track->getTrackTitle(),$encode->getEncodeLabel());
            $variants[] = array(
                //'title' => $track->getTrackTitle()." ".$encode->getEncodeLabel(),
                'option1' => $encode->getEncodeLabel(),
                'price' => strval($track->getTrackPrice()),
                'sku' => $sku,
                'taxable' => false,
                'requires_shipping' => false
            );
        }
        $args = array("product" => array(
            'title' => $track->getTrackTitle(),
            'body_html' => "From ".$track->getAlbum()->getAlbumTitle()." by ".$track->getTrackArtist()." ".$track->getTrackYear(),
            'vendor' => "Jonathan Coulton",
            'product_type' => 'Music download',
            'images' => array(
                array('attachment' => base64_encode(file_get_contents($track->getTrackArtObject()->getPath())))
            ),
            'variants' => $variants
        ));
        return $this->createProduct($args);
    }

}
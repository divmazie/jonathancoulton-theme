<?php

namespace jct;

use FetchApp\API\Product as FetchProduct;

/**
 * The API does what we want, but it's a little weird about it
 * This is a bridge... and it breaks some rules
 */
class FetchProductUtil {

    public static function makeSerializable(FetchProduct $product) {
        $reflect = new \ReflectionClass($product);

        foreach($reflect->getProperties() as $reflectionProperty) {
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($product, self::sanitizeProductValueForSerialization($reflectionProperty->getValue($product)));
        }

        return $product;
    }

    public static function setUrls(FetchProduct $product, $urlsArray = []) {
        $reflect = new \ReflectionClass($product);

        $prop = $reflect->getProperty('item_urls');
        $prop->setAccessible(true);
        $prop->setValue($product, $urlsArray);
    }


    private static function sanitizeProductValueForSerialization($value) {
        if($value instanceof \SimpleXMLElement) {
            switch(@$value->attributes()['type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'float':
                    $value = (float)$value;
                    break;

                default:
                    $value = (string)$value;
            }
        }
        return $value;
    }

}
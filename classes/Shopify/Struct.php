<?php

namespace jct\Shopify;

use jct\Shopify\Exception\Exception;

class Struct {

    private static $id_map;

    // everything in the shopify system has an id... store it here
    public $id;


    protected function propertySet($propertyName, $property) {
        switch($propertyName) {
            case 'created_at':
            case 'updated_at':
            case 'published_at':
                $property = new \DateTime($property);
                break;
        }

        $this->{$propertyName} = $property;
    }

    protected function fieldSet($propertyArray) {
        foreach($propertyArray as $propertyName => $property) {
            if(property_exists(get_class($this), $propertyName)) {
                $this->propertySet($propertyName, $property);
            }
        }
    }


    /** @return static */
    public static function instanceFromArray($array) {
        $obj = new static();

        $obj->id = @$array['id'];
        if(!$obj->id) {
            throw new Exception('no id for resource');
        }

        // cache all our shit in this here map
        self::$id_map[$obj->id] = $obj;

        $obj->fieldSet($array);
        return $obj;
    }

    /** @return static[] */
    public static function instancesFromArray($array) {
        return array_map(function ($array) {
            return self::instanceFromArray($array);
        }, $array);
    }
}


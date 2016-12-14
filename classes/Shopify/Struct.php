<?php

namespace jct\Shopify;

use jct\Shopify\Exception\Exception;

abstract class Struct {

    private static $id_map;

    // everything in the shopify system has an id... store it here
    public $id;


    abstract protected function postProperties();

    abstract protected function putProperties();

    private function arrayForVerb($verb) {
        // put or postProperties
        $callable = [$this, mb_strtolower($verb) . 'Properties'];
        $topLevelArray = array_intersect_key(
        // filter out nulls
        // we get back the object vars that are not null that are in the VERB array
            array_filter(get_object_vars($this)),
            array_combine(call_user_func($callable), call_user_func($callable))
        );

        // we need to do this down the chain though
        array_walk_recursive($topLevelArray, function (&$param) use ($verb) {
            if($param instanceof self) {
                $param = $param->arrayForVerb($verb);
            }
        });

        return $topLevelArray;
    }

    public function postArray() {
        return $this->arrayForVerb('POST');
    }

    public function putArray() {
        return $this->arrayForVerb('PUT');
    }

    protected function setProperty($propertyName, $property) {
        switch($propertyName) {
            case 'created_at':
            case 'updated_at':
            case 'published_at':
                $property = new \DateTime($property);
                break;
        }

        $this->{$propertyName} = $property;
    }

    protected function setProperties($propertyArray) {
        foreach($propertyArray as $propertyName => $property) {
            if(property_exists(get_class($this), $propertyName)) {
                $this->setProperty($propertyName, $property);
            } else {
                throw new Exception('unanticipated property in response');
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

        $obj->setProperties($array);
        return $obj;
    }

    /** @return static[] */
    public static function instancesFromArray($array) {
        return array_map(function ($array) {
            return self::instanceFromArray($array);
        }, $array);
    }
}


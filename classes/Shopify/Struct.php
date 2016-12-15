<?php

namespace jct\Shopify;

use jct\Shopify\Exception\Exception;

abstract class Struct {

    const ID_MAP_DEFAULT_NAMESPACE = 0;
    const ID_MAP_BY_ID = 'id';
    const ID_MAP_BY_CLASS = 'class';
    const ID_MAP_BY_SKU = 'sku';

    private static $id_map;

    private $parent;

    // everything in the shopify system has an id... store it here
    public $id;

    public function __construct(Struct $parent = null) {
        $this->setParent($parent);
    }

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

    public function getParent() {
        return $this->parent;
    }

    public function hasParent() {
        return (bool)$this->getParent();
    }

    public function setParent(Struct $parent = null) {
        $this->parent = $parent;
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

    protected function setProperties(array $propertyArray) {
        foreach($propertyArray as $propertyName => $property) {
            if(property_exists(get_class($this), $propertyName)) {
                $this->setProperty($propertyName, $property);
            } else {
                throw new Exception('unanticipated property in response');
            }
        }
    }

    /** @return static */
    public static function instanceFromArray($array, Struct $parent = null, $namespace = self::ID_MAP_DEFAULT_NAMESPACE) {
        if(is_null($array)) {
            return null;
        }

        $obj = new static();
        $obj->setProperties($array);
        $obj->setParent($parent);

        if(!$obj->id) {
            throw new Exception('no id for resource');
        }

        // cache all our shit in this here map
        self::buildIDMap($obj);

        return $obj;
    }

    private static function buildIDMap(Struct $struct, $namespace = self::ID_MAP_DEFAULT_NAMESPACE) {
        $map = &self::$id_map[$namespace];

        $map[self::ID_MAP_BY_ID][$struct->id] = $struct;
        $map[self::ID_MAP_BY_CLASS][get_class($struct)][] = $struct;


        if(property_exists($struct, 'sku')) {
            /** @noinspection PhpUndefinedFieldInspection */
            if(isset($map[self::ID_MAP_BY_SKU][$struct->sku])) {
                throw new Exception('multiple structs with same sku');
            }
            /** @noinspection PhpUndefinedFieldInspection */
            $map[self::ID_MAP_BY_SKU][$struct->sku] = $struct;
        }
    }

    /** @return static[] */
    public static function instancesFromArray($array, Struct $parent = null) {
        return array_map(function ($childArray) use ($parent) {
            return self::instanceFromArray($childArray, $parent);
        }, $array);
    }

    public static function getObjectByID($id) {
        return static::$id_map[$id];
    }


}


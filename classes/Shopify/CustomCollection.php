<?php

namespace jct\Shopify;

use jct\Shopify\Exception\Exception;

class CustomCollection extends Struct {
    public
        $title,
        $body_html,
        $sort_order,
        $template_suffix,
        $image,
        $collects = [],
        $metafields = [],

        // unused/shopify default
        $handle,
        $updated_at,
        $published_at,
        $published_scope = 'global';


    protected function postProperties() {
        // post appears not to *work* if you attach collects...
        return ['title', 'body_html', 'template_suffix', 'image', 'metafields', 'collects', 'sort_order'];
    }

    protected function putProperties() {
        return array_merge(['id'], $this->postProperties());
    }


    protected function setProperty($propertyName, $property) {
        switch($propertyName) {
            case 'image':
                $property = Image::instanceFromArray($property, $this);
                break;
        }

        parent::setProperty($propertyName, $property);
    }

}


?>
<?php

namespace jct\Shopify;

class CustomCollection extends Struct {
    public
        $title,
        $body_html,
        $sort_order,
        $template_suffix,
        $image,
        $collects = [],

        // unused/shopify default
        $handle,
        $updated_at,
        $published_at,
        $published_scope = 'global';


    protected function postProperties() {
        return ['title', 'body_html', 'sort_order', 'template_suffix', 'image'];
    }

    protected function putProperties() {
        array_merge(['id'], $this->postProperties());
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
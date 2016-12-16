<?php

namespace jct\Shopify\Provider;

interface MetafieldProvider {

    public function getMetafieldID();

    public function getMetafieldNamespace();

    public function getMetafieldKey();

    public function getMetafieldValue();

    public function getMetafieldValueType();

}
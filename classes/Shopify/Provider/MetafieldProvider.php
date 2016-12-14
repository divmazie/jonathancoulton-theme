<?php

namespace jct\Shopify\Provider;

interface MetafieldProvider {

    public function getProductMetafieldNamespace();

    public function getProductMetafieldKey();

    public function getProductMetafieldValue();

    public function getProductMetafieldValueType();

}
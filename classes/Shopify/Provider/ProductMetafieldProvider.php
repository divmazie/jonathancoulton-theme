<?php

namespace jct\Shopify\Provider;

interface ProductMetafieldProvider {

    public function getProductMetafieldNamespace();

    public function getProductMetafieldKey();

    public function getProductMetafieldValue();

    public function getProductMetafieldValueType();

}
<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 11/16/15
 * Time: 17:03
 */

delete_transient('store_context');
$apiKey = get_field('shopify_api_key','options');
$apiPassword = get_field('shopify_api_password','options');
$handle = get_field('shopify_handle','options');
$shopify = new jct\Shopify($apiKey,$apiPassword,$handle);
$context['store'] = $shopify->getStoreContext();

header("Location: ".site_url()."/wp-admin/admin.php?page=theme-general-settings");
<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 11/16/15
 * Time: 17:03
 */

$apiKey = get_field('shopify_api_key','options');
$apiPassword = get_field('shopify_api_password','options');
$handle = get_field('shopify_handle','options');
$shopify = new jct\ShopifyAPIClient($apiKey, $apiPassword, $handle);
$albums_to_go = $shopify->getAlbumsFromShopify();
if ($albums_to_go>0) {
    reload($albums_to_go);
} else {
    $shopify->getAllShopifyContext();
    delete_transient('collections_to_go');
    delete_transient('temporary_albums_context');
    header("Location: ".site_url()."/wp-admin/admin.php?page=theme-general-settings");
}
function reload($albums_to_go) {
    ?>
    Hang on, still working...<br />
    <?=$albums_to_go?> albums left to get from Shopify...
    <script>
        window.onload = function() {
            window.location = '<?=get_site_url()?>/custom_admin/refresh_store';
        }
    </script>
    <?php
}
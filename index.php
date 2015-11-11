<?php
/**
 * The main template file
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

if ( !class_exists( 'Timber' ) ) {
    echo 'Timber not activated. Make sure you activate the plugin in <a href="/wp-admin/plugins.php#timber">/wp-admin/plugins.php</a>';
    return;
}
$context = Timber::get_context();
include_once(get_template_directory().'/include/sitewide_context.php');
$context['post'] = new TimberPost();
$context['showcase_tiles'] = Timber::get_posts('post_type=showcase_tile');
$context['blurb_header'] = get_field('front_page_blurb_header','options');
$context['blurb_content'] = get_field('front_page_blurb_content','options');
$context['twitter'] = include(get_template_directory().'/config/twitter.php');
$context['instagram'] = include(get_template_directory().'/config/instagram.php');
$context['facebook_link'] = get_field('facebook_link','options');
$apiKey = get_field('shopify_api_key','options');
$apiPassword = get_field('shopify_api_password','options');
$handle = get_field('shopify_handle','options');
$shopify = new jct\Shopify($apiKey,$apiPassword,$handle);
$store = $shopify->getStoreContext();
foreach ($store as $category) {
    if ($category['shopify_type'] == "Music download") {
        $context['albums'] = $category['products'];
    }
}
$context['store'] = $store;
$bandsintown = get_transient('bandsintown');
if (!$bandsintown) {
    $bandsintown = json_decode(file_get_contents("http://api.bandsintown.com/artists/jonathancoulton/events.json"));
    set_transient('bandsintown',$bandsintown,600);
}
$context['bandsintown'] = $bandsintown;

$templates = array( 'index.twig' );
if ( is_home() ) {
    array_unshift( $templates, 'home.twig' );
}
Timber::render( $templates, $context );

<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * To generate specific templates for your pages you can use:
 * /mytheme/views/page-mypage.twig
 * (which will still route through this PHP file)
 * OR
 * /mytheme/page-mypage.php
 * (in which case you'll want to duplicate this file and save to the above path)
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

$context = Timber::get_context();
include_once(get_template_directory().'/include/sitewide_context.php');
$post = new TimberPost();
$context['post'] = $post;
if ($post->slug == "faq") {
    $context['faqs'] = Timber::get_posts('post_type=faq');
}
if ($post->slug == "store") {
    $apiKey = get_field('shopify_api_key','options');
    $apiPassword = get_field('shopify_api_password','options');
    $handle = get_field('shopify_handle','options');
    $shopify = new jct\Shopify($apiKey,$apiPassword,$handle);
    $context['store'] = $shopify->getStoreContext();
}
Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
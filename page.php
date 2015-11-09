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
if ($post->slug == 'news') {
    global $paged;
    if (!isset($paged) || !$paged){
        $paged = 1;
    }
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => 4,
        'paged' => $paged
    );
    query_posts($args);
    $context['posts'] = Timber::get_posts();
    $context['pagination'] = Timber::get_pagination();
    $context['twitter'] = include(get_template_directory().'/config/twitter.php');
    $context['instagram'] = include(get_template_directory().'/config/instagram.php');
    $context['facebook_link'] = get_field('facebook_link','options');
}
Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
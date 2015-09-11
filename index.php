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

if ( ! class_exists( 'Timber' ) ) {
	echo 'Timber not activated. Make sure you activate the plugin in <a href="/wp-admin/plugins.php#timber">/wp-admin/plugins.php</a>';
	return;
}
$context = Timber::get_context();
$context['post'] = new TimberPost();
$context['showcase_tiles'] = Timber::get_posts('post_type=showcase_tile');
$context['faqs'] = Timber::get_posts('post_type=faq');
$context['blurb_header'] = get_field('front_page_blurb_header','options');
$context['blurb_content'] = get_field('front_page_blurb_content','options');
$twitter = include_once(get_template_directory().'/config/twitter.php');
$context['twitter'] = $twitter;
$context['instagram'] = include(get_template_directory().'/config/instagram.php');
$context['facebook_link'] = get_field('facebook_link','options');
$context['foo'] = 'bar';
$templates = array( 'index.twig' );
if ( is_home() ) {
	array_unshift( $templates, 'home.twig' );
}
$bandsintown = get_transient('bandsintown');
if (!$bandsintown) {
	$bandsintown = json_decode(file_get_contents("http://api.bandsintown.com/artists/jonathancoulton/events.json"));
	set_transient('bandsintown',$bandsintown,600);
}
//ob_start();
//the_bandsintown_events(array('artist' => 'Jonathan Coulton', 'display_limit' => 10));
//$bandsintown = ob_get_contents();
//ob_end_clean();
$context['bandsintown'] = $bandsintown;
Timber::render( $templates, $context );

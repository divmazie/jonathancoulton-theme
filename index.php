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

namespace jct;

if(!class_exists('Timber\Timber')) {
    die('you need to install the timber plugin for this theme to render things');
}

use Timber\Post;
use Timber\Timber;

$context = Timber::get_context();
$context['post'] = new Post();
$context['showcase_tiles'] = Timber::get_posts('post_type=showcase_tile');
$context['blurb_header'] = Util::get_theme_option('front_page_blurb_header');
$context['blurb_content'] = Util::get_theme_option('front_page_blurb_content');
$context['featured_albums'] = SyncManager::get_featured_albums();

$bandsInTown = get_site_transient('bandsintown');
if(!$bandsInTown) {
    // if we have nothing worthwhile stored there, delete it
    delete_site_transient('bandsintown');
    // checkit for lols https://www.bandsintown.com/api/authentication
    $url = sprintf("http://api.bandsintown.com/artists/jonathancoulton/events.json?api_version=2.0&app_id=%s",
                   Util::get_theme_option('bandsintown_app_id'));
    $bandsInTown = json_decode(file_get_contents($url), JSON_OBJECT_AS_ARRAY);

    set_site_transient('bandsintown', $bandsInTown, 1800);
}


$context['bandsintown'] = $bandsInTown;

$templates = ['index.twig'];

if(is_home() || is_front_page()) {
    array_unshift($templates, 'home.twig');
}
Timber::render($templates, $context);

<?php
namespace jct;

use Timber\Site;
use Timber\Timber;

require __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/include/tgm.php';


if(class_exists('Timber\Timber')) {
    new JCTSite();
}

add_action('wp_enqueue_scripts', function () {
    if(!is_admin()) {
        //wp_enqueue_style('bootstrap',get_template_directory_uri().'/css/bootstrap.css');
        wp_enqueue_script('bower', get_template_directory_uri() . '/js/bower.min.js');
        wp_enqueue_script('site', get_template_directory_uri() . '/js/site.js');
        wp_dequeue_script('jquery');
        wp_dequeue_script('jquery-core');
    }
});

if(function_exists('acf_add_options_page')) {
    acf_add_options_page([
                             'page_title' => 'Theme General Settings',
                             'menu_title' => 'Theme Settings',
                             'menu_slug'  => 'theme-general-settings',
                             'capability' => 'edit_posts',
                             'redirect'   => false,
                             'autoload'   => true,

                         ]);
}

add_action('init', function () {
    // register CPT not assoc with a class...
    \jct\Util::register_generic_cpt("Showcase Tile");
    \jct\Util::register_generic_cpt("FAQ");
    \jct\Util::register_generic_cpt('Album');
    \jct\Util::register_generic_cpt('Track');
});


include __DIR__ . '/include/routes.php';
include __DIR__ . '/include/filters.php';

// only include our prepop code, which does queries, if we need to
$prepopTransientKey = 'jct_prepop_mtime';
$postPrepopFile = __DIR__ . '/include/prepopulate.php';
if(filemtime($postPrepopFile) > intval(get_site_transient($prepopTransientKey))) {
    include $postPrepopFile;
    set_site_transient($prepopTransientKey, filemtime($postPrepopFile));
}

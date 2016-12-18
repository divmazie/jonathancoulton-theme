<?php

namespace jct;

use Timber\Menu;
use Timber\Site;
use Timber\Timber;

class JCTSite extends Site {

    function __construct() {
        add_theme_support('post-formats');
        add_theme_support('post-thumbnails');
        add_theme_support('menus');
        add_filter('timber_context', [$this, 'add_to_context']);
        add_filter('get_twig', [$this, 'add_to_twig']);
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        parent::__construct();
    }

    function add_to_context($context) {
        $context['menu'] = new Menu();
        $context['site'] = $this;
        // make get ACF theme-options automatically available in twig
        $context['theme_options'] = get_fields('options');
        $context['is_logged_in'] = is_user_logged_in();
        $context['get_vars'] = $_GET;

        $context['twitter'] = include(dirname(__DIR__) . '/config/twitter.php');
        $context['instagram'] = include(dirname(__DIR__) . '/config/instagram.php');
        $context['facebook_link'] = Util::get_theme_option('facebook_link');

        // we put these in a function to defer their execution until a template calls them
        return $context;
    }

    function add_to_twig(\Twig_Environment $twig) {
        /* this is where you can add your own fuctions to twig */
        $twig->addExtension(new \Twig_Extension_StringLoader());
        $twig->addExtension(new \Twig_Extension_Debug());
        $twig->addFilter('myfoo', new \Twig_Filter_Function('myfoo'));
        return $twig;
    }


}
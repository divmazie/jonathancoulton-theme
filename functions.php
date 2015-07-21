<?php

if ( ! class_exists( 'Timber' ) ) {
	add_action( 'admin_notices', function() {
			echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php' ) ) . '</a></p></div>';
		} );
	return;
}

class StarterSite extends TimberSite {

	function __construct() {
		add_theme_support( 'post-formats' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'menus' );
		add_filter( 'timber_context', array( $this, 'add_to_context' ) );
		add_filter( 'get_twig', array( $this, 'add_to_twig' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		parent::__construct();
	}

	function register_post_types() {
		//this is where you can register custom post types
	}

	function register_taxonomies() {
		//this is where you can register custom taxonomies
	}

	function add_to_context( $context ) {
		$context['foo'] = 'bar';
		$context['stuff'] = 'I am a value set in your functions.php file';
		$context['notes'] = 'These values are available everytime you call Timber::get_context();';
		$context['menu'] = new TimberMenu();
		$context['site'] = $this;
		return $context;
	}

	function add_to_twig( $twig ) {
		/* this is where you can add your own fuctions to twig */
		$twig->addExtension( new Twig_Extension_StringLoader() );
		$twig->addFilter( 'myfoo', new Twig_Filter_Function( 'myfoo' ) );
		return $twig;
	}

}

new StarterSite();

function myfoo( $text ) {
	$text .= ' bar!';
	return $text;
}

wp_enqueue_style('bootstrap',get_template_directory_uri().'/css/bootstrap.css');
wp_enqueue_script('bower',get_template_directory_uri().'/js/bower.min.js');
wp_enqueue_script('site',get_template_directory_uri().'/js/site.js');

if( function_exists('acf_add_options_page') ) {
	acf_add_options_page(array(
		'page_title' => 'Theme General Settings',
		'menu_title' => 'Theme Settings',
		'menu_slug' => 'theme-general-settings',
		'capability' => 'edit_posts',
		'redirect' => false
	));
	acf_add_local_field_group(array (
		'key' => 'group_55ad56ef6b3ea',
		'title' => 'Options',
		'fields' => array (
			array(
				'key' => 'field_54b83b55ed730',
				'label' => 'Footer left column header',
				'name' => 'footer_left_header',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'default_value' => 'Contact / Press',
				'placeholder' => ''
			),
			array(
				'key' => 'field_54b83bbced731',
				'label' => 'Footer left column text',
				'name' => 'footer_left_text',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'default_value' => '',
				'placeholder' => ''
			)
		),
		'location' => array (
			array (
				array (
					'param' => 'options_page',
					'operator' => '==',
					'value' => 'theme-general-settings',
				),
			),
		),
		'menu_order' => 1,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
	));
}
<?php

function register_generic_cpt($name, $plural_name="") {
    if(!isset($plural_name) || $plural_name == "") {
        $plural_name = $name . "s";
    }
    if(ctype_upper($name)) {
        $up_name = $name;
        $up_plural_name = $plural_name;
    } else {
        $up_name = ucwords($name);
        $up_plural_name = ucwords($plural_name);
    }
    $down_name = preg_replace('/\s+/', '_', strtolower($name));
    register_post_type($down_name, array(
        'label' => $down_name,
        'description' => '',
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'rewrite' => array(
            'slug' => $down_name,
            'with_front' => true
        ),
        'query_var' => true,
        'supports' => array(
            'title',
            'editor',
            'excerpt',
            'trackbacks',
            'custom-fields',
            'comments',
            'revisions',
            'thumbnail',
            'author',
            'page-attributes',
            'post-formats',
            'wpcom-markdown'
        ),
        'labels' => array(
            'name' => $up_plural_name,
            'singular_name' => $up_name,
            'menu_name' => $up_plural_name,
            'add_new' => 'Add ' . $up_name,
            'add_new_item' => 'Add New ' . $up_name,
            'edit' => 'Edit',
            'edit_item' => 'Edit ' . $up_name,
            'new_item' => 'New ' . $up_name,
            'view' => 'View ' . $up_plural_name,
            'view_item' => 'View ' . $up_plural_name,
            'search_items' => 'Search ' . $up_plural_name,
            'not_found' => 'No ' . $up_plural_name . ' Found',
            'not_found_in_trash' => 'No ' . $up_plural_name . ' Found in Trash',
            'parent' => 'Parent ' . $up_name,
        )
    ));
}

function register_my_cpt_showcase_tile() {
    register_generic_cpt("Showcase Tile");
}
add_action('init', 'register_my_cpt_showcase_tile');

function register_my_cpt_album() {
    register_generic_cpt("Album");
}
add_action('init', 'register_my_cpt_album'); // fields: artist, year, genre, art

function register_my_cpt_track() {
    register_generic_cpt("Track");
}
add_action('init', 'register_my_cpt_track'); // fields: artist, album, source

function register_my_cpt_faq() {
    register_generic_cpt("FAQ");
}
add_action('init', 'register_my_cpt_faq'); // fields: artist, album, source
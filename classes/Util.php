<?php

namespace jct;

use Timber\Timber;

class Util {

    public static function get_site_url() {
        return get_site_url();
    }

    public static function get_posts_cached($args, $returnClass, $prepopValue = null, $prepopNull = false) {
        static $res_cache = [];

        // if someone passes us an ID as a string... convert it here,
        // treat as the same
        if(!is_array($args)) {
            $args = intval($args);
        }

        // cache_key is just exactly how we were asked for the valuepre
        $cache_key = md5(serialize([$args, $returnClass]));

        // if we are actually just prepopulating the cache, do so and return
        if($prepopValue || $prepopNull) {
            return $res_cache[$cache_key] = $prepopValue;
        }

        // if we have a cached value, return with it
        if(isset($res_cache[$cache_key])) {
            return $res_cache[$cache_key];
        }

        if($args) {
            if(is_array($args)) {
                $result = Timber::get_posts($args, $returnClass);
            } else {
                $result = Timber::get_post($args, $returnClass);
            }

            return $res_cache[$cache_key] = $result;
        }

        throw new JCTException('attempt to get_posts_cached with null $args');
    }

    /**
     * @return array
     */
    public static function get_encode_types() {
        static $encodeTypes = null;
        if($encodeTypes) {
            return $encodeTypes;
        }
        return $encodeTypes = include(dirname(__DIR__) . '/config/encode_types.php');
    }

    public static function get_user_option($option_name) {
        return get_field($option_name, 'options');
    }

    public static function register_generic_cpt($name, $plural_name = "") {
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
        register_post_type($down_name, [
            'label'           => $down_name,
            'description'     => '',
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'hierarchical'    => false,
            'rewrite'         => [
                'slug'       => $down_name,
                'with_front' => true,
            ],
            'query_var'       => true,
            'supports'        => [
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
                'wpcom-markdown',
            ],
            'labels'          => [
                'name'               => $up_plural_name,
                'singular_name'      => $up_name,
                'menu_name'          => $up_plural_name,
                'add_new'            => 'Add ' . $up_name,
                'add_new_item'       => 'Add New ' . $up_name,
                'edit'               => 'Edit',
                'edit_item'          => 'Edit ' . $up_name,
                'new_item'           => 'New ' . $up_name,
                'view'               => 'View ' . $up_plural_name,
                'view_item'          => 'View ' . $up_plural_name,
                'search_items'       => 'Search ' . $up_plural_name,
                'not_found'          => 'No ' . $up_plural_name . ' Found',
                'not_found_in_trash' => 'No ' . $up_plural_name . ' Found in Trash',
                'parent'             => 'Parent ' . $up_name,
            ],
        ]);
    }

}
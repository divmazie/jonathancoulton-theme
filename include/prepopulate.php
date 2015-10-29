<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/10/15
 * Time: 13:52
 */

$pieces = array(// ORIGINAL PREPOP ARRAY, WILL DISCARD
    array(
        'post_name' => 'faq',
        'post_title' => 'FAQ',
        'post_content' => "Questions you may have.",
        'post_type' => 'page',
    ),
    array(
        'post_name' => 'store',
        'post_title' => 'Store',
        'post_content' => "",
        'post_type' => 'page',
    ),
    array(
        'post_name' => 'news',
        'post_title' => 'News',
        'post_content' => "",
        'post_type' => 'page',
    )
);

foreach($pieces as $piece) {
    if(!get_page_by_path($piece['post_name'], OBJECT, $piece['post_type'])) {
        wp_insert_post(array(
            'post_name' => $piece['post_name'],
            'post_title' => $piece['post_title'],
            'post_content' => $piece['post_content'],
            'post_status' => 'publish',
            'post_type' => $piece['post_type'],
            'ping_status' => 'closed'
        ), true);
    }
}
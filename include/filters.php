<?php

add_filter('upload_mimes', function ($existing_mimes = []) {
    // add as many as you like e.g.
    $existing_mimes['flac'] = 'audio/x-flac';
    return $existing_mimes;
});

add_filter( 'img_caption_shortcode', 'my_img_caption_shortcode', 10, 3 );

function my_img_caption_shortcode( $empty, $attr, $content ){
    $attr = shortcode_atts( array(
        'id'      => '',
        'align'   => 'alignnone',
        'width'   => '',
        'caption' => ''
    ), $attr );

    if ( 1 > (int) $attr['width'] || empty( $attr['caption'] ) ) {
        return '';
    }

    if ( $attr['id'] ) {
        $attr['id'] = 'id="' . esc_attr( $attr['id'] ) . '" ';
    }

    return '<div ' . $attr['id']
    . 'class="wp-caption ' . esc_attr( $attr['align'] ) . '" '
    . 'style="max-width: ' . ( 10 + (int) $attr['width'] ) . 'px;">'
    . do_shortcode( $content )
    . '<p class="wp-caption-text">' . $attr['caption'] . '</p>'
    . '</div>';

}
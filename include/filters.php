<?php

add_filter('upload_mimes', function ($existing_mimes = []) {
    // add as many as you like e.g.
    $existing_mimes['flac'] = 'audio/x-flac';
    return $existing_mimes;
});

add_filter('img_caption_shortcode', function ($empty, $attr, $content) {
    $attr = shortcode_atts([
                               'id'      => '',
                               'align'   => 'alignnone',
                               'width'   => '',
                               'caption' => '',
                           ], $attr);

    if(1 > (int)$attr['width'] || empty($attr['caption'])) {
        return '';
    }

    if($attr['id']) {
        $attr['id'] = 'id="' . esc_attr($attr['id']) . '" ';
    }

    return '<div ' . $attr['id']
           . 'class="wp-caption ' . esc_attr($attr['align']) . '" '
           . 'style="max-width: ' . (10 + (int)$attr['width']) . 'px;">'
           . do_shortcode($content)
           . '<p class="wp-caption-text">' . $attr['caption'] . '</p>'
           . '</div>';

}, 10, 3);

// from https://codex.wordpress.org/Plugin_API/Filter_Reference/wp_get_attachment_url
// and https://wordimpress.com/image-urls-forcing-ssl-wordpress/
add_filter('wp_get_attachment_url', function ($url) {
    list($protocol, $uri) = explode('://', $url, 2);
    return sprintf('http%s://%s', is_ssl() ? 's' : '', $uri);
});


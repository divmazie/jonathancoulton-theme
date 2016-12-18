<?php
/**
 * The Template for displaying all single posts
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */
namespace jct;

use Timber\Helper;
use Timber\Post;
use Timber\Timber;

$context = Timber::get_context();
$post = new Post();
$context['post'] = $post;
$context['comment_form'] = Helper::get_comment_form();

if(post_password_required($post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render(['single-' . $post->ID . '.twig', 'single-' . $post->post_type . '.twig', 'single.twig'], $context);
}

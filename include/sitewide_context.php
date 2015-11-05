<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/15/15
 * Time: 15:51
 */

if (!isset($context)) {
    $context = array();
}

$context['is_logged_in'] = is_user_logged_in();
$context['faqs'] = Timber::get_posts('post_type=faq');
$context['archives'] = Timber::get_posts('numberposts=3');
$context['get_vars'] = $_GET;
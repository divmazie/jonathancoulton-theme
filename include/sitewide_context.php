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

$context['faqs'] = Timber::get_posts('post_type=faq');
$context['archives'] = Timber::get_posts('numberposts=3');
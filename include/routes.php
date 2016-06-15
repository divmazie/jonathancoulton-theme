<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/4/15
 * Time: 16:01
 */

function authcode_valid($code) { // Check against transient with encoder validation
    if ($code==get_transient('do_secret'))
        return true;
    else
        return false; // Change this to false when actually want to test!
}

Routes::map('api/:authcode/:script/:var', function($params){
    if (authcode_valid($params['authcode'])) {
        include get_template_directory()."/api/".$params['script'].".php";
        die();
    } else {
        Routes::load("404.php");
    }
});

Routes::map('custom_admin/:script', function($params){
    if (current_user_can('manage_options')) {
        Routes::load('custom_admin/'.$params['script'].".php");
    } else {
        Routes::load("404.php");
    }
});

Routes::map('wiki/:wikipage', function($params) {
    $redirect_location = get_field('joco_wiki_base_url','options').$params['wikipage'];
    header('Location: '.$redirect_location,TRUE,301);
    die();
});
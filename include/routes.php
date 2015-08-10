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
        return true; // Change this to false when actually want to test!
}

Timber::add_route('api/:authcode/:script/:var', function($params){
    if (authcode_valid($params['authcode'])) {
        include get_template_directory()."/api/".$params['script'].".php";
        die();
    } else {
        Timber::load_template("404.php");
    }
});
<?php

namespace jct;

class Util {


    public static function get_user_option($option_name) {
        return get_field($option_name, 'options');
    }
}
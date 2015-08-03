<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/31/15
 * Time: 17:44
 */

function base64_url_encode($input) {
    return strtr(base64_encode($input), '+/=', '-_~');
}

if (get_transient('encodes_needed') && !get_transient('do_secret')) {
    $do_secret = base64_url_encode(openssl_random_pseudo_bytes(18, $did));
    set_transient('do_secret',$do_secret,60*60*24);
    echo "authcode set!";
    // Spin up digital ocean droplet with do_secret as an authcode

} else {
    echo "No action needed.";
}
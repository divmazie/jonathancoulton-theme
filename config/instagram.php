<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/11/15
 * Time: 13:35
 */

$number_images = 3;

// Return cached tweets if we have them, doesn't call api more than once per minute
$from_transient = get_transient('instagram_context');
if ($from_transient) {
    return array_merge($from_transient,array('from'=>'transient'));
}

use MetzWeb\Instagram\Instagram;

$handle = get_field('instagram_handle','options');
$user_id = 1593277106; // This is a realjonathancoulton's user-id hardcoded, see http://jelled.com/instagram/lookup-user-id#
$client_id = get_field('instagram_client_id','option'); // API key was set up by David (Instagram: divmazie), get from him or register new one
$instagram = new Instagram($client_id);
$response = $instagram->getUserMedia($user_id,$number_images);
//return $response->data;
$media = array();
foreach ($response->data as $datum) {
    $media[] = array('link' => $datum->link, 'thumb' => $datum->images->thumbnail->url);
}
$instagram_context = array('handle' => $handle, 'url' => "https://instagram.com/$handle", 'media' => $media);
set_transient('instagram_context',$instagram_context,60);

return array_merge($instagram_context,array('from'=>'api'));
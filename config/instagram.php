<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/11/15
 * Time: 13:35
 */

namespace jct;

$number_images = 3;

// Return cached tweets if we have them, doesn't call api more than once per minute
$from_transient = get_site_transient('instagram_context');
if($from_transient) {
    return array_merge($from_transient, ['from' => 'transient']);
}

$handle = Util::get_theme_option('instagram_handle');

$json = @file_get_contents("https://www.instagram.com/$handle/media/");
$instagram = json_decode($json);
$media = [];
$i = 1;
foreach($instagram->items as $item) {
    $media[] = ['link' => $item->link, 'thumb' => $item->images->thumbnail->url];
    $i++;
    if($i > $number_images) {
        break;
    }
}
$instagram_context = ['handle' => $handle, 'url' => "https://instagram.com/$handle", 'media' => $media];
set_site_transient('instagram_context', $instagram_context, 60);

return array_merge($instagram_context, ['from' => 'api']);
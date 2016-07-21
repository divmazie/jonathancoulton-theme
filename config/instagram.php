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

$handle = get_field('instagram_handle','options');

$json = file_get_contents("https://www.instagram.com/$handle/media/");
$insta = json_decode($json);
$media = array();
$i = 1;
foreach ($insta->items as $item) {
    $media[] = array('link' => $item->link, 'thumb' => $item->images->thumbnail->url);
    $i++;
    if ($i > $number_images) break;
}
$instagram_context = array('handle' => $handle, 'url' => "https://instagram.com/$handle", 'media' => $media);
set_transient('instagram_context',$instagram_context,60);

return array_merge($instagram_context,array('from'=>'api'));
<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 9/21/15
 * Time: 15:06
 */

$apiKey = get_field('shopify_api_key','options');
$apiPassword = get_field('shopify_api_password','options');
$handle = get_field('shopify_handle','options');
$shopify = new jct\Shopify($apiKey,$apiPassword,$handle);

$album_num = $_GET['album'];
$step_num = $_GET['step'];
if (!$album_num) {
    $album_num = 0;
}
if (!$step_num) {
    $step_num = 0;
}
if ($album_num==0 && $step_num==0) {
    delete_transient('missing_files');
}
$albums = \jct\Album::getAllAlbums();
$missing_files = get_transient('missing_files');
if ($missing_files === false) {
    $missing_files = array();
}
if ($album_num<count($albums)) {
    $album = $albums[$album_num];
    if ($step_num==0) {
        $album->cleanAttachments();
        $response = $album->syncToStore($shopify,$step_num);
        reload($album_num,1);
    } elseif ($step_num==1) {
        $response = $album->syncToStore($shopify,$step_num);
        $missing_files = array_merge($missing_files, array_values($response['missing_files']));
        set_transient('missing_files',$missing_files);
        reload($album_num,2);
    } else {
        $album->syncCollection($shopify);
        reload($album_num+1,0);
    }
} else {
    if ($step_num == 0) {
        $response = $shopify->syncEverythingProduct($albums);
        $missing_files = array_merge($missing_files, array_values($response['missing_files']));
        set_transient('missing_files',$missing_files);
        reload($album_num,1);
    } else if ($step_num==1) {
        $shopify->deleteUnusedProducts($albums);
        $shopify->deleteUnusedCollections($albums);
        reload($album_num,2);
    } else if ($step_num==2) {
        $shopify->deleteUnusedFetchProducts($albums);
        reload($album_num, 3);
    } else {
        $context = Timber::get_context();
        $context['missing_files'] = $missing_files;
        Timber::render("store_sync.twig", $context);
    }
}
function reload($album,$step) {
    $next_gets = "album=$album&step=$step";
    ?>
    Hang on, still working...<br />
    Synced <?=$album?> albums...
    <script>
        window.onload = function() {
            window.location = '<?=get_site_url()?>/custom_admin/store_sync?<?=$next_gets?>';
        }
    </script>
    <?php
}
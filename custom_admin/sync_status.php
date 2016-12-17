<?php

namespace jct;


use Timber\Timber;

SyncManager::optimizeQueries();
//die();


$syncMan = $context['sync_man'] = new SyncManager(Util::get_shopify_api_client(), Util::get_fetch_api_client());
$context['status'] = @urldecode($_GET['status']);

function status_message_loc($statusMessage) {
    return './?status=' . urlencode($statusMessage);
}

switch(@$_GET['pipeline_stage']) {
    case 'encodes':
        $syncMan->doEncodes();
        Util::redirect(status_message_loc("Encoding in progress. Resubmission will not speed the process. Reload the page to see new encodes come in."));
        break;

    case 'zips':
        $syncMan->doZips(status_message_loc("Should be all zipped up"));
        break;

    case 's3':
        $syncMan->doS3(status_message_loc("Think we uploaded it all!"));
        break;

    case 'shopify_cache':
        $filename = $syncMan->cacheRemoteShopifyProducts();
        Util::redirect(status_message_loc("Remote Products cached in $filename. Thx! "));
        break;

    case 'shopify_create':
        $syncMan->doShopifyCreates(status_message_loc("we create the products, for you!"));
        break;

    case 'shopify_update':
        $syncMan->doShopifyUpdates(status_message_loc("updates completed"));
        break;

    case 'shopify_force_update':
        $syncMan->forceShopifyUpdates(status_message_loc("forced updates completed"));
        break;

    case 'shopify_delete':
        $syncMan->doShopifyDeletes(status_message_loc("shopify deletions completed (we think--maybe refresj the cache and check?)"));
        break;

    case 'fetch_cache':
        $filename = $syncMan->cacheRemoteFetchProducts();
        Util::redirect(status_message_loc("Fetch cached in $filename. Thx!"));
        break;

    case 'fetch_create':
        $syncMan->doFetchCreates(status_message_loc("We created those fetch products!"));
        break;

    case 'fetch_update':
        $syncMan->doFetchUpdates(status_message_loc("We updated those fetch products!"));
        break;

    case 'fetch_delete':
        $syncMan->doFetchDeletes(status_message_loc("We deleted those products! Felt good."));
        break;

    case 'garbage':
        $syncMan->deleteGarbage(status_message_loc("We deleted that garbage."));
        break;

}

$context['status'] = @$_GET['status'];

Timber::render("sync_status.twig", $context);

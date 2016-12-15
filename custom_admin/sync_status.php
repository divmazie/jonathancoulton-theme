<?php

namespace jct;


use Timber\Timber;

ThemeObjectRepository::optimizeQueries();
//die();


$context['albums'] = Album::getAll();


$allEncodes = $context['all_encodes'] = EncodeConfig::getAll();
$pendingEncodes = $context['pending_encodes'] = EncodeConfig::getPending();
$canEncode = $context['can_encode'] = (bool)count($pendingEncodes);

$allZips = $context['all_zips'] = AlbumZipConfig::getAll();

/** @var AlbumZipConfig[] $pendingZips */
$pendingZips = $context['pending_zips'] = AlbumZipConfig::getPending();
$canZip = $context['can_zip'] = !$canEncode && $pendingZips;

/** @var EncodedAsset[] $uploadableAssets */
$uploadableAssets = array_merge(Encode::getAll(), AlbumZip::getAll());
$assetCount = $context['total_asset_count'] = count($allEncodes) + count($allZips);
/** @var EncodedAsset[] $unUploadedAssets */
$unUploadedAssets = $context['un_uploaded_assets'] =
    array_filter($uploadableAssets, function (EncodedAsset $encodedAsset) {
        return $encodedAsset->shouldUploadToS3();
    });

$uploadedAssets = $context['uploaded_assets'] =
    array_filter($uploadableAssets, function (EncodedAsset $encodedAsset) {
        return $encodedAsset->isUploadedToS3();
    });
$canUpload = $context['can_upload'] = count($unUploadedAssets);


$garbage = $context['garbage_attachments'] = array_diff_key($uploadableAssets, array_merge($allZips, $allEncodes));

function status_redirect($statusMessage) {
    Util::redirect('./?status=' . urlencode($statusMessage));
    exit();
}

function maintenance_redirect() {
    Util::redirect('./?' . $_SERVER['QUERY_STRING']);
    exit();
}

function loopForX(callable $loopFunction, $array, $xSeconds = 44) {
    $endAt = time() + $xSeconds;
    $counter = 0;
    // needs to be numerically addressasble
    $array = array_values($array);

    while($counter < count($array) && time() < $endAt) {
        $item = $array[$counter];
        $loopFunction($item);
        $counter++;
    }
}

switch(@$_GET['pipeline_stage']) {
    case 'encodes':
        EncodeConfig::postEncodeConfigsToEncodeBot($pendingEncodes);
        status_redirect("Encoding in progress. Resubmission will not speed the process. Reload the page to see new encodes come in.");
        break;

    case 'zips':
        if($pendingZips) {
            loopForX(function (AlbumZipConfig $zipConfig) {
                $zipConfig->createZip();
            }, $pendingZips);
            maintenance_redirect();
        } else {
            status_redirect("Should be all zipped up");
        }
        break;

    case 's3':
        if($unUploadedAssets) {
            loopForX(function (EncodedAsset $encodedAsset) {
                $encodedAsset->uploadToS3();
            }, $unUploadedAssets);
            maintenance_redirect();

        } else {
            status_redirect("Think we uploaded it all!");
        }

        break;

    case 'garbage':
        if($garbage) {
            loopForX(function (EncodedAsset $encodedAsset) {
                $encodedAsset->deleteAttachment(true);
            }, $garbage);
            maintenance_redirect();

        } else {
            status_redirect("Think we uploaded it all!");
        }

        break;

}

$context['status'] = @$_GET['status'];

Timber::render("sync_status.twig", $context);

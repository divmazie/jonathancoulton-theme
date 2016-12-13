<?php

namespace jct;


use Timber\Timber;

$context['albums'] = Album::getAllAlbums();


$allEncodes = $context['all_encodes'] = EncodeConfig::getAll();
$pendingEncodes = $context['pending_encodes'] = EncodeConfig::getPending();
$canEncode = $context['can_encode'] = (bool)count($pendingEncodes);

$allZips = $context['all_zips'] = AlbumZipConfig::getAll();
$pendingZips = $context['pending_zips'] = AlbumZipConfig::getPending();
$canZip = $context['can_zip'] = !$canEncode;

$canUpload = $context['can_upload'] = !($canEncode || $canZip);
/** @var EncodedAsset[] $uploadableAssets */
$uploadableAssets = array_merge(Encode::getAllOfClass(), AlbumZip::getAllOfClass());
$assetCount = $context['total_asset_count'] = count($allEncodes) + count($allZips);
$unUploadedAssets = $context['un_uploaded_assets'] =
    array_filter($uploadableAssets, function (EncodedAsset $encodedAsset) {
        return $encodedAsset->shouldUploadToS3();
    });

$uploadedAssets = $context['uploaded_assets'] =
    array_filter($uploadableAssets, function (EncodedAsset $encodedAsset) {
        return $encodedAsset->isUploadedToS3();
    });

Timber::render("sync_status.twig", $context);

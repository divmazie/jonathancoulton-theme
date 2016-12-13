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


Timber::render("sync_status.twig", $context);

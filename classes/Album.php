<?php

namespace jct;

use Timber\Timber;

class Album extends ShopifyProduct {

    const CPT_NAME = 'album';

    private $albumArtObject, $albumBonusAssetObjects, $albumShow;
    private $albumTracks = [];

    // meta fields acf will load (here for autocomplete purposes)=
    public $album_artist, $album_price, $album_year, $album_genre, $album_art, $album_comment, $album_sort_order, $album_description, $shopify_collection_id, $bonus_assets, $show_album_in_store;

    /**
     * @param \WP_Post $postObject the post in the blog that forms the base of this
     * album. The post contains ACF fields and post_meta data that will define the
     * internal variables of this class
     **/
    public function __construct($pid) {
        //$this->shopify_id = get_post_meta($post_id,'shopify_id',false)[0];
        //$this->shopify_variant_ids = unserialize(get_post_meta($post_id,'shopify_variant_ids',false)[0]);
        //$this->shopify_variant_skus = unserialize(get_post_meta($post_id,'shopify_variant_skus',false)[0]);
        //$this->shopify_collection_id = get_post_meta($post_id,'shopify_collection_id',false)[0];
        // Tracks now gotten in getAlbumTracks() to reduce database hits
        parent::__construct($pid);
    }

    public function getAlbumTitle() {
        return $this->title();
    }

    public function getTitle() {
        return $this->getAlbumTitle();
    }

    public function getAlbumArtist() {
        return $this->album_artist;
    }

    public function getAlbumPrice() {
        return abs(intval($this->album_price));
    }

    public function getAlbumYear() {
        return $this->album_year;
    }

    public function getAlbumGenre() {
        return $this->album_genre;
    }

    public function getAlbumArtObject() {
        return Timber::get_post($this->album_art, AlbumArt::class);
    }

    public function getAlbumComment() {
        return $this->album_comment;
    }

    public function getAlbumSortOrder() {
        return $this->album_sort_order;
    }

    public function getAlbumDescription() {
        return $this->album_description;
    }

    public function getShopifyCollectionId() {
        return $this->shopify_collection_id;
    }

    public function setShopifyCollectionId($id) {
        $this->update('shopify_collection_id', $id);
        $this->shopify_collection_id = $id;
    }

    public function getAlbumShow() {
        return $this->show_album_in_store;
    }

    // @return Track[] the album tracks IN ORDER
    public function getAlbumTracks() {
        return Track::getTracksForAlbum($this);
    }

    /**
     * @return BonusAsset[]
     */
    public function getAlbumBonusAssetObjects() {
        return Timber::get_posts([
                                     'post__in'    => array_map(function ($idx) {
                                         // get the ids of each of the bonus assets
                                         return $this->{sprintf('bonus_assets_%d_bonus_asset', $idx)};
                                         // bonus_assets is the number of
                                     }, range(0, intval($this->bonus_assets) - 1)),
                                     // the things ACF will put in here are pretty widely variable
                                     // so
                                     'post_type'   => 'any',
                                     'post_status' => 'any',
                                 ], BonusAsset::class);
    }


    public function syncToStore($shopify, $step = 0) {
        if($step == 0) {
            $context = [];
            $context['sync_responses'] = [];
            $response = $shopify->syncProduct($this);
            $context['sync_responses'][] = $response['response'];
            $missing_files = $response['missing_files'];
            $track_product_ids = [0 => $response['response']->product->id];
        } else {
            $context = get_transient('temp_context');
            $missing_files = get_transient('temp_missing_files');
            $track_product_ids = get_transient('track_product_ids');
        }
        $tracks = $this->getAlbumTracks();
        $track_counter = 0;
        foreach($tracks as $track) {
            if(($step == 0 && $track_counter < 8) || ($step > 0 && $track_counter >= 8)) {
                $response = $track->syncToStore($shopify);
                $context['sync_responses'][] = $response['response'];
                $missing_files = array_merge($missing_files, $response['missing_files']);
                $track_product_ids[intval($track->getTrackNumber())] = $response['response']->product->id;
            }
            $track_counter++;
        }
        set_transient('track_product_ids', $track_product_ids);
        if($step == 0) {
            set_transient('temp_context', $context);
            set_transient('temp_missing_files', $missing_files);
        } else {
            delete_transient('temp_context');
            delete_transient('temp_missing_files');
            //$context['collection_sync_response'] = $shopify->syncAlbumCollection($this,$track_product_ids);
            $missing_files_context = $missing_files;
            $context['missing_files'] = $missing_files_context;
        }
        return $context;
    }

    public function syncCollection($shopify) {
        $track_product_ids = get_transient('track_product_ids');
        $response = $shopify->syncAlbumCollection($this, $track_product_ids);
        delete_transient('track_product_ids');
        return $response;
    }

    public function getAlbumContext() {
        $context = ['title' => $this->getAlbumTitle(), 'artist' => $this->getAlbumArtist()];
        $context['show_album'] = $this->getAlbumShow() ? true : false;
        $context['encode_worthy'] = $this->isEncodeWorthy() ? true : false;
        //$context['year'] = $this->getAlbumYear();
        //$context['price'] = $this->getAlbumPrice();
        $context['sort_order'] = $this->getAlbumSortOrder();
        $context['sort_order_conflict'] = false;
        $context['art'] = $this->getAlbumArtObject() ?
            [
                'filename' => basename($this->getAlbumArtObject()->getPath()),
                'url'      => $this->getAlbumArtObject()->getURL(),
                'exists'   => file_exists($this->getAlbumArtObject()->getPath()),
            ]
            : ['filename' => 'MISSING!!!', 'url' => 'MISSING!!!', 'exists' => false];
        $context['album_zips'] = [];
        foreach($this->getAllChildZips() as $zip) {
            $zip_context = $zip->getZipContext();
            if(!is_array($zip_context)) {
                $zip_context = ['exists' => false];
            }
            $context['album_zips'][] = $zip_context;
        }
        $context['tracks'] = [];
        foreach($this->getAlbumTracks() as $key => $track) {
            $context['tracks'][$key] = $track->getTrackContext();
            if($key > 1000) {
                $context['tracks'][$key]['track_num_conflict'] = true;
            }
        }
        $context['bonus_assets'] = [];
        foreach($this->getAlbumBonusAssetObjects() as $bonus_asset) {
            $context['bonus_assets'][] =
                ['filename' => basename($bonus_asset->getPath()), 'exists' => file_exists($bonus_asset->getPath())];
        }
        return $context;
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if($this->getAlbumShow() && $this->getAlbumTitle() && $this->getAlbumArtist() && $this->getAlbumArtObject()) {
            $worthy = true;
        }
        return $worthy;
    }

    public function getNeededEncodes() {
        if(!$this->isEncodeWorthy()) {
            return false;
        }
        $zips = $this->getAllChildZips();
        $all_zips_exist = true;
        foreach($zips as $zip) {
            if(!$zip->fileAssetExists()) {
                $all_zips_exist = false;
            }
        }
        if($all_zips_exist) {
            return false;
        }
        $encodes = [];
        foreach($this->getAlbumTracks() as $track) {
            $track_encodes = $track->getNeededEncodes();
            if($track_encodes) {
                $encodes = array_merge($encodes, $track_encodes);
            }
        }
        return $encodes;
    }

    public function getAllChildZips() {
        $encode_types = include(dirname(__DIR__) . '/config/encode_types.php');

        $zips = [];
        foreach($encode_types as $key => $encode_type) {
            $label = $key;
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $zips[$label] = $this->getChildZip($format, $flags, $label);
        }
        return $zips;
    }

    public function getChildZip($format, $flags, $label = '') {
        if(!$label) {
            $label = $format;
        }
        $zip = new AlbumZip($this, $format, $flags, $label);
        return $zip;
    }

    public function cleanAttachments() {
        $deleted = $this->deleteOldZips();
        foreach($this->getAlbumTracks() as $track) {
            $deleted = array_merge($deleted, $track->deleteOldEncodes());
        }
        return $deleted;
    }

    public function deleteOldZips() {
        $goodKeys = [];
        foreach($this->getAllChildZips() as $zip) {
            $goodKeys[] = $zip->getUniqueKey();
        }
        return AlbumZip::deleteOldAttachments($this->postID, $goodKeys);
    }

    public function getNumberOfAlbumTracks() {
        return count($this->getAlbumTracks());
    }


    public static function getAllAlbums() {
        return Timber::get_posts(['post_type' => 'album', 'numberposts' => -1], Album::class);
    }


}
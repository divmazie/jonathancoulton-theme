<?php

namespace jct;

use Timber\Timber;

class Album extends ShopifyProduct {

    const CPT_NAME = 'album';

    // meta fields acf will load (here for autocomplete purposes)
    public $album_artist, $album_price, $album_year, $album_genre, $album_art, $album_comment, $album_sort_order, $album_description, $shopify_collection_id, $bonus_assets, $show_album_in_store;

    public function __construct($pid) {
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

    /** @return CoverArt */
    public function getAlbumArtObject() {
        return Util::get_posts_cached($this->album_art, CoverArt::class);
    }

    // @return Track[] the album tracks IN ORDER
    public function getAlbumTracks() {
        return Track::getTracksForAlbum($this);
    }

    /**
     * @return BonusAsset[]
     */
    public function getAlbumBonusAssetObjects() {
        return Util::get_posts_cached([
                                          'post__in'    => array_map(function ($idx) {
                                              // get the ids of each of the bonus assets
                                              return $this->{sprintf('bonus_assets_%d_bonus_asset', $idx)};
                                              // bonus_assets is the number of
                                          }, range(0, intval($this->bonus_assets) -
                                                      1)),
                                          // the things ACF will put in here are pretty widely variable
                                          // so
                                          'post_type'   => 'any',
                                          'post_status' => 'any',
                                      ], BonusAsset::class);
    }

    public function getAllChildZips() {
        $encode_types = Util::get_encode_types();

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
        return ($this->getAlbumShow() && $this->getAlbumTitle() && $this->getAlbumArtist() &&
                $this->getAlbumArtObject());
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


    /** @return Album[] */
    public static function getAllAlbums() {
        return Util::get_posts_cached(['post_type' => self::CPT_NAME, 'numberposts' => -1], Album::class);
    }

    /** @return Album */
    public static function getAlbumByID($id) {
        return Util::get_posts_cached($id, self::class);
    }


}
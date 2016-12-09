<?php

namespace jct;

class Album extends ShopifyProduct {


    private $albumTitle, $albumArtist, $albumPrice, $albumYear, $albumGenre, $albumComment, $albumArtObject, $albumBonusAssetObjects, $albumShow, $albumSortOrder, $albumDescription;
    // the parent post object
    private $encode_types,$wpPost;
    //
    private $albumTracks = array();
    private $shopify_collection_id,$shopify_collect_ids;

    /**
     * @param \WP_Post $postObject the post in the blog that forms the base of this
     * album. The post contains ACF fields and post_meta data that will define the
     * internal variables of this class
     **/
    public function __construct(\WP_Post $post) {
        $post_id = $post->ID;
        $this->postID = $post_id;
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
        $this->albumTitle = $post->post_title;
        //$this->albumArtist = get_field('album_artist',$post_id);
        //$this->albumPrice = get_field('album_price',$post_id);
        //$this->albumYear = get_field('album_year',$post_id);
        //$this->albumGenre = get_field('album_genre',$post_id);
        //$this->albumComment = get_field('album_comment',$post_id);
        //$this->albumArtObject = get_field('album_art',$post_id) ? new WPAttachment(get_field('album_art',$post_id)) : false;
        /*
        $this->albumBonusAssetObjects = array();
        $bonus_asset_rows = get_field('bonus_assets',$post_id);
        if (is_array($bonus_asset_rows)) {
            foreach ($bonus_asset_rows as $row) {
                $this->albumBonusAssetObjects[] = new WPAttachment($row['bonus_asset']);
            }
        }
        */
        //$this->albumShow = get_field('show_album_in_store',$post_id);
        //$this->albumSortOrder = get_field('album_sort_order',$post_id);
        $this->encode_types = include(get_template_directory().'/config/encode_types.php');
        //$this->shopify_id = get_post_meta($post_id,'shopify_id',false)[0];
        //$this->shopify_variant_ids = unserialize(get_post_meta($post_id,'shopify_variant_ids',false)[0]);
        //$this->shopify_variant_skus = unserialize(get_post_meta($post_id,'shopify_variant_skus',false)[0]);
        //$this->shopify_collection_id = get_post_meta($post_id,'shopify_collection_id',false)[0];
        // Tracks now gotten in getAlbumTracks() to reduce database hits
    }

    static function getAllAlbums() {
        $albums = array();
        $album_posts = get_posts(array('post_type' => 'album','numberposts' => -1));
        foreach ($album_posts as $album_post) {
            $album = new Album($album_post);
            if ($album->getAlbumShow())
                $albums[] = $album;
        }
        return $albums;
    }

    public function getAlbumContext() {
        $context = array('title' => $this->getAlbumTitle(), 'artist' => $this->getAlbumArtist());
        $context['show_album'] = $this->getAlbumShow() ? true : false;
        $context['encode_worthy'] = $this->isEncodeWorthy() ? true : false;
        //$context['year'] = $this->getAlbumYear();
        //$context['price'] = $this->getAlbumPrice();
        $context['sort_order'] = $this->getAlbumSortOrder();
        $context['sort_order_conflict'] = false;
        $context['art'] = $this->getAlbumArtObject() ?
            array('filename'=> basename($this->getAlbumArtObject()->getPath()),
                'url'=>$this->getAlbumArtObject()->getURL(),
                'exists'=>file_exists($this->getAlbumArtObject()->getPath()))
            : array('filename'=>'MISSING!!!', 'url'=>'MISSING!!!', 'exists'=>false);
        $context['album_zips'] = array();
        foreach ($this->getAllChildZips() as $zip) {
            $zip_context = $zip->getZipContext();
            if (!is_array($zip_context)) $zip_context = array('exists'=>false);
            $context['album_zips'][] = $zip_context;
        }
        $context['tracks'] = array();
        foreach ($this->getAlbumTracks() as $key => $track) {
            $context['tracks'][$key] = $track->getTrackContext();
            if ($key>1000) {
                $context['tracks'][$key]['track_num_conflict'] = true;
            }
        }
        $context['bonus_assets'] = array();
        foreach ($this->getAlbumBonusAssetObjects() as $bonus_asset) {
            $context['bonus_assets'][] = array('filename'=>basename($bonus_asset->getPath()),'exists'=>file_exists($bonus_asset->getPath()));
        }
        return $context;
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if ($this->getAlbumShow() && $this->getAlbumTitle() && $this->getAlbumArtist() && $this->getAlbumArtObject()) {
            $worthy = true;
        }
        return $worthy;
    }

    public function getNeededEncodes() {
        if (!$this->isEncodeWorthy()) {
            return false;
        }
        $zips = $this->getAllChildZips();
        $all_zips_exist = true;
        foreach ($zips as $zip) {
            if (!$zip->fileAssetExists()) $all_zips_exist = false;
        }
        if ($all_zips_exist) return false;
        $encodes = array();
        foreach ($this->getAlbumTracks() as $track) {
            $track_encodes = $track->getNeededEncodes();
            if ($track_encodes) {
                $encodes = array_merge($encodes, $track_encodes);
            }
        }
        return $encodes;
    }

    public function getAllChildZips() {
        $zips = array();
        foreach ($this->encode_types as $key => $encode_type) {
            $label = $key;
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $zips[$label] = $this->getChildZip($format,$flags,$label);
        }
        return $zips;
    }

    public function getChildZip($format,$flags,$label='') {
        if (!$label) {
            $label = $format;
        }
        $zip = new AlbumZip($this,$format,$flags,$label);
        return $zip;
    }

    public function cleanAttachments() {
        $deleted = $this->deleteOldZips();
        foreach ($this->getAlbumTracks() as $track) {
            $deleted = array_merge($deleted,$track->deleteOldEncodes());
        }
        return $deleted;
    }

    public function deleteOldZips() {
        $goodKeys = array();
        foreach ($this->getAllChildZips() as $zip) {
            $goodKeys[] = $zip->getUniqueKey();
        }
        return AlbumZip::deleteOldAttachments($this->postID,$goodKeys);
    }

    public function getNumberOfAlbumTracks() {
        return count($this->getAlbumTracks());
    }

    // @return array the album tracks IN ORDER
    public function getAlbumTracks() {
        if (!count($this->albumTracks)) {
            $tracks = get_posts(array('post_type' => 'track', 'posts_per_page' => -1, 'meta_key' => 'track_album', 'meta_value' => $this->postID));
            foreach ($tracks as $track) {
                $track_num = intval(get_field('track_number', $track->ID));
                while (isset($this->albumTracks[$track_num])) {
                    $track_num = 1000 + $track_num;
                }
                $this->albumTracks[$track_num] = new Track($track, $this);
            }
            ksort($this->albumTracks);
        }
        return $this->albumTracks;
    }

    public function getPostID() {
        return $this->postID;
    }

    /**
     * @return mixed
     */
    public function getAlbumTitle() {
        return $this->albumTitle;
    }
    public function getTitle() { return $this->getAlbumTitle(); }

    /**
     * @return mixed
     */
    public function getAlbumArtist() {
        if (!isset($this->albumArtist)) $this->albumArtist = get_field('album_artist',$this->postID);
        return $this->albumArtist;
    }

    public function getAlbumPrice() {
        if (!isset($this->albumPrice)) $this->albumPrice = get_field('album_price',$this->postID);
        return abs(intval($this->albumPrice));
    }

    /**
     * @return mixed
     */
    public function getAlbumYear() {
        if (!isset($this->albumYear)) $this->albumYear = get_field('album_year',$this->postID);
        return $this->albumYear;
    }

    /**
     * @return mixed
     */
    public function getAlbumGenre() {
        if (!isset($this->albumGenre)) $this->albumGenre = get_field('album_genre',$this->postID);
        return $this->albumGenre;
    }

    /**
     * @return mixed
     */
    public function getAlbumArtObject() {
        if (!isset($this->albumArtObject)) $this->albumArtObject = get_field('album_art',$this->postID) ? new WPAttachment(get_field('album_art',$this->postID)) : false;
        return $this->albumArtObject;
    }

    /**
     * @return mixed
     */
    public function getAlbumBonusAssetObjects() {
        if (!isset($this->albumBonusAssetObjects)) {
            $this->albumBonusAssetObjects = array();
            $bonus_asset_rows = get_field('bonus_assets',$this->postID);
            if (is_array($bonus_asset_rows)) {
                foreach ($bonus_asset_rows as $row) {
                    $this->albumBonusAssetObjects[] = new WPAttachment($row['bonus_asset']);
                }
            }
        }
        return $this->albumBonusAssetObjects;
    }

    public function getAlbumShow() {
        if (!isset($this->albumShow)) $this->albumShow = get_field('show_album_in_store',$this->postID);
        return $this->albumShow;
    }

    /**
     * @return mixed
     */
    public function getAlbumComment() {
        if (!isset($this->albumComment)) $this->albumComment = get_field('album_comment',$this->postID);
        return $this->albumComment;
    }

    public function getAlbumSortOrder() {
        if (!isset($this->albumSortOrder)) $this->albumSortOrder = get_field('album_sort_order',$this->postID);
        return $this->albumSortOrder;
    }

    public function getAlbumDescription() {
        if (!isset($this->albumDescription)) $this->albumDescription = get_field('album_description',$this->postID);
        return $this->albumDescription;
    }

    public function getShopifyCollectionId() {
        if (!isset($this->shopify_collection_id)) $this->shopify_collection_id = get_post_meta($this->postID,'shopify_collection_id',false)[0];
        return $this->shopify_collection_id;
    }

    public function setShopifyCollectionId($id) {
        if (update_post_meta($this->postID,'shopify_collection_id',$id)) {
            $this->shopify_collection_id = $id;
        }
    }

    public function syncToStore($shopify, $step=0) {
        if ($step == 0) {
            $context = array();
            $context['sync_responses'] = array();
            $response = $shopify->syncProduct($this);
            $context['sync_responses'][] = $response['response'];
            $missing_files = $response['missing_files'];
            $track_product_ids = array(0 => $response['response']->product->id);
        } else {
            $context = get_transient('temp_context');
            $missing_files = get_transient('temp_missing_files');
            $track_product_ids = get_transient('track_product_ids');
        }
        $tracks = $this->getAlbumTracks();
        $track_counter = 0;
        foreach ($tracks as $track) {
            if (($step==0 && $track_counter<8) || ($step>0 && $track_counter>=8)) {
                $response = $track->syncToStore($shopify);
                $context['sync_responses'][] = $response['response'];
                $missing_files = array_merge($missing_files, $response['missing_files']);
                $track_product_ids[intval($track->getTrackNumber())] = $response['response']->product->id;
            }
            $track_counter++;
        }
        set_transient('track_product_ids',$track_product_ids);
        if ($step==0) {
            set_transient('temp_context', $context);
            set_transient('temp_missing_files',$missing_files);
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
        $response = $shopify->syncAlbumCollection($this,$track_product_ids);
        delete_transient('track_product_ids');
        return $response;
    }

}
<?php

namespace jct;

class Album extends ShopifyProduct {


    private $albumTitle, $albumArtist, $albumPrice, $albumYear, $albumGenre, $albumComment, $albumArtObject, $albumBonusAssetObjects, $albumShow, $albumSortOrder;
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
        $this->albumArtist = get_field('album_artist',$post_id);
        $this->albumPrice = get_field('album_price',$post_id);
        $this->albumYear = get_field('album_year',$post_id);
        $this->albumGenre = get_field('album_genre',$post_id);
        $this->albumComment = get_field('album_comment',$post_id);
        $this->albumArtObject = get_field('album_art',$post_id) ? new WPAttachment(get_field('album_art',$post_id)) : false;
        $this->albumBonusAssetObjects = array();
        $bonus_asset_rows = get_field('bonus_assets',$post_id);
        if (is_array($bonus_asset_rows)) {
            foreach ($bonus_asset_rows as $row) {
                $this->albumBonusAssetObjects[] = new WPAttachment($row['bonus_asset']);
            }
        }
        $this->albumShow = get_field('show_album_in_store',$post_id);
        $this->albumSortOrder = get_field('album_sort_order',$post_id);
        $this->encode_types = include(get_template_directory().'/config/encode_types.php');
        $this->shopify_id = get_post_meta($post_id,'shopify_id',false)[0];
        $this->shopify_variant_ids = unserialize(get_post_meta($post_id,'shopify_variant_ids',false)[0]);
        $this->shopify_variant_skus = unserialize(get_post_meta($post_id,'shopify_variant_skus',false)[0]);
        $this->shopify_collection_id = get_post_meta($post_id,'shopify_collection_id',false)[0];
        $tracks = get_posts(array('post_type' => 'track', 'posts_per_page' => -1, 'meta_key' => 'track_album', 'meta_value' => $post_id)); // Constructor probs shouldn't do this lookup
        foreach ($tracks as $track) {
            $track_num = intval(get_field('track_number', $track->ID));
            while (isset($this->albumTracks[$track_num])) {
                $track_num = 1000 + $track_num;
            }
            $this->albumTracks[$track_num] = new Track($track, $this);
        }
        ksort($this->albumTracks);
    }

    static function getAllAlbums() {
        $albums = array();
        $album_posts = get_posts(array('post_type' => 'album','numberposts' => -1));
        foreach ($album_posts as $album_post) {
            $albums[] = new Album($album_post);
        }
        return $albums;
    }

    public function getAlbumContext() {
        $context = array('title' => $this->getAlbumTitle(), 'artist' => $this->getAlbumArtist());
        $context['show_album'] = $this->albumShow ? true : false;
        $context['encode_worthy'] = $this->isEncodeWorthy() ? true : false;
        $context['year'] = $this->getAlbumYear();
        $context['price'] = $this->getAlbumPrice();
        $context['sort_order'] = $this->getAlbumSortOrder();
        $context['sort_order_conflict'] = false;
        $context['art'] = $this->getAlbumArtObject() ?
            array('filename'=> basename($this->getAlbumArtObject()->getPath()),
                'url'=>$this->getAlbumArtObject()->getURL(),
                'exists'=>file_exists($this->getAlbumArtObject()->getPath()))
            : array('filename'=>'MISSING!!!', 'url'=>'MISSING!!!', 'exists'=>false);
        $context['album_zips'] = array();
        foreach ($this->getAllChildZips() as $zip) {
            $context['album_zips'][] = $zip->getZipContext();
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
        if ($this->albumShow && $this->albumTitle && $this->albumArtist && $this->albumArtObject) {
            $worthy = true;
        }
        return $worthy;
    }

    public function getNeededEncodes() {
        if (!$this->isEncodeWorthy()) {
            return false;
        }
        $encodes = array();
        foreach ($this->albumTracks as $track) {
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
        return count($this->albumTracks);
    }

    // @return array the album tracks IN ORDER
    public function getAlbumTracks() {
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
        return $this->albumArtist;
    }

    public function getAlbumPrice() {
        return abs(intval($this->albumPrice));
    }

    /**
     * @return mixed
     */
    public function getAlbumYear() {
        return $this->albumYear;
    }

    /**
     * @return mixed
     */
    public function getAlbumGenre() {
        return $this->albumGenre;
    }

    /**
     * @return mixed
     */
    public function getAlbumArtObject() {
        return $this->albumArtObject;
    }

    /**
     * @return mixed
     */
    public function getAlbumBonusAssetObjects() {
        return $this->albumBonusAssetObjects;
    }

    /**
     * @return mixed
     */
    public function getAlbumComment() {
        return $this->albumComment;
    }

    public function getAlbumSortOrder() {
        return $this->albumSortOrder;
    }

    public function getShopifyCollectionId() {
        return $this->shopify_collection_id;
    }

    public function setShopifyCollectionId($id) {
        if (update_post_meta($this->postID,'shopify_collection_id',$id)) {
            $this->shopify_collection_id = $id;
        }
    }

    public function syncToStore($shopify) {
        $context = array();
        $context['sync_responses'] = array();
        $response = $shopify->syncProduct($this);
        $context['sync_responses'][] = $response['response'];
        $missing_files = $response['missing_files'];
        $track_product_ids = array(0=>$response['response']->product->id);
        $tracks = $this->getAlbumTracks();
        foreach ($tracks as $track) {
            $response = $track->syncToStore($shopify);
            $context['sync_responses'][] = $response['response'];
            $missing_files = array_merge($missing_files,$response['missing_files']);
            $track_product_ids[intval($track->getTrackNumber())] = $response['response']->product->id;
        }
        set_transient('track_product_ids',$track_product_ids);
        //$context['collection_sync_response'] = $shopify->syncAlbumCollection($this,$track_product_ids);
        $missing_files_context = array();
        foreach ($missing_files as $missing_file) {
            $format = $missing_file['format'];
            if (!isset($missing_files_context[$format])) {
                $zip = $this->getAllChildZips()[$format];
                $missing_files_context[$format] = array('zip'=>array('filename'=>$zip->getFileAssetFileName(),'url'=>$zip->getURL()),'files'=>array());
            }
            $missing_files_context[$format]['files'][] = $missing_file['filename'];
        }
        $context['missing_files'] = $missing_files_context;
        return $context;
    }

    public function syncCollection($shopify) {
        $track_product_ids = get_transient('track_product_ids');
        $response = $shopify->syncAlbumCollection($this,$track_product_ids);
        delete_transient('track_product_ids');
        return $response;
    }

}
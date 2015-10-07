<?php

namespace jct;

class Track extends ShopifyProduct {

    private $trackNumber, $trackPrice, $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileObject;
    private $wpPost, $encode_types;
    private $parentAlbum;

    /**
     * @param \WP_Post $post the parent post object whence the fields
     *
     */
    public function __construct(\WP_Post $post, Album $parentAlbum = NULL) {
        if (!$parentAlbum) {
            $parent_post = get_field('track_album',$post->ID);
            $parentAlbum = new Album($parent_post);
        }
        $post_id = $post->ID;
        $this->postID = $post_id;
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
        $this->parentAlbum = $parentAlbum;
        $this->trackTitle = $post->post_title;
        $this->trackNumber = get_field('track_number',$post_id);
        $this->trackPrice = get_field('track_price',$post_id);
        $this->trackArtist = get_field('track_artist',$post_id);
        $this->trackGenre = get_field('track_genre',$post_id);
        $this->trackYear = get_field('track_year',$post_id);
        $this->trackComment = get_field('track_comment',$post_id);
        $this->trackArtObject = get_field('track_art',$post_id) ? new WPAttachment(get_field('track_art',$post_id)) : false;
        $this->trackSourceFileObject = get_field('track_source',$post_id) ? new WPAttachment(get_field('track_source',$post_id)) : false;
        $this->encode_types = include(get_template_directory().'/config/encode_types.php');
        $this->shopify_id = get_post_meta($post_id,'shopify_id',false)[0];
        $this->shopify_variant_ids = unserialize(get_post_meta($post_id,'shopify_variant_ids',false)[0]);
        $this->shopify_variant_skus = unserialize(get_post_meta($post_id,'shopify_variant_skus',false)[0]);
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if ($this->parentAlbum->isEncodeWorthy()) {
            //$worthy = true;
            if ($this->trackTitle && $this->getTrackArtist() && $this->trackSourceFileObject && $this->getTrackArtObject()) {
                $worthy = true;
            }
        }
        return $worthy;
    }

    public function getAllChildEncodes() {
        $encodes = array();
        foreach ($this->encode_types as $key => $encode_type) {
            $label = $key;
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $encodes[$label] = $this->getChildEncode($format,$flags,$label);
        }
        return $encodes;
    }

    public function getChildEncode($format,$flags,$label) {
        $encode = new Encode($this, $format, $flags, $label);
        return $encode;
    }

    public function deleteOldEncodes() {
        $goodKeys = array();
        foreach ($this->getAllChildEncodes() as $encode) {
            $goodKeys[] = $encode->getUniqueKey();
        }
        return Encode::deleteOldAttachments($this->postID,$goodKeys);
    }

    public function getNeededEncodes() {
        if(!$this->isEncodeWorthy()) {
            return false;
        }
        $needed_encodes = array();
        foreach($this->getAllChildEncodes() as $encode) {
            if($encode->encodeIsNeeded()) {
                $needed_encodes[] = $encode;
            }
        }
        return $needed_encodes;
    }

    public function getAlbum() {
        return $this->parentAlbum;
    }

    public function getPostID() {
        return $this->postID;
    }

    /**
     * @return mixed
     */
    public function getTrackTitle() {
        return $this->trackTitle;
    }
    public function getTitle() { return $this->getTrackTitle(); }

    /**
     * @return mixed
     */
    public function getTrackNumber() {
        return abs(intval($this->trackNumber));
    }

    public function getTrackPrice() {
        return abs(intval($this->trackPrice));
    }


    /**
     * @return mixed
     */
    public function getTrackArtist() {
        return $this->trackArtist ? $this->trackArtist : $this->parentAlbum->getAlbumArtist();
    }

    /**
     * @return mixed
     */
    public function getTrackGenre() {
        return $this->trackGenre ? $this->trackGenre : $this->parentAlbum->getAlbumGenre();
    }

    /**
     * @return mixed
     */
    public function getTrackYear() {
        return $this->trackYear ? $this->trackYear : $this->parentAlbum->getAlbumYear();
    }

    /**
     * @return mixed
     */
    public function getTrackComment() {
        return $this->trackComment ? $this->trackComment : $this->parentAlbum->getAlbumComment();
    }

    /**
     * @return mixed
     */
    public function getTrackArtObject() {
        return $this->trackArtObject ? $this->trackArtObject : $this->parentAlbum->getAlbumArtObject();
    }

    public function getTrackSourceFileObject() {
        return $this->trackSourceFileObject;
    }

    public function getTrackContext() {
        $context = array('title' => $this->getTrackTitle(), 'artist' => $this->getTrackArtist());
        $context['number'] = $this->getTrackNumber();
        $context['price'] = $this->getTrackPrice();
        $context['encode_worthy'] = $this->isEncodeWorthy();
        $context['art'] = array('filename'=>basename($this->getTrackArtObject()->getPath()),'exists'=>file_exists($this->getTrackArtObject()->getPath()));
        $context['source'] = array('filename'=>basename($this->getTrackSourceFileObject()->getPath()),'exists'=>file_exists($this->getTrackSourceFileObject()->getPath()));
        $context['encodes'] = array();
        $context['track_num_conflict'] = false;
        foreach ($this->getAllChildEncodes() as $encode) {
            $context['encodes'][] = $encode->getEncodeContext();
        }
        return $context;
    }

    public function syncToStore($shopify) {
        return $shopify->syncProduct($this);
    }

}


?>
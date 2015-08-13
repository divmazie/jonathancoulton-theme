<?php

namespace jct;

class Album {


    private $postID, $albumTitle, $albumArtist, $albumYear, $albumGenre, $albumComment, $albumArtObject, $albumBonusAssetObjects, $albumShow;
    // the parent post object
    private $encode_types,$wpPost;
    //
    private $albumTracks = array();

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
        $this->albumYear = get_field('album_year',$post_id);
        $this->albumGenre = get_field('album_genre',$post_id);
        $this->albumComment = get_field('album_comment',$post_id);
        $this->albumArtObject = get_field('album_art',$post_id) ? new WordpressACFFile(get_field('album_art',$post_id)) : false;
        $this->albumBonusAssetObjects = array();
        $bonus_asset_rows = get_field('bonus_assets',$post_id);
        if (is_array($bonus_asset_rows)) {
            foreach ($bonus_asset_rows as $row) {
                $this->albumBonusAssetObjects[] = new WordpressACFFile($row['bonus_asset']);
            }
        }
        $this->albumShow = get_field('show_album_in_store',$post_id);
        $this->encode_types = include(get_template_directory().'/config/encode_types.php');
        $tracks = get_posts(array('post_type' => 'track', 'meta_key' => 'track_album', 'meta_value' => $post_id)); // Constructor probs shouldn't do this lookup
        foreach ($tracks as $track) {
            $this->albumTracks[get_field('track_number',$track->id)] = new Track($track,$this);
        }
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
            var_dump($track_encodes);
            if ($track_encodes) {
                $encodes = array_merge($encodes, $track_encodes);
            }
        }
        return $encodes;
    }

    public function getAllChildZips() {
        $zips = array();
        foreach ($this->encode_types as $encode_type) {
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $zips[$format] = $this->getChildZip($format,$flags);
        }
        return $zips;
    }

    public function getChildZip($format,$flags) {
        $zip = new AlbumZip($this,$format,$flags);
        return $zip;
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

    /**
     * @return mixed
     */
    public function getAlbumArtist() {
        return $this->albumArtist;
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

}
<?php

namespace jct;

class Album {


    private $albumTitle, $albumArtist, $albumYear, $albumGenre, $albumComment, $albumArtObject;
    // the parent post object
    private $wpPost;
    //
    private $albumTracks = array();

    /**
     * @param \WP_Post $postObject the post in the blog that forms the base of this
     * album. The post contains ACF fields and post_meta data that will define the
     * internal variables of this class
     **/
    public function __construct(\WP_Post $post) {
        $post_id = $post->ID;
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
        $this->albumTitle = $post->post_title;
        $this->albumArtist = get_field('album_artist',$post_id);
        $this->albumYear = get_field('album_year',$post_id);
        $this->albumGenre = get_field('album_genre',$post_id);
        $this->albumComment = get_field('album_comment',$post_id);
        $this->albumArtObject  = get_field('album_art',$post_id); // returns array with id, url, sizes, etc
        $tracks = get_posts(array('post_type' => 'track', 'meta_key' => 'track_album', 'meta_value' => $post_id));
        foreach ($tracks as $track) {
            $this->albumTracks[get_field('track_number',$track->id)] = new Track($track,$this);
        }
    }


    public function addTrack(Track $track) {

    }

    public function removeTrack(Track $track) {

    }

    public function getNumberOfAlbumTracks() {
        return count($this->albumTracks);
    }

    // @return array the album tracks IN ORDER
    public function getAlbumTracks() {
        return $this->albumTracks;
    }

    public function getNeededEncodes() {
        $encodes = array();
        foreach ($this->albumTracks as $track) {
            $track_encodes = $track->getNeededEncodes();
            if (count($track_encodes)) {
                $encodes = array_merge($encodes,$track_encodes);
            }
        }
        if (count($encodes)) {
            return $encodes;
        } else {
            return false;
        }
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
    public function getAlbumComment() {
        return $this->albumComment;
    }

}
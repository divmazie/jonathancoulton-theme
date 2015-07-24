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
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
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
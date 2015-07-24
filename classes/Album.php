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
     * @param mixed $albumTitle
     */
    public function setAlbumTitle($albumTitle) {
        $this->albumTitle = $albumTitle;
    }

    /**
     * @return mixed
     */
    public function getAlbumArtist() {
        return $this->albumArtist;
    }

    /**
     * @param mixed $albumArtist
     */
    public function setAlbumArtist($albumArtist) {
        $this->albumArtist = $albumArtist;
    }

    /**
     * @return mixed
     */
    public function getAlbumYear() {
        return $this->albumYear;
    }

    /**
     * @param mixed $albumYear
     */
    public function setAlbumYear($albumYear) {
        $this->albumYear = $albumYear;
    }

    /**
     * @return mixed
     */
    public function getAlbumGenre() {
        return $this->albumGenre;
    }

    /**
     * @param mixed $albumGenre
     */
    public function setAlbumGenre($albumGenre) {
        $this->albumGenre = $albumGenre;
    }

    /**
     * @return mixed
     */
    public function getAlbumArtObject() {
        return $this->albumArtObject;
    }

    /**
     * @param mixed $albumArtObject
     */
    public function setAlbumArtObject($albumArtObject) {
        $this->albumArtObject = $albumArtObject;
    }

    /**
     * @return mixed
     */
    public function getAlbumComment() {
        return $this->albumComment;
    }

    /**
     * @param mixed $albumComment
     */
    public function setAlbumComment($albumComment) {
        $this->albumComment = $albumComment;
    }


}
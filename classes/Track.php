<?php

namespace jct;

class Track {

    private $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileObject;
    private $wpPost;
    private $parentAlbum;

    /**
     * @param \WP_Post $post the parent post object whence the fields
     *
     */
    public function __construct(\WP_Post $post, Album $parentAlbum) {
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
        $this->parentAlbum = $parentAlbum;

        $this->parentAlbum->addTrack($this);
    }

    /**
     * @return mixed
     */
    public function getTrackTitle() {
        return $this->trackTitle;
    }

    /**
     * @param mixed $trackTitle
     */
    public function setTrackTitle($trackTitle) {
        $this->trackTitle = $trackTitle;
    }

    /**
     * @return mixed
     */
    public function getTrackArtist() {
        return $this->trackArtist ? $this->trackArtist : $this->parentAlbum->getAlbumArtist();
    }

    /**
     * @param mixed $trackArtist
     */
    public function setTrackArtist($trackArtist) {
        $this->trackArtist = $trackArtist;
    }

    /**
     * @return mixed
     */
    public function getTrackGenre() {
        return $this->trackGenre ? $this->trackGenre : $this->parentAlbum->getAlbumGenre();
    }

    /**
     * @param mixed $trackGenre
     */
    public function setTrackGenre($trackGenre) {
        $this->trackGenre = $trackGenre;
    }

    /**
     * @return mixed
     */
    public function getTrackYear() {
        return $this->trackYear ? $this->trackYear : $this->parentAlbum->getAlbumYear();
    }

    /**
     * @param mixed $trackYear
     */
    public function setTrackYear($trackYear) {
        $this->trackYear = $trackYear;
    }

    /**
     * @return mixed
     */
    public function getTrackComment() {
        return $this->trackComment ? $this->trackComment : $this->parentAlbum->getAlbumComment();
    }

    /**
     * @param mixed $trackComment
     */
    public function setTrackComment($trackComment) {
        $this->trackComment = $trackComment;
    }

    /**
     * @return mixed
     */
    public function getTrackArtObject() {
        return $this->trackArtObject ? $this->trackArtObject : $this->parentAlbum->getAlbumArtObject();
    }

    /**
     * @param mixed $trackArtObject
     */
    public function setTrackArtObject($trackArtObject) {
        $this->trackArtObject = $trackArtObject;
    }

    /**
     * @return mixed
     */
    public function getTrackSourceFileObject() {
        return $this->trackSourceFileObject;
    }

    /**
     * @param mixed $trackSourceFileObject
     */
    public function setTrackSourceFileObject($trackSourceFileObject) {
        $this->trackSourceFileObject = $trackSourceFileObject;
    }


}


?>
<?php

namespace jct;

class Track {

    private $trackNumber, $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileObject;
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
    public function getTrackNumber() {
        return abs(intval($this->trackNumber));
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

    /**
     * @return mixed
     */
    public function getTrackSourceFileObject() {
        return $this->trackSourceFileObject;
    }


}


?>
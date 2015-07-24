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

    public function getTrackVersionHash() {
        return md5(implode('|||', array(
            $this->getTrackArtist(),
            $this->getTrackComment(),
            $this->getTrackGenre(),
            $this->getTrackNumber(),
            $this->getTrackTitle(),
            $this->getTrackYear(),
            $this->getTrackArtFilePath(),
            $this->getTrackSourceFilePath(),
        )));
    }

    public function getChildEncodes() {
        return array(
            new Encode($this, 'mp3', '-V1'),
            new Encode($this, 'flac', '--best'),
            new Encode($this, 'ogg', ''),
        );
    }

    /**
     * @return mixed
     */
    public function getTrackTitle() {
        return $this->trackTitle;
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

    public function getTrackArtFilePath() {
        get_attached_file($this->getTrackArtObject()->ID);
    }

    public function getTrackSourceFilePath() {
        get_attached_file($this->trackSourceFileObject->ID);
    }

    /**
     * @return mixed
     */
    public function getTrackSourceFileObject() {
        return $this->trackSourceFileObject;
    }


}


?>
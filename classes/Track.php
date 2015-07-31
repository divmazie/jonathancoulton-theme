<?php

namespace jct;

class Track {

    private $postID, $trackNumber, $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileURL;
    private $wpPost;
    private $parentAlbum;

    /**
     * @param \WP_Post $post the parent post object whence the fields
     *
     */
    public function __construct(\WP_Post $post, Album $parentAlbum) {
        $post_id = $post->ID;
        $this->postID = $post_id;
        // fill in private fields from post object/acf/postmeta
        $this->wpPost = $post;
        $this->parentAlbum = $parentAlbum;
        $this->trackTitle = $post->post_title;
        $this->trackArtist = get_field('track_artist',$post_id);
        $this->trackGenre = get_field('track_genre',$post_id);
        $this->trackYear = get_field('track_year',$post_id);
        $this->trackComment = get_field('track_comment',$post_id);
        $this->trackArtObject = get_field('track_art',$post_id);
        $this->trackSourceFileURL = get_field('track_source',$post_id);

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
            $this->getTrackSourceFileURL(),
        )));
    }

    public function getChildEncodes() {
        return array(
            new Encode($this, 'mp3', '-V1'),
            new Encode($this, 'flac', '--best'),
            new Encode($this, 'ogg', ''),
        );
    }

    public function getNeededEncodes() {
        $needed_encodes = array();
        foreach ($this->getChildEncodes() as $encode) {
            $config = $encode->getEncodeConfig();
            if ($config) {
                $needed_encodes[] = $config;
            }
        }
        if (count($needed_encodes)) {
            return $needed_encodes;
        } else {
            return false;
        }
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

    /**
     * @return mixed
     */
    public function getTrackSourceFileURL() {
        return $this->trackSourceFileURL;
    }


}


?>
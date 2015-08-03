<?php

namespace jct;

class Track {

    static $encode_types = array('mp3' => '-V1',
        'flac' => '--best',
        'ogg' => '');

    private $postID, $trackNumber, $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileURL;
    private $lastforsale;
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
        $this->lastforsale = array();
        foreach (self::$encode_types as $format => $flags) {
            $this->lastforsale[$format] = get_post_meta($this->postID,'lastforsale_'.$format.'_hash');
        }

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
            $this->getTrackArtURL(),
            $this->getTrackSourceFileURL(),
        )));
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if ($this->parentAlbum->isEncodeWorthy()) {
            if ($this->trackTitle && $this->getTrackArtist() && $this->trackSourceFileURL && $this->getTrackArtURL()) {
                $worthy = true;
            }
        }
        return $worthy;
    }

    public function getAllChildEncodes() {
        $encodes = array();
        foreach (self::$encode_types as $format => $flags) {
            $encodes[$format] = $this->getChildEncode($format,$flags);
        }
        return $encodes;
    }

    public function getChildEncode($format,$flags) {
        $encode = new Encode($this, $format, $flags);
        if ($encode->getEncodeHash() != $this->lastforsale[$format]) {
            set_transient('encodes_needed',true);
        }
        return $encode;
    }

    public function getNeededEncodes() {
        if (!$this->isEncodeWorthy()) {
            return false;
        }
        $needed_encodes = array();
        foreach ($this->getAllChildEncodes() as $encode) {
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

    public function getTrackArtURL() {
        $art_object = $this->getTrackArtObject();
        return wp_get_attachment_url($art_object['id']);
    }

    /**
     * @return mixed
     */
    public function getTrackSourceFileURL() {
        return $this->trackSourceFileURL;
    }


}


?>
<?php

namespace jct;

class Track {

    private $postID, $trackNumber, $trackTitle, $trackArtist, $trackGenre, $trackYear, $trackComment, $trackArtObject, $trackSourceFileObject;
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
        $this->trackArtist = get_field('track_artist',$post_id);
        $this->trackGenre = get_field('track_genre',$post_id);
        $this->trackYear = get_field('track_year',$post_id);
        $this->trackComment = get_field('track_comment',$post_id);
        $this->trackArtObject = get_field('track_art',$post_id);
        $this->trackSourceFileObject = get_field('track_source',$post_id);
        $this->encode_types = include(get_template_directory().'/config/encode_types.php');
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if ($this->parentAlbum->isEncodeWorthy()) {
            if ($this->trackTitle && $this->getTrackArtist() && $this->trackSourceFileObject && $this->getTrackArtURL()) {
                $worthy = true;
            }
        }
        return $worthy;
    }

    public function getAllChildEncodes() {
        $encodes = array();
        foreach ($this->encode_types as $encode_type) {
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $encodes[$format] = $this->getChildEncode($format,$flags);
        }
        return $encodes;
    }

    public function getChildEncode($format,$flags) {
        $encode = new Encode($this, $format, $flags);
        return $encode;
    }

    public function getNeededEncodes() {
        if (!$this->isEncodeWorthy()) {
            return false;
        }
        $needed_encodes = array();
        foreach ($this->getAllChildEncodes() as $encode) {
            if ($encode->encodeIsNeeded()) {
                $needed_encodes[] = $encode->getEncodeConfig();
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

    public function getTrackArtPath() {
        $art_object = $this->getTrackArtObject();
        return get_attached_file($art_object['id']);
    }

    public function getTrackSourceFileObject() {
        return $this->trackSourceFileObject;
    }

    /**
     * @return mixed
     */
    public function getTrackSourceFileURL() {
        $source_object = $this->getTrackSourceFileObject();
        return wp_get_attachment_url($source_object['id']);
    }

    public function getTrackSourceFilePath() {
        $source_object = $this->getTrackSourceFileObject();
        return get_attached_file($source_object['id']);
    }


}


?>
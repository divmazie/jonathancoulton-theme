<?php

namespace jct;

use Timber\Timber;

class Track extends ShopifyProduct {

    const CPT_NAME = 'track';

    // auto complete acf props
    public $track_album, $track_source, $track_number, $track_price, $track_artist, $track_year, $track_genre, $track_art, $track_comment, $wiki_link;

    /**
     * @return Album
     */
    public function getAlbum() {
        return Album::getAlbumByID($this->track_album);
    }

    public function getPostID() {
        return $this->postID;
    }

    public function getTrackTitle() {
        return $this->title();
    }

    public function getTitle() {
        return $this->getTrackTitle();
    }

    public function getTrackNumber() {
        return abs(intval($this->track_number));
    }

    public function getTrackPrice() {
        return abs(intval($this->track_price));
    }

    public function getTrackArtist() {
        return $this->track_artist ? $this->track_artist : $this->getAlbum()->getAlbumArtist();
    }

    public function getTrackGenre() {
        return $this->track_genre ? $this->track_genre : $this->getAlbum()->getAlbumGenre();
    }

    public function getTrackYear() {
        return $this->track_year ? $this->track_year : $this->getAlbum()->getAlbumYear();
    }

    public function getTrackComment() {
        return $this->track_comment ? $this->track_comment : $this->getAlbum()->getAlbumComment();
    }

    public function getTrackArtObject() {
        $trackArtObject = $this->track_art ? Util::get_posts_cached($this->track_art, CoverArt::class) : null;
        return $trackArtObject ? $trackArtObject : $this->getAlbum()->getAlbumArtObject();
    }

    public function getTrackSourceFileObject() {
        return Util::get_posts_cached($this->track_source, WPAttachment::class);
    }

    public function getTrackContext() {
        $context = ['title' => $this->getTrackTitle(), 'artist' => $this->getTrackArtist()];
        $context['number'] = $this->getTrackNumber();
        //$context['price'] = $this->getTrackPrice();
        $context['encode_worthy'] = $this->isEncodeWorthy();
        $context['art'] = $this->getTrackArtObject() ?
            [
                'filename' => basename($this->getTrackArtObject()->getPath()),
                'exists'   => file_exists($this->getTrackArtObject()->getPath()),
            ]
            : ['filename' => 'MISSING!!!', 'exists' => false];
        $context['source'] = $this->getTrackSourceFileObject() ?
            [
                'filename' => basename($this->getTrackSourceFileObject()->getPath()),
                'exists'   => file_exists($this->getTrackSourceFileObject()->getPath()),
            ]
            : ['filename' => 'MISSING', 'exists' => false];
        $context['encodes'] = [];
        $context['track_num_conflict'] = false;
        foreach($this->getAllChildEncodes() as $encode) {
            $context['encodes'][] = $encode->getEncodeContext();
        }
        return $context;
    }

    public function isEncodeWorthy() {
        $worthy = false;
        if($this->parentAlbum->isEncodeWorthy()) {
            //$worthy = true;
            if($this->getTrackTitle() && $this->getTrackArtist() && $this->getTrackSourceFileObject() &&
               $this->getTrackArtObject()
            ) {
                $worthy = true;
            }
        }
        return $worthy;
    }

    public function getAllChildEncodes() {
        $encodes = [];
        foreach(Util::get_encode_types() as $key => $encode_type) {
            $label = $key;
            $format = $encode_type[0];
            $flags = $encode_type[1];
            $encodes[$label] = $this->getChildEncode($format, $flags, $label);
        }
        return $encodes;
    }

    public function getChildEncode($format, $flags, $label) {
        $encode = new Encode($this, $format, $flags, $label);
        return $encode;
    }

    public function getMusicLink() {
        $encode_label = "MP3";
        $encode =
            $this->getChildEncode(Util::get_encode_types()[$encode_label][0], Util::get_encode_types()[$encode_label][1], $encode_label);
        return $encode->getURL();
    }

    public function deleteOldEncodes() {
        $goodKeys = [];
        foreach($this->getAllChildEncodes() as $encode) {
            $goodKeys[] = $encode->getUniqueKey();
        }
        return Encode::deleteOldAttachments($this->postID, $goodKeys);
    }

    public function getNeededEncodes() {
        if(!$this->isEncodeWorthy()) {
            return false;
        }
        $needed_encodes = [];
        foreach($this->getAllChildEncodes() as $encode) {
            if($encode->encodeIsNeeded()) {
                $needed_encodes[] = $encode;
            }
        }
        return $needed_encodes;
    }

    public function syncToStore($shopify) {
        return $shopify->syncProduct($this);
    }

    public static function getTracksForAlbum(Album $album) {
        $tracks = Util::get_posts_cached([
                                             'post_type'      => self::CPT_NAME,
                                             'posts_per_page' => -1,
                                             'meta_query'     => [
                                                 'key'   => 'track_album',
                                                 'value' => $album->getPostID(),
                                             ],
                                         ], self::class);


        usort($tracks, function (Track $left, Track $right) {
            $ltn = $left->getTrackNumber();
            $rtn = $right->getTrackNumber();

            return $ltn === $rtn ? 0 : ($ltn < $rtn ? -1 : 1);
        });

        return $tracks;

    }

}


?>
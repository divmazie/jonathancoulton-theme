<?php

namespace jct;

use jct\Shopify\Metafield;

class Track extends MusicStoreProductPost {

    const PLAYER_ENCODE_CONFIG_NAME = 'MP3';

    // auto complete acf props
    public $track_album, $track_source, $track_number, $track_price, $track_artist, $track_year, $track_genre, $track_art, $track_comment, $wiki_link;

    /**
     * @return Album
     */
    public function getAlbum() {
        return Album::getByID($this->track_album);
    }

    public function getTrackNumber() {
        return abs(intval($this->track_number));
    }

    public function getPrice() {
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

    /** @returns CoverArt */
    public function getCoverArt() {
        $trackArtObject = $this->track_art ? Util::get_posts_cached($this->track_art, CoverArt::class) : null;
        return $trackArtObject ? $trackArtObject : $this->getAlbum()->getCoverArt();
    }

    /**
     * @return WPAttachment
     */
    public function getTrackSourceFileObject() {
        return Util::get_posts_cached($this->track_source, SourceTrack::class);
    }

    public function isFilledOut() {
        return $this->getAlbum()->isFilledOut() && $this->getTitle() && $this->getTrackArtist() &&
               $this->getTrackSourceFileObject() && $this->getTrackSourceFileObject()->fileAssetExists() &&
               $this->getCoverArt() && $this->getCoverArt()->fileAssetExists();
    }

    public function getListenLink() {
        return EncodeConfig::getConfigForTrackByName(
            $this, self::PLAYER_ENCODE_CONFIG_NAME)->getEncode()->getURL();
    }

    public function getTrackEncodeConfigs() {
        if(!$this->isFilledOut()) {
            return [];
        }
        return EncodeConfig::getConfigsForTrack($this);
    }

    public function getEncodedAssets() {
        // remove nulls...
        return array_filter(array_map(function (EncodeConfig $encodeConfig) {
            return $encodeConfig->getAsset();
        }, $this->getTrackEncodeConfigs()));
    }


    public function getEncodeConfigByName($configName) {
        return EncodeConfig::getConfigForTrackByName($this, $configName);
    }

    public function getPublicFilename($withExtension = null) {
        return sprintf('%02d %s', $this->getTrackNumber(), $this->getTitle()) .
               ($withExtension ? '.' . $withExtension : '');
    }

    public function getDownloadStoreTitle() {
        return $this->getTitle();
    }

    public function getDownloadStoreBodyHtml() {
        return sprintf('%s by %s. From the album %s. Released in %d.',
                       $this->getDownloadStoreTitle(),
                       $this->getTrackArtist(),
                       $this->getAlbum()->getTitle(),
                       $this->getTrackYear());
    }

    public function getShopifyMetafields() {
        $musicLink = new Metafield();
        $musicLink->key = 'music_link';
        $musicLink->value = $this->getListenLink();
        $musicLink->useInferredValueType();

        return array_merge([$musicLink], parent::getShopifyMetafields());
    }

    public static function getPostType() {
        return 'track';
    }

    public static function getTracksForAlbum(Album $album, $prepop = null) {
        /** @var Track[] $tracks */
        $tracks = Util::get_posts_cached([
                                             'post_type'      => self::getPostType(),
                                             'posts_per_page' => -1,
                                             'meta_query'     => [
                                                 'key'   => 'track_album',
                                                 'value' => $album->getPostID(),
                                             ],
                                         ], self::class, $prepop);


        usort($tracks, function (Track $left, Track $right) {
            $ltn = $left->getTrackNumber();
            $rtn = $right->getTrackNumber();

            return $ltn === $rtn ? 0 : ($ltn < $rtn ? -1 : 1);
        });

        return $tracks;
    }

    public static function getAll() {
        /** @var Track[] $allTracks */
        $allTracks = parent::getAll();

        $albumsArray = [];
        foreach($allTracks as $track) {
            $album = $track->getAlbum();
            $albumsArray[$album->getPostID()]['album'] = $album;
            $albumsArray[$album->getPostID()]['tracks'][] = $track;
        }

        // prepop by album
        foreach($albumsArray as $albumRow) {
            self::getTracksForAlbum($albumRow['album'], $albumRow['tracks']);
        }

        return $allTracks;
    }
}


?>
<?php

namespace jct;

use jct\Shopify\Provider\ImageProvider;
use jct\Shopify\Provider\ProductOptionProvider;
use jct\Shopify\Provider\ProductProvider;
use jct\Shopify\Provider\ProductVariantProvider;
use Timber\Timber;

class Track extends ShopifyProduct {

    const PLAYER_ENCODE_CONFIG_NAME = 'MP3';

    // auto complete acf props
    public $track_album, $track_source, $track_number, $track_price, $track_artist, $track_year, $track_genre, $track_art, $track_comment, $wiki_link;

    /**
     * @return Album
     */
    public function getAlbum() {
        return Album::getByID($this->track_album);
    }

    public function getTrackTitle() {
        return $this->title();
    }

    function getTitle() {
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

    /** @returns CoverArt */
    public function getTrackArtObject() {
        $trackArtObject = $this->track_art ? Util::get_posts_cached($this->track_art, CoverArt::class) : null;
        return $trackArtObject ? $trackArtObject : $this->getAlbum()->getAlbumArtObject();
    }

    /**
     * @return WPAttachment
     */
    public function getTrackSourceFileObject() {
        return Util::get_posts_cached($this->track_source, SourceTrack::class);
    }

    public function isFilledOut() {
        return $this->getAlbum()->isFilledOut() && $this->getTrackTitle() && $this->getTrackArtist() &&
               $this->getTrackSourceFileObject() && $this->getTrackSourceFileObject()->fileAssetExists() &&
               $this->getTrackArtObject() && $this->getTrackArtObject()->fileAssetExists();
    }

    public function getMusicLink() {
        return EncodeConfig::getConfigForTrackByName(
            $this, self::PLAYER_ENCODE_CONFIG_NAME)->getEncode()->getURL();
    }

    public function getTrackEncodeConfigs() {
        if(!$this->isFilledOut()) {
            return [];
        }
        return EncodeConfig::getConfigsForTrack($this);
    }

    public function getEncodeConfigByName($configName) {
        return EncodeConfig::getConfigForTrackByName($this, $configName);
    }

    public function getPublicFilename($withExtension = null) {
        return sprintf('%02d %s', $this->getTrackNumber(), $this->getTrackTitle()) .
               ($withExtension ? '.' . $withExtension : '');
    }

    public function syncToStore($shopify) {
        return $shopify->syncProduct($this);
    }

    public function getProductImageSourceUrl() {
        //return $this->getTrackArtObject()->getURL();
        return 'http://www.jonathancoulton.com/images/jc-face-blog-thumb.jpg';
    }

    public function getShopifyTitle() {
        return $this->getTrackTitle();
    }

    public function getShopifyBodyHtml() {
        return sprintf('%s by %s. From the album %s. Released in %d.',
                       $this->getShopifyTitle(),
                       $this->getTrackArtist(),
                       $this->getAlbum()->getAlbumTitle(),
                       $this->getTrackYear());
    }

    public function getShopifyProductType() {
        return ThemeObjectRepository::DEFAULT_SHOPIFY_PRODUCT_TYPE;
    }

    public function getShopifyVendor() {
        return 'Jonathan Coulton';
    }

    public function getShopifyTags() {
        return $this->getAlbum()->getFilenameFriendlyTitle();
    }

    public function getProductVariantProviders() {
        return $this->getTrackEncodeConfigs();
    }

    public function getProductOptionProviders() {
        return [$this->getTrackEncodeConfigs()[0]];
    }

    public function getProductImageProviders() {
        return [$this];
    }

    public function getProductMetafieldProviders() {
        return MusicStoreMetafieldProvider::getForProduct($this);
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
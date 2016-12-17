<?php

namespace jct;

class Album extends MusicStoreProduct {
    // meta fields acf will load (here for autocomplete purposes)
    public $album_artist, $album_price, $album_year, $album_genre, $album_art, $album_comment, $album_sort_order, $album_description, $shopify_collection_id, $bonus_assets, $show_album_in_store;

    public function __construct($pid) {
        parent::__construct($pid);
    }

    public function getAlbumArtist() {
        return $this->album_artist;
    }

    public function getPrice() {
        return abs(intval($this->album_price));
    }

    public function getAlbumYear() {
        return $this->album_year;
    }

    public function getAlbumGenre() {
        return $this->album_genre;
    }

    public function getAlbumComment() {
        return $this->album_comment;
    }

    public function getAlbumSortOrder() {
        return $this->album_sort_order;
    }

    public function getAlbumDescription() {
        return $this->album_description;
    }

    public function getAlbumShow() {
        return $this->show_album_in_store;
    }

    /** @return CoverArt */
    public function getCoverArt() {
        return Util::get_posts_cached($this->album_art, CoverArt::class);
    }

    // @return Track[] the album tracks IN ORDER
    public function getAlbumTracks() {
        return Track::getTracksForAlbum($this);
    }

    public function numTracks() {
        return count($this->getAlbumTracks());
    }

    public function isFilledOut() {
        return ($this->getAlbumShow() && $this->getTitle() && $this->getAlbumArtist() &&
                $this->getCoverArt() && $this->getCoverArt()->fileAssetExists());
    }

    /**
     * @return BonusAsset[]
     */
    public function getAlbumBonusAssetObjects() {
        return Util::get_posts_cached([
                                          'post__in'    => array_map(function ($idx) {
                                              // get the ids of each of the bonus assets
                                              return $this->{sprintf('bonus_assets_%d_bonus_asset', $idx)};
                                              // bonus_assets is the number of
                                          }, range(0, intval($this->bonus_assets) -
                                                      1)),
                                          // the things ACF will put in here are pretty widely variable
                                          // so
                                          'post_type'   => 'any',
                                          'post_status' => 'any',
                                      ], BonusAsset::class);
    }

    public function getAlbumZipConfigs() {
        return AlbumZipConfig::getConfigsForAlbum($this);
    }

    public function getEncodedAssetConfigs() {
        return $this->getAlbumZipConfigs();
    }

    public function getAlbumZipConfigByName($configName) {
        return AlbumZipConfig::getConfigsForAlbumByName($this, $configName);
    }

    public function getDownloadStoreTitle() {
        return $this->getTitle() . ' (Full Album)';
    }

    public function getDownloadStoreBodyHtml() {
        return sprintf('%s by %s. Released %s.',
                       $this->getDownloadStoreTitle(),
                       $this->getAlbumArtist(),
                       $this->getAlbumYear());
    }


    public static function getPostType() {
        return 'album';
    }

    /** @return Album[] */
    public static function getAll() {
        /** @var Album[] $all */
        $all = parent::getAll();

        usort($all, function (Album $left, Album $right) {
            $leftSortOrder = $left->getAlbumSortOrder();
            $rightSortOrder = $right->getAlbumSortOrder();

            return $leftSortOrder == $rightSortOrder ? 0 : ($leftSortOrder < $rightSortOrder ? -1 : 1);
        });

        return $all;
    }

}
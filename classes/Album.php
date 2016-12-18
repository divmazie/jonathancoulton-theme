<?php

namespace jct;

use jct\Shopify\CustomCollection;
use jct\Shopify\Exception\Exception;
use jct\Shopify\Image;
use jct\Shopify\Product;

class Album extends MusicStoreProduct {

    const ALBUM_SHOPIFY_COLLECTION_CUSTOM_SUFFIX = 'album_collection';

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

    /** @return AlbumZipConfig[] */
    public function getAlbumZipConfigs() {
        return AlbumZipConfig::getConfigsForAlbum($this);
    }

    public function getEncodedAssets() {
        return array_map(function (AlbumZipConfig $encodeConfig) {
            return $encodeConfig->getAsset();
        }, $this->getAlbumZipConfigs());
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

    /** @return CustomCollection */
    public function getShopifyCustomCollection() {
        $collection = new CustomCollection();

        $collection->id = $this->getShopifySyncMetadata()->getCustomCollectionID();
        $collection->title = $this->getTitle();
        $collection->body_html = "All products in " . $this->getDownloadStoreBodyHtml();
        $collection->template_suffix = self::ALBUM_SHOPIFY_COLLECTION_CUSTOM_SUFFIX;
        $image = new Image();
        $image->src = $this->getCoverArt()->getURL();
        $collection->image = $image;
        $collection->sort_order = 'manual';

        $collectProducts = array_merge([$this->getShopifyProduct()], array_map(function (Track $track) {
            return $this->getShopifyProduct();
        }, $this->getAlbumTracks()));

        // syntax for this bad boy https://help.shopify.com/api/reference/customcollection#update
        $collectArray = array_map(function (Product $product, $position) {
            return ['product_id' => $product->id, 'position' => $position];
        }, $collectProducts, range(1, count($collectProducts)));

        $collection->collects = $collectArray;

        return $collection;
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
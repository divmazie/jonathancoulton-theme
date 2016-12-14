<?php

namespace jct;

use jct\Shopify\Provider\ImageProvider;
use jct\Shopify\Provider\ProductOptionProvider;
use jct\Shopify\Provider\ProductProvider;
use jct\Shopify\Provider\ProductVariantProvider;
use Timber\Timber;

class Album extends ShopifyProduct implements ProductProvider, ImageProvider {
    // meta fields acf will load (here for autocomplete purposes)
    public $album_artist, $album_price, $album_year, $album_genre, $album_art, $album_comment, $album_sort_order, $album_description, $shopify_collection_id, $bonus_assets, $show_album_in_store;

    public function __construct($pid) {
        parent::__construct($pid);
    }

    public function getAlbumTitle() {
        return $this->title();
    }

    public function getTitle() {
        return $this->getAlbumTitle();
    }

    public function getAlbumArtist() {
        return $this->album_artist;
    }

    public function getAlbumPrice() {
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

    public function getShopifyCollectionId() {
        return $this->shopify_collection_id;
    }

    public function setShopifyCollectionId($id) {
        $this->update('shopify_collection_id', $id);
        $this->shopify_collection_id = $id;
    }

    public function getAlbumShow() {
        return $this->show_album_in_store;
    }

    /** @return CoverArt */
    public function getAlbumArtObject() {
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
        return ($this->getAlbumShow() && $this->getAlbumTitle() && $this->getAlbumArtist() &&
                $this->getAlbumArtObject() && $this->getAlbumArtObject()->fileAssetExists());
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

    public function getAlbumZipConfigByName($configName) {
        return AlbumZipConfig::getConfigsForAlbumByName($this, $configName);
    }

    public function syncToStore($shopify, $step = 0) {
        if($step == 0) {
            $context = [];
            $context['sync_responses'] = [];
            $response = $shopify->syncProduct($this);
            $context['sync_responses'][] = $response['response'];
            $missing_files = $response['missing_files'];
            $track_product_ids = [0 => $response['response']->product->id];
        } else {
            $context = get_transient('temp_context');
            $missing_files = get_transient('temp_missing_files');
            $track_product_ids = get_transient('track_product_ids');
        }
        $tracks = $this->getAlbumTracks();
        $track_counter = 0;
        foreach($tracks as $track) {
            if(($step == 0 && $track_counter < 8) || ($step > 0 && $track_counter >= 8)) {
                $response = $track->syncToStore($shopify);
                $context['sync_responses'][] = $response['response'];
                $missing_files = array_merge($missing_files, $response['missing_files']);
                $track_product_ids[intval($track->getTrackNumber())] = $response['response']->product->id;
            }
            $track_counter++;
        }
        set_transient('track_product_ids', $track_product_ids);
        if($step == 0) {
            set_transient('temp_context', $context);
            set_transient('temp_missing_files', $missing_files);
        } else {
            delete_transient('temp_context');
            delete_transient('temp_missing_files');
            //$context['collection_sync_response'] = $shopify->syncAlbumCollection($this,$track_product_ids);
            $missing_files_context = $missing_files;
            $context['missing_files'] = $missing_files_context;
        }
        return $context;
    }

    public function syncCollection($shopify) {
        $track_product_ids = get_transient('track_product_ids');
        $response = $shopify->syncAlbumCollection($this, $track_product_ids);
        delete_transient('track_product_ids');
        return $response;
    }

    public function getProductImageSourceUrl() {
        return $this->getAlbumArtObject()->getURL();
    }

    public function getShopifyTitle() {
        return $this->getAlbumTitle() . '(Full Album)';
    }

    public function getShopifyBodyHtml() {
        return sprintf('%s by %s. Released in %s.',
                       $this->getShopifyTitle(),
                       $this->getAlbumArtist(),
                       $this->getAlbumYear());
    }

    public function getShopifyProductType() {
        return static::DEFAULT_SHOPIFY_PRODUCT_TYPE;
    }

    public function getShopifyVendor() {
        return 'Jonathan Coulton';
    }

    public function getShopifyTags() {
        return $this->getFilenameFriendlyTitle();
    }

    public function getProductVariantProviders() {
        return $this->getAlbumZipConfigs();
    }

    public function getProductOptionProviders() {
        return [$this->getAlbumZipConfigs()[0]];
    }

    public function getProductImageProviders() {
        return [$this];
    }

    public function getProductMetafieldProviders() {
        return [];
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
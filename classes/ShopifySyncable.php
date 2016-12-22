<?php

namespace jct;

interface ShopifySyncable {
    /**
     * @return MusicStoreProductSyncMetadata
     */
    public function getShopifySyncMetadata();

    public function setShopifySyncMetadata(MusicStoreProductSyncMetadata $syncMetadata);

}
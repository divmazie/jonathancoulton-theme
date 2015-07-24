<?php

namespace jct;

abstract class WordpressFileAsset {

    abstract public function getFileAssetFileName();

    public function doesFileAssetExist() {
        // use the file name and some metadata? attached
        // to the attachment post object in wordpress
        // to find the upload by file name alone
    }

}


?>
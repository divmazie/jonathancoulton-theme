<?php

namespace jct;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use FetchApp\API\Currency;
use FetchApp\API\Product as FetchProduct;

abstract class EncodedAsset extends WPAttachment {
    const META_CONFIG_PAYLOAD = 'jct_asset_config_payload';
    const META_S3_URL = 'jct_s3_url';
    const META_S3_HASH = 'jct_s3_hash';

    abstract public function getAwsName();

    /** @return EncodedAssetConfig */
    abstract public function getEncodedAssetConfig();


    public function getUniqueKey() {
        return $this->slug;
    }

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

    abstract public function getShopifyAndFetchSKU();

    abstract public function getShopifyProductVariantTitle();

    /** @return MusicStoreProduct */
    abstract public function getParentMusicStoreProduct();

    public function getConfigPayloadArray() {
        return $this->get_field(self::META_CONFIG_PAYLOAD);
    }

    public function setConfigPayloadArray(array $payload) {
        $this->update(self::META_CONFIG_PAYLOAD, $payload);
    }

    public function getS3Url() {
        return $this->get_field(self::META_S3_URL);
    }

    public function setS3Url($url) {
        $this->update(self::META_S3_URL, $url);
    }

    public function getS3Hash() {
        return $this->get_field(self::META_S3_HASH);
    }

    public function setS3Hash($hash) {
        $this->update(self::META_S3_HASH, $hash);
    }

    /** @return MusicStoreProduct */
    public function getParentPost($parentPostClass = JCTPost::class) {
        return Util::get_posts_cached($this->post_parent, $parentPostClass);
    }

    public function shouldUploadToS3() {
        return $this->getCanonicalContentHash() !== $this->getS3Hash();
    }

    public function isUploadedToS3() {
        return $this->getCanonicalContentHash() === $this->getS3Hash();
    }

    public function uploadToS3() {
        $result = self::getS3Client()
            ->putObject([
                            'Bucket'             => Util::get_theme_option('aws_bucket_name'),
                            'Key'                => $this->getAwsName(),
                            'SourceFile'         => $this->getPath(),
                            'ContentDisposition' => 'attachment'
                            //'ACL'   =>  'public-read' // Set permissions through bucket policy for referrals from joco.fetchapp.com
                        ]);

        $this->setS3Url($result->toArray()['ObjectURL']);
        $this->setS3Hash($this->getCanonicalContentHash());
        return $result;
    }

    public function getFetchAppProduct() {
        $fetch_product = new FetchProduct();
        $fetch_product->setProductID($this->getShopifyAndFetchSKU());
        $fetch_product->setSKU($this->getShopifyAndFetchSKU());
        $fetch_product->setName($this->getFetchAppName());
        $fetch_product->setDescription($this->getParentMusicStoreProduct()->getDownloadStoreBodyHtml());
        $fetch_product->setPrice($this->getParentMusicStoreProduct()->getPrice());
        $fetch_product->setCurrency(Currency::USD);

        return $fetch_product;
    }

    public function getFetchAppName($withExtension = false) {
        return sprintf('%s (%s)', $this->getParentMusicStoreProduct()->getDownloadStoreTitle(),
                       $this->getEncodedAssetConfig()->getConfigName())
               . ($withExtension ? '.' . $this->getEncodedAssetConfig()->getFileExtension() : '');
    }

    public function getFetchAppUrlsArray() {
        return [["url" => $this->getS3Url(), "name" => $this->getFetchAppName(true)]];
    }

    /**
     * @param $tempFilePath
     * @param EncodedAssetConfig $encodedAssetConfig
     * @return static
     * @throws JCTException
     */
    public static function createFromTempFile($tempFilePath, EncodedAssetConfig $encodedAssetConfig) {
        /** @noinspection PhpUndefinedFunctionInspection */
        $wpUploadDir = wp_upload_dir();
        $fullStoragePath =
            $wpUploadDir['path'] . '/' .
            $encodedAssetConfig->getUploadRelativeStorageDirectory() . '/' .
            $encodedAssetConfig->getConfigUniqueFilename();


        // possible we could find garbage from testing... just delete it.
        $copyCat = WPAttachment::findByUniqueKey($encodedAssetConfig->getUniqueKey());
        if($copyCat) {
            $copyCat->deleteAttachment(true);
        }

        /** @noinspection PhpUndefinedFunctionInspection */
        if(!wp_mkdir_p($mkdirTarget = dirname($fullStoragePath))) {
            throw new JCTException("Could not create file storage path [$mkdirTarget]");
        }

        if(file_exists($fullStoragePath) && !unlink($fullStoragePath)) {
            throw new JCTException('Could not unlink pre-existing file of same name');
        }

        // move the temp file in
        if(!rename($tempFilePath, $fullStoragePath)) {
            throw new JCTException('Could not rename file');
        }

        @chmod($fullStoragePath, 644);


        /** @noinspection PhpUndefinedFunctionInspection */
        $wpFileType = wp_check_filetype(basename($tempFilePath), null);

        $completedAttachment = [
            // guid must be the filepath!
            'guid'           => $fullStoragePath,
            'post_name'      => $encodedAssetConfig->getUniqueKey(),
            'post_mime_type' => $wpFileType['type'],
            'post_title'     => $encodedAssetConfig->getConfigUniqueFilename(),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        /** @noinspection PhpUndefinedFunctionInspection */
        $attach_id =
            wp_insert_attachment($completedAttachment, $fullStoragePath, $encodedAssetConfig->getParentPost()->getPostID());

        $completedAttachment = static::getByID($attach_id);
        $completedAttachment->setCanonicalContentHash();
        $completedAttachment->setAttachmentClassMetaVariables();
        $completedAttachment->setConfigPayloadArray($encodedAssetConfig->toPersistableArray());

        // update the cache so we pull the freshened version on this request
        static::findByUniqueKey($completedAttachment->getUniqueKey(), $completedAttachment);
        return $completedAttachment;
    }

    /**
     * @return S3Client
     */
    public static function getS3Client() {
        static $client = null;
        if(!$client) {
            $client = new S3Client(
                [
                    'credentials' => new Credentials(Util::get_theme_option('aws_access_key_id'),
                                                     Util::get_theme_option('aws_secret_access_key')),
                    'region'      => 'us-east-1',
                    'version'     => '2006-03-01',
                ]);
        }
        return $client;
    }


}


?>
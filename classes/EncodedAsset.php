<?php

namespace jct;

use Aws\S3\S3Client;

abstract class EncodedAsset extends WPAttachment {
    const META_CONFIG_PAYLOAD = 'jct_asset_config_payload';
    const META_S3_URL = 'jct_s3_url';
    const META_S3_HASH = 'jct_s3_hash';


    private $awsUrl;
    private $createdTime, $uploadedTime;


    abstract public function getAwsName();


    public function getUniqueKey() {
        return $this->slug;
    }

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

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

    public function getParentPost($parentPostClass = JCTPost::class) {
        return Util::get_posts_cached($this->post_parent, $parentPostClass);
    }

    public function shouldUploadToS3() {
        return $this->getCanonicalContentHash() !== $this->getS3Hash();
    }

    public function isUploadedToS3() {
        return !$this->shouldUploadToS3();
    }

    public function uploadToS3() {
        $result = self::getS3Client()
            ->putObject([
                            'Bucket'              => Util::get_theme_option('aws_bucket_name'),
                            'Key'                 => $this->getEncodedAsset()->getAwsName(),
                            'SourceFile'          => $this->getEncodedAsset()->getPath(),
                            'Content-Disposition' => 'attachment'
                            //'ACL'   =>  'public-read' // Set permissions through bucket policy for referrals from joco.fetchapp.com
                        ]);

        $this->s3Url = $result->toArray()['ObjectURL'];
        $this->setS3Hash($this->getCanonicalContentHash());
        return $result;
    }


    public static function createFromTempFile($tempFilePath, EncodedAssetConfig $encodedAssetConfig) {
        /** @noinspection PhpUndefinedFunctionInspection */
        $wpUploadDir = wp_upload_dir();
        $fullStoragePath =
            $wpUploadDir['basedir'] . '/' .
            $encodedAssetConfig->getUploadRelativeStorageDirectory() . '/' .
            $encodedAssetConfig->getConfigUniqueFilename();

        /** @noinspection PhpUndefinedFunctionInspection */
        if(!wp_mkdir_p($mkdirTarget = dirname($fullStoragePath))) {
            throw new JCTException("Could not create file storage path [$mkdirTarget]");
        }

        // move the temp file in
        if(!rename($tempFilePath, $fullStoragePath)) {
            throw new JCTException('Could not rename file');
        }

        @chmod($fullStoragePath, 644);

        /** @noinspection PhpUndefinedFunctionInspection */
        $wpFileType = wp_check_filetype(basename($tempFilePath), null);

        $attachment = [
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
            wp_insert_attachment($attachment, $fullStoragePath, $encodedAssetConfig->getParentPost()->getPostID());

        $attachment = static::getByID($attach_id);
        $attachment->setCanonicalContentHash();
        $attachment->setAttachmentClassMetaVariables();

        return $attachment;
    }

    /**
     * @return S3Client
     */
    public static function getS3Client() {
        static $client = null;
        if(!$client) {
            $client = new S3Client(
                [
                    'key'    => Util::get_theme_option('aws_access_key_id'),
                    'secret' => Util::get_theme_option('aws_secret_access_key'),
                ]);
        }
        return $client;
    }


}


?>
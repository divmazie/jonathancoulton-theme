<?php

namespace jct;

abstract class EncodedAsset extends WPAttachment {

    const META_ATTACHMENT_META_PAYLOAD = 'attachment_meta_payload';


    private $awsUrl;
    private $createdTime, $uploadedTime;


    abstract public function getFileAssetFileName();

    abstract public function getAwsKey();


    public function getUniqueKey() {
        return $this->name();
    }

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

    public function getAttachmentMetaPayloadArray() {
        return $this->get_field(self::META_ATTACHMENT_META_PAYLOAD);
    }

    public function setAttachmentMetaPayloadArray(array $payload) {
        $this->update(self::META_ATTACHMENT_META_PAYLOAD, $payload);
    }

    public function getParentPost($parentPostClass = JCTPost::class) {
        return Util::get_posts_cached($this->post_parent, $parentPostClass);
    }


    public function uploadToAws($s3) {
        $bucket = get_field('aws_bucket_name', 'options');
        $result = $s3->putObject([
                                     'Bucket'              => $bucket,
                                     'Key'                 => $this->getAwsKey(),
                                     'SourceFile'          => $this->getPath(),
                                     'Content-Disposition' => 'attachment'
                                     //'ACL'   =>  'public-read' // Set permissions through bucket policy for referrals from joco.fetchapp.com
                                 ]);
        $url = $result->toArray()['ObjectURL'];
        $this->setAwsUrl($url);
        $this->setUploadedTime();
        return $result;
    }

    public function setAwsUrl($url) {
        $this->awsUrl = $url;
        return update_post_meta($this->parent_post_id, strtolower('aws_url_' . $this->encodeLabel), $url);
    }

    public function getAwsUrl() {
        if($this->awsUrl) {
            return $this->awsUrl;
        }
        $key = strtolower('aws_url_' . $this->encodeLabel);
        $url = get_post_meta($this->parent_post_id, $key, false)[0];
        $this->awsUrl = $url;
        return $url ? $url : false;
    }

    public function setCreatedTime() {
        $time = $this->createdTime = time();
        return update_post_meta($this->parent_post_id, strtolower('encode_created_time_' . $this->encodeLabel), $time);
    }

    public function getCreatedTime() {
        if($this->createdTime) {
            return $this->createdTime;
        }
        $time = get_post_meta($this->parent_post_id, strtolower('encode_created_time_' . $this->encodeLabel), false)[0];
        if(!$time) {
            $time = 1;
        }
        $this->createdTime = $time;
        return $this->createdTime;
    }

    public function setUploadedTime() {
        $time = $this->uploadedTime = time();
        return update_post_meta($this->parent_post_id, strtolower('encode_uploaded_time_' . $this->encodeLabel), $time);
    }

    public function getUploadedTime() {
        if($this->uploadedTime) {
            return $this->uploadedTime;
        }
        $time =
            get_post_meta($this->parent_post_id, strtolower('encode_uploaded_time_' . $this->encodeLabel), false)[0];
        if(!$time) {
            $time = 0;
        }
        $this->uploadedTime = $time;
        return $this->uploadedTime;
    }

    public function needToUpload() {
        return $this->getUploadedTime() < $this->getCreatedTime();
    }


    /**
     * @param $uniqueKey
     * @return Encode|null
     */
    public static function findByUniqueKey($uniqueKey, $prepop = null) {
        return Util::get_posts_cached([
                                          'post_type' => static::POST_TYPE_NAME,
                                          'name'      => $uniqueKey,
                                      ], static::class);
    }

    public static function getAll() {
        /** @var EncodedAsset[] $all */
        $all = Util::get_posts_cached([
                                          'post_type'      => static::POST_TYPE_NAME,
                                          'category_name'  => static::getWPCategoryName(),
                                          'posts_per_page' => -1,
                                      ], static::class);

        foreach($all as $encodedAsset) {
            // cache on the two class levels that we easily can
            // for the two key types we'll likely get asked about in the future
            static::getByID($encodedAsset->getPostID(), $encodedAsset);
            self::getByID($encodedAsset->getPostID(), $encodedAsset);

            static::findByUniqueKey($encodedAsset->getUniqueKey(), $encodedAsset);
            self::findByUniqueKey($encodedAsset->getUniqueKey(), $encodedAsset);
        }

        return $all;
    }


    public static function createFromTempFile($tempFilePath, EncodedAssetConfig $encodedAssetConfig) {
        /** @noinspection PhpUndefinedFunctionInspection */
        $wpUploadDir = wp_upload_dir();
        $fullStoragePath =
            $wpUploadDir['basedir'] . '/' .
            $encodedAssetConfig->getUploadRelativeStorageDirectory() . '/' .
            $encodedAssetConfig->getConfigUniqueFilename();

        /** @noinspection PhpUndefinedFunctionInspection */
        if(!wp_mkdir_p(dirname($fullStoragePath))) {
            throw new JCTException("Could not create file storage path");
        }

        // move the temp file in
        if(!rename($tempFilePath, $fullStoragePath)) {
            throw new JCTException('Could not rename file');
        }

        /** @noinspection PhpUndefinedFunctionInspection */
        $wpFileType = wp_check_filetype(basename($tempFilePath), null);

        $attachment = [
            'guid'           => $encodedAssetConfig->getUniqueKey(),
            'post_name'      => $encodedAssetConfig->getUniqueKey(),
            'post_mime_type' => $wpFileType['type'],
            'post_title'     => $encodedAssetConfig->getConfigUniqueFilename(),
            'post_content'   => json_encode($encodedAssetConfig->toPersistableArray(), JSON_PRETTY_PRINT),
            'post_status'    => 'inherit',
            // want to be able to find by this class and by the child class...
            'post_category'  => array_unique([self::getWPCategoryName(), static::getWPCategoryName()]),
        ];

        /** @noinspection PhpUndefinedFunctionInspection */
        $attach_id =
            wp_insert_attachment($attachment, $fullStoragePath, $encodedAssetConfig->getParentPost()->getPostID());

        return static::getWPAttachmentByID($attach_id);
    }

    public static function getWPCategoryName() {
        return static::class;
    }

    public static function wpRegisterCategory() {
        /** @noinspection PhpUndefinedFunctionInspection */
        wp_create_category(static::getWPCategoryName());
    }


}


?>
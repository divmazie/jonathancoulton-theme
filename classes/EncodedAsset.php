<?php

namespace jct;

abstract class EncodedAsset extends WPAttachment {
    const META_CONFIG_PAYLOAD = 'jct_asset_config_payload';


    private $awsUrl;
    private $createdTime, $uploadedTime;


    abstract public function getFileAssetFileName();

    abstract public function getAwsKey();

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

}


?>
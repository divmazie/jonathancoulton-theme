<?php

namespace jct;

abstract class KeyedWPAttachment extends WPAttachment {

    const META_UNIQUE_KEY = 'attachment_unique_key';
    const META_ATTACHMENT_META_PAYLOAD = 'attachment_meta_payload';
    const META_PARENT_PARENT_POST_KEY = 'attachment_parent_post';


    private $awsUrl;
    private $createdTime, $uploadedTime;

    // encode format === file extension!


    abstract public function getFileAssetFileName();

    abstract public function getAwsKey();


    public function getUniqueKey() {
        return $this->get_field(self::META_UNIQUE_KEY);
    }

    public function setUniqueKey($uniqueKey) {
        $this->update(self::META_UNIQUE_KEY, $uniqueKey);
    }

    public function getAttachmentMetaPayloadArray() {
        return $this->get_field(self::META_ATTACHMENT_META_PAYLOAD);
    }

    public function setAttachmentMetaPayloadArray(array $payload) {
        $this->update(self::META_ATTACHMENT_META_PAYLOAD, $payload);
    }

    public function getParentPost($parentPostClass = JCTPost::class) {
        return Util::get_posts_cached($this->get_field(self::META_PARENT_PARENT_POST_KEY), $parentPostClass);
    }

    public function setParentPost(JCTPost $post) {
        $this->update(self::META_PARENT_PARENT_POST_KEY, $post->getPostID());
    }

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

    public function hasCorrectUniqueKey() {
        $metadata = wp_get_attachment_metadata($this->getAttachmentID());
        if($metadata['unique_key'] == $this->getUniqueKey()) {
            return true;
        } else {
            return false;
        }
    }

    public function setKeyedAttachmentID($attachment_id) {
        $this->attachment_id = $attachment_id;
        if(!$attachment_id) {
            return false;
        }
        return update_post_meta($this->parent_post_id, 'attachment_id_' . $this->getUniqueKey(), $attachment_id);
    }

    public function getAttachmentID() { // Redefined from parent class, because property is not set in constructor for child objects
        if(isset($this->attachment_id)) {
            return $this->attachment_id;
        }
        $attachment_id = get_post_meta($this->parent_post_id, 'attachment_id_' . $this->getUniqueKey(), false)[0];
        $this->attachment_id = $attachment_id;
        return $attachment_id ? $attachment_id : false;
    }

    public function completeAttaching($attachment_id, $skip_renaming) {
        $this->setKeyedAttachmentID($attachment_id);
        if(!$skip_renaming) {
            $this->fixAttachmentFileName();
        }
    }

    public function fixAttachmentFileName() {
        $attachment_id = $this->getAttachmentID();
        if(!$attachment_id) {
            return false;
        }
        $file = $this->getPath();
        $dir = pathinfo($file)['dirname'];
        $newfile = $dir . "/" . $this->getFileAssetFileName();
        rename($file, $newfile);
        update_attached_file($attachment_id, $newfile);
    }

    static function deleteOldAttachments($post_id, $goodKeys) {
        $metadata = get_post_meta($post_id);
        $deleted = [];
        foreach($metadata as $key => $val) {
            if(substr($key, 0, 14) == 'attachment_id_' && !in_array(substr($key, -32), $goodKeys)) {
                $deleted[] = wp_delete_attachment(intval($val[0]));
            }
        }
        return $deleted;
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

}


?>
<?php

namespace jct;

abstract class KeyedWPAttachment extends WPAttachment {

    private $awsUrl;

    abstract public function getUniqueKey();

    abstract public function getFileAssetFileName();

    abstract public function getAwsKey();

    public function getShortUniqueKey() {
        // if 7 is good enough for git/github, it's good enough for us
        return substr($this->getUniqueKey(), 0, 7);
    }

    public function hasCorrectUniqueKey() {
        $metadata = wp_get_attachment_metadata($this->getAttachmentID());
        if ($metadata['unique_key'] == $this->getUniqueKey()) {
            return true;
        } else {
            return false;
        }
    }

    public function setKeyedAttachmentID($attachment_id) {
        $this->attachment_id = $attachment_id;
        if (!$attachment_id) { return false; }
        return update_post_meta($this->parent_post_id, 'attachment_id_'.$this->getUniqueKey(), $attachment_id);
    }

    public function getAttachmentID() { // Redefined from parent class, because property is not set in constructor for child objects
        if (isset($this->attachment_id)) { return $this->attachment_id; }
        $attachment_id = get_post_meta($this->parent_post_id,'attachment_id_'.$this->getUniqueKey(),false)[0];
        $this->attachment_id = $attachment_id;
        return $attachment_id ? $attachment_id : false;
    }

    public function completeAttaching($attachment_id) {
        $this->setKeyedAttachmentID($attachment_id);
        $this->fixAttachmentFileName();
    }

    public function fixAttachmentFileName() {
        $attachment_id = $this->getAttachmentID();
        if (!$attachment_id) { return false; }
        $file = $this->getPath();
        $dir = pathinfo($file)['dirname'];
        $newfile = $dir."/".$this->getFileAssetFileName();
        rename($file, $newfile);
        update_attached_file($attachment_id,$newfile);
    }

    static function deleteOldAttachments($post_id,$goodKeys) {
        $metadata = get_post_meta($post_id);
        $deleted = array();
        foreach ($metadata as $key => $val) {
            if (substr($key,0,14)=='attachment_id_' && !in_array(substr($key,-32),$goodKeys)) {
                $deleted[] = wp_delete_attachment(intval($val[0]));
            }
        }
        return $deleted;
    }
    
    public function uploadToAws($s3) {
        $bucket = 'joco-songs-new'; // Should this be a theme option? I think this is the only place it's needed
        $result = $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $this->getAwsKey(),
            'SourceFile' => $this->getPath()
            //'ACL'   =>  'public-read' // Set permissions through bucket policy for referrals from joco.fetchapp.com
        ]);
        $url = $result->toArray()['ObjectURL'];
        $this->setAwsUrl($url);
        return $result;
    }

    public function setAwsUrl($url) {
        $this->awsUrl = $url;
        return update_post_meta($this->parent_post_id, 'aws_url', $url);
    }

    public function getAwsUrl() {
        if (isset($this->awsUrl)) { return $this->awsUrl; }
        $url = get_post_meta($this->parent_post_id,'aws_url',false)[0];
        $this->awsUrl = $url;
        return $url ? $url : false;
    }

}


?>
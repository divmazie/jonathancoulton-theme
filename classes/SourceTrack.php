<?php

namespace jct;

class SourceTrack extends WPAttachment {

    const SOURCE_UPLOADS_FOLDER = 'source';

    public function __construct($id) {
        parent::__construct($id);


        if($this->fileAssetExists() && $this->parent()) {
            $baseStoragePath = $desiredPath = wp_upload_dir()['basedir'] . '/' . self::SOURCE_UPLOADS_FOLDER;
            $desiredPath = $baseStoragePath . '/' . $this->getFilename();
            if($desiredPath !== $this->getPath()) {
                // this file is not where we want it to be
                // let's move it
                // make sure we have a dir to move it to...
                wp_mkdir_p($baseStoragePath);

                if(file_exists($desiredPath)) {
                    // we need to modify the filename to fit
                    // we try to preserve the extension
                    for($i = 1; $i < 100 && file_exists($desiredPath); $i++) {
                        // i thought about using hash here, but people upload dupes
                        // and that's ok (/too hard to address here)
                        $path_parts = pathinfo($desiredPath);
                        if(isset($path_parts['extension'])) {
                            $desiredPath = sprintf('%s/%s-%d.%s',
                                                   $path_parts['dirname'],
                                                   $path_parts['filename'], $i,
                                                   $path_parts['extension']);
                        } else {
                            $desiredPath = implode('-', explode('-', $desiredPath, -1)) . '-' . $i;
                        }
                    }
                }


                if(file_exists($desiredPath) || !rename($this->getPath(), $desiredPath)) {
                    $curpath = $this->getPath();
                    die("Cannot move source file to desired location after $i tries: attachment [$this->ID], curpath: [$curpath] final attempt place: [$desiredPath]");
                }

                update_attached_file($this->ID, $desiredPath);
            }
        }
    }


}
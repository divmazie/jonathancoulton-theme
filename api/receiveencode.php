<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/31/15
 * Time: 17:16
 */
$encode_hash = $params['var'];
$encode_transient = get_transient($encode_hash);
if (!$encode_transient) {
    die("Can't find that encode hash!");
} else {
    $encode_details = $encode_transient;
    $track_post_id = $encode_details[0];
    $encode_format = $encode_details[1];
    $encode_flags = $encode_details[2];

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    if (!function_exists('media_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    if (!isset($_FILES['file'])) {
        die("No file!");
    }

    $upload_overrides = array('test_form' => false);

    $attachment_id = media_handle_upload('file', $track_post_id, array(), $upload_overrides) or die('media_handle_upload() failed!');

    if ( !is_wp_error($attachment_id) ) {
        echo "File is valid, and was successfully uploaded.\n";
        if (!function_exists('update_metadata')) {
            die("Can't update metadata!");
        }
        $old_meta = wp_get_attachment_metadata($attachment_id);
        $new_meta = array_merge($old_meta, array('unique_key' => $encode_hash));
        $success = wp_update_attachment_metadata($attachment_id,$new_meta);
        echo $success ? "Updated metadata! \n" : "Failed to update metadata! \n";
        echo "Attachment_id = ".$attachment_id."\n";

        update_post_meta($track_post_id, 'attachment_id_'.$encode_hash, $attachment_id);
    } else {
        echo $attachment_id->get_error_message();;
    }
}
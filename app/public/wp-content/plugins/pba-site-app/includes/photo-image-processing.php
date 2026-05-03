<?php
/**
 * PBA Photo Feature - Image Processing
 *
 * Resizes and compresses uploaded photos before they are stored.
 * Produces a display image and a smaller thumbnail image.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PBA_PHOTO_THUMBNAIL_MAX_DIMENSION')) {
    define('PBA_PHOTO_THUMBNAIL_MAX_DIMENSION', 600);
}

if (!defined('PBA_PHOTO_THUMBNAIL_JPEG_QUALITY')) {
    define('PBA_PHOTO_THUMBNAIL_JPEG_QUALITY', 76);
}

function pba_photo_validate_uploaded_file_array($file) {
    if (empty($file) || !is_array($file)) {
        return new WP_Error('pba_photo_no_file', 'No photo was uploaded.');
    }

    if (!empty($file['error'])) {
        return new WP_Error(
            'pba_photo_upload_error',
            pba_photo_get_upload_error_message((int) $file['error'])
        );
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('pba_photo_invalid_upload', 'The uploaded photo could not be validated.');
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;

    if ($size <= 0) {
        return new WP_Error('pba_photo_empty_file', 'The uploaded photo is empty.');
    }

    if ($size > PBA_PHOTO_MAX_UPLOAD_BYTES) {
        return new WP_Error(
            'pba_photo_file_too_large',
            'The uploaded photo is too large. Maximum size is ' . pba_photo_format_bytes(PBA_PHOTO_MAX_UPLOAD_BYTES, 0) . '.'
        );
    }

    $check = wp_check_filetype_and_ext(
        $file['tmp_name'],
        isset($file['name']) ? $file['name'] : ''
    );

    $allowed_mimes = array(
        'image/jpeg',
        'image/png',
        'image/webp',
    );

    $mime_type = '';

    if (!empty($check['type'])) {
        $mime_type = $check['type'];
    } elseif (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file['tmp_name']);
    }

    if (!in_array($mime_type, $allowed_mimes, true)) {
        return new WP_Error(
            'pba_photo_invalid_type',
            'Please upload a JPG, PNG, or WebP image.'
        );
    }

    return true;
}

function pba_photo_get_upload_error_message($error_code) {
    switch ((int) $error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded photo is too large.';
        case UPLOAD_ERR_PARTIAL:
            return 'The photo was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No photo was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'The server is missing a temporary upload folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not save the uploaded photo.';
        case UPLOAD_ERR_EXTENSION:
            return 'The upload was blocked by a server extension.';
        default:
            return 'The photo upload failed.';
    }
}

function pba_photo_process_uploaded_photo($file) {
    $validation = pba_photo_validate_uploaded_file_array($file);

    if (is_wp_error($validation)) {
        return $validation;
    }

    $tmp_path = $file['tmp_name'];
    $original_filename = isset($file['name']) ? sanitize_file_name($file['name']) : 'uploaded-photo';
    $original_size_bytes = isset($file['size']) ? (int) $file['size'] : filesize($tmp_path);

    $image_info = @getimagesize($tmp_path);

    if (!$image_info || empty($image_info[0]) || empty($image_info[1]) || empty($image_info['mime'])) {
        return new WP_Error('pba_photo_invalid_image', 'The uploaded file is not a valid image.');
    }

    $source_width = (int) $image_info[0];
    $source_height = (int) $image_info[1];
    $source_mime = (string) $image_info['mime'];

    $source_image = pba_photo_create_image_resource($tmp_path, $source_mime);

    if (is_wp_error($source_image)) {
        return $source_image;
    }

    $source_image = pba_photo_maybe_fix_jpeg_orientation($source_image, $tmp_path, $source_mime);

    $display = pba_photo_create_resized_jpeg(
        $source_image,
        PBA_PHOTO_MAX_PROCESSED_DIMENSION,
        PBA_PHOTO_JPEG_QUALITY,
        'pba-photo-display-'
    );

    if (is_wp_error($display)) {
        imagedestroy($source_image);
        return $display;
    }

    $thumbnail = pba_photo_create_resized_jpeg(
        $source_image,
        PBA_PHOTO_THUMBNAIL_MAX_DIMENSION,
        PBA_PHOTO_THUMBNAIL_JPEG_QUALITY,
        'pba-photo-thumb-'
    );

    imagedestroy($source_image);

    if (is_wp_error($thumbnail)) {
        if (!empty($display['path']) && file_exists($display['path'])) {
            @unlink($display['path']);
        }

        return $thumbnail;
    }

    return array(
        'processed_path'              => $display['path'],
        'processed_size_bytes'        => $display['size_bytes'],
        'processed_width'             => $display['width'],
        'processed_height'            => $display['height'],
        'processed_mime_type'         => 'image/jpeg',
        'processed_extension'         => 'jpg',

        'thumbnail_path'              => $thumbnail['path'],
        'thumbnail_size_bytes'        => $thumbnail['size_bytes'],
        'thumbnail_width'             => $thumbnail['width'],
        'thumbnail_height'            => $thumbnail['height'],
        'thumbnail_mime_type'         => 'image/jpeg',
        'thumbnail_extension'         => 'jpg',

        'original_filename'           => $original_filename,
        'original_file_size_bytes'    => (int) $original_size_bytes,
        'original_mime_type'          => $source_mime,
        'original_width'              => $source_width,
        'original_height'             => $source_height,
    );
}

function pba_photo_create_resized_jpeg($source_image, $max_dimension, $jpeg_quality, $temp_prefix) {
    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);

    $target = pba_photo_calculate_target_dimensions(
        $source_width,
        $source_height,
        $max_dimension
    );

    $target_width = $target['width'];
    $target_height = $target['height'];

    $processed_image = imagecreatetruecolor($target_width, $target_height);

    if (!$processed_image) {
        return new WP_Error('pba_photo_processing_failed', 'The server could not prepare the photo for processing.');
    }

    $white = imagecolorallocate($processed_image, 255, 255, 255);
    imagefill($processed_image, 0, 0, $white);

    imagecopyresampled(
        $processed_image,
        $source_image,
        0,
        0,
        0,
        0,
        $target_width,
        $target_height,
        $source_width,
        $source_height
    );

    $temp_path = wp_tempnam($temp_prefix);

    if (!$temp_path) {
        imagedestroy($processed_image);
        return new WP_Error('pba_photo_temp_file_failed', 'The server could not create a temporary processed photo file.');
    }

    $jpeg_path = $temp_path . '.jpg';

    if (file_exists($temp_path)) {
        @unlink($temp_path);
    }

    $saved = imagejpeg($processed_image, $jpeg_path, (int) $jpeg_quality);

    imagedestroy($processed_image);

    if (!$saved || !file_exists($jpeg_path)) {
        return new WP_Error('pba_photo_save_failed', 'The server could not save the processed photo.');
    }

    $size_bytes = filesize($jpeg_path);

    if (!$size_bytes || $size_bytes <= 0) {
        @unlink($jpeg_path);
        return new WP_Error('pba_photo_processed_empty', 'The processed photo is empty.');
    }

    return array(
        'path'       => $jpeg_path,
        'size_bytes' => (int) $size_bytes,
        'width'      => (int) $target_width,
        'height'     => (int) $target_height,
    );
}

function pba_photo_create_image_resource($path, $mime_type) {
    switch ($mime_type) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($path);
            break;

        case 'image/png':
            $image = @imagecreatefrompng($path);
            break;

        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                return new WP_Error(
                    'pba_photo_webp_not_supported',
                    'This server does not support WebP uploads. Please upload a JPG or PNG image.'
                );
            }

            $image = @imagecreatefromwebp($path);
            break;

        default:
            return new WP_Error(
                'pba_photo_unsupported_type',
                'Please upload a JPG, PNG, or WebP image.'
            );
    }

    if (!$image) {
        return new WP_Error(
            'pba_photo_image_load_failed',
            'The server could not read the uploaded photo.'
        );
    }

    return $image;
}

function pba_photo_calculate_target_dimensions($width, $height, $max_dimension) {
    $width = max(1, (int) $width);
    $height = max(1, (int) $height);
    $max_dimension = max(1, (int) $max_dimension);

    if ($width <= $max_dimension && $height <= $max_dimension) {
        return array(
            'width'  => $width,
            'height' => $height,
        );
    }

    if ($width >= $height) {
        $target_width = $max_dimension;
        $target_height = (int) round(($height / $width) * $target_width);
    } else {
        $target_height = $max_dimension;
        $target_width = (int) round(($width / $height) * $target_height);
    }

    return array(
        'width'  => max(1, $target_width),
        'height' => max(1, $target_height),
    );
}

function pba_photo_maybe_fix_jpeg_orientation($image, $path, $mime_type) {
    if ($mime_type !== 'image/jpeg') {
        return $image;
    }

    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($path);

    if (empty($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int) $exif['Orientation'];

    switch ($orientation) {
        case 3:
            $rotated = imagerotate($image, 180, 0);
            break;

        case 6:
            $rotated = imagerotate($image, -90, 0);
            break;

        case 8:
            $rotated = imagerotate($image, 90, 0);
            break;

        default:
            $rotated = false;
            break;
    }

    if ($rotated) {
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

function pba_photo_cleanup_processed_file($processed) {
    if (!is_array($processed)) {
        return;
    }

    $paths = array();

    if (!empty($processed['processed_path'])) {
        $paths[] = $processed['processed_path'];
    }

    if (!empty($processed['thumbnail_path'])) {
        $paths[] = $processed['thumbnail_path'];
    }

    foreach ($paths as $path) {
        if (is_string($path) && file_exists($path)) {
            @unlink($path);
        }
    }
}
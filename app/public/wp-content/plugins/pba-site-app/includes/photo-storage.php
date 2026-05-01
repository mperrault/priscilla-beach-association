<?php
/**
 * PBA Photo Feature - Supabase Storage Helpers
 *
 * Handles upload, delete, and signed URL creation for private Supabase Storage photos.
 */

if (!defined('ABSPATH')) {
    exit;
}

function pba_photo_storage_request($method, $path, $body = null, $headers = array(), $timeout = 45) {
    $supabase_url = pba_photo_get_supabase_url();
    $supabase_key = pba_photo_get_supabase_service_key();

    if ($supabase_url === '' || $supabase_key === '') {
        return new WP_Error(
            'pba_photo_storage_config_missing',
            'Supabase configuration is missing for photo storage.'
        );
    }

    $url = $supabase_url . '/storage/v1/' . ltrim((string) $path, '/');

    $request_headers = array_merge(
        array(
            'apikey'        => $supabase_key,
            'Authorization' => 'Bearer ' . $supabase_key,
        ),
        $headers
    );

    $args = array(
        'method'  => strtoupper($method),
        'headers' => $request_headers,
        'timeout' => $timeout,
    );

    if ($body !== null) {
        $args['body'] = $body;
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'pba_photo_storage_request_failed',
            'Supabase Storage request failed.',
            array(
                'status_code' => $status_code,
                'body'        => $raw_body,
                'url'         => $url,
            )
        );
    }

    if ($raw_body === '') {
        return true;
    }

    $decoded = json_decode($raw_body, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $raw_body;
}

function pba_photo_storage_upload_file($local_path, $storage_path, $mime_type = 'image/jpeg') {
    if (!is_string($local_path) || $local_path === '' || !file_exists($local_path)) {
        return new WP_Error(
            'pba_photo_storage_local_file_missing',
            'The processed photo file was not found.'
        );
    }

    $storage_path = ltrim((string) $storage_path, '/');

    if ($storage_path === '') {
        return new WP_Error(
            'pba_photo_storage_path_missing',
            'The photo storage path is missing.'
        );
    }

    $file_contents = file_get_contents($local_path);

    if ($file_contents === false) {
        return new WP_Error(
            'pba_photo_storage_read_failed',
            'The processed photo file could not be read.'
        );
    }

    $bucket = PBA_PHOTO_STORAGE_BUCKET;
    $path = 'object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($storage_path));

    return pba_photo_storage_request(
        'POST',
        $path,
        $file_contents,
        array(
            'Content-Type' => $mime_type,
            'x-upsert'    => 'false',
        )
    );
}

function pba_photo_storage_delete_file($storage_path, $bucket = null) {
    $storage_path = ltrim((string) $storage_path, '/');

    if ($storage_path === '') {
        return true;
    }

    $bucket = $bucket ? (string) $bucket : PBA_PHOTO_STORAGE_BUCKET;

    /*
     * Supabase delete object API expects:
     * DELETE /storage/v1/object/{bucket}/{path}
     */
    $path = 'object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($storage_path));

    return pba_photo_storage_request(
        'DELETE',
        $path,
        null,
        array(
            'Content-Type' => 'application/json',
        )
    );
}

function pba_photo_storage_create_signed_url($storage_path, $expires_in_seconds = 3600, $bucket = null) {
    $storage_path = ltrim((string) $storage_path, '/');

    if ($storage_path === '') {
        return new WP_Error(
            'pba_photo_signed_url_path_missing',
            'The photo storage path is missing.'
        );
    }

    $bucket = $bucket ? (string) $bucket : PBA_PHOTO_STORAGE_BUCKET;
    $expires_in_seconds = max(60, (int) $expires_in_seconds);

    $path = 'object/sign/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($storage_path));

    $result = pba_photo_storage_request(
        'POST',
        $path,
        wp_json_encode(array(
            'expiresIn' => $expires_in_seconds,
        )),
        array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        )
    );

    if (is_wp_error($result)) {
        return $result;
    }

    if (!is_array($result) || empty($result['signedURL'])) {
        return new WP_Error(
            'pba_photo_signed_url_missing',
            'Supabase did not return a signed photo URL.',
            array(
                'response' => $result,
            )
        );
    }

    $signed_url = (string) $result['signedURL'];

    if (strpos($signed_url, 'http://') === 0 || strpos($signed_url, 'https://') === 0) {
        return $signed_url;
    }

    return pba_photo_get_supabase_url() . '/storage/v1' . $signed_url;
}

function pba_photo_storage_delete_for_photo($photo) {
    if (!is_array($photo)) {
        return new WP_Error(
            'pba_photo_delete_invalid_photo',
            'The photo record is invalid.'
        );
    }

    $storage_path = isset($photo['storage_path']) ? (string) $photo['storage_path'] : '';
    $bucket = !empty($photo['storage_bucket']) ? (string) $photo['storage_bucket'] : PBA_PHOTO_STORAGE_BUCKET;

    if ($storage_path === '') {
        return true;
    }

    if (!empty($photo['storage_deleted_at'])) {
        return true;
    }

    return pba_photo_storage_delete_file($storage_path, $bucket);
}
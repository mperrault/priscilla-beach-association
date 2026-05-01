<?php
/**
 * PBA Photo Feature - Shared Functions
 *
 * Provides shared constants, role checks, Supabase REST helpers, formatting helpers,
 * and storage usage calculations for the PBA photo gallery workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PBA_PHOTO_STORAGE_BUCKET')) {
    define('PBA_PHOTO_STORAGE_BUCKET', 'pba-photos');
}

if (!defined('PBA_PHOTO_STORAGE_LIMIT_BYTES')) {
    define('PBA_PHOTO_STORAGE_LIMIT_BYTES', 1024 * 1024 * 1024); // 1 GB Supabase free-tier file storage target
}

if (!defined('PBA_PHOTO_MAX_UPLOAD_BYTES')) {
    define('PBA_PHOTO_MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB original upload cap
}

if (!defined('PBA_PHOTO_MAX_PROCESSED_DIMENSION')) {
    define('PBA_PHOTO_MAX_PROCESSED_DIMENSION', 1600);
}

if (!defined('PBA_PHOTO_JPEG_QUALITY')) {
    define('PBA_PHOTO_JPEG_QUALITY', 80);
}

function pba_photo_statuses() {
    return array('pending', 'approved', 'denied', 'unpublished', 'deleted');
}

function pba_photo_public_statuses() {
    return array('approved');
}

function pba_photo_storage_counted_statuses() {
    return array('pending', 'approved', 'unpublished');
}

function pba_photo_current_user_can_upload() {
    if (!is_user_logged_in()) {
        return false;
    }

    /*
     * Keep this intentionally broad for now:
     * any logged-in PBA portal user may submit photos for review.
     * Admin approval controls public visibility.
     */
    return true;
}

function pba_photo_current_user_can_manage() {
    return is_user_logged_in() && current_user_can('pba_manage_roles');
}

function pba_photo_get_current_wp_user_id() {
    return is_user_logged_in() ? get_current_user_id() : null;
}

function pba_photo_get_current_actor_email() {
    if (!is_user_logged_in()) {
        return null;
    }

    $user = wp_get_current_user();

    if (!$user || empty($user->user_email)) {
        return null;
    }

    return $user->user_email;
}

function pba_photo_get_current_actor_role_names() {
    if (!is_user_logged_in()) {
        return array();
    }

    $user = wp_get_current_user();

    if (!$user || empty($user->roles) || !is_array($user->roles)) {
        return array();
    }

    return array_values($user->roles);
}

function pba_photo_get_current_person_id() {
    /*
     * This function intentionally tries a few likely project helpers.
     * If none exist, audit records still capture WP user ID and email.
     */
    if (function_exists('pba_get_current_person_id')) {
        $person_id = pba_get_current_person_id();
        return $person_id ? (int) $person_id : null;
    }

    if (function_exists('pba_current_person_id')) {
        $person_id = pba_current_person_id();
        return $person_id ? (int) $person_id : null;
    }

    if (function_exists('pba_get_current_person')) {
        $person = pba_get_current_person();

        if (is_array($person) && isset($person['person_id'])) {
            return (int) $person['person_id'];
        }

        if (is_object($person) && isset($person->person_id)) {
            return (int) $person->person_id;
        }
    }

    return null;
}

function pba_photo_get_supabase_url() {

    if (defined('SUPABASE_URL')) {
        return rtrim(SUPABASE_URL, '/');
    }
    return '';
}

function pba_photo_get_supabase_service_key() {

if (defined('SUPABASE_SERVICE_ROLE_KEY')) {
        return rtrim(SUPABASE_SERVICE_ROLE_KEY, '/');
    }
    return '';
}

function pba_photo_has_supabase_config() {
    return pba_photo_get_supabase_url() !== '' && pba_photo_get_supabase_service_key() !== '';
}

function pba_photo_supabase_rest_request($method, $path, $query_args = array(), $body = null, $extra_headers = array()) {
    $supabase_url = pba_photo_get_supabase_url();
    $supabase_key = pba_photo_get_supabase_service_key();

    if ($supabase_url === '' || $supabase_key === '') {
        return new WP_Error(
            'pba_photo_supabase_config_missing',
            'Supabase configuration is missing for the photo feature.'
        );
    }

    $path = ltrim((string) $path, '/');
    $url = $supabase_url . '/' . $path;

    if (!empty($query_args)) {
        $url = add_query_arg($query_args, $url);
    }

    $headers = array_merge(
        array(
            'apikey'        => $supabase_key,
            'Authorization' => 'Bearer ' . $supabase_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Prefer'        => 'return=representation',
        ),
        $extra_headers
    );

    $args = array(
        'method'  => strtoupper($method),
        'headers' => $headers,
        'timeout' => 30,
    );

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'pba_photo_supabase_request_failed',
            'Supabase request failed.',
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

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $raw_body;
    }

    return $decoded;
}

function pba_photo_supabase_select($table, $query_args = array()) {
    $table = trim((string) $table, '"');

    return pba_photo_supabase_rest_request(
        'GET',
        'rest/v1/' . rawurlencode($table),
        $query_args
    );
}

function pba_photo_supabase_insert($table, $row) {
    $table = trim((string) $table, '"');

    return pba_photo_supabase_rest_request(
        'POST',
        'rest/v1/' . rawurlencode($table),
        array(),
        $row
    );
}

function pba_photo_supabase_update($table, $query_args, $row) {
    $table = trim((string) $table, '"');

    return pba_photo_supabase_rest_request(
        'PATCH',
        'rest/v1/' . rawurlencode($table),
        $query_args,
        $row
    );
}

function pba_photo_supabase_delete($table, $query_args) {
    $table = trim((string) $table, '"');

    return pba_photo_supabase_rest_request(
        'DELETE',
        'rest/v1/' . rawurlencode($table),
        $query_args
    );
}

function pba_photo_get_storage_usage_summary() {
    $limit_bytes = (int) PBA_PHOTO_STORAGE_LIMIT_BYTES;

    $query_args = array(
        'select'             => 'processed_file_size_bytes',
        'status'             => 'in.(pending,approved,unpublished)',
        'storage_deleted_at' => 'is.null',
        'processed_file_size_bytes' => 'not.is.null',
    );

    $rows = pba_photo_supabase_select('Photo', $query_args);

    if (is_wp_error($rows)) {
        return array(
            'ok'                         => false,
            'error'                      => $rows->get_error_message(),
            'limit_bytes'                => $limit_bytes,
            'used_bytes'                 => 0,
            'remaining_bytes'            => $limit_bytes,
            'used_percent'               => 0,
            'photo_count'                => 0,
            'average_photo_bytes'        => 0,
            'estimated_remaining_photos' => null,
        );
    }

    $used_bytes = 0;
    $photo_count = 0;

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $size = isset($row['processed_file_size_bytes']) ? (int) $row['processed_file_size_bytes'] : 0;

            if ($size > 0) {
                $used_bytes += $size;
                $photo_count++;
            }
        }
    }

    $remaining_bytes = max(0, $limit_bytes - $used_bytes);
    $used_percent = $limit_bytes > 0 ? round(($used_bytes / $limit_bytes) * 100, 1) : 0;
    $average_photo_bytes = $photo_count > 0 ? (int) round($used_bytes / $photo_count) : 0;
    $estimated_remaining_photos = $average_photo_bytes > 0
        ? (int) floor($remaining_bytes / $average_photo_bytes)
        : null;

    return array(
        'ok'                         => true,
        'error'                      => null,
        'limit_bytes'                => $limit_bytes,
        'used_bytes'                 => $used_bytes,
        'remaining_bytes'            => $remaining_bytes,
        'used_percent'               => $used_percent,
        'photo_count'                => $photo_count,
        'average_photo_bytes'        => $average_photo_bytes,
        'estimated_remaining_photos' => $estimated_remaining_photos,
    );
}

function pba_photo_format_bytes($bytes, $precision = 1) {
    $bytes = (float) $bytes;

    if ($bytes < 1024) {
        return number_format($bytes, 0) . ' B';
    }

    $units = array('KB', 'MB', 'GB', 'TB');
    $value = $bytes / 1024;

    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, $precision) . ' ' . $unit;
        }

        $value = $value / 1024;
    }

    return number_format($bytes, 0) . ' B';
}

function pba_photo_sanitize_status($status, $default = 'pending') {
    $status = sanitize_key((string) $status);

    return in_array($status, pba_photo_statuses(), true) ? $status : $default;
}

function pba_photo_slugify($value) {
    $value = sanitize_title((string) $value);

    return $value !== '' ? $value : 'collection';
}

function pba_photo_generate_storage_path($extension = 'jpg') {
    $extension = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $extension));

    if ($extension === '') {
        $extension = 'jpg';
    }

    $year = current_time('Y');
    $month = current_time('m');

    if (function_exists('wp_generate_uuid4')) {
        $uuid = wp_generate_uuid4();
    } else {
        $uuid = uniqid('photo-', true);
    }

    return 'photos/' . $year . '/' . $month . '/photo-' . $uuid . '.' . $extension;
}

function pba_photo_get_request_ip_address() {
    $keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    );

    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $value = sanitize_text_field(wp_unslash($_SERVER[$key]));

        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0]);
        }

        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function pba_photo_get_request_user_agent() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return null;
    }

    return sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
}

function pba_photo_get_gauge_level($used_percent) {
    $used_percent = (float) $used_percent;

    if ($used_percent >= 95) {
        return 'critical';
    }

    if ($used_percent >= 85) {
        return 'high';
    }

    if ($used_percent >= 70) {
        return 'warning';
    }

    return 'normal';
}
function pba_enqueue_photo_assets() {
    wp_enqueue_style(
        'pba-photos',
        plugins_url('assets/css/pba-photos.css', dirname(__FILE__)),
        array(),
        '1.0.9'
    );

    wp_enqueue_script(
        'pba-photos',
        plugins_url('assets/js/pba-photos.js', dirname(__FILE__)),
        array(),
        '1.0.1',
        true
    );
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_upload_document_item', 'pba_handle_upload_document_item');
add_action('admin_post_pba_deactivate_document_item', 'pba_handle_deactivate_document_item');
add_action('admin_post_pba_restore_document_item', 'pba_handle_restore_document_item');
add_action('admin_post_pba_save_document_item_metadata', 'pba_handle_save_document_item_metadata');
add_action('admin_post_pba_delete_document_item_permanently', 'pba_handle_delete_document_item_permanently');

function pba_get_allowed_document_upload_mime_types() {
    return array(
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    );
}

function pba_get_max_document_upload_size_bytes() {
    return 10 * 1024 * 1024;
}

function pba_get_document_upload_max_size_label() {
    return '10 MB';
}

function pba_validate_uploaded_document_file($file) {
    if (!is_array($file) || empty($file['name'])) {
        return new WP_Error('missing_document_file', 'No file was provided.');
    }

    if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('document_upload_failed', 'The uploaded file could not be processed.');
    }

    if (!isset($file['size']) || (int) $file['size'] < 1) {
        return new WP_Error('empty_document_file', 'The uploaded file is empty.');
    }

    $max_size = pba_get_max_document_upload_size_bytes();
    if ((int) $file['size'] > $max_size) {
        return new WP_Error('document_file_too_large', 'The uploaded file exceeds the allowed size.');
    }

    $filename = isset($file['name']) ? (string) $file['name'] : '';
    $check = wp_check_filetype_and_ext(
        isset($file['tmp_name']) ? $file['tmp_name'] : '',
        $filename,
        pba_get_allowed_document_upload_mime_types()
    );

    $ext = isset($check['ext']) ? (string) $check['ext'] : '';
    $type = isset($check['type']) ? (string) $check['type'] : '';

    if ($ext === '' || $type === '') {
        return new WP_Error('invalid_document_file_type', 'The uploaded file type is not allowed.');
    }

    return array(
        'ext'  => $ext,
        'type' => $type,
    );
}

function pba_get_document_upload_subdir_for_folder($folder) {
    $scope = isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '';
    $folder_id = isset($folder['document_folder_id']) ? (int) $folder['document_folder_id'] : 0;

    if ($folder_id < 1) {
        $folder_id = 0;
    }

    $year = gmdate('Y');
    $month = gmdate('m');
    $folder_segment = 'folder-' . $folder_id;

    if ($scope === 'Board') {
        return '/pba-documents/board/' . $folder_segment . '/' . $year . '/' . $month;
    }

    if ($scope === 'Committee') {
        return '/pba-documents/committee/' . $folder_segment . '/' . $year . '/' . $month;
    }

    if ($scope === 'Admin') {
        return '/pba-documents/admin/' . $folder_segment . '/' . $year . '/' . $month;
    }

    return '/pba-documents/general/' . $folder_segment . '/' . $year . '/' . $month;
}

function pba_document_upload_dir_filter($dirs) {
    $subdir = isset($GLOBALS['pba_document_upload_subdir']) ? (string) $GLOBALS['pba_document_upload_subdir'] : '';

    if ($subdir === '') {
        return $dirs;
    }

    $dirs['subdir'] = $subdir;
    $dirs['path']   = $dirs['basedir'] . $subdir;
    $dirs['url']    = $dirs['baseurl'] . $subdir;

    return $dirs;
}

function pba_get_document_item_row($document_item_id) {
    $document_item_id = (int) $document_item_id;

    if ($document_item_id < 1) {
        return false;
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select'           => 'document_item_id,document_folder_id,file_name,file_url,mime_type,document_title,document_date,document_category,document_version,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit'            => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return false;
    }

    return $rows[0];
}

if (!function_exists('pba_document_item_get_snapshot')) {
    function pba_document_item_get_snapshot($document_item_id) {
        $document_item_id = (int) $document_item_id;

        if ($document_item_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Document_Item', array(
            'select'           => 'document_item_id,document_folder_id,file_name,file_url,mime_type,document_title,document_date,document_category,document_version,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at',
            'document_item_id' => 'eq.' . $document_item_id,
            'limit'            => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_document_item_get_label')) {
    function pba_document_item_get_label($item_row) {
        if (!is_array($item_row)) {
            return '';
        }

        $title = trim((string) ($item_row['document_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $file_name = trim((string) ($item_row['file_name'] ?? ''));
        if ($file_name !== '') {
            return $file_name;
        }

        $document_item_id = isset($item_row['document_item_id']) ? (int) $item_row['document_item_id'] : 0;
        return $document_item_id > 0 ? 'Document #' . $document_item_id : '';
    }
}

if (!function_exists('pba_document_item_audit_log')) {
    function pba_document_item_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

function pba_get_uploads_relative_path_from_url($file_url) {
    $file_url = (string) $file_url;

    if ($file_url === '') {
        return '';
    }

    $uploads = wp_upload_dir();
    $baseurl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';

    if ($baseurl === '') {
        return '';
    }

    if (strpos($file_url, $baseurl . '/') === 0) {
        return ltrim(substr($file_url, strlen($baseurl)), '/');
    }

    return '';
}

function pba_delete_document_file_from_uploads($file_url) {
    $file_url = (string) $file_url;

    if ($file_url === '') {
        return true;
    }

    $uploads = wp_upload_dir();
    $basedir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';

    if ($basedir === '') {
        return false;
    }

    $relative_path = pba_get_uploads_relative_path_from_url($file_url);
    if ($relative_path === '') {
        return false;
    }

    $full_path = wp_normalize_path(trailingslashit($basedir) . $relative_path);

    if (!file_exists($full_path)) {
        return true;
    }

    wp_delete_file($full_path);

    return !file_exists($full_path);
}

function pba_handle_upload_document_item() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_item_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_item_nonce'], 'pba_document_item_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug         = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_id         = absint($_POST['folder_id'] ?? 0);
    $committee_id      = absint($_POST['committee_id'] ?? 0);
    $notes             = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
    $document_title    = sanitize_text_field(wp_unslash($_POST['document_title'] ?? ''));
    $document_category = sanitize_text_field(wp_unslash($_POST['document_category'] ?? ''));
    $document_version  = sanitize_text_field(wp_unslash($_POST['document_version'] ?? ''));
    $document_date_raw = sanitize_text_field(wp_unslash($_POST['document_date'] ?? ''));

    $document_date = null;
    if ($document_date_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $document_date_raw);
        if (!$dt || $dt->format('Y-m-d') !== $document_date_raw) {
            pba_documents_redirect($page_slug, 'invalid_document_date', $committee_id);
        }
        $document_date = $document_date_raw;
    }

    if ($folder_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_folder', $committee_id);
    }

    if (!pba_current_person_can_upload_to_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    if (empty($_FILES['document_file']) || empty($_FILES['document_file']['name'])) {
        pba_documents_redirect($page_slug, 'missing_document_file', $committee_id);
    }

    $folder = pba_get_document_folder($folder_id);
    if (!$folder) {
        pba_documents_redirect($page_slug, 'invalid_document_folder', $committee_id);
    }

    $validation = pba_validate_uploaded_document_file($_FILES['document_file']);
    if (is_wp_error($validation)) {
        pba_documents_redirect($page_slug, $validation->get_error_code(), $committee_id);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $GLOBALS['pba_document_upload_subdir'] = pba_get_document_upload_subdir_for_folder($folder);
    add_filter('upload_dir', 'pba_document_upload_dir_filter');

    $uploaded = wp_handle_upload($_FILES['document_file'], array(
        'test_form' => false,
        'mimes'     => pba_get_allowed_document_upload_mime_types(),
    ));

    remove_filter('upload_dir', 'pba_document_upload_dir_filter');
    unset($GLOBALS['pba_document_upload_subdir']);

    if (!empty($uploaded['error'])) {
        pba_document_item_audit_log(
            'document.uploaded',
            'Document_Item',
            null,
            array(
                'entity_label'              => $document_title !== '' ? $document_title : sanitize_file_name($_FILES['document_file']['name']),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'result_status'             => 'failure',
                'summary'                   => 'Document upload failed during file handling.',
                'details'                   => array(
                    'folder_id'          => $folder_id,
                    'folder_scope'       => isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '',
                    'file_name'          => sanitize_file_name($_FILES['document_file']['name']),
                    'upload_error'       => (string) $uploaded['error'],
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_upload_failed', $committee_id);
    }

    $original_name = sanitize_file_name($_FILES['document_file']['name']);
    $file_url      = isset($uploaded['url']) ? $uploaded['url'] : '';
    $mime_type     = isset($uploaded['type']) ? $uploaded['type'] : '';
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($file_url === '') {
        pba_document_item_audit_log(
            'document.uploaded',
            'Document_Item',
            null,
            array(
                'entity_label'              => $document_title !== '' ? $document_title : $original_name,
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'result_status'             => 'failure',
                'summary'                   => 'Document upload failed because uploaded file URL was empty.',
                'details'                   => array(
                    'folder_id'    => $folder_id,
                    'file_name'    => $original_name,
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_upload_failed', $committee_id);
    }

    if ($mime_type === '') {
        $mime_type = isset($validation['type']) ? $validation['type'] : '';
    }

    $inserted = pba_supabase_insert('Document_Item', array(
        'document_folder_id'         => $folder_id,
        'file_name'                  => $original_name,
        'file_url'                   => $file_url,
        'mime_type'                  => $mime_type !== '' ? $mime_type : null,
        'document_title'             => $document_title !== '' ? $document_title : null,
        'document_date'              => $document_date,
        'document_category'          => $document_category !== '' ? $document_category : null,
        'document_version'           => $document_version !== '' ? $document_version : null,
        'uploaded_by_person_id'      => $current_person_id > 0 ? $current_person_id : null,
        'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        'is_active'                  => true,
        'notes'                      => $notes !== '' ? $notes : null,
        'last_modified_at'           => gmdate('c'),
    ));

    if (is_wp_error($inserted)) {
        pba_document_item_audit_log(
            'document.uploaded',
            'Document_Item',
            null,
            array(
                'entity_label'              => $document_title !== '' ? $document_title : $original_name,
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'result_status'             => 'failure',
                'summary'                   => 'File uploaded, but document record creation failed.',
                'details'                   => array(
                    'folder_id'       => $folder_id,
                    'folder_scope'    => isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '',
                    'file_name'       => $original_name,
                    'file_url'        => $file_url,
                    'mime_type'       => $mime_type,
                    'error_code'      => $inserted->get_error_code(),
                    'error_message'   => $inserted->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_record_create_failed', $committee_id);
    }

    $document_item_id = isset($inserted['document_item_id']) ? (int) $inserted['document_item_id'] : 0;
    $after = $document_item_id > 0 ? pba_document_item_get_snapshot($document_item_id) : null;

    pba_document_item_audit_log(
        'document.uploaded',
        'Document_Item',
        $document_item_id > 0 ? $document_item_id : null,
        array(
            'entity_label'              => pba_document_item_get_label($after) !== '' ? pba_document_item_get_label($after) : ($document_title !== '' ? $document_title : $original_name),
            'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id > 0 ? $document_item_id : null,
            'summary'                   => 'Document uploaded.',
            'after'                     => $after,
            'details'                   => array(
                'folder_scope' => isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '',
            ),
        )
    );

    pba_documents_redirect($page_slug, 'document_uploaded', $committee_id);
}

function pba_handle_deactivate_document_item() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_item_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_item_nonce'], 'pba_document_item_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug        = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id = absint($_POST['document_item_id'] ?? 0);
    $committee_id     = absint($_POST['committee_id'] ?? 0);

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_delete', $committee_id);
    }

    $item = pba_get_document_item_row($document_item_id);
    if (!$item) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $before = pba_document_item_get_snapshot($document_item_id);
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'is_active'                  => false,
            'last_modified_at'           => gmdate('c'),
            'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_document_item_audit_log(
            'document.deleted',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_item_get_label($before ?: $item),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => 'Failed to deactivate document.',
                'before'                    => $before,
                'details'                   => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_delete_failed', $committee_id);
    }

    $after = pba_document_item_get_snapshot($document_item_id);

    pba_document_item_audit_log(
        'document.deleted',
        'Document_Item',
        $document_item_id,
        array(
            'entity_label'              => pba_document_item_get_label($after ?: $before ?: $item),
            'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id,
            'summary'                   => 'Document deactivated.',
            'before'                    => $before,
            'after'                     => $after,
        )
    );

    pba_documents_redirect($page_slug, 'document_deleted', $committee_id);
}

function pba_handle_restore_document_item() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_item_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_item_nonce'], 'pba_document_item_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug        = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id = absint($_POST['document_item_id'] ?? 0);
    $committee_id     = absint($_POST['committee_id'] ?? 0);

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_restore', $committee_id);
    }

    $item = pba_get_document_item_row($document_item_id);
    if (!$item) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $before = pba_document_item_get_snapshot($document_item_id);
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'is_active'                  => true,
            'last_modified_at'           => gmdate('c'),
            'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_document_item_audit_log(
            'document.restored',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_item_get_label($before ?: $item),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => 'Failed to restore document.',
                'before'                    => $before,
                'details'                   => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_restore_failed', $committee_id);
    }

    $after = pba_document_item_get_snapshot($document_item_id);

    pba_document_item_audit_log(
        'document.restored',
        'Document_Item',
        $document_item_id,
        array(
            'entity_label'              => pba_document_item_get_label($after ?: $before ?: $item),
            'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id,
            'summary'                   => 'Document restored.',
            'before'                    => $before,
            'after'                     => $after,
        )
    );

    pba_documents_redirect($page_slug, 'document_restored', $committee_id);
}

function pba_handle_save_document_item_metadata() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_item_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_item_nonce'], 'pba_document_item_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug         = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id  = absint($_POST['document_item_id'] ?? 0);
    $committee_id      = absint($_POST['committee_id'] ?? 0);
    $document_title    = sanitize_text_field(wp_unslash($_POST['document_title'] ?? ''));
    $document_category = sanitize_text_field(wp_unslash($_POST['document_category'] ?? ''));
    $document_version  = sanitize_text_field(wp_unslash($_POST['document_version'] ?? ''));
    $document_date_raw = sanitize_text_field(wp_unslash($_POST['document_date'] ?? ''));
    $notes             = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_edit', $committee_id);
    }

    $item = pba_get_document_item_row($document_item_id);
    if (!$item) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $before = pba_document_item_get_snapshot($document_item_id);
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $document_date = null;
    if ($document_date_raw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $document_date_raw);
        if (!$dt || $dt->format('Y-m-d') !== $document_date_raw) {
            pba_documents_redirect($page_slug, 'invalid_document_date', $committee_id);
        }
        $document_date = $document_date_raw;
    }

    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'document_title'             => $document_title !== '' ? $document_title : null,
            'document_date'              => $document_date,
            'document_category'          => $document_category !== '' ? $document_category : null,
            'document_version'           => $document_version !== '' ? $document_version : null,
            'notes'                      => $notes !== '' ? $notes : null,
            'last_modified_at'           => gmdate('c'),
            'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_document_item_audit_log(
            'document.updated',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_item_get_label($before ?: $item),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => 'Failed to save document metadata.',
                'before'                    => $before,
                'details'                   => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_edit_failed', $committee_id);
    }

    $after = pba_document_item_get_snapshot($document_item_id);

    pba_document_item_audit_log(
        'document.updated',
        'Document_Item',
        $document_item_id,
        array(
            'entity_label'              => pba_document_item_get_label($after ?: $before ?: $item),
            'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id,
            'summary'                   => 'Document metadata updated.',
            'before'                    => $before,
            'after'                     => $after,
        )
    );

    pba_documents_redirect($page_slug, 'document_saved', $committee_id);
}

function pba_handle_delete_document_item_permanently() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_item_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_item_nonce'], 'pba_document_item_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug        = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id = absint($_POST['document_item_id'] ?? 0);
    $committee_id     = absint($_POST['committee_id'] ?? 0);

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_delete', $committee_id);
    }

    $item = pba_get_document_item_row($document_item_id);
    if (!$item) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $before = pba_document_item_get_snapshot($document_item_id);
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;
    $is_active = !empty($item['is_active']);

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    if ($is_active) {
        pba_documents_redirect($page_slug, 'document_delete_failed', $committee_id);
    }

    $file_deleted = pba_delete_document_file_from_uploads(isset($item['file_url']) ? (string) $item['file_url'] : '');
    if (!$file_deleted) {
        pba_document_item_audit_log(
            'document.permanently_deleted',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_item_get_label($before ?: $item),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => 'Failed to permanently delete document because uploaded file could not be removed.',
                'before'                    => $before,
                'details'                   => array(
                    'file_url' => isset($item['file_url']) ? (string) $item['file_url'] : '',
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_delete_failed', $committee_id);
    }

    $deleted = pba_supabase_delete(
        'Document_Item',
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($deleted)) {
        pba_document_item_audit_log(
            'document.permanently_deleted',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_item_get_label($before ?: $item),
                'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => 'Failed to permanently delete document record.',
                'before'                    => $before,
                'details'                   => array(
                    'error_code'    => $deleted->get_error_code(),
                    'error_message' => $deleted->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'document_delete_failed', $committee_id);
    }

    pba_document_item_audit_log(
        'document.permanently_deleted',
        'Document_Item',
        $document_item_id,
        array(
            'entity_label'              => pba_document_item_get_label($before ?: $item),
            'target_committee_id'       => $committee_id > 0 ? $committee_id : null,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id,
            'summary'                   => 'Document permanently deleted.',
            'before'                    => $before,
            'after'                     => null,
            'details'                   => array(
                'file_deleted' => true,
            ),
        )
    );

    pba_documents_redirect($page_slug, 'document_permanently_deleted', $committee_id);
}
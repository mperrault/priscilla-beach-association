<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_upload_document_item', 'pba_handle_upload_document_item');
add_action('admin_post_pba_deactivate_document_item', 'pba_handle_deactivate_document_item');
add_action('admin_post_pba_restore_document_item', 'pba_handle_restore_document_item');
add_action('admin_post_pba_save_document_item_metadata', 'pba_handle_save_document_item_metadata');

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
        pba_documents_redirect($page_slug, 'document_upload_failed', $committee_id);
    }

    $original_name = sanitize_file_name($_FILES['document_file']['name']);
    $file_url      = isset($uploaded['url']) ? $uploaded['url'] : '';
    $mime_type     = isset($uploaded['type']) ? $uploaded['type'] : '';

    if ($file_url === '') {
        pba_documents_redirect($page_slug, 'document_upload_failed', $committee_id);
    }

    if ($mime_type === '') {
        $mime_type = isset($validation['type']) ? $validation['type'] : '';
    }

    $inserted = pba_supabase_insert('Document_Item', array(
        'document_folder_id'    => $folder_id,
        'file_name'             => $original_name,
        'file_url'              => $file_url,
        'mime_type'             => $mime_type !== '' ? $mime_type : null,
        'document_title'        => $document_title !== '' ? $document_title : null,
        'document_date'         => $document_date,
        'document_category'     => $document_category !== '' ? $document_category : null,
        'document_version'      => $document_version !== '' ? $document_version : null,
        'uploaded_by_person_id' => pba_current_person_id(),
        'is_active'             => true,
        'notes'                 => $notes !== '' ? $notes : null,
        'last_modified_at'      => gmdate('c'),
    ));

    if (is_wp_error($inserted)) {
        pba_documents_redirect($page_slug, 'document_record_create_failed', $committee_id);
    }

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

    $page_slug         = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id  = absint($_POST['document_item_id'] ?? 0);
    $committee_id      = absint($_POST['committee_id'] ?? 0);

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_delete', $committee_id);
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select'           => 'document_item_id,document_folder_id,is_active',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit'            => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $item = $rows[0];
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'is_active'        => false,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_documents_redirect($page_slug, 'document_delete_failed', $committee_id);
    }

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

    $page_slug         = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $document_item_id  = absint($_POST['document_item_id'] ?? 0);
    $committee_id      = absint($_POST['committee_id'] ?? 0);

    if ($document_item_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_document_restore', $committee_id);
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select'           => 'document_item_id,document_folder_id,is_active',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit'            => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $item = $rows[0];
    $folder_id = isset($item['document_folder_id']) ? (int) $item['document_folder_id'] : 0;

    if ($folder_id < 1 || !pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'is_active'        => true,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_documents_redirect($page_slug, 'document_restore_failed', $committee_id);
    }

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

    $rows = pba_supabase_get('Document_Item', array(
        'select'           => 'document_item_id,document_folder_id',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit'            => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_documents_redirect($page_slug, 'document_not_found', $committee_id);
    }

    $item = $rows[0];
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

    $updated = pba_supabase_update(
        'Document_Item',
        array(
            'document_title'    => $document_title !== '' ? $document_title : null,
            'document_date'     => $document_date,
            'document_category' => $document_category !== '' ? $document_category : null,
            'document_version'  => $document_version !== '' ? $document_version : null,
            'notes'             => $notes !== '' ? $notes : null,
            'last_modified_at'  => gmdate('c'),
        ),
        array(
            'document_item_id' => 'eq.' . $document_item_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_documents_redirect($page_slug, 'document_edit_failed', $committee_id);
    }

    pba_documents_redirect($page_slug, 'document_saved', $committee_id);
}
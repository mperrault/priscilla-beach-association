<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_create_document_folder', 'pba_handle_create_document_folder');
add_action('admin_post_pba_rename_document_folder', 'pba_handle_rename_document_folder');
add_action('admin_post_pba_deactivate_document_folder', 'pba_handle_deactivate_document_folder');

function pba_documents_redirect($page_slug, $status = '', $committee_id = 0) {
    $page_slug = pba_normalize_document_page_slug($page_slug);
    $url = home_url('/' . $page_slug . '/');

    $args = array();

    if ($status !== '') {
        $args['pba_documents_status'] = $status;
    }

    if ((int) $committee_id > 0) {
        $args['committee_id'] = (int) $committee_id;
    }

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    wp_safe_redirect($url);
    exit;
}

function pba_normalize_document_page_slug($raw_page) {
    $raw_page = sanitize_title((string) $raw_page);

    if ($raw_page === 'committee-documents') {
        return 'committee-documents';
    }

    return 'board-documents';
}

function pba_handle_create_document_folder() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_folder_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_folder_nonce'], 'pba_document_folder_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug    = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_name  = sanitize_text_field(wp_unslash($_POST['folder_name'] ?? ''));
    $folder_scope = sanitize_text_field(wp_unslash($_POST['folder_scope'] ?? ''));
    $committee_id = absint($_POST['committee_id'] ?? 0);
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($folder_name === '' || !in_array($folder_scope, array('Board', 'Committee', 'Admin'), true)) {
        pba_documents_redirect($page_slug, 'invalid_folder_input', $committee_id);
    }

    if (!pba_current_person_can_create_folder($folder_scope, $committee_id)) {
        wp_die('Unauthorized', 403);
    }

    if ($folder_scope !== 'Committee') {
        $committee_id = 0;
    }

    $inserted = pba_supabase_insert('Document_Folder', array(
        'folder_name'               => $folder_name,
        'folder_scope'              => $folder_scope,
        'committee_id'              => $committee_id > 0 ? $committee_id : null,
        'parent_folder_id'          => null,
        'display_order'             => null,
        'is_active'                 => true,
        'created_by_person_id'      => $current_person_id > 0 ? $current_person_id : null,
        'last_modified_by_person_id'=> $current_person_id > 0 ? $current_person_id : null,
        'notes'                     => null,
        'last_modified_at'          => gmdate('c'),
    ));

    if (is_wp_error($inserted)) {
        pba_documents_redirect($page_slug, 'folder_create_failed', $committee_id);
    }

    pba_documents_redirect($page_slug, 'folder_created', $committee_id);
}

function pba_handle_rename_document_folder() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_folder_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_folder_nonce'], 'pba_document_folder_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug    = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_id    = absint($_POST['folder_id'] ?? 0);
    $folder_name  = sanitize_text_field(wp_unslash($_POST['folder_name'] ?? ''));
    $committee_id = absint($_POST['committee_id'] ?? 0);
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($folder_id < 1 || $folder_name === '') {
        pba_documents_redirect($page_slug, 'invalid_folder_rename', $committee_id);
    }

    if (!pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $updated = pba_supabase_update(
        'Document_Folder',
        array(
            'folder_name'               => $folder_name,
            'last_modified_at'          => gmdate('c'),
            'last_modified_by_person_id'=> $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_folder_id' => 'eq.' . $folder_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_documents_redirect($page_slug, 'folder_rename_failed', $committee_id);
    }

    pba_documents_redirect($page_slug, 'folder_renamed', $committee_id);
}

function pba_handle_deactivate_document_folder() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_document_folder_nonce']) ||
        !wp_verify_nonce($_POST['pba_document_folder_nonce'], 'pba_document_folder_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $page_slug    = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_id    = absint($_POST['folder_id'] ?? 0);
    $committee_id = absint($_POST['committee_id'] ?? 0);
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($folder_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_folder_delete', $committee_id);
    }

    if (!pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $updated = pba_supabase_update(
        'Document_Folder',
        array(
            'is_active'                 => false,
            'last_modified_at'          => gmdate('c'),
            'last_modified_by_person_id'=> $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_folder_id' => 'eq.' . $folder_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_documents_redirect($page_slug, 'folder_delete_failed', $committee_id);
    }

    pba_documents_redirect($page_slug, 'folder_deleted', $committee_id);
}
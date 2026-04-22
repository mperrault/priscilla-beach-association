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

if (!function_exists('pba_document_folder_get_snapshot')) {
    function pba_document_folder_get_snapshot($folder_id) {
        $folder_id = (int) $folder_id;

        if ($folder_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Document_Folder', array(
            'select'             => 'document_folder_id,folder_name,folder_scope,committee_id,parent_folder_id,display_order,is_active,created_by_person_id,last_modified_by_person_id,notes,last_modified_at',
            'document_folder_id' => 'eq.' . $folder_id,
            'limit'              => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_document_folder_get_label')) {
    function pba_document_folder_get_label($folder_row) {
        if (!is_array($folder_row)) {
            return '';
        }

        $folder_name = trim((string) ($folder_row['folder_name'] ?? ''));

        if ($folder_name !== '') {
            return $folder_name;
        }

        $folder_id = isset($folder_row['document_folder_id']) ? (int) $folder_row['document_folder_id'] : 0;

        return $folder_id > 0 ? 'Folder #' . $folder_id : '';
    }
}

if (!function_exists('pba_document_folder_audit_log')) {
    function pba_document_folder_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
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

    $page_slug = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_name = sanitize_text_field(wp_unslash($_POST['folder_name'] ?? ''));

    // Accept the field name actually posted by the forms.
    $folder_scope = sanitize_text_field(wp_unslash($_POST['folder_scope_type'] ?? ''));

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
        'folder_name'                => $folder_name,
        'folder_scope'               => $folder_scope,
        'committee_id'               => $committee_id > 0 ? $committee_id : null,
        'parent_folder_id'           => null,
        'display_order'              => null,
        'is_active'                  => true,
        'created_by_person_id'       => $current_person_id > 0 ? $current_person_id : null,
        'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        'notes'                      => null,
        'last_modified_at'           => gmdate('c'),
    ));

    if (is_wp_error($inserted)) {
        pba_document_folder_audit_log(
            'document.folder.created',
            'Document_Folder',
            null,
            array(
                'entity_label'        => $folder_name,
                'target_committee_id' => $committee_id > 0 ? $committee_id : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to create document folder.',
                'details'             => array(
                    'folder_name'    => $folder_name,
                    'folder_scope'   => $folder_scope,
                    'committee_id'   => $committee_id > 0 ? $committee_id : null,
                    'error_code'     => $inserted->get_error_code(),
                    'error_message'  => $inserted->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'folder_create_failed', $committee_id);
    }

    $folder_id = isset($inserted['document_folder_id']) ? (int) $inserted['document_folder_id'] : 0;
    $after = $folder_id > 0 ? pba_document_folder_get_snapshot($folder_id) : null;

    pba_document_folder_audit_log(
        'document.folder.created',
        'Document_Folder',
        $folder_id > 0 ? $folder_id : null,
        array(
            'entity_label'        => pba_document_folder_get_label($after) !== '' ? pba_document_folder_get_label($after) : $folder_name,
            'target_committee_id' => $committee_id > 0 ? $committee_id : null,
            'summary'             => 'Document folder created.',
            'after'               => $after,
            'details'             => array(
                'folder_scope' => $folder_scope,
            ),
        )
    );

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

    $page_slug = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_id = absint($_POST['folder_id'] ?? 0);
    $folder_name = sanitize_text_field(wp_unslash($_POST['folder_name'] ?? ''));
    $committee_id = absint($_POST['committee_id'] ?? 0);
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($folder_id < 1 || $folder_name === '') {
        pba_documents_redirect($page_slug, 'invalid_folder_rename', $committee_id);
    }

    if (!pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $before = pba_document_folder_get_snapshot($folder_id);

    $updated = pba_supabase_update(
        'Document_Folder',
        array(
            'folder_name'                => $folder_name,
            'last_modified_at'           => gmdate('c'),
            'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_folder_id' => 'eq.' . $folder_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_document_folder_audit_log(
            'document.folder.renamed',
            'Document_Folder',
            $folder_id,
            array(
                'entity_label'        => pba_document_folder_get_label($before),
                'target_committee_id' => isset($before['committee_id']) ? (int) $before['committee_id'] : ($committee_id > 0 ? $committee_id : null),
                'result_status'       => 'failure',
                'summary'             => 'Failed to rename document folder.',
                'before'              => $before,
                'details'             => array(
                    'new_folder_name' => $folder_name,
                    'error_code'      => $updated->get_error_code(),
                    'error_message'   => $updated->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'folder_rename_failed', $committee_id);
    }

    $after = pba_document_folder_get_snapshot($folder_id);

    pba_document_folder_audit_log(
        'document.folder.renamed',
        'Document_Folder',
        $folder_id,
        array(
            'entity_label'        => pba_document_folder_get_label($after ?: $before),
            'target_committee_id' => isset($after['committee_id']) ? (int) $after['committee_id'] : (isset($before['committee_id']) ? (int) $before['committee_id'] : ($committee_id > 0 ? $committee_id : null)),
            'summary'             => 'Document folder renamed.',
            'before'              => $before,
            'after'               => $after,
        )
    );

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

    $page_slug = pba_normalize_document_page_slug(wp_unslash($_POST['page_slug'] ?? ''));
    $folder_id = absint($_POST['folder_id'] ?? 0);
    $committee_id = absint($_POST['committee_id'] ?? 0);
    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($folder_id < 1) {
        pba_documents_redirect($page_slug, 'invalid_folder_delete', $committee_id);
    }

    if (!pba_current_person_can_manage_folder($folder_id)) {
        wp_die('Unauthorized', 403);
    }

    $before = pba_document_folder_get_snapshot($folder_id);

    $updated = pba_supabase_update(
        'Document_Folder',
        array(
            'is_active'                  => false,
            'last_modified_at'           => gmdate('c'),
            'last_modified_by_person_id' => $current_person_id > 0 ? $current_person_id : null,
        ),
        array(
            'document_folder_id' => 'eq.' . $folder_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_document_folder_audit_log(
            'document.folder.deleted',
            'Document_Folder',
            $folder_id,
            array(
                'entity_label'        => pba_document_folder_get_label($before),
                'target_committee_id' => isset($before['committee_id']) ? (int) $before['committee_id'] : ($committee_id > 0 ? $committee_id : null),
                'result_status'       => 'failure',
                'summary'             => 'Failed to deactivate document folder.',
                'before'              => $before,
                'details'             => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_documents_redirect($page_slug, 'folder_delete_failed', $committee_id);
    }

    $after = pba_document_folder_get_snapshot($folder_id);

    pba_document_folder_audit_log(
        'document.folder.deleted',
        'Document_Folder',
        $folder_id,
        array(
            'entity_label'        => pba_document_folder_get_label($after ?: $before),
            'target_committee_id' => isset($after['committee_id']) ? (int) $after['committee_id'] : (isset($before['committee_id']) ? (int) $before['committee_id'] : ($committee_id > 0 ? $committee_id : null)),
            'summary'             => 'Document folder deactivated.',
            'before'              => $before,
            'after'               => $after,
        )
    );

    pba_documents_redirect($page_slug, 'folder_deleted', $committee_id);
}
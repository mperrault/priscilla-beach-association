<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_save_committee_admin', 'pba_handle_save_committee_admin');

function pba_committees_redirect($status = '', $committee_id = 0, $view = 'list') {
    $args = array();

    if ($status !== '') {
        $args['pba_committees_status'] = $status;
    }

    if ($committee_id > 0) {
        $args['committee_id'] = (int) $committee_id;
    }

    if ($view !== '') {
        $args['committee_view'] = $view;
    }

    wp_safe_redirect(add_query_arg($args, home_url('/committees/')));
    exit;
}

if (!function_exists('pba_committee_admin_get_committee_snapshot')) {
    function pba_committee_admin_get_committee_snapshot($committee_id) {
        $committee_id = (int) $committee_id;

        if ($committee_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Committee', array(
            'select'       => 'committee_id,committee_name,committee_description,status,display_order,notes,last_modified_at',
            'committee_id' => 'eq.' . $committee_id,
            'limit'        => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_committee_admin_get_committee_label')) {
    function pba_committee_admin_get_committee_label($committee_row) {
        if (!is_array($committee_row)) {
            return '';
        }

        $committee_name = trim((string) ($committee_row['committee_name'] ?? ''));

        if ($committee_name !== '') {
            return $committee_name;
        }

        $committee_id = isset($committee_row['committee_id']) ? (int) $committee_row['committee_id'] : 0;

        return $committee_id > 0 ? 'Committee #' . $committee_id : '';
    }
}

if (!function_exists('pba_committee_admin_audit_log')) {
    function pba_committee_admin_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

function pba_handle_save_committee_admin() {
    if (!is_user_logged_in() || !pba_current_person_has_role('PBAAdmin')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_committee_admin_nonce']) ||
        !wp_verify_nonce($_POST['pba_committee_admin_nonce'], 'pba_committee_admin_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $committee_id          = isset($_POST['committee_id']) ? absint($_POST['committee_id']) : 0;
    $committee_name        = isset($_POST['committee_name']) ? sanitize_text_field(wp_unslash($_POST['committee_name'])) : '';
    $committee_description = isset($_POST['committee_description']) ? sanitize_textarea_field(wp_unslash($_POST['committee_description'])) : '';
    $status                = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Active';
    $display_order         = isset($_POST['display_order']) && $_POST['display_order'] !== '' ? intval($_POST['display_order']) : null;
    $notes                 = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

    if ($committee_id < 1 || $committee_name === '') {
        pba_committees_redirect('invalid_committee_input', $committee_id, 'edit');
    }

    $before = pba_committee_admin_get_committee_snapshot($committee_id);

    $payload = array(
        'committee_name'        => $committee_name,
        'committee_description' => $committee_description !== '' ? $committee_description : null,
        'status'                => $status,
        'display_order'         => $display_order,
        'notes'                 => $notes !== '' ? $notes : null,
        'last_modified_at'      => gmdate('c'),
    );

    $updated = pba_supabase_update(
        'Committee',
        $payload,
        array(
            'committee_id' => 'eq.' . $committee_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_committee_admin_audit_log(
            'committee.updated',
            'Committee',
            $committee_id,
            array(
                'entity_label'        => pba_committee_admin_get_committee_label($before),
                'target_committee_id' => $committee_id,
                'result_status'       => 'failure',
                'summary'             => 'Failed to save committee changes.',
                'before'              => $before,
                'details'             => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_committees_redirect('committee_update_failed', $committee_id, 'edit');
    }

    $after = pba_committee_admin_get_committee_snapshot($committee_id);

    pba_committee_admin_audit_log(
        'committee.updated',
        'Committee',
        $committee_id,
        array(
            'entity_label'        => pba_committee_admin_get_committee_label($after ?: $before),
            'target_committee_id' => $committee_id,
            'summary'             => 'Committee record updated by admin.',
            'before'              => $before,
            'after'               => $after,
        )
    );

    pba_committees_redirect('committee_saved', $committee_id, 'edit');
}
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

    $updated = pba_supabase_update(
        'Committee',
        array(
            'committee_name'        => $committee_name,
            'committee_description' => $committee_description !== '' ? $committee_description : null,
            'status'                => $status,
            'display_order'         => $display_order,
            'notes'                 => $notes !== '' ? $notes : null,
            'last_modified_at'      => gmdate('c'),
        ),
        array(
            'committee_id' => 'eq.' . $committee_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_committees_redirect('committee_update_failed', $committee_id, 'edit');
    }

    pba_committees_redirect('committee_saved', $committee_id, 'edit');
}
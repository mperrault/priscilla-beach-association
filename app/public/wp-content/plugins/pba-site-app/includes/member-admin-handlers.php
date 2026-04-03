<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_save_member_admin', 'pba_handle_save_member_admin');

function pba_members_redirect($status = '', $member_id = 0, $view = 'list') {
    $args = array();

    if ($status !== '') {
        $args['pba_members_status'] = $status;
    }

    if ($member_id > 0) {
        $args['member_id'] = (int) $member_id;
    }

    if ($view !== '') {
        $args['member_view'] = $view;
    }

    wp_safe_redirect(add_query_arg($args, home_url('/members/')));
    exit;
}

function pba_handle_save_member_admin() {
    if (!is_user_logged_in() || !pba_current_person_has_role('PBAAdmin')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_member_admin_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_admin_nonce'], 'pba_member_admin_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $member_id      = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $household_id   = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $first_name     = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name      = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address  = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $status         = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Unregistered';

    $role_ids       = isset($_POST['role_ids']) ? array_map('absint', (array) $_POST['role_ids']) : array();
    $committee_ids  = isset($_POST['committee_ids']) ? array_map('absint', (array) $_POST['committee_ids']) : array();
    $committee_roles = isset($_POST['committee_roles']) ? (array) $_POST['committee_roles'] : array();

    if ($member_id < 1 || $household_id < 1 || $first_name === '' || $last_name === '') {
        pba_members_redirect('invalid_member_input', $member_id, 'edit');
    }

    if ($email_address !== '' && !is_email($email_address)) {
        pba_members_redirect('invalid_member_email', $member_id, 'edit');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'household_id'      => $household_id,
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'email_address'     => $email_address !== '' ? $email_address : null,
            'status'            => $status,
            'last_modified_at'  => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $member_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    pba_member_admin_replace_roles($member_id, $role_ids);
    pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles);

    pba_members_redirect('member_saved', $member_id, 'edit');
}

function pba_member_admin_replace_roles($member_id, $role_ids) {
    $member_id = (int) $member_id;

    $existing = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_role_id'])) {
                pba_supabase_delete('Person_to_Role', array(
                    'person_to_role_id' => 'eq.' . (int) $row['person_to_role_id'],
                ));
            }
        }
    }

    foreach ($role_ids as $role_id) {
        pba_supabase_insert('Person_to_Role', array(
            'person_id'        => $member_id,
            'role_id'          => (int) $role_id,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'last_modified_at' => gmdate('c'),
        ));
    }
}

function pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles) {
    $member_id = (int) $member_id;

    $existing = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_to_committee_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_committee_id'])) {
                pba_supabase_delete('Person_to_Committee', array(
                    'person_to_committee_id' => 'eq.' . (int) $row['person_to_committee_id'],
                ));
            }
        }
    }

    foreach ($committee_ids as $committee_id) {
        $committee_role = isset($committee_roles[$committee_id])
            ? sanitize_text_field(wp_unslash($committee_roles[$committee_id]))
            : '';

        pba_supabase_insert('Person_to_Committee', array(
            'person_id'        => $member_id,
            'committee_id'     => (int) $committee_id,
            'committee_role'   => $committee_role !== '' ? $committee_role : null,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'display_order'    => null,
            'last_modified_at' => gmdate('c'),
        ));
    }
}
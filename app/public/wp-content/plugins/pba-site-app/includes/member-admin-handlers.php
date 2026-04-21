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
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_member_admin_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_admin_nonce'], 'pba_member_admin_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $member_id       = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $household_id    = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $first_name      = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name       = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address   = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $status          = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Unregistered';

    $role_ids        = isset($_POST['role_ids']) ? array_map('absint', (array) $_POST['role_ids']) : array();
    $committee_ids   = isset($_POST['committee_ids']) ? array_map('absint', (array) $_POST['committee_ids']) : array();
    $committee_roles = isset($_POST['committee_roles']) ? (array) $_POST['committee_roles'] : array();

    $role_ids = array_values(array_unique(array_filter($role_ids, function ($id) {
        return (int) $id > 0;
    })));

    $committee_ids = array_values(array_unique(array_filter($committee_ids, function ($id) {
        return (int) $id > 0;
    })));

    if ($member_id < 1 || $first_name === '' || $last_name === '') {
        pba_members_redirect('invalid_member_input', $member_id, 'edit');
    }

    if ($email_address !== '' && !is_email($email_address)) {
        pba_members_redirect('invalid_member_email', $member_id, 'edit');
    }
    $directory_visibility_level = isset($_POST['directory_visibility_level'])
        ? sanitize_text_field(wp_unslash($_POST['directory_visibility_level']))
        : 'hidden';

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        $directory_visibility_level = 'hidden';
    }
    $updated = pba_supabase_update(
        'Person',
        array(
            'first_name'       => $first_name,
            'last_name'        => $last_name,
            'email_address'    => $email_address !== '' ? $email_address : null,
            'directory_visibility_level' => $directory_visibility_level,
            'status'           => $status,
            'household_id' => $household_id > 0 ? $household_id : null,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $member_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (!pba_member_admin_replace_roles($member_id, $role_ids)) {
        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (!pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles)) {
        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (function_exists('pba_sync_wp_roles_for_person')) {
        pba_sync_wp_roles_for_person($member_id);
    }

    pba_members_redirect('member_saved', $member_id, 'edit');
}

function pba_member_admin_replace_roles($member_id, $role_ids) {
    $member_id = (int) $member_id;

    if ($member_id < 1) {
        return false;
    }

    $existing = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_role_id'])) {
                $deleted = pba_supabase_delete('Person_to_Role', array(
                    'person_to_role_id' => 'eq.' . (int) $row['person_to_role_id'],
                ));

                if (is_wp_error($deleted)) {
                    return false;
                }
            }
        }
    }

    foreach ($role_ids as $role_id) {
        $inserted = pba_supabase_insert('Person_to_Role', array(
            'person_id'        => $member_id,
            'role_id'          => (int) $role_id,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'last_modified_at' => gmdate('c'),
        ));

        if (is_wp_error($inserted)) {
            return false;
        }
    }

    return true;
}

function pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles) {
    $member_id = (int) $member_id;

    if ($member_id < 1) {
        return false;
    }

    $existing = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_to_committee_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_committee_id'])) {
                $deleted = pba_supabase_delete('Person_to_Committee', array(
                    'person_to_committee_id' => 'eq.' . (int) $row['person_to_committee_id'],
                ));

                if (is_wp_error($deleted)) {
                    return false;
                }
            }
        }
    }

    foreach ($committee_ids as $committee_id) {
        $committee_role = isset($committee_roles[$committee_id])
            ? sanitize_text_field(wp_unslash($committee_roles[$committee_id]))
            : '';

        $inserted = pba_supabase_insert('Person_to_Committee', array(
            'person_id'        => $member_id,
            'committee_id'     => (int) $committee_id,
            'committee_role'   => $committee_role !== '' ? $committee_role : null,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'display_order'    => null,
            'last_modified_at' => gmdate('c'),
        ));

        if (is_wp_error($inserted)) {
            return false;
        }
    }

    return true;
}
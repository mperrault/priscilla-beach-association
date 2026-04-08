<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_admin_disable_member', 'pba_handle_admin_disable_member');
add_action('admin_post_pba_admin_enable_member', 'pba_handle_admin_enable_member');
add_action('admin_post_pba_admin_cancel_invite', 'pba_handle_admin_cancel_invite');
add_action('admin_post_pba_admin_resend_invite', 'pba_handle_admin_resend_invite');

function pba_clear_managed_wp_roles_for_user($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return;
    }

    $user = get_user_by('id', $wp_user_id);
    if (!$user) {
        return;
    }

    $managed_roles = function_exists('pba_get_managed_wp_role_slugs')
        ? pba_get_managed_wp_role_slugs()
        : array('pba_member', 'pba_house_admin', 'pba_board_member', 'pba_committee_member', 'pba_admin');

    foreach ($managed_roles as $role_slug) {
        if (in_array($role_slug, (array) $user->roles, true)) {
            $user->remove_role($role_slug);
        }
    }
}

function pba_handle_admin_disable_member() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('disable_failed', 0, 'list');
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,status,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('disable_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Disabled',
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_members_redirect('disable_failed', $person_id, 'edit');
    }

    /*
     * Disabled users should no longer carry effective mirrored PBA access.
     * Clear managed WP roles now, then destroy active sessions.
     */
    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;

    if ($wp_user_id > 0) {
        pba_clear_managed_wp_roles_for_user($wp_user_id);

        if (class_exists('WP_Session_Tokens')) {
            $manager = WP_Session_Tokens::get_instance($wp_user_id);
            $manager->destroy_all();
        }
    }

    pba_members_redirect('member_disabled', $person_id, 'edit');
}

function pba_handle_admin_enable_member() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('enable_failed', 0, 'list');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Active',
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_members_redirect('enable_failed', $person_id, 'edit');
    }

    if (function_exists('pba_sync_wp_roles_for_person')) {
        pba_sync_wp_roles_for_person($person_id);
    }

    pba_members_redirect('member_enabled', $person_id, 'edit');
}

function pba_handle_admin_cancel_invite() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('cancel_failed', 0, 'list');
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,status,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    if (($person['status'] ?? '') !== 'Pending') {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $delete_links = pba_supabase_delete('Person_to_Role', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_links)) {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $delete_committees = pba_supabase_delete('Person_to_Committee', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_committees)) {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;
    if ($wp_user_id > 0) {
        pba_clear_managed_wp_roles_for_user($wp_user_id);
    }

    $delete_person = pba_supabase_delete('Person', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_person)) {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    pba_delete_member_invite_transients($person_id);

    pba_members_redirect('invite_cancelled', 0, 'list');
}

function pba_handle_admin_resend_invite() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('resend_failed', 0, 'list');
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,first_name,last_name,email_address,status,invited_by_person_id,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    if (($person['status'] ?? '') !== 'Expired') {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $member_role_id = pba_supabase_find_role_id_by_name('PBAMember');

    if (!$member_role_id) {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Pending',
            'email_verified'   => 0,
            'wp_user_id'       => null,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $existing_role_links = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id,person_id,role_id',
        'person_id' => 'eq.' . $person_id,
        'role_id'   => 'eq.' . (int) $member_role_id,
        'limit'     => 1,
    ));

    if (!is_wp_error($existing_role_links) && empty($existing_role_links)) {
        $ptr = pba_create_person_to_role_record($person_id, $member_role_id);
        if (is_wp_error($ptr)) {
            pba_members_redirect('resend_failed', $person_id, 'edit');
        }
    }

    /*
     * Since the person is now pending and unlinked from a WP user, no immediate
     * WP-role sync is needed here. The eventual login/registration path will sync.
     */
    pba_delete_member_invite_transients($person_id);

    $invite_token = pba_store_member_invite_token(
        $person_id,
        (int) ($person['household_id'] ?? 0),
        (string) ($person['email_address'] ?? ''),
        (string) ($person['first_name'] ?? ''),
        (string) ($person['last_name'] ?? ''),
        'PBAMember'
    );

    $sent = pba_send_member_invite_email(array(
        'first_name'    => $person['first_name'] ?? '',
        'last_name'     => $person['last_name'] ?? '',
        'email_address' => $person['email_address'] ?? '',
    ), $invite_token);

    if (!$sent) {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    pba_members_redirect('invite_resent', $person_id, 'edit');
}
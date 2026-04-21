<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_household_send_invites', 'pba_handle_household_send_invites');
add_action('admin_post_nopriv_pba_accept_member_invite', 'pba_handle_member_invite_accept');
add_action('admin_post_pba_accept_member_invite', 'pba_handle_member_invite_accept');

add_action('admin_post_pba_household_disable_member', 'pba_handle_household_disable_member');
add_action('admin_post_pba_household_enable_member', 'pba_handle_household_enable_member');
add_action('admin_post_pba_household_cancel_invite', 'pba_handle_household_cancel_invite');
add_action('admin_post_pba_household_resend_invite', 'pba_handle_household_resend_invite');

function pba_handle_household_send_invites() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_household_invite_nonce']) ||
        !wp_verify_nonce($_POST['pba_household_invite_nonce'], 'pba_household_invite_action')
    ) {
        pba_household_redirect('invalid_nonce');
    }

    $first_names = isset($_POST['invite_first_name']) ? array_values((array) wp_unslash($_POST['invite_first_name'])) : array();
    $last_names  = isset($_POST['invite_last_name']) ? array_values((array) wp_unslash($_POST['invite_last_name'])) : array();
    $emails      = isset($_POST['invite_email']) ? array_values((array) wp_unslash($_POST['invite_email'])) : array();

    $household_id      = (int) pba_get_current_household_id();
    $inviter_person_id = (int) pba_get_current_house_admin_person_id();

    if (empty($household_id) || empty($inviter_person_id)) {
        pba_household_redirect('missing_household_context');
    }

    $member_role_id = pba_supabase_find_role_id_by_name('PBAMember');

    if (!$member_role_id) {
        pba_household_redirect('member_role_lookup_failed');
    }

    $row_count = max(count($first_names), count($last_names), count($emails));

    if ($row_count < 1) {
        pba_household_redirect('no_invites_created');
    }

    $normalized_rows = array();

    for ($i = 0; $i < $row_count; $i++) {
        $first_name_raw = isset($first_names[$i]) ? (string) $first_names[$i] : '';
        $last_name_raw  = isset($last_names[$i]) ? (string) $last_names[$i] : '';
        $email_raw      = isset($emails[$i]) ? (string) $emails[$i] : '';

        if ($first_name_raw === '' || $last_name_raw === '' || $email_raw === '') {
            pba_household_redirect('invalid_invite_row');
        }

        if (
            $first_name_raw !== trim($first_name_raw) ||
            $last_name_raw !== trim($last_name_raw) ||
            $email_raw !== trim($email_raw)
        ) {
            pba_household_redirect('invalid_invite_row');
        }

        $first_name = sanitize_text_field($first_name_raw);
        $last_name  = sanitize_text_field($last_name_raw);
        $email      = sanitize_email($email_raw);

        if (
            !preg_match("/^[A-Za-z][A-Za-z' -]{0,49}$/", $first_name) ||
            !preg_match("/^[A-Za-z][A-Za-z' -]{0,49}$/", $last_name)
        ) {
            pba_household_redirect('invalid_invite_name');
        }

        if (!is_email($email)) {
            pba_household_redirect('invalid_invite_email');
        }

        $normalized_rows[] = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
        );
    }

    $submitted_emails = wp_list_pluck($normalized_rows, 'email');
    if (count($submitted_emails) !== count(array_unique(array_map('strtolower', $submitted_emails)))) {
        pba_household_redirect('duplicate_invite_email');
    }

    $created_count = 0;
    $email_fail_count = 0;
    $duplicate_messages = array();

    foreach ($normalized_rows as $row) {
        $first_name = $row['first_name'];
        $last_name  = $row['last_name'];
        $email      = $row['email'];

        $existing_people = pba_supabase_get('Person', array(
            'select'        => 'person_id,household_id,first_name,last_name,email_address,status,invited_by_person_id,wp_user_id',
            'email_address' => 'eq.' . $email,
            'limit'         => 1,
        ));

        if (is_wp_error($existing_people)) {
            continue;
        }

        $person_id = 0;
        $existing_person = array();

        if (!empty($existing_people[0]) && is_array($existing_people[0])) {
            $existing_person = $existing_people[0];
            $person_id = (int) $existing_person['person_id'];

            $existing_status = isset($existing_person['status']) ? (string) $existing_person['status'] : '';
            $existing_household_id = isset($existing_person['household_id']) ? (int) $existing_person['household_id'] : 0;

            if ($existing_status === 'Active' || $existing_status === 'Pending') {
                $message = $email . ' was already invited';

                $inviter_label = '';
                $existing_inviter_person_id = isset($existing_person['invited_by_person_id']) ? (int) $existing_person['invited_by_person_id'] : 0;

                if ($existing_inviter_person_id > 0) {
                    $inviter_name = pba_get_person_display_name($existing_inviter_person_id);
                    if ($inviter_name !== '') {
                        $inviter_label = $inviter_name;
                    }
                }

                if (!empty($existing_household_id)) {
                    if ($existing_household_id === $household_id) {
                        if ($inviter_label !== '') {
                            $message .= ' by this household (' . $inviter_label . ').';
                        } else {
                            $message .= ' by this household.';
                        }
                    } else {
                        $household_label = pba_get_household_label($existing_household_id);
                        if ($inviter_label !== '') {
                            $message .= ' by ' . $household_label . ' (' . $inviter_label . ').';
                        } else {
                            $message .= ' by ' . $household_label . '.';
                        }
                    }
                } else {
                    if ($inviter_label !== '') {
                        $message .= ' by ' . $inviter_label . '.';
                    } else {
                        $message .= '.';
                    }
                }

                $duplicate_messages[] = $message;
                continue;
            }

            $updated = pba_supabase_update(
                'Person',
                array(
                    'household_id'         => $household_id,
                    'first_name'           => $first_name,
                    'last_name'            => $last_name,
                    'email_address'        => $email,
                    'invited_by_person_id' => $inviter_person_id,
                    'email_verified'       => 0,
                    'wp_user_id'           => null,
                    'status'               => 'Pending',
                    'last_modified_at'     => gmdate('c'),
                ),
                array(
                    'person_id' => 'eq.' . $person_id,
                )
            );

            if (is_wp_error($updated)) {
                continue;
            }
        } else {
            $person = pba_create_person_record(array(
                'household_id'         => $household_id,
                'first_name'           => $first_name,
                'last_name'            => $last_name,
                'email'                => $email,
                'status'               => 'Pending',
                'email_verified'       => 0,
                'wp_user_id'           => null,
                'invited_by_person_id' => $inviter_person_id,
            ));

            if (is_wp_error($person) || empty($person['person_id'])) {
                continue;
            }

            $person_id = (int) $person['person_id'];
        }

        if (empty($person_id)) {
            continue;
        }

        $existing_role_links = pba_supabase_get('Person_to_Role', array(
            'select'    => 'person_to_role_id,person_id,role_id',
            'person_id' => 'eq.' . $person_id,
            'role_id'   => 'eq.' . (int) $member_role_id,
            'limit'     => 1,
        ));

        if (is_wp_error($existing_role_links)) {
            continue;
        }

        if (empty($existing_role_links)) {
            $ptr = pba_create_person_to_role_record($person_id, $member_role_id);
            if (is_wp_error($ptr)) {
                continue;
            }
        }

        $invite_token = pba_store_member_invite_token(
            $person_id,
            $household_id,
            $email,
            $first_name,
            $last_name,
            'PBAMember'
        );

        $sent = pba_send_member_invite_email(array(
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email_address' => $email,
        ), $invite_token);

        if (!$sent) {
            $email_fail_count++;
        }

        $created_count++;
    }

    if (!empty($duplicate_messages)) {
        set_transient(
            'pba_household_duplicate_messages_' . get_current_user_id(),
            $duplicate_messages,
            5 * MINUTE_IN_SECONDS
        );
    }

    if ($created_count < 1 && !empty($duplicate_messages)) {
        pba_household_redirect('already_invited');
    }

    if ($created_count < 1) {
        pba_household_redirect('no_invites_created');
    }

    if ($email_fail_count > 0 && !empty($duplicate_messages)) {
        pba_household_redirect('invite_created_email_partial_with_duplicates');
    }

    if ($email_fail_count > 0) {
        pba_household_redirect('invite_created_email_partial');
    }

    if (!empty($duplicate_messages)) {
        pba_household_redirect('invite_created_with_duplicates');
    }

    pba_household_redirect('invite_created');
}

function pba_handle_member_invite_accept() {
    if (
        !isset($_POST['pba_member_invite_accept_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_invite_accept_nonce'], 'pba_member_invite_accept_action')
    ) {
        pba_member_invite_redirect('invalid_nonce');
    }

    $invite_token    = isset($_POST['invite_token']) ? sanitize_text_field(wp_unslash($_POST['invite_token'])) : '';
    $password        = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $password_verify = isset($_POST['password_verify']) ? (string) wp_unslash($_POST['password_verify']) : '';

    if ($invite_token === '') {
        pba_member_invite_redirect('invalid_token');
    }

    $invite_data = get_transient(pba_member_invite_token_key($invite_token));

    if (!is_array($invite_data) || empty($invite_data['person_id']) || empty($invite_data['email'])) {
        pba_member_invite_redirect('invalid_token');
    }

    if ($password === '' || $password_verify === '') {
        pba_member_invite_redirect('missing_password', $invite_token);
    }

    if ($password !== $password_verify) {
        pba_member_invite_redirect('password_mismatch', $invite_token);
    }

    if (strlen($password) < 8) {
        pba_member_invite_redirect('password_too_short', $invite_token);
    }

    $person_id    = (int) $invite_data['person_id'];
    $household_id = (int) $invite_data['household_id'];
    $email        = $invite_data['email'];
    $first_name   = isset($invite_data['first_name']) ? $invite_data['first_name'] : '';
    $last_name    = isset($invite_data['last_name']) ? $invite_data['last_name'] : '';
    $directory_visibility_level = isset($_POST['directory_visibility_level'])
        ? sanitize_text_field(wp_unslash($_POST['directory_visibility_level']))
        : '';

    if ($directory_visibility_level === '') {
        wp_safe_redirect(add_query_arg(array(
            'invite_token' => rawurlencode($invite_token),
            'pba_invite_status' => 'missing_directory_visibility',
            'directory_visibility_level' => 'hidden',
        ), home_url('/member-invite-accept/')));
        exit;
    }

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        wp_safe_redirect(add_query_arg(array(
            'invite_token' => rawurlencode($invite_token),
            'pba_invite_status' => 'invalid_directory_visibility',
            'directory_visibility_level' => 'hidden',
        ), home_url('/member-invite-accept/')));
        exit;
    }
    $person_rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,first_name,last_name,email_address,status',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($person_rows) || empty($person_rows[0])) {
        pba_member_invite_redirect('person_not_found');
    }

    $person_row = $person_rows[0];

    if ((string) $person_row['email_address'] !== (string) $email) {
        pba_member_invite_redirect('email_mismatch');
    }

    if ((int) $person_row['household_id'] !== $household_id) {
        pba_member_invite_redirect('household_mismatch');
    }

    if (isset($person_row['status']) && $person_row['status'] === 'Active') {
        pba_member_invite_redirect('already_accepted');
    }

    if (username_exists($email) || email_exists($email)) {
        pba_member_invite_redirect('user_exists');
    }

    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        pba_member_invite_redirect('create_failed');
    }

    wp_update_user(array(
        'ID'           => $user_id,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name),
    ));

    update_user_meta($user_id, 'pba_household_id', $household_id);
    update_user_meta($user_id, 'pba_person_id', $person_id);

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Active',
            'email_verified'   => 1,
            'directory_visibility_level' => $directory_visibility_level,
            'wp_user_id'       => (string) $user_id,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        pba_member_invite_redirect('person_update_failed', $invite_token);
    }

    pba_sync_wp_role_for_person($user_id, $person_id);

    pba_delete_member_invite_transients($person_id, $invite_token);

    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    $user = get_user_by('id', $user_id);
    if ($user instanceof WP_User) {
        do_action('wp_login', $user->user_login, $user);
    }

    wp_safe_redirect(home_url('/member-home/'));
    exit;
}

function pba_household_get_action_person() {
    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;
    $household_id = (int) pba_get_current_household_id();
    $inviter_person_id = (int) pba_get_current_house_admin_person_id();

    if ($person_id < 1 || $household_id < 1 || $inviter_person_id < 1) {
        return array(false, false);
    }

    $rows = pba_supabase_get('Person', array(
        'select'               => 'person_id,household_id,first_name,last_name,email_address,status,invited_by_person_id,wp_user_id,last_modified_at',
        'person_id'            => 'eq.' . $person_id,
        'household_id'         => 'eq.' . $household_id,
        'invited_by_person_id' => 'eq.' . $inviter_person_id,
        'limit'                => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return array(false, false);
    }

    return array($rows[0], array(
        'household_id'      => $household_id,
        'inviter_person_id' => $inviter_person_id,
    ));
}

function pba_handle_household_disable_member() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_household_disable_nonce']) ||
        !wp_verify_nonce($_POST['pba_household_disable_nonce'], 'pba_household_disable_action')
    ) {
        pba_household_redirect('invalid_nonce');
    }

    list($person, $context) = pba_household_get_action_person();

    if (!$person) {
        pba_household_redirect('disable_failed');
    }

    if ((string) $person['status'] !== 'Active') {
        pba_household_redirect('disable_failed');
    }

    $person_id = (int) $person['person_id'];
    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;

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
        pba_household_redirect('disable_failed');
    }

    if ($wp_user_id > 0 && class_exists('WP_Session_Tokens')) {
        $manager = WP_Session_Tokens::get_instance($wp_user_id);
        $manager->destroy_all();
    }

    pba_household_redirect('member_disabled');
}

function pba_handle_household_enable_member() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_household_enable_nonce']) ||
        !wp_verify_nonce($_POST['pba_household_enable_nonce'], 'pba_household_enable_action')
    ) {
        pba_household_redirect('invalid_nonce');
    }

    list($person, $context) = pba_household_get_action_person();

    if (!$person) {
        pba_household_redirect('enable_failed');
    }

    if ((string) $person['status'] !== 'Disabled') {
        pba_household_redirect('enable_failed');
    }

    $person_id = (int) $person['person_id'];

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
        pba_household_redirect('enable_failed');
    }

    pba_household_redirect('member_enabled');
}

function pba_handle_household_cancel_invite() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_household_cancel_nonce']) ||
        !wp_verify_nonce($_POST['pba_household_cancel_nonce'], 'pba_household_cancel_action')
    ) {
        pba_household_redirect('invalid_nonce');
    }

    list($person, $context) = pba_household_get_action_person();

    if (!$person) {
        pba_household_redirect('cancel_failed');
    }

    if ((string) $person['status'] !== 'Pending') {
        pba_household_redirect('cancel_failed');
    }

    $person_id = (int) $person['person_id'];

    $delete_links = pba_supabase_delete('Person_to_Role', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_links)) {
        pba_household_redirect('cancel_failed');
    }

    $delete_person = pba_supabase_delete('Person', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_person)) {
        pba_household_redirect('cancel_failed');
    }

    pba_delete_member_invite_transients($person_id);

    pba_household_redirect('invite_cancelled');
}

function pba_handle_household_resend_invite() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_household_resend_nonce']) ||
        !wp_verify_nonce($_POST['pba_household_resend_nonce'], 'pba_household_resend_action')
    ) {
        pba_household_redirect('invalid_nonce');
    }

    list($person, $context) = pba_household_get_action_person();

    if (!$person) {
        pba_household_redirect('resend_failed');
    }

    if ((string) $person['status'] !== 'Expired') {
        pba_household_redirect('resend_failed');
    }

    $person_id = (int) $person['person_id'];
    $household_id = (int) $context['household_id'];
    $inviter_person_id = (int) $context['inviter_person_id'];
    $email = isset($person['email_address']) ? (string) $person['email_address'] : '';
    $first_name = isset($person['first_name']) ? (string) $person['first_name'] : '';
    $last_name = isset($person['last_name']) ? (string) $person['last_name'] : '';

    $member_role_id = pba_supabase_find_role_id_by_name('PBAMember');

    if (!$member_role_id) {
        pba_household_redirect('resend_failed');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'               => 'Pending',
            'email_verified'       => 0,
            'wp_user_id'           => null,
            'invited_by_person_id' => $inviter_person_id,
            'household_id'         => $household_id,
            'last_modified_at'     => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_household_redirect('resend_failed');
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
            pba_household_redirect('resend_failed');
        }
    }

    pba_delete_member_invite_transients($person_id);

    $invite_token = pba_store_member_invite_token(
        $person_id,
        $household_id,
        $email,
        $first_name,
        $last_name,
        'PBAMember'
    );

    $sent = pba_send_member_invite_email(array(
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'email_address' => $email,
    ), $invite_token);

    if (!$sent) {
        pba_household_redirect('resend_failed');
    }

    pba_household_redirect('invite_resent');
}
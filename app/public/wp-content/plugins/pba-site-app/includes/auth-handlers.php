<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_nopriv_pba_member_login', 'pba_handle_member_login');
add_action('admin_post_pba_member_login', 'pba_handle_member_login');

add_action('admin_post_nopriv_pba_member_logout', 'pba_handle_member_logout');
add_action('admin_post_pba_member_logout', 'pba_handle_member_logout');

add_action('admin_post_nopriv_pba_house_admin_verify', 'pba_handle_house_admin_verify');
add_action('admin_post_pba_house_admin_verify', 'pba_handle_house_admin_verify');

add_action('admin_post_nopriv_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');
add_action('admin_post_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');

add_action('admin_post_nopriv_pba_reset_password_request', 'pba_handle_reset_password_request');
add_action('admin_post_pba_reset_password_request', 'pba_handle_reset_password_request');

add_action('admin_post_nopriv_pba_reset_password_confirm', 'pba_handle_reset_password_confirm');
add_action('admin_post_pba_reset_password_confirm', 'pba_handle_reset_password_confirm');

if (!function_exists('pba_auth_audit_log')) {
    function pba_auth_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

if (!function_exists('pba_auth_get_person_snapshot')) {
    function pba_auth_get_person_snapshot($person_id) {
        $person_id = (int) $person_id;

        if ($person_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,household_id,first_name,last_name,email_address,status,email_verified,wp_user_id,invited_by_person_id,directory_visibility_level,last_modified_at',
            'person_id' => 'eq.' . $person_id,
            'limit'     => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_auth_get_person_label')) {
    function pba_auth_get_person_label($person_row, $fallback_email = '') {
        if (is_array($person_row)) {
            $first_name = trim((string) ($person_row['first_name'] ?? ''));
            $last_name = trim((string) ($person_row['last_name'] ?? ''));
            $label = trim($first_name . ' ' . $last_name);

            if ($label !== '') {
                return $label;
            }

            $email = trim((string) ($person_row['email_address'] ?? ''));
            if ($email !== '') {
                return $email;
            }

            $person_id = isset($person_row['person_id']) ? (int) $person_row['person_id'] : 0;
            if ($person_id > 0) {
                return 'Person #' . $person_id;
            }
        }

        $fallback_email = trim((string) $fallback_email);

        return $fallback_email;
    }
}

function pba_auth_normalize_compare_value($value) {
    $value = is_string($value) ? $value : (string) $value;
    $value = trim(wp_strip_all_tags($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower($value);
}

function pba_auth_find_allowed_street($street_name) {
    $normalized_input = pba_auth_normalize_compare_value($street_name);

    foreach (pba_allowed_streets() as $allowed_street) {
        if (pba_auth_normalize_compare_value($allowed_street) === $normalized_input) {
            return $allowed_street;
        }
    }

    return '';
}

function pba_auth_values_match($left, $right) {
    return pba_auth_normalize_compare_value($left) === pba_auth_normalize_compare_value($right);
}

function pba_reset_password_redirect($status, $args = array()) {
    $query_args = array_merge(
        array(
            'pba_reset_status' => $status,
        ),
        $args
    );

    wp_safe_redirect(add_query_arg($query_args, home_url('/reset-password/')));
    exit;
}

function pba_handle_member_logout() {
    if (is_user_logged_in()) {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'pba_member_logout_action')) {
            wp_safe_redirect(home_url('/member-home/'));
            exit;
        }

        $user_id = get_current_user_id();
        $person_id = (int) get_user_meta($user_id, 'pba_person_id', true);
        $person_snapshot = pba_auth_get_person_snapshot($person_id);

        pba_auth_audit_log(
            'auth.logout',
            'Person',
            $person_id > 0 ? $person_id : null,
            array(
                'entity_label'        => pba_auth_get_person_label($person_snapshot, ''),
                'target_person_id'    => $person_id > 0 ? $person_id : null,
                'target_household_id' => is_array($person_snapshot) && isset($person_snapshot['household_id']) ? (int) $person_snapshot['household_id'] : null,
                'summary'             => 'Logout succeeded.',
                'details'             => array(
                    'wp_user_id' => $user_id > 0 ? $user_id : null,
                ),
            )
        );

        delete_user_meta($user_id, 'pba_last_activity');
        wp_logout();
    }

    wp_safe_redirect(add_query_arg(
        array(
            'logged_out' => '1',
        ),
        home_url('/login/')
    ));
    exit;
}

function pba_handle_member_login() {
    if (
        !isset($_POST['pba_member_login_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_login_nonce'], 'pba_member_login_action')
    ) {
        pba_registration_redirect('invalid_nonce');
    }

    $email    = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
    $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';

    if ($email === '' || $password === '') {
        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => 'missing_login_fields',
                'login_email'         => rawurlencode($email),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    wp_clear_auth_cookie();
    wp_set_current_user(0);

    $creds = array(
        'user_login'    => $email,
        'user_password' => $password,
        'remember'      => false,
    );

    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        $status = 'login_failed';

        if ($user->get_error_code() === 'pba_account_disabled') {
            $status = 'account_disabled';
        }

        pba_auth_audit_log(
            'auth.login.failure',
            'Auth',
            null,
            array(
                'entity_label'  => $email,
                'result_status' => 'failure',
                'summary'       => 'Login failed.',
                'details'       => array(
                    'email'         => $email,
                    'error_code'    => $user->get_error_code(),
                    'error_message' => $user->get_error_message(),
                    'status'        => $status,
                ),
            )
        );

        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => $status,
                'login_email'         => rawurlencode($email),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    $person_id = 0;
    $person_snapshot = null;

    if ($user instanceof WP_User) {
        wp_set_current_user($user->ID);

        delete_user_meta($user->ID, 'pba_last_activity');
        update_user_meta($user->ID, 'pba_last_activity', time());

        do_action('wp_login', $user->user_login, $user);

        $person_id = (int) get_user_meta($user->ID, 'pba_person_id', true);
        $person_snapshot = pba_auth_get_person_snapshot($person_id);
    }

    pba_auth_audit_log(
        'auth.login.success',
        'Person',
        $person_id > 0 ? $person_id : null,
        array(
            'entity_label'        => pba_auth_get_person_label($person_snapshot, $email),
            'target_person_id'    => $person_id > 0 ? $person_id : null,
            'target_household_id' => is_array($person_snapshot) && isset($person_snapshot['household_id']) ? (int) $person_snapshot['household_id'] : null,
            'summary'             => 'Login succeeded.',
            'details'             => array(
                'email'      => $email,
                'wp_user_id' => ($user instanceof WP_User) ? (int) $user->ID : null,
                'roles'      => ($user instanceof WP_User) ? array_values((array) $user->roles) : array(),
            ),
        )
    );

    $roles = (array) $user->roles;
    if (
        in_array('pba_house_admin', $roles, true) ||
        in_array('pba_admin', $roles, true)
    ) {
        wp_safe_redirect(home_url('/household/'));
        exit;
    }

    wp_safe_redirect(home_url('/member-home/'));
    exit;
}

function pba_handle_reset_password_request() {
    if (
        !isset($_POST['pba_reset_password_request_nonce']) ||
        !wp_verify_nonce($_POST['pba_reset_password_request_nonce'], 'pba_reset_password_request_action')
    ) {
        pba_reset_password_redirect('invalid_nonce');
    }

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

    if ($email === '') {
        pba_reset_password_redirect('missing_email');
    }

    if (!is_email($email)) {
        pba_reset_password_redirect(
            'invalid_email',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    $user = get_user_by('email', $email);

    if (!$user instanceof WP_User) {
        pba_reset_password_redirect(
            'check_email',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    $person_id = (int) get_user_meta($user->ID, 'pba_person_id', true);

    if ($person_id < 1) {
        pba_reset_password_redirect(
            'check_email',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    $person_rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,status,email_address,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (
        is_wp_error($person_rows) ||
        empty($person_rows) ||
        !isset($person_rows[0]) ||
        !is_array($person_rows[0])
    ) {
        pba_reset_password_redirect(
            'check_email',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    $person_row = $person_rows[0];
    $person_status = isset($person_row['status']) ? pba_auth_normalize_compare_value($person_row['status']) : '';
    $person_email = isset($person_row['email_address']) ? (string) $person_row['email_address'] : '';
    $person_wp_user_id = isset($person_row['wp_user_id']) ? (int) $person_row['wp_user_id'] : 0;

    if (
        $person_status !== 'active' ||
        !pba_auth_values_match($person_email, $user->user_email) ||
        $person_wp_user_id !== (int) $user->ID
    ) {
        pba_reset_password_redirect(
            'check_email',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    $token = wp_generate_password(64, false, false);

    update_user_meta($user->ID, 'pba_active_reset_token', $token);

    set_transient(
        'pba_password_reset_' . $token,
        array(
            'user_id' => (int) $user->ID,
            'email'   => $user->user_email,
        ),
        30 * MINUTE_IN_SECONDS
    );

    $link = add_query_arg(
        array(
            'pba_reset_status' => 'reset_link',
            'email_token'      => rawurlencode($token),
        ),
        home_url('/reset-password/')
    );

    $subject = 'Reset Your Priscilla Beach Association (PBA) Password';

    $display_name = trim((string) $user->display_name);
    if ($display_name === '') {
        $display_name = $user->user_email;
    }

    $message = "
      <h1>Reset Your PBA Password</h1>
      <h2>Hello {$display_name},</h2>
      <p>We received a request to reset your password for your Priscilla Beach Association (PBA) account.</p>
      <p>To reset your password, please click the link below:</p>
      <p><a href='{$link}'>Reset My Password</a></p>
      <p>This link will expire in 30 minutes.</p>
      <p>If you did not request a password reset, you can safely ignore this email.</p>
      <p>Best regards,<br>The Priscilla Beach Association Team</p>
      <p><strong>Contact us:</strong> info@priscillabeachassociation.com</p>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $sent = wp_mail($user->user_email, $subject, $message, $headers);

    if (!$sent) {
        delete_user_meta($user->ID, 'pba_active_reset_token');

        pba_auth_audit_log(
            'auth.password_reset.requested',
            'Person',
            $person_id,
            array(
                'entity_label'     => pba_auth_get_person_label($person_row, $email),
                'target_person_id' => $person_id,
                'result_status'    => 'failure',
                'summary'          => 'Password reset requested, but reset email failed to send.',
                'before'           => $person_row,
                'details'          => array(
                    'email'      => $email,
                    'wp_user_id' => (int) $user->ID,
                    'email_sent' => false,
                ),
            )
        );

        pba_reset_password_redirect(
            'email_send_failed',
            array(
                'email' => rawurlencode($email),
            )
        );
    }

    pba_auth_audit_log(
        'auth.password_reset.requested',
        'Person',
        $person_id,
        array(
            'entity_label'     => pba_auth_get_person_label($person_row, $email),
            'target_person_id' => $person_id,
            'summary'          => 'Password reset requested; reset email sent.',
            'before'           => $person_row,
            'details'          => array(
                'email'      => $email,
                'wp_user_id' => (int) $user->ID,
                'email_sent' => true,
            ),
        )
    );

    pba_reset_password_redirect(
        'check_email',
        array(
            'email' => rawurlencode($email),
        )
    );
}

function pba_handle_reset_password_confirm() {
    if (
        !isset($_POST['pba_reset_password_confirm_nonce']) ||
        !wp_verify_nonce($_POST['pba_reset_password_confirm_nonce'], 'pba_reset_password_confirm_action')
    ) {
        pba_reset_password_redirect('invalid_nonce');
    }

    $email_token     = isset($_POST['email_token']) ? sanitize_text_field(wp_unslash($_POST['email_token'])) : '';
    $password        = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $password_verify = isset($_POST['password_verify']) ? (string) wp_unslash($_POST['password_verify']) : '';

    if ($email_token === '') {
        pba_reset_password_redirect('invalid_token');
    }

    $reset_data = get_transient('pba_password_reset_' . $email_token);

    if (!is_array($reset_data) || empty($reset_data['email']) || empty($reset_data['user_id'])) {
        pba_reset_password_redirect('invalid_token');
    }

    $user_id = (int) $reset_data['user_id'];
    $active_token = (string) get_user_meta($user_id, 'pba_active_reset_token', true);

    if ($active_token === '' || !hash_equals($active_token, $email_token)) {
        delete_transient('pba_password_reset_' . $email_token);
        pba_reset_password_redirect('invalid_token');
    }

    if ($password === '' || $password_verify === '') {
        pba_reset_password_redirect(
            'missing_password',
            array(
                'email_token' => rawurlencode($email_token),
            )
        );
    }

    if ($password !== $password_verify) {
        pba_reset_password_redirect(
            'password_mismatch',
            array(
                'email_token' => rawurlencode($email_token),
            )
        );
    }

    if (strlen($password) < 8) {
        pba_reset_password_redirect(
            'password_too_short',
            array(
                'email_token' => rawurlencode($email_token),
            )
        );
    }

    $user = get_user_by('id', $user_id);

    if (!$user instanceof WP_User) {
        delete_transient('pba_password_reset_' . $email_token);
        delete_user_meta($user_id, 'pba_active_reset_token');
        pba_reset_password_redirect('invalid_token');
    }

    $person_id = (int) get_user_meta($user_id, 'pba_person_id', true);
    $before = pba_auth_get_person_snapshot($person_id);

    wp_set_password($password, $user_id);

    delete_transient('pba_password_reset_' . $email_token);
    delete_user_meta($user_id, 'pba_active_reset_token');

    wp_clear_auth_cookie();
    wp_set_current_user(0);

    $after = pba_auth_get_person_snapshot($person_id);

    pba_auth_audit_log(
        'auth.password_reset.completed',
        'Person',
        $person_id > 0 ? $person_id : null,
        array(
            'entity_label'        => pba_auth_get_person_label($after ?: $before, $user->user_email),
            'target_person_id'    => $person_id > 0 ? $person_id : null,
            'target_household_id' => is_array($after) && isset($after['household_id']) ? (int) $after['household_id'] : (is_array($before) && isset($before['household_id']) ? (int) $before['household_id'] : null),
            'summary'             => 'Password reset completed.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'wp_user_id' => $user_id,
                'email'      => $user->user_email,
            ),
        )
    );

    wp_safe_redirect(add_query_arg(
        array(
            'pba_register_status' => 'password_reset',
            'login_email'         => rawurlencode($user->user_email),
        ),
        home_url('/login/')
    ));
    exit;
}

function pba_handle_house_admin_verify() {
    if (
        !isset($_POST['pba_house_admin_verify_nonce']) ||
        !wp_verify_nonce($_POST['pba_house_admin_verify_nonce'], 'pba_house_admin_verify_action')
    ) {
        pba_registration_redirect('invalid_nonce');
    }

    $first_name   = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name    = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $house_number = isset($_POST['house_number']) ? sanitize_text_field(wp_unslash($_POST['house_number'])) : '';
    $street_name  = isset($_POST['street_name']) ? sanitize_text_field(wp_unslash($_POST['street_name'])) : '';
    $email        = isset($_POST['register_email']) ? sanitize_email(wp_unslash($_POST['register_email'])) : '';

    if ($first_name === '' || $last_name === '' || $house_number === '' || $street_name === '' || $email === '') {
        pba_registration_redirect('missing_fields');
    }

    if (!is_email($email)) {
        pba_registration_redirect('invalid_email');
    }

    if (!preg_match('/^[0-9A-Za-z\\-\\/ ]{1,10}$/', $house_number)) {
        pba_registration_redirect('invalid_house_number');
    }

    $matched_allowed_street = pba_auth_find_allowed_street($street_name);
    if ($matched_allowed_street === '') {
        pba_registration_redirect('invalid_street');
    }

    /*
     * House Admin verification matching rule:
     *
     * Required match fields:
     * - Household.pb_street_number
     * - Household.pb_street_name
     * - Person.email_address OR Household.household_admin_email_address
     * - Person.last_name OR Household.household_admin_last_name
     *
     * First name is intentionally ignored for matching because it may vary
     * between formal names, nicknames, initials, or imported assessor data.
     */
    $household_rows = pba_supabase_get('Household', array(
        'select'           => 'household_id,household_admin_email_address,household_admin_first_name,household_admin_last_name,pb_street_number,pb_street_name,household_status',
        'pb_street_number' => 'eq.' . $house_number,
        'limit'            => 25,
    ));

    if (is_wp_error($household_rows)) {
        pba_registration_redirect('lookup_failed');
    }

    if (empty($household_rows) || !is_array($household_rows)) {
        pba_registration_redirect('no_match');
    }

    $matched_household = null;
    $existing_person   = null;

    foreach ($household_rows as $row) {
        $street_matches = pba_auth_values_match(
            isset($row['pb_street_name']) ? $row['pb_street_name'] : '',
            $matched_allowed_street
        );

        if (!$street_matches || empty($row['household_id'])) {
            continue;
        }

        $candidate_household_id = (int) $row['household_id'];

        if ($candidate_household_id < 1) {
            continue;
        }

        /*
         * Preferred source of truth:
         * Match an existing Person in the household by last name + email.
         * First name is not used for matching.
         */
        $people_rows = pba_supabase_get('Person', array(
            'select'       => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id',
            'household_id' => 'eq.' . $candidate_household_id,
            'limit'        => 100,
        ));

        if (is_wp_error($people_rows)) {
            pba_registration_redirect('lookup_failed');
        }

        $matched_person_for_household = null;

        if (!empty($people_rows) && is_array($people_rows)) {
            foreach ($people_rows as $person_row) {
                $person_last_name_matches = pba_auth_values_match(
                    isset($person_row['last_name']) ? $person_row['last_name'] : '',
                    $last_name
                );

                $person_email_matches = pba_auth_values_match(
                    isset($person_row['email_address']) ? $person_row['email_address'] : '',
                    $email
                );

                if ($person_last_name_matches && $person_email_matches) {
                    $matched_person_for_household = $person_row;
                    break;
                }
            }
        }

        /*
         * Legacy fallback:
         * Match Household admin fields by last name + email.
         * First name is not used for matching.
         */
        $household_admin_last_name_matches = pba_auth_values_match(
            isset($row['household_admin_last_name']) ? $row['household_admin_last_name'] : '',
            $last_name
        );

        $household_admin_email_matches = pba_auth_values_match(
            isset($row['household_admin_email_address']) ? $row['household_admin_email_address'] : '',
            $email
        );

        $household_admin_fields_match = (
            $household_admin_last_name_matches &&
            $household_admin_email_matches
        );

        if ($matched_person_for_household || $household_admin_fields_match) {
            $matched_household = $row;
            $existing_person   = $matched_person_for_household;
            break;
        }
    }

    if (!$matched_household || empty($matched_household['household_id'])) {
        pba_registration_redirect('no_match');
    }

    $household_id = (int) $matched_household['household_id'];

    if ($household_id < 1) {
        pba_registration_redirect('lookup_failed');
    }

    $existing_person_id = 0;
    $existing_wp_user_id = 0;

    if ($existing_person) {
        $existing_person_id = isset($existing_person['person_id']) ? (int) $existing_person['person_id'] : 0;
        $existing_wp_user_id = isset($existing_person['wp_user_id']) ? (int) $existing_person['wp_user_id'] : 0;

        if ($existing_wp_user_id > 0) {
            pba_registration_redirect('user_exists');
        }
    }

    /*
     * If the household matched only through the legacy Household admin fields,
     * try to locate a matching Person again by last name + email. This handles
     * cases where the Person exists but was not found during the preferred path.
     */
    if ($existing_person_id < 1) {
        $matching_people = pba_supabase_get('Person', array(
            'select'        => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id',
            'household_id'  => 'eq.' . $household_id,
            'last_name'     => 'eq.' . $last_name,
            'email_address' => 'eq.' . $email,
            'limit'         => 1,
        ));

        if (is_wp_error($matching_people)) {
            pba_registration_redirect('lookup_failed');
        }

        if (!empty($matching_people[0])) {
            $existing_person_id = isset($matching_people[0]['person_id']) ? (int) $matching_people[0]['person_id'] : 0;
            $existing_wp_user_id = isset($matching_people[0]['wp_user_id']) ? (int) $matching_people[0]['wp_user_id'] : 0;

            if ($existing_wp_user_id > 0) {
                pba_registration_redirect('user_exists');
            }
        }
    }

    $token = wp_generate_password(64, false, false);

    set_transient(
        'pba_house_admin_email_verify_' . $token,
        array(
            'household_id' => (string) $household_id,
            'person_id'    => $existing_person_id,
            'email'        => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
        ),
        30 * MINUTE_IN_SECONDS
    );

    $link = add_query_arg(
        array(
            'pba_register_status' => 'email_verified_link',
            'email_token'         => rawurlencode($token),
        ),
        home_url('/login/')
    );

    $subject = 'Welcome to the Priscilla Beach Association (PBA)!';

    $message = "
      <h1>Complete Your PBA House Admin Account Setup</h1>
      <h2>Hello {$first_name} {$last_name},</h2>
      <p>Thank you for registering as a <strong>House Admin</strong> for the Priscilla Beach Association (PBA)!</p>
      <p>To complete your account setup, please click the link below to set your password:</p>
      <p><a href='{$link}'>Set My Password</a></p>
      <p>This link will expire in 30 minutes, so please be sure to complete the setup soon.</p>
      <p>If you did not request this registration or need assistance, please contact the PBA Admin.</p>
      <p>Best regards,<br>The Priscilla Beach Association Team</p>
      <p><strong>Contact us:</strong> info@priscillabeachassociation.com</p>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $sent = wp_mail($email, $subject, $message, $headers);

    if (!$sent) {
        pba_auth_audit_log(
            'auth.house_admin.verification_requested',
            'Household',
            $household_id,
            array(
                'entity_label'        => $email,
                'target_household_id' => $household_id,
                'target_person_id'    => $existing_person_id > 0 ? $existing_person_id : null,
                'result_status'       => 'failure',
                'summary'             => 'House Admin verification passed, but setup email failed to send.',
                'details'             => array(
                    'email'      => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ),
            )
        );

        pba_registration_redirect('email_send_failed');
    }

    pba_auth_audit_log(
        'auth.house_admin.verification_requested',
        'Household',
        $household_id,
        array(
            'entity_label'        => $email,
            'target_household_id' => $household_id,
            'target_person_id'    => $existing_person_id > 0 ? $existing_person_id : null,
            'summary'             => 'House Admin verification succeeded; setup email sent.',
            'details'             => array(
                'email'              => $email,
                'first_name'         => $first_name,
                'last_name'          => $last_name,
                'existing_person_id' => $existing_person_id > 0 ? $existing_person_id : null,
                'match_rule'         => 'street_number_street_name_last_name_email',
            ),
        )
    );

    pba_registration_redirect('check_email');
}

function pba_handle_house_admin_create_user() {
    if (
        !isset($_POST['pba_house_admin_create_user_nonce']) ||
        !wp_verify_nonce($_POST['pba_house_admin_create_user_nonce'], 'pba_house_admin_create_user_action')
    ) {
        pba_registration_redirect('invalid_nonce');
    }

    $email_token     = isset($_POST['email_token']) ? sanitize_text_field(wp_unslash($_POST['email_token'])) : '';
    $password        = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $password_verify = isset($_POST['password_verify']) ? (string) wp_unslash($_POST['password_verify']) : '';

    $allowed_directory_visibility_levels = array(
        'hidden',
        'name_only',
        'name_email',
    );

    $directory_visibility_level = isset($_POST['directory_visibility_level'])
        ? sanitize_key(wp_unslash($_POST['directory_visibility_level']))
        : 'hidden';

    if ($email_token === '') {
        pba_registration_redirect('invalid_token');
    }

    $setup_data = get_transient('pba_house_admin_email_verify_' . $email_token);

    if (!is_array($setup_data) || empty($setup_data['email'])) {
        pba_registration_redirect('invalid_token');
    }

    if (!in_array($directory_visibility_level, $allowed_directory_visibility_levels, true)) {
        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => 'invalid_directory_visibility',
                'email_token'         => rawurlencode($email_token),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    if ($password === '' || $password_verify === '') {
        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => 'missing_password',
                'email_token'         => rawurlencode($email_token),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    if ($password !== $password_verify) {
        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => 'password_mismatch',
                'email_token'         => rawurlencode($email_token),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    if (strlen($password) < 8) {
        $redirect_url = add_query_arg(
            array(
                'pba_register_status' => 'password_too_short',
                'email_token'         => rawurlencode($email_token),
            ),
            home_url('/login/')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    $email        = isset($setup_data['email']) ? (string) $setup_data['email'] : '';
    $person_id    = isset($setup_data['person_id']) ? (int) $setup_data['person_id'] : 0;
    $household_id = isset($setup_data['household_id']) ? (int) $setup_data['household_id'] : 0;
    $first_name   = isset($setup_data['first_name']) ? (string) $setup_data['first_name'] : '';
    $last_name    = isset($setup_data['last_name']) ? (string) $setup_data['last_name'] : '';

    $before = $person_id > 0 ? pba_auth_get_person_snapshot($person_id) : null;

    if (username_exists($email) || email_exists($email)) {
        delete_transient('pba_house_admin_email_verify_' . $email_token);
        pba_registration_redirect('user_exists');
    }

    if ($person_id > 0) {
        $person_rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,wp_user_id',
            'person_id' => 'eq.' . $person_id,
            'limit'     => 1,
        ));

        if (is_wp_error($person_rows) || empty($person_rows[0])) {
            pba_registration_redirect('lookup_failed');
        }

        $existing_wp_user_id = isset($person_rows[0]['wp_user_id']) ? (int) $person_rows[0]['wp_user_id'] : 0;
        if ($existing_wp_user_id > 0) {
            delete_transient('pba_house_admin_email_verify_' . $email_token);
            pba_registration_redirect('user_exists');
        }
    }

    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        pba_registration_redirect('create_failed');
    }

    wp_update_user(array(
        'ID'           => $user_id,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim($first_name . ' ' . $last_name),
    ));

    if ($household_id > 0) {
        update_user_meta($user_id, 'pba_household_id', $household_id);
        update_user_meta($user_id, 'pba_is_house_admin', 1);
    }

    if ($person_id > 0) {
        $updated_person = pba_supabase_update(
            'Person',
            array(
                'household_id'                => $household_id,
                'first_name'                  => $first_name,
                'last_name'                   => $last_name,
                'email_address'               => $email,
                'status'                      => 'Active',
                'email_verified'              => 1,
                'wp_user_id'                  => (string) $user_id,
                'directory_visibility_level'  => $directory_visibility_level,
                'last_modified_at'            => gmdate('c'),
            ),
            array(
                'person_id' => 'eq.' . $person_id,
            )
        );

        if (is_wp_error($updated_person)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            pba_registration_redirect('person_create_failed');
        }
    } else {
        $person = pba_create_person_record(array(
            'household_id'   => $household_id,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'status'         => 'Active',
            'email_verified' => 1,
            'wp_user_id'     => (string) $user_id,
        ));

        if (is_wp_error($person) || empty($person['person_id'])) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            pba_registration_redirect('person_create_failed');
        }

        $person_id = (int) $person['person_id'];

        /*
         * Keep this as a direct update after creation rather than assuming
         * pba_create_person_record() accepts directory_visibility_level.
         */
        $updated_person_visibility = pba_supabase_update(
            'Person',
            array(
                'directory_visibility_level' => $directory_visibility_level,
                'last_modified_at'           => gmdate('c'),
            ),
            array(
                'person_id' => 'eq.' . $person_id,
            )
        );

        if (is_wp_error($updated_person_visibility)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            pba_registration_redirect('person_create_failed');
        }
    }

    update_user_meta($user_id, 'pba_person_id', $person_id);

    $house_admin_role_id = pba_supabase_find_role_id_by_name('PBAHouseholdAdmin');

    if (!$house_admin_role_id) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        pba_registration_redirect('role_lookup_failed');
    }

    $existing_role_links = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id',
        'person_id' => 'eq.' . $person_id,
        'role_id'   => 'eq.' . (int) $house_admin_role_id,
        'limit'     => 1,
    ));

    if (is_wp_error($existing_role_links)) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        pba_registration_redirect('person_role_create_failed');
    }

    if (empty($existing_role_links)) {
        $person_role = pba_create_person_to_role_record($person_id, $house_admin_role_id);

        if (is_wp_error($person_role)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user_id);
            pba_registration_redirect('person_role_create_failed');
        }
    }

    pba_sync_wp_role_for_person($person_id);

    delete_transient('pba_house_admin_email_verify_' . $email_token);

    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    $user = get_user_by('id', $user_id);
    if ($user instanceof WP_User) {
        do_action('wp_login', $user->user_login, $user);
    }

    $after = pba_auth_get_person_snapshot($person_id);

    pba_auth_audit_log(
        'auth.house_admin.account_created',
        'Person',
        $person_id > 0 ? $person_id : null,
        array(
            'entity_label'        => pba_auth_get_person_label($after ?: $before, $email),
            'target_person_id'    => $person_id > 0 ? $person_id : null,
            'target_household_id' => $household_id > 0 ? $household_id : null,
            'summary'             => 'House Admin account created and signed in.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'email'                      => $email,
                'wp_user_id'                 => $user_id,
                'directory_visibility_level' => $directory_visibility_level,
            ),
        )
    );

    pba_household_redirect('account_created');
}

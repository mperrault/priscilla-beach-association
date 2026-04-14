<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_nopriv_pba_member_login', 'pba_handle_member_login');
add_action('admin_post_pba_member_login', 'pba_handle_member_login');

add_action('admin_post_nopriv_pba_house_admin_verify', 'pba_handle_house_admin_verify');
add_action('admin_post_pba_house_admin_verify', 'pba_handle_house_admin_verify');

add_action('admin_post_nopriv_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');
add_action('admin_post_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');

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

    // Ensure any stale auth/session state from a timed-out session is cleared
    // before attempting a fresh sign-in.
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

    if ($user instanceof WP_User) {
        wp_set_current_user($user->ID);
    }

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

    foreach ($household_rows as $row) {
        $street_matches = pba_auth_values_match(
            isset($row['pb_street_name']) ? $row['pb_street_name'] : '',
            $matched_allowed_street
        );

        $last_name_matches = pba_auth_values_match(
            isset($row['household_admin_last_name']) ? $row['household_admin_last_name'] : '',
            $last_name
        );

        if ($street_matches && $last_name_matches) {
            $matched_household = $row;
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

    $people_rows = pba_supabase_get('Person', array(
        'select'       => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id',
        'household_id' => 'eq.' . $household_id,
        'limit'        => 100,
    ));

    if (is_wp_error($people_rows)) {
        pba_registration_redirect('lookup_failed');
    }

    $existing_person = null;

    if (!empty($people_rows) && is_array($people_rows)) {
        foreach ($people_rows as $person_row) {
            $email_matches = pba_auth_values_match(
                isset($person_row['email_address']) ? $person_row['email_address'] : '',
                $email
            );

            $last_name_matches = pba_auth_values_match(
                isset($person_row['last_name']) ? $person_row['last_name'] : '',
                $last_name
            );

            if ($email_matches && $last_name_matches) {
                $existing_person = $person_row;
                break;
            }
        }
    }

    $household_admin_email_matches = pba_auth_values_match(
        isset($matched_household['household_admin_email_address']) ? $matched_household['household_admin_email_address'] : '',
        $email
    );

    $verification_passed = false;

    if ($existing_person) {
        $verification_passed = true;
    } elseif ($household_admin_email_matches) {
        $verification_passed = true;
    }

    if (!$verification_passed) {
        pba_registration_redirect('no_match');
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
    <html>
    <head>
      <title>Complete Your PBA House Admin Account Setup</title>
    </head>
    <body>
      <h2>Hello {$first_name} {$last_name},</h2>
      <p>Thank you for registering as a <strong>House Admin</strong> for the Priscilla Beach Association (PBA)!</p>
      <p>To complete your account setup, please click the link below to set your password:</p>
      <p><a href='{$link}'>Set My Password</a></p>
      <p>This link will expire in 30 minutes, so please be sure to complete the setup soon.</p>
      <p>If you did not request this registration or need assistance, please contact the PBA Admin.</p>
      <p>Best regards,<br>The Priscilla Beach Association Team</p>
      <p><strong>Contact us:</strong> info@priscillabeachassociation.com</p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    $sent = wp_mail($email, $subject, $message, $headers);

    if (!$sent) {
        pba_registration_redirect('email_send_failed');
    }

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

    if ($email_token === '') {
        pba_registration_redirect('invalid_token');
    }

    $setup_data = get_transient('pba_house_admin_email_verify_' . $email_token);

    if (!is_array($setup_data) || empty($setup_data['email'])) {
        pba_registration_redirect('invalid_token');
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
                'household_id'      => $household_id,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email_address'     => $email,
                'status'            => 'Active',
                'email_verified'    => 1,
                'wp_user_id'        => (string) $user_id,
                'last_modified_at'  => gmdate('c'),
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

    pba_household_redirect('account_created');
}
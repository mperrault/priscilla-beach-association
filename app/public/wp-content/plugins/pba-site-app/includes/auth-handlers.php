<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_nopriv_pba_house_admin_verify', 'pba_handle_house_admin_verify');
add_action('admin_post_pba_house_admin_verify', 'pba_handle_house_admin_verify');

add_action('admin_post_nopriv_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');
add_action('admin_post_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');

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

    if (!in_array($street_name, pba_allowed_streets(), true)) {
        pba_registration_redirect('invalid_street');
    }

    $query_args = array(
        'select'                        => 'household_id,household_admin_email_address,household_admin_first_name,household_admin_last_name,pb_street_number,pb_street_name',
        'household_admin_email_address' => 'eq.' . $email,
        'household_admin_first_name'    => 'eq.' . $first_name,
        'household_admin_last_name'     => 'eq.' . $last_name,
        'pb_street_number'              => 'eq.' . $house_number,
        'pb_street_name'                => 'eq.' . $street_name,
        'limit'                         => 1,
    );

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/Household?' . http_build_query($query_args, '', '&', PHP_QUERY_RFC3986);

    $response = wp_remote_get($url, array(
        'headers' => pba_supabase_headers(),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        pba_registration_redirect('lookup_failed');
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);

    if ($status < 200 || $status >= 300) {
        pba_registration_redirect('lookup_failed');
    }

    $rows = json_decode($body, true);

    if (!is_array($rows) || empty($rows)) {
        pba_registration_redirect('no_match');
    }

    $token = wp_generate_password(64, false, false);

    set_transient(
        'pba_house_admin_email_verify_' . $token,
        array(
            'household_id' => isset($rows[0]['household_id']) ? (string) $rows[0]['household_id'] : '',
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

    $email = $setup_data['email'];

    if (username_exists($email) || email_exists($email)) {
        delete_transient('pba_house_admin_email_verify_' . $email_token);
        pba_registration_redirect('user_exists');
    }

    $user_id = wp_create_user($email, $password, $email);

    if (is_wp_error($user_id)) {
        pba_registration_redirect('create_failed');
    }

    wp_update_user(array(
        'ID'           => $user_id,
        'first_name'   => isset($setup_data['first_name']) ? $setup_data['first_name'] : '',
        'last_name'    => isset($setup_data['last_name']) ? $setup_data['last_name'] : '',
        'display_name' => trim(
            (isset($setup_data['first_name']) ? $setup_data['first_name'] : '') . ' ' .
            (isset($setup_data['last_name']) ? $setup_data['last_name'] : '')
        ),
        'role'         => 'pba_house_admin',
    ));

    if (!empty($setup_data['household_id'])) {
        update_user_meta($user_id, 'pba_household_id', $setup_data['household_id']);
        update_user_meta($user_id, 'pba_is_house_admin', 1);
    }

    $person = pba_create_person_record(array(
        'household_id'   => isset($setup_data['household_id']) ? (int) $setup_data['household_id'] : 0,
        'first_name'     => isset($setup_data['first_name']) ? $setup_data['first_name'] : '',
        'last_name'      => isset($setup_data['last_name']) ? $setup_data['last_name'] : '',
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
    update_user_meta($user_id, 'pba_person_id', $person_id);

    $house_admin_role_id = pba_supabase_find_role_id_by_name('PBAHouseholdAdmin');

    if (!$house_admin_role_id) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        pba_registration_redirect('role_lookup_failed');
    }

    $person_role = pba_create_person_to_role_record($person_id, $house_admin_role_id);

    if (is_wp_error($person_role)) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        pba_registration_redirect('person_role_create_failed');
    }

    delete_transient('pba_house_admin_email_verify_' . $email_token);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    pba_household_redirect('account_created');
}
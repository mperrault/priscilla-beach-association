<?php

add_action('send_headers', function () {
    if (!is_admin()) {
        nocache_headers();
    }
});

add_action('wp_enqueue_scripts', 'pba_theme_scripts');
add_action('after_setup_theme', 'pba_theme_setup');

add_action('admin_post_nopriv_pba_house_admin_verify', 'pba_handle_house_admin_verify');
add_action('admin_post_pba_house_admin_verify', 'pba_handle_house_admin_verify');

add_action('admin_post_nopriv_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');
add_action('admin_post_pba_house_admin_create_user', 'pba_handle_house_admin_create_user');

add_action('admin_post_pba_household_send_invites', 'pba_handle_household_send_invites');

add_action('admin_post_nopriv_pba_accept_member_invite', 'pba_handle_member_invite_accept');
add_action('admin_post_pba_accept_member_invite', 'pba_handle_member_invite_accept');

add_shortcode('pba_household_dashboard', 'pba_render_household_dashboard');
add_shortcode('pba_member_invite_accept', 'pba_render_member_invite_accept');

add_filter('wp_nav_menu_objects', 'pba_filter_nav_menu_items_by_role', 10, 2);

/**
 * -------------------------------------------------------------------------
 * Theme setup
 * -------------------------------------------------------------------------
 */
function pba_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    register_nav_menus(array(
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ));
}

function pba_theme_scripts() {
    wp_enqueue_style('pba-theme-style', get_stylesheet_uri(), array(), '1.0');
}

/**
 * -------------------------------------------------------------------------
 * Helpers
 * -------------------------------------------------------------------------
 */
function pba_allowed_streets() {
    return array(
        'Arlington Rd',
        'Charlemont Rd',
        'Cochituate Rd',
        'Emerson Rd',
        'Farmhurst Rd',
        'John Alden Rd',
        'Morse Rd',
        'Priscilla Beach Rd',
        'Quaker Rd',
        'Robbins Hill Rd',
        'Rocky Hill Rd',
        'Warrendale Rd',
        'Wellington Rd',
    );
}

function pba_supabase_headers($prefer_return_representation = false) {
    $headers = array(
        'apikey'        => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY,
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
    );

    if ($prefer_return_representation) {
        $headers['Prefer'] = 'return=representation';
    }

    return $headers;
}

function pba_registration_redirect($status) {
    wp_safe_redirect(
        add_query_arg(
            'pba_register_status',
            rawurlencode($status),
            home_url('/login/')
        )
    );
    exit;
}

function pba_household_redirect($status = '') {
    $url = home_url('/household/');

    if ($status !== '') {
        $url = add_query_arg('pba_household_status', rawurlencode($status), $url);
    }

    wp_safe_redirect($url);
    exit;
}

function pba_member_invite_redirect($status = '', $invite_token = '') {
    $url = home_url('/member-invite-accept/');

    $args = array();
    if ($status !== '') {
        $args['pba_invite_status'] = $status;
    }
    if ($invite_token !== '') {
        $args['invite_token'] = $invite_token;
    }

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    wp_safe_redirect($url);
    exit;
}

function pba_current_user_has_house_admin_access() {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();

    return in_array('pba_house_admin', (array) $user->roles, true);
}

function pba_supabase_get($table, $query_args = array()) {
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    if (!empty($query_args)) {
        $url .= '?' . http_build_query($query_args, '', '&', PHP_QUERY_RFC3986);
    }

    $response = wp_remote_get($url, array(
        'headers' => pba_supabase_headers(),
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $data   = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('supabase_get_failed', 'Supabase GET failed', array(
            'status' => $status,
            'body'   => $body,
            'table'  => $table,
            'query'  => $query_args,
        ));
    }

    if (!is_array($data)) {
        return new WP_Error('supabase_get_invalid_json', 'Invalid Supabase GET JSON', array(
            'status' => $status,
            'body'   => $body,
            'table'  => $table,
        ));
    }

    return $data;
}

function pba_supabase_insert($table, $payload) {
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    $response = wp_remote_post($url, array(
        'headers' => pba_supabase_headers(true),
        'timeout' => 20,
        'body'    => wp_json_encode(array($payload)),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $data   = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('supabase_insert_failed', 'Supabase INSERT failed', array(
            'status'  => $status,
            'body'    => $body,
            'table'   => $table,
            'payload' => $payload,
        ));
    }

    if (!is_array($data) || empty($data[0]) || !is_array($data[0])) {
        return new WP_Error('supabase_insert_invalid_json', 'Invalid Supabase INSERT JSON', array(
            'status' => $status,
            'body'   => $body,
            'table'  => $table,
        ));
    }

    return $data[0];
}

function pba_supabase_update($table, $payload, $filters = array()) {
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    if (!empty($filters)) {
        $url .= '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    }

    $response = wp_remote_request($url, array(
        'method'  => 'PATCH',
        'headers' => pba_supabase_headers(true),
        'timeout' => 20,
        'body'    => wp_json_encode($payload),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $data   = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('supabase_update_failed', 'Supabase UPDATE failed', array(
            'status'  => $status,
            'body'    => $body,
            'table'   => $table,
            'payload' => $payload,
            'filters' => $filters,
        ));
    }

    if ($body === '') {
        return array();
    }

    if (!is_array($data)) {
        return new WP_Error('supabase_update_invalid_json', 'Invalid Supabase UPDATE JSON', array(
            'status' => $status,
            'body'   => $body,
            'table'  => $table,
        ));
    }

    return $data;
}

function pba_supabase_find_role_id_by_name($role_name) {
    $rows = pba_supabase_get('Role', array(
        'select'    => 'role_id,role_name',
        'role_name' => 'eq.' . $role_name,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]['role_id'])) {
        return false;
    }

    return (int) $rows[0]['role_id'];
}

function pba_create_person_record($args) {
    $payload = array(
        'household_id'         => isset($args['household_id']) ? (int) $args['household_id'] : 0,
        'first_name'           => isset($args['first_name']) ? $args['first_name'] : '',
        'last_name'            => isset($args['last_name']) ? $args['last_name'] : '',
        'email_address'        => isset($args['email']) ? $args['email'] : '',
        'invited_by_person_id' => !empty($args['invited_by_person_id']) ? (int) $args['invited_by_person_id'] : null,
        'email_verified'       => isset($args['email_verified']) ? (int) $args['email_verified'] : 0,
        'wp_user_id'           => array_key_exists('wp_user_id', $args) ? $args['wp_user_id'] : null,
        'status'               => isset($args['status']) ? $args['status'] : 'Pending',
    );

    if (empty($payload['household_id'])) {
        return new WP_Error('missing_household_id', 'household_id is required for Person insert');
    }

    return pba_supabase_insert('Person', $payload);
}

function pba_create_person_to_role_record($person_id, $role_id) {
    return pba_supabase_insert('Person_to_Role', array(
        'person_id' => (int) $person_id,
        'role_id'   => (int) $role_id,
    ));
}

function pba_get_current_house_admin_person_id() {
    return get_user_meta(get_current_user_id(), 'pba_person_id', true);
}

function pba_get_current_household_id() {
    return get_user_meta(get_current_user_id(), 'pba_household_id', true);
}

function pba_get_people_for_household_by_status($household_id, $invited_by_person_id, $status) {
    if (empty($household_id) || empty($invited_by_person_id) || empty($status)) {
        return array();
    }

    $rows = pba_supabase_get('Person', array(
        'select'               => 'person_id,first_name,last_name,email_address,status,created_at,invited_by_person_id,household_id',
        'household_id'         => 'eq.' . (int) $household_id,
        'invited_by_person_id' => 'eq.' . (int) $invited_by_person_id,
        'status'               => 'eq.' . $status,
        'order'                => 'created_at.desc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_render_people_table($rows, $title) {
    ob_start();
    ?>
    <div class="pba-household-section">
        <h3><?php echo esc_html($title); ?></h3>
        <table class="pba-household-table">
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="5">None.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(isset($row['first_name']) ? $row['first_name'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['last_name']) ? $row['last_name'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['email_address']) ? $row['email_address'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['status']) ? $row['status'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['created_at']) ? $row['created_at'] : ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

function pba_send_member_invite_email($person_row, $invite_token) {
    if (empty($person_row['email_address']) || empty($invite_token)) {
        return false;
    }

    $first_name = isset($person_row['first_name']) ? $person_row['first_name'] : '';
    $last_name  = isset($person_row['last_name']) ? $person_row['last_name'] : '';
    $email      = $person_row['email_address'];

    $link = add_query_arg(
        array(
            'invite_token' => rawurlencode($invite_token),
        ),
        home_url('/member-invite-accept/')
    );

    $subject = 'You have been invited to join the Priscilla Beach Association site';

    $message = "
    <html>
    <head>
      <title>PBA Member Invitation</title>
    </head>
    <body>
      <h2>Hello {$first_name} {$last_name},</h2>
      <p>You have been invited by your Household Admin to become a Priscilla Beach Association site member.</p>
      <p>To accept this invitation and set your password, please click the link below:</p>
      <p><a href='{$link}'>Accept My Invitation</a></p>
      <p>This link will expire in 7 days.</p>
      <p>If you were not expecting this invitation, please contact the PBA Admin.</p>
      <p>Best regards,<br>The Priscilla Beach Association Team</p>
      <p><strong>Contact us:</strong> info@priscillabeachassociation.com</p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    return wp_mail($email, $subject, $message, $headers);
}

/**
 * -------------------------------------------------------------------------
 * House Admin verify
 * -------------------------------------------------------------------------
 */
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

/**
 * -------------------------------------------------------------------------
 * House Admin create user
 * -------------------------------------------------------------------------
 */
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

/**
 * -------------------------------------------------------------------------
 * Household send invites
 * -------------------------------------------------------------------------
 */
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

    $household_id      = pba_get_current_household_id();
    $inviter_person_id = pba_get_current_house_admin_person_id();

    if (empty($household_id) || empty($inviter_person_id)) {
        pba_household_redirect('missing_household_context');
    }

    $member_role_id = pba_supabase_find_role_id_by_name('PBAMember');

    if (!$member_role_id) {
        pba_household_redirect('member_role_lookup_failed');
    }

    $process_indexes = array();
    if (isset($_POST['invite_single_row'])) {
        $process_indexes[] = absint($_POST['invite_single_row']);
    } else {
        $row_count = max(count($first_names), count($last_names), count($emails));
        for ($i = 0; $i < $row_count; $i++) {
            $process_indexes[] = $i;
        }
    }

    $created_count = 0;
    $email_fail_count = 0;

    foreach ($process_indexes as $i) {
        $first_name = isset($first_names[$i]) ? sanitize_text_field($first_names[$i]) : '';
        $last_name  = isset($last_names[$i]) ? sanitize_text_field($last_names[$i]) : '';
        $email      = isset($emails[$i]) ? sanitize_email($emails[$i]) : '';

        if ($first_name === '' || $last_name === '' || $email === '' || !is_email($email)) {
            continue;
        }

        $existing = pba_supabase_get('Person', array(
            'select'        => 'person_id,email_address,household_id,status',
            'email_address' => 'eq.' . $email,
            'household_id'  => 'eq.' . (int) $household_id,
            'limit'         => 1,
        ));

        if (!is_wp_error($existing) && !empty($existing)) {
            continue;
        }

        $person = pba_create_person_record(array(
            'household_id'         => (int) $household_id,
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'email'                => $email,
            'status'               => 'Pending',
            'email_verified'       => 0,
            'wp_user_id'           => null,
            'invited_by_person_id' => (int) $inviter_person_id,
        ));

        if (is_wp_error($person) || empty($person['person_id'])) {
            continue;
        }

        $person_id = (int) $person['person_id'];

        $ptr = pba_create_person_to_role_record($person_id, $member_role_id);
        if (is_wp_error($ptr)) {
            continue;
        }

        $invite_token = wp_generate_password(64, false, false);

        set_transient(
            'pba_member_invite_' . $invite_token,
            array(
                'person_id'      => $person_id,
                'household_id'   => (int) $household_id,
                'email'          => $email,
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'role_name'      => 'PBAMember',
            ),
            7 * DAY_IN_SECONDS
        );

        $sent = pba_send_member_invite_email($person, $invite_token);
        if (!$sent) {
            $email_fail_count++;
        }

        $created_count++;
    }

    if ($created_count < 1) {
        pba_household_redirect('no_invites_created');
    }

    if ($email_fail_count > 0) {
        pba_household_redirect('invite_created_email_partial');
    }

    pba_household_redirect('invite_created');
}

/**
 * -------------------------------------------------------------------------
 * Member accepts invite
 * -------------------------------------------------------------------------
 */
function pba_handle_member_invite_accept() {
    if (
        !isset($_POST['pba_member_invite_accept_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_invite_accept_nonce'], 'pba_member_invite_accept_action')
    ) {
        pba_member_invite_redirect('invalid_nonce');
    }

    $invite_token     = isset($_POST['invite_token']) ? sanitize_text_field(wp_unslash($_POST['invite_token'])) : '';
    $password         = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
    $password_verify  = isset($_POST['password_verify']) ? (string) wp_unslash($_POST['password_verify']) : '';

    if ($invite_token === '') {
        pba_member_invite_redirect('invalid_token');
    }

    $invite_data = get_transient('pba_member_invite_' . $invite_token);

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
        'role'         => 'pba_member',
    ));

    update_user_meta($user_id, 'pba_household_id', $household_id);
    update_user_meta($user_id, 'pba_person_id', $person_id);

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'         => 'Active',
            'email_verified' => 1,
            'wp_user_id'     => (string) $user_id,
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

    delete_transient('pba_member_invite_' . $invite_token);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_safe_redirect(
        add_query_arg(
            'pba_register_status',
            'member_account_created',
            home_url('/login/')
        )
    );
    exit;
}

/**
 * -------------------------------------------------------------------------
 * Menu visibility
 * -------------------------------------------------------------------------
 */
function pba_filter_nav_menu_items_by_role($items, $args) {
    $household_url = trailingslashit(home_url('/household/'));
    $can_see = pba_current_user_has_house_admin_access();

    foreach ($items as $index => $item) {
        $item_url = isset($item->url) ? trailingslashit($item->url) : '';
        $title    = isset($item->title) ? trim((string) $item->title) : '';

        if ($item_url === $household_url || strcasecmp($title, 'Household') === 0) {
            if (!$can_see) {
                unset($items[$index]);
            }
        }
    }

    return $items;
}

/**
 * -------------------------------------------------------------------------
 * Household dashboard shortcode
 * -------------------------------------------------------------------------
 */
function pba_render_household_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_user_has_house_admin_access()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $household_id      = pba_get_current_household_id();
    $inviter_person_id = pba_get_current_house_admin_person_id();

    if (empty($household_id) || empty($inviter_person_id)) {
        return '<p>Household context is missing for this account.</p>';
    }

    $pending_rows  = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Pending');
    $accepted_rows = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Active');

    $status = isset($_GET['pba_household_status']) ? sanitize_text_field(wp_unslash($_GET['pba_household_status'])) : '';

    ob_start();
    ?>
    <style>
        .pba-household-wrap { max-width: 1100px; margin: 0 auto; }
        .pba-household-message { padding: 12px 16px; margin: 0 0 20px; border-radius: 6px; background: #eef6ee; }
        .pba-household-message.error { background: #f8e9e9; }
        .pba-household-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .pba-household-table th, .pba-household-table td { border: 1px solid #d7d7d7; padding: 10px; text-align: left; vertical-align: middle; }
        .pba-household-table th { background: #f3f3f3; }
        .pba-household-table input[type="text"],
        .pba-household-table input[type="email"] { width: 100%; box-sizing: border-box; }
        .pba-household-section { margin: 28px 0; }
        .pba-household-actions { margin: 14px 0 22px; display: flex; gap: 10px; flex-wrap: wrap; }
        .pba-household-btn {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            background: #1f4d6b;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }
        .pba-household-btn.secondary { background: #5d6b75; }
        .pba-household-btn.plus { background: #2f7d32; }
        .pba-household-btn:hover { opacity: 0.92; }
        .pba-household-note { color: #555; margin-top: -8px; margin-bottom: 18px; }
        .pba-row-invite-btn { white-space: nowrap; }
    </style>

    <div class="pba-household-wrap">
        <h2>Household</h2>
        <p class="pba-household-note">Invite household members to become site members.</p>

        <?php if ($status === 'account_created') : ?>
            <div class="pba-household-message">Your House Admin account has been created.</div>
        <?php elseif ($status === 'invite_created') : ?>
            <div class="pba-household-message">Invitation record(s) and email(s) created successfully.</div>
        <?php elseif ($status === 'invite_created_email_partial') : ?>
            <div class="pba-household-message error">Invitation record(s) were created, but at least one invitation email could not be sent.</div>
        <?php elseif ($status === 'no_invites_created') : ?>
            <div class="pba-household-message error">No invitation records were created.</div>
        <?php elseif ($status !== '') : ?>
            <div class="pba-household-message error"><?php echo esc_html(str_replace('_', ' ', $status)); ?></div>
        <?php endif; ?>

        <div class="pba-household-section">
            <h3>Invite Household Members</h3>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pba-household-invite-form">
                <?php wp_nonce_field('pba_household_invite_action', 'pba_household_invite_nonce'); ?>
                <input type="hidden" name="action" value="pba_household_send_invites">

                <table class="pba-household-table" id="pba-household-invite-table">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email Address</th>
                            <th>Invite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="invite_first_name[]" required></td>
                            <td><input type="text" name="invite_last_name[]" required></td>
                            <td><input type="email" name="invite_email[]" required></td>
                            <td><button type="submit" class="pba-household-btn pba-row-invite-btn" name="invite_single_row" value="0">Invite</button></td>
                        </tr>
                    </tbody>
                </table>

                <div class="pba-household-actions">
                    <button type="button" class="pba-household-btn plus" id="pba-household-add-row">+ Add Row</button>
                    <button type="submit" class="pba-household-btn secondary">Invite All Rows</button>
                </div>
            </form>
        </div>

        <?php echo pba_render_people_table($accepted_rows, 'Accepted Invitations'); ?>
        <?php echo pba_render_people_table($pending_rows, 'Pending Invitations'); ?>
    </div>

    <script>
        (function () {
            var addRowBtn = document.getElementById('pba-household-add-row');
            var tableBody = document.querySelector('#pba-household-invite-table tbody');

            if (!addRowBtn || !tableBody) {
                return;
            }

            function updateRowIndexes() {
                var rows = tableBody.querySelectorAll('tr');
                rows.forEach(function (row, index) {
                    var btn = row.querySelector('button[name="invite_single_row"]');
                    if (btn) {
                        btn.value = String(index);
                    }
                });
            }

            addRowBtn.addEventListener('click', function () {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" name="invite_first_name[]" required></td>' +
                    '<td><input type="text" name="invite_last_name[]" required></td>' +
                    '<td><input type="email" name="invite_email[]" required></td>' +
                    '<td><button type="submit" class="pba-household-btn pba-row-invite-btn" name="invite_single_row" value="0">Invite</button></td>';
                tableBody.appendChild(tr);
                updateRowIndexes();
            });

            updateRowIndexes();
        })();
    </script>
    <?php

    return ob_get_clean();
}

/**
 * -------------------------------------------------------------------------
 * Member invite acceptance shortcode
 * -------------------------------------------------------------------------
 */
function pba_render_member_invite_accept() {
    $invite_token = isset($_GET['invite_token']) ? sanitize_text_field(wp_unslash($_GET['invite_token'])) : '';
    $status       = isset($_GET['pba_invite_status']) ? sanitize_text_field(wp_unslash($_GET['pba_invite_status'])) : '';

    if ($invite_token === '') {
        return '<p>Invitation token is missing.</p>';
    }

    $invite_data = get_transient('pba_member_invite_' . $invite_token);

    if (!is_array($invite_data) || empty($invite_data['person_id']) || empty($invite_data['email'])) {
        return '<p>This invitation is invalid or has expired.</p>';
    }

    $first_name = isset($invite_data['first_name']) ? $invite_data['first_name'] : '';
    $last_name  = isset($invite_data['last_name']) ? $invite_data['last_name'] : '';
    $email      = isset($invite_data['email']) ? $invite_data['email'] : '';

    ob_start();
    ?>
    <style>
        .pba-invite-wrap { max-width: 640px; margin: 0 auto; }
        .pba-invite-box { border: 1px solid #ddd; padding: 24px; border-radius: 8px; background: #fff; }
        .pba-invite-field { margin-bottom: 16px; }
        .pba-invite-field label { display: block; margin-bottom: 6px; font-weight: 600; }
        .pba-invite-field input { width: 100%; box-sizing: border-box; padding: 10px; }
        .pba-invite-msg { padding: 12px 16px; margin: 0 0 18px; border-radius: 6px; background: #eef6ee; }
        .pba-invite-msg.error { background: #f8e9e9; }
        .pba-invite-btn {
            display: inline-block;
            padding: 10px 16px;
            border: 0;
            border-radius: 6px;
            background: #1f4d6b;
            color: #fff;
            cursor: pointer;
        }
    </style>

    <div class="pba-invite-wrap">
        <div class="pba-invite-box">
            <h2>Accept Your PBA Invitation</h2>
            <p>Hello <?php echo esc_html(trim($first_name . ' ' . $last_name)); ?>.</p>
            <p>Your email address is <strong><?php echo esc_html($email); ?></strong>.</p>

            <?php if ($status === 'missing_password') : ?>
                <div class="pba-invite-msg error">Please enter both password fields.</div>
            <?php elseif ($status === 'password_mismatch') : ?>
                <div class="pba-invite-msg error">The password fields do not match.</div>
            <?php elseif ($status === 'password_too_short') : ?>
                <div class="pba-invite-msg error">Password must be at least 8 characters long.</div>
            <?php elseif ($status === 'user_exists') : ?>
                <div class="pba-invite-msg error">An account already exists for that email address.</div>
            <?php elseif ($status === 'already_accepted') : ?>
                <div class="pba-invite-msg error">This invitation has already been accepted.</div>
            <?php elseif ($status === 'person_update_failed') : ?>
                <div class="pba-invite-msg error">We could not finish setting up your membership. Please contact the PBA Admin.</div>
            <?php elseif ($status !== '') : ?>
                <div class="pba-invite-msg error"><?php echo esc_html(str_replace('_', ' ', $status)); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pba_member_invite_accept_action', 'pba_member_invite_accept_nonce'); ?>
                <input type="hidden" name="action" value="pba_accept_member_invite">
                <input type="hidden" name="invite_token" value="<?php echo esc_attr($invite_token); ?>">

                <div class="pba-invite-field">
                    <label for="pba-invite-password">Password</label>
                    <input id="pba-invite-password" type="password" name="password" required>
                </div>

                <div class="pba-invite-field">
                    <label for="pba-invite-password-verify">Confirm Password</label>
                    <input id="pba-invite-password-verify" type="password" name="password_verify" required>
                </div>

                <button type="submit" class="pba-invite-btn">Create My Member Account</button>
            </form>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

?>
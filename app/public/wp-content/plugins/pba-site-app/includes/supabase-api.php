<?php

if (!defined('ABSPATH')) {
    exit;
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

function pba_supabase_delete($table, $filters = array()) {
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    if (!empty($filters)) {
        $url .= '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    }

    $response = wp_remote_request($url, array(
        'method'  => 'DELETE',
        'headers' => pba_supabase_headers(true),
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('supabase_delete_failed', 'Supabase DELETE failed', array(
            'status'  => $status,
            'body'    => $body,
            'table'   => $table,
            'filters' => $filters,
        ));
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : array();
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
        'last_modified_at'     => gmdate('c'),
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

function pba_get_people_for_household_by_status($household_id, $invited_by_person_id, $status) {
    if (empty($household_id) || empty($invited_by_person_id) || empty($status)) {
        return array();
    }

    $rows = pba_supabase_get('Person', array(
        'select'               => 'person_id,first_name,last_name,email_address,status,last_modified_at,invited_by_person_id,household_id,wp_user_id',
        'household_id'         => 'eq.' . (int) $household_id,
        'invited_by_person_id' => 'eq.' . (int) $invited_by_person_id,
        'status'               => 'eq.' . $status,
        'order'                => 'person_id.desc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
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

    $subject = 'Welcome to the Priscilla Beach Association (PBA)!';

    $message = "
    <html>
    <head>
      <title>Complete Your PBA Member Account Setup</title>
    </head>
    <body>
      <h2>Hello {$first_name} {$last_name},</h2>
      <p>You have been invited by your Household Admin to become a site member of the Priscilla Beach Association (PBA).</p>
      <p>To complete your account setup, please click the link below to create your password:</p>
      <p><a href='{$link}'>Set My Password</a></p>
      <p>This link will expire in 7 days, so please complete your setup soon.</p>
      <p>If you did not expect this invitation or need assistance, please contact the PBA Admin.</p>
      <p>Best regards,<br>The Priscilla Beach Association Team</p>
      <p><strong>Contact us:</strong> info@priscillabeachassociation.com</p>
    </body>
    </html>
    ";

    $headers = array('Content-Type: text/html; charset=UTF-8');

    return wp_mail($email, $subject, $message, $headers);
}
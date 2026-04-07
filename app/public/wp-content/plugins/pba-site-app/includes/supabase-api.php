<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_supabase_headers($prefer_return_representation = false, $prefer_tokens = array()) {
    $headers = array(
        'apikey'        => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY,
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
    );

    $prefer_values = array();

    if ($prefer_return_representation) {
        $prefer_values[] = 'return=representation';
    }

    foreach ((array) $prefer_tokens as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $prefer_values[] = $token;
        }
    }

    $prefer_values = array_values(array_unique($prefer_values));

    if (!empty($prefer_values)) {
        $headers['Prefer'] = implode(',', $prefer_values);
    }

    return $headers;
}

function pba_supabase_extract_total_count_from_response($response) {
    $content_range = wp_remote_retrieve_header($response, 'content-range');

    if (!is_string($content_range) || $content_range === '') {
        return null;
    }

    if (preg_match('#/(\d+)$#', $content_range, $matches)) {
        return (int) $matches[1];
    }

    return null;
}

function pba_supabase_get($table, $query_args = array(), $options = array()) {
    static $request_cache = array();

    $defaults = array(
        'return_meta' => false,
        'count'       => false,   // false | 'exact' | 'planned' | 'estimated'
        'timeout'     => 20,
    );

    $options = wp_parse_args($options, $defaults);

    $cache_key = md5($table . '|' . wp_json_encode($query_args) . '|' . wp_json_encode($options));
    if (array_key_exists($cache_key, $request_cache)) {
        return $request_cache[$cache_key];
    }

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    if (!empty($query_args)) {
        $url .= '?' . http_build_query($query_args, '', '&', PHP_QUERY_RFC3986);
    }

    $prefer_tokens = array();
    if (!empty($options['count'])) {
        $prefer_tokens[] = 'count=' . $options['count'];
    }

    $response = wp_remote_get($url, array(
        'headers' => pba_supabase_headers(false, $prefer_tokens),
        'timeout' => (int) $options['timeout'],
    ));

    if (is_wp_error($response)) {
        $request_cache[$cache_key] = $response;
        return $request_cache[$cache_key];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = wp_remote_retrieve_body($response);
    $data   = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        $request_cache[$cache_key] = new WP_Error('supabase_get_failed', 'Supabase GET failed', array(
            'status'  => $status,
            'body'    => $body,
            'table'   => $table,
            'query'   => $query_args,
            'options' => $options,
        ));
        return $request_cache[$cache_key];
    }

    if (!is_array($data)) {
        $request_cache[$cache_key] = new WP_Error('supabase_get_invalid_json', 'Invalid Supabase GET JSON', array(
            'status'  => $status,
            'body'    => $body,
            'table'   => $table,
            'options' => $options,
        ));
        return $request_cache[$cache_key];
    }

    if (!empty($options['return_meta'])) {
        $request_cache[$cache_key] = array(
            'rows'    => $data,
            'count'   => pba_supabase_extract_total_count_from_response($response),
            'status'  => $status,
            'headers' => wp_remote_retrieve_headers($response),
        );
        return $request_cache[$cache_key];
    }

    $request_cache[$cache_key] = $data;
    return $request_cache[$cache_key];
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

function pba_get_active_supabase_role_names_for_person($person_id) {
    static $role_cache = array();

    $person_id = (int) $person_id;

    if ($person_id < 1) {
        return array();
    }

    if (isset($role_cache[$person_id])) {
        return $role_cache[$person_id];
    }

    $rows = pba_supabase_get('Person_to_Role', array(
        'select'    => 'role_id,is_active',
        'person_id' => 'eq.' . $person_id,
        'is_active' => 'eq.true',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        $role_cache[$person_id] = array();
        return $role_cache[$person_id];
    }

    $role_ids = array();
    foreach ($rows as $row) {
        $role_id = isset($row['role_id']) ? (int) $row['role_id'] : 0;
        if ($role_id > 0) {
            $role_ids[] = $role_id;
        }
    }

    $role_ids = array_values(array_unique($role_ids));
    if (empty($role_ids)) {
        $role_cache[$person_id] = array();
        return $role_cache[$person_id];
    }

    $role_rows = pba_supabase_get('Role', array(
        'select'  => 'role_id,role_name',
        'role_id' => 'in.(' . implode(',', $role_ids) . ')',
        'limit'   => count($role_ids),
    ));

    if (is_wp_error($role_rows) || empty($role_rows)) {
        $role_cache[$person_id] = array();
        return $role_cache[$person_id];
    }

    $role_names = array();
    foreach ($role_rows as $role_row) {
        if (!empty($role_row['role_name'])) {
            $role_names[] = (string) $role_row['role_name'];
        }
    }

    sort($role_names);
    $role_cache[$person_id] = array_values(array_unique($role_names));
    return $role_cache[$person_id];
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
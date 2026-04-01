<?php

if (!defined('ABSPATH')) {
    exit;
}

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

function pba_get_current_house_admin_person_id() {
    return get_user_meta(get_current_user_id(), 'pba_person_id', true);
}

function pba_get_current_household_id() {
    return get_user_meta(get_current_user_id(), 'pba_household_id', true);
}

function pba_format_datetime_display($value) {
    if (empty($value)) {
        return '';
    }

    try {
        $dt = new DateTime($value);
        $tz = new DateTimeZone('America/New_York');
        $dt->setTimezone($tz);
        return $dt->format('m/d/Y h:i A T');
    } catch (Exception $e) {
        return (string) $value;
    }
}

function pba_get_household_label($household_id) {
    $household_id = (int) $household_id;

    if ($household_id < 1) {
        return 'another household';
    }

    $rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,pb_street_number,pb_street_name',
        'household_id' => 'eq.' . $household_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return 'household #' . $household_id;
    }

    $row = $rows[0];
    $street_number = isset($row['pb_street_number']) ? trim((string) $row['pb_street_number']) : '';
    $street_name   = isset($row['pb_street_name']) ? trim((string) $row['pb_street_name']) : '';

    $label = trim($street_number . ' ' . $street_name);

    if ($label === '') {
        return 'household #' . $household_id;
    }

    return 'household ' . $label;
}

function pba_get_person_display_name($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1) {
        return '';
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,first_name,last_name',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return '';
    }

    $row = $rows[0];
    $first_name = isset($row['first_name']) ? trim((string) $row['first_name']) : '';
    $last_name  = isset($row['last_name']) ? trim((string) $row['last_name']) : '';

    return trim($first_name . ' ' . $last_name);
}

function pba_member_invite_token_key($invite_token) {
    return 'pba_member_invite_' . $invite_token;
}

function pba_member_invite_person_key($person_id) {
    return 'pba_member_invite_person_' . (int) $person_id;
}

function pba_store_member_invite_token($person_id, $household_id, $email, $first_name, $last_name, $role_name = 'PBAMember') {
    $person_id = (int) $person_id;
    $household_id = (int) $household_id;

    $invite_token = wp_generate_password(64, false, false);
    $expires_at_gmt = time() + (7 * DAY_IN_SECONDS);

    $payload = array(
        'person_id'      => $person_id,
        'household_id'   => $household_id,
        'email'          => $email,
        'first_name'     => $first_name,
        'last_name'      => $last_name,
        'role_name'      => $role_name,
        'invite_token'   => $invite_token,
        'expires_at_gmt' => $expires_at_gmt,
    );

    set_transient(
        pba_member_invite_token_key($invite_token),
        $payload,
        7 * DAY_IN_SECONDS
    );

    set_transient(
        pba_member_invite_person_key($person_id),
        $payload,
        7 * DAY_IN_SECONDS
    );

    return $invite_token;
}

function pba_get_member_invite_data_by_person($person_id) {
    return get_transient(pba_member_invite_person_key($person_id));
}

function pba_delete_member_invite_transients($person_id, $invite_token = '') {
    delete_transient(pba_member_invite_person_key((int) $person_id));

    if ($invite_token !== '') {
        delete_transient(pba_member_invite_token_key($invite_token));
        return;
    }

    $person_data = get_transient(pba_member_invite_person_key((int) $person_id));
    if (is_array($person_data) && !empty($person_data['invite_token'])) {
        delete_transient(pba_member_invite_token_key($person_data['invite_token']));
    }
}

function pba_update_pending_household_invites_to_expired($household_id, $invited_by_person_id) {
    $household_id = (int) $household_id;
    $invited_by_person_id = (int) $invited_by_person_id;

    if ($household_id < 1 || $invited_by_person_id < 1) {
        return;
    }

    $pending_rows = pba_supabase_get('Person', array(
        'select'               => 'person_id,status,household_id,invited_by_person_id',
        'household_id'         => 'eq.' . $household_id,
        'invited_by_person_id' => 'eq.' . $invited_by_person_id,
        'status'               => 'eq.Pending',
        'order'                => 'created_at.desc',
    ));

    if (is_wp_error($pending_rows) || empty($pending_rows)) {
        return;
    }

    foreach ($pending_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        if ($person_id < 1) {
            continue;
        }

        $invite_data = pba_get_member_invite_data_by_person($person_id);
        $is_expired = false;

        if (!is_array($invite_data) || empty($invite_data['expires_at_gmt'])) {
            $is_expired = true;
        } elseif ((int) $invite_data['expires_at_gmt'] < time()) {
            $is_expired = true;
        }

        if ($is_expired) {
            pba_supabase_update(
                'Person',
                array(
                    'status' => 'Expired',
                ),
                array(
                    'person_id' => 'eq.' . $person_id,
                )
            );

            if (is_array($invite_data) && !empty($invite_data['invite_token'])) {
                pba_delete_member_invite_transients($person_id, $invite_data['invite_token']);
            } else {
                delete_transient(pba_member_invite_person_key($person_id));
            }
        }
    }
}
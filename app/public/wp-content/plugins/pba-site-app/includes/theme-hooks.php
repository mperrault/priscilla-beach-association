<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('send_headers', 'pba_send_nocache_headers');
add_filter('show_admin_bar', 'pba_maybe_hide_admin_bar');
add_filter('login_redirect', 'pba_role_based_login_redirect', 10, 3);
add_filter('wp_authenticate_user', 'pba_block_disabled_person_login', 10, 2);
add_action('wp_login', 'pba_sync_wp_role_on_login', 10, 2);

function pba_send_nocache_headers() {
    if (!is_admin()) {
        nocache_headers();
    }
}

function pba_maybe_hide_admin_bar($show) {
    if (!current_user_can('manage_options')) {
        return false;
    }

    return $show;
}

function pba_role_based_login_redirect($redirect_to, $requested_redirect_to, $user) {
    if (!($user instanceof WP_User)) {
        return $redirect_to;
    }

    $person_rows = pba_supabase_get('Person', array(
        'select'     => 'person_id,wp_user_id',
        'wp_user_id' => 'eq.' . (string) $user->ID,
        'limit'      => 1,
    ));

    if (is_wp_error($person_rows) || empty($person_rows[0]['person_id'])) {
        return home_url('/member-home/');
    }

    $person_id = (int) $person_rows[0]['person_id'];
    $role_names = pba_get_active_supabase_role_names_for_person($person_id);

    if (in_array('PBAAdmin', $role_names, true) || in_array('PBAHouseholdAdmin', $role_names, true)) {
        return home_url('/household/');
    }

    if (in_array('PBABoardMember', $role_names, true)) {
        return home_url('/board-documents/');
    }

    if (in_array('PBACommitteeMember', $role_names, true)) {
        return home_url('/committee-documents/');
    }

    return home_url('/member-home/');
}

function pba_block_disabled_person_login($user, $password) {
    if (!($user instanceof WP_User)) {
        return $user;
    }

    $person_rows = pba_supabase_get('Person', array(
        'select'        => 'person_id,status,wp_user_id,email_address',
        'email_address' => 'eq.' . $user->user_email,
        'limit'         => 1,
    ));

    if (is_wp_error($person_rows) || empty($person_rows[0])) {
        return $user;
    }

    $person = $person_rows[0];
    $status = isset($person['status']) ? (string) $person['status'] : '';

    if ($status === 'Disabled') {
        return new WP_Error(
            'pba_account_disabled',
            'Your membership has been disabled. Please contact the PBA Admin.'
        );
    }

    return $user;
}

function pba_sync_wp_role_on_login($user_login, $user) {
    if (!($user instanceof WP_User)) {
        return;
    }

    if (!function_exists('pba_sync_wp_role_for_person')) {
        return;
    }

    $person_rows = pba_supabase_get('Person', array(
        'select'     => 'person_id,wp_user_id,email_address',
        'wp_user_id' => 'eq.' . (string) $user->ID,
        'limit'      => 1,
    ));

    if (is_wp_error($person_rows) || empty($person_rows[0]['person_id'])) {
        return;
    }

    $person_id = (int) $person_rows[0]['person_id'];

    pba_sync_wp_role_for_person((int) $user->ID, $person_id);
}
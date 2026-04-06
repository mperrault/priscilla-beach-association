<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_login', 'pba_sync_user_links_on_login', 10, 2);

function pba_sync_user_links_on_login($user_login, $user) {
    if (!$user || empty($user->ID)) {
        return;
    }

    pba_sync_wp_user_meta_from_supabase_person((int) $user->ID);
}

function pba_sync_wp_user_meta_from_supabase_person($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return false;
    }

    $user = get_userdata($wp_user_id);

    if (!$user || empty($user->ID)) {
        return false;
    }

    $person = pba_find_supabase_person_for_wp_user($user);

    if (!$person || empty($person['person_id'])) {
        return false;
    }

    $person_id = isset($person['person_id']) ? (int) $person['person_id'] : 0;
    $household_id = isset($person['household_id']) ? (int) $person['household_id'] : 0;

    if ($person_id < 1) {
        return false;
    }

    update_user_meta($wp_user_id, 'pba_person_id', $person_id);

    if ($household_id > 0) {
        update_user_meta($wp_user_id, 'pba_household_id', $household_id);
    } else {
        delete_user_meta($wp_user_id, 'pba_household_id');
    }

    $person_wp_user_id = isset($person['wp_user_id']) && $person['wp_user_id'] !== null && $person['wp_user_id'] !== ''
        ? (int) $person['wp_user_id']
        : 0;

    if ($person_wp_user_id !== $wp_user_id) {
        pba_supabase_update('Person', array(
            'wp_user_id'       => $wp_user_id,
            'last_modified_at' => gmdate('c'),
        ), array(
            'person_id' => 'eq.' . $person_id,
        ));
    }

    return array(
        'wp_user_id'    => $wp_user_id,
        'person_id'     => $person_id,
        'household_id'  => $household_id,
    );
}

function pba_find_supabase_person_for_wp_user($user) {
    if (!$user || empty($user->ID)) {
        return false;
    }

    $wp_user_id = (int) $user->ID;
    $email = isset($user->user_email) ? strtolower(trim((string) $user->user_email)) : '';

    $person = pba_find_supabase_person_by_wp_user_id($wp_user_id);

    if ($person) {
        return $person;
    }

    if ($email !== '') {
        $person = pba_find_supabase_person_by_email($email);

        if ($person) {
            return $person;
        }
    }

    return false;
}

function pba_find_supabase_person_by_wp_user_id($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return false;
    }

    $rows = pba_supabase_get('Person', array(
        'select'     => 'person_id,household_id,email_address,wp_user_id,status,last_modified_at',
        'wp_user_id' => 'eq.' . $wp_user_id,
        'limit'      => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return false;
    }

    return $rows[0];
}

function pba_find_supabase_person_by_email($email) {
    $email = strtolower(trim((string) $email));

    if ($email === '') {
        return false;
    }

    $rows = pba_supabase_get('Person', array(
        'select'        => 'person_id,household_id,email_address,wp_user_id,status,last_modified_at',
        'email_address' => 'eq.' . $email,
        'limit'         => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return false;
    }

    return $rows[0];
}
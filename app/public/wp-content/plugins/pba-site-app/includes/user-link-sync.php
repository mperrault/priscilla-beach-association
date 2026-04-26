<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_login', 'pba_sync_user_links_on_login', 10, 2);

/**
 * Password reset happens before login.
 *
 * If a PBAAdmin corrected Person.email_address but the linked WordPress
 * wp_users.user_email is still stale, WordPress cannot find the account
 * by the corrected email.
 *
 * This filter lets us resolve the user through Supabase Person.email_address,
 * sync wp_users.user_email, and then allow the reset email to be sent.
 */
add_filter('lostpassword_user_data', 'pba_filter_lostpassword_user_data_from_supabase_person', 10, 2);

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

function pba_filter_lostpassword_user_data_from_supabase_person($user_data, $errors) {
    /*
     * If WordPress already found the user, keep normal WordPress behavior.
     */
    if ($user_data instanceof WP_User && !empty($user_data->ID)) {
        return $user_data;
    }

    $submitted_login = '';

    if (!empty($_POST['user_login']) && is_string($_POST['user_login'])) {
        $submitted_login = sanitize_text_field(wp_unslash($_POST['user_login']));
    }

    $submitted_login = strtolower(trim($submitted_login));

    if ($submitted_login === '' || !is_email($submitted_login)) {
        return $user_data;
    }

    /*
     * Look up the corrected email in Supabase.
     */
    $person = pba_find_supabase_person_by_email($submitted_login);

    if (!$person || empty($person['person_id'])) {
        return $user_data;
    }

    /*
     * Find the linked WordPress user by Person.wp_user_id first,
     * then fall back to user meta pba_person_id.
     */
    $wp_user = pba_find_wp_user_for_supabase_person($person);

    if (!$wp_user || empty($wp_user->ID)) {
        return $user_data;
    }

    /*
     * Sync the stale WordPress email to the corrected Person.email_address.
     * WordPress sends the reset email to $user_data->user_email, so this needs
     * to be corrected before retrieve_password() continues.
     */
    $sync_result = pba_sync_wordpress_user_email_to_person_email($wp_user, $person);

    if (is_wp_error($sync_result)) {
        return $user_data;
    }

    $fresh_user = get_userdata((int) $wp_user->ID);

    if (!$fresh_user || empty($fresh_user->ID)) {
        return $user_data;
    }

    /*
     * WordPress may have already added invalid_email before this filter ran.
     * Remove it now that we resolved the user through the PBA Person table.
     */
    pba_remove_password_reset_error($errors, 'invalid_email');
    pba_remove_password_reset_error($errors, 'invalidcombo');

    return $fresh_user;
}

function pba_find_wp_user_for_supabase_person($person) {
    if (empty($person) || !is_array($person)) {
        return false;
    }

    $person_id = isset($person['person_id']) ? (int) $person['person_id'] : 0;
    $wp_user_id = isset($person['wp_user_id']) && $person['wp_user_id'] !== null && $person['wp_user_id'] !== ''
        ? (int) $person['wp_user_id']
        : 0;

    if ($wp_user_id > 0) {
        $user = get_userdata($wp_user_id);

        if ($user && !empty($user->ID)) {
            return $user;
        }
    }

    if ($person_id > 0) {
        $users = get_users(array(
            'meta_key'   => 'pba_person_id',
            'meta_value' => (string) $person_id,
            'number'     => 1,
            'fields'     => 'all',
        ));

        if (!empty($users[0]) && $users[0] instanceof WP_User) {
            return $users[0];
        }
    }

    return false;
}

function pba_sync_wordpress_user_email_to_person_email($wp_user, $person) {
    if (!$wp_user || empty($wp_user->ID) || empty($person) || !is_array($person)) {
        return new WP_Error('pba_invalid_user_sync_data', 'Invalid user sync data.');
    }

    $wp_user_id = (int) $wp_user->ID;
    $person_id = isset($person['person_id']) ? (int) $person['person_id'] : 0;
    $person_email = isset($person['email_address']) ? sanitize_email((string) $person['email_address']) : '';
    $household_id = isset($person['household_id']) ? (int) $person['household_id'] : 0;

    if ($wp_user_id < 1 || $person_id < 1 || $person_email === '' || !is_email($person_email)) {
        return new WP_Error('pba_invalid_user_sync_data', 'Invalid user sync data.');
    }

    $email_owner = get_user_by('email', $person_email);

    if ($email_owner && (int) $email_owner->ID !== $wp_user_id) {
        return new WP_Error(
            'pba_email_in_use',
            'That email address is already associated with another WordPress user.'
        );
    }

    if (strtolower((string) $wp_user->user_email) !== strtolower((string) $person_email)) {
        $updated = wp_update_user(array(
            'ID'         => $wp_user_id,
            'user_email' => $person_email,
        ));

        if (is_wp_error($updated)) {
            return $updated;
        }
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

    return true;
}

function pba_remove_password_reset_error($errors, $code) {
    if (!is_wp_error($errors) || $code === '') {
        return;
    }

    if (method_exists($errors, 'remove')) {
        $errors->remove($code);
        return;
    }

    /*
     * Defensive fallback for older WP_Error behavior.
     */
    if (isset($errors->errors[$code])) {
        unset($errors->errors[$code]);
    }

    if (isset($errors->error_data[$code])) {
        unset($errors->error_data[$code]);
    }
}
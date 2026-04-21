<?php
/**
 * Plugin Name: PBA Custom Roles
 * Description: Registers PBA application roles and capabilities and syncs WordPress roles from Supabase.
 * Version: 1.1.2
 * Author: PBA
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'pba_register_or_update_roles');
add_action('init', 'pba_register_or_update_roles_if_needed');
add_action('init', 'pba_maybe_sync_current_user_wp_roles_from_supabase', 20);
add_action('wp_login', 'pba_sync_wp_roles_on_login', 10, 2);

function pba_register_or_update_roles_if_needed() {
    pba_register_or_update_roles();
}

function pba_get_role_definitions() {
    return array(
        'pba_member' => array(
            'label'       => 'PBA Member',
            'description' => 'Standard member access to member-only content and tools.',
        ),
        'pba_house_admin' => array(
            'label'       => 'PBA Household Admin',
            'description' => 'Member access plus household invitation and household member management.',
        ),
        'pba_board_member' => array(
            'label'       => 'PBA Board Member',
            'description' => 'Member access plus board document access.',
        ),
        'pba_committee_member' => array(
            'label'       => 'PBA Committee Member',
            'description' => 'Member access plus committee document access.',
        ),
        'pba_admin' => array(
            'label'       => 'PBA Admin',
            'description' => 'Application administrator with association-wide management privileges.',
        ),
    );
}

function pba_get_role_capability_map() {
    $member_caps = array(
        'read'                         => true,
        'pba_view_member_home'         => true,
        'pba_edit_own_profile'         => true,
        'pba_view_news'                => true,
        'pba_view_announcements'       => true,
        'pba_view_calendar'            => true,
        'pba_view_directory'           => true,
        'pba_view_meeting_info'        => true,
        'pba_view_governing_documents' => true,
    );

    return array(
        'pba_member' => $member_caps,

        'pba_house_admin' => array_merge($member_caps, array(
            'pba_view_household_page'          => true,
            'pba_invite_household_members'     => true,
            'pba_manage_household_invitations' => true,
            'pba_disable_household_member'     => true,
            'pba_resend_household_invitation'  => true,
            'pba_cancel_household_invitation'  => true,
        )),

        'pba_board_member' => array_merge($member_caps, array(
            'pba_view_board_docs'   => true,
            'pba_manage_board_docs' => true,
            'pba_upload_board_docs' => true,
        )),

        'pba_committee_member' => array_merge($member_caps, array(
            'pba_view_committee_docs'   => true,
            'pba_manage_committee_docs' => true,
            'pba_upload_committee_docs' => true,
        )),

        'pba_admin' => array_merge($member_caps, array(
            'pba_view_household_page'          => true,
            'pba_invite_household_members'     => true,
            'pba_manage_household_invitations' => true,
            'pba_disable_household_member'     => true,
            'pba_resend_household_invitation'  => true,
            'pba_cancel_household_invitation'  => true,

            'pba_view_board_docs'              => true,
            'pba_manage_board_docs'            => true,
            'pba_upload_board_docs'            => true,

            'pba_view_committee_docs'          => true,
            'pba_manage_committee_docs'        => true,
            'pba_upload_committee_docs'        => true,

            'pba_manage_calendar'              => true,
            'pba_manage_directory'             => true,
            'pba_manage_meeting_info'          => true,
            'pba_manage_agendas'               => true,
            'pba_manage_minutes'               => true,

            'pba_view_all_households'          => true,
            'pba_manage_all_households'        => true,
            'pba_send_association_invitations' => true,

            'pba_view_access_rights'           => true,
            'pba_manage_roles'                 => true,
            'pba_manage_news'                  => true,

            'pba_view_governing_documents'     => true,
            'pba_manage_governing_documents'   => true,
        )),
    );
}

function pba_register_or_update_roles() {
    $role_defs = pba_get_role_definitions();
    $role_caps = pba_get_role_capability_map();

    foreach ($role_defs as $role_slug => $def) {
        $label = isset($def['label']) ? $def['label'] : $role_slug;
        $caps  = isset($role_caps[$role_slug]) ? $role_caps[$role_slug] : array();

        $role = get_role($role_slug);

        if (!$role) {
            add_role($role_slug, $label, $caps);
            $role = get_role($role_slug);
        }

        if (!$role) {
            continue;
        }

        foreach ($caps as $cap => $grant) {
            if ($grant) {
                $role->add_cap($cap, true);
            } else {
                $role->remove_cap($cap);
            }
        }

        $existing_caps = isset($role->capabilities) && is_array($role->capabilities)
            ? $role->capabilities
            : array();

        foreach ($existing_caps as $cap => $granted) {
            if ($cap === 'read') {
                continue;
            }

            if (!array_key_exists($cap, $caps)) {
                $role->remove_cap($cap);
            }
        }
    }
}

function pba_get_managed_wp_role_slugs() {
    return array(
        'subscriber',
        'pba_member',
        'pba_house_admin',
        'pba_board_member',
        'pba_committee_member',
        'pba_admin',
    );
}

function pba_get_application_wp_role_slugs() {
    return array(
        'pba_member',
        'pba_house_admin',
        'pba_board_member',
        'pba_committee_member',
        'pba_admin',
    );
}

function pba_normalize_supabase_role_name($role_name) {
    $role_name = strtolower(trim((string) $role_name));
    $role_name = preg_replace('/\s+/', '', $role_name);

    return $role_name;
}

function pba_get_supabase_role_name_to_wp_role_map() {
    return array(
        'pbamember'          => 'pba_member',
        'pbahouseholdadmin'  => 'pba_house_admin',
        'pbaboardmember'     => 'pba_board_member',
        'pbacommitteemember' => 'pba_committee_member',
        'pbaadmin'           => 'pba_admin',
    );
}

function pba_get_person_select_fields() {
    return 'person_id,wp_user_id,first_name,last_name,email_address,status,email_verified,household_id,last_modified_at,directory_visibility_level';
}

function pba_get_person_record_by_id($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1 || !function_exists('pba_supabase_get')) {
        return null;
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => pba_get_person_select_fields(),
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_get_person_record_by_wp_user_id($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1 || !function_exists('pba_supabase_get')) {
        return null;
    }

    $rows = pba_supabase_get('Person', array(
        'select'     => pba_get_person_select_fields(),
        'wp_user_id' => 'eq.' . $wp_user_id,
        'limit'      => 1,
    ));

    if (!is_wp_error($rows) && !empty($rows[0]) && is_array($rows[0])) {
        return $rows[0];
    }

    $user = get_user_by('id', $wp_user_id);
    if (!$user || empty($user->user_email)) {
        return null;
    }

    $email = strtolower(trim((string) $user->user_email));

    $rows = pba_supabase_get('Person', array(
        'select'        => pba_get_person_select_fields(),
        'email_address' => 'eq.' . $email,
        'limit'         => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_person_has_any_active_committee_assignment($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1 || !function_exists('pba_supabase_get')) {
        return false;
    }

    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_to_committee_id',
        'person_id' => 'eq.' . $person_id,
        'is_active' => 'eq.true',
        'limit'     => 1,
    ));

    return !is_wp_error($rows) && !empty($rows);
}

function pba_get_expected_wp_roles_for_person($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1) {
        return array();
    }

    $person = pba_get_person_record_by_id($person_id);
    if (!$person) {
        error_log('PBA role sync error: no person record found for person_id=' . $person_id);
        return array();
    }

    $expected_roles = array();
    $status = strtolower(trim((string) ($person['status'] ?? '')));
    $supabase_role_names = function_exists('pba_get_active_supabase_role_names_for_person')
        ? pba_get_active_supabase_role_names_for_person($person_id)
        : array();
    $role_name_map = pba_get_supabase_role_name_to_wp_role_map();

    if ($status !== '' && $status !== 'unregistered') {
        $expected_roles[] = 'pba_member';
    }

    foreach ($supabase_role_names as $role_name) {
        $normalized = pba_normalize_supabase_role_name($role_name);

        if (!isset($role_name_map[$normalized])) {
            error_log(
                'PBA role sync error: unknown Supabase role name "' .
                (string) $role_name .
                '" for person_id=' . $person_id
            );
            continue;
        }

        $expected_roles[] = $role_name_map[$normalized];
    }

    if (pba_person_has_any_active_committee_assignment($person_id)) {
        $expected_roles[] = 'pba_committee_member';
    }

    $expected_roles = array_values(array_unique(array_filter($expected_roles, function ($role_slug) {
        return $role_slug !== '';
    })));

    $managed_roles = pba_get_application_wp_role_slugs();

    return array_values(array_intersect($managed_roles, $expected_roles));
}

function pba_sync_wp_roles_for_person($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1) {
        return false;
    }

    $person = pba_get_person_record_by_id($person_id);
    if (!$person) {
        error_log('PBA role sync error: unable to sync, no person record for person_id=' . $person_id);
        return false;
    }

    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;

    if ($wp_user_id < 1) {
        error_log('PBA role sync notice: no linked wp_user_id for person_id=' . $person_id);
        return false;
    }

    $user = get_user_by('id', $wp_user_id);
    if (!$user) {
        error_log('PBA role sync error: wp user not found for wp_user_id=' . $wp_user_id . ', person_id=' . $person_id);
        return false;
    }

    $managed_roles = pba_get_managed_wp_role_slugs();
    $expected_roles = pba_get_expected_wp_roles_for_person($person_id);

    foreach ($managed_roles as $role_slug) {
        if (in_array($role_slug, (array) $user->roles, true)) {
            $user->remove_role($role_slug);
        }
    }

    foreach ($expected_roles as $role_slug) {
        $user->add_role($role_slug);
    }

    update_user_meta($wp_user_id, 'pba_last_role_sync_gmt', gmdate('c'));

    return true;
}

function pba_sync_wp_roles_for_wp_user($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return false;
    }

    $person = pba_get_person_record_by_wp_user_id($wp_user_id);
    if (!$person || empty($person['person_id'])) {
        error_log('PBA role sync error: no linked person found for wp_user_id=' . $wp_user_id);
        return false;
    }

    return pba_sync_wp_roles_for_person((int) $person['person_id']);
}

function pba_sync_wp_roles_on_login($user_login, $user) {
    if (!$user instanceof WP_User) {
        return;
    }

    pba_sync_wp_roles_for_wp_user((int) $user->ID);
}

function pba_maybe_sync_current_user_wp_roles_from_supabase() {
    static $did_sync = false;

    if ($did_sync || !is_user_logged_in()) {
        return;
    }

    $did_sync = true;
    pba_sync_wp_roles_for_wp_user(get_current_user_id());
}

if (!function_exists('pba_get_current_person_role_names')) {
    function pba_get_current_person_role_names() {
        if (!is_user_logged_in()) {
            return array();
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return array();
        }

        $application_roles = function_exists('pba_get_application_wp_role_slugs')
            ? pba_get_application_wp_role_slugs()
            : array('pba_member', 'pba_house_admin', 'pba_board_member', 'pba_committee_member', 'pba_admin');

        $names = array();

        foreach ($user->roles as $role_slug) {
            $role_slug = (string) $role_slug;

            if (in_array($role_slug, $application_roles, true)) {
                $names[] = $role_slug;
            }
        }

        return array_values(array_unique($names));
    }
}

if (!function_exists('pba_get_current_person_role_labels')) {
    function pba_get_current_person_role_labels() {
        $role_slugs = pba_get_current_person_role_names();

        if (empty($role_slugs)) {
            return array();
        }

        $role_defs = function_exists('pba_get_role_definitions')
            ? pba_get_role_definitions()
            : array();

        $labels = array();

        foreach ($role_slugs as $role_slug) {
            $role_slug = (string) $role_slug;

            if ($role_slug === '') {
                continue;
            }

            if (isset($role_defs[$role_slug]['label']) && $role_defs[$role_slug]['label'] !== '') {
                $labels[] = (string) $role_defs[$role_slug]['label'];
            } else {
                $labels[] = $role_slug;
            }
        }

        return array_values(array_unique($labels));
    }
}

if (!function_exists('pba_current_person_has_role')) {
    function pba_current_person_has_role($role_name) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        $normalized = strtolower(trim((string) $role_name));

        $aliases = array(
            'pbaadmin'             => 'pba_admin',
            'pbamember'            => 'pba_member',
            'pbaboardmember'       => 'pba_board_member',
            'pbacommitteemember'   => 'pba_committee_member',
            'pbahouseholdadmin'    => 'pba_house_admin',
            'houseadmin'           => 'pba_house_admin',
        );

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        return in_array($normalized, array_map('strtolower', $user->roles), true);
    }
}

if (!function_exists('pba_current_person_is_admin')) {
    function pba_current_person_is_admin() {
        return current_user_can('pba_manage_roles');
    }
}

if (!function_exists('pba_current_person_is_board_member')) {
    function pba_current_person_is_board_member() {
        return current_user_can('pba_view_board_docs');
    }
}

if (!function_exists('pba_current_person_is_committee_member')) {
    function pba_current_person_is_committee_member() {
        return current_user_can('pba_view_committee_docs');
    }
}

if (!function_exists('pba_get_current_person_record')) {
    function pba_get_current_person_record() {
        static $cached_person = null;
        static $did_lookup = false;

        if ($did_lookup) {
            return $cached_person;
        }

        $did_lookup = true;

        if (!is_user_logged_in()) {
            $cached_person = null;
            return null;
        }

        $user = wp_get_current_user();

        if (!$user) {
            $cached_person = null;
            return null;
        }

        $person = pba_get_person_record_by_wp_user_id((int) $user->ID);

        if ($person) {
            $cached_person = $person;
            return $cached_person;
        }

        if (empty($user->user_email) || !function_exists('pba_supabase_get')) {
            $cached_person = null;
            return null;
        }

        $email = strtolower(trim((string) $user->user_email));

        $rows = pba_supabase_get('Person', array(
            'select'        => pba_get_person_select_fields(),
            'email_address' => 'eq.' . $email,
            'limit'         => 1,
        ));

        if (is_wp_error($rows) || empty($rows) || !is_array($rows)) {
            $cached_person = null;
            return null;
        }

        $cached_person = $rows[0];
        return $cached_person;
    }
}

if (!function_exists('pba_current_person_id')) {
    function pba_current_person_id() {
        $person = pba_get_current_person_record();

        if (!$person || empty($person['person_id'])) {
            return 0;
        }

        return (int) $person['person_id'];
    }
}

/*
 * Backward-compatible helper functions so existing site code
 * can continue to work while we shift toward capability checks.
 */

function pba_user_has_role($role_slug) {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();

    return in_array($role_slug, (array) $user->roles, true);
}

function pba_current_user_has_house_admin_access() {
    return current_user_can('pba_view_household_page');
}

function pba_current_user_has_pba_admin_access() {
    return current_user_can('pba_manage_roles');
}

function pba_get_welcome_name() {
    if (!is_user_logged_in()) {
        return '';
    }

    $user = wp_get_current_user();

    if (!empty($user->first_name)) {
        return $user->first_name;
    }

    if (!empty($user->display_name)) {
        return $user->display_name;
    }

    return $user->user_login;
}
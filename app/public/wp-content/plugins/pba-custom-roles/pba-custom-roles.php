<?php
/**
 * Plugin Name: PBA Custom Roles
 * Description: Registers PBA application roles and capabilities.
 * Version: 1.0.0
 * Author: PBA
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'pba_register_or_update_roles');
add_action('init', 'pba_register_or_update_roles_if_needed');

function pba_register_or_update_roles_if_needed() {
    /*
     * During development, keep roles/caps in sync on init.
     * In production, this is still safe, since the updater is idempotent.
     */
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
            'pba_view_board_docs' => true,
        )),

        'pba_committee_member' => array_merge($member_caps, array(
            'pba_view_committee_docs' => true,
        )),

        'pba_admin' => array_merge($member_caps, array(
            'pba_view_household_page'            => true,
            'pba_invite_household_members'       => true,
            'pba_manage_household_invitations'   => true,
            'pba_disable_household_member'       => true,
            'pba_resend_household_invitation'    => true,
            'pba_cancel_household_invitation'    => true,

            'pba_view_board_docs'                => true,
            'pba_manage_board_docs'              => true,
            'pba_upload_board_docs'              => true,

            'pba_view_committee_docs'            => true,
            'pba_manage_committee_docs'          => true,
            'pba_upload_committee_docs'          => true,

            'pba_manage_calendar'                => true,
            'pba_manage_directory'               => true,
            'pba_manage_meeting_info'            => true,
            'pba_manage_agendas'                 => true,
            'pba_manage_minutes'                 => true,

            'pba_view_all_households'            => true,
            'pba_manage_all_households'          => true,
            'pba_send_association_invitations'   => true,

            'pba_view_access_rights'             => true,
            'pba_manage_roles'                   => true,
            'pba_manage_news'                    => true,

            'pba_view_governing_documents'       => true,
            'pba_manage_governing_documents'     => true,
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
if (!function_exists('pba_get_current_person_role_names')) {
    function pba_get_current_person_role_names() {
        if (!is_user_logged_in()) {
            return array();
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return array();
        }

        $role_defs = function_exists('pba_get_role_definitions')
            ? pba_get_role_definitions()
            : array();

        $names = array();

        foreach ($user->roles as $role_slug) {
            if (isset($role_defs[$role_slug]['label']) && $role_defs[$role_slug]['label'] !== '') {
                $names[] = $role_defs[$role_slug]['label'];
            } else {
                $names[] = $role_slug;
            }
        }

        return $names;
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
            'pbaadmin'            => 'pba_admin',
            'pbamember'           => 'pba_member',
            'pbaboardmember'      => 'pba_board_member',
            'pbacommitteemember'  => 'pba_committee_member',
            'pbahouseadmin'       => 'pba_house_admin',
            'houseadmin'          => 'pba_house_admin',
        );

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        return in_array($normalized, array_map('strtolower', $user->roles), true);
    }
}
if (!function_exists('pba_current_person_is_admin')) {
    function pba_current_person_is_admin() {
        return pba_current_person_has_role('PBAAdmin');
    }
}

if (!function_exists('pba_current_person_is_board_member')) {
    function pba_current_person_is_board_member() {
        return pba_current_person_has_role('PBABoardMember');
    }
}

if (!function_exists('pba_current_person_is_committee_member')) {
    function pba_current_person_is_committee_member() {
        return pba_current_person_has_role('PBACommitteeMember');
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

        if (!$user || empty($user->user_email)) {
            $cached_person = null;
            return null;
        }

        $email = strtolower(trim($user->user_email));

        $rows = pba_supabase_get('Person', array(
            'select'        => '*',
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
<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_save_member_admin', 'pba_handle_save_member_admin');

function pba_members_redirect($status = '', $member_id = 0, $view = 'list') {
    $args = array();

    if ($status !== '') {
        $args['pba_members_status'] = $status;
    }

    if ($member_id > 0) {
        $args['member_id'] = (int) $member_id;
    }

    if ($view !== '') {
        $args['member_view'] = $view;
    }

    wp_safe_redirect(add_query_arg($args, home_url('/members/')));
    exit;
}

if (!function_exists('pba_member_admin_get_person_snapshot')) {
    function pba_member_admin_get_person_snapshot($member_id) {
        $member_id = (int) $member_id;

        if ($member_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,household_id,first_name,last_name,email_address,directory_visibility_level,status,email_verified,wp_user_id,invited_by_person_id,last_modified_at',
            'person_id' => 'eq.' . $member_id,
            'limit'     => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_member_admin_get_person_label')) {
    function pba_member_admin_get_person_label($person_row) {
        if (!is_array($person_row)) {
            return '';
        }

        $first_name = trim((string) ($person_row['first_name'] ?? ''));
        $last_name = trim((string) ($person_row['last_name'] ?? ''));
        $label = trim($first_name . ' ' . $last_name);

        if ($label !== '') {
            return $label;
        }

        $email = trim((string) ($person_row['email_address'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        $person_id = isset($person_row['person_id']) ? (int) $person_row['person_id'] : 0;
        return $person_id > 0 ? 'Person #' . $person_id : '';
    }
}

if (!function_exists('pba_member_admin_get_role_name_map')) {
    function pba_member_admin_get_role_name_map() {
        $rows = pba_supabase_get('Role', array(
            'select' => 'role_id,role_name',
            'order'  => 'role_name.asc',
        ));

        if (is_wp_error($rows) || !is_array($rows)) {
            return array();
        }

        $map = array();

        foreach ($rows as $row) {
            $role_id = isset($row['role_id']) ? (int) $row['role_id'] : 0;
            $role_name = trim((string) ($row['role_name'] ?? ''));

            if ($role_id > 0 && $role_name !== '') {
                $map[$role_id] = $role_name;
            }
        }

        return $map;
    }
}

if (!function_exists('pba_member_admin_get_committee_name_map')) {
    function pba_member_admin_get_committee_name_map() {
        $rows = pba_supabase_get('Committee', array(
            'select' => 'committee_id,committee_name',
            'order'  => 'display_order.asc,committee_name.asc',
        ));

        if (is_wp_error($rows) || !is_array($rows)) {
            return array();
        }

        $map = array();

        foreach ($rows as $row) {
            $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
            $committee_name = trim((string) ($row['committee_name'] ?? ''));

            if ($committee_id > 0 && $committee_name !== '') {
                $map[$committee_id] = $committee_name;
            }
        }

        return $map;
    }
}

if (!function_exists('pba_member_admin_get_existing_role_ids')) {
    function pba_member_admin_get_existing_role_ids($member_id) {
        $member_id = (int) $member_id;

        if ($member_id < 1) {
            return array();
        }

        $rows = pba_supabase_get('Person_to_Role', array(
            'select'    => 'role_id',
            'person_id' => 'eq.' . $member_id,
            'is_active' => 'eq.true',
        ));

        if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
            return array();
        }

        $role_ids = array();

        foreach ($rows as $row) {
            $role_id = isset($row['role_id']) ? (int) $row['role_id'] : 0;
            if ($role_id > 0) {
                $role_ids[] = $role_id;
            }
        }

        $role_ids = array_values(array_unique($role_ids));
        sort($role_ids);

        return $role_ids;
    }
}

if (!function_exists('pba_member_admin_get_existing_committees')) {
    function pba_member_admin_get_existing_committees($member_id) {
        $member_id = (int) $member_id;

        if ($member_id < 1) {
            return array();
        }

        $rows = pba_supabase_get('Person_to_Committee', array(
            'select'    => 'committee_id,committee_role',
            'person_id' => 'eq.' . $member_id,
            'is_active' => 'eq.true',
        ));

        if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
            return array();
        }

        $result = array();

        foreach ($rows as $row) {
            $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
            if ($committee_id > 0) {
                $result[$committee_id] = array(
                    'committee_role' => trim((string) ($row['committee_role'] ?? '')),
                );
            }
        }

        ksort($result);

        return $result;
    }
}

if (!function_exists('pba_member_admin_audit_log')) {
    function pba_member_admin_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

if (!function_exists('pba_member_admin_log_role_changes')) {
    function pba_member_admin_log_role_changes($member_id, $person_before, $person_after, $old_role_ids, $new_role_ids) {
        $member_id = (int) $member_id;
        $old_role_ids = array_values(array_unique(array_map('intval', (array) $old_role_ids)));
        $new_role_ids = array_values(array_unique(array_map('intval', (array) $new_role_ids)));
        sort($old_role_ids);
        sort($new_role_ids);

        $removed_role_ids = array_values(array_diff($old_role_ids, $new_role_ids));
        $added_role_ids = array_values(array_diff($new_role_ids, $old_role_ids));

        if (empty($removed_role_ids) && empty($added_role_ids)) {
            return;
        }

        $role_name_map = pba_member_admin_get_role_name_map();
        $entity_label = pba_member_admin_get_person_label($person_after ?: $person_before);
        $target_household_id = isset($person_after['household_id'])
            ? (int) $person_after['household_id']
            : (isset($person_before['household_id']) ? (int) $person_before['household_id'] : null);

        foreach ($removed_role_ids as $role_id) {
            $role_name = isset($role_name_map[$role_id]) ? $role_name_map[$role_id] : ('Role #' . $role_id);

            pba_member_admin_audit_log(
                'role.removed',
                'Person',
                $member_id,
                array(
                    'entity_label'        => $entity_label,
                    'target_person_id'    => $member_id,
                    'target_household_id' => $target_household_id,
                    'summary'             => 'Role removed from member.',
                    'details'             => array(
                        'role_id'   => $role_id,
                        'role_name' => $role_name,
                    ),
                )
            );
        }

        foreach ($added_role_ids as $role_id) {
            $role_name = isset($role_name_map[$role_id]) ? $role_name_map[$role_id] : ('Role #' . $role_id);

            pba_member_admin_audit_log(
                'role.assigned',
                'Person',
                $member_id,
                array(
                    'entity_label'        => $entity_label,
                    'target_person_id'    => $member_id,
                    'target_household_id' => $target_household_id,
                    'summary'             => 'Role assigned to member.',
                    'details'             => array(
                        'role_id'   => $role_id,
                        'role_name' => $role_name,
                    ),
                )
            );
        }
    }
}

if (!function_exists('pba_member_admin_log_committee_changes')) {
    function pba_member_admin_log_committee_changes($member_id, $person_before, $person_after, $old_committees, $new_committees) {
        $member_id = (int) $member_id;
        $old_committees = is_array($old_committees) ? $old_committees : array();
        $new_committees = is_array($new_committees) ? $new_committees : array();

        $old_committee_ids = array_map('intval', array_keys($old_committees));
        $new_committee_ids = array_map('intval', array_keys($new_committees));

        $removed_committee_ids = array_values(array_diff($old_committee_ids, $new_committee_ids));
        $added_committee_ids = array_values(array_diff($new_committee_ids, $old_committee_ids));

        $committee_name_map = pba_member_admin_get_committee_name_map();
        $entity_label = pba_member_admin_get_person_label($person_after ?: $person_before);
        $target_household_id = isset($person_after['household_id'])
            ? (int) $person_after['household_id']
            : (isset($person_before['household_id']) ? (int) $person_before['household_id'] : null);

        foreach ($removed_committee_ids as $committee_id) {
            $committee_name = isset($committee_name_map[$committee_id]) ? $committee_name_map[$committee_id] : ('Committee #' . $committee_id);
            $old_role = isset($old_committees[$committee_id]['committee_role']) ? (string) $old_committees[$committee_id]['committee_role'] : '';

            pba_member_admin_audit_log(
                'committee.member.removed',
                'Person',
                $member_id,
                array(
                    'entity_label'        => $entity_label,
                    'target_person_id'    => $member_id,
                    'target_household_id' => $target_household_id,
                    'target_committee_id' => $committee_id,
                    'summary'             => 'Member removed from committee.',
                    'details'             => array(
                        'committee_id'   => $committee_id,
                        'committee_name' => $committee_name,
                        'committee_role' => $old_role !== '' ? $old_role : null,
                    ),
                )
            );
        }

        foreach ($added_committee_ids as $committee_id) {
            $committee_name = isset($committee_name_map[$committee_id]) ? $committee_name_map[$committee_id] : ('Committee #' . $committee_id);
            $new_role = isset($new_committees[$committee_id]['committee_role']) ? (string) $new_committees[$committee_id]['committee_role'] : '';

            pba_member_admin_audit_log(
                'committee.member.added',
                'Person',
                $member_id,
                array(
                    'entity_label'        => $entity_label,
                    'target_person_id'    => $member_id,
                    'target_household_id' => $target_household_id,
                    'target_committee_id' => $committee_id,
                    'summary'             => 'Member added to committee.',
                    'details'             => array(
                        'committee_id'   => $committee_id,
                        'committee_name' => $committee_name,
                        'committee_role' => $new_role !== '' ? $new_role : null,
                    ),
                )
            );
        }

        $common_committee_ids = array_values(array_intersect($old_committee_ids, $new_committee_ids));

        foreach ($common_committee_ids as $committee_id) {
            $old_role = isset($old_committees[$committee_id]['committee_role']) ? trim((string) $old_committees[$committee_id]['committee_role']) : '';
            $new_role = isset($new_committees[$committee_id]['committee_role']) ? trim((string) $new_committees[$committee_id]['committee_role']) : '';

            if ($old_role === $new_role) {
                continue;
            }

            $committee_name = isset($committee_name_map[$committee_id]) ? $committee_name_map[$committee_id] : ('Committee #' . $committee_id);

            pba_member_admin_audit_log(
                'committee.member.added',
                'Person',
                $member_id,
                array(
                    'entity_label'        => $entity_label,
                    'target_person_id'    => $member_id,
                    'target_household_id' => $target_household_id,
                    'target_committee_id' => $committee_id,
                    'summary'             => 'Committee role updated for member.',
                    'details'             => array(
                        'committee_id'        => $committee_id,
                        'committee_name'      => $committee_name,
                        'old_committee_role'  => $old_role !== '' ? $old_role : null,
                        'new_committee_role'  => $new_role !== '' ? $new_role : null,
                    ),
                )
            );
        }
    }
}

function pba_handle_save_member_admin() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_member_admin_nonce']) ||
        !wp_verify_nonce($_POST['pba_member_admin_nonce'], 'pba_member_admin_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $member_id       = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $household_id    = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $first_name      = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name       = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address   = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $status          = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Unregistered';

    $role_ids        = isset($_POST['role_ids']) ? array_map('absint', (array) $_POST['role_ids']) : array();
    $committee_ids   = isset($_POST['committee_ids']) ? array_map('absint', (array) $_POST['committee_ids']) : array();
    $committee_roles = isset($_POST['committee_roles']) ? (array) $_POST['committee_roles'] : array();

    $role_ids = array_values(array_unique(array_filter($role_ids, function ($id) {
        return (int) $id > 0;
    })));

    $committee_ids = array_values(array_unique(array_filter($committee_ids, function ($id) {
        return (int) $id > 0;
    })));

    if ($member_id < 1 || $first_name === '' || $last_name === '') {
        pba_members_redirect('invalid_member_input', $member_id, 'edit');
    }

    if ($email_address !== '' && !is_email($email_address)) {
        pba_members_redirect('invalid_member_email', $member_id, 'edit');
    }

    $directory_visibility_level = isset($_POST['directory_visibility_level'])
        ? sanitize_text_field(wp_unslash($_POST['directory_visibility_level']))
        : 'hidden';

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        $directory_visibility_level = 'hidden';
    }

    $before = pba_member_admin_get_person_snapshot($member_id);
    $old_role_ids = pba_member_admin_get_existing_role_ids($member_id);
    $old_committees = pba_member_admin_get_existing_committees($member_id);

    $updated = pba_supabase_update(
        'Person',
        array(
            'first_name'                => $first_name,
            'last_name'                 => $last_name,
            'email_address'             => $email_address !== '' ? $email_address : null,
            'directory_visibility_level'=> $directory_visibility_level,
            'status'                    => $status,
            'household_id'              => $household_id > 0 ? $household_id : null,
            'last_modified_at'          => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $member_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_member_admin_audit_log(
            'member.updated',
            'Person',
            $member_id,
            array(
                'entity_label'        => pba_member_admin_get_person_label($before),
                'target_person_id'    => $member_id,
                'target_household_id' => isset($before['household_id']) ? (int) $before['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to save member changes.',
                'before'              => $before,
                'details'             => array(
                    'error_code'    => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (!pba_member_admin_replace_roles($member_id, $role_ids)) {
        pba_member_admin_audit_log(
            'member.updated',
            'Person',
            $member_id,
            array(
                'entity_label'        => pba_member_admin_get_person_label($before),
                'target_person_id'    => $member_id,
                'target_household_id' => isset($before['household_id']) ? (int) $before['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Member record saved but role replacement failed.',
                'before'              => $before,
                'details'             => array(
                    'stage' => 'replace_roles',
                ),
            )
        );

        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (!pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles)) {
        pba_member_admin_audit_log(
            'member.updated',
            'Person',
            $member_id,
            array(
                'entity_label'        => pba_member_admin_get_person_label($before),
                'target_person_id'    => $member_id,
                'target_household_id' => isset($before['household_id']) ? (int) $before['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Member record saved but committee replacement failed.',
                'before'              => $before,
                'details'             => array(
                    'stage' => 'replace_committees',
                ),
            )
        );

        pba_members_redirect('member_update_failed', $member_id, 'edit');
    }

    if (function_exists('pba_sync_wp_roles_for_person')) {
        pba_sync_wp_roles_for_person($member_id);
    }

    $after = pba_member_admin_get_person_snapshot($member_id);
    $new_role_ids = pba_member_admin_get_existing_role_ids($member_id);
    $new_committees = pba_member_admin_get_existing_committees($member_id);

    pba_member_admin_audit_log(
        'member.updated',
        'Person',
        $member_id,
        array(
            'entity_label'        => pba_member_admin_get_person_label($after ?: $before),
            'target_person_id'    => $member_id,
            'target_household_id' => isset($after['household_id'])
                ? (int) $after['household_id']
                : (isset($before['household_id']) ? (int) $before['household_id'] : null),
            'summary'             => 'Member record updated by admin.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'synced_wp_roles' => function_exists('pba_sync_wp_roles_for_person'),
            ),
        )
    );

    pba_member_admin_log_role_changes($member_id, $before, $after, $old_role_ids, $new_role_ids);
    pba_member_admin_log_committee_changes($member_id, $before, $after, $old_committees, $new_committees);

    pba_members_redirect('member_saved', $member_id, 'edit');
}

function pba_member_admin_replace_roles($member_id, $role_ids) {
    $member_id = (int) $member_id;

    if ($member_id < 1) {
        return false;
    }

    $existing = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_role_id'])) {
                $deleted = pba_supabase_delete('Person_to_Role', array(
                    'person_to_role_id' => 'eq.' . (int) $row['person_to_role_id'],
                ));

                if (is_wp_error($deleted)) {
                    return false;
                }
            }
        }
    }

    foreach ($role_ids as $role_id) {
        $inserted = pba_supabase_insert('Person_to_Role', array(
            'person_id'        => $member_id,
            'role_id'          => (int) $role_id,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'last_modified_at' => gmdate('c'),
        ));

        if (is_wp_error($inserted)) {
            return false;
        }
    }

    return true;
}

function pba_member_admin_replace_committees($member_id, $committee_ids, $committee_roles) {
    $member_id = (int) $member_id;

    if ($member_id < 1) {
        return false;
    }

    $existing = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_to_committee_id',
        'person_id' => 'eq.' . $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            if (!empty($row['person_to_committee_id'])) {
                $deleted = pba_supabase_delete('Person_to_Committee', array(
                    'person_to_committee_id' => 'eq.' . (int) $row['person_to_committee_id'],
                ));

                if (is_wp_error($deleted)) {
                    return false;
                }
            }
        }
    }

    foreach ($committee_ids as $committee_id) {
        $committee_role = isset($committee_roles[$committee_id])
            ? sanitize_text_field(wp_unslash($committee_roles[$committee_id]))
            : '';

        $inserted = pba_supabase_insert('Person_to_Committee', array(
            'person_id'        => $member_id,
            'committee_id'     => (int) $committee_id,
            'committee_role'   => $committee_role !== '' ? $committee_role : null,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'display_order'    => null,
            'last_modified_at' => gmdate('c'),
        ));

        if (is_wp_error($inserted)) {
            return false;
        }
    }

    return true;
}
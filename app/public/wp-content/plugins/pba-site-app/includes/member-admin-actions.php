<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_admin_disable_member', 'pba_handle_admin_disable_member');
add_action('admin_post_pba_admin_enable_member', 'pba_handle_admin_enable_member');
add_action('admin_post_pba_admin_cancel_invite', 'pba_handle_admin_cancel_invite');
add_action('admin_post_pba_admin_resend_invite', 'pba_handle_admin_resend_invite');
add_action('admin_post_pba_admin_remove_member_from_household', 'pba_handle_admin_remove_member_from_household');
add_action('admin_post_pba_admin_hard_delete_person', 'pba_handle_admin_hard_delete_person');
add_filter('wp_mail_from_name', 'pba_wp_mail_from_name');

function pba_wp_mail_from_name($name) {
    return 'Priscilla Beach Association';
}

add_filter('wp_mail_from', 'pba_wp_mail_from');

function pba_wp_mail_from($email) {
    return 'no-reply@priscilla-beach-association.local';
}

if (!function_exists('pba_admin_member_get_person_snapshot')) {
    function pba_admin_member_get_person_snapshot($person_id) {
        $person_id = (int) $person_id;

        if ($person_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,household_id,first_name,last_name,email_address,status,email_verified,wp_user_id,invited_by_person_id,directory_visibility_level,last_modified_at',
            'person_id' => 'eq.' . $person_id,
            'limit'     => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_admin_member_get_person_label')) {
    function pba_admin_member_get_person_label($person_row) {
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

if (!function_exists('pba_admin_member_audit_log')) {
    function pba_admin_member_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

function pba_clear_managed_wp_roles_for_user($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return;
    }

    $user = get_user_by('id', $wp_user_id);
    if (!$user) {
        return;
    }

    $managed_roles = function_exists('pba_get_managed_wp_role_slugs')
        ? pba_get_managed_wp_role_slugs()
        : array('pba_member', 'pba_house_admin', 'pba_board_member', 'pba_committee_member', 'pba_admin');

    foreach ($managed_roles as $role_slug) {
        if (in_array($role_slug, (array) $user->roles, true)) {
            $user->remove_role($role_slug);
        }
    }
}

function pba_handle_admin_disable_member() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('disable_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,status,wp_user_id,first_name,last_name,email_address,household_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('disable_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Disabled',
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_admin_member_audit_log(
            'member.disabled',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to disable member.',
                'before'              => $before,
                'details'             => array(
                    'error_code' => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_members_redirect('disable_failed', $person_id, 'edit');
    }

    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;

    if ($wp_user_id > 0) {
        pba_clear_managed_wp_roles_for_user($wp_user_id);

        if (class_exists('WP_Session_Tokens')) {
            $manager = WP_Session_Tokens::get_instance($wp_user_id);
            $manager->destroy_all();
        }
    }

    $after = pba_admin_member_get_person_snapshot($person_id);

    pba_admin_member_audit_log(
        'member.disabled',
        'Person',
        $person_id,
        array(
            'entity_label'        => pba_admin_member_get_person_label($after ?: $before ?: $person),
            'target_person_id'    => $person_id,
            'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
            'summary'             => 'Member disabled by admin.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'cleared_wp_roles' => ($wp_user_id > 0),
                'destroyed_sessions' => ($wp_user_id > 0),
            ),
        )
    );

    pba_members_redirect('member_disabled', $person_id, 'edit');
}

function pba_handle_admin_enable_member() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('enable_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Active',
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_admin_member_audit_log(
            'member.enabled',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($before['household_id']) ? (int) $before['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to enable member.',
                'before'              => $before,
                'details'             => array(
                    'error_code' => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_members_redirect('enable_failed', $person_id, 'edit');
    }

    if (function_exists('pba_sync_wp_roles_for_person')) {
        pba_sync_wp_roles_for_person($person_id);
    }

    $after = pba_admin_member_get_person_snapshot($person_id);

    pba_admin_member_audit_log(
        'member.enabled',
        'Person',
        $person_id,
        array(
            'entity_label'        => pba_admin_member_get_person_label($after ?: $before),
            'target_person_id'    => $person_id,
            'target_household_id' => isset($after['household_id']) ? (int) $after['household_id'] : (isset($before['household_id']) ? (int) $before['household_id'] : null),
            'summary'             => 'Member enabled by admin.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'synced_wp_roles' => function_exists('pba_sync_wp_roles_for_person'),
            ),
        )
    );

    pba_members_redirect('member_enabled', $person_id, 'edit');
}

function pba_handle_admin_cancel_invite() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('cancel_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,status,wp_user_id,first_name,last_name,email_address,household_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    if (($person['status'] ?? '') !== 'Pending') {
        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $delete_links = pba_supabase_delete('Person_to_Role', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_links)) {
        pba_admin_member_audit_log(
            'household.invite.cancelled',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to cancel pending invitation while removing roles.',
                'before'              => $before,
                'details'             => array(
                    'stage' => 'delete_person_to_role',
                    'error_code' => $delete_links->get_error_code(),
                    'error_message' => $delete_links->get_error_message(),
                ),
            )
        );

        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $delete_committees = pba_supabase_delete('Person_to_Committee', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_committees)) {
        pba_admin_member_audit_log(
            'household.invite.cancelled',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to cancel pending invitation while removing committee assignments.',
                'before'              => $before,
                'details'             => array(
                    'stage' => 'delete_person_to_committee',
                    'error_code' => $delete_committees->get_error_code(),
                    'error_message' => $delete_committees->get_error_message(),
                ),
            )
        );

        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;
    if ($wp_user_id > 0) {
        pba_clear_managed_wp_roles_for_user($wp_user_id);

        if (class_exists('WP_Session_Tokens')) {
            $manager = WP_Session_Tokens::get_instance($wp_user_id);
            $manager->destroy_all();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted_wp_user = wp_delete_user($wp_user_id);

        if (!$deleted_wp_user) {
            pba_admin_member_audit_log(
                'household.invite.cancelled',
                'Person',
                $person_id,
                array(
                    'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                    'target_person_id'    => $person_id,
                    'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                    'result_status'       => 'failure',
                    'summary'             => 'Failed to cancel pending invitation because linked WordPress user could not be deleted.',
                    'before'              => $before,
                    'details'             => array(
                        'stage' => 'delete_wp_user',
                        'wp_user_id' => $wp_user_id,
                    ),
                )
            );

            pba_members_redirect('cancel_failed', $person_id, 'edit');
        }
    }

    $delete_person = pba_supabase_delete('Person', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_person)) {
        pba_admin_member_audit_log(
            'household.invite.cancelled',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to cancel pending invitation while deleting person record.',
                'before'              => $before,
                'details'             => array(
                    'stage' => 'delete_person',
                    'error_code' => $delete_person->get_error_code(),
                    'error_message' => $delete_person->get_error_message(),
                ),
            )
        );

        pba_members_redirect('cancel_failed', $person_id, 'edit');
    }

    pba_delete_member_invite_transients($person_id);

    pba_admin_member_audit_log(
        'household.invite.cancelled',
        'Person',
        $person_id,
        array(
            'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
            'target_person_id'    => $person_id,
            'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
            'summary'             => 'Pending invitation cancelled by admin; person record removed.',
            'before'              => $before,
            'after'               => null,
            'details'             => array(
                'deleted_wp_user' => ($wp_user_id > 0),
                'deleted_person_to_role_links' => true,
                'deleted_person_to_committee_links' => true,
                'deleted_person_record' => true,
            ),
        )
    );

    pba_members_redirect('invite_cancelled', 0, 'list');
}

function pba_handle_admin_resend_invite() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('resend_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,first_name,last_name,email_address,status,invited_by_person_id,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $person = $rows[0];

    if (($person['status'] ?? '') !== 'Expired') {
        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $member_role_id = pba_supabase_find_role_id_by_name('PBAMember');

    if (!$member_role_id) {
        pba_admin_member_audit_log(
            'household.invite.resent',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to resend invitation because PBAMember role id could not be found.',
                'before'              => $before,
            )
        );

        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'status'           => 'Pending',
            'email_verified'   => 0,
            'wp_user_id'       => null,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_admin_member_audit_log(
            'household.invite.resent',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Failed to resend invitation while updating person status back to Pending.',
                'before'              => $before,
                'details'             => array(
                    'error_code' => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $existing_role_links = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id,person_id,role_id',
        'person_id' => 'eq.' . $person_id,
        'role_id'   => 'eq.' . (int) $member_role_id,
        'limit'     => 1,
    ));

    if (!is_wp_error($existing_role_links) && empty($existing_role_links)) {
        $ptr = pba_create_person_to_role_record($person_id, $member_role_id);
        if (is_wp_error($ptr)) {
            pba_admin_member_audit_log(
                'household.invite.resent',
                'Person',
                $person_id,
                array(
                    'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                    'target_person_id'    => $person_id,
                    'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                    'result_status'       => 'failure',
                    'summary'             => 'Failed to resend invitation while restoring PBAMember role link.',
                    'before'              => $before,
                    'details'             => array(
                        'stage' => 'create_person_to_role',
                        'role_id' => (int) $member_role_id,
                        'error_code' => $ptr->get_error_code(),
                        'error_message' => $ptr->get_error_message(),
                    ),
                )
            );

            pba_members_redirect('resend_failed', $person_id, 'edit');
        }
    }

    pba_delete_member_invite_transients($person_id);

    $invite_token = pba_store_member_invite_token(
        $person_id,
        (int) ($person['household_id'] ?? 0),
        (string) ($person['email_address'] ?? ''),
        (string) ($person['first_name'] ?? ''),
        (string) ($person['last_name'] ?? ''),
        'PBAMember'
    );

    $sent = pba_send_member_invite_email(array(
        'first_name'    => $person['first_name'] ?? '',
        'last_name'     => $person['last_name'] ?? '',
        'email_address' => $person['email_address'] ?? '',
    ), $invite_token);

    if (!$sent) {
        pba_admin_member_audit_log(
            'household.invite.resent',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
                'result_status'       => 'failure',
                'summary'             => 'Invitation token was regenerated but resend email failed.',
                'before'              => $before,
                'details'             => array(
                    'invite_token_created' => true,
                    'email_sent' => false,
                ),
            )
        );

        pba_members_redirect('resend_failed', $person_id, 'edit');
    }

    $after = pba_admin_member_get_person_snapshot($person_id);

    pba_admin_member_audit_log(
        'household.invite.resent',
        'Person',
        $person_id,
        array(
            'entity_label'        => pba_admin_member_get_person_label($after ?: $before ?: $person),
            'target_person_id'    => $person_id,
            'target_household_id' => isset($person['household_id']) ? (int) $person['household_id'] : null,
            'summary'             => 'Expired invitation was resent by admin.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'invite_token_created' => true,
                'email_sent' => true,
                'role_id_ensured' => (int) $member_role_id,
            ),
        )
    );

    pba_members_redirect('invite_resent', $person_id, 'edit');
}

function pba_handle_admin_remove_member_from_household() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('remove_from_household_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,status,wp_user_id,first_name,last_name,email_address',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('remove_from_household_failed', $person_id, 'edit');
    }

    $person = $rows[0];
    $household_id = isset($person['household_id']) ? (int) $person['household_id'] : 0;

    if ($household_id < 1) {
        pba_members_redirect('member_removed_from_household', $person_id, 'edit');
    }

    if (pba_admin_person_has_role_name($person_id, 'PBAHouseholdAdmin')) {
        pba_members_redirect('remove_from_household_blocked_house_admin', $person_id, 'edit');
    }

    if (pba_admin_person_is_last_active_house_admin_for_household($person_id, $household_id)) {
        pba_members_redirect('remove_from_household_blocked_last_admin', $person_id, 'edit');
    }

    $updated = pba_supabase_update(
        'Person',
        array(
            'household_id'     => null,
            'last_modified_at' => gmdate('c'),
        ),
        array(
            'person_id' => 'eq.' . $person_id,
        )
    );

    if (is_wp_error($updated)) {
        pba_admin_member_audit_log(
            'member.removed_from_household',
            'Person',
            $person_id,
            array(
                'entity_label'        => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id'    => $person_id,
                'target_household_id' => $household_id,
                'result_status'       => 'failure',
                'summary'             => 'Failed to remove member from household.',
                'before'              => $before,
                'details'             => array(
                    'error_code' => $updated->get_error_code(),
                    'error_message' => $updated->get_error_message(),
                ),
            )
        );

        pba_members_redirect('remove_from_household_failed', $person_id, 'edit');
    }

    if (function_exists('pba_sync_wp_roles_for_person')) {
        pba_sync_wp_roles_for_person($person_id);
    }

    $after = pba_admin_member_get_person_snapshot($person_id);

    pba_admin_member_audit_log(
        'member.removed_from_household',
        'Person',
        $person_id,
        array(
            'entity_label'        => pba_admin_member_get_person_label($after ?: $before ?: $person),
            'target_person_id'    => $person_id,
            'target_household_id' => $household_id,
            'summary'             => 'Member removed from household by admin.',
            'before'              => $before,
            'after'               => $after,
            'details'             => array(
                'synced_wp_roles' => function_exists('pba_sync_wp_roles_for_person'),
            ),
        )
    );

    pba_members_redirect('member_removed_from_household', $person_id, 'edit');
}

function pba_handle_admin_hard_delete_person() {
    if (!is_user_logged_in() || !current_user_can('pba_manage_roles')) {
        wp_die('Unauthorized', 403);
    }

    if (
        !isset($_POST['pba_admin_member_action_nonce']) ||
        !wp_verify_nonce($_POST['pba_admin_member_action_nonce'], 'pba_admin_member_action')
    ) {
        wp_die('Invalid nonce', 403);
    }

    $person_id = isset($_POST['person_id']) ? absint($_POST['person_id']) : 0;

    if ($person_id < 1) {
        pba_members_redirect('delete_person_failed', 0, 'list');
    }

    $before = pba_admin_member_get_person_snapshot($person_id);

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,wp_user_id,first_name,last_name,email_address',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        pba_members_redirect('delete_person_failed', $person_id, 'edit');
    }

    $person = $rows[0];
    $household_id = isset($person['household_id']) ? (int) $person['household_id'] : 0;
    $wp_user_id = isset($person['wp_user_id']) ? (int) $person['wp_user_id'] : 0;

    if (pba_admin_person_has_role_name($person_id, 'PBAHouseholdAdmin')) {
        pba_members_redirect('delete_person_blocked_house_admin', $person_id, 'edit');
    }

    if ($household_id > 0) {
        pba_members_redirect('delete_person_blocked_household', $person_id, 'edit');
    }

    if (pba_admin_person_has_active_committees($person_id)) {
        pba_members_redirect('delete_person_blocked_committees', $person_id, 'edit');
    }

    if ($wp_user_id > 0) {
        pba_clear_managed_wp_roles_for_user($wp_user_id);

        if (class_exists('WP_Session_Tokens')) {
            $manager = WP_Session_Tokens::get_instance($wp_user_id);
            $manager->destroy_all();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted_wp_user = wp_delete_user($wp_user_id);

        if (!$deleted_wp_user) {
            pba_admin_member_audit_log(
                'member.hard_deleted',
                'Person',
                $person_id,
                array(
                    'entity_label'     => pba_admin_member_get_person_label($before ?: $person),
                    'target_person_id' => $person_id,
                    'result_status'    => 'failure',
                    'summary'          => 'Failed to hard delete person because linked WordPress user could not be deleted.',
                    'before'           => $before,
                    'details'          => array(
                        'stage' => 'delete_wp_user',
                        'wp_user_id' => $wp_user_id,
                    ),
                )
            );

            pba_members_redirect('delete_person_failed', $person_id, 'edit');
        }
    }

    $delete_roles = pba_supabase_delete('Person_to_Role', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_roles)) {
        pba_admin_member_audit_log(
            'member.hard_deleted',
            'Person',
            $person_id,
            array(
                'entity_label'     => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id' => $person_id,
                'result_status'    => 'failure',
                'summary'          => 'Failed to hard delete person while deleting role links.',
                'before'           => $before,
                'details'          => array(
                    'stage' => 'delete_person_to_role',
                    'error_code' => $delete_roles->get_error_code(),
                    'error_message' => $delete_roles->get_error_message(),
                ),
            )
        );

        pba_members_redirect('delete_person_failed', $person_id, 'edit');
    }

    $delete_committees = pba_supabase_delete('Person_to_Committee', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_committees)) {
        pba_admin_member_audit_log(
            'member.hard_deleted',
            'Person',
            $person_id,
            array(
                'entity_label'     => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id' => $person_id,
                'result_status'    => 'failure',
                'summary'          => 'Failed to hard delete person while deleting committee links.',
                'before'           => $before,
                'details'          => array(
                    'stage' => 'delete_person_to_committee',
                    'error_code' => $delete_committees->get_error_code(),
                    'error_message' => $delete_committees->get_error_message(),
                ),
            )
        );

        pba_members_redirect('delete_person_failed', $person_id, 'edit');
    }

    $delete_person = pba_supabase_delete('Person', array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($delete_person)) {
        pba_admin_member_audit_log(
            'member.hard_deleted',
            'Person',
            $person_id,
            array(
                'entity_label'     => pba_admin_member_get_person_label($before ?: $person),
                'target_person_id' => $person_id,
                'result_status'    => 'failure',
                'summary'          => 'Failed to hard delete person while deleting person record.',
                'before'           => $before,
                'details'          => array(
                    'stage' => 'delete_person',
                    'error_code' => $delete_person->get_error_code(),
                    'error_message' => $delete_person->get_error_message(),
                ),
            )
        );

        pba_members_redirect('delete_person_failed', $person_id, 'edit');
    }

    if (function_exists('pba_delete_member_invite_transients')) {
        pba_delete_member_invite_transients($person_id);
    }

    pba_admin_member_audit_log(
        'member.hard_deleted',
        'Person',
        $person_id,
        array(
            'entity_label'     => pba_admin_member_get_person_label($before ?: $person),
            'target_person_id' => $person_id,
            'summary'          => 'Person record hard deleted by admin.',
            'before'           => $before,
            'after'            => null,
            'details'          => array(
                'deleted_wp_user' => ($wp_user_id > 0),
                'deleted_person_to_role_links' => true,
                'deleted_person_to_committee_links' => true,
                'deleted_person_record' => true,
            ),
        )
    );

    pba_members_redirect('member_deleted', 0, 'list');
}

function pba_admin_person_has_role_name($person_id, $role_name) {
    $person_id = (int) $person_id;
    $role_name = trim((string) $role_name);

    if ($person_id < 1 || $role_name === '') {
        return false;
    }

    $rows = pba_supabase_get('Person_to_Role', array(
        'select'    => 'role_id',
        'person_id' => 'eq.' . $person_id,
        'is_active' => 'eq.true',
        'limit'     => 20,
    ));

    if (is_wp_error($rows) || empty($rows)) {
        return false;
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
        return false;
    }

    $role_rows = pba_supabase_get('Role', array(
        'select'  => 'role_id,role_name',
        'role_id' => 'in.(' . implode(',', $role_ids) . ')',
        'limit'   => count($role_ids),
    ));

    if (is_wp_error($role_rows) || empty($role_rows)) {
        return false;
    }

    foreach ($role_rows as $role_row) {
        if (isset($role_row['role_name']) && (string) $role_row['role_name'] === $role_name) {
            return true;
        }
    }

    return false;
}

function pba_admin_person_is_last_active_house_admin_for_household($person_id, $household_id) {
    $person_id = (int) $person_id;
    $household_id = (int) $household_id;

    if ($person_id < 1 || $household_id < 1) {
        return false;
    }

    if (!pba_admin_person_has_role_name($person_id, 'PBAHouseholdAdmin')) {
        return false;
    }

    $household_people = pba_supabase_get('Person', array(
        'select'       => 'person_id,status',
        'household_id' => 'eq.' . $household_id,
        'limit'        => 200,
    ));

    if (is_wp_error($household_people) || empty($household_people)) {
        return false;
    }

    $active_admin_count = 0;

    foreach ($household_people as $person_row) {
        $candidate_person_id = isset($person_row['person_id']) ? (int) $person_row['person_id'] : 0;
        $status = isset($person_row['status']) ? (string) $person_row['status'] : '';

        if ($candidate_person_id < 1) {
            continue;
        }

        if (!in_array($status, array('Active', 'Pending', 'Disabled', 'Expired', 'Unregistered'), true)) {
            continue;
        }

        if (pba_admin_person_has_role_name($candidate_person_id, 'PBAHouseholdAdmin')) {
            $active_admin_count++;
        }
    }

    return $active_admin_count <= 1;
}

function pba_admin_person_has_active_committees($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1) {
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

function pba_admin_can_hard_delete_person($person_id) {
    $person_id = (int) $person_id;

    if ($person_id < 1) {
        return false;
    }

    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,wp_user_id',
        'person_id' => 'eq.' . $person_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return false;
    }

    $person = $rows[0];
    $household_id = isset($person['household_id']) ? (int) $person['household_id'] : 0;

    if ($household_id > 0) {
        return false;
    }

    if (pba_admin_person_has_role_name($person_id, 'PBAHouseholdAdmin')) {
        return false;
    }

    if (pba_admin_person_has_active_committees($person_id)) {
        return false;
    }

    return true;
}
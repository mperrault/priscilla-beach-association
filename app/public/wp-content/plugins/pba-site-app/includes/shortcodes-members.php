<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_members_shortcode');

function pba_register_members_shortcode() {
    add_shortcode('pba_members', 'pba_render_members_shortcode');
}
if (!function_exists('pba_get_active_role_names_for_person')) {
    function pba_get_active_role_names_for_person($person_id) {
        static $role_cache = array();

        $person_id = (int) $person_id;

        if ($person_id < 1) {
            return array();
        }

        if (array_key_exists($person_id, $role_cache)) {
            return $role_cache[$person_id];
        }

        $rows = pba_supabase_get('Person_to_Role', array(
            'select'    => 'role_id',
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
}

function pba_render_members_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_person_has_role('PBAAdmin')) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $view = isset($_GET['member_view']) ? sanitize_text_field(wp_unslash($_GET['member_view'])) : 'list';
    $member_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;

    if ($view === 'edit' && $member_id > 0) {
        return pba_render_member_edit_view($member_id);
    }

    return pba_render_members_list_view();
}

function pba_render_members_status_message() {
    $status = isset($_GET['pba_members_status']) ? sanitize_text_field(wp_unslash($_GET['pba_members_status'])) : '';

    if ($status === '') {
        return '';
    }

    $message = str_replace('_', ' ', $status);
    $success_statuses = array(
        'member_saved',
        'member_disabled',
        'member_enabled',
        'invite_cancelled',
        'invite_resent',
    );
    $class = in_array($status, $success_statuses, true) ? 'pba-members-message' : 'pba-members-message error';

    return '<div class="' . esc_attr($class) . '">' . esc_html(ucfirst($message)) . '</div>';
}

function pba_render_members_list_view() {
    $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';

    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,household_id,first_name,last_name,email_address,status,last_modified_at,wp_user_id',
        'order'  => 'last_name.asc,first_name.asc',
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load members.</p>';
    }

    if ($search !== '') {
        $rows = array_values(array_filter($rows, function ($row) use ($search) {
            $haystack = implode(' ', array(
                isset($row['first_name']) ? $row['first_name'] : '',
                isset($row['last_name']) ? $row['last_name'] : '',
                isset($row['email_address']) ? $row['email_address'] : '',
            ));
            return stripos($haystack, $search) !== false;
        }));
    }

    ob_start();
    ?>
    <style>
        .pba-members-wrap { max-width: 1200px; margin: 0 auto; }
        .pba-members-search { margin: 18px 0; }
        .pba-members-search input[type="text"] { width: 320px; max-width: 100%; padding: 9px 10px; }
        .pba-members-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-members-btn.secondary { background: #fff; color: #0d3b66; }
        .pba-members-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .pba-members-table th, .pba-members-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .pba-members-table th { background: #f3f3f3; }
        .pba-members-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-members-message.error { background: #f8e9e9; }
        .pba-members-muted { color: #666; font-size: 13px; }
    </style>

    <div class="pba-members-wrap">
        <!-- h2>Members</h2 -->
        <p>View and manage member records, roles, committee assignments, and invite status.</p>

        <?php echo pba_render_members_status_message(); ?>

        <form method="get" class="pba-members-search">
            <input type="text" name="member_search" value="<?php echo esc_attr($search); ?>" placeholder="Search members">
            <button type="submit" class="pba-members-btn secondary">Search</button>
        </form>

        <table class="pba-members-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Household</th>
                    <th>Roles</th>
                    <th>Committees</th>
                    <th>Linked WP User</th>
                    <th>Last Modified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="9">No members found.</td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
                        $roles = pba_get_active_role_names_for_person($person_id);
                        $committees = pba_get_committee_labels_for_person_in_app($person_id);
                        $household_label = pba_get_household_label(isset($row['household_id']) ? (int) $row['household_id'] : 0);
                        $wp_user_id = isset($row['wp_user_id']) && $row['wp_user_id'] !== null && $row['wp_user_id'] !== '' ? (string) $row['wp_user_id'] : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td><?php echo esc_html($row['email_address'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                            <td><?php echo esc_html($household_label); ?></td>
                            <td><?php echo esc_html(!empty($roles) ? implode(', ', $roles) : ''); ?></td>
                            <td><?php echo esc_html(!empty($committees) ? implode(', ', $committees) : ''); ?></td>
                            <td><?php echo esc_html($wp_user_id !== '' ? $wp_user_id : 'Not linked'); ?></td>
                            <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_at'] ?? '')); ?></td>
                            <td>
                                <a class="pba-members-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'member_view' => 'edit',
                                    'member_id' => $person_id,
                                ), home_url('/members/'))); ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_member_edit_view($member_id) {
    $member_rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id,email_verified,last_modified_at',
        'person_id' => 'eq.' . (int) $member_id,
        'limit'     => 1,
    ));

    if (is_wp_error($member_rows) || empty($member_rows[0])) {
        return '<p>Member not found.</p>';
    }

    $member = $member_rows[0];

    $households = pba_supabase_get('Household', array(
        'select' => 'household_id,pb_street_number,pb_street_name',
        'order'  => 'pb_street_name.asc,pb_street_number.asc',
    ));

    $roles = pba_supabase_get('Role', array(
        'select' => 'role_id,role_name',
        'order'  => 'role_name.asc',
    ));

    $committees = pba_supabase_get('Committee', array(
        'select' => 'committee_id,committee_name',
        'order'  => 'display_order.asc,committee_name.asc',
    ));

    $selected_role_ids = pba_get_role_ids_for_person_in_app($member_id);
    $selected_committees = pba_get_committees_for_person_in_app($member_id);
    $invite_data = pba_get_member_invite_data_by_person($member_id);

    $wp_user_id = isset($member['wp_user_id']) && $member['wp_user_id'] !== null && $member['wp_user_id'] !== '' ? (string) $member['wp_user_id'] : '';
    $email_verified = !empty($member['email_verified']) ? 'Yes' : 'No';

    ob_start();
    ?>
    <style>
        .pba-member-edit-wrap { max-width: 1100px; margin: 0 auto; }
        .pba-member-edit-form table { width: 100%; border-collapse: collapse; }
        .pba-member-edit-form th, .pba-member-edit-form td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }
        .pba-member-edit-form th { width: 220px; }
        .pba-member-edit-input, .pba-member-edit-select {
            width: 360px;
            max-width: 100%;
            padding: 8px 10px;
        }
        .pba-member-edit-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-member-edit-btn.secondary { background: #fff; color: #0d3b66; }
        .pba-members-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-members-message.error { background: #f8e9e9; }
        .pba-committee-box {
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
        }
        .pba-member-meta {
            margin-bottom: 16px;
            color: #666;
        }
        .pba-member-actions {
            margin: 18px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .pba-member-action-form {
            display: inline-block;
        }
    </style>

    <div class="pba-member-edit-wrap">
        <h2>Edit Member</h2>
        <p>
            <a class="pba-member-edit-btn secondary" href="<?php echo esc_url(home_url('/members/')); ?>">Back to Members</a>
        </p>

        <?php echo pba_render_members_status_message(); ?>

        <div class="pba-member-meta">
            <strong>Linked WP User ID:</strong> <?php echo esc_html($wp_user_id !== '' ? $wp_user_id : 'Not linked'); ?><br>
            <strong>Email Verified:</strong> <?php echo esc_html($email_verified); ?><br>
            <strong>Last Modified:</strong> <?php echo esc_html(pba_format_datetime_display($member['last_modified_at'] ?? '')); ?>
            <?php if (is_array($invite_data) && !empty($invite_data['expires_at_gmt'])) : ?>
                <br><strong>Invite Expires:</strong> <?php echo esc_html(date('m/d/y h:i A', (int) $invite_data['expires_at_gmt'])); ?>
            <?php endif; ?>
        </div>

        <div class="pba-member-actions">
            <?php $status = (string) ($member['status'] ?? ''); ?>

            <?php if ($status === 'Active') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                    <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                    <input type="hidden" name="action" value="pba_admin_disable_member">
                    <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                    <button type="submit" class="pba-member-edit-btn secondary">Disable</button>
                </form>
            <?php elseif ($status === 'Disabled') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                    <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                    <input type="hidden" name="action" value="pba_admin_enable_member">
                    <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                    <button type="submit" class="pba-member-edit-btn secondary">Enable</button>
                </form>
            <?php elseif ($status === 'Pending') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form" onsubmit="return confirm('Cancel this invite?');">
                    <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                    <input type="hidden" name="action" value="pba_admin_cancel_invite">
                    <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                    <button type="submit" class="pba-member-edit-btn secondary">Cancel Invite</button>
                </form>
            <?php elseif ($status === 'Expired') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                    <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                    <input type="hidden" name="action" value="pba_admin_resend_invite">
                    <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                    <button type="submit" class="pba-member-edit-btn secondary">Resend Invite</button>
                </form>
            <?php endif; ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-edit-form">
            <?php wp_nonce_field('pba_member_admin_action', 'pba_member_admin_nonce'); ?>
            <input type="hidden" name="action" value="pba_save_member_admin">
            <input type="hidden" name="member_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">

            <table>
                <tr>
                    <th><label for="household_id">Household</label></th>
                    <td>
                        <select name="household_id" id="household_id" class="pba-member-edit-select" required>
                            <option value="">Select household</option>
                            <?php if (!is_wp_error($households)) : ?>
                                <?php foreach ($households as $household) : ?>
                                    <?php $label = trim(($household['pb_street_number'] ?? '') . ' ' . ($household['pb_street_name'] ?? '')); ?>
                                    <option value="<?php echo esc_attr($household['household_id']); ?>" <?php selected((string) $member['household_id'], (string) $household['household_id']); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="first_name">First Name</label></th>
                    <td><input class="pba-member-edit-input" type="text" name="first_name" id="first_name" value="<?php echo esc_attr($member['first_name'] ?? ''); ?>" required></td>
                </tr>

                <tr>
                    <th><label for="last_name">Last Name</label></th>
                    <td><input class="pba-member-edit-input" type="text" name="last_name" id="last_name" value="<?php echo esc_attr($member['last_name'] ?? ''); ?>" required></td>
                </tr>

                <tr>
                    <th><label for="email_address">Email Address</label></th>
                    <td><input class="pba-member-edit-input" type="email" name="email_address" id="email_address" value="<?php echo esc_attr($member['email_address'] ?? ''); ?>"></td>
                </tr>

                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select name="status" id="status" class="pba-member-edit-select">
                            <option value="Unregistered" <?php selected($member['status'] ?? '', 'Unregistered'); ?>>Unregistered</option>
                            <option value="Pending" <?php selected($member['status'] ?? '', 'Pending'); ?>>Pending</option>
                            <option value="Active" <?php selected($member['status'] ?? '', 'Active'); ?>>Active</option>
                            <option value="Disabled" <?php selected($member['status'] ?? '', 'Disabled'); ?>>Disabled</option>
                            <option value="Expired" <?php selected($member['status'] ?? '', 'Expired'); ?>>Expired</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Broad Roles</th>
                    <td>
                        <?php if (!is_wp_error($roles)) : ?>
                            <?php foreach ($roles as $role) : ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="role_ids[]" value="<?php echo esc_attr($role['role_id']); ?>" <?php checked(in_array((int) $role['role_id'], $selected_role_ids, true)); ?>>
                                    <?php echo esc_html($role['role_name']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th>Committees</th>
                    <td>
                        <?php if (!is_wp_error($committees)) : ?>
                            <?php foreach ($committees as $committee) : ?>
                                <?php
                                $committee_id = (int) $committee['committee_id'];
                                $selected = isset($selected_committees[$committee_id]);
                                $committee_role = $selected ? $selected_committees[$committee_id]['committee_role'] : '';
                                ?>
                                <div class="pba-committee-box">
                                    <label>
                                        <input type="checkbox" name="committee_ids[]" value="<?php echo esc_attr($committee_id); ?>" <?php checked($selected); ?>>
                                        <?php echo esc_html($committee['committee_name']); ?>
                                    </label>
                                    <div style="margin-top:6px;">
                                        <input
                                            class="pba-member-edit-input"
                                            type="text"
                                            name="committee_roles[<?php echo esc_attr($committee_id); ?>]"
                                            value="<?php echo esc_attr($committee_role); ?>"
                                            placeholder="Committee role, e.g. Chair, Treasurer, Member"
                                        >
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p style="margin-top:18px;">
                <button type="submit" class="pba-member-edit-btn">Save Member</button>
                <a class="pba-member-edit-btn secondary" href="<?php echo esc_url(home_url('/members/')); ?>">Cancel</a>
            </p>
        </form>
    </div>
    <?php

    return ob_get_clean();
}

function pba_get_committee_labels_for_person_in_app($person_id) {
    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'committee_id,committee_role,is_active',
        'person_id' => 'eq.' . (int) $person_id,
        'is_active' => 'eq.true',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        return array();
    }

    $labels = array();

    foreach ($rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        if ($committee_id < 1) {
            continue;
        }

        $committee_name = pba_get_committee_name($committee_id);
        if ($committee_name === '') {
            continue;
        }

        $label = $committee_name;
        if (!empty($row['committee_role'])) {
            $label .= ' (' . $row['committee_role'] . ')';
        }

        $labels[] = $label;
    }

    return $labels;
}

function pba_get_role_ids_for_person_in_app($person_id) {
    $rows = pba_supabase_get('Person_to_Role', array(
        'select'    => 'role_id,is_active',
        'person_id' => 'eq.' . (int) $person_id,
        'is_active' => 'eq.true',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        return array();
    }

    return array_map(function ($row) {
        return (int) $row['role_id'];
    }, $rows);
}

function pba_get_committees_for_person_in_app($person_id) {
    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'committee_id,committee_role,is_active',
        'person_id' => 'eq.' . (int) $person_id,
        'is_active' => 'eq.true',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        return array();
    }

    $result = array();

    foreach ($rows as $row) {
        $result[(int) $row['committee_id']] = array(
            'committee_role' => $row['committee_role'] ?? '',
        );
    }

    return $result;
}
<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_render_admin_members_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 403);
    }

    pba_handle_admin_member_form_submit();

    $action    = isset($_GET['member_action']) ? sanitize_text_field(wp_unslash($_GET['member_action'])) : 'list';
    $member_id = isset($_GET['member_id']) ? absint($_GET['member_id']) : 0;
    $status    = isset($_GET['pba_admin_status']) ? sanitize_text_field(wp_unslash($_GET['pba_admin_status'])) : '';

    echo '<div class="wrap">';
    echo '<h1>Members</h1>';

    if ($status !== '') {
        $notice = pba_admin_members_get_notice($status);

        echo '<div class="notice ' . esc_attr($notice['class']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    if ($action === 'edit') {
        pba_render_admin_member_edit_form($member_id);
    } elseif ($action === 'add') {
        pba_render_admin_member_edit_form(0);
    } else {
        pba_render_admin_members_list();
    }

    echo '</div>';
}

function pba_admin_members_get_notice($status) {
    $notices = array(
        'member_saved' => array(
            'class'   => 'notice-success',
            'message' => 'Member saved.',
        ),
        'invalid_member_input' => array(
            'class'   => 'notice-error',
            'message' => 'Please provide a household, first name, and last name.',
        ),
        'invalid_member_email' => array(
            'class'   => 'notice-error',
            'message' => 'Please provide a valid email address.',
        ),
        'member_update_failed' => array(
            'class'   => 'notice-error',
            'message' => 'Member update failed.',
        ),
        'member_create_failed' => array(
            'class'   => 'notice-error',
            'message' => 'Member creation failed.',
        ),
        'member_load_failed' => array(
            'class'   => 'notice-error',
            'message' => 'Unable to load the selected member.',
        ),
        'linked_wordpress_user_missing' => array(
            'class'   => 'notice-error',
            'message' => 'The member is linked to a WordPress user that could not be found.',
        ),
        'member_email_in_use' => array(
            'class'   => 'notice-error',
            'message' => 'That email address is already associated with another WordPress user.',
        ),
        'wordpress_email_update_failed' => array(
            'class'   => 'notice-error',
            'message' => 'The member was saved, but the linked WordPress user email could not be updated.',
        ),
    );

    if (isset($notices[$status])) {
        return $notices[$status];
    }

    return array(
        'class'   => 'notice-success',
        'message' => str_replace('_', ' ', $status),
    );
}

function pba_admin_members_redirect($status) {
    wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=' . rawurlencode($status)));
    exit;
}

function pba_handle_admin_member_form_submit() {
    if (
        !isset($_POST['pba_admin_member_form_submit']) ||
        !isset($_POST['pba_admin_member_nonce'])
    ) {
        return;
    }

    if (!wp_verify_nonce($_POST['pba_admin_member_nonce'], 'pba_admin_member_form')) {
        wp_die('Invalid nonce', 403);
    }

    $member_id     = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $household_id  = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $first_name    = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name     = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $status        = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Unregistered';

    $role_ids        = isset($_POST['role_ids']) ? array_map('absint', (array) $_POST['role_ids']) : array();
    $committee_ids   = isset($_POST['committee_ids']) ? array_map('absint', (array) $_POST['committee_ids']) : array();
    $committee_roles = isset($_POST['committee_roles']) ? (array) $_POST['committee_roles'] : array();

    if ($household_id < 1 || $first_name === '' || $last_name === '') {
        wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=invalid_member_input'));
        exit;
    }

    if ($email_address !== '' && !is_email($email_address)) {
        wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=invalid_member_email'));
        exit;
    }

    $existing_person = null;

    if ($member_id > 0) {
        $existing_rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,email_address,wp_user_id',
            'person_id' => 'eq.' . $member_id,
            'limit'     => 1,
        ));

        if (is_wp_error($existing_rows) || empty($existing_rows[0])) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=member_load_failed'));
            exit;
        }

        $existing_person = $existing_rows[0];

        $updated = pba_supabase_update(
            'Person',
            array(
                'household_id'      => $household_id,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email_address'     => $email_address !== '' ? $email_address : null,
                'status'            => $status,
                'last_modified_at'  => gmdate('c'),
            ),
            array(
                'person_id' => 'eq.' . $member_id,
            )
        );

        if (is_wp_error($updated)) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=member_update_failed'));
            exit;
        }

        $wp_sync_result = pba_admin_member_sync_wp_email_after_person_email_change(
            $member_id,
            $existing_person,
            $email_address
        );

        if (is_wp_error($wp_sync_result)) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=' . rawurlencode($wp_sync_result->get_error_code())));
            exit;
        }
    } else {
        $inserted = pba_supabase_insert('Person', array(
            'household_id'         => $household_id,
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'email_address'        => $email_address !== '' ? $email_address : null,
            'status'               => $status,
            'email_verified'       => 0,
            'wp_user_id'           => null,
            'invited_by_person_id' => null,
            'last_modified_at'     => gmdate('c'),
        ));

        if (is_wp_error($inserted) || empty($inserted['person_id'])) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=member_create_failed'));
            exit;
        }

        $member_id = (int) $inserted['person_id'];
    }

    pba_admin_member_replace_roles($member_id, $role_ids);
    pba_admin_member_replace_committees($member_id, $committee_ids, $committee_roles);

    wp_safe_redirect(admin_url('admin.php?page=pba-admin-members&pba_admin_status=member_saved'));
    exit;
}

function pba_admin_member_get_person_for_save($member_id) {
    $rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,email_address,wp_user_id',
        'person_id' => 'eq.' . (int) $member_id,
        'limit'     => 1,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (empty($rows[0])) {
        return new WP_Error('member_load_failed', 'Unable to load member.');
    }

    return $rows[0];
}

function pba_admin_member_validate_wp_user_email_sync($person, $email_address) {
    $wp_user_id = isset($person['wp_user_id']) ? absint($person['wp_user_id']) : 0;

    if ($wp_user_id < 1 || $email_address === '') {
        return true;
    }

    $wp_user = get_user_by('id', $wp_user_id);

    if (!$wp_user) {
        return new WP_Error('linked_wordpress_user_missing', 'Linked WordPress user was not found.');
    }

    $email_owner = get_user_by('email', $email_address);

    if ($email_owner && (int) $email_owner->ID !== $wp_user_id) {
        return new WP_Error('member_email_in_use', 'Email address is already associated with another WordPress user.');
    }

    return true;
}

function pba_admin_member_sync_wp_user_email($person, $email_address) {
    $wp_user_id = isset($person['wp_user_id']) ? absint($person['wp_user_id']) : 0;

    if ($wp_user_id < 1 || $email_address === '') {
        return true;
    }

    $wp_user = get_user_by('id', $wp_user_id);

    if (!$wp_user) {
        return new WP_Error('linked_wordpress_user_missing', 'Linked WordPress user was not found.');
    }

    if (strtolower((string) $wp_user->user_email) === strtolower((string) $email_address)) {
        return true;
    }

    $email_owner = get_user_by('email', $email_address);

    if ($email_owner && (int) $email_owner->ID !== $wp_user_id) {
        return new WP_Error('member_email_in_use', 'Email address is already associated with another WordPress user.');
    }

    $updated = wp_update_user(array(
        'ID'         => $wp_user_id,
        'user_email' => $email_address,
    ));

    if (is_wp_error($updated)) {
        return new WP_Error(
            'wordpress_email_update_failed',
            $updated->get_error_message()
        );
    }

    return true;
}

function pba_render_admin_members_list() {
    $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';

    $query_args = array(
        'select' => 'person_id,first_name,last_name,email_address,status,household_id,last_modified_at',
        'order'  => 'last_name.asc,first_name.asc',
    );

    $rows = pba_supabase_get('Person', $query_args);

    if (is_wp_error($rows)) {
        echo '<div class="notice notice-error"><p>Unable to load members.</p></div>';
        return;
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

    ?>
    <p>
        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-members&member_action=add')); ?>">Add Member</a>
    </p>

    <form method="get" action="">
        <input type="hidden" name="page" value="pba-admin-members">
        <p>
            <input type="text" name="member_search" value="<?php echo esc_attr($search); ?>" placeholder="Search members">
            <button class="button">Search</button>
        </p>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Household</th>
                <th>Last Modified</th>
                <th>Roles</th>
                <th>Committees</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="8">No members found.</td></tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $person_id = (int) $row['person_id'];
                    $roles = pba_admin_get_role_names_for_person($person_id);
                    $committees = pba_admin_get_committee_labels_for_person($person_id);
                    ?>
                    <tr>
                        <td><?php echo esc_html(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                        <td><?php echo esc_html($row['email_address'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['household_id'] ?? ''); ?></td>
                        <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_at'] ?? '')); ?></td>
                        <td><?php echo esc_html(implode(', ', $roles)); ?></td>
                        <td><?php echo esc_html(implode(', ', $committees)); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-members&member_action=edit&member_id=' . $person_id)); ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function pba_render_admin_member_edit_form($member_id = 0) {
    $member = array(
        'person_id'      => 0,
        'household_id'   => '',
        'first_name'     => '',
        'last_name'      => '',
        'email_address'  => '',
        'status'         => 'Unregistered',
        'wp_user_id'     => null,
    );

    if ($member_id > 0) {
        $rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id',
            'person_id' => 'eq.' . $member_id,
            'limit'     => 1,
        ));

        if (!is_wp_error($rows) && !empty($rows[0])) {
            $member = $rows[0];
        }
    }

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

    $selected_role_ids = $member_id > 0 ? pba_admin_get_role_ids_for_person($member_id) : array();
    $selected_committees = $member_id > 0 ? pba_admin_get_committees_for_person($member_id) : array();

    ?>
    <h2><?php echo $member_id > 0 ? 'Edit Member' : 'Add Member'; ?></h2>

    <form method="post">
        <?php wp_nonce_field('pba_admin_member_form', 'pba_admin_member_nonce'); ?>
        <input type="hidden" name="pba_admin_member_form_submit" value="1">
        <input type="hidden" name="member_id" value="<?php echo esc_attr($member['person_id']); ?>">

        <table class="form-table">
            <tr>
                <th><label for="household_id">Household</label></th>
                <td>
                    <select name="household_id" id="household_id" required>
                        <option value="">Select household</option>
                        <?php if (!is_wp_error($households)) : ?>
                            <?php foreach ($households as $household) : ?>
                                <?php
                                $label = trim(($household['pb_street_number'] ?? '') . ' ' . ($household['pb_street_name'] ?? ''));
                                ?>
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
                <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($member['first_name']); ?>" class="regular-text" required></td>
            </tr>

            <tr>
                <th><label for="last_name">Last Name</label></th>
                <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($member['last_name']); ?>" class="regular-text" required></td>
            </tr>

            <tr>
                <th><label for="email_address">Email Address</label></th>
                <td>
                    <input type="email" name="email_address" id="email_address" value="<?php echo esc_attr($member['email_address']); ?>" class="regular-text">
                    <?php if (!empty($member['wp_user_id'])) : ?>
                        <p class="description">
                            This member is linked to WordPress user ID <?php echo esc_html((string) $member['wp_user_id']); ?>.
                            Saving an email change here will also update the linked WordPress user email so password reset works with the corrected address.
                        </p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label for="status">Status</label></th>
                <td>
                    <select name="status" id="status">
                        <option value="Unregistered" <?php selected($member['status'], 'Unregistered'); ?>>Unregistered</option>
                        <option value="Pending" <?php selected($member['status'], 'Pending'); ?>>Pending</option>
                        <option value="Active" <?php selected($member['status'], 'Active'); ?>>Active</option>
                        <option value="Disabled" <?php selected($member['status'], 'Disabled'); ?>>Disabled</option>
                        <option value="Expired" <?php selected($member['status'], 'Expired'); ?>>Expired</option>
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
                            <div style="margin-bottom:10px;padding:8px;border:1px solid #ddd;">
                                <label>
                                    <input type="checkbox" name="committee_ids[]" value="<?php echo esc_attr($committee_id); ?>" <?php checked($selected); ?>>
                                    <?php echo esc_html($committee['committee_name']); ?>
                                </label>
                                <div style="margin-top:6px;">
                                    <input
                                        type="text"
                                        name="committee_roles[<?php echo esc_attr($committee_id); ?>]"
                                        value="<?php echo esc_attr($committee_role); ?>"
                                        placeholder="Committee role, e.g. Chair, Treasurer, Member"
                                        class="regular-text"
                                    >
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <p>
            <button class="button button-primary" type="submit">Save Member</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-members')); ?>">Cancel</a>
        </p>
    </form>
    <?php
}

function pba_admin_get_role_ids_for_person($person_id) {
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

function pba_admin_get_role_names_for_person($person_id) {
    $rows = pba_supabase_get('Person_to_Role', array(
        'select'    => 'role_id,is_active',
        'person_id' => 'eq.' . (int) $person_id,
        'is_active' => 'eq.true',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        return array();
    }

    $names = array();

    foreach ($rows as $row) {
        $role_rows = pba_supabase_get('Role', array(
            'select'  => 'role_name',
            'role_id' => 'eq.' . (int) $row['role_id'],
            'limit'   => 1,
        ));

        if (!is_wp_error($role_rows) && !empty($role_rows[0]['role_name'])) {
            $names[] = $role_rows[0]['role_name'];
        }
    }

    return $names;
}

function pba_admin_get_committees_for_person($person_id) {
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

function pba_admin_get_committee_labels_for_person($person_id) {
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
        $committee_rows = pba_supabase_get('Committee', array(
            'select'       => 'committee_name',
            'committee_id' => 'eq.' . (int) $row['committee_id'],
            'limit'        => 1,
        ));

        if (!is_wp_error($committee_rows) && !empty($committee_rows[0]['committee_name'])) {
            $label = $committee_rows[0]['committee_name'];

            if (!empty($row['committee_role'])) {
                $label .= ' (' . $row['committee_role'] . ')';
            }

            $labels[] = $label;
        }
    }

    return $labels;
}

function pba_admin_member_replace_roles($member_id, $role_ids) {
    $existing = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_to_role_id',
        'person_id' => 'eq.' . (int) $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            pba_supabase_delete('Person_to_Role', array(
                'person_to_role_id' => 'eq.' . (int) $row['person_to_role_id'],
            ));
        }
    }

    foreach ($role_ids as $role_id) {
        pba_supabase_insert('Person_to_Role', array(
            'person_id'        => (int) $member_id,
            'role_id'          => (int) $role_id,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'last_modified_at' => gmdate('c'),
        ));
    }
}

function pba_admin_member_replace_committees($member_id, $committee_ids, $committee_roles) {
    $existing = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_to_committee_id',
        'person_id' => 'eq.' . (int) $member_id,
    ));

    if (!is_wp_error($existing) && !empty($existing)) {
        foreach ($existing as $row) {
            pba_supabase_delete('Person_to_Committee', array(
                'person_to_committee_id' => 'eq.' . (int) $row['person_to_committee_id'],
            ));
        }
    }

    foreach ($committee_ids as $committee_id) {
        $committee_role = isset($committee_roles[$committee_id]) ? sanitize_text_field(wp_unslash($committee_roles[$committee_id])) : '';

        pba_supabase_insert('Person_to_Committee', array(
            'person_id'        => (int) $member_id,
            'committee_id'     => (int) $committee_id,
            'committee_role'   => $committee_role !== '' ? $committee_role : null,
            'start_date'       => gmdate('Y-m-d'),
            'is_active'        => true,
            'display_order'    => null,
            'last_modified_at' => gmdate('c'),
        ));
    }
}
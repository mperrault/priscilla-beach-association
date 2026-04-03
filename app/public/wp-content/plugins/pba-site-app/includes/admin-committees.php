<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_render_admin_committees_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 403);
    }

    pba_handle_admin_committee_form_submit();

    $action       = isset($_GET['committee_action']) ? sanitize_text_field(wp_unslash($_GET['committee_action'])) : 'list';
    $committee_id = isset($_GET['committee_id']) ? absint($_GET['committee_id']) : 0;
    $status       = isset($_GET['pba_admin_status']) ? sanitize_text_field(wp_unslash($_GET['pba_admin_status'])) : '';

    echo '<div class="wrap">';
    echo '<h1>Committees</h1>';

    if ($status !== '') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(str_replace('_', ' ', $status)) . '</p></div>';
    }

    if ($action === 'edit') {
        pba_render_admin_committee_edit_form($committee_id);
    } elseif ($action === 'add') {
        pba_render_admin_committee_edit_form(0);
    } else {
        pba_render_admin_committees_list();
    }

    echo '</div>';
}

function pba_handle_admin_committee_form_submit() {
    if (
        !isset($_POST['pba_admin_committee_form_submit']) ||
        !isset($_POST['pba_admin_committee_nonce'])
    ) {
        return;
    }

    if (!wp_verify_nonce($_POST['pba_admin_committee_nonce'], 'pba_admin_committee_form')) {
        wp_die('Invalid nonce', 403);
    }

    $committee_id          = isset($_POST['committee_id']) ? absint($_POST['committee_id']) : 0;
    $committee_name        = isset($_POST['committee_name']) ? sanitize_text_field(wp_unslash($_POST['committee_name'])) : '';
    $committee_description = isset($_POST['committee_description']) ? sanitize_textarea_field(wp_unslash($_POST['committee_description'])) : '';
    $status                = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'Active';
    $display_order         = isset($_POST['display_order']) && $_POST['display_order'] !== '' ? intval($_POST['display_order']) : null;
    $notes                 = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

    if ($committee_name === '') {
        wp_safe_redirect(admin_url('admin.php?page=pba-admin-committees&pba_admin_status=invalid_committee_name'));
        exit;
    }

    $payload = array(
        'committee_name'        => $committee_name,
        'committee_description' => $committee_description !== '' ? $committee_description : null,
        'status'                => $status,
        'display_order'         => $display_order,
        'notes'                 => $notes !== '' ? $notes : null,
        'last_modified_at'      => gmdate('c'),
    );

    if ($committee_id > 0) {
        $updated = pba_supabase_update(
            'Committee',
            $payload,
            array(
                'committee_id' => 'eq.' . $committee_id,
            )
        );

        if (is_wp_error($updated)) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-committees&pba_admin_status=committee_update_failed'));
            exit;
        }
    } else {
        $inserted = pba_supabase_insert('Committee', $payload);

        if (is_wp_error($inserted)) {
            wp_safe_redirect(admin_url('admin.php?page=pba-admin-committees&pba_admin_status=committee_create_failed'));
            exit;
        }
    }

    wp_safe_redirect(admin_url('admin.php?page=pba-admin-committees&pba_admin_status=committee_saved'));
    exit;
}

function pba_render_admin_committees_list() {
    $rows = pba_supabase_get('Committee', array(
        'select' => 'committee_id,committee_name,committee_description,status,display_order,last_modified_at',
        'order'  => 'display_order.asc,committee_name.asc',
    ));

    if (is_wp_error($rows)) {
        echo '<div class="notice notice-error"><p>Unable to load committees.</p></div>';
        return;
    }

    ?>
    <p>
        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-committees&committee_action=add')); ?>">Add Committee</a>
    </p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Display Order</th>
                <th>Last Modified</th>
                <th>Members</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="7">No committees found.</td></tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['committee_name'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['committee_description'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['display_order'] ?? ''); ?></td>
                        <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_at'] ?? '')); ?></td>
                        <td><?php echo esc_html(pba_admin_get_committee_member_count((int) $row['committee_id'])); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-committees&committee_action=edit&committee_id=' . (int) $row['committee_id'])); ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function pba_render_admin_committee_edit_form($committee_id = 0) {
    $committee = array(
        'committee_id'          => 0,
        'committee_name'        => '',
        'committee_description' => '',
        'status'                => 'Active',
        'display_order'         => '',
        'notes'                 => '',
    );

    if ($committee_id > 0) {
        $rows = pba_supabase_get('Committee', array(
            'select'       => 'committee_id,committee_name,committee_description,status,display_order,notes',
            'committee_id' => 'eq.' . $committee_id,
            'limit'        => 1,
        ));

        if (!is_wp_error($rows) && !empty($rows[0])) {
            $committee = $rows[0];
        }
    }

    ?>
    <h2><?php echo $committee_id > 0 ? 'Edit Committee' : 'Add Committee'; ?></h2>

    <form method="post">
        <?php wp_nonce_field('pba_admin_committee_form', 'pba_admin_committee_nonce'); ?>
        <input type="hidden" name="pba_admin_committee_form_submit" value="1">
        <input type="hidden" name="committee_id" value="<?php echo esc_attr($committee['committee_id']); ?>">

        <table class="form-table">
            <tr>
                <th><label for="committee_name">Committee Name</label></th>
                <td><input type="text" name="committee_name" id="committee_name" value="<?php echo esc_attr($committee['committee_name']); ?>" class="regular-text" required></td>
            </tr>

            <tr>
                <th><label for="committee_description">Description</label></th>
                <td><textarea name="committee_description" id="committee_description" class="large-text" rows="4"><?php echo esc_textarea($committee['committee_description']); ?></textarea></td>
            </tr>

            <tr>
                <th><label for="status">Status</label></th>
                <td>
                    <select name="status" id="status">
                        <option value="Active" <?php selected($committee['status'], 'Active'); ?>>Active</option>
                        <option value="Inactive" <?php selected($committee['status'], 'Inactive'); ?>>Inactive</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="display_order">Display Order</label></th>
                <td><input type="number" name="display_order" id="display_order" value="<?php echo esc_attr($committee['display_order']); ?>" class="small-text"></td>
            </tr>

            <tr>
                <th><label for="notes">Notes</label></th>
                <td><textarea name="notes" id="notes" class="large-text" rows="4"><?php echo esc_textarea($committee['notes']); ?></textarea></td>
            </tr>
        </table>

        <p>
            <button class="button button-primary" type="submit">Save Committee</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-committees')); ?>">Cancel</a>
        </p>
    </form>
    <?php
}

function pba_admin_get_committee_member_count($committee_id) {
    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'       => 'person_to_committee_id',
        'committee_id' => 'eq.' . (int) $committee_id,
        'is_active'    => 'eq.true',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return 0;
    }

    return count($rows);
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_committees_shortcode');

function pba_register_committees_shortcode() {
    add_shortcode('pba_committees', 'pba_render_committees_shortcode');
}

function pba_render_committees_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_person_has_role('PBAAdmin')) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $view = isset($_GET['committee_view']) ? sanitize_text_field(wp_unslash($_GET['committee_view'])) : 'list';
    $committee_id = isset($_GET['committee_id']) ? absint($_GET['committee_id']) : 0;

    if ($view === 'edit' && $committee_id > 0) {
        return pba_render_committee_edit_view($committee_id);
    }

    return pba_render_committees_list_view();
}

function pba_render_committees_status_message() {
    $status = isset($_GET['pba_committees_status']) ? sanitize_text_field(wp_unslash($_GET['pba_committees_status'])) : '';

    if ($status === '') {
        return '';
    }

    $message = str_replace('_', ' ', $status);
    $class = ($status === 'committee_saved') ? 'pba-committees-message' : 'pba-committees-message error';

    return '<div class="' . esc_attr($class) . '">' . esc_html(ucfirst($message)) . '</div>';
}

function pba_render_committees_list_view() {
    $rows = pba_supabase_get('Committee', array(
        'select' => 'committee_id,committee_name,committee_description,status,display_order,last_modified_at',
        'order'  => 'display_order.asc,committee_name.asc',
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load committees.</p>';
    }

    ob_start();
    ?>
    <style>
        .pba-committees-wrap { max-width: 1200px; margin: 0 auto; }
        .pba-committees-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .pba-committees-table th,
        .pba-committees-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .pba-committees-table th {
            background: #f3f3f3;
        }
        .pba-committees-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-committees-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }
        .pba-committees-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-committees-message.error {
            background: #f8e9e9;
        }
    </style>

    <div class="pba-committees-wrap">
        <!-- h2>Committees</h2 -->
        <p>View and manage committees and active membership counts.</p>

        <?php echo pba_render_committees_status_message(); ?>

        <table class="pba-committees-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Display Order</th>
                    <th>Members</th>
                    <th>Last Modified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="7">No committees found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
                        $member_count = pba_get_committee_member_count_in_app($committee_id);
                        ?>
                        <tr>
                            <td><?php echo esc_html($row['committee_name'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['committee_description'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['display_order'] ?? ''); ?></td>
                            <td><?php echo esc_html($member_count); ?></td>
                            <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_at'] ?? '')); ?></td>
                            <td>
                                <a class="pba-committees-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'committee_view' => 'edit',
                                    'committee_id' => $committee_id,
                                ), home_url('/committees/'))); ?>">Edit</a>
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

function pba_render_committee_edit_view($committee_id) {
    $rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_id,committee_name,committee_description,status,display_order,notes,last_modified_at',
        'committee_id' => 'eq.' . (int) $committee_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return '<p>Committee not found.</p>';
    }

    $committee = $rows[0];
    $member_count = pba_get_committee_member_count_in_app($committee_id);

    ob_start();
    ?>
    <style>
        .pba-committee-edit-wrap { max-width: 1000px; margin: 0 auto; }
        .pba-committee-edit-form table { width: 100%; border-collapse: collapse; }
        .pba-committee-edit-form th,
        .pba-committee-edit-form td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }
        .pba-committee-edit-form th { width: 220px; }
        .pba-committee-edit-input,
        .pba-committee-edit-select,
        .pba-committee-edit-textarea {
            width: 420px;
            max-width: 100%;
            padding: 8px 10px;
        }
        .pba-committee-edit-textarea {
            min-height: 110px;
        }
        .pba-committees-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-committees-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }
        .pba-committees-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-committees-message.error {
            background: #f8e9e9;
        }
        .pba-committee-meta {
            color: #666;
            margin-bottom: 16px;
        }
    </style>

    <div class="pba-committee-edit-wrap">
        <h2>Edit Committee</h2>
        <p>
            <a class="pba-committees-btn secondary" href="<?php echo esc_url(home_url('/committees/')); ?>">Back to Committees</a>
        </p>

        <?php echo pba_render_committees_status_message(); ?>

        <div class="pba-committee-meta">
            Active members: <?php echo esc_html($member_count); ?><br>
            Last modified: <?php echo esc_html(pba_format_datetime_display($committee['last_modified_at'] ?? '')); ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-committee-edit-form">
            <?php wp_nonce_field('pba_committee_admin_action', 'pba_committee_admin_nonce'); ?>
            <input type="hidden" name="action" value="pba_save_committee_admin">
            <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee['committee_id']); ?>">

            <table>
                <tr>
                    <th><label for="committee_name">Committee Name</label></th>
                    <td><input class="pba-committee-edit-input" type="text" name="committee_name" id="committee_name" value="<?php echo esc_attr($committee['committee_name'] ?? ''); ?>" required></td>
                </tr>

                <tr>
                    <th><label for="committee_description">Description</label></th>
                    <td><textarea class="pba-committee-edit-textarea" name="committee_description" id="committee_description"><?php echo esc_textarea($committee['committee_description'] ?? ''); ?></textarea></td>
                </tr>

                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select name="status" id="status" class="pba-committee-edit-select">
                            <option value="Active" <?php selected($committee['status'] ?? '', 'Active'); ?>>Active</option>
                            <option value="Inactive" <?php selected($committee['status'] ?? '', 'Inactive'); ?>>Inactive</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="display_order">Display Order</label></th>
                    <td><input class="pba-committee-edit-input" type="number" name="display_order" id="display_order" value="<?php echo esc_attr($committee['display_order'] ?? ''); ?>"></td>
                </tr>

                <tr>
                    <th><label for="notes">Notes</label></th>
                    <td><textarea class="pba-committee-edit-textarea" name="notes" id="notes"><?php echo esc_textarea($committee['notes'] ?? ''); ?></textarea></td>
                </tr>
            </table>

            <p style="margin-top:18px;">
                <button type="submit" class="pba-committees-btn">Save Committee</button>
                <a class="pba-committees-btn secondary" href="<?php echo esc_url(home_url('/committees/')); ?>">Cancel</a>
            </p>
        </form>
    </div>
    <?php

    return ob_get_clean();
}

function pba_get_committee_member_count_in_app($committee_id) {
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
<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pba_register_committees_shortcode')) {
    add_action('init', 'pba_register_committees_shortcode');

    function pba_register_committees_shortcode() {
        add_shortcode('pba_committees', 'pba_render_committees_shortcode');
    }
}

if (!function_exists('pba_render_committees_shortcode')) {
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
}

if (!function_exists('pba_render_committees_shared_styles')) {
    function pba_render_committees_shared_styles() {
        ob_start();
        ?>
        <style>
            .pba-committees-page {
                max-width: 1400px;
                margin: 0 auto;
                color: #16324f;
            }

            .pba-committees-shell,
            .pba-committee-edit-shell {
                background: #ffffff;
                border: 1px solid #d9e2ec;
                border-radius: 16px;
                box-shadow: 0 10px 24px rgba(13, 59, 102, 0.08);
                overflow: hidden;
            }

            .pba-committees-header,
            .pba-committee-edit-header {
                padding: 24px 24px 12px;
                border-bottom: 1px solid #e6edf5;
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            }

            .pba-committees-title,
            .pba-committee-edit-title {
                margin: 0;
                font-size: 32px;
                line-height: 1.15;
                color: #0d3b66;
            }

            .pba-committees-subtitle,
            .pba-committee-edit-subtitle {
                margin: 10px 0 0;
                color: #4b5f75;
                font-size: 15px;
                line-height: 1.55;
                max-width: 900px;
            }

            .pba-committees-toolbar,
            .pba-committee-edit-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                padding: 18px 24px;
                border-bottom: 1px solid #e6edf5;
                background: #f8fbff;
            }

            .pba-committees-content,
            .pba-committee-edit-content {
                padding: 24px;
            }

            .pba-committees-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 42px;
                padding: 10px 16px;
                border: 1px solid #0d3b66;
                border-radius: 10px;
                background: #0d3b66;
                color: #ffffff;
                text-decoration: none;
                font-weight: 600;
                line-height: 1.2;
                cursor: pointer;
                transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
            }

            .pba-committees-btn:hover,
            .pba-committees-btn:focus {
                background: #114a7f;
                border-color: #114a7f;
                color: #ffffff;
                text-decoration: none;
                transform: translateY(-1px);
            }

            .pba-committees-btn.secondary {
                background: #ffffff;
                color: #0d3b66;
                border-color: #bfd0e0;
            }

            .pba-committees-btn.secondary:hover,
            .pba-committees-btn.secondary:focus {
                background: #f4f8fb;
                color: #0d3b66;
                border-color: #9bb6cf;
            }

            .pba-committees-message {
                margin: 0 0 18px;
                padding: 13px 16px;
                border-radius: 12px;
                border: 1px solid #cfe3d1;
                background: #eef8ef;
                color: #1f5130;
                font-weight: 600;
            }

            .pba-committees-message.error {
                background: #fdf0f0;
                border-color: #efcaca;
                color: #8a2f2f;
            }

            .pba-committees-muted {
                color: #6b7c8f;
            }

            .pba-committees-table-wrap {
                width: 100%;
                overflow-x: auto;
                border: 1px solid #d9e2ec;
                border-radius: 14px;
                background: #ffffff;
            }

            .pba-committees-table {
                width: 100%;
                min-width: 980px;
                border-collapse: separate;
                border-spacing: 0;
            }

            .pba-committees-table th,
            .pba-committees-table td {
                padding: 14px 12px;
                text-align: left;
                vertical-align: top;
                border-bottom: 1px solid #e6edf5;
            }

            .pba-committees-table th {
                position: sticky;
                top: 0;
                background: #eef4fa;
                color: #0d3b66;
                font-size: 13px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                white-space: nowrap;
                z-index: 1;
            }

            .pba-committees-table tbody tr:nth-child(even) td {
                background: #fbfdff;
            }

            .pba-committees-table tbody tr:hover td {
                background: #f4f8fc;
            }

            .pba-committees-table tbody tr:last-child td {
                border-bottom: none;
            }

            .pba-committee-name {
                font-weight: 700;
                color: #16324f;
            }

            .pba-committee-roster {
                display: flex;
                flex-direction: column;
                gap: 6px;
                min-width: 180px;
            }

            .pba-committee-roster-entry {
                margin: 0;
                padding: 8px 10px;
                background: #eef3f8;
                border-radius: 10px;
                color: #16324f;
                line-height: 1.4;
            }

            .pba-committee-status-pill {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                white-space: nowrap;
                background: #eaf6ec;
                color: #25613a;
            }

            .pba-committee-status-pill.inactive {
                background: #f3f4f6;
                color: #5b6470;
            }

            .pba-committee-meta {
                display: grid;
                gap: 10px;
                margin-bottom: 20px;
                padding: 16px 18px;
                border: 1px solid #d9e2ec;
                border-radius: 14px;
                background: #f8fbff;
                color: #4b5f75;
            }

            .pba-committee-meta strong {
                color: #16324f;
            }

            .pba-committee-edit-form-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border: 1px solid #d9e2ec;
                border-radius: 14px;
                overflow: hidden;
                background: #ffffff;
            }

            .pba-committee-edit-form-table th,
            .pba-committee-edit-form-table td {
                padding: 16px 18px;
                text-align: left;
                vertical-align: top;
                border-bottom: 1px solid #e6edf5;
            }

            .pba-committee-edit-form-table tr:last-child th,
            .pba-committee-edit-form-table tr:last-child td {
                border-bottom: none;
            }

            .pba-committee-edit-form-table th {
                width: 240px;
                background: #f8fbff;
                color: #0d3b66;
                font-weight: 700;
            }

            .pba-committee-edit-input,
            .pba-committee-edit-select,
            .pba-committee-edit-textarea {
                width: 100%;
                max-width: 520px;
                padding: 10px 12px;
                border: 1px solid #bfd0e0;
                border-radius: 10px;
                background: #ffffff;
                color: #16324f;
                font: inherit;
                box-sizing: border-box;
            }

            .pba-committee-edit-input:focus,
            .pba-committee-edit-select:focus,
            .pba-committee-edit-textarea:focus {
                outline: none;
                border-color: #0d3b66;
                box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.12);
            }

            .pba-committee-edit-textarea {
                min-height: 120px;
                resize: vertical;
            }

            .pba-committee-edit-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 20px;
            }

            @media (max-width: 900px) {
                .pba-committees-header,
                .pba-committee-edit-header,
                .pba-committees-toolbar,
                .pba-committee-edit-toolbar,
                .pba-committees-content,
                .pba-committee-edit-content {
                    padding-left: 16px;
                    padding-right: 16px;
                }

                .pba-committees-title,
                .pba-committee-edit-title {
                    font-size: 28px;
                }

                .pba-committee-edit-form-table,
                .pba-committee-edit-form-table tbody,
                .pba-committee-edit-form-table tr,
                .pba-committee-edit-form-table th,
                .pba-committee-edit-form-table td {
                    display: block;
                    width: 100%;
                }

                .pba-committee-edit-form-table th {
                    border-bottom: none;
                    padding-bottom: 6px;
                }

                .pba-committee-edit-form-table td {
                    padding-top: 0;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_render_committees_status_message')) {
    function pba_render_committees_status_message() {
        $status = isset($_GET['pba_committees_status']) ? sanitize_text_field(wp_unslash($_GET['pba_committees_status'])) : '';

        if ($status === '') {
            return '';
        }

        $message = str_replace('_', ' ', $status);
        $class = ($status === 'committee_saved') ? 'pba-committees-message' : 'pba-committees-message error';

        return '<div class="' . esc_attr($class) . '">' . esc_html(ucfirst($message)) . '</div>';
    }
}

if (!function_exists('pba_sort_committees_for_display')) {
    function pba_sort_committees_for_display($rows) {
        if (!is_array($rows)) {
            return array();
        }

        usort($rows, function ($a, $b) {
            $name_a = trim((string) ($a['committee_name'] ?? ''));
            $name_b = trim((string) ($b['committee_name'] ?? ''));

            $is_bod_a = strcasecmp($name_a, 'Board of Directors') === 0;
            $is_bod_b = strcasecmp($name_b, 'Board of Directors') === 0;

            if ($is_bod_a && !$is_bod_b) {
                return -1;
            }

            if (!$is_bod_a && $is_bod_b) {
                return 1;
            }

            return strcasecmp($name_a, $name_b);
        });

        return $rows;
    }
}

if (!function_exists('pba_render_committees_list_view')) {
    function pba_render_committees_list_view() {
        $rows = pba_supabase_get('Committee', array(
            'select' => 'committee_id,committee_name,committee_description,status,last_modified_at',
            'order'  => 'committee_name.asc',
        ));

        if (is_wp_error($rows)) {
            return '<p>Unable to load committees.</p>';
        }

        $rows = pba_sort_committees_for_display($rows);

        $committee_ids = array();

        foreach ($rows as $row) {
            $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
            if ($committee_id > 0) {
                $committee_ids[] = $committee_id;
            }
        }

        $committee_rosters = pba_get_committee_roster_for_display($committee_ids);

        ob_start();
        echo pba_render_committees_shared_styles();
        ?>
        <div class="pba-committees-page">
            <div class="pba-committees-shell">
                <div class="pba-committees-header">
                    <p class="pba-committees-subtitle">View and manage committees and their current board and member rosters.</p>
                </div>

                <div class="pba-committees-toolbar">
                    <div class="pba-committees-muted">
                        <?php echo esc_html(count($rows)); ?> committee<?php echo count($rows) === 1 ? '' : 's'; ?>
                    </div>
                </div>

                <div class="pba-committees-content">
                    <?php echo pba_render_committees_status_message(); ?>

                    <div class="pba-committees-table-wrap">
                        <table class="pba-committees-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Board</th>
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
                                        $roster = isset($committee_rosters[$committee_id]) ? $committee_rosters[$committee_id] : array(
                                            'board'   => array(),
                                            'members' => array(),
                                        );

                                        $status = trim((string) ($row['status'] ?? ''));
                                        $status_class = (strcasecmp($status, 'Active') === 0) ? 'pba-committee-status-pill' : 'pba-committee-status-pill inactive';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="pba-committee-name"><?php echo esc_html($row['committee_name'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo esc_html($row['committee_description'] ?? ''); ?></td>
                                            <td>
                                                <span class="<?php echo esc_attr($status_class); ?>">
                                                    <?php echo esc_html($status !== '' ? $status : '—'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($roster['board'])) : ?>
                                                    <div class="pba-committee-roster">
                                                        <?php foreach ($roster['board'] as $person) : ?>
                                                            <div class="pba-committee-roster-entry">
                                                                <?php
                                                                $label = $person['name'];

                                                                if (!empty($person['role'])) {
                                                                    $label .= ', ' . $person['role'];
                                                                }

                                                                if (!empty($person['status']) && strcasecmp($person['status'], 'Active') !== 0) {
                                                                    $label .= ' (' . $person['status'] . ')';
                                                                }

                                                                echo esc_html($label);
                                                                ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else : ?>
                                                    <span class="pba-committees-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($roster['members'])) : ?>
                                                    <div class="pba-committee-roster">
                                                        <?php foreach ($roster['members'] as $person) : ?>
                                                            <div class="pba-committee-roster-entry">
                                                                <?php
                                                                $label = $person['name'];

                                                                if (!empty($person['role'])) {
                                                                    $label .= ', ' . $person['role'];
                                                                }

                                                                if (!empty($person['status']) && strcasecmp($person['status'], 'Active') !== 0) {
                                                                    $label .= ' (' . $person['status'] . ')';
                                                                }

                                                                echo esc_html($label);
                                                                ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else : ?>
                                                    <span class="pba-committees-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_at'] ?? '')); ?></td>
                                            <td>
                                                <a class="pba-committees-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                                    'committee_view' => 'edit',
                                                    'committee_id'   => $committee_id,
                                                ), home_url('/committees/'))); ?>">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('pba_render_committee_edit_view')) {
    function pba_render_committee_edit_view($committee_id) {
        $rows = pba_supabase_get('Committee', array(
            'select'       => 'committee_id,committee_name,committee_description,status,notes,last_modified_at',
            'committee_id' => 'eq.' . (int) $committee_id,
            'limit'        => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0])) {
            return '<p>Committee not found.</p>';
        }

        $committee = $rows[0];
        $member_count = pba_get_committee_member_count_in_app($committee_id);

        ob_start();
        echo pba_render_committees_shared_styles();
        ?>
        <div class="pba-committees-page">
            <div class="pba-committee-edit-shell">
                <div class="pba-committee-edit-header">
                    <h1 class="pba-committee-edit-title">Edit Committee</h1>
                    <p class="pba-committee-edit-subtitle">Update committee details, status, and internal notes.</p>
                </div>

                <div class="pba-committee-edit-toolbar">
                    <a class="pba-committees-btn secondary" href="<?php echo esc_url(home_url('/committees/')); ?>">Back to Committees</a>
                </div>

                <div class="pba-committee-edit-content">
                    <?php echo pba_render_committees_status_message(); ?>

                    <div class="pba-committee-meta">
                        <div><strong>Active members:</strong> <?php echo esc_html($member_count); ?></div>
                        <div><strong>Last modified:</strong> <?php echo esc_html(pba_format_datetime_display($committee['last_modified_at'] ?? '')); ?></div>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-committee-edit-form">
                        <?php wp_nonce_field('pba_committee_admin_action', 'pba_committee_admin_nonce'); ?>
                        <input type="hidden" name="action" value="pba_save_committee_admin">
                        <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee['committee_id']); ?>">

                        <table class="pba-committee-edit-form-table">
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
                                <th><label for="notes">Notes</label></th>
                                <td><textarea class="pba-committee-edit-textarea" name="notes" id="notes"><?php echo esc_textarea($committee['notes'] ?? ''); ?></textarea></td>
                            </tr>
                        </table>

                        <div class="pba-committee-edit-actions">
                            <button type="submit" class="pba-committees-btn">Save Committee</button>
                            <a class="pba-committees-btn secondary" href="<?php echo esc_url(home_url('/committees/')); ?>">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('pba_get_committee_member_count_in_app')) {
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
}

if (!function_exists('pba_get_committee_roster_for_display')) {
    function pba_get_committee_roster_for_display($committee_ids) {
        $committee_ids = array_values(array_unique(array_map('intval', (array) $committee_ids)));
        $committee_ids = array_filter($committee_ids, function ($id) {
            return $id > 0;
        });

        if (empty($committee_ids)) {
            return array();
        }

        $membership_rows = pba_supabase_get('Person_to_Committee', array(
            'select'       => 'committee_id,person_id,committee_role,is_active',
            'committee_id' => 'in.(' . implode(',', $committee_ids) . ')',
            'is_active'    => 'eq.true',
            'limit'        => max(count($committee_ids) * 10, count($committee_ids)),
        ));

        if (is_wp_error($membership_rows) || !is_array($membership_rows) || empty($membership_rows)) {
            return array();
        }

        $person_ids = array();

        foreach ($membership_rows as $row) {
            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
            if ($person_id > 0) {
                $person_ids[] = $person_id;
            }
        }

        $person_ids = array_values(array_unique($person_ids));

        if (empty($person_ids)) {
            return array();
        }

        $people_rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,first_name,last_name,email_address,status',
            'person_id' => 'in.(' . implode(',', $person_ids) . ')',
            'limit'     => count($person_ids),
        ));

        if (is_wp_error($people_rows) || !is_array($people_rows)) {
            return array();
        }

        $people_by_id = array();

        foreach ($people_rows as $person) {
            $person_id = isset($person['person_id']) ? (int) $person['person_id'] : 0;
            if ($person_id < 1) {
                continue;
            }

            $people_by_id[$person_id] = array(
                'name'   => trim(((string) ($person['first_name'] ?? '')) . ' ' . ((string) ($person['last_name'] ?? ''))),
                'email'  => trim((string) ($person['email_address'] ?? '')),
                'status' => trim((string) ($person['status'] ?? '')),
            );
        }

        $roster_by_committee = array();

        foreach ($membership_rows as $row) {
            $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;

            if ($committee_id < 1 || $person_id < 1 || !isset($people_by_id[$person_id])) {
                continue;
            }

            $person = $people_by_id[$person_id];
            $role = trim((string) ($row['committee_role'] ?? ''));
            $status = trim((string) ($person['status'] ?? ''));

            if (!isset($roster_by_committee[$committee_id])) {
                $roster_by_committee[$committee_id] = array(
                    'board'   => array(),
                    'members' => array(),
                );
            }

            $display_name = $person['name'] !== '' ? $person['name'] : 'Unnamed member';

            $entry = array(
                'name'   => $display_name,
                'email'  => $person['email'],
                'role'   => $role,
                'status' => $status,
            );

            $role_lc = strtolower($role);

            if (
                $role_lc === 'chair' ||
                $role_lc === 'co-chair' ||
                $role_lc === 'treasurer' ||
                $role_lc === 'secretary' ||
                $role_lc === 'vice chair' ||
                $role_lc === 'vice-chair' ||
                $role_lc === 'president' ||
                $role_lc === 'vice president' ||
                $role_lc === 'vice-president'
            ) {
                $roster_by_committee[$committee_id]['board'][] = $entry;
            } else {
                $roster_by_committee[$committee_id]['members'][] = $entry;
            }
        }

        foreach ($roster_by_committee as $committee_id => $groups) {
            usort($roster_by_committee[$committee_id]['board'], function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            usort($roster_by_committee[$committee_id]['members'], function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
        }

        return $roster_by_committee;
    }
}
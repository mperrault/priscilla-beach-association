<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_member_resources_shortcode');

function pba_register_member_resources_shortcode() {
    add_shortcode('pba_member_resources', 'pba_render_member_resources_shortcode');
}

function pba_render_member_resources_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!function_exists('pba_current_person_can_view_member_resources') || !pba_current_person_can_view_member_resources()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $search = isset($_GET['resource_search']) ? sanitize_text_field(wp_unslash($_GET['resource_search'])) : '';
    $source_filter = isset($_GET['resource_source']) ? sanitize_text_field(wp_unslash($_GET['resource_source'])) : '';
    $committee_filter = isset($_GET['resource_committee']) ? (int) $_GET['resource_committee'] : 0;
    $category_filter = isset($_GET['resource_category']) ? sanitize_text_field(wp_unslash($_GET['resource_category'])) : '';

    $rows = pba_get_member_resource_rows();

    if (is_wp_error($rows)) {
        return '<p>Unable to load member resources right now.</p>';
    }

    if (!is_array($rows)) {
        $rows = array();
    }

    $committee_options = array();
    $category_options = array();

    foreach ($rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        $committee_name = isset($row['committee_name']) ? trim((string) $row['committee_name']) : '';
        $category = isset($row['document_category']) ? trim((string) $row['document_category']) : '';

        if ($committee_id > 0 && $committee_name !== '') {
            $committee_options[$committee_id] = $committee_name;
        }

        if ($category !== '') {
            $category_options[$category] = $category;
        }
    }

    asort($committee_options, SORT_NATURAL | SORT_FLAG_CASE);
    natcasesort($category_options);

    if ($search !== '' || $source_filter !== '' || $committee_filter > 0 || $category_filter !== '') {
        $needle = strtolower($search);

        $rows = array_values(array_filter($rows, function ($row) use ($needle, $source_filter, $committee_filter, $category_filter) {
            $scope = isset($row['folder_scope_type']) ? (string) $row['folder_scope_type'] : '';
            $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
            $category = isset($row['document_category']) ? trim((string) $row['document_category']) : '';

            if ($source_filter !== '' && strcasecmp($scope, $source_filter) !== 0) {
                return false;
            }

            if ($committee_filter > 0 && $committee_id !== $committee_filter) {
                return false;
            }

            if ($category_filter !== '' && strcasecmp($category, $category_filter) !== 0) {
                return false;
            }

            if ($needle === '') {
                return true;
            }

            $haystack_parts = array(
                isset($row['document_title']) ? (string) $row['document_title'] : '',
                isset($row['file_name']) ? (string) $row['file_name'] : '',
                isset($row['member_summary']) ? (string) $row['member_summary'] : '',
                isset($row['notes']) ? (string) $row['notes'] : '',
                isset($row['folder_name']) ? (string) $row['folder_name'] : '',
                isset($row['committee_name']) ? (string) $row['committee_name'] : '',
                isset($row['document_category']) ? (string) $row['document_category'] : '',
                isset($row['document_version']) ? (string) $row['document_version'] : '',
                $scope,
            );

            $haystack = strtolower(trim(implode(' ', $haystack_parts)));
            return $haystack !== '' && strpos($haystack, $needle) !== false;
        }));
    }

    ob_start();
    ?>
    <style>
        .pba-member-resources-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .pba-member-resources-search {
            margin: 18px 0 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .pba-member-resources-search input[type="text"],
        .pba-member-resources-search select {
            padding: 9px 10px;
            min-height: 40px;
            max-width: 100%;
        }

        .pba-member-resources-search input[type="text"] {
            width: 280px;
        }

        .pba-member-resources-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        .pba-member-resources-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-member-resources-btn:hover {
            background: #0b3154;
            border-color: #0b3154;
            color: #fff;
        }

        .pba-member-resources-btn.secondary:hover {
            background: #f3f7fb;
            border-color: #0d3b66;
            color: #0d3b66;
        }

        .pba-member-resources-meta {
            margin-bottom: 14px;
            color: #666;
            font-size: 14px;
        }

        .pba-member-resources-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pba-member-resources-table th,
        .pba-member-resources-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .pba-member-resources-table th {
            background: #f3f3f3;
        }

        .pba-member-resource-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .pba-member-resource-muted {
            color: #666;
            font-size: 13px;
        }

        .pba-member-resource-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: #edf3f8;
            color: #0d3b66;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .pba-member-resource-open-col {
            width: 110px;
        }

        @media (max-width: 860px) {
            .pba-member-resources-table,
            .pba-member-resources-table thead,
            .pba-member-resources-table tbody,
            .pba-member-resources-table th,
            .pba-member-resources-table td,
            .pba-member-resources-table tr {
                display: block;
            }

            .pba-member-resources-table thead {
                display: none;
            }

            .pba-member-resources-table tr {
                margin-bottom: 14px;
                border: 1px solid #d7d7d7;
            }

            .pba-member-resources-table td {
                border: 0;
                border-bottom: 1px solid #eee;
            }

            .pba-member-resources-table td:last-child {
                border-bottom: 0;
            }
        }
    </style>

    <div class="pba-member-resources-wrap">
        <!-- h2>Member Resources</h2-->
        <p>Documents and resources shared with all members by the Board and committees.</p>

        <form method="get" class="pba-member-resources-search">
            <input
                type="text"
                name="resource_search"
                value="<?php echo esc_attr($search); ?>"
                placeholder="Search title, folder, committee, category, or summary"
            >

            <select name="resource_source">
                <option value="">All sources</option>
                <option value="Board" <?php selected($source_filter, 'Board'); ?>>Board</option>
                <option value="Committee" <?php selected($source_filter, 'Committee'); ?>>Committee</option>
            </select>

            <select name="resource_committee">
                <option value="0">All committees</option>
                <?php foreach ($committee_options as $committee_id => $committee_name) : ?>
                    <option value="<?php echo esc_attr((string) $committee_id); ?>" <?php selected($committee_filter, $committee_id); ?>>
                        <?php echo esc_html($committee_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="resource_category">
                <option value="">All categories</option>
                <?php foreach ($category_options as $category) : ?>
                    <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                        <?php echo esc_html($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="pba-member-resources-btn secondary">Filter</button>
            <a class="pba-member-resources-btn secondary" href="<?php echo esc_url(home_url('/member-resources/')); ?>">Clear</a>
        </form>

        <div class="pba-member-resources-meta">
            Showing <?php echo esc_html((string) count($rows)); ?> shared resource<?php echo count($rows) === 1 ? '' : 's'; ?>.
        </div>

        <table class="pba-member-resources-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Shared From</th>
                    <th>Folder</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Version</th>
                    <th>Summary</th>
                    <th class="pba-member-resource-open-col">Open</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="8">No shared resources found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $title = trim((string) ($row['document_title'] ?? ''));
                        if ($title === '') {
                            $title = trim((string) ($row['file_name'] ?? 'Untitled document'));
                        }

                        $scope = isset($row['folder_scope_type']) ? trim((string) $row['folder_scope_type']) : '';
                        $committee_name = isset($row['committee_name']) ? trim((string) $row['committee_name']) : '';
                        $shared_from = $scope === 'Committee' && $committee_name !== '' ? 'Committee - ' . $committee_name : 'Board';
                        $folder_name = isset($row['folder_name']) ? trim((string) $row['folder_name']) : '';
                        $category = isset($row['document_category']) ? trim((string) $row['document_category']) : '';
                        $version = isset($row['document_version']) ? trim((string) $row['document_version']) : '';
                        $summary = isset($row['member_summary']) ? trim((string) $row['member_summary']) : '';
                        $notes = isset($row['notes']) ? trim((string) $row['notes']) : '';
                        $url = isset($row['file_url']) ? trim((string) $row['file_url']) : '';
                        $date_value = isset($row['document_date']) ? trim((string) $row['document_date']) : '';
                        $date_display = $date_value !== '' ? pba_member_resources_format_date($date_value) : '';

                        if ($summary === '' && $notes !== '') {
                            $summary = $notes;
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="pba-member-resource-title"><?php echo esc_html($title); ?></div>
                                <?php if ($scope !== '') : ?>
                                    <span class="pba-member-resource-badge"><?php echo esc_html($scope); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($shared_from); ?></td>
                            <td><?php echo esc_html($folder_name !== '' ? $folder_name : ''); ?></td>
                            <td><?php echo esc_html($category !== '' ? $category : ''); ?></td>
                            <td><?php echo esc_html($date_display); ?></td>
                            <td><?php echo esc_html($version !== '' ? $version : ''); ?></td>
                            <td>
                                <?php if ($summary !== '') : ?>
                                    <div><?php echo esc_html($summary); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($row['shared_with_members_at'])) : ?>
                                    <div class="pba-member-resource-muted">Shared <?php echo esc_html(pba_format_datetime_display($row['shared_with_members_at'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="pba-member-resource-open-col">
                                <?php if ($url !== '') : ?>
                                    <a class="pba-member-resources-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Open</a>
                                <?php else : ?>
                                    <span class="pba-member-resource-muted">Unavailable</span>
                                <?php endif; ?>
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

function pba_member_resources_format_date($date_value) {
    $date_value = trim((string) $date_value);
    if ($date_value === '') {
        return '';
    }

    $timestamp = strtotime($date_value);
    if ($timestamp === false) {
        return $date_value;
    }

    return date_i18n('M j, Y', $timestamp);
}
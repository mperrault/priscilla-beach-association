<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_member_resources_shortcode');

if (!function_exists('pba_get_document_viewer_url')) {
    function pba_get_document_viewer_url($document_item_id) {
        $document_item_id = absint($document_item_id);

        if ($document_item_id < 1) {
            return '';
        }

        return add_query_arg('pba_document_view', $document_item_id, home_url('/'));
    }
}

function pba_register_member_resources_shortcode() {
    add_shortcode('pba_member_resources', 'pba_render_member_resources_shortcode');
}

function pba_member_resources_enqueue_styles() {
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    $base_url = plugin_dir_url(__FILE__) . 'css/';
    $base_path = dirname(__FILE__) . '/css/';

    wp_enqueue_style(
        'pba-admin-list-styles',
        $base_url . 'pba-admin-list-styles.css',
        array(),
        file_exists($base_path . 'pba-admin-list-styles.css') ? (string) filemtime($base_path . 'pba-admin-list-styles.css') : '1.0.0'
    );
}

function pba_render_member_resources_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!function_exists('pba_current_person_can_view_member_resources') || !pba_current_person_can_view_member_resources()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    pba_member_resources_enqueue_styles();

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

    $resource_count = count($rows);

    ob_start();
    ?>
    <style>
        .pba-member-resources-wrap {
            max-width: 1480px;
            margin: 0 auto;
            color: #17324a;
        }

        .pba-member-resources-wrap .pba-admin-list-card {
            width: 100%;
        }

        .pba-member-resources-toolbar-grid {
            grid-template-columns: minmax(260px, 1.4fr) minmax(170px, 0.7fr) minmax(220px, 0.9fr) minmax(180px, 0.8fr);
            gap: 12px;
        }

        .pba-member-resources-table {
            width: 100%;
            min-width: 1080px;
        }

        .pba-member-resource-title {
            color: #17324a;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.35;
        }

        .pba-member-resource-title a {
            color: #0d3b66;
            font-weight: 700;
            text-decoration: none;
        }

        .pba-member-resource-title a:hover,
        .pba-member-resource-title a:focus {
            color: #0b3154;
            text-decoration: underline;
        }

        .pba-member-resource-muted {
            color: #647b8d;
            font-size: 13px;
            line-height: 1.45;
        }

        .pba-member-resource-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef3f8;
            color: #21425c;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pba-member-resource-summary {
            max-width: 420px;
            line-height: 1.45;
        }

        .pba-member-resources-wrap .pba-admin-list-btn {
            text-decoration: none;
        }

        .pba-member-resources-wrap .pba-admin-list-btn.secondary {
            color: #0d3b66;
        }

        @media (max-width: 1080px) {
            .pba-member-resources-toolbar-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 760px) {
            .pba-member-resources-toolbar-grid {
                grid-template-columns: 1fr;
            }

            .pba-member-resources-wrap .pba-admin-list-toolbar-actions .pba-admin-list-btn {
                width: 100%;
            }

            .pba-member-resources-table {
                min-width: 920px;
            }
        }
    </style>

    <div class="pba-member-resources-wrap pba-page-wrap pba-member-resources-list-shell">
        <div class="pba-admin-list-hero">
            <div class="pba-admin-list-hero-top">
                <div>
                    <p>Documents and resources shared with all members by the Board and committees.</p>
                </div>
                <div class="pba-admin-list-badge">
                    <?php echo esc_html(number_format_i18n($resource_count)); ?> Shared Resource<?php echo $resource_count === 1 ? '' : 's'; ?>
                </div>
            </div>
        </div>

        <div class="pba-admin-list-card">
            <form method="get" class="pba-admin-list-toolbar">
                <div class="pba-admin-list-toolbar-grid pba-member-resources-toolbar-grid">
                    <div class="pba-admin-list-field">
                        <label for="resource_search">Search</label>
                        <input
                            id="resource_search"
                            type="text"
                            name="resource_search"
                            value="<?php echo esc_attr($search); ?>"
                            placeholder="Search title, folder, committee, category, or summary"
                        >
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="resource_source">Source</label>
                        <select id="resource_source" name="resource_source">
                            <option value="">All sources</option>
                            <option value="Board" <?php selected($source_filter, 'Board'); ?>>Board</option>
                            <option value="Committee" <?php selected($source_filter, 'Committee'); ?>>Committee</option>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="resource_committee">Committee</label>
                        <select id="resource_committee" name="resource_committee">
                            <option value="0">All committees</option>
                            <?php foreach ($committee_options as $committee_id => $committee_name) : ?>
                                <option value="<?php echo esc_attr((string) $committee_id); ?>" <?php selected($committee_filter, $committee_id); ?>>
                                    <?php echo esc_html($committee_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="resource_category">Category</label>
                        <select id="resource_category" name="resource_category">
                            <option value="">All categories</option>
                            <?php foreach ($category_options as $category) : ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                                    <?php echo esc_html($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pba-admin-list-toolbar-actions">
                    <button type="submit" class="pba-admin-list-btn">Apply Filters</button>

                    <?php if ($search !== '' || $source_filter !== '' || $committee_filter > 0 || $category_filter !== '') : ?>
                        <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(home_url('/member-resources/')); ?>">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="pba-admin-list-resultsbar">
                <div>
                    Showing <?php echo esc_html((string) $resource_count); ?> shared resource<?php echo $resource_count === 1 ? '' : 's'; ?>.
                </div>

                <div class="pba-admin-list-filter-summary">
                    <?php if ($search !== '') : ?>
                        <span class="pba-admin-list-chip">Search: <?php echo esc_html($search); ?></span>
                    <?php endif; ?>

                    <?php if ($source_filter !== '') : ?>
                        <span class="pba-admin-list-chip">Source: <?php echo esc_html($source_filter); ?></span>
                    <?php endif; ?>

                    <?php if ($committee_filter > 0 && isset($committee_options[$committee_filter])) : ?>
                        <span class="pba-admin-list-chip">Committee: <?php echo esc_html($committee_options[$committee_filter]); ?></span>
                    <?php endif; ?>

                    <?php if ($category_filter !== '') : ?>
                        <span class="pba-admin-list-chip">Category: <?php echo esc_html($category_filter); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pba-admin-list-grid-wrap" aria-live="polite">
                <div class="pba-admin-list-skeleton" aria-hidden="true">
                    <div class="pba-admin-list-skeleton-line"></div>
                    <div class="pba-admin-list-skeleton-line"></div>
                    <div class="pba-admin-list-skeleton-line"></div>
                    <div class="pba-admin-list-skeleton-line"></div>
                </div>

                <table class="pba-admin-list-table pba-table pba-member-resources-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Shared From</th>
                            <th>Folder</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Version</th>
                            <th>Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr>
                                <td colspan="7" class="pba-admin-list-empty">No shared resources found for the current filters.</td>
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
                                        <div class="pba-member-resource-title">
                                            <?php if ($url !== '') : ?>
                                                <?php
                                                $document_item_id = isset($row['document_item_id']) ? absint($row['document_item_id']) : 0;
                                                $viewer_url = $document_item_id > 0 ? pba_get_document_viewer_url($document_item_id) : '';
                                                $link_url = $viewer_url !== '' ? $viewer_url : $url;
                                                ?>
                                                <a href="<?php echo esc_url($link_url); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo esc_html($title); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo esc_html($title); ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($scope !== '') : ?>
                                            <span class="pba-member-resource-badge"><?php echo esc_html($scope); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?php echo esc_html($shared_from); ?></td>

                                    <td>
                                        <?php if ($folder_name !== '') : ?>
                                            <?php echo esc_html($folder_name); ?>
                                        <?php else : ?>
                                            <span class="pba-admin-list-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($category !== '') : ?>
                                            <?php echo esc_html($category); ?>
                                        <?php else : ?>
                                            <span class="pba-admin-list-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($date_display !== '') : ?>
                                            <?php echo esc_html($date_display); ?>
                                        <?php else : ?>
                                            <span class="pba-admin-list-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($version !== '') : ?>
                                            <?php echo esc_html($version); ?>
                                        <?php else : ?>
                                            <span class="pba-admin-list-muted">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="pba-member-resource-summary">
                                            <?php if ($summary !== '') : ?>
                                                <div><?php echo esc_html($summary); ?></div>
                                            <?php else : ?>
                                                <span class="pba-admin-list-muted">-</span>
                                            <?php endif; ?>

                                            <?php if (!empty($row['shared_with_members_at'])) : ?>
                                                <div class="pba-member-resource-muted">Shared <?php echo esc_html(pba_format_datetime_display($row['shared_with_members_at'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
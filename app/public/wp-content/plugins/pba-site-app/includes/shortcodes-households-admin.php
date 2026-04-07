<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_households_admin_shortcode');
add_action('admin_post_pba_save_household_admin', 'pba_handle_save_household_admin');

function pba_register_households_admin_shortcode() {
    add_shortcode('pba_households_admin', 'pba_render_households_admin_shortcode');
}

function pba_render_households_admin_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles')) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $view = isset($_GET['household_view']) ? sanitize_text_field(wp_unslash($_GET['household_view'])) : 'list';
    $household_id = isset($_GET['household_id']) ? absint($_GET['household_id']) : 0;

    if ($view === 'edit' && $household_id > 0) {
        return pba_render_household_admin_edit_view($household_id);
    }

    return pba_render_households_admin_list_view();
}

function pba_render_households_admin_status_message() {
    $status = isset($_GET['pba_households_status']) ? sanitize_text_field(wp_unslash($_GET['pba_households_status'])) : '';

    if ($status === '') {
        return '';
    }

    $message = str_replace('_', ' ', $status);
    $success_statuses = array(
        'household_saved',
    );

    $class = in_array($status, $success_statuses, true) ? 'pba-households-message' : 'pba-households-message error';

    return '<div class="' . esc_attr($class) . '">' . esc_html(ucfirst($message)) . '</div>';
}

function pba_get_households_admin_list_request_args() {
    $allowed_sort_columns = array(
        'address',
        'owner',
        'house_admin',
        'status',
        'active_members',
        'total_members',
        'owner_occupied',
        'last_modified',
    );

    $allowed_sort_directions = array('asc', 'desc');
    $allowed_per_page = array(25, 50, 100);

    $search = isset($_GET['household_search']) ? sanitize_text_field(wp_unslash($_GET['household_search'])) : '';
    $status_filter = isset($_GET['household_status_filter']) ? sanitize_text_field(wp_unslash($_GET['household_status_filter'])) : '';
    $owner_occupied_filter = isset($_GET['household_owner_occupied']) ? sanitize_text_field(wp_unslash($_GET['household_owner_occupied'])) : '';
    $sort = isset($_GET['household_sort']) ? sanitize_key(wp_unslash($_GET['household_sort'])) : 'address';
    $direction = isset($_GET['household_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['household_direction']))) : 'asc';
    $page = isset($_GET['household_page']) ? max(1, absint($_GET['household_page'])) : 1;
    $per_page = isset($_GET['household_per_page']) ? absint($_GET['household_per_page']) : 25;

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'address';
    }

    if (!in_array($direction, $allowed_sort_directions, true)) {
        $direction = 'asc';
    }

    if (!in_array($per_page, $allowed_per_page, true)) {
        $per_page = 25;
    }

    if (!in_array($owner_occupied_filter, array('', 'yes', 'no'), true)) {
        $owner_occupied_filter = '';
    }

    return array(
        'search' => $search,
        'status_filter' => $status_filter,
        'owner_occupied_filter' => $owner_occupied_filter,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $page,
        'per_page' => $per_page,
    );
}

function pba_get_households_admin_base_url() {
    return home_url('/households/');
}

function pba_get_households_admin_list_url($overrides = array()) {
    $args = pba_get_households_admin_list_request_args();

    $query_args = array(
        'household_search' => $args['search'],
        'household_status_filter' => $args['status_filter'],
        'household_owner_occupied' => $args['owner_occupied_filter'],
        'household_sort' => $args['sort'],
        'household_direction' => $args['direction'],
        'household_page' => $args['page'],
        'household_per_page' => $args['per_page'],
    );

    foreach ($overrides as $key => $value) {
        $query_args[$key] = $value;
    }

    foreach ($query_args as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, pba_get_households_admin_base_url());
}

function pba_render_households_admin_list_view() {
    $request_args = pba_get_households_admin_list_request_args();
    $data = pba_get_households_admin_list_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load households.</p>';
    }

    ob_start();
    ?>
    <style>
        .pba-households-wrap {
            max-width: 1480px;
            margin: 0 auto;
            color: #17324a;
        }
        .pba-households-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 10px;
        }
        .pba-households-message.error {
            background: #f8e9e9;
        }
        .pba-households-hero {
            margin: 0 0 20px;
            padding: 24px;
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f9fc 100%);
            box-shadow: 0 8px 24px rgba(14, 46, 76, 0.06);
        }
        .pba-households-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .pba-households-hero p {
            margin: 0;
            color: #4e6477;
            max-width: 760px;
        }
        .pba-households-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }
        .pba-households-kpi {
            padding: 16px 18px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e3ebf3;
            box-shadow: 0 6px 18px rgba(14, 46, 76, 0.05);
        }
        .pba-households-kpi-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #5f7386;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .pba-households-kpi-value {
            font-size: 28px;
            line-height: 1.1;
            font-weight: 700;
            color: #102a43;
        }
        .pba-households-card {
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(14, 46, 76, 0.05);
            overflow: hidden;
        }
        .pba-households-toolbar {
            padding: 18px;
            border-bottom: 1px solid #e5edf5;
            background: #fbfdff;
        }
        .pba-households-search {
            display: grid;
            grid-template-columns: minmax(220px, 2.2fr) minmax(180px, 1fr) minmax(180px, 1fr) minmax(120px, 140px) auto auto;
            gap: 12px;
            align-items: end;
        }
        .pba-households-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #607487;
        }
        .pba-households-field input[type="text"],
        .pba-households-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cdd9e5;
            border-radius: 12px;
            background: #ffffff;
            color: #17324a;
            box-sizing: border-box;
        }
        .pba-households-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 16px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            line-height: 1.2;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .pba-households-btn:hover {
            background: #0b3154;
            border-color: #0b3154;
            transform: translateY(-1px);
            color: #fff;
        }
        .pba-households-btn.secondary {
            background: #ffffff;
            color: #0d3b66;
            border: 1px solid #c9d8e6;
            border-radius: 999px;
            min-height: 38px;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(13, 59, 102, 0.04);
        }
        .pba-households-btn.secondary:hover {
            background: #f3f8fc;
            color: #0b3154;
            border-color: #9fb8cd;
            box-shadow: 0 4px 12px rgba(13, 59, 102, 0.10);
            transform: translateY(-1px);
        }
        .pba-households-btn.secondary:focus {
            outline: 2px solid #9fc2df;
            outline-offset: 2px;
        }
        .pba-households-resultsbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            padding: 14px 18px;
            border-bottom: 1px solid #e5edf5;
            color: #597084;
            font-size: 14px;
        }
        .pba-households-filter-summary {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .pba-households-chip,
        .pba-households-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .pba-households-chip {
            background: #eef4fa;
            color: #31536f;
        }
        .pba-households-badge {
            background: #eef3f8;
            color: #21425c;
        }
        .pba-households-badge.status-active,
        .pba-households-badge.owner-yes {
            background: #eaf7ef;
            color: #21633f;
        }
        .pba-households-badge.status-inactive,
        .pba-households-badge.owner-no {
            background: #f7eee7;
            color: #8f4a1f;
        }
        .pba-households-badge.status-unknown {
            background: #eef3f8;
            color: #46617a;
        }
        .pba-households-grid-wrap {
            position: relative;
            overflow-x: auto;
            overflow-y: visible;
        }
        .pba-households-grid-wrap.is-loading::after {
            content: "Refreshing…";
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 3;
            padding: 7px 12px;
            background: rgba(13, 59, 102, 0.92);
            color: #fff;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .pba-households-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .pba-households-table th,
        .pba-households-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
            background: #fff;
        }
        .pba-households-table tbody tr:hover td {
            background: #fbfdff;
        }
        .pba-households-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fbfe;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #607487;
            font-weight: 800;
        }
        .pba-households-table th:first-child { border-top-left-radius: 12px; }
        .pba-households-table th:last-child { border-top-right-radius: 12px; }
        .pba-households-table td strong {
            color: #17324a;
            font-size: 15px;
        }
        .pba-households-muted {
            color: #647b8d;
            font-size: 13px;
        }
        .pba-households-sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pba-households-sort-link:hover {
            color: #0d3b66;
        }
        .pba-households-sort-indicator {
            display: inline-block;
            min-width: 14px;
            color: #0d3b66;
        }
        .pba-households-empty {
            padding: 34px 20px;
            text-align: center;
            color: #5f7386;
        }
        .pba-households-pagination {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 16px 18px 18px;
            flex-wrap: wrap;
        }
        .pba-households-page-links {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pba-households-page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid #d4e0eb;
            background: #fff;
            color: #17324a;
            font-weight: 700;
        }
        .pba-households-page-link:hover {
            background: #f5f9fd;
            color: #17324a;
        }
        .pba-households-page-link.current {
            background: #0d3b66;
            border-color: #0d3b66;
            color: #fff;
        }
        .pba-households-skeleton {
            display: none;
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 18px;
        }
        .pba-households-grid-wrap.is-loading .pba-households-skeleton {
            display: grid;
        }
        .pba-households-skeleton-line {
            height: 14px;
            border-radius: 999px;
            background: linear-gradient(90deg, #edf3f8 0%, #f7fafc 50%, #edf3f8 100%);
            background-size: 200% 100%;
            animation: pba-households-shimmer 1.4s linear infinite;
        }
        @keyframes pba-households-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @media (max-width: 1080px) {
            .pba-households-search {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
        @media (max-width: 680px) {
            .pba-households-hero { padding: 20px; }
            .pba-households-search {
                grid-template-columns: 1fr;
            }
            .pba-households-pagination {
                align-items: flex-start;
            }
        }
    </style>

    <div class="pba-households-wrap">
        <?php echo pba_render_households_admin_status_message(); ?>

        <div id="pba-households-admin-root">
            <?php echo pba_render_households_admin_dynamic_content($data, $request_args); ?>
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById('pba-households-admin-root');

            if (!root || !window.fetch || !window.URL) {
                return;
            }

            function getForm() {
                return root.querySelector('#pba-households-search-form');
            }

            function getShell() {
                return root.querySelector('.pba-households-admin-list-shell');
            }

            function bindInteractiveElements() {
                var form = getForm();
                if (form && form.dataset.bound !== '1') {
                    form.dataset.bound = '1';
                    form.addEventListener('submit', function (event) {
                        event.preventDefault();
                        var url = buildFormUrl(form);
                        window.history.pushState({}, '', url);
                        fetchIntoRoot(url);
                    });
                }

                var pageLinks = root.querySelectorAll('[data-households-ajax-link="1"]');
                pageLinks.forEach(function (link) {
                    if (link.dataset.bound === '1') {
                        return;
                    }

                    link.dataset.bound = '1';
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        window.history.pushState({}, '', link.href);
                        fetchIntoRoot(link.href);
                    });
                });
            }

            function setLoading(isLoading) {
                var shell = getShell();
                if (!shell) {
                    return;
                }

                var wrap = shell.querySelector('.pba-households-grid-wrap');
                if (!wrap) {
                    return;
                }

                if (isLoading) {
                    wrap.classList.add('is-loading');
                } else {
                    wrap.classList.remove('is-loading');
                }
            }

            function buildFormUrl(form) {
                var actionUrl = form.action || window.location.pathname;
                var parsed = new URL(actionUrl, window.location.origin);
                var params = new URLSearchParams(new FormData(form));
                parsed.search = params.toString();
                return parsed.toString();
            }

            function fetchIntoRoot(url) {
                setLoading(true);

                var parsed = new URL(url, window.location.origin);
                parsed.searchParams.set('pba_households_partial', '1');

                window.fetch(parsed.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.text();
                })
                .then(function (html) {
                    root.innerHTML = html;
                    bindInteractiveElements();
                })
                .catch(function () {
                    window.location.href = url;
                })
                .finally(function () {
                    setLoading(false);
                });
            }

            window.addEventListener('popstate', function () {
                fetchIntoRoot(window.location.href);
            });

            bindInteractiveElements();
        })();
    </script>
    <?php

    return ob_get_clean();
}

function pba_render_households_admin_dynamic_content($data, $request_args) {
    ob_start();
    ?>
    <div class="pba-households-hero">
        <div class="pba-households-hero-top">
            <div>
                <p>Browse and manage household records with a faster, richer table experience. Search, filter, sort, and page through the list without a full page refresh.</p>
            </div>
        </div>
        <div class="pba-households-kpis">
            <div class="pba-households-kpi">
                <span class="pba-households-kpi-label">Filtered Households</span>
                <span class="pba-households-kpi-value"><?php echo esc_html(number_format_i18n($data['total_filtered'])); ?></span>
            </div>
            <div class="pba-households-kpi">
                <span class="pba-households-kpi-label">On This Page</span>
                <span class="pba-households-kpi-value"><?php echo esc_html(number_format_i18n(count($data['page_rows']))); ?></span>
            </div>
            <div class="pba-households-kpi">
                <span class="pba-households-kpi-label">Page</span>
                <span class="pba-households-kpi-value"><?php echo esc_html(number_format_i18n($data['pagination']['current_page'])); ?> / <?php echo esc_html(number_format_i18n($data['pagination']['total_pages'])); ?></span>
            </div>
            <div class="pba-households-kpi">
                <span class="pba-households-kpi-label">Page Size</span>
                <span class="pba-households-kpi-value"><?php echo esc_html(number_format_i18n($request_args['per_page'])); ?></span>
            </div>
        </div>
    </div>

    <div class="pba-households-card">
        <div class="pba-households-toolbar">
            <form method="get" class="pba-households-search" id="pba-households-search-form">
                <input type="hidden" name="household_page" value="1">
                <input type="hidden" name="household_sort" value="<?php echo esc_attr($request_args['sort']); ?>">
                <input type="hidden" name="household_direction" value="<?php echo esc_attr($request_args['direction']); ?>">

                <div class="pba-households-field">
                    <label for="household_search">Search</label>
                    <input type="text" id="household_search" name="household_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Address, owner, or house admin">
                </div>

                <div class="pba-households-field">
                    <label for="household_status_filter">Status</label>
                    <select id="household_status_filter" name="household_status_filter">
                        <option value="">All statuses</option>
                        <?php foreach ($data['filter_options']['statuses'] as $status_option) : ?>
                            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($request_args['status_filter'], $status_option); ?>><?php echo esc_html($status_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pba-households-field">
                    <label for="household_owner_occupied">Owner occupied</label>
                    <select id="household_owner_occupied" name="household_owner_occupied">
                        <option value="">All</option>
                        <option value="yes" <?php selected($request_args['owner_occupied_filter'], 'yes'); ?>>Yes</option>
                        <option value="no" <?php selected($request_args['owner_occupied_filter'], 'no'); ?>>No</option>
                    </select>
                </div>

                <div class="pba-households-field">
                    <label for="household_per_page">Rows</label>
                    <select id="household_per_page" name="household_per_page">
                        <option value="25" <?php selected($request_args['per_page'], 25); ?>>25</option>
                        <option value="50" <?php selected($request_args['per_page'], 50); ?>>50</option>
                        <option value="100" <?php selected($request_args['per_page'], 100); ?>>100</option>
                    </select>
                </div>

                <button type="submit" class="pba-households-btn">Apply</button>
                <a href="<?php echo esc_url(pba_get_households_admin_base_url()); ?>" class="pba-households-btn secondary">Reset</a>
            </form>
        </div>

        <div class="pba-households-admin-list-shell">
            <?php echo pba_render_households_admin_list_shell($data, $request_args); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_get_households_admin_list_data($request_args) {
    $rows = pba_supabase_get('Household', array(
        'select' => 'household_id,pb_street_number,pb_street_name,household_admin_first_name,household_admin_last_name,household_status,owner_occupied,last_modified_at,owner_name_raw',
        'order'  => 'pb_street_name.asc,pb_street_number.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return is_wp_error($rows) ? $rows : new WP_Error('pba_households_load_failed', 'Unable to load households.');
    }

    $prepared_rows = array_map('pba_prepare_households_admin_list_row', $rows);
    $filter_options = pba_get_households_admin_filter_options($prepared_rows);
    $filtered_rows = pba_filter_households_admin_rows($prepared_rows, $request_args);

    $all_filtered_household_ids = array();
    foreach ($filtered_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id > 0) {
            $all_filtered_household_ids[] = $household_id;
        }
    }

    $stats_for_filtered = pba_get_household_stats_for_admin_list($all_filtered_household_ids);

    foreach ($filtered_rows as $index => $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        $stats = isset($stats_for_filtered[$household_id]) ? $stats_for_filtered[$household_id] : array(
            'active_count' => 0,
            'total_count'  => 0,
            'house_admin'  => '',
        );

        $filtered_rows[$index]['active_count'] = (int) $stats['active_count'];
        $filtered_rows[$index]['total_count'] = (int) $stats['total_count'];

        if ($filtered_rows[$index]['display_house_admin'] === '' && !empty($stats['house_admin'])) {
            $filtered_rows[$index]['display_house_admin'] = (string) $stats['house_admin'];
        }
    }

    $sorted_rows = pba_sort_households_admin_rows($filtered_rows, $request_args['sort'], $request_args['direction']);
    $pagination = pba_paginate_households_admin_rows($sorted_rows, $request_args['page'], $request_args['per_page']);

    return array(
        'all_rows' => $prepared_rows,
        'filtered_rows' => $sorted_rows,
        'page_rows' => $pagination['rows'],
        'filter_options' => $filter_options,
        'pagination' => $pagination,
        'total_filtered' => count($sorted_rows),
    );
}

function pba_prepare_households_admin_list_row($row) {
    $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
    $street_number = trim((string) ($row['pb_street_number'] ?? ''));
    $street_name = trim((string) ($row['pb_street_name'] ?? ''));
    $address = trim($street_number . ' ' . $street_name);
    $owner_name_raw = trim((string) ($row['owner_name_raw'] ?? ''));
    $stored_house_admin = trim(((string) ($row['household_admin_first_name'] ?? '')) . ' ' . ((string) ($row['household_admin_last_name'] ?? '')));
    $household_status = trim((string) ($row['household_status'] ?? ''));
    $owner_occupied = array_key_exists('owner_occupied', $row) ? $row['owner_occupied'] : null;
    $last_modified_raw = (string) ($row['last_modified_at'] ?? '');
    $last_modified_timestamp = $last_modified_raw !== '' ? strtotime($last_modified_raw) : false;

    $street_number_sort = null;
    if ($street_number !== '' && preg_match('/^\d+/', $street_number, $matches)) {
        $street_number_sort = (int) $matches[0];
    }

    return array(
        'household_id' => $household_id,
        'address' => $address,
        'street_number' => $street_number,
        'street_number_sort' => $street_number_sort,
        'street_name_sort' => strtolower($street_name),
        'owner_name_raw' => $owner_name_raw,
        'display_house_admin' => $stored_house_admin,
        'household_status' => $household_status,
        'owner_occupied' => $owner_occupied,
        'last_modified_raw' => $last_modified_raw,
        'last_modified_timestamp' => $last_modified_timestamp ? (int) $last_modified_timestamp : 0,
        'active_count' => 0,
        'total_count' => 0,
    );
}

function pba_get_households_admin_filter_options($rows) {
    $statuses = array();

    foreach ($rows as $row) {
        $status = trim((string) ($row['household_status'] ?? ''));
        if ($status !== '') {
            $statuses[$status] = $status;
        }
    }

    natcasesort($statuses);

    return array(
        'statuses' => array_values($statuses),
    );
}

function pba_filter_households_admin_rows($rows, $request_args) {
    $search = strtolower(trim((string) $request_args['search']));
    $status_filter = trim((string) $request_args['status_filter']);
    $owner_occupied_filter = trim((string) $request_args['owner_occupied_filter']);

    return array_values(array_filter($rows, function ($row) use ($search, $status_filter, $owner_occupied_filter) {
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['address'] ?? ''),
                (string) ($row['owner_name_raw'] ?? ''),
                (string) ($row['display_house_admin'] ?? ''),
                'household ' . (string) ($row['household_id'] ?? 0),
            )));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        if ($status_filter !== '' && (string) ($row['household_status'] ?? '') !== $status_filter) {
            return false;
        }

        if ($owner_occupied_filter === 'yes' && !($row['owner_occupied'] ?? false)) {
            return false;
        }

        if ($owner_occupied_filter === 'no' && ($row['owner_occupied'] ?? null) !== false) {
            return false;
        }

        return true;
    }));
}

function pba_sort_households_admin_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'owner':
                $value_a = strtolower((string) ($a['owner_name_raw'] ?? ''));
                $value_b = strtolower((string) ($b['owner_name_raw'] ?? ''));
                break;

            case 'house_admin':
                $value_a = strtolower((string) ($a['display_house_admin'] ?? ''));
                $value_b = strtolower((string) ($b['display_house_admin'] ?? ''));
                break;

            case 'status':
                $value_a = strtolower((string) ($a['household_status'] ?? ''));
                $value_b = strtolower((string) ($b['household_status'] ?? ''));
                break;

            case 'active_members':
                $value_a = (int) ($a['active_count'] ?? 0);
                $value_b = (int) ($b['active_count'] ?? 0);
                break;

            case 'total_members':
                $value_a = (int) ($a['total_count'] ?? 0);
                $value_b = (int) ($b['total_count'] ?? 0);
                break;

            case 'owner_occupied':
                $value_a = ($a['owner_occupied'] === null) ? -1 : ((bool) $a['owner_occupied'] ? 1 : 0);
                $value_b = ($b['owner_occupied'] === null) ? -1 : ((bool) $b['owner_occupied'] ? 1 : 0);
                break;

            case 'last_modified':
                $value_a = (int) ($a['last_modified_timestamp'] ?? 0);
                $value_b = (int) ($b['last_modified_timestamp'] ?? 0);
                break;

            case 'address':
            default:
                $name_a = (string) ($a['street_name_sort'] ?? '');
                $name_b = (string) ($b['street_name_sort'] ?? '');

                if ($name_a !== $name_b) {
                    $comparison = strnatcasecmp($name_a, $name_b);
                    return $direction === 'desc' ? -$comparison : $comparison;
                }

                $num_a = array_key_exists('street_number_sort', $a) ? $a['street_number_sort'] : null;
                $num_b = array_key_exists('street_number_sort', $b) ? $b['street_number_sort'] : null;

                if ($num_a !== null && $num_b !== null && $num_a !== $num_b) {
                    $comparison = ($num_a < $num_b) ? -1 : 1;
                    return $direction === 'desc' ? -$comparison : $comparison;
                }

                $value_a = strtolower((string) ($a['address'] ?? ''));
                $value_b = strtolower((string) ($b['address'] ?? ''));
                break;
        }

        if ($value_a === $value_b) {
            $fallback_a = (int) ($a['household_id'] ?? 0);
            $fallback_b = (int) ($b['household_id'] ?? 0);
            return $fallback_a <=> $fallback_b;
        }

        $comparison = ($value_a < $value_b) ? -1 : 1;
        return $direction === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
}

function pba_paginate_households_admin_rows($rows, $page, $per_page) {
    $total_rows = count($rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $current_page = min(max(1, (int) $page), $total_pages);
    $offset = ($current_page - 1) * $per_page;
    $page_rows = array_slice($rows, $offset, $per_page);

    return array(
        'rows' => $page_rows,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'per_page' => $per_page,
        'offset' => $offset,
        'start_number' => $total_rows > 0 ? ($offset + 1) : 0,
        'end_number' => $total_rows > 0 ? min($offset + $per_page, $total_rows) : 0,
    );
}

function pba_render_households_admin_list_shell($data, $request_args) {
    ob_start();

    $pagination = $data['pagination'];
    $page_rows = $data['page_rows'];
    ?>
    <div class="pba-households-resultsbar">
        <div>
            Showing <?php echo esc_html(number_format_i18n($pagination['start_number'])); ?>–<?php echo esc_html(number_format_i18n($pagination['end_number'])); ?> of <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?> households
        </div>
        <div class="pba-households-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-households-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['status_filter'] !== '') : ?>
                <span class="pba-households-chip">Status: <?php echo esc_html($request_args['status_filter']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['owner_occupied_filter'] !== '') : ?>
                <span class="pba-households-chip">Owner Occupied: <?php echo esc_html(ucfirst($request_args['owner_occupied_filter'])); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="pba-households-grid-wrap" aria-live="polite">
        <div class="pba-households-skeleton" aria-hidden="true">
            <div class="pba-households-skeleton-line"></div>
            <div class="pba-households-skeleton-line"></div>
            <div class="pba-households-skeleton-line"></div>
            <div class="pba-households-skeleton-line"></div>
        </div>

        <table class="pba-households-table">
            <thead>
                <tr>
                    <?php echo pba_render_households_admin_sortable_th('Address', 'address', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Owner', 'owner', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('House Admin', 'house_admin', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Status', 'status', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Active Members', 'active_members', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Total Members', 'total_members', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Owner Occupied', 'owner_occupied', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Last Modified', 'last_modified', $request_args); ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="9" class="pba-households-empty">No households found for the current filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($page_rows as $row) : ?>
                        <?php
                        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
                        $address = (string) ($row['address'] ?? '');
                        $owner_name_raw = trim((string) ($row['owner_name_raw'] ?? ''));
                        $display_house_admin = trim((string) ($row['display_house_admin'] ?? ''));
                        $household_status = trim((string) ($row['household_status'] ?? ''));
                        $owner_occupied = array_key_exists('owner_occupied', $row) ? $row['owner_occupied'] : null;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($address !== '' ? $address : ('Household #' . $household_id)); ?></strong>
                                <div class="pba-households-muted">Household ID <?php echo esc_html((string) $household_id); ?></div>
                            </td>
                            <td><?php echo esc_html($owner_name_raw !== '' ? $owner_name_raw : '—'); ?></td>
                            <td><?php echo esc_html($display_house_admin !== '' ? $display_house_admin : '—'); ?></td>
                            <td><?php echo pba_render_households_admin_status_badge($household_status); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) ($row['active_count'] ?? 0))); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) ($row['total_count'] ?? 0))); ?></td>
                            <td><?php echo pba_render_households_admin_owner_occupied_badge($owner_occupied); ?></td>
                            <td><?php echo esc_html(function_exists('pba_format_datetime_display') ? pba_format_datetime_display($row['last_modified_raw'] ?? '') : ($row['last_modified_raw'] ?? '')); ?></td>
                            <td>
                                <a class="pba-households-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'household_view' => 'edit',
                                    'household_id'   => $household_id,
                                ), pba_get_households_admin_base_url())); ?>">Manage &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo pba_render_households_admin_pagination($pagination); ?>
    <?php

    return ob_get_clean();
}

function pba_render_households_admin_sortable_th($label, $column, $request_args) {
    $is_current = $request_args['sort'] === $column;
    $next_direction = ($is_current && $request_args['direction'] === 'asc') ? 'desc' : 'asc';
    $indicator = '↕';

    if ($is_current) {
        $indicator = $request_args['direction'] === 'asc' ? '↑' : '↓';
    }

    $url = pba_get_households_admin_list_url(array(
        'household_sort' => $column,
        'household_direction' => $next_direction,
        'household_page' => 1,
    ));

    return '<th><a class="pba-households-sort-link" data-households-ajax-link="1" href="' . esc_url($url) . '">' . esc_html($label) . '<span class="pba-households-sort-indicator">' . esc_html($indicator) . '</span></a></th>';
}

function pba_render_households_admin_status_badge($status) {
    $status = trim((string) $status);
    $normalized = strtolower($status);
    $class = 'status-unknown';

    if ($normalized === 'active') {
        $class = 'status-active';
    } elseif ($normalized === 'inactive' || $normalized === 'disabled') {
        $class = 'status-inactive';
    }

    return '<span class="pba-households-badge ' . esc_attr($class) . '">' . esc_html($status !== '' ? $status : '—') . '</span>';
}

function pba_render_households_admin_owner_occupied_badge($owner_occupied) {
    if ($owner_occupied === null) {
        return '<span class="pba-households-badge">—</span>';
    }

    if ($owner_occupied) {
        return '<span class="pba-households-badge owner-yes">Yes</span>';
    }

    return '<span class="pba-households-badge owner-no">No</span>';
}

function pba_render_households_admin_pagination($pagination) {
    if ((int) $pagination['total_pages'] <= 1) {
        return '';
    }

    $current_page = (int) $pagination['current_page'];
    $total_pages = (int) $pagination['total_pages'];
    $pages_to_show = array();

    for ($page = 1; $page <= $total_pages; $page++) {
        if ($page === 1 || $page === $total_pages || abs($page - $current_page) <= 2) {
            $pages_to_show[] = $page;
        }
    }

    $pages_to_show = array_values(array_unique($pages_to_show));
    sort($pages_to_show);

    ob_start();
    ?>
    <div class="pba-households-pagination">
        <div class="pba-households-muted">
            Page <?php echo esc_html(number_format_i18n($current_page)); ?> of <?php echo esc_html(number_format_i18n($total_pages)); ?>
        </div>
        <div class="pba-households-page-links">
            <?php if ($current_page > 1) : ?>
                <a class="pba-households-page-link" data-households-ajax-link="1" href="<?php echo esc_url(pba_get_households_admin_list_url(array('household_page' => $current_page - 1))); ?>">Prev</a>
            <?php endif; ?>

            <?php
            $last_rendered = 0;
            foreach ($pages_to_show as $page_number) :
                if ($last_rendered > 0 && $page_number > ($last_rendered + 1)) {
                    echo '<span class="pba-households-muted">…</span>';
                }

                $classes = 'pba-households-page-link';
                if ($page_number === $current_page) {
                    $classes .= ' current';
                }
                ?>
                <a class="<?php echo esc_attr($classes); ?>" data-households-ajax-link="1" href="<?php echo esc_url(pba_get_households_admin_list_url(array('household_page' => $page_number))); ?>"><?php echo esc_html(number_format_i18n($page_number)); ?></a>
                <?php
                $last_rendered = $page_number;
            endforeach;
            ?>

            <?php if ($current_page < $total_pages) : ?>
                <a class="pba-households-page-link" data-households-ajax-link="1" href="<?php echo esc_url(pba_get_households_admin_list_url(array('household_page' => $current_page + 1))); ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_household_admin_edit_view($household_id) {
    $rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,created_at,pb_street_number,pb_street_name,household_admin_first_name,household_admin_last_name,household_admin_email_address,correspondence_address,invite_policy,notes,household_status,last_modified_at,owner_name_raw,owner_address_text,building_value,land_value,other_value,total_value,assessment_fy,lot_size_acres,last_sale_price,last_sale_date,use_code,year_built,residential_area_sqft,building_style,number_of_units,number_of_rooms,assessor_book_raw,assessor_page_raw,property_id,location_id,owner_occupied,parcel_source,parcel_last_updated_at',
        'household_id' => 'eq.' . (int) $household_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return '<p>Household not found.</p>';
    }

    $household = $rows[0];
    $member_rows = pba_get_household_member_rows_for_admin($household_id);
    $stats = pba_get_household_stats_for_admin_list(array($household_id));
    $household_stats = isset($stats[$household_id]) ? $stats[$household_id] : array(
        'active_count' => 0,
        'total_count'  => 0,
        'house_admin'  => '',
    );

    $address = trim(((string) ($household['pb_street_number'] ?? '')) . ' ' . ((string) ($household['pb_street_name'] ?? '')));
    $stored_house_admin = trim(((string) ($household['household_admin_first_name'] ?? '')) . ' ' . ((string) ($household['household_admin_last_name'] ?? '')));
    $display_house_admin = $stored_house_admin !== '' ? $stored_house_admin : $household_stats['house_admin'];

    ob_start();
    ?>
    <style>
        .pba-household-edit-wrap { max-width: 1200px; margin: 0 auto; }
        .pba-households-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-households-btn.secondary { background: #fff; color: #0d3b66; }
        .pba-households-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-households-message.error { background: #f8e9e9; }

        .pba-household-summary {
            margin: 0 0 24px;
            padding: 18px;
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .pba-household-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px 24px;
            margin-top: 14px;
        }
        .pba-household-summary-item strong {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .pba-household-detail-section {
            margin-top: 18px;
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .pba-household-detail-section summary {
            cursor: pointer;
            list-style: none;
            padding: 14px 16px;
            font-weight: 600;
            background: #f7f9fb;
            border-bottom: 1px solid #e7edf3;
        }
        .pba-household-detail-section summary::-webkit-details-marker {
            display: none;
        }
        .pba-household-detail-section[open] summary {
            background: #eef3f8;
        }
        .pba-household-detail-body {
            padding: 16px;
        }

        .pba-household-edit-form table,
        .pba-household-display-table,
        .pba-household-members-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pba-household-edit-form th,
        .pba-household-edit-form td,
        .pba-household-display-table th,
        .pba-household-display-table td,
        .pba-household-members-table th,
        .pba-household-members-table td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }
        .pba-household-edit-form th,
        .pba-household-display-table th {
            width: 240px;
        }
        .pba-household-edit-input,
        .pba-household-edit-textarea {
            width: 420px;
            max-width: 100%;
            padding: 8px 10px;
        }
        .pba-household-edit-input[readonly] {
            background: #f7f7f7;
            color: #555;
            cursor: not-allowed;
        }
        .pba-household-edit-textarea {
            min-height: 90px;
        }
        .pba-household-status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #eef3f8;
            color: #21425c;
        }
        .pba-household-muted {
            color: #666;
        }
    </style>

    <div class="pba-household-edit-wrap">
        <p>
            <a class="pba-households-btn secondary" href="<?php echo esc_url(pba_get_households_admin_base_url()); ?>">Back to Households</a>
        </p>

        <?php echo pba_render_households_admin_status_message(); ?>

        <div class="pba-household-summary">
            <h3 style="margin:0;"><?php echo esc_html($address !== '' ? $address : ('Household #' . (int) $household['household_id'])); ?></h3>
            <div class="pba-household-summary-grid">
                <div class="pba-household-summary-item">
                    <strong>Owner Name</strong>
                    <div><?php echo esc_html(($household['owner_name_raw'] ?? '') !== '' ? $household['owner_name_raw'] : '—'); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Household Admin</strong>
                    <div><?php echo esc_html($display_house_admin !== '' ? $display_house_admin : '—'); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Household Status</strong>
                    <div><?php echo esc_html(($household['household_status'] ?? '') !== '' ? $household['household_status'] : '—'); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Members</strong>
                    <div><?php echo esc_html((string) $household_stats['active_count']); ?> active / <?php echo esc_html((string) $household_stats['total_count']); ?> total</div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Owner Occupied</strong>
                    <div><?php echo esc_html(array_key_exists('owner_occupied', $household) ? ($household['owner_occupied'] ? 'Yes' : 'No') : '—'); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Invite Policy</strong>
                    <div><?php echo esc_html(isset($household['invite_policy']) && $household['invite_policy'] !== null ? (string) $household['invite_policy'] : '—'); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Last Modified</strong>
                    <div><?php echo esc_html(function_exists('pba_format_datetime_display') ? pba_format_datetime_display($household['last_modified_at'] ?? '') : ($household['last_modified_at'] ?? '')); ?></div>
                </div>
                <div class="pba-household-summary-item">
                    <strong>Created</strong>
                    <div><?php echo esc_html(function_exists('pba_format_datetime_display') ? pba_format_datetime_display($household['created_at'] ?? '') : ($household['created_at'] ?? '')); ?></div>
                </div>
            </div>
        </div>

        <details class="pba-household-detail-section" open>
            <summary>Admin & Contact</summary>
            <div class="pba-household-detail-body">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-edit-form">
                    <?php wp_nonce_field('pba_household_admin_action', 'pba_household_admin_nonce'); ?>
                    <input type="hidden" name="action" value="pba_save_household_admin">
                    <input type="hidden" name="household_id" value="<?php echo esc_attr((int) $household['household_id']); ?>">

                    <table>
                        <tr>
                            <th><label for="pb_street_number">Street Number</label></th>
                            <td><input class="pba-household-edit-input" type="text" name="pb_street_number" id="pb_street_number" value="<?php echo esc_attr($household['pb_street_number'] ?? ''); ?>" readonly></td>
                        </tr>
                        <tr>
                            <th><label for="pb_street_name">Street Name</label></th>
                            <td><input class="pba-household-edit-input" type="text" name="pb_street_name" id="pb_street_name" value="<?php echo esc_attr($household['pb_street_name'] ?? ''); ?>" readonly></td>
                        </tr>
                        <tr>
                            <th><label for="household_admin_first_name">Household Admin First Name</label></th>
                            <td><input class="pba-household-edit-input" type="text" name="household_admin_first_name" id="household_admin_first_name" value="<?php echo esc_attr($household['household_admin_first_name'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="household_admin_last_name">Household Admin Last Name</label></th>
                            <td><input class="pba-household-edit-input" type="text" name="household_admin_last_name" id="household_admin_last_name" value="<?php echo esc_attr($household['household_admin_last_name'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="household_admin_email_address">Household Admin Email</label></th>
                            <td><input class="pba-household-edit-input" type="email" name="household_admin_email_address" id="household_admin_email_address" value="<?php echo esc_attr($household['household_admin_email_address'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="correspondence_address">Correspondence Address</label></th>
                            <td><textarea class="pba-household-edit-textarea" name="correspondence_address" id="correspondence_address"><?php echo esc_textarea($household['correspondence_address'] ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="invite_policy">Invite Policy</label></th>
                            <td><input class="pba-household-edit-input" type="number" name="invite_policy" id="invite_policy" value="<?php echo esc_attr(isset($household['invite_policy']) && $household['invite_policy'] !== null ? (string) $household['invite_policy'] : ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="notes">Notes</label></th>
                            <td><textarea class="pba-household-edit-textarea" name="notes" id="notes"><?php echo esc_textarea($household['notes'] ?? ''); ?></textarea></td>
                        </tr>
                    </table>

                    <p style="margin-top:18px;">
                        <button type="submit" class="pba-households-btn">Save Household</button>
                        <a class="pba-households-btn secondary" href="<?php echo esc_url(pba_get_households_admin_base_url()); ?>">Cancel</a>
                    </p>
                </form>
            </div>
        </details>

        <details class="pba-household-detail-section" open>
            <summary>Members</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-members-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Roles</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($member_rows)) : ?>
                            <tr><td colspan="5">No members found for this household.</td></tr>
                        <?php else : ?>
                            <?php foreach ($member_rows as $member) : ?>
                                <?php
                                $person_id = isset($member['person_id']) ? (int) $member['person_id'] : 0;
                                $name = trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? '')));
                                $roles = function_exists('pba_get_active_role_names_for_person') ? pba_get_active_role_names_for_person($person_id) : array();
                                ?>
                                <tr>
                                    <td><?php echo esc_html($name !== '' ? $name : 'Unnamed member'); ?></td>
                                    <td><?php echo esc_html($member['email_address'] ?? ''); ?></td>
                                    <td><span class="pba-household-status-badge"><?php echo esc_html($member['status'] ?? ''); ?></span></td>
                                    <td><?php echo esc_html(!empty($roles) ? implode(', ', $roles) : ''); ?></td>
                                    <td>
                                        <a class="pba-households-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                            'member_view' => 'edit',
                                            'member_id'   => $person_id,
                                        ), home_url('/members/'))); ?>">Edit Member</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section" open>
            <summary>Association & Ownership</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table">
                    <tr>
                        <th>Household Status</th>
                        <td><?php echo esc_html(($household['household_status'] ?? '') !== '' ? $household['household_status'] : '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Owner Name (raw)</th>
                        <td><?php echo esc_html(($household['owner_name_raw'] ?? '') !== '' ? $household['owner_name_raw'] : '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Owner Address Text</th>
                        <td><?php echo nl2br(esc_html($household['owner_address_text'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <th>Owner Occupied</th>
                        <td><?php echo esc_html(array_key_exists('owner_occupied', $household) ? ($household['owner_occupied'] ? 'Yes' : 'No') : '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Correspondence Address</th>
                        <td><?php echo nl2br(esc_html($household['correspondence_address'] ?? '')); ?></td>
                    </tr>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section">
            <summary>Property Details</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table">
                    <tr><th>Property ID</th><td><?php echo esc_html($household['property_id'] ?? ''); ?></td></tr>
                    <tr><th>Location ID</th><td><?php echo esc_html($household['location_id'] ?? ''); ?></td></tr>
                    <tr><th>Use Code</th><td><?php echo esc_html($household['use_code'] ?? ''); ?></td></tr>
                    <tr><th>Parcel Source</th><td><?php echo esc_html($household['parcel_source'] ?? ''); ?></td></tr>
                    <tr><th>Parcel Last Updated</th><td><?php echo esc_html(function_exists('pba_format_datetime_display') ? pba_format_datetime_display($household['parcel_last_updated_at'] ?? '') : ($household['parcel_last_updated_at'] ?? '')); ?></td></tr>
                    <tr><th>Lot Size (Acres)</th><td><?php echo esc_html(isset($household['lot_size_acres']) ? (string) $household['lot_size_acres'] : ''); ?></td></tr>
                    <tr><th>Year Built</th><td><?php echo esc_html(isset($household['year_built']) ? (string) $household['year_built'] : ''); ?></td></tr>
                    <tr><th>Residential Area (Sq Ft)</th><td><?php echo esc_html(isset($household['residential_area_sqft']) ? (string) $household['residential_area_sqft'] : ''); ?></td></tr>
                    <tr><th>Building Style</th><td><?php echo esc_html($household['building_style'] ?? ''); ?></td></tr>
                    <tr><th>Number of Units</th><td><?php echo esc_html(isset($household['number_of_units']) ? (string) $household['number_of_units'] : ''); ?></td></tr>
                    <tr><th>Number of Rooms</th><td><?php echo esc_html(isset($household['number_of_rooms']) ? (string) $household['number_of_rooms'] : ''); ?></td></tr>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section">
            <summary>Valuation</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table">
                    <tr><th>Building Value</th><td><?php echo esc_html(pba_format_usd($household['building_value'] ?? '')); ?></td></tr>
                    <tr><th>Land Value</th><td><?php echo esc_html(pba_format_usd($household['land_value'] ?? '')); ?></td></tr>
                    <tr><th>Other Value</th><td><?php echo esc_html(pba_format_usd($household['other_value'] ?? '')); ?></td></tr>
                    <tr><th>Total Value</th><td><?php echo esc_html(pba_format_usd($household['total_value'] ?? '')); ?></td></tr>
                    <tr><th>Assessment FY</th><td><?php echo esc_html(isset($household['assessment_fy']) ? (string) $household['assessment_fy'] : ''); ?></td></tr>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section">
            <summary>Sales & Assessor</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table">
                    <tr><th>Last Sale Price</th><td><?php echo esc_html(pba_format_usd($household['last_sale_price'] ?? '')); ?></td></tr>
                    <tr><th>Last Sale Date</th><td><?php echo esc_html(isset($household['last_sale_date']) ? (string) $household['last_sale_date'] : ''); ?></td></tr>
                    <tr><th>Assessor Book</th><td><?php echo esc_html($household['assessor_book_raw'] ?? ''); ?></td></tr>
                    <tr><th>Assessor Page</th><td><?php echo esc_html($household['assessor_page_raw'] ?? ''); ?></td></tr>
                </table>
            </div>
        </details>
    </div>
    <?php

    return ob_get_clean();
}

function pba_handle_save_household_admin() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login/'));
        exit;
    }

    if (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles')) {
        wp_safe_redirect(home_url('/member-home/'));
        exit;
    }

    if (
        !isset($_POST['pba_household_admin_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pba_household_admin_nonce'])), 'pba_household_admin_action')
    ) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', pba_get_households_admin_base_url()));
        exit;
    }

    $household_id = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $household_admin_first_name = isset($_POST['household_admin_first_name']) ? sanitize_text_field(wp_unslash($_POST['household_admin_first_name'])) : '';
    $household_admin_last_name = isset($_POST['household_admin_last_name']) ? sanitize_text_field(wp_unslash($_POST['household_admin_last_name'])) : '';
    $household_admin_email_address = isset($_POST['household_admin_email_address']) ? sanitize_email(wp_unslash($_POST['household_admin_email_address'])) : '';
    $correspondence_address = isset($_POST['correspondence_address']) ? sanitize_textarea_field(wp_unslash($_POST['correspondence_address'])) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
    $invite_policy = isset($_POST['invite_policy']) && $_POST['invite_policy'] !== '' ? (int) $_POST['invite_policy'] : null;

    if ($household_id < 1) {
        wp_safe_redirect(add_query_arg(array(
            'household_view'        => 'edit',
            'household_id'          => $household_id,
            'pba_households_status' => 'invalid_request',
        ), pba_get_households_admin_base_url()));
        exit;
    }

    $update_data = array(
        'household_admin_first_name'    => $household_admin_first_name,
        'household_admin_last_name'     => $household_admin_last_name,
        'household_admin_email_address' => $household_admin_email_address,
        'correspondence_address'        => $correspondence_address,
        'notes'                         => $notes,
    );

    if ($invite_policy !== null) {
        $update_data['invite_policy'] = $invite_policy;
    }

    $updated = pba_supabase_update('Household', $update_data, array(
        'household_id' => 'eq.' . $household_id,
    ));

    if (is_wp_error($updated)) {
        wp_safe_redirect(add_query_arg(array(
            'household_view'        => 'edit',
            'household_id'          => $household_id,
            'pba_households_status' => 'save_failed',
        ), pba_get_households_admin_base_url()));
        exit;
    }

    wp_safe_redirect(add_query_arg(array(
        'household_view'        => 'edit',
        'household_id'          => $household_id,
        'pba_households_status' => 'household_saved',
    ), pba_get_households_admin_base_url()));
    exit;
}

function pba_person_has_house_admin_role_name($role_names) {
    if (!is_array($role_names) || empty($role_names)) {
        return false;
    }

    foreach ($role_names as $role_name) {
        $normalized = strtolower(trim((string) $role_name));

        if (
            $normalized === 'pbahouseholdadmin' ||
            $normalized === 'pbahouseadmin' ||
            $normalized === 'pba household admin' ||
            $normalized === 'house admin' ||
            $normalized === 'household admin'
        ) {
            return true;
        }
    }

    return false;
}

function pba_person_is_house_admin_by_wp_user_id($wp_user_id) {
    $wp_user_id = (int) $wp_user_id;

    if ($wp_user_id < 1) {
        return false;
    }

    $user = get_userdata($wp_user_id);

    if (!$user) {
        return false;
    }

    if (in_array('pba_house_admin', (array) $user->roles, true)) {
        return true;
    }

    return user_can($user, 'pba_view_household_page');
}

function pba_get_household_stats_for_admin_list($household_ids) {
    $household_ids = array_values(array_unique(array_map('intval', (array) $household_ids)));
    $household_ids = array_filter($household_ids, function ($id) {
        return $id > 0;
    });

    if (empty($household_ids)) {
        return array();
    }

    $rows = pba_supabase_get('Person', array(
        'select'       => 'person_id,household_id,first_name,last_name,status,wp_user_id',
        'household_id' => 'in.(' . implode(',', $household_ids) . ')',
        'order'        => 'last_name.asc,first_name.asc',
        'limit'        => max(count($household_ids) * 12, count($household_ids)),
    ));

    $stats = array();
    foreach ($household_ids as $household_id) {
        $stats[$household_id] = array(
            'active_count' => 0,
            'total_count'  => 0,
            'house_admin'  => '',
        );
    }

    if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
        return $stats;
    }

    foreach ($rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $wp_user_id = isset($row['wp_user_id']) ? (int) $row['wp_user_id'] : 0;

        if ($household_id < 1 || !isset($stats[$household_id])) {
            continue;
        }

        $stats[$household_id]['total_count']++;

        $status = isset($row['status']) ? (string) $row['status'] : '';
        if ($status === 'Active') {
            $stats[$household_id]['active_count']++;
        }

        if ($person_id > 0 && $stats[$household_id]['house_admin'] === '') {
            $is_house_admin = false;

            if (function_exists('pba_get_active_role_names_for_person')) {
                $role_names = pba_get_active_role_names_for_person($person_id);
                $is_house_admin = pba_person_has_house_admin_role_name($role_names);
            }

            if (!$is_house_admin && $wp_user_id > 0) {
                $is_house_admin = pba_person_is_house_admin_by_wp_user_id($wp_user_id);
            }

            if ($is_house_admin) {
                $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                $stats[$household_id]['house_admin'] = $name;
            }
        }
    }

    return $stats;
}

function pba_get_household_member_rows_for_admin($household_id) {
    $household_id = (int) $household_id;

    if ($household_id < 1) {
        return array();
    }

    $rows = pba_supabase_get('Person', array(
        'select'       => 'person_id,household_id,first_name,last_name,email_address,status,last_modified_at,wp_user_id',
        'household_id' => 'eq.' . $household_id,
        'order'        => 'last_name.asc,first_name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_format_usd($value) {
    if ($value === null || $value === '') {
        return '';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    return '$' . number_format((float) $value, 2);
}

if (isset($_GET['pba_households_partial']) && sanitize_text_field(wp_unslash($_GET['pba_households_partial'])) === '1') {
    add_action('template_redirect', 'pba_maybe_render_households_admin_partial');
}

function pba_maybe_render_households_admin_partial() {
    if (!is_user_logged_in()) {
        return;
    }

    if (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles')) {
        return;
    }

    $view = isset($_GET['household_view']) ? sanitize_text_field(wp_unslash($_GET['household_view'])) : 'list';
    if ($view !== 'list') {
        return;
    }

    $request_args = pba_get_households_admin_list_request_args();
    $data = pba_get_households_admin_list_data($request_args);

    if (is_wp_error($data)) {
        wp_die('Unable to load households.', 500);
    }

    echo pba_render_households_admin_dynamic_content($data, $request_args);
    exit;
}
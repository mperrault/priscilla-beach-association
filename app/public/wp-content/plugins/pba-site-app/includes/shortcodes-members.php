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

function pba_get_members_base_url() {
    return home_url('/members/');
}

function pba_get_members_list_request_args() {
    $allowed_sort_columns = array(
        'name',
        'email',
        'status',
        'household',
        'roles',
        'committees',
        'last_modified',
    );

    $allowed_sort_directions = array('asc', 'desc');
    $allowed_per_page = array(25, 50, 100);

    $search = isset($_GET['member_search']) ? sanitize_text_field(wp_unslash($_GET['member_search'])) : '';
    $status_filter = isset($_GET['member_status_filter']) ? sanitize_text_field(wp_unslash($_GET['member_status_filter'])) : '';
    $sort = isset($_GET['member_sort']) ? sanitize_key(wp_unslash($_GET['member_sort'])) : 'name';
    $direction = isset($_GET['member_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['member_direction']))) : 'asc';
    $page = isset($_GET['member_page']) ? max(1, absint($_GET['member_page'])) : 1;
    $per_page = isset($_GET['member_per_page']) ? absint($_GET['member_per_page']) : 25;

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'name';
    }

    if (!in_array($direction, $allowed_sort_directions, true)) {
        $direction = 'asc';
    }

    if (!in_array($per_page, $allowed_per_page, true)) {
        $per_page = 25;
    }

    return array(
        'search'        => $search,
        'status_filter' => $status_filter,
        'sort'          => $sort,
        'direction'     => $direction,
        'page'          => $page,
        'per_page'      => $per_page,
    );
}

function pba_get_members_list_url($overrides = array()) {
    $args = pba_get_members_list_request_args();

    $query_args = array(
        'member_search'        => $args['search'],
        'member_status_filter' => $args['status_filter'],
        'member_sort'          => $args['sort'],
        'member_direction'     => $args['direction'],
        'member_page'          => $args['page'],
        'member_per_page'      => $args['per_page'],
    );

    foreach ($overrides as $key => $value) {
        $query_args[$key] = $value;
    }

    foreach ($query_args as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, pba_get_members_base_url());
}

function pba_render_members_admin_styles() {
    ob_start();
    ?>
    <style>
        .pba-members-wrap {
            max-width: 1480px;
            margin: 0 auto;
            color: #17324a;
        }
        .pba-members-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 10px;
        }
        .pba-members-message.error {
            background: #f8e9e9;
        }
        .pba-members-hero {
            margin: 0 0 20px;
            padding: 24px;
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f9fc 100%);
            box-shadow: 0 8px 24px rgba(14, 46, 76, 0.06);
        }
        .pba-members-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .pba-members-hero p {
            margin: 0;
            color: #4e6477;
            max-width: 760px;
        }
        .pba-members-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }
        .pba-members-kpi {
            padding: 16px 18px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e3ebf3;
            box-shadow: 0 6px 18px rgba(14, 46, 76, 0.05);
        }
        .pba-members-kpi-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #5f7386;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .pba-members-kpi-value {
            font-size: 28px;
            line-height: 1.1;
            font-weight: 700;
            color: #102a43;
        }
        .pba-members-card {
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(14, 46, 76, 0.05);
            overflow: hidden;
        }
        .pba-members-toolbar {
            padding: 18px;
            border-bottom: 1px solid #e5edf5;
            background: #fbfdff;
        }
        .pba-members-search {
            display: grid;
            grid-template-columns: minmax(220px, 2.2fr) minmax(180px, 1fr) minmax(120px, 140px) auto auto;
            gap: 12px;
            align-items: end;
        }
        .pba-members-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #607487;
        }
        .pba-members-field input[type="text"],
        .pba-members-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cdd9e5;
            border-radius: 12px;
            background: #ffffff;
            color: #17324a;
            box-sizing: border-box;
        }
        .pba-members-btn,
        .pba-member-edit-btn {
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
        .pba-members-btn:hover,
        .pba-member-edit-btn:hover {
            background: #0b3154;
            border-color: #0b3154;
            transform: translateY(-1px);
            color: #fff;
        }
        .pba-members-btn.secondary,
        .pba-member-edit-btn.secondary {
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
        .pba-members-btn.secondary:hover,
        .pba-member-edit-btn.secondary:hover {
            background: #f3f8fc;
            color: #0b3154;
            border-color: #9fb8cd;
            box-shadow: 0 4px 12px rgba(13, 59, 102, 0.10);
            transform: translateY(-1px);
        }
        .pba-members-btn.secondary:focus,
        .pba-member-edit-btn.secondary:focus {
            outline: 2px solid #9fc2df;
            outline-offset: 2px;
        }
        .pba-members-resultsbar {
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
        .pba-members-filter-summary {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .pba-members-chip,
        .pba-members-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .pba-members-chip {
            background: #eef4fa;
            color: #31536f;
        }
        .pba-members-badge {
            background: #eef3f8;
            color: #21425c;
        }
        .pba-members-badge.status-active,
        .pba-members-badge.status-yes {
            background: #eaf7ef;
            color: #21633f;
        }
        .pba-members-badge.status-inactive {
            background: #f7eee7;
            color: #8f4a1f;
        }
        .pba-members-grid-wrap {
            position: relative;
            overflow-x: auto;
            overflow-y: visible;
        }
        .pba-members-grid-wrap.is-loading::after {
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
        .pba-members-table {
            width: 100%;
            min-width: 1160px;
            border-collapse: separate;
            border-spacing: 0;
        }
        .pba-members-table th,
        .pba-members-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f7;
            background: #fff;
        }
        .pba-members-table tbody tr:hover td {
            background: #fbfdff;
        }
        .pba-members-table th {
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
        .pba-members-table th:first-child { border-top-left-radius: 12px; }
        .pba-members-table th:last-child { border-top-right-radius: 12px; }
        .pba-members-table td strong {
            color: #17324a;
            font-size: 15px;
        }
        .pba-members-muted {
            color: #647b8d;
            font-size: 13px;
        }
        .pba-members-sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pba-members-sort-link:hover {
            color: #0d3b66;
        }
        .pba-members-sort-indicator {
            display: inline-block;
            min-width: 14px;
            color: #0d3b66;
        }
        .pba-members-empty {
            padding: 34px 20px;
            text-align: center;
            color: #5f7386;
        }
        .pba-members-pagination {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 16px 18px 18px;
            flex-wrap: wrap;
        }
        .pba-members-page-links {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pba-members-page-link {
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
        .pba-members-page-link:hover {
            background: #f5f9fd;
            color: #17324a;
        }
        .pba-members-page-link.current {
            background: #0d3b66;
            border-color: #0d3b66;
            color: #fff;
        }
        .pba-members-skeleton {
            display: none;
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 18px;
        }
        .pba-members-grid-wrap.is-loading .pba-members-skeleton {
            display: grid;
        }
        .pba-members-skeleton-line {
            height: 14px;
            border-radius: 999px;
            background: linear-gradient(90deg, #edf3f8 0%, #f7fafc 50%, #edf3f8 100%);
            background-size: 200% 100%;
            animation: pba-members-shimmer 1.4s linear infinite;
        }
        .pba-members-pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .pba-members-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef4fa;
            color: #31536f;
            font-size: 12px;
            font-weight: 700;
        }
        @keyframes pba-members-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @media (max-width: 1080px) {
            .pba-members-search {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
        @media (max-width: 680px) {
            .pba-members-hero {
                padding: 20px;
            }
            .pba-members-search {
                grid-template-columns: 1fr;
            }
            .pba-members-pagination {
                align-items: flex-start;
            }
        }

        .pba-member-edit-wrap {
            max-width: 1200px;
            margin: 0 auto;
            color: #17324a;
        }
        .pba-member-summary {
            margin: 0 0 24px;
            padding: 18px;
            border: 1px solid #d7d7d7;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .pba-member-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px 24px;
            margin-top: 14px;
        }
        .pba-member-summary-item strong {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }
        .pba-member-edit-form table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid #dde7f0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(14, 46, 76, 0.05);
        }
        .pba-member-edit-form th,
        .pba-member-edit-form td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }
        .pba-member-edit-form th {
            width: 220px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #607487;
            background: #fbfdff;
        }
        .pba-member-edit-input,
        .pba-member-edit-select {
            width: 360px;
            max-width: 100%;
            padding: 10px 12px;
            border: 1px solid #cdd9e5;
            border-radius: 12px;
            background: #ffffff;
            color: #17324a;
            box-sizing: border-box;
        }
        .pba-committee-box {
            margin-bottom: 10px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            background: #fbfdff;
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
    <?php
    return ob_get_clean();
}

function pba_render_members_list_view() {
    $request_args = pba_get_members_list_request_args();
    $data = pba_get_members_list_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load members.</p>';
    }

    ob_start();
    echo pba_render_members_admin_styles();
    ?>
    <div class="pba-members-wrap">
        <?php echo pba_render_members_status_message(); ?>

        <div id="pba-members-admin-root">
            <?php echo pba_render_members_dynamic_content($data, $request_args); ?>
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById('pba-members-admin-root');

            if (!root || !window.fetch || !window.URL) {
                return;
            }

            function getForm() {
                return root.querySelector('#pba-members-search-form');
            }

            function getShell() {
                return root.querySelector('.pba-members-admin-list-shell');
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

                var pageLinks = root.querySelectorAll('[data-members-ajax-link="1"]');
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

                var wrap = shell.querySelector('.pba-members-grid-wrap');
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
                parsed.searchParams.set('pba_members_partial', '1');

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

function pba_get_members_server_sortable_columns() {
    return array('name', 'email', 'status', 'last_modified');
}

function pba_get_members_hybrid_sort_columns() {
    return array('household', 'roles', 'committees');
}

function pba_build_members_person_query_args($request_args, $include_paging = true, $include_sort = true) {
    $query_args = array(
        'select' => 'person_id,household_id,first_name,last_name,email_address,status,last_modified_at',
    );

    if ($request_args['status_filter'] !== '') {
        $query_args['status'] = 'eq.' . $request_args['status_filter'];
    }

    if ($request_args['search'] !== '') {
        $search = trim((string) $request_args['search']);
        $escaped = str_replace('*', '', $search);
        $query_args['or'] = '(first_name.ilike.*' . $escaped . '*,last_name.ilike.*' . $escaped . '*,email_address.ilike.*' . $escaped . '*)';
    }

    if ($include_sort) {
        switch ($request_args['sort']) {
            case 'email':
                $query_args['order'] = 'email_address.' . $request_args['direction'];
                break;
            case 'status':
                $query_args['order'] = 'status.' . $request_args['direction'] . ',last_name.asc,first_name.asc';
                break;
            case 'last_modified':
                $query_args['order'] = 'last_modified_at.' . $request_args['direction'];
                break;
            case 'name':
            default:
                $query_args['order'] = 'last_name.' . $request_args['direction'] . ',first_name.' . $request_args['direction'];
                break;
        }
    } else {
        $query_args['order'] = 'last_name.asc,first_name.asc';
    }

    if ($include_paging) {
        $offset = max(0, ((int) $request_args['page'] - 1) * (int) $request_args['per_page']);
        $query_args['limit'] = (int) $request_args['per_page'];
        $query_args['offset'] = $offset;
    }

    return $query_args;
}

function pba_get_members_list_data($request_args) {
    $filter_options = pba_get_members_filter_options();

    if (in_array($request_args['sort'], pba_get_members_server_sortable_columns(), true)) {
        return pba_get_members_list_data_server_mode($request_args, $filter_options);
    }

    return pba_get_members_list_data_hybrid_mode($request_args, $filter_options);
}

function pba_get_members_list_data_server_mode($request_args, $filter_options) {
    $meta = pba_supabase_get(
        'Person',
        pba_build_members_person_query_args($request_args, true, true),
        array(
            'return_meta' => true,
            'count'       => 'exact',
        )
    );

    if (is_wp_error($meta) || !is_array($meta) || !isset($meta['rows'])) {
        return is_wp_error($meta) ? $meta : new WP_Error('pba_members_load_failed', 'Unable to load members.');
    }

    $base_rows = array_map('pba_prepare_members_list_base_row', $meta['rows']);
    $page_rows = pba_enrich_members_page_rows($base_rows);
    $total_rows = isset($meta['count']) && $meta['count'] !== null ? (int) $meta['count'] : count($page_rows);

    $pagination = pba_build_members_pagination_from_total(
        $total_rows,
        (int) $request_args['page'],
        (int) $request_args['per_page'],
        count($page_rows)
    );

    return array(
        'all_rows'       => array(),
        'filtered_rows'  => array(),
        'page_rows'      => $page_rows,
        'filter_options' => $filter_options,
        'pagination'     => $pagination,
        'total_filtered' => $total_rows,
    );
}

function pba_get_members_list_data_hybrid_mode($request_args, $filter_options) {
    $meta = pba_supabase_get(
        'Person',
        pba_build_members_person_query_args($request_args, false, false),
        array(
            'return_meta' => true,
            'count'       => 'exact',
        )
    );

    if (is_wp_error($meta) || !is_array($meta) || !isset($meta['rows'])) {
        return is_wp_error($meta) ? $meta : new WP_Error('pba_members_load_failed', 'Unable to load members.');
    }

    $base_rows = array_map('pba_prepare_members_list_base_row', $meta['rows']);
    $enriched_rows = pba_enrich_members_page_rows($base_rows);

    $sorted_rows = pba_sort_members_hybrid_rows($enriched_rows, $request_args['sort'], $request_args['direction']);
    $pagination = pba_paginate_members_rows($sorted_rows, $request_args['page'], $request_args['per_page']);

    return array(
        'all_rows'       => array(),
        'filtered_rows'  => array(),
        'page_rows'      => $pagination['rows'],
        'filter_options' => $filter_options,
        'pagination'     => $pagination,
        'total_filtered' => isset($meta['count']) && $meta['count'] !== null ? (int) $meta['count'] : count($sorted_rows),
    );
}

function pba_prepare_members_list_base_row($row) {
    $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
    $first_name = trim((string) ($row['first_name'] ?? ''));
    $last_name = trim((string) ($row['last_name'] ?? ''));
    $display_name = trim($first_name . ' ' . $last_name);
    $email_address = trim((string) ($row['email_address'] ?? ''));
    $status = trim((string) ($row['status'] ?? ''));
    $last_modified_raw = (string) ($row['last_modified_at'] ?? '');
    $last_modified_timestamp = $last_modified_raw !== '' ? strtotime($last_modified_raw) : false;
    $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;

    return array(
        'person_id'               => $person_id,
        'first_name'              => $first_name,
        'last_name'               => $last_name,
        'display_name'            => $display_name,
        'display_name_sort'       => strtolower(trim($last_name . ' ' . $first_name)),
        'email_address'           => $email_address,
        'status'                  => $status,
        'household_id'            => $household_id,
        'household_label'         => '',
        'household_label_sort'    => '',
        'roles'                   => array(),
        'roles_label'             => '',
        'committees'              => array(),
        'committees_label'        => '',
        'last_modified_raw'       => $last_modified_raw,
        'last_modified_timestamp' => $last_modified_timestamp ? (int) $last_modified_timestamp : 0,
    );
}

function pba_enrich_members_page_rows($rows) {
    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $person_ids = array();
    $household_ids = array();

    foreach ($rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;

        if ($person_id > 0) {
            $person_ids[] = $person_id;
        }

        if ($household_id > 0) {
            $household_ids[] = $household_id;
        }
    }

    $person_ids = array_values(array_unique($person_ids));
    $household_ids = array_values(array_unique($household_ids));

    $household_labels = pba_get_household_labels_map_for_members($household_ids);
    $roles_map = pba_get_role_names_map_for_people($person_ids);
    $committees_map = pba_get_committee_labels_map_for_people($person_ids);

    foreach ($rows as $index => $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;

        $household_label = isset($household_labels[$household_id]) ? $household_labels[$household_id] : '';
        $roles = isset($roles_map[$person_id]) ? $roles_map[$person_id] : array();
        $committees = isset($committees_map[$person_id]) ? $committees_map[$person_id] : array();

        $rows[$index]['household_label'] = $household_label;
        $rows[$index]['household_label_sort'] = strtolower($household_label);
        $rows[$index]['roles'] = $roles;
        $rows[$index]['roles_label'] = !empty($roles) ? implode(', ', $roles) : '';
        $rows[$index]['committees'] = $committees;
        $rows[$index]['committees_label'] = !empty($committees) ? implode(', ', $committees) : '';
    }

    return $rows;
}

function pba_get_household_labels_map_for_members($household_ids) {
    $household_ids = array_values(array_unique(array_map('intval', (array) $household_ids)));
    $household_ids = array_filter($household_ids, function ($id) {
        return $id > 0;
    });

    if (empty($household_ids)) {
        return array();
    }

    $rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,pb_street_number,pb_street_name',
        'household_id' => 'in.(' . implode(',', $household_ids) . ')',
        'limit'        => count($household_ids),
    ));

    if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
        return array();
    }

    $map = array();

    foreach ($rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1) {
            continue;
        }

        $map[$household_id] = trim(((string) ($row['pb_street_number'] ?? '')) . ' ' . ((string) ($row['pb_street_name'] ?? '')));
    }

    return $map;
}

function pba_get_role_names_map_for_people($person_ids) {
    $person_ids = array_values(array_unique(array_map('intval', (array) $person_ids)));
    $person_ids = array_filter($person_ids, function ($id) {
        return $id > 0;
    });

    if (empty($person_ids)) {
        return array();
    }

    $rows = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_id,role_id',
        'person_id' => 'in.(' . implode(',', $person_ids) . ')',
        'is_active' => 'eq.true',
        'limit'     => max(count($person_ids) * 12, count($person_ids)),
    ));

    $map = array();
    foreach ($person_ids as $person_id) {
        $map[$person_id] = array();
    }

    if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
        return $map;
    }

    $role_ids = array();
    $role_ids_by_person = array();

    foreach ($rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $role_id = isset($row['role_id']) ? (int) $row['role_id'] : 0;

        if ($person_id < 1 || $role_id < 1) {
            continue;
        }

        if (!isset($role_ids_by_person[$person_id])) {
            $role_ids_by_person[$person_id] = array();
        }

        $role_ids_by_person[$person_id][] = $role_id;
        $role_ids[] = $role_id;
    }

    $role_ids = array_values(array_unique($role_ids));

    if (empty($role_ids)) {
        return $map;
    }

    $role_rows = pba_supabase_get('Role', array(
        'select'  => 'role_id,role_name',
        'role_id' => 'in.(' . implode(',', $role_ids) . ')',
        'limit'   => count($role_ids),
    ));

    if (is_wp_error($role_rows) || !is_array($role_rows) || empty($role_rows)) {
        return $map;
    }

    $role_name_map = array();

    foreach ($role_rows as $role_row) {
        $role_id = isset($role_row['role_id']) ? (int) $role_row['role_id'] : 0;
        $role_name = isset($role_row['role_name']) ? (string) $role_row['role_name'] : '';
        if ($role_id > 0 && $role_name !== '') {
            $role_name_map[$role_id] = $role_name;
        }
    }

    foreach ($role_ids_by_person as $person_id => $role_ids_for_person) {
        $names = array();

        foreach ($role_ids_for_person as $role_id) {
            if (isset($role_name_map[$role_id])) {
                $names[] = $role_name_map[$role_id];
            }
        }

        sort($names);
        $map[$person_id] = array_values(array_unique($names));
    }

    return $map;
}

function pba_get_committee_labels_map_for_people($person_ids) {
    $person_ids = array_values(array_unique(array_map('intval', (array) $person_ids)));
    $person_ids = array_filter($person_ids, function ($id) {
        return $id > 0;
    });

    if (empty($person_ids)) {
        return array();
    }

    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_id,committee_id,committee_role',
        'person_id' => 'in.(' . implode(',', $person_ids) . ')',
        'is_active' => 'eq.true',
        'limit'     => max(count($person_ids) * 12, count($person_ids)),
    ));

    $map = array();
    foreach ($person_ids as $person_id) {
        $map[$person_id] = array();
    }

    if (is_wp_error($rows) || !is_array($rows) || empty($rows)) {
        return $map;
    }

    $committee_ids = array();
    $rows_by_person = array();

    foreach ($rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;

        if ($person_id < 1 || $committee_id < 1) {
            continue;
        }

        if (!isset($rows_by_person[$person_id])) {
            $rows_by_person[$person_id] = array();
        }

        $rows_by_person[$person_id][] = array(
            'committee_id'   => $committee_id,
            'committee_role' => isset($row['committee_role']) ? (string) $row['committee_role'] : '',
        );

        $committee_ids[] = $committee_id;
    }

    $committee_ids = array_values(array_unique($committee_ids));

    if (empty($committee_ids)) {
        return $map;
    }

    $committee_rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_id,committee_name',
        'committee_id' => 'in.(' . implode(',', $committee_ids) . ')',
        'limit'        => count($committee_ids),
    ));

    if (is_wp_error($committee_rows) || !is_array($committee_rows) || empty($committee_rows)) {
        return $map;
    }

    $committee_name_map = array();

    foreach ($committee_rows as $committee_row) {
        $committee_id = isset($committee_row['committee_id']) ? (int) $committee_row['committee_id'] : 0;
        $committee_name = isset($committee_row['committee_name']) ? (string) $committee_row['committee_name'] : '';
        if ($committee_id > 0 && $committee_name !== '') {
            $committee_name_map[$committee_id] = $committee_name;
        }
    }

    foreach ($rows_by_person as $person_id => $committee_rows_for_person) {
        $labels = array();

        foreach ($committee_rows_for_person as $committee_item) {
            $committee_id = (int) $committee_item['committee_id'];
            if (!isset($committee_name_map[$committee_id])) {
                continue;
            }

            $label = $committee_name_map[$committee_id];
            $committee_role = trim((string) $committee_item['committee_role']);
            if ($committee_role !== '') {
                $label .= ' (' . $committee_role . ')';
            }

            $labels[] = $label;
        }

        sort($labels);
        $map[$person_id] = array_values(array_unique($labels));
    }

    return $map;
}

function pba_get_members_filter_options() {
    $rows = pba_supabase_get('Person', array(
        'select' => 'status',
        'order'  => 'status.asc',
    ));

    $statuses = array();

    if (!is_wp_error($rows) && is_array($rows)) {
        foreach ($rows as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            if ($status !== '') {
                $statuses[$status] = $status;
            }
        }
    }

    natcasesort($statuses);

    return array(
        'statuses' => array_values($statuses),
    );
}

function pba_sort_members_hybrid_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'household':
                $value_a = strtolower((string) ($a['household_label'] ?? ''));
                $value_b = strtolower((string) ($b['household_label'] ?? ''));
                break;
            case 'roles':
                $value_a = strtolower((string) ($a['roles_label'] ?? ''));
                $value_b = strtolower((string) ($b['roles_label'] ?? ''));
                break;
            case 'committees':
                $value_a = strtolower((string) ($a['committees_label'] ?? ''));
                $value_b = strtolower((string) ($b['committees_label'] ?? ''));
                break;
            default:
                $value_a = '';
                $value_b = '';
                break;
        }

        if ($value_a === $value_b) {
            $fallback_a = (int) ($a['person_id'] ?? 0);
            $fallback_b = (int) ($b['person_id'] ?? 0);
            return $fallback_a <=> $fallback_b;
        }

        $comparison = ($value_a < $value_b) ? -1 : 1;
        return $direction === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
}

function pba_build_members_pagination_from_total($total_rows, $page, $per_page, $page_row_count) {
    $total_rows = max(0, (int) $total_rows);
    $per_page = max(1, (int) $per_page);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $current_page = min(max(1, (int) $page), $total_pages);
    $offset = ($current_page - 1) * $per_page;

    return array(
        'rows'         => array(),
        'total_rows'   => $total_rows,
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'per_page'     => $per_page,
        'offset'       => $offset,
        'start_number' => $total_rows > 0 ? ($offset + 1) : 0,
        'end_number'   => $total_rows > 0 ? min($offset + (int) $page_row_count, $total_rows) : 0,
    );
}

function pba_paginate_members_rows($rows, $page, $per_page) {
    $total_rows = count($rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $current_page = min(max(1, (int) $page), $total_pages);
    $offset = ($current_page - 1) * $per_page;
    $page_rows = array_slice($rows, $offset, $per_page);

    return array(
        'rows'         => $page_rows,
        'total_rows'   => $total_rows,
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'per_page'     => $per_page,
        'offset'       => $offset,
        'start_number' => $total_rows > 0 ? ($offset + 1) : 0,
        'end_number'   => $total_rows > 0 ? min($offset + $per_page, $total_rows) : 0,
    );
}

function pba_render_members_dynamic_content($data, $request_args) {
    ob_start();
    ?>
    <div class="pba-members-hero">
        <div class="pba-members-hero-top">
            <div>
                <p>View and manage member records, roles, committee assignments, and invite status with a faster, richer table experience.</p>
            </div>
        </div>

        <div class="pba-members-kpis">
            <div class="pba-members-kpi">
                <span class="pba-members-kpi-label">Filtered Members</span>
                <span class="pba-members-kpi-value"><?php echo esc_html(number_format_i18n($data['total_filtered'])); ?></span>
            </div>
            <div class="pba-members-kpi">
                <span class="pba-members-kpi-label">On This Page</span>
                <span class="pba-members-kpi-value"><?php echo esc_html(number_format_i18n(count($data['page_rows']))); ?></span>
            </div>
            <div class="pba-members-kpi">
                <span class="pba-members-kpi-label">Page</span>
                <span class="pba-members-kpi-value"><?php echo esc_html(number_format_i18n($data['pagination']['current_page'])); ?> / <?php echo esc_html(number_format_i18n($data['pagination']['total_pages'])); ?></span>
            </div>
            <div class="pba-members-kpi">
                <span class="pba-members-kpi-label">Page Size</span>
                <span class="pba-members-kpi-value"><?php echo esc_html(number_format_i18n($request_args['per_page'])); ?></span>
            </div>
        </div>
    </div>

    <div class="pba-members-card">
        <div class="pba-members-toolbar">
            <form method="get" class="pba-members-search" id="pba-members-search-form">
                <input type="hidden" name="member_page" value="1">
                <input type="hidden" name="member_sort" value="<?php echo esc_attr($request_args['sort']); ?>">
                <input type="hidden" name="member_direction" value="<?php echo esc_attr($request_args['direction']); ?>">

                <div class="pba-members-field">
                    <label for="member_search">Search</label>
                    <input type="text" id="member_search" name="member_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Name or email">
                </div>

                <div class="pba-members-field">
                    <label for="member_status_filter">Status</label>
                    <select id="member_status_filter" name="member_status_filter">
                        <option value="">All statuses</option>
                        <?php foreach ($data['filter_options']['statuses'] as $status_option) : ?>
                            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($request_args['status_filter'], $status_option); ?>><?php echo esc_html($status_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pba-members-field">
                    <label for="member_per_page">Rows</label>
                    <select id="member_per_page" name="member_per_page">
                        <option value="25" <?php selected($request_args['per_page'], 25); ?>>25</option>
                        <option value="50" <?php selected($request_args['per_page'], 50); ?>>50</option>
                        <option value="100" <?php selected($request_args['per_page'], 100); ?>>100</option>
                    </select>
                </div>

                <button type="submit" class="pba-members-btn">Apply</button>
                <a href="<?php echo esc_url(pba_get_members_base_url()); ?>" class="pba-members-btn secondary">Reset</a>
            </form>
        </div>

        <div class="pba-members-admin-list-shell">
            <?php echo pba_render_members_list_shell($data, $request_args); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_members_list_shell($data, $request_args) {
    ob_start();

    $pagination = $data['pagination'];
    $page_rows = $data['page_rows'];
    ?>
    <div class="pba-members-resultsbar">
        <div>
            Showing <?php echo esc_html(number_format_i18n($pagination['start_number'])); ?>–<?php echo esc_html(number_format_i18n($pagination['end_number'])); ?> of <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?> members
        </div>
        <div class="pba-members-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-members-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['status_filter'] !== '') : ?>
                <span class="pba-members-chip">Status: <?php echo esc_html($request_args['status_filter']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="pba-members-grid-wrap" aria-live="polite">
        <div class="pba-members-skeleton" aria-hidden="true">
            <div class="pba-members-skeleton-line"></div>
            <div class="pba-members-skeleton-line"></div>
            <div class="pba-members-skeleton-line"></div>
            <div class="pba-members-skeleton-line"></div>
        </div>

        <table class="pba-members-table">
            <thead>
                <tr>
                    <?php echo pba_render_members_sortable_th('Name', 'name', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Email', 'email', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Status', 'status', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Household', 'household', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Roles', 'roles', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Committees', 'committees', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Last Modified', 'last_modified', $request_args); ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="8" class="pba-members-empty">No members found for the current filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($page_rows as $row) : ?>
                        <?php
                        $person_id = (int) ($row['person_id'] ?? 0);
                        $roles = (array) ($row['roles'] ?? array());
                        $committees = (array) ($row['committees'] ?? array());
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(($row['display_name'] ?? '') !== '' ? $row['display_name'] : ('Member #' . $person_id)); ?></strong>
                                <div class="pba-members-muted">Member ID <?php echo esc_html((string) $person_id); ?></div>
                            </td>
                            <td><?php echo esc_html($row['email_address'] ?? ''); ?></td>
                            <td><?php echo pba_render_members_status_badge($row['status'] ?? ''); ?></td>
                            <td><?php echo esc_html(($row['household_label'] ?? '') !== '' ? $row['household_label'] : '—'); ?></td>
                            <td>
                                <?php if (!empty($roles)) : ?>
                                    <div class="pba-members-pill-list">
                                        <?php foreach ($roles as $role_name) : ?>
                                            <span class="pba-members-pill"><?php echo esc_html($role_name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($committees)) : ?>
                                    <div class="pba-members-pill-list">
                                        <?php foreach ($committees as $committee_label) : ?>
                                            <span class="pba-members-pill"><?php echo esc_html($committee_label); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(pba_format_datetime_display($row['last_modified_raw'] ?? '')); ?></td>
                            <td>
                                <a class="pba-members-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'member_view' => 'edit',
                                    'member_id'   => $person_id,
                                ), pba_get_members_base_url())); ?>">Manage &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo pba_render_members_pagination($pagination); ?>
    <?php

    return ob_get_clean();
}

function pba_render_members_sortable_th($label, $column, $request_args) {
    $is_current = $request_args['sort'] === $column;
    $next_direction = ($is_current && $request_args['direction'] === 'asc') ? 'desc' : 'asc';
    $indicator = '↕';

    if ($is_current) {
        $indicator = $request_args['direction'] === 'asc' ? '↑' : '↓';
    }

    $url = pba_get_members_list_url(array(
        'member_sort'      => $column,
        'member_direction' => $next_direction,
        'member_page'      => 1,
    ));

    return '<th><a class="pba-members-sort-link" data-members-ajax-link="1" href="' . esc_url($url) . '">' . esc_html($label) . '<span class="pba-members-sort-indicator">' . esc_html($indicator) . '</span></a></th>';
}

function pba_render_members_status_badge($status) {
    $status = trim((string) $status);
    $normalized = strtolower($status);
    $class = 'status-unknown';

    if ($normalized === 'active') {
        $class = 'status-active';
    } elseif ($normalized === 'disabled' || $normalized === 'inactive' || $normalized === 'expired') {
        $class = 'status-inactive';
    }

    return '<span class="pba-members-badge ' . esc_attr($class) . '">' . esc_html($status !== '' ? $status : '—') . '</span>';
}

function pba_render_members_pagination($pagination) {
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
    <div class="pba-members-pagination">
        <div class="pba-members-muted">
            Page <?php echo esc_html(number_format_i18n($current_page)); ?> of <?php echo esc_html(number_format_i18n($total_pages)); ?>
        </div>
        <div class="pba-members-page-links">
            <?php if ($current_page > 1) : ?>
                <a class="pba-members-page-link" data-members-ajax-link="1" href="<?php echo esc_url(pba_get_members_list_url(array('member_page' => $current_page - 1))); ?>">Prev</a>
            <?php endif; ?>

            <?php
            $last_rendered = 0;
            foreach ($pages_to_show as $page_number) :
                if ($last_rendered > 0 && $page_number > ($last_rendered + 1)) {
                    echo '<span class="pba-members-muted">…</span>';
                }

                $classes = 'pba-members-page-link';
                if ($page_number === $current_page) {
                    $classes .= ' current';
                }
                ?>
                <a class="<?php echo esc_attr($classes); ?>" data-members-ajax-link="1" href="<?php echo esc_url(pba_get_members_list_url(array('member_page' => $page_number))); ?>"><?php echo esc_html(number_format_i18n($page_number)); ?></a>
                <?php
                $last_rendered = $page_number;
            endforeach;
            ?>

            <?php if ($current_page < $total_pages) : ?>
                <a class="pba-members-page-link" data-members-ajax-link="1" href="<?php echo esc_url(pba_get_members_list_url(array('member_page' => $current_page + 1))); ?>">Next</a>
            <?php endif; ?>
        </div>
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
    $status = (string) ($member['status'] ?? '');
    $display_name = trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? '')));

    ob_start();
    echo pba_render_members_admin_styles();
    ?>
    <div class="pba-member-edit-wrap">
        <p>
            <a class="pba-member-edit-btn secondary" href="<?php echo esc_url(pba_get_members_base_url()); ?>">Back to Members</a>
        </p>

        <?php echo pba_render_members_status_message(); ?>

        <div class="pba-member-summary">
            <h3 style="margin:0;"><?php echo esc_html($display_name !== '' ? $display_name : ('Member #' . (int) $member['person_id'])); ?></h3>
            <div class="pba-member-summary-grid">
                <div class="pba-member-summary-item">
                    <strong>Status</strong>
                    <div><?php echo esc_html($status !== '' ? $status : '—'); ?></div>
                </div>
                <div class="pba-member-summary-item">
                    <strong>Linked WP User ID</strong>
                    <div><?php echo esc_html($wp_user_id !== '' ? $wp_user_id : 'Not linked'); ?></div>
                </div>
                <div class="pba-member-summary-item">
                    <strong>Email Verified</strong>
                    <div><?php echo esc_html($email_verified); ?></div>
                </div>
                <div class="pba-member-summary-item">
                    <strong>Last Modified</strong>
                    <div><?php echo esc_html(pba_format_datetime_display($member['last_modified_at'] ?? '')); ?></div>
                </div>
                <?php if (is_array($invite_data) && !empty($invite_data['expires_at_gmt'])) : ?>
                    <div class="pba-member-summary-item">
                        <strong>Invite Expires</strong>
                        <div><?php echo esc_html(date('m/d/y h:i A', (int) $invite_data['expires_at_gmt'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="pba-member-actions">
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
                <a class="pba-member-edit-btn secondary" href="<?php echo esc_url(pba_get_members_base_url()); ?>">Cancel</a>
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

if (isset($_GET['pba_members_partial']) && sanitize_text_field(wp_unslash($_GET['pba_members_partial'])) === '1') {
    add_action('template_redirect', 'pba_maybe_render_members_partial');
}

function pba_maybe_render_members_partial() {
    if (!is_user_logged_in()) {
        return;
    }

    if (!pba_current_person_has_role('PBAAdmin')) {
        return;
    }

    $view = isset($_GET['member_view']) ? sanitize_text_field(wp_unslash($_GET['member_view'])) : 'list';
    if ($view !== 'list') {
        return;
    }

    $request_args = pba_get_members_list_request_args();
    $data = pba_get_members_list_data($request_args);

    if (is_wp_error($data)) {
        wp_die('Unable to load members.', 500);
    }

    echo pba_render_members_dynamic_content($data, $request_args);
    exit;
}
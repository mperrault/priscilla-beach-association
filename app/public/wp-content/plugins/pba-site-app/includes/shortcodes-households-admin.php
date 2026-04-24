<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/pba-admin-list-ui.php';

add_action('init', 'pba_register_households_admin_shortcode');
add_action('admin_post_pba_save_household_admin', 'pba_handle_save_household_admin');

if (isset($_GET['pba_households_partial']) && sanitize_text_field(wp_unslash($_GET['pba_households_partial'])) === '1') {
    add_action('template_redirect', 'pba_maybe_render_households_partial');
}

function pba_maybe_render_households_partial() {
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

    pba_households_admin_enqueue_styles();

    $request_args = pba_get_households_admin_list_request_args();
    $data = pba_get_households_admin_list_data($request_args);

    if (is_wp_error($data)) {
        wp_die('Unable to load households.', 500);
    }

    echo pba_render_households_admin_dynamic_content($data, $request_args);
    exit;
}

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

    pba_households_admin_enqueue_styles();

    $view = isset($_GET['household_view']) ? sanitize_text_field(wp_unslash($_GET['household_view'])) : 'list';
    $household_id = isset($_GET['household_id']) ? absint($_GET['household_id']) : 0;

    if ($view === 'edit' && $household_id > 0) {
        return pba_render_household_admin_edit_view($household_id);
    }

    return pba_render_households_admin_list_view();
}

function pba_households_admin_enqueue_styles() {
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

    wp_enqueue_style(
        'pba-households-admin-styles',
        $base_url . 'pba-households-admin.css',
        array('pba-admin-list-styles'),
        file_exists($base_path . 'pba-households-admin.css') ? (string) filemtime($base_path . 'pba-households-admin.css') : '1.0.0'
    );
}

function pba_render_households_admin_status_message() {
    $status = isset($_GET['pba_households_status']) ? sanitize_text_field(wp_unslash($_GET['pba_households_status'])) : '';

    if ($status === '') {
        return '';
    }

    $success_messages = array(
        'household_saved' => 'Household saved successfully.',
    );

    $error_messages = array(
        'invalid_request' => 'We could not process that request.',
        'save_failed'     => 'We could not save that household.',
    );

    if (isset($success_messages[$status])) {
        $text = $success_messages[$status];

        if (function_exists('pba_shared_render_message')) {
            return pba_shared_render_message('success', 'Success', $text);
        }

        return '<div class="pba-households-message">' . esc_html($text) . '</div>';
    }

    $text = isset($error_messages[$status])
        ? $error_messages[$status]
        : ucfirst(str_replace('_', ' ', $status));

    if (function_exists('pba_shared_render_message')) {
        return pba_shared_render_message('error', 'Please review', $text);
    }

    return '<div class="pba-households-message error">' . esc_html($text) . '</div>';
}

function pba_get_households_admin_list_request_args() {
    $allowed_sort_columns = array(
        'address',
        'owner',
        'house_admin',
        'status_members',
        'owner_occupied',
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
    global $post;

    if ($post instanceof WP_Post) {
        $permalink = get_permalink($post);
        if (!empty($permalink)) {
            return $permalink;
        }
    }

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
        html.pba-households-cursor-reset,
        html.pba-households-cursor-reset *,
        body.pba-households-cursor-reset,
        body.pba-households-cursor-reset *,
        #pba-households-admin-root,
        #pba-households-admin-root * {
            cursor: auto !important;
        }

        #pba-households-admin-root.is-busy,
        #pba-households-admin-root.is-busy * {
            cursor: wait !important;
        }
    </style>

    <div class="pba-households-wrap pba-page-wrap">
        <?php echo pba_render_households_admin_status_message(); ?>

        <div id="pba-households-admin-root">
            <?php echo pba_render_households_admin_dynamic_content($data, $request_args); ?>
        </div>
    </div>

    <?php
    echo pba_admin_list_render_resizable_table_script();

    echo pba_admin_list_render_ajax_script(array(
        'root_id' => 'pba-households-admin-root',
        'form_id' => 'pba-households-search-form',
        'shell_selector' => '.pba-households-admin-list-shell',
        'loading_selector' => '.pba-admin-list-grid-wrap',
        'ajax_link_attr' => 'data-households-ajax-link',
        'partial_param' => 'pba_households_partial',
    ));
    ?>
    <script>
    (function () {
        var root = document.getElementById('pba-households-admin-root');

        function clearCursorEverywhere() {
            var nodes;
            var i;

            document.documentElement.classList.add('pba-households-cursor-reset');
            document.body.classList.add('pba-households-cursor-reset');

            document.documentElement.style.cursor = '';
            document.body.style.cursor = '';

            document.documentElement.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting');
            document.body.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting', 'pba-table-resizing');

            if (root) {
                root.classList.remove('is-busy');
            }

            nodes = document.querySelectorAll('[style*="cursor"]');
            for (i = 0; i < nodes.length; i++) {
                nodes[i].style.cursor = '';
            }
        }

        function setBusy() {
            if (root) {
                root.classList.add('is-busy');
            }
        }

        function scheduleClear() {
            clearCursorEverywhere();
            window.requestAnimationFrame(clearCursorEverywhere);
            window.setTimeout(clearCursorEverywhere, 0);
            window.setTimeout(clearCursorEverywhere, 150);
            window.setTimeout(clearCursorEverywhere, 500);
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-households-ajax-link="1"]');
            if (link) {
                setBusy();
            }
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target.closest('#pba-households-search-form');
            if (form) {
                setBusy();
            }
        }, true);

        if (window.MutationObserver && root) {
            new MutationObserver(function () {
                scheduleClear();
            }).observe(root, { childList: true, subtree: true });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scheduleClear);
        } else {
            scheduleClear();
        }

        window.addEventListener('load', scheduleClear);
        window.addEventListener('pageshow', scheduleClear);
        window.addEventListener('focus', scheduleClear);
    })();
    </script>
    <?php

    return ob_get_clean();
}

function pba_render_households_admin_dynamic_content($data, $request_args) {
    $status_options = $data['status_options'];
    $pagination = $data['pagination'];

    ob_start();
    ?>
    <div class="pba-households-admin-list-shell">
        <div class="pba-admin-list-hero">
            <div class="pba-admin-list-hero-top">
                <div>
                    <p>Manage household records, track member counts, and review household registration readiness.</p>
                </div>
                <div class="pba-admin-list-badge">
                    Total Households: <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?>
                </div>
            </div>

            <div class="pba-admin-list-kpis">
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Active</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['summary']['active'])); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Inactive</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['summary']['inactive'])); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Owner Occupied</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['summary']['owner_occupied_yes'])); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Non Owner Occupied</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['summary']['owner_occupied_no'])); ?></span>
                </div>
            </div>
        </div>

        <div class="pba-admin-list-card">
            <form id="pba-households-search-form" class="pba-admin-list-toolbar pba-households-toolbar-compact" method="get" action="<?php echo esc_url(pba_get_households_admin_base_url()); ?>">
                <div class="pba-admin-list-toolbar-grid pba-households-toolbar-grid">
                    <div class="pba-admin-list-field">
                        <label for="pba-household-search">Search</label>
                        <input id="pba-household-search" type="text" name="household_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Search by address, owner, or household admin">
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-household-status-filter">Status</label>
                        <select id="pba-household-status-filter" name="household_status_filter">
                            <option value="">All statuses</option>
                            <?php foreach ($status_options as $status_option) : ?>
                                <option value="<?php echo esc_attr($status_option); ?>" <?php selected($request_args['status_filter'], $status_option); ?>>
                                    <?php echo esc_html($status_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-household-owner-occupied">Owner Occupied</label>
                        <select id="pba-household-owner-occupied" name="household_owner_occupied">
                            <option value="">All</option>
                            <option value="yes" <?php selected($request_args['owner_occupied_filter'], 'yes'); ?>>Yes</option>
                            <option value="no" <?php selected($request_args['owner_occupied_filter'], 'no'); ?>>No</option>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-household-per-page">Rows per page</label>
                        <select id="pba-household-per-page" name="household_per_page">
                            <?php foreach (array(25, 50, 100) as $per_page_option) : ?>
                                <option value="<?php echo esc_attr((string) $per_page_option); ?>" <?php selected($request_args['per_page'], $per_page_option); ?>>
                                    <?php echo esc_html(number_format_i18n($per_page_option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="pba-admin-list-toolbar-actions">
                    <button type="submit" class="pba-admin-list-btn">Apply Filters</button>
                    <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(pba_get_households_admin_base_url()); ?>">Reset</a>
                </div>
            </form>

            <?php echo pba_render_households_admin_table($data, $request_args); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_households_admin_table($data, $request_args) {
    $pagination = $data['pagination'];
    $page_rows = $data['page_rows'];

    ob_start();
    ?>
    <div class="pba-admin-list-resultsbar">
        <div>
            Showing <?php echo esc_html(number_format_i18n($pagination['start_number'])); ?>–<?php echo esc_html(number_format_i18n($pagination['end_number'])); ?> of <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?> households
        </div>
        <div class="pba-admin-list-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-admin-list-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['status_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Status: <?php echo esc_html($request_args['status_filter']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['owner_occupied_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Owner Occupied: <?php echo esc_html(ucfirst($request_args['owner_occupied_filter'])); ?></span>
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

        <table class="pba-admin-list-table pba-table pba-households-table pba-resizable-table" id="pba-households-admin-table" data-resize-key="pbaHouseholdsAdminColumnWidthsV3" data-min-col-width="100">
        <colgroup data-pba-resizable-cols="1">
                <col style="width: 141px;">
                <col style="width: 100px;">
                <col style="width: 100px;">
                <col style="width: 100px;">
                <col style="width: 100px;">
                <col style="width: 150px;">
            </colgroup>
            <thead>
                <tr>
                    <?php echo pba_render_households_admin_sortable_th('Address', 'address', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Owner', 'owner', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('House Admin', 'house_admin', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Status / Members', 'status_members', $request_args); ?>
                    <?php echo pba_render_households_admin_sortable_th('Owner Occupied', 'owner_occupied', $request_args); ?>
                    <th data-resizable="false">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="6" class="pba-admin-list-empty">No households found for the current filters.</td>
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
                        $active_count = (int) ($row['active_count'] ?? 0);
                        $total_count = (int) ($row['total_count'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($address !== '' ? $address : ('Household #' . $household_id)); ?></strong>
                                <div class="pba-admin-list-muted">Household ID <?php echo esc_html((string) $household_id); ?></div>
                            </td>
                            <td><?php echo esc_html($owner_name_raw !== '' ? $owner_name_raw : '—'); ?></td>
                            <td><?php echo esc_html($display_house_admin !== '' ? $display_house_admin : '—'); ?></td>
                            <td>
                                <div class="pba-households-status-members-stack">
                                    <?php echo pba_render_households_admin_status_badge($household_status); ?>
                                    <div class="pba-admin-list-muted"><?php echo esc_html(number_format_i18n($active_count)); ?> active / <?php echo esc_html(number_format_i18n($total_count)); ?> total</div>
                                </div>
                            </td>
                            <td><?php echo pba_render_households_admin_owner_occupied_badge($owner_occupied); ?></td>
                            <td>
                                <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(add_query_arg(array(
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
    $next_direction = ($request_args['sort'] === $column && $request_args['direction'] === 'asc') ? 'desc' : 'asc';

    $url = pba_get_households_admin_list_url(array(
        'household_sort' => $column,
        'household_direction' => $next_direction,
        'household_page' => 1,
    ));

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $request_args['sort'],
        'current_direction' => $request_args['direction'],
        'url' => $url,
        'link_attr' => 'data-households-ajax-link',
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_households_admin_pagination($pagination) {
    return pba_admin_list_render_pagination(array(
        'pagination' => $pagination,
        'url_builder' => function ($overrides) {
            return pba_get_households_admin_list_url($overrides);
        },
        'page_param' => 'household_page',
        'container_class' => 'pba-admin-list-pagination',
        'muted_class' => 'pba-admin-list-muted',
        'links_class' => 'pba-admin-list-page-links',
    ));
}

function pba_get_households_admin_list_data($request_args) {
    $household_rows = pba_supabase_get('Household', array(
        'select' => 'household_id,pb_street_number,pb_street_name,household_admin_first_name,household_admin_last_name,household_admin_email_address,household_status,last_modified_at,owner_name_raw,owner_occupied',
        'limit'  => 5000,
    ));

    if (is_wp_error($household_rows)) {
        return $household_rows;
    }

    $household_rows = is_array($household_rows) ? $household_rows : array();

    $household_ids = array();
    foreach ($household_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id > 0) {
            $household_ids[] = $household_id;
        }
    }

    $household_ids = array_values(array_unique($household_ids));
    $counts_map = pba_get_households_admin_member_counts($household_ids);

    $rows = array();
    $summary = array(
        'active' => 0,
        'inactive' => 0,
        'owner_occupied_yes' => 0,
        'owner_occupied_no' => 0,
    );

    foreach ($household_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        $street_number = trim((string) ($row['pb_street_number'] ?? ''));
        $street_name = trim((string) ($row['pb_street_name'] ?? ''));
        $address = trim($street_number . ' ' . $street_name);
        $owner_name_raw = trim((string) ($row['owner_name_raw'] ?? ''));
        $house_admin_first_name = trim((string) ($row['household_admin_first_name'] ?? ''));
        $house_admin_last_name = trim((string) ($row['household_admin_last_name'] ?? ''));
        $household_status = trim((string) ($row['household_status'] ?? ''));
        $display_house_admin = trim($house_admin_first_name . ' ' . $house_admin_last_name);
        $owner_occupied = array_key_exists('owner_occupied', $row) ? $row['owner_occupied'] : null;

        if ($household_status === 'Active') {
            $summary['active']++;
        } else {
            $summary['inactive']++;
        }

        if ($owner_occupied === true || $owner_occupied === 'true' || $owner_occupied === 1 || $owner_occupied === '1') {
            $summary['owner_occupied_yes']++;
        } else {
            $summary['owner_occupied_no']++;
        }

        $counts = isset($counts_map[$household_id]) ? $counts_map[$household_id] : array(
            'active_count' => 0,
            'total_count' => 0,
        );

        $rows[] = array(
            'household_id' => $household_id,
            'address' => $address,
            'owner_name_raw' => $owner_name_raw,
            'display_house_admin' => $display_house_admin,
            'household_status' => $household_status,
            'active_count' => (int) ($counts['active_count'] ?? 0),
            'total_count' => (int) ($counts['total_count'] ?? 0),
            'owner_occupied' => $owner_occupied,
            'last_modified_raw' => (string) ($row['last_modified_at'] ?? ''),
        );
    }

    $rows = pba_filter_households_admin_rows($rows, $request_args);
    $rows = pba_sort_households_admin_rows($rows, $request_args['sort'], $request_args['direction']);

    $pagination = pba_paginate_households_admin_rows($rows, $request_args['page'], $request_args['per_page']);
    $page_rows = array_slice($rows, $pagination['offset'], $pagination['per_page']);

    $status_options = array();
    foreach ($household_rows as $row) {
        $status = trim((string) ($row['household_status'] ?? ''));
        if ($status !== '') {
            $status_options[$status] = $status;
        }
    }
    natcasesort($status_options);

    return array(
        'summary' => $summary,
        'rows' => $rows,
        'page_rows' => $page_rows,
        'pagination' => $pagination,
        'status_options' => array_values($status_options),
    );
}

function pba_filter_households_admin_rows($rows, $request_args) {
    $search = strtolower(trim((string) ($request_args['search'] ?? '')));
    $status_filter = trim((string) ($request_args['status_filter'] ?? ''));
    $owner_occupied_filter = trim((string) ($request_args['owner_occupied_filter'] ?? ''));

    return array_values(array_filter($rows, function ($row) use ($search, $status_filter, $owner_occupied_filter) {
        if ($status_filter !== '' && (string) ($row['household_status'] ?? '') !== $status_filter) {
            return false;
        }

        if ($owner_occupied_filter !== '') {
            $is_owner_occupied = ($row['owner_occupied'] === true || $row['owner_occupied'] === 'true' || $row['owner_occupied'] === 1 || $row['owner_occupied'] === '1');
            if (($owner_occupied_filter === 'yes' && !$is_owner_occupied) || ($owner_occupied_filter === 'no' && $is_owner_occupied)) {
                return false;
            }
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['address'] ?? ''),
                (string) ($row['owner_name_raw'] ?? ''),
                (string) ($row['display_house_admin'] ?? ''),
                (string) ($row['household_status'] ?? ''),
            )));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }));
}

function pba_get_household_admin_edit_record($household_id) {
    $rows = pba_supabase_get('Household', array(
        'select' => '*',
        'household_id' => 'eq.' . (int) $household_id,
        'limit' => 1,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (empty($rows) || !is_array($rows)) {
        return array();
    }

    return $rows[0];
}

function pba_get_household_admin_edit_members($household_id) {
    $rows = pba_supabase_get('Person', array(
        'select'       => 'person_id,first_name,last_name,email_address,status,household_id',
        'household_id' => 'eq.' . (int) $household_id,
        'limit'        => 500,
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    foreach ($rows as &$row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $roles = array();

        if (function_exists('pba_get_active_supabase_role_names_for_person')) {
            $roles = pba_get_active_supabase_role_names_for_person($person_id);
        } elseif (function_exists('pba_get_active_role_names_for_person')) {
            $roles = pba_get_active_role_names_for_person($person_id);
        }

        $row['role_names'] = is_array($roles) ? $roles : array();
        $row['roles_display'] = !empty($row['role_names']) ? implode(', ', $row['role_names']) : '—';
    }
    unset($row);

    $sort = isset($_GET['member_sort']) ? sanitize_key(wp_unslash($_GET['member_sort'])) : 'name';
    $direction = isset($_GET['member_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['member_direction']))) : 'asc';

    if (!in_array($sort, array('name', 'email', 'status', 'roles'), true)) {
        $sort = 'name';
    }

    if (!in_array($direction, array('asc', 'desc'), true)) {
        $direction = 'asc';
    }

    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'email':
                $a_value = strtolower((string) ($a['email_address'] ?? ''));
                $b_value = strtolower((string) ($b['email_address'] ?? ''));
                break;

            case 'status':
                $a_value = strtolower((string) ($a['status'] ?? ''));
                $b_value = strtolower((string) ($b['status'] ?? ''));
                break;

            case 'roles':
                $a_value = strtolower((string) ($a['roles_display'] ?? ''));
                $b_value = strtolower((string) ($b['roles_display'] ?? ''));
                break;

            case 'name':
            default:
                $a_value = strtolower(trim(((string) ($a['last_name'] ?? '')) . ' ' . ((string) ($a['first_name'] ?? ''))));
                $b_value = strtolower(trim(((string) ($b['last_name'] ?? '')) . ' ' . ((string) ($b['first_name'] ?? ''))));
                break;
        }

        $result = strcmp($a_value, $b_value);

        return ($direction === 'desc') ? -$result : $result;
    });

    return $rows;
}

function pba_sort_households_admin_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        $a_value = '';
        $b_value = '';

        switch ($sort) {
            case 'owner':
                $a_value = strtolower((string) ($a['owner_name_raw'] ?? ''));
                $b_value = strtolower((string) ($b['owner_name_raw'] ?? ''));
                break;
            case 'house_admin':
                $a_value = strtolower((string) ($a['display_house_admin'] ?? ''));
                $b_value = strtolower((string) ($b['display_house_admin'] ?? ''));
                break;
            case 'status_members':
                $a_value = strtolower((string) ($a['household_status'] ?? '')) . '|' . (int) ($a['active_count'] ?? 0) . '|' . (int) ($a['total_count'] ?? 0);
                $b_value = strtolower((string) ($b['household_status'] ?? '')) . '|' . (int) ($b['active_count'] ?? 0) . '|' . (int) ($b['total_count'] ?? 0);
                break;
            case 'owner_occupied':
                $a_value = ($a['owner_occupied'] === true || $a['owner_occupied'] === 'true' || $a['owner_occupied'] === 1 || $a['owner_occupied'] === '1') ? 1 : 0;
                $b_value = ($b['owner_occupied'] === true || $b['owner_occupied'] === 'true' || $b['owner_occupied'] === 1 || $b['owner_occupied'] === '1') ? 1 : 0;
                break;
            case 'address':
            default:
                $a_value = strtolower((string) ($a['address'] ?? ''));
                $b_value = strtolower((string) ($b['address'] ?? ''));
                break;
        }

        if ($a_value === $b_value) {
            return 0;
        }

        $result = ($a_value < $b_value) ? -1 : 1;

        return ($direction === 'desc') ? -$result : $result;
    });

    return $rows;
}

function pba_paginate_households_admin_rows($rows, $page, $per_page) {
    $total_rows = count($rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = min(max(1, (int) $page), $total_pages);
    $offset = ($page - 1) * $per_page;

    $start_number = $total_rows > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total_rows);

    return array(
        'page' => $page,
        'per_page' => $per_page,
        'offset' => $offset,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'start_number' => $start_number,
        'end_number' => $end_number,
    );
}

function pba_get_households_admin_member_counts($household_ids) {
    $counts = array();

    foreach ($household_ids as $household_id) {
        $counts[(int) $household_id] = array(
            'active_count' => 0,
            'total_count' => 0,
        );
    }

    if (empty($household_ids)) {
        return $counts;
    }

    $people_rows = pba_supabase_get('Person', array(
        'select' => 'person_id,household_id,status',
        'household_id' => 'in.(' . implode(',', array_map('intval', $household_ids)) . ')',
        'limit' => 10000,
    ));

    if (is_wp_error($people_rows) || !is_array($people_rows)) {
        return $counts;
    }

    foreach ($people_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1 || !isset($counts[$household_id])) {
            continue;
        }

        $counts[$household_id]['total_count']++;

        if ((string) ($row['status'] ?? '') === 'Active') {
            $counts[$household_id]['active_count']++;
        }
    }

    return $counts;
}

function pba_render_households_admin_status_badge($status) {
    $status = trim((string) $status);
    $class = 'default';

    if ($status === 'Active') {
        $class = 'accepted';
    } elseif ($status === 'Inactive') {
        $class = 'disabled';
    }

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($status !== '' ? $status : 'Unknown', $class);
    }

    return '<span class="pba-status-badge ' . esc_attr($class) . '">' . esc_html($status !== '' ? $status : 'Unknown') . '</span>';
}

function pba_render_households_admin_owner_occupied_badge($value) {
    $is_yes = ($value === true || $value === 'true' || $value === 1 || $value === '1');
    $label = $is_yes ? 'Yes' : 'No';
    $class = $is_yes ? 'accepted' : 'default';

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($label, $class);
    }

    return '<span class="pba-status-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

function pba_render_household_admin_members_sortable_th($label, $column) {
    $current_sort = isset($_GET['member_sort']) ? sanitize_key(wp_unslash($_GET['member_sort'])) : 'name';
    $current_direction = isset($_GET['member_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['member_direction']))) : 'asc';

    if (!in_array($current_sort, array('name', 'email', 'status', 'roles'), true)) {
        $current_sort = 'name';
    }

    if (!in_array($current_direction, array('asc', 'desc'), true)) {
        $current_direction = 'asc';
    }

    $next_direction = ($current_sort === $column && $current_direction === 'asc') ? 'desc' : 'asc';

    $url = add_query_arg(array(
        'member_sort'      => $column,
        'member_direction' => $next_direction,
    )) . '#pba-household-members-section';

    if (function_exists('pba_admin_list_render_sortable_th')) {
        return pba_admin_list_render_sortable_th(array(
            'label'             => $label,
            'column'            => $column,
            'current_sort'      => $current_sort,
            'current_direction' => $current_direction,
            'url'               => $url,
            'link_class'        => 'pba-admin-list-sort-link',
            'indicator_class'   => 'pba-admin-list-sort-indicator',
        ));
    }

    $indicator = '';
    if ($current_sort === $column) {
        $indicator = $current_direction === 'asc' ? ' ▲' : ' ▼';
    }

    return '<th><a class="pba-admin-list-sort-link" href="' . esc_url($url) . '">' . esc_html($label . $indicator) . '</a></th>';
}

function pba_format_household_admin_currency($value) {
    if ($value === null || $value === '') {
        return '—';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    return '$' . number_format((float) $value, 2, '.', ',');
}

function pba_render_household_admin_raw_value($value) {
    $value = trim((string) $value);
    return $value !== '' ? $value : '—';
}

function pba_render_household_admin_edit_view($household_id) {
    $household = pba_get_household_admin_edit_record($household_id);

    if (is_wp_error($household) || empty($household)) {
        return '<p>Unable to load that household.</p>';
    }

    $member_rows = pba_get_household_admin_edit_members($household_id);

    $street_address = trim(((string) ($household['pb_street_number'] ?? '')) . ' ' . ((string) ($household['pb_street_name'] ?? '')));
    $city = trim((string) ($household['city'] ?? 'Plymouth'));
    $state = trim((string) ($household['state'] ?? 'MA'));
    $household_address_parts = array_filter(array($street_address, trim($city . ', ' . $state)));
    $household_address = implode(', ', $household_address_parts);

    if ($street_address === '') {
        $household_address = 'Address not set';
    }

    $status = isset($_GET['pba_households_status'])
        ? sanitize_text_field(wp_unslash($_GET['pba_households_status']))
        : '';

    ob_start();
    ?>
    <div class="pba-household-detail-wrap pba-page-wrap">
        <div class="pba-household-detail-header">
            <div>
                <h2 class="pba-page-title" style="margin:0;">Manage Household: <?php echo esc_html($household_address); ?></h2>
                <p class="pba-page-intro" style="margin-top:8px;">Review household ownership, registration readiness, members, and association details.</p>
            </div>
            <div>
                <a class="pba-btn secondary" href="<?php echo esc_url(pba_get_households_admin_base_url()); ?>">&larr; Back to Households</a>
            </div>
        </div>

        <?php if ($status === 'household_saved') : ?>
            <div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #34a853;border-radius:10px;background:#e6f4ea;color:#137333;font-weight:700;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#34a853;color:#fff;font-size:20px;font-weight:800;line-height:1;">✓</span>
                <span>Household updated successfully.</span>
            </div>
        <?php elseif ($status === 'save_failed') : ?>
            <div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #d93025;border-radius:10px;background:#fdecea;color:#a50e0e;font-weight:700;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#d93025;color:#fff;font-size:20px;font-weight:800;line-height:1;">!</span>
                <span>Household update failed. Please try again.</span>
            </div>
        <?php endif; ?>

        <details class="pba-household-detail-section pba-section" open>
            <summary>Admin &amp; Contact</summary>
            <div class="pba-household-detail-body">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-auth-form">
                    <?php wp_nonce_field('pba_save_household_admin', 'pba_household_admin_nonce'); ?>
                    <input type="hidden" name="action" value="pba_save_household_admin">
                    <input type="hidden" name="household_id" value="<?php echo esc_attr((string) $household_id); ?>">

                    <div class="pba-form-grid">
                        <div class="pba-field">
                            <label for="pba-household-admin-first-name">Household Admin First Name</label>
                            <input id="pba-household-admin-first-name" type="text" name="household_admin_first_name" value="<?php echo esc_attr((string) ($household['household_admin_first_name'] ?? '')); ?>">
                        </div>

                        <div class="pba-field">
                            <label for="pba-household-admin-last-name">Household Admin Last Name</label>
                            <input id="pba-household-admin-last-name" type="text" name="household_admin_last_name" value="<?php echo esc_attr((string) ($household['household_admin_last_name'] ?? '')); ?>">
                        </div>

                        <div class="pba-field">
                            <label for="pba-household-admin-email-address">Household Admin Email Address</label>
                            <input id="pba-household-admin-email-address" type="email" name="household_admin_email_address" value="<?php echo esc_attr((string) ($household['household_admin_email_address'] ?? '')); ?>">
                        </div>

                        <div class="pba-field">
                            <label for="pba-household-status">Household Status</label>
                            <select id="pba-household-status" name="household_status">
                                <?php foreach (array('Active', 'Inactive') as $status_option) : ?>
                                    <option value="<?php echo esc_attr($status_option); ?>" <?php selected((string) ($household['household_status'] ?? ''), $status_option); ?>>
                                        <?php echo esc_html($status_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <p class="pba-maintenance-actions">
                        <button type="submit" class="pba-btn">Save Household</button>
                    </p>
                </form>
            </div>
        </details>

        <details id="pba-household-members-section" class="pba-household-detail-section pba-section" open>
            <summary>Members</summary>
            <div class="pba-household-detail-body">
                <div class="pba-admin-list-grid-wrap">
                    <table class="pba-household-members-table pba-admin-list-table pba-table pba-resizable-table" id="pba-household-members-table" data-resize-key="pbaHouseholdMembersColumnWidthsV5" data-min-col-width="80">
                        <colgroup data-pba-resizable-cols="1">
                            <col style="width: 170px;">
                            <col style="width: 220px;">
                            <col style="width: 105px;">
                            <col style="width: 210px;">
                            <col style="width: 150px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <?php echo pba_render_household_admin_members_sortable_th('Name', 'name'); ?>
                                <?php echo pba_render_household_admin_members_sortable_th('Email', 'email'); ?>
                                <?php echo pba_render_household_admin_members_sortable_th('Status', 'status'); ?>
                                <?php echo pba_render_household_admin_members_sortable_th('Roles', 'roles'); ?>
                                <th style="text-align:left;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($member_rows)) : ?>
                                <tr>
                                    <td colspan="5" class="pba-admin-list-empty">No members found for this household.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($member_rows as $member) : ?>
                                    <?php
                                    $person_id = isset($member['person_id']) ? (int) $member['person_id'] : 0;
                                    $name = trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? '')));
                                    $roles = isset($member['role_names']) && is_array($member['role_names']) ? $member['role_names'] : array();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($name !== '' ? $name : 'Unnamed member'); ?></strong>
                                            <div class="pba-admin-list-muted">Person ID <?php echo esc_html((string) $person_id); ?></div>
                                        </td>
                                        <td><?php echo esc_html((string) ($member['email_address'] ?? '')); ?></td>
                                        <td><?php echo pba_render_households_admin_status_badge($member['status'] ?? ''); ?></td>
                                        <td><?php echo esc_html(!empty($roles) ? implode(', ', $roles) : '—'); ?></td>
                                        <td style="text-align:left;white-space:nowrap;">
                                            <a class="pba-households-btn pba-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                                'member_view' => 'edit',
                                                'person_id'   => $person_id,
                                            ), home_url('/members/'))); ?>">Edit Member</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section" open>
            <summary>Association &amp; Ownership</summary>
            <div class="pba-household-detail-body">
                <table class="pba-table pba-household-display-table">
                    <tbody>
                        <tr>
                            <th>Owner Name</th>
                            <td><?php echo esc_html((string) ($household['owner_name_raw'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th>Owner Occupied</th>
                            <td><?php echo pba_render_households_admin_owner_occupied_badge($household['owner_occupied'] ?? null); ?></td>
                        </tr>
                        <tr>
                            <th>Invite Policy</th>
                            <td><?php echo esc_html('Allowed'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section" open>
            <summary>Property Identifiers</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table pba-table">
                    <tbody>
                        <tr><th>Assessor Book Raw</th><td><?php echo esc_html(pba_render_household_admin_raw_value($household['assessor_book_raw'] ?? '')); ?></td></tr>
                        <tr><th>Assessor Page Raw</th><td><?php echo esc_html(pba_render_household_admin_raw_value($household['assessor_page_raw'] ?? '')); ?></td></tr>
                        <tr><th>Property ID</th><td><?php echo esc_html(pba_render_household_admin_raw_value($household['property_id'] ?? '')); ?></td></tr>
                        <tr><th>Location ID</th><td><?php echo esc_html(pba_render_household_admin_raw_value($household['location_id'] ?? '')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section" open>
            <summary>Correspondence</summary>
            <div class="pba-household-detail-body">
                <table class="pba-table pba-household-display-table">
                    <tbody>
                        <tr>
                            <th>Correspondence Address</th>
                            <td><?php echo nl2br(esc_html((string) ($household['correspondence_address'] ?? ''))); ?></td>
                        </tr>
                        <tr>
                            <th>Owner Address</th>
                            <td><?php echo nl2br(esc_html((string) ($household['owner_address_text'] ?? ''))); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section">
            <summary>Valuation</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table pba-table">
                    <tbody>
                        <tr><th>Building Value</th><td><?php echo esc_html(pba_format_household_admin_currency($household['building_value'] ?? null)); ?></td></tr>
                        <tr><th>Land Value</th><td><?php echo esc_html(pba_format_household_admin_currency($household['land_value'] ?? null)); ?></td></tr>
                        <tr><th>Other Value</th><td><?php echo esc_html(pba_format_household_admin_currency($household['other_value'] ?? null)); ?></td></tr>
                        <tr><th>Total Value</th><td><?php echo esc_html(pba_format_household_admin_currency($household['total_value'] ?? null)); ?></td></tr>
                        <tr><th>Assessment FY</th><td><?php echo esc_html((string) ($household['assessment_fy'] ?? '')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section">
            <summary>Property Details</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table pba-table">
                    <tbody>
                        <tr><th>Lot Size Acres</th><td><?php echo esc_html((string) ($household['lot_size_acres'] ?? '')); ?></td></tr>
                        <tr><th>Last Sale Date</th><td><?php echo esc_html((string) ($household['last_sale_date'] ?? '')); ?></td></tr>
                        <tr><th>Last Sale Price</th><td><?php echo esc_html((string) ($household['last_sale_price'] ?? '')); ?></td></tr>
                        <tr><th>Year Built</th><td><?php echo esc_html((string) ($household['year_built'] ?? '')); ?></td></tr>
                        <tr><th>Living Area Sqft</th><td><?php echo esc_html((string) ($household['living_area_sqft'] ?? '')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="pba-household-detail-section pba-section">
            <summary>Mailing &amp; Notes</summary>
            <div class="pba-household-detail-body">
                <table class="pba-household-display-table pba-table">
                    <tbody>
                        <tr><th>Notes</th><td><?php echo nl2br(esc_html((string) ($household['notes'] ?? ''))); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
    <?php
    echo pba_admin_list_render_resizable_table_script();

    return ob_get_clean();
}

function pba_handle_save_household_admin() {
    if (!is_user_logged_in() || (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles'))) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', pba_get_households_admin_base_url()));
        exit;
    }

    check_admin_referer('pba_save_household_admin', 'pba_household_admin_nonce');

    $household_id = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;

    if ($household_id < 1) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', pba_get_households_admin_base_url()));
        exit;
    }

    $payload = array(
        'household_admin_first_name' => isset($_POST['household_admin_first_name']) ? sanitize_text_field(wp_unslash($_POST['household_admin_first_name'])) : '',
        'household_admin_last_name' => isset($_POST['household_admin_last_name']) ? sanitize_text_field(wp_unslash($_POST['household_admin_last_name'])) : '',
        'household_admin_email_address' => isset($_POST['household_admin_email_address']) ? sanitize_email(wp_unslash($_POST['household_admin_email_address'])) : '',
        'household_status' => isset($_POST['household_status']) ? sanitize_text_field(wp_unslash($_POST['household_status'])) : 'Active',
        'last_modified_at' => gmdate('c'),
    );

    $result = pba_supabase_update('Household', $payload, array(
        'household_id' => 'eq.' . $household_id,
    ));

    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg(array(
            'household_view' => 'edit',
            'household_id' => $household_id,
            'pba_households_status' => 'save_failed',
        ), pba_get_households_admin_base_url()));
        exit;
    }

    wp_safe_redirect(add_query_arg(array(
        'household_view' => 'edit',
        'household_id' => $household_id,
        'pba_households_status' => 'household_saved',
    ), pba_get_households_admin_base_url()));
    exit;
}
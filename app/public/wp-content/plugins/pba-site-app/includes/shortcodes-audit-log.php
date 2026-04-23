<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/pba-admin-list-ui.php';

add_action('init', 'pba_register_audit_log_shortcode');

function pba_register_audit_log_shortcode() {
    add_shortcode('pba_audit_log', 'pba_render_audit_log_shortcode');
}

function pba_render_audit_log_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    $can_access = current_user_can('pba_manage_roles') || current_user_can('pba_view_board_docs');

    if (!$can_access && function_exists('pba_get_current_person_role_names')) {
        $role_names = pba_get_current_person_role_names();

        if (is_array($role_names)) {
            $can_access =
                in_array('pba_admin', $role_names, true) ||
                in_array('pba_board_member', $role_names, true) ||
                in_array('PBAAdmin', $role_names, true) ||
                in_array('PBABoardMember', $role_names, true);
        }
    }

    if (!$can_access && function_exists('pba_current_person_has_role')) {
        $can_access =
            pba_current_person_has_role('PBAAdmin') ||
            pba_current_person_has_role('PBABoardMember');
    }

    if (!$can_access && function_exists('pba_current_user_can_access_admin_area')) {
        $can_access = pba_current_user_can_access_admin_area();
    }

    if (!$can_access) {
        return '<p>You do not have permission to access this page.</p>';
    }

    pba_audit_log_enqueue_styles();

    $request_args = pba_get_audit_log_request_args();
    $data = pba_get_audit_log_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load audit log records right now.</p>'
            . '<pre style="white-space:pre-wrap;background:#f6f6f6;padding:12px;border:1px solid #ddd;">'
            . esc_html($data->get_error_message())
            . '</pre>';
    }

    ob_start();
    ?>
    <div class="pba-audit-log-wrap pba-page-wrap">
        <?php echo pba_render_audit_log_dynamic_content($data, $request_args); ?>
    </div>
    <?php

    echo pba_admin_list_render_resizable_table_script();

    return ob_get_clean();
}

function pba_audit_log_enqueue_styles() {
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

    wp_add_inline_style('pba-admin-list-styles', pba_get_audit_log_inline_css());
}

function pba_get_audit_log_inline_css() {
    return <<<CSS
.pba-audit-log-wrap {
    max-width: 1680px;
    margin: 0 auto;
}

.pba-audit-log-admin-list-shell,
.pba-audit-log-wrap .pba-admin-list-card {
    width: 100%;
}

.pba-audit-log-wrap .pba-admin-list-grid-wrap {
    overflow-x: auto;
}

.pba-audit-log-toolbar-grid {
    grid-template-columns: minmax(280px, 1.5fr) minmax(220px, 0.9fr) minmax(220px, 0.9fr) minmax(160px, 0.7fr);
    gap: 12px;
}

.pba-audit-log-toolbar-grid .pba-admin-list-field input[type="text"],
.pba-audit-log-toolbar-grid .pba-admin-list-field select {
    min-height: 42px;
    padding: 9px 12px;
}

#pba-audit-log-table {
    width: 100%;
    min-width: 1420px;
}

#pba-audit-log-table .pba-col-resizer,
#pba-audit-log-table .pba-col-resizer:hover,
#pba-audit-log-table .pba-col-resizer:active {
    cursor: col-resize !important;
}

#pba-audit-log-table th {
    position: relative;
}

#pba-audit-log-table td:last-child .pba-admin-list-btn {
    min-width: 120px;
}

.pba-audit-log-result-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
}

.pba-audit-log-summary-cell {
    color: #334e68;
    line-height: 1.45;
}

.pba-audit-log-summary-cell .pba-admin-list-muted {
    display: block;
    margin-top: 6px;
}

@media (max-width: 1200px) {
    .pba-audit-log-toolbar-grid {
        grid-template-columns: repeat(2, minmax(240px, 1fr));
    }
}

@media (max-width: 760px) {
    .pba-audit-log-toolbar-grid {
        grid-template-columns: 1fr;
    }
}
CSS;
}

function pba_get_audit_log_base_url() {
    global $post;

    if ($post instanceof WP_Post) {
        $permalink = get_permalink($post);
        if (!empty($permalink)) {
            return $permalink;
        }
    }

    return home_url('/audit-log/');
}

function pba_get_audit_log_request_args() {
    $search = isset($_GET['audit_search']) ? sanitize_text_field(wp_unslash($_GET['audit_search'])) : '';
    $action_filter = isset($_GET['audit_action_filter']) ? sanitize_text_field(wp_unslash($_GET['audit_action_filter'])) : '';
    $result_filter = isset($_GET['audit_result_filter']) ? sanitize_text_field(wp_unslash($_GET['audit_result_filter'])) : '';
    $actor_filter = isset($_GET['audit_actor_filter']) ? sanitize_text_field(wp_unslash($_GET['audit_actor_filter'])) : '';
    $sort = isset($_GET['audit_sort']) ? sanitize_key(wp_unslash($_GET['audit_sort'])) : 'created_at';
    $direction = isset($_GET['audit_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['audit_direction']))) : 'desc';
    $page = isset($_GET['audit_page']) ? max(1, absint($_GET['audit_page'])) : 1;
    $per_page = isset($_GET['audit_per_page']) ? absint($_GET['audit_per_page']) : 25;

    $allowed_sort_columns = array(
        'created_at',
        'action_type',
        'actor_name',
        'entity_type',
        'entity_id',
        'result_status',
    );

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'created_at';
    }

    if (!in_array($direction, array('asc', 'desc'), true)) {
        $direction = 'desc';
    }

    if (!in_array($per_page, array(25, 50, 100), true)) {
        $per_page = 25;
    }

    return array(
        'search' => $search,
        'action_filter' => $action_filter,
        'result_filter' => $result_filter,
        'actor_filter' => $actor_filter,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $page,
        'per_page' => $per_page,
    );
}

function pba_get_audit_log_list_url($overrides = array()) {
    $args = pba_get_audit_log_request_args();

    $query_args = array(
        'audit_search' => $args['search'],
        'audit_action_filter' => $args['action_filter'],
        'audit_result_filter' => $args['result_filter'],
        'audit_actor_filter' => $args['actor_filter'],
        'audit_sort' => $args['sort'],
        'audit_direction' => $args['direction'],
        'audit_page' => $args['page'],
        'audit_per_page' => $args['per_page'],
    );

    foreach ($overrides as $key => $value) {
        $query_args[$key] = $value;
    }

    foreach ($query_args as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, pba_get_audit_log_base_url());
}

function pba_get_audit_log_data($request_args) {
    $rows = pba_supabase_get('AuditLog', array(
        'select' => 'audit_log_id,created_at,action_type,entity_type,entity_id,entity_label,actor_person_id,actor_email_address,result_status,summary,details_json',
        'order'  => 'created_at.desc',
        'limit'  => 500,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    $rows = is_array($rows) ? $rows : array();
    $rows = pba_audit_log_enrich_rows($rows);
    $rows = pba_filter_audit_log_rows($rows, $request_args);
    $rows = pba_sort_audit_log_rows($rows, $request_args['sort'], $request_args['direction']);

    $action_options = array();
    $actor_options = array();
    $result_options = array();

    foreach ($rows as $row) {
        $action_type = trim((string) ($row['action_type'] ?? ''));
        $actor_name = trim((string) ($row['actor_name'] ?? ''));
        $result_status = trim((string) ($row['result_status'] ?? ''));

        if ($action_type !== '') {
            $action_options[$action_type] = $action_type;
        }

        if ($actor_name !== '') {
            $actor_options[$actor_name] = $actor_name;
        }

        if ($result_status !== '') {
            $result_options[$result_status] = $result_status;
        }
    }

    natcasesort($action_options);
    natcasesort($actor_options);
    natcasesort($result_options);

    $summary = array(
        'total' => count($rows),
        'success' => 0,
        'failure' => 0,
        'denied' => 0,
    );

    foreach ($rows as $row) {
        $result_status = trim((string) ($row['result_status'] ?? ''));
        if ($result_status === 'success') {
            $summary['success']++;
        } elseif ($result_status === 'failure') {
            $summary['failure']++;
        } elseif ($result_status === 'denied') {
            $summary['denied']++;
        }
    }

    $pagination = pba_paginate_audit_log_rows($rows, $request_args['page'], $request_args['per_page']);
    $page_rows = array_slice($rows, $pagination['offset'], $pagination['per_page']);

    return array(
        'rows' => $rows,
        'page_rows' => $page_rows,
        'pagination' => $pagination,
        'summary' => $summary,
        'action_options' => array_values($action_options),
        'actor_options' => array_values($actor_options),
        'result_options' => array_values($result_options),
    );
}

function pba_render_audit_log_dynamic_content($data, $request_args) {
    $pagination = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : array(
        'total_rows' => 0,
        'start_number' => 0,
        'end_number' => 0,
    );

    ob_start();
    ?>
    <div class="pba-audit-log-admin-list-shell">
        <div class="pba-admin-list-hero">
            <div class="pba-admin-list-hero-top">
                <div>
                    <h2 style="margin:0 0 6px;">Audit Log</h2>
                    <p>Review recorded activity across the application.</p>
                </div>
                <div class="pba-admin-list-badge">
                    Total Records: <?php echo esc_html(number_format_i18n((int) ($pagination['total_rows'] ?? 0))); ?>
                </div>
            </div>

            <div class="pba-admin-list-kpis">
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Success</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['success'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Failure</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['failure'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Denied</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['denied'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Visible Rows</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($pagination['total_rows'] ?? 0))); ?></span>
                </div>
            </div>
        </div>

        <div class="pba-admin-list-card">
            <form id="pba-audit-log-search-form" class="pba-admin-list-toolbar" method="get" action="<?php echo esc_url(pba_get_audit_log_base_url()); ?>">
                <div class="pba-admin-list-toolbar-grid pba-audit-log-toolbar-grid">
                    <div class="pba-admin-list-field">
                        <label for="pba-audit-search">Search</label>
                        <input id="pba-audit-search" type="text" name="audit_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Search action, actor, entity, summary, or result">
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-audit-action-filter">Action</label>
                        <select id="pba-audit-action-filter" name="audit_action_filter">
                            <option value="">All actions</option>
                            <?php foreach (($data['action_options'] ?? array()) as $option) : ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['action_filter'], $option); ?>>
                                    <?php echo esc_html($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-audit-actor-filter">Actor</label>
                        <select id="pba-audit-actor-filter" name="audit_actor_filter">
                            <option value="">All actors</option>
                            <?php foreach (($data['actor_options'] ?? array()) as $option) : ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['actor_filter'], $option); ?>>
                                    <?php echo esc_html($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-audit-result-filter">Result</label>
                        <select id="pba-audit-result-filter" name="audit_result_filter">
                            <option value="">All results</option>
                            <?php foreach (($data['result_options'] ?? array()) as $option) : ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['result_filter'], $option); ?>>
                                    <?php echo esc_html(ucfirst($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-audit-per-page">Rows per page</label>
                        <select id="pba-audit-per-page" name="audit_per_page">
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
                    <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(pba_get_audit_log_base_url()); ?>">Reset</a>
                </div>
            </form>

            <?php echo pba_render_audit_log_table($data, $request_args); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_audit_log_table($data, $request_args) {
    $pagination = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : array(
        'start_number' => 0,
        'end_number' => 0,
        'total_rows' => 0,
    );
    $page_rows = isset($data['page_rows']) && is_array($data['page_rows']) ? $data['page_rows'] : array();

    ob_start();
    ?>
    <div class="pba-admin-list-resultsbar">
        <div>
            Showing <?php echo esc_html(number_format_i18n((int) ($pagination['start_number'] ?? 0))); ?>–<?php echo esc_html(number_format_i18n((int) ($pagination['end_number'] ?? 0))); ?> of <?php echo esc_html(number_format_i18n((int) ($pagination['total_rows'] ?? 0))); ?> audit records
        </div>
        <div class="pba-admin-list-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-admin-list-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['action_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Action: <?php echo esc_html($request_args['action_filter']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['actor_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Actor: <?php echo esc_html($request_args['actor_filter']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['result_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Result: <?php echo esc_html($request_args['result_filter']); ?></span>
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

        <table class="pba-admin-list-table pba-table pba-resizable-table" id="pba-audit-log-table" data-resize-key="pbaAuditLogColumnWidthsV2" data-min-col-width="100">
            <colgroup data-pba-resizable-cols="1">
                <col style="width: 185px;">
                <col style="width: 230px;">
                <col style="width: 190px;">
                <col style="width: 180px;">
                <col style="width: 110px;">
                <col style="width: 140px;">
                <col style="width: 385px;">
            </colgroup>
            <thead>
                <tr>
                    <?php echo pba_render_audit_log_sortable_th('Date / Time', 'created_at', $request_args); ?>
                    <?php echo pba_render_audit_log_sortable_th('Action', 'action_type', $request_args); ?>
                    <?php echo pba_render_audit_log_sortable_th('Actor', 'actor_name', $request_args); ?>
                    <?php echo pba_render_audit_log_sortable_th('Entity Type', 'entity_type', $request_args); ?>
                    <?php echo pba_render_audit_log_sortable_th('Entity ID', 'entity_id', $request_args); ?>
                    <?php echo pba_render_audit_log_sortable_th('Result', 'result_status', $request_args); ?>
                    <th>Summary</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="7" class="pba-admin-list-empty">No audit records found for the current filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($page_rows as $row) : ?>
                        <?php
                        $entity_id = isset($row['entity_id']) && $row['entity_id'] !== null && $row['entity_id'] !== '' ? (string) $row['entity_id'] : '—';
                        $entity_label = trim((string) ($row['entity_label'] ?? ''));
                        $result_status = trim((string) ($row['result_status'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html((string) ($row['created_at_display'] ?? '')); ?></strong>
                                <div class="pba-admin-list-muted">Audit ID <?php echo esc_html((string) ($row['audit_log_id'] ?? '')); ?></div>
                            </td>
                            <td><?php echo esc_html((string) ($row['action_type'] ?? '—')); ?></td>
                            <td><?php echo esc_html((string) ($row['actor_name'] ?? 'System')); ?></td>
                            <td>
                                <strong><?php echo esc_html((string) ($row['entity_type'] ?? '—')); ?></strong>
                                <?php if ($entity_label !== '') : ?>
                                    <div class="pba-admin-list-muted"><?php echo esc_html($entity_label); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($entity_id); ?></td>
                            <td>
                                <div class="pba-audit-log-result-stack">
                                    <?php echo pba_render_audit_log_result_badge($result_status); ?>
                                </div>
                            </td>
                            <td class="pba-audit-log-summary-cell">
                                <?php echo esc_html((string) ($row['details_summary'] ?? '—')); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo pba_render_audit_log_pagination($pagination); ?>
    <?php

    return ob_get_clean();
}

function pba_render_audit_log_sortable_th($label, $column, $request_args) {
    $next_direction = ($request_args['sort'] === $column && $request_args['direction'] === 'asc') ? 'desc' : 'asc';

    $url = pba_get_audit_log_list_url(array(
        'audit_sort' => $column,
        'audit_direction' => $next_direction,
        'audit_page' => 1,
    ));

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $request_args['sort'],
        'current_direction' => $request_args['direction'],
        'url' => $url,
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_audit_log_pagination($pagination) {
    return pba_admin_list_render_pagination(array(
        'pagination' => $pagination,
        'url_builder' => function ($overrides) {
            return pba_get_audit_log_list_url($overrides);
        },
        'page_param' => 'audit_page',
        'container_class' => 'pba-admin-list-pagination',
        'muted_class' => 'pba-admin-list-muted',
        'links_class' => 'pba-admin-list-page-links',
    ));
}

function pba_filter_audit_log_rows($rows, $request_args) {
    $search = strtolower(trim((string) ($request_args['search'] ?? '')));
    $action_filter = trim((string) ($request_args['action_filter'] ?? ''));
    $actor_filter = trim((string) ($request_args['actor_filter'] ?? ''));
    $result_filter = trim((string) ($request_args['result_filter'] ?? ''));

    return array_values(array_filter($rows, function ($row) use ($search, $action_filter, $actor_filter, $result_filter) {
        if ($action_filter !== '' && (string) ($row['action_type'] ?? '') !== $action_filter) {
            return false;
        }

        if ($actor_filter !== '' && (string) ($row['actor_name'] ?? '') !== $actor_filter) {
            return false;
        }

        if ($result_filter !== '' && (string) ($row['result_status'] ?? '') !== $result_filter) {
            return false;
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['created_at_display'] ?? ''),
                (string) ($row['action_type'] ?? ''),
                (string) ($row['actor_name'] ?? ''),
                (string) ($row['actor_email_address'] ?? ''),
                (string) ($row['entity_type'] ?? ''),
                (string) ($row['entity_id'] ?? ''),
                (string) ($row['entity_label'] ?? ''),
                (string) ($row['result_status'] ?? ''),
                (string) ($row['details_summary'] ?? ''),
            )));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }));
}

function pba_sort_audit_log_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'action_type':
                $a_value = strtolower((string) ($a['action_type'] ?? ''));
                $b_value = strtolower((string) ($b['action_type'] ?? ''));
                break;
            case 'actor_name':
                $a_value = strtolower((string) ($a['actor_name'] ?? ''));
                $b_value = strtolower((string) ($b['actor_name'] ?? ''));
                break;
            case 'entity_type':
                $a_value = strtolower((string) ($a['entity_type'] ?? ''));
                $b_value = strtolower((string) ($b['entity_type'] ?? ''));
                break;
            case 'entity_id':
                $a_value = (int) ($a['entity_id'] ?? 0);
                $b_value = (int) ($b['entity_id'] ?? 0);
                break;
            case 'result_status':
                $a_value = strtolower((string) ($a['result_status'] ?? ''));
                $b_value = strtolower((string) ($b['result_status'] ?? ''));
                break;
            case 'created_at':
            default:
                $a_value = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
                $b_value = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
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

function pba_paginate_audit_log_rows($rows, $page, $per_page) {
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

function pba_render_audit_log_result_badge($result_status) {
    $result_status = trim((string) $result_status);
    $label = $result_status !== '' ? ucfirst($result_status) : 'Unknown';
    $class = 'default';

    if ($result_status === 'success') {
        $class = 'accepted';
    } elseif ($result_status === 'failure') {
        $class = 'disabled';
    } elseif ($result_status === 'denied') {
        $class = 'pending';
    }

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($label, $class);
    }

    return '<span class="pba-status-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

function pba_audit_log_enrich_rows($rows) {
    $rows = is_array($rows) ? $rows : array();
    $person_ids = array();

    foreach ($rows as $row) {
        $person_id = isset($row['actor_person_id']) ? (int) $row['actor_person_id'] : 0;
        if ($person_id > 0) {
            $person_ids[] = $person_id;
        }
    }

    $person_ids = array_values(array_unique($person_ids));
    $people_map = array();

    if (!empty($person_ids)) {
        $people_rows = pba_supabase_get('Person', array(
            'select'    => 'person_id,first_name,last_name,email_address',
            'person_id' => 'in.(' . implode(',', array_map('intval', $person_ids)) . ')',
            'limit'     => count($person_ids),
        ));

        if (!is_wp_error($people_rows) && is_array($people_rows)) {
            foreach ($people_rows as $person_row) {
                $person_id = isset($person_row['person_id']) ? (int) $person_row['person_id'] : 0;
                if ($person_id < 1) {
                    continue;
                }

                $name = trim(
                    ((string) ($person_row['first_name'] ?? '')) . ' ' .
                    ((string) ($person_row['last_name'] ?? ''))
                );

                if ($name === '') {
                    $name = isset($person_row['email_address']) ? (string) $person_row['email_address'] : '';
                }

                $people_map[$person_id] = $name;
            }
        }
    }

    foreach ($rows as &$row) {
        $person_id = isset($row['actor_person_id']) ? (int) $row['actor_person_id'] : 0;
        $created_at = isset($row['created_at']) ? (string) $row['created_at'] : '';
        $summary = isset($row['summary']) ? trim((string) $row['summary']) : '';
        $details_json = isset($row['details_json']) ? $row['details_json'] : '';
        $fallback_email = trim((string) ($row['actor_email_address'] ?? ''));

        if ($person_id > 0 && isset($people_map[$person_id]) && $people_map[$person_id] !== '') {
            $row['actor_name'] = $people_map[$person_id];
        } elseif ($fallback_email !== '') {
            $row['actor_name'] = $fallback_email;
        } else {
            $row['actor_name'] = 'System';
        }

        $row['created_at_display'] = $created_at !== '' ? pba_format_datetime_display($created_at) : '';

        if ($summary !== '') {
            $row['details_summary'] = $summary;
        } else {
            $row['details_summary'] = pba_audit_log_details_to_summary($details_json);
        }
    }
    unset($row);

    return $rows;
}

function pba_audit_log_details_to_summary($details_json) {
    if (is_array($details_json)) {
        $decoded = $details_json;
    } else {
        $decoded = json_decode((string) $details_json, true);
    }

    if (!is_array($decoded) || empty($decoded)) {
        return '';
    }

    $parts = array();

    foreach ($decoded as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }

        $label = ucwords(str_replace(array('_', '-'), ' ', (string) $key));
        $parts[] = $label . ': ' . (string) $value;
    }

    return implode(' | ', $parts);
}
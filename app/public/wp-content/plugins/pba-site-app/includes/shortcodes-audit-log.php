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

    if (!function_exists('pba_current_person_has_role') || !pba_current_person_has_role('PBAAdmin')) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $request_args = pba_get_audit_log_request_args();
    $data = pba_get_audit_log_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load audit log.</p>';
    }

    ob_start();
    echo function_exists('pba_admin_list_render_shared_styles') ? pba_admin_list_render_shared_styles() : '';
    ?>
    <style>
        .pba-audit-log-wrap {
            width: 100%;
            max-width: none;
        }

        .pba-audit-log-wrap.pba-page-wrap {
            width: 100%;
            max-width: none;
        }

        .pba-audit-log-wrap .pba-admin-list-card,
        .pba-audit-log-wrap .pba-admin-list-hero,
        .pba-audit-log-wrap .pba-section {
            width: 100%;
            max-width: none;
            box-sizing: border-box;
        }

        .pba-audit-log-wrap .pba-admin-list-grid-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .pba-audit-log-table {
            min-width: 1400px;
            width: 100%;
            table-layout: fixed;
        }

        .pba-audit-log-table th,
        .pba-audit-log-table td {
            vertical-align: top;
            box-sizing: border-box;
        }

        .pba-audit-log-table td {
            font-size: 14px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .pba-audit-log-table th {
            position: relative;
        }

        .pba-audit-log-summary {
            max-width: 360px;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .pba-audit-log-entity,
        .pba-audit-log-actor,
        .pba-audit-log-request {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .pba-audit-log-toggle {
            min-width: 88px;
        }

        .pba-audit-log-details-row {
            display: none;
        }

        .pba-audit-log-details-row.is-open {
            display: table-row;
        }

        .pba-audit-log-details-cell {
            padding: 0 !important;
            background: #f8fbff !important;
        }

        .pba-audit-log-details-wrap {
            padding: 18px;
            border-top: 1px solid #e5edf5;
        }

        .pba-audit-log-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .pba-audit-log-details-card {
            border: 1px solid #dbe7f1;
            border-radius: 14px;
            background: #ffffff;
            overflow: hidden;
        }

        .pba-audit-log-details-card h4 {
            margin: 0;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.2;
            background: #f5f9fc;
            border-bottom: 1px solid #e3ebf3;
            color: #16324f;
        }

        .pba-audit-log-details-card pre {
            margin: 0;
            padding: 14px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 12px;
            line-height: 1.45;
            color: #17324a;
            background: #fff;
        }

        .pba-audit-log-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            background: #eef3f8;
            color: #21425c;
        }

        .pba-audit-log-badge.success {
            background: #eaf7ef;
            color: #21633f;
        }

        .pba-audit-log-badge.failure,
        .pba-audit-log-badge.denied {
            background: #f8e9e9;
            color: #8a2f2f;
        }

        .pba-audit-log-muted {
            color: #5f7386;
            font-size: 13px;
        }

        .pba-audit-log-empty-json {
            padding: 14px;
            color: #5f7386;
            font-size: 13px;
        }

        .pba-audit-log-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
        }

        .pba-audit-log-filter-grid .pba-admin-list-field input[type="date"],
        .pba-audit-log-filter-grid .pba-admin-list-field input[type="text"],
        .pba-audit-log-filter-grid .pba-admin-list-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cdd9e5;
            border-radius: 12px;
            background: #ffffff;
            color: #17324a;
            box-sizing: border-box;
        }

        .pba-audit-log-toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: end;
            margin-top: 14px;
        }

        .pba-audit-log-col-resizer {
            position: absolute;
            top: 0;
            right: -5px;
            width: 10px;
            height: 100%;
            cursor: col-resize;
            user-select: none;
            z-index: 5;
        }

        .pba-audit-log-col-resizer::before {
            content: '';
            position: absolute;
            top: 8px;
            bottom: 8px;
            left: 50%;
            width: 2px;
            transform: translateX(-50%);
            background: #d6e0ea;
            border-radius: 999px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .pba-audit-log-table th:hover .pba-audit-log-col-resizer::before,
        .pba-audit-log-col-resizer.is-active::before {
            opacity: 1;
        }

        body.pba-audit-log-resizing,
        body.pba-audit-log-resizing * {
            cursor: col-resize !important;
            user-select: none !important;
        }
    </style>

    <div class="pba-audit-log-wrap pba-page-wrap">
        <div class="pba-admin-list-hero">
            <div class="pba-admin-list-hero-top">
                <div>
                    <p>Review audited activity across members, households, committees, documents, and authentication flows. Use filters below to investigate specific actions.</p>
                </div>
            </div>
            <div class="pba-admin-list-kpis">
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Filtered Events</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['pagination']['total_items'])); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">On This Page</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n(count($data['rows']))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Page</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['pagination']['current_page'])); ?> / <?php echo esc_html(number_format_i18n($data['pagination']['total_pages'])); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Rows</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($request_args['per_page'])); ?></span>
                </div>
            </div>
        </div>

        <div class="pba-admin-list-card pba-section">
            <div class="pba-admin-list-toolbar">
                <form method="get" action="<?php echo esc_url(pba_get_audit_log_base_url()); ?>">
                    <input type="hidden" name="audit_page" value="1">
                    <input type="hidden" name="audit_sort" value="<?php echo esc_attr($request_args['sort']); ?>">
                    <input type="hidden" name="audit_direction" value="<?php echo esc_attr($request_args['direction']); ?>">

                    <div class="pba-audit-log-filter-grid">
                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_date_from">Date From</label>
                            <input type="date" id="audit_date_from" name="audit_date_from" value="<?php echo esc_attr($request_args['date_from']); ?>">
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_date_to">Date To</label>
                            <input type="date" id="audit_date_to" name="audit_date_to" value="<?php echo esc_attr($request_args['date_to']); ?>">
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_actor_person_id">Actor Person ID</label>
                            <input type="text" id="audit_actor_person_id" name="audit_actor_person_id" value="<?php echo esc_attr($request_args['actor_person_id']); ?>" placeholder="e.g. 18">
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_action_type">Action Type</label>
                            <select id="audit_action_type" name="audit_action_type">
                                <option value="">All action types</option>
                                <?php foreach ($data['filter_options']['action_types'] as $option) : ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['action_type'], $option); ?>><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_entity_type">Entity Type</label>
                            <select id="audit_entity_type" name="audit_entity_type">
                                <option value="">All entity types</option>
                                <?php foreach ($data['filter_options']['entity_types'] as $option) : ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['entity_type'], $option); ?>><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_result_status">Result</label>
                            <select id="audit_result_status" name="audit_result_status">
                                <option value="">All results</option>
                                <?php foreach ($data['filter_options']['result_statuses'] as $option) : ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($request_args['result_status'], $option); ?>><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_search">Summary Search</label>
                            <input type="text" id="audit_search" name="audit_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Search summary text">
                        </div>

                        <div class="pba-admin-list-field pba-field">
                            <label for="audit_per_page">Rows</label>
                            <select id="audit_per_page" name="audit_per_page">
                                <option value="25" <?php selected($request_args['per_page'], 25); ?>>25</option>
                                <option value="50" <?php selected($request_args['per_page'], 50); ?>>50</option>
                                <option value="100" <?php selected($request_args['per_page'], 100); ?>>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="pba-audit-log-toolbar-actions">
                        <button type="submit" class="pba-admin-list-btn pba-btn">Apply</button>
                        <a href="<?php echo esc_url(pba_get_audit_log_base_url()); ?>" class="pba-admin-list-btn pba-btn secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="pba-admin-list-resultsbar">
                <div>
                    Showing <?php echo esc_html(number_format_i18n(count($data['rows']))); ?> of <?php echo esc_html(number_format_i18n($data['pagination']['total_items'])); ?> audited events.
                </div>
                <div class="pba-admin-list-filter-summary">
                    <?php if ($request_args['date_from'] !== '') : ?>
                        <span class="pba-admin-list-chip">From: <?php echo esc_html($request_args['date_from']); ?></span>
                    <?php endif; ?>
                    <?php if ($request_args['date_to'] !== '') : ?>
                        <span class="pba-admin-list-chip">To: <?php echo esc_html($request_args['date_to']); ?></span>
                    <?php endif; ?>
                    <?php if ($request_args['action_type'] !== '') : ?>
                        <span class="pba-admin-list-chip">Action: <?php echo esc_html($request_args['action_type']); ?></span>
                    <?php endif; ?>
                    <?php if ($request_args['entity_type'] !== '') : ?>
                        <span class="pba-admin-list-chip">Entity: <?php echo esc_html($request_args['entity_type']); ?></span>
                    <?php endif; ?>
                    <?php if ($request_args['result_status'] !== '') : ?>
                        <span class="pba-admin-list-chip">Result: <?php echo esc_html($request_args['result_status']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pba-admin-list-grid-wrap">
                <table class="pba-admin-list-table pba-audit-log-table" id="pba-audit-log-table">
                    <thead>
                        <tr>
                            <?php echo pba_admin_list_render_sortable_th(array(
                                'label' => 'Timestamp',
                                'column' => 'created_at',
                                'current_sort' => $request_args['sort'],
                                'current_direction' => $request_args['direction'],
                                'url' => pba_get_audit_log_list_url(array(
                                    'audit_sort' => 'created_at',
                                    'audit_direction' => pba_get_audit_log_next_direction($request_args, 'created_at'),
                                    'audit_page' => 1,
                                )),
                                'link_attr' => 'data-audit-log-link',
                                'link_class' => 'pba-admin-list-sort-link',
                                'indicator_class' => 'pba-admin-list-sort-indicator',
                            )); ?>

                            <?php echo pba_admin_list_render_sortable_th(array(
                                'label' => 'Actor',
                                'column' => 'actor_person_id',
                                'current_sort' => $request_args['sort'],
                                'current_direction' => $request_args['direction'],
                                'url' => pba_get_audit_log_list_url(array(
                                    'audit_sort' => 'actor_person_id',
                                    'audit_direction' => pba_get_audit_log_next_direction($request_args, 'actor_person_id'),
                                    'audit_page' => 1,
                                )),
                                'link_attr' => 'data-audit-log-link',
                                'link_class' => 'pba-admin-list-sort-link',
                                'indicator_class' => 'pba-admin-list-sort-indicator',
                            )); ?>

                            <?php echo pba_admin_list_render_sortable_th(array(
                                'label' => 'Action',
                                'column' => 'action_type',
                                'current_sort' => $request_args['sort'],
                                'current_direction' => $request_args['direction'],
                                'url' => pba_get_audit_log_list_url(array(
                                    'audit_sort' => 'action_type',
                                    'audit_direction' => pba_get_audit_log_next_direction($request_args, 'action_type'),
                                    'audit_page' => 1,
                                )),
                                'link_attr' => 'data-audit-log-link',
                                'link_class' => 'pba-admin-list-sort-link',
                                'indicator_class' => 'pba-admin-list-sort-indicator',
                            )); ?>

                            <?php echo pba_admin_list_render_sortable_th(array(
                                'label' => 'Entity',
                                'column' => 'entity_type',
                                'current_sort' => $request_args['sort'],
                                'current_direction' => $request_args['direction'],
                                'url' => pba_get_audit_log_list_url(array(
                                    'audit_sort' => 'entity_type',
                                    'audit_direction' => pba_get_audit_log_next_direction($request_args, 'entity_type'),
                                    'audit_page' => 1,
                                )),
                                'link_attr' => 'data-audit-log-link',
                                'link_class' => 'pba-admin-list-sort-link',
                                'indicator_class' => 'pba-admin-list-sort-indicator',
                            )); ?>

                            <th>Result</th>
                            <th>Summary</th>
                            <th>Request</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['rows'])) : ?>
                            <tr>
                                <td colspan="8">No audit records found.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($data['rows'] as $index => $row) : ?>
                                <?php
                                $row_id = 'pba-audit-row-' . $index;
                                $created_at_display = pba_format_datetime_display($row['created_at'] ?? '');
                                $actor_label = pba_audit_log_get_actor_display($row);
                                $entity_label = pba_audit_log_get_entity_display($row);
                                $result_status = trim((string) ($row['result_status'] ?? ''));
                                $summary = trim((string) ($row['summary'] ?? ''));
                                $request_label = trim((string) ($row['request_method'] ?? '')) . ' ' . trim((string) ($row['request_uri'] ?? ''));
                                $before_pretty = pba_audit_log_pretty_json($row['before_json'] ?? null);
                                $after_pretty = pba_audit_log_pretty_json($row['after_json'] ?? null);
                                $details_pretty = pba_audit_log_pretty_json($row['details_json'] ?? null);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($created_at_display); ?></td>
                                    <td class="pba-audit-log-actor"><?php echo esc_html($actor_label); ?></td>
                                    <td><?php echo esc_html($row['action_type'] ?? ''); ?></td>
                                    <td class="pba-audit-log-entity"><?php echo esc_html($entity_label); ?></td>
                                    <td>
                                        <span class="pba-audit-log-badge <?php echo esc_attr(strtolower($result_status)); ?>">
                                            <?php echo esc_html($result_status !== '' ? $result_status : '—'); ?>
                                        </span>
                                    </td>
                                    <td class="pba-audit-log-summary">
                                        <?php echo esc_html($summary !== '' ? $summary : '—'); ?>
                                    </td>
                                    <td class="pba-audit-log-request">
                                        <?php if (trim($request_label) !== '') : ?>
                                            <?php echo esc_html(trim($request_label)); ?>
                                            <?php if (!empty($row['ip_address'])) : ?>
                                                <div class="pba-audit-log-muted"><?php echo esc_html((string) $row['ip_address']); ?></div>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="pba-admin-list-btn pba-btn secondary pba-audit-log-toggle" data-target="<?php echo esc_attr($row_id); ?>">View</button>
                                    </td>
                                </tr>
                                <tr id="<?php echo esc_attr($row_id); ?>" class="pba-audit-log-details-row">
                                    <td colspan="8" class="pba-audit-log-details-cell">
                                        <div class="pba-audit-log-details-wrap">
                                            <div class="pba-audit-log-details-grid">
                                                <div class="pba-audit-log-details-card">
                                                    <h4>Summary</h4>
                                                    <?php if ($summary !== '') : ?>
                                                        <pre><?php echo esc_html($summary); ?></pre>
                                                    <?php else : ?>
                                                        <div class="pba-audit-log-empty-json">No summary available.</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="pba-audit-log-details-card">
                                                    <h4>Before</h4>
                                                    <?php if ($before_pretty !== '') : ?>
                                                        <pre><?php echo esc_html($before_pretty); ?></pre>
                                                    <?php else : ?>
                                                        <div class="pba-audit-log-empty-json">No before snapshot.</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="pba-audit-log-details-card">
                                                    <h4>After</h4>
                                                    <?php if ($after_pretty !== '') : ?>
                                                        <pre><?php echo esc_html($after_pretty); ?></pre>
                                                    <?php else : ?>
                                                        <div class="pba-audit-log-empty-json">No after snapshot.</div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="pba-audit-log-details-card">
                                                    <h4>Details</h4>
                                                    <?php if ($details_pretty !== '') : ?>
                                                        <pre><?php echo esc_html($details_pretty); ?></pre>
                                                    <?php else : ?>
                                                        <div class="pba-audit-log-empty-json">No additional details.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            echo pba_admin_list_render_pagination(array(
                'pagination' => $data['pagination'],
                'base_url' => 'javascript:void(0);',
                'page_query_arg' => 'audit_page',
                'make_page_url' => function ($page) {
                    return pba_get_audit_log_list_url(array('audit_page' => $page));
                },
                'link_attr' => 'data-audit-log-link',
            ));
            ?>
        </div>
    </div>

    <script>
    (function () {
        var buttons = document.querySelectorAll('.pba-audit-log-toggle');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-target');
                var row = targetId ? document.getElementById(targetId) : null;

                if (!row) {
                    return;
                }

                row.classList.toggle('is-open');
                button.textContent = row.classList.contains('is-open') ? 'Hide' : 'View';
            });
        });

        var table = document.getElementById('pba-audit-log-table');
        if (!table) {
            return;
        }

        var headRow = table.querySelector('thead tr');
        if (!headRow) {
            return;
        }

        var ths = Array.prototype.slice.call(headRow.children);
        if (!ths.length) {
            return;
        }

        var storageKey = 'pbaAuditLogColumnWidthsV3';
        var minWidth = 90;
        var colgroup = document.createElement('colgroup');
        var cols = [];

        ths.forEach(function (th) {
            var col = document.createElement('col');
            colgroup.appendChild(col);
            cols.push(col);
        });

        table.insertBefore(colgroup, table.firstChild);

        function setDefaultWidths() {
            ths.forEach(function (th, index) {
                var width = Math.max(minWidth, Math.round(th.getBoundingClientRect().width));
                cols[index].style.width = width + 'px';
            });
        }

        function loadWidths() {
            try {
                var raw = window.localStorage.getItem(storageKey);
                if (!raw) {
                    setDefaultWidths();
                    return;
                }

                var widths = JSON.parse(raw);
                if (!Array.isArray(widths) || widths.length !== cols.length) {
                    setDefaultWidths();
                    return;
                }

                widths.forEach(function (width, index) {
                    if (typeof width === 'number' && width >= minWidth) {
                        cols[index].style.width = width + 'px';
                    } else {
                        cols[index].style.width = Math.max(minWidth, Math.round(ths[index].getBoundingClientRect().width)) + 'px';
                    }
                });
            } catch (e) {
                setDefaultWidths();
            }
        }

        function saveWidths() {
            try {
                var widths = cols.map(function (col, index) {
                    var parsed = parseInt(col.style.width, 10);
                    if (!parsed || parsed < minWidth) {
                        parsed = Math.max(minWidth, Math.round(ths[index].getBoundingClientRect().width));
                    }
                    return parsed;
                });
                window.localStorage.setItem(storageKey, JSON.stringify(widths));
            } catch (e) {
            }
        }

        loadWidths();

        ths.forEach(function (th, index) {
            var handle = document.createElement('span');
            handle.className = 'pba-audit-log-col-resizer';
            handle.setAttribute('aria-hidden', 'true');
            th.appendChild(handle);

            handle.addEventListener('mousedown', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var startX = event.clientX;
                var startWidth = Math.max(minWidth, Math.round(th.getBoundingClientRect().width));

                document.body.classList.add('pba-audit-log-resizing');
                handle.classList.add('is-active');

                function onMouseMove(moveEvent) {
                    var delta = moveEvent.clientX - startX;
                    var newWidth = Math.max(minWidth, startWidth + delta);
                    cols[index].style.width = newWidth + 'px';
                }

                function onMouseUp() {
                    document.body.classList.remove('pba-audit-log-resizing');
                    handle.classList.remove('is-active');
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                    saveWidths();
                }

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    })();
    </script>
    <?php

    return ob_get_clean();
}

function pba_get_audit_log_base_url() {
    return home_url('/audit-log/');
}

function pba_get_audit_log_request_args() {
    $allowed_sort_columns = array(
        'created_at',
        'actor_person_id',
        'action_type',
        'entity_type',
    );

    $allowed_sort_directions = array('asc', 'desc');
    $allowed_per_page = array(25, 50, 100);

    $date_from = isset($_GET['audit_date_from']) ? sanitize_text_field(wp_unslash($_GET['audit_date_from'])) : '';
    $date_to = isset($_GET['audit_date_to']) ? sanitize_text_field(wp_unslash($_GET['audit_date_to'])) : '';
    $actor_person_id = isset($_GET['audit_actor_person_id']) ? sanitize_text_field(wp_unslash($_GET['audit_actor_person_id'])) : '';
    $action_type = isset($_GET['audit_action_type']) ? sanitize_text_field(wp_unslash($_GET['audit_action_type'])) : '';
    $entity_type = isset($_GET['audit_entity_type']) ? sanitize_text_field(wp_unslash($_GET['audit_entity_type'])) : '';
    $result_status = isset($_GET['audit_result_status']) ? sanitize_text_field(wp_unslash($_GET['audit_result_status'])) : '';
    $search = isset($_GET['audit_search']) ? sanitize_text_field(wp_unslash($_GET['audit_search'])) : '';
    $sort = isset($_GET['audit_sort']) ? sanitize_key(wp_unslash($_GET['audit_sort'])) : 'created_at';
    $direction = isset($_GET['audit_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['audit_direction']))) : 'desc';
    $page = isset($_GET['audit_page']) ? max(1, absint($_GET['audit_page'])) : 1;
    $per_page = isset($_GET['audit_per_page']) ? absint($_GET['audit_per_page']) : 25;

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'created_at';
    }

    if (!in_array($direction, $allowed_sort_directions, true)) {
        $direction = 'desc';
    }

    if (!in_array($per_page, $allowed_per_page, true)) {
        $per_page = 25;
    }

    return array(
        'date_from' => $date_from,
        'date_to' => $date_to,
        'actor_person_id' => $actor_person_id,
        'action_type' => $action_type,
        'entity_type' => $entity_type,
        'result_status' => $result_status,
        'search' => $search,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $page,
        'per_page' => $per_page,
    );
}

function pba_get_audit_log_list_url($overrides = array()) {
    $args = pba_get_audit_log_request_args();

    $query_args = array(
        'audit_date_from' => $args['date_from'],
        'audit_date_to' => $args['date_to'],
        'audit_actor_person_id' => $args['actor_person_id'],
        'audit_action_type' => $args['action_type'],
        'audit_entity_type' => $args['entity_type'],
        'audit_result_status' => $args['result_status'],
        'audit_search' => $args['search'],
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

function pba_get_audit_log_next_direction($request_args, $column) {
    if ($request_args['sort'] === $column) {
        return $request_args['direction'] === 'asc' ? 'desc' : 'asc';
    }

    return $column === 'created_at' ? 'desc' : 'asc';
}

function pba_get_audit_log_data($request_args) {
    $all_rows = pba_supabase_get('AuditLog', array(
        'select' => 'audit_log_id,created_at,request_id,actor_person_id,actor_wp_user_id,actor_email_address,actor_role_names,action_type,entity_type,entity_id,entity_label,target_person_id,target_household_id,target_committee_id,target_document_folder_id,target_document_item_id,result_status,summary,before_json,after_json,details_json,ip_address,user_agent,request_method,request_uri',
        'order' => 'created_at.desc',
        'limit' => 5000,
    ));

    if (is_wp_error($all_rows) || !is_array($all_rows)) {
        return is_wp_error($all_rows) ? $all_rows : new WP_Error('pba_audit_log_load_failed', 'Unable to load audit records.');
    }

    $prepared_rows = array_map('pba_prepare_audit_log_row', $all_rows);
    $filter_options = pba_get_audit_log_filter_options($prepared_rows);
    $filtered_rows = pba_filter_audit_log_rows($prepared_rows, $request_args);
    $sorted_rows = pba_sort_audit_log_rows($filtered_rows, $request_args['sort'], $request_args['direction']);
    $pagination = pba_paginate_audit_log_rows($sorted_rows, $request_args['page'], $request_args['per_page']);

    return array(
        'rows' => $pagination['rows'],
        'filter_options' => $filter_options,
        'pagination' => $pagination,
    );
}

function pba_prepare_audit_log_row($row) {
    $created_at_raw = (string) ($row['created_at'] ?? '');
    $created_at_ts = $created_at_raw !== '' ? strtotime($created_at_raw) : false;

    return array(
        'audit_log_id' => isset($row['audit_log_id']) ? (int) $row['audit_log_id'] : 0,
        'created_at' => $created_at_raw,
        'created_at_timestamp' => $created_at_ts ? (int) $created_at_ts : 0,
        'request_id' => (string) ($row['request_id'] ?? ''),
        'actor_person_id' => isset($row['actor_person_id']) && $row['actor_person_id'] !== '' ? (int) $row['actor_person_id'] : null,
        'actor_wp_user_id' => isset($row['actor_wp_user_id']) && $row['actor_wp_user_id'] !== '' ? (int) $row['actor_wp_user_id'] : null,
        'actor_email_address' => (string) ($row['actor_email_address'] ?? ''),
        'actor_role_names' => $row['actor_role_names'] ?? null,
        'action_type' => (string) ($row['action_type'] ?? ''),
        'entity_type' => (string) ($row['entity_type'] ?? ''),
        'entity_id' => isset($row['entity_id']) && $row['entity_id'] !== '' ? (int) $row['entity_id'] : null,
        'entity_label' => (string) ($row['entity_label'] ?? ''),
        'target_person_id' => isset($row['target_person_id']) && $row['target_person_id'] !== '' ? (int) $row['target_person_id'] : null,
        'target_household_id' => isset($row['target_household_id']) && $row['target_household_id'] !== '' ? (int) $row['target_household_id'] : null,
        'target_committee_id' => isset($row['target_committee_id']) && $row['target_committee_id'] !== '' ? (int) $row['target_committee_id'] : null,
        'target_document_folder_id' => isset($row['target_document_folder_id']) && $row['target_document_folder_id'] !== '' ? (int) $row['target_document_folder_id'] : null,
        'target_document_item_id' => isset($row['target_document_item_id']) && $row['target_document_item_id'] !== '' ? (int) $row['target_document_item_id'] : null,
        'result_status' => (string) ($row['result_status'] ?? ''),
        'summary' => (string) ($row['summary'] ?? ''),
        'before_json' => $row['before_json'] ?? null,
        'after_json' => $row['after_json'] ?? null,
        'details_json' => $row['details_json'] ?? null,
        'ip_address' => (string) ($row['ip_address'] ?? ''),
        'user_agent' => (string) ($row['user_agent'] ?? ''),
        'request_method' => (string) ($row['request_method'] ?? ''),
        'request_uri' => (string) ($row['request_uri'] ?? ''),
    );
}

function pba_get_audit_log_filter_options($rows) {
    $action_types = array();
    $entity_types = array();
    $result_statuses = array();

    foreach ($rows as $row) {
        $action_type = trim((string) ($row['action_type'] ?? ''));
        $entity_type = trim((string) ($row['entity_type'] ?? ''));
        $result_status = trim((string) ($row['result_status'] ?? ''));

        if ($action_type !== '') {
            $action_types[$action_type] = $action_type;
        }

        if ($entity_type !== '') {
            $entity_types[$entity_type] = $entity_type;
        }

        if ($result_status !== '') {
            $result_statuses[$result_status] = $result_status;
        }
    }

    natcasesort($action_types);
    natcasesort($entity_types);
    natcasesort($result_statuses);

    return array(
        'action_types' => array_values($action_types),
        'entity_types' => array_values($entity_types),
        'result_statuses' => array_values($result_statuses),
    );
}

function pba_filter_audit_log_rows($rows, $request_args) {
    $date_from = trim((string) $request_args['date_from']);
    $date_to = trim((string) $request_args['date_to']);
    $actor_person_id = trim((string) $request_args['actor_person_id']);
    $action_type = trim((string) $request_args['action_type']);
    $entity_type = trim((string) $request_args['entity_type']);
    $result_status = trim((string) $request_args['result_status']);
    $search = strtolower(trim((string) $request_args['search']));

    $date_from_ts = $date_from !== '' ? strtotime($date_from . ' 00:00:00') : false;
    $date_to_ts = $date_to !== '' ? strtotime($date_to . ' 23:59:59') : false;

    return array_values(array_filter($rows, function ($row) use ($date_from_ts, $date_to_ts, $actor_person_id, $action_type, $entity_type, $result_status, $search) {
        $created_ts = isset($row['created_at_timestamp']) ? (int) $row['created_at_timestamp'] : 0;

        if ($date_from_ts !== false && $created_ts > 0 && $created_ts < $date_from_ts) {
            return false;
        }

        if ($date_to_ts !== false && $created_ts > 0 && $created_ts > $date_to_ts) {
            return false;
        }

        if ($actor_person_id !== '') {
            $row_actor = isset($row['actor_person_id']) && $row['actor_person_id'] !== null ? (string) $row['actor_person_id'] : '';
            if ($row_actor !== $actor_person_id) {
                return false;
            }
        }

        if ($action_type !== '' && (string) ($row['action_type'] ?? '') !== $action_type) {
            return false;
        }

        if ($entity_type !== '' && (string) ($row['entity_type'] ?? '') !== $entity_type) {
            return false;
        }

        if ($result_status !== '' && (string) ($row['result_status'] ?? '') !== $result_status) {
            return false;
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['summary'] ?? ''),
                (string) ($row['entity_label'] ?? ''),
                (string) ($row['action_type'] ?? ''),
                (string) ($row['entity_type'] ?? ''),
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
            case 'actor_person_id':
                $value_a = isset($a['actor_person_id']) && $a['actor_person_id'] !== null ? (int) $a['actor_person_id'] : -1;
                $value_b = isset($b['actor_person_id']) && $b['actor_person_id'] !== null ? (int) $b['actor_person_id'] : -1;
                break;

            case 'action_type':
                $value_a = strtolower((string) ($a['action_type'] ?? ''));
                $value_b = strtolower((string) ($b['action_type'] ?? ''));
                break;

            case 'entity_type':
                $value_a = strtolower((string) ($a['entity_type'] ?? ''));
                $value_b = strtolower((string) ($b['entity_type'] ?? ''));
                break;

            case 'created_at':
            default:
                $value_a = (int) ($a['created_at_timestamp'] ?? 0);
                $value_b = (int) ($b['created_at_timestamp'] ?? 0);
                break;
        }

        if ($value_a === $value_b) {
            $tie_a = isset($a['audit_log_id']) ? (int) $a['audit_log_id'] : 0;
            $tie_b = isset($b['audit_log_id']) ? (int) $b['audit_log_id'] : 0;
            $comparison = $tie_a <=> $tie_b;
        } else {
            $comparison = ($value_a <=> $value_b);
        }

        return $direction === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
}

function pba_paginate_audit_log_rows($rows, $page, $per_page) {
    $total_items = count($rows);
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    $page = min(max(1, (int) $page), $total_pages);
    $offset = ($page - 1) * $per_page;

    return array(
        'rows' => array_slice($rows, $offset, $per_page),
        'current_page' => $page,
        'per_page' => $per_page,
        'total_items' => $total_items,
        'total_pages' => $total_pages,
    );
}

function pba_audit_log_get_actor_display($row) {
    $parts = array();

    if (isset($row['actor_person_id']) && $row['actor_person_id'] !== null) {
        $parts[] = 'Person #' . (int) $row['actor_person_id'];
    }

    if (!empty($row['actor_email_address'])) {
        $parts[] = (string) $row['actor_email_address'];
    }

    if (empty($parts)) {
        return '—';
    }

    return implode(' · ', $parts);
}

function pba_audit_log_get_entity_display($row) {
    $entity_type = trim((string) ($row['entity_type'] ?? ''));
    $entity_label = trim((string) ($row['entity_label'] ?? ''));
    $entity_id = isset($row['entity_id']) && $row['entity_id'] !== null ? (int) $row['entity_id'] : 0;

    $parts = array();

    if ($entity_type !== '') {
        $parts[] = $entity_type;
    }

    if ($entity_label !== '') {
        $parts[] = $entity_label;
    } elseif ($entity_id > 0) {
        $parts[] = '#' . $entity_id;
    }

    if (empty($parts)) {
        return '—';
    }

    return implode(' · ', $parts);
}

function pba_audit_log_pretty_json($value) {
    if ($value === null || $value === '') {
        return '';
    }

    if (is_array($value)) {
        $decoded = $value;
    } else {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded) && !is_object($decoded)) {
            return trim((string) $value);
        }
    }

    $encoded = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '';
}
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

    if (!function_exists('pba_current_user_can_access_admin_area') || !pba_current_user_can_access_admin_area()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $search = isset($_GET['audit_search']) ? sanitize_text_field(wp_unslash($_GET['audit_search'])) : '';
    $event  = isset($_GET['audit_event']) ? sanitize_text_field(wp_unslash($_GET['audit_event'])) : '';
    $actor  = isset($_GET['audit_actor']) ? sanitize_text_field(wp_unslash($_GET['audit_actor'])) : '';
    $page   = isset($_GET['audit_page']) ? max(1, (int) $_GET['audit_page']) : 1;
    $per_page = 50;

    $rows = pba_supabase_get('AuditLog', array(
        'select' => 'audit_log_id,created_at,event_name,entity_type,entity_id,person_id,details_json',
        'order'  => 'created_at.desc',
        'limit'  => 500,
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load audit log records right now.</p>';
    }

    if (!is_array($rows)) {
        $rows = array();
    }

    $rows = pba_audit_log_enrich_rows($rows);

    $event_options = array();
    $actor_options = array();

    foreach ($rows as $row) {
        $event_name = isset($row['event_name']) ? (string) $row['event_name'] : '';
        $actor_name = isset($row['actor_name']) ? (string) $row['actor_name'] : '';

        if ($event_name !== '') {
            $event_options[$event_name] = $event_name;
        }

        if ($actor_name !== '') {
            $actor_options[$actor_name] = $actor_name;
        }
    }

    natcasesort($event_options);
    natcasesort($actor_options);

    if ($event !== '') {
        $rows = array_values(array_filter($rows, function ($row) use ($event) {
            return isset($row['event_name']) && (string) $row['event_name'] === $event;
        }));
    }

    if ($actor !== '') {
        $rows = array_values(array_filter($rows, function ($row) use ($actor) {
            return isset($row['actor_name']) && (string) $row['actor_name'] === $actor;
        }));
    }

    if ($search !== '') {
        $needle = strtolower($search);

        $rows = array_values(array_filter($rows, function ($row) use ($needle) {
            $parts = array(
                isset($row['created_at_display']) ? (string) $row['created_at_display'] : '',
                isset($row['event_name']) ? (string) $row['event_name'] : '',
                isset($row['actor_name']) ? (string) $row['actor_name'] : '',
                isset($row['entity_type']) ? (string) $row['entity_type'] : '',
                isset($row['entity_id']) ? (string) $row['entity_id'] : '',
                isset($row['details_summary']) ? (string) $row['details_summary'] : '',
            );

            $haystack = strtolower(trim(implode(' ', $parts)));
            return $haystack !== '' && strpos($haystack, $needle) !== false;
        }));
    }

    $total_rows = count($rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    $page_rows = array_slice($rows, $offset, $per_page);

    $base_url = remove_query_arg(array('audit_page'));

    ob_start();
    ?>
    <style>
        .pba-audit-log-wrap {
            max-width: 1400px;
            margin: 0 auto;
        }

        .pba-audit-log-hero {
            margin: 0 0 20px;
        }

        .pba-audit-log-search {
            margin: 18px 0 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }

        .pba-audit-log-field {
            min-width: 180px;
            flex: 1 1 180px;
        }

        .pba-audit-log-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #607487;
        }

        .pba-audit-log-field input[type="text"],
        .pba-audit-log-field select {
            width: 100%;
            min-height: 42px;
            padding: 9px 10px;
            box-sizing: border-box;
        }

        .pba-audit-log-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }

        .pba-audit-log-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-audit-log-meta {
            margin-bottom: 14px;
            color: #666;
            font-size: 14px;
        }

        .pba-audit-log-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .pba-audit-log-table {
            width: 100%;
            min-width: 1180px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pba-audit-log-table th,
        .pba-audit-log-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .pba-audit-log-table th {
            background: #f3f3f3;
            position: relative;
        }

        .pba-audit-log-muted {
            color: #666;
            font-size: 13px;
        }

        .pba-audit-log-pagination {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            align-items: center;
        }

        .pba-audit-log-page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            min-height: 38px;
            padding: 0 10px;
            border: 1px solid #d7d7d7;
            text-decoration: none;
            border-radius: 4px;
            background: #fff;
            color: #17324a;
        }

        .pba-audit-log-page-link.current {
            background: #0d3b66;
            border-color: #0d3b66;
            color: #fff;
        }
    </style>

    <div class="pba-audit-log-wrap">
        <div class="pba-audit-log-hero">
            <h2>Audit Log</h2>
            <p>Review recorded activity across the application.</p>
        </div>

        <form method="get" class="pba-audit-log-search">
            <div class="pba-audit-log-field">
                <label for="pba-audit-search">Search</label>
                <input
                    id="pba-audit-search"
                    type="text"
                    name="audit_search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Search audit records"
                >
            </div>

            <div class="pba-audit-log-field">
                <label for="pba-audit-event">Event</label>
                <select id="pba-audit-event" name="audit_event">
                    <option value="">All events</option>
                    <?php foreach ($event_options as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($event, $value); ?>>
                            <?php echo esc_html($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pba-audit-log-field">
                <label for="pba-audit-actor">Actor</label>
                <select id="pba-audit-actor" name="audit_actor">
                    <option value="">All actors</option>
                    <?php foreach ($actor_options as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($actor, $value); ?>>
                            <?php echo esc_html($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="pba-audit-log-btn secondary">Apply</button>

            <?php if ($search !== '' || $event !== '' || $actor !== '') : ?>
                <a class="pba-audit-log-btn secondary" href="<?php echo esc_url(remove_query_arg(array('audit_search', 'audit_event', 'audit_actor', 'audit_page'))); ?>">Clear</a>
            <?php endif; ?>
        </form>

        <div class="pba-audit-log-meta">
            Showing <?php echo esc_html((string) count($page_rows)); ?> of <?php echo esc_html((string) $total_rows); ?> record<?php echo $total_rows === 1 ? '' : 's'; ?>.
        </div>

        <div class="pba-audit-log-table-wrap">
            <table
                class="pba-audit-log-table pba-resizable-table"
                id="pba-audit-log-table"
                data-resize-key="pbaAuditLogColumnWidthsV1"
                data-min-col-width="110"
            >
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Event</th>
                        <th>Actor</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($page_rows)) : ?>
                        <tr>
                            <td colspan="6">No audit records found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($page_rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html(isset($row['created_at_display']) ? $row['created_at_display'] : ''); ?></td>
                                <td><?php echo esc_html(isset($row['event_name']) ? $row['event_name'] : ''); ?></td>
                                <td><?php echo esc_html(isset($row['actor_name']) ? $row['actor_name'] : ''); ?></td>
                                <td><?php echo esc_html(isset($row['entity_type']) ? $row['entity_type'] : ''); ?></td>
                                <td><?php echo esc_html(isset($row['entity_id']) ? (string) $row['entity_id'] : ''); ?></td>
                                <td>
                                    <?php if (!empty($row['details_summary'])) : ?>
                                        <?php echo esc_html($row['details_summary']); ?>
                                    <?php else : ?>
                                        <span class="pba-audit-log-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="pba-audit-log-pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <?php
                    $url = add_query_arg(array('audit_page' => $i), $base_url);
                    ?>
                    <?php if ($i === $page) : ?>
                        <span class="pba-audit-log-page-link current"><?php echo esc_html((string) $i); ?></span>
                    <?php else : ?>
                        <a class="pba-audit-log-page-link" href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) $i); ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    echo pba_admin_list_render_resizable_table_script();

    return ob_get_clean();
}

function pba_audit_log_enrich_rows($rows) {
    $rows = is_array($rows) ? $rows : array();
    $person_ids = array();

    foreach ($rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
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
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $created_at = isset($row['created_at']) ? (string) $row['created_at'] : '';
        $details_json = isset($row['details_json']) ? $row['details_json'] : '';

        $row['actor_name'] = ($person_id > 0 && isset($people_map[$person_id]) && $people_map[$person_id] !== '')
            ? $people_map[$person_id]
            : 'System';

        $row['created_at_display'] = $created_at !== '' ? pba_format_datetime_display($created_at) : '';

        $row['details_summary'] = pba_audit_log_details_to_summary($details_json);
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
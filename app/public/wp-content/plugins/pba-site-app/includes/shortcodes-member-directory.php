<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/pba-admin-list-ui.php';

add_action('init', 'pba_register_member_directory_shortcode');

function pba_register_member_directory_shortcode() {
    add_shortcode('pba_member_directory', 'pba_render_member_directory_shortcode');
}

function pba_render_member_directory_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    $base_url = plugin_dir_url(__FILE__) . 'css/';
    $base_path = dirname(__FILE__) . '/css/';

    wp_enqueue_style(
        'pba-admin-list-styles',
        $base_url . 'pba-admin-list-styles.css',
        array(),
        file_exists($base_path . 'pba-admin-list-styles.css') ? (string) filemtime($base_path . 'pba-admin-list-styles.css') : '1.0.0'
    );

    $allowed_sort_columns = array('name', 'email', 'household', 'committees');
    $allowed_sort_directions = array('asc', 'desc');

    $search = isset($_GET['directory_search']) ? sanitize_text_field(wp_unslash($_GET['directory_search'])) : '';
    $page = isset($_GET['directory_page']) ? max(1, absint($_GET['directory_page'])) : 1;
    $per_page = isset($_GET['directory_per_page']) ? absint($_GET['directory_per_page']) : 25;
    $sort = isset($_GET['directory_sort']) ? sanitize_key(wp_unslash($_GET['directory_sort'])) : 'name';
    $direction = isset($_GET['directory_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['directory_direction']))) : 'asc';

    if (!in_array($per_page, array(25, 50, 100), true)) {
        $per_page = 25;
    }

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'name';
    }

    if (!in_array($direction, $allowed_sort_directions, true)) {
        $direction = 'asc';
    }

    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,household_id,first_name,last_name,email_address,status',
        'status' => 'eq.Active',
        'order'  => 'last_name.asc,first_name.asc',
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load the member directory right now.</p>';
    }

    if (!is_array($rows)) {
        $rows = array();
    }

    $household_labels = pba_member_directory_get_household_labels($rows);
    $committee_labels = pba_member_directory_get_committee_labels($rows);

    $normalized_rows = array();
    foreach ($rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $normalized_rows[] = array(
            'person_id' => $person_id,
            'first_name' => isset($row['first_name']) ? (string) $row['first_name'] : '',
            'last_name' => isset($row['last_name']) ? (string) $row['last_name'] : '',
            'email_address' => isset($row['email_address']) ? (string) $row['email_address'] : '',
            'household_label' => isset($household_labels[$person_id]) ? (string) $household_labels[$person_id] : '',
            'committee_labels' => isset($committee_labels[$person_id]) ? (array) $committee_labels[$person_id] : array(),
        );
    }

    if ($search !== '') {
        $needle = strtolower($search);

        $normalized_rows = array_values(array_filter($normalized_rows, function ($row) use ($needle) {
            $haystack = strtolower(trim(implode(' ', array(
                (string) $row['first_name'],
                (string) $row['last_name'],
                (string) $row['email_address'],
                (string) $row['household_label'],
                implode(' ', (array) $row['committee_labels']),
            ))));

            return $haystack !== '' && strpos($haystack, $needle) !== false;
        }));
    }

    usort($normalized_rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'email':
                $a_value = strtolower((string) $a['email_address']);
                $b_value = strtolower((string) $b['email_address']);
                break;
            case 'household':
                $a_value = strtolower((string) $a['household_label']);
                $b_value = strtolower((string) $b['household_label']);
                break;
            case 'committees':
                $a_value = strtolower(implode(', ', (array) $a['committee_labels']));
                $b_value = strtolower(implode(', ', (array) $b['committee_labels']));
                break;
            case 'name':
            default:
                $a_value = strtolower(trim($a['last_name'] . ' ' . $a['first_name']));
                $b_value = strtolower(trim($b['last_name'] . ' ' . $b['first_name']));
                break;
        }

        if ($a_value === $b_value) {
            return 0;
        }

        $result = ($a_value < $b_value) ? -1 : 1;

        return ($direction === 'desc') ? -$result : $result;
    });

    $total_rows = count($normalized_rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    $page_rows = array_slice($normalized_rows, $offset, $per_page);

    $start_number = $total_rows > 0 ? ($offset + 1) : 0;
    $end_number = min($offset + $per_page, $total_rows);

    ob_start();
    ?>
    <style>
        .pba-member-directory-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .pba-member-directory-search {
            margin: 18px 0 20px;
            display: grid;
            grid-template-columns: minmax(260px, 1fr) minmax(120px, 140px) auto auto;
            gap: 10px;
            align-items: end;
        }

        .pba-member-directory-field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #607487;
        }

        .pba-member-directory-search input[type="text"],
        .pba-member-directory-search select {
            width: 100%;
            max-width: 100%;
            padding: 9px 10px;
            box-sizing: border-box;
        }

        .pba-member-directory-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            box-sizing: border-box;
        }

        .pba-member-directory-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-member-directory-meta {
            margin-bottom: 14px;
            color: #666;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pba-member-directory-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .pba-member-directory-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pba-member-directory-table th,
        .pba-member-directory-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .pba-member-directory-table th {
            background: #f3f3f3;
            position: relative;
        }

        .pba-member-directory-table a.pba-admin-list-sort-link,
        .pba-member-directory-table a.pba-admin-list-sort-link:visited,
        .pba-member-directory-table a.pba-admin-list-sort-link:hover,
        .pba-member-directory-table a.pba-admin-list-sort-link:focus {
            color: #17324a !important;
        }

        .pba-member-directory-muted {
            color: #666;
            font-size: 13px;
        }

        .pba-member-directory-pagination {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .pba-member-directory-page-links {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pba-member-directory-page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            min-height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid #d4e0eb;
            background: #fff;
            color: #17324a;
            font-weight: 700;
            box-sizing: border-box;
        }

        .pba-member-directory-page-link.current {
            background: #0d3b66;
            border-color: #0d3b66;
            color: #fff;
        }

        .pba-member-directory-page-link.disabled {
            opacity: 0.55;
            pointer-events: none;
        }

        @media (max-width: 700px) {
            .pba-member-directory-search {
                grid-template-columns: 1fr;
            }

            .pba-member-directory-table-wrap {
                overflow-x: visible;
            }

            .pba-member-directory-table,
            .pba-member-directory-table thead,
            .pba-member-directory-table tbody,
            .pba-member-directory-table th,
            .pba-member-directory-table td,
            .pba-member-directory-table tr {
                display: block;
            }

            .pba-member-directory-table {
                min-width: 0;
            }

            .pba-member-directory-table thead {
                display: none;
            }

            .pba-member-directory-table tr {
                margin-bottom: 14px;
                border: 1px solid #d7d7d7;
            }

            .pba-member-directory-table td {
                border: 0;
                border-bottom: 1px solid #eee;
            }

            .pba-member-directory-table td:last-child {
                border-bottom: 0;
            }

            .pba-member-directory-pagination {
                align-items: flex-start;
            }
        }
    </style>

    <div class="pba-member-directory-wrap">
        <h2>Member Directory</h2>
        <p>Browse active PBA members and their household and committee information.</p>

        <form method="get" class="pba-member-directory-search">
            <div class="pba-member-directory-field">
                <label for="directory_search">Search</label>
                <input
                    id="directory_search"
                    type="text"
                    name="directory_search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Search by name, email, household, or committee"
                >
            </div>

            <div class="pba-member-directory-field">
                <label for="directory_per_page">Rows</label>
                <select id="directory_per_page" name="directory_per_page">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                </select>
            </div>

            <input type="hidden" name="directory_page" value="1">

            <button type="submit" class="pba-member-directory-btn secondary">Apply</button>

            <?php if ($search !== '' || $per_page !== 25 || $sort !== 'name' || $direction !== 'asc') : ?>
                <a class="pba-member-directory-btn secondary" href="<?php echo esc_url(home_url('/member-directory/')); ?>">Clear</a>
            <?php endif; ?>
        </form>

        <div class="pba-member-directory-meta">
            <div>
                Showing <?php echo esc_html((string) $start_number); ?>–<?php echo esc_html((string) $end_number); ?>
                of <?php echo esc_html((string) $total_rows); ?> active member<?php echo $total_rows === 1 ? '' : 's'; ?>.
            </div>
            <div>
                Page <?php echo esc_html((string) $page); ?> of <?php echo esc_html((string) $total_pages); ?>
            </div>
        </div>

        <div class="pba-member-directory-table-wrap">
            <table
                class="pba-member-directory-table pba-resizable-table"
                id="pba-member-directory-table"
                data-resize-key="pbaMemberDirectoryColumnWidthsV1"
                data-min-col-width="120"
            >
                <thead>
                    <tr>
                        <?php echo pba_render_member_directory_sortable_th('Name', 'name', $sort, $direction, $search, $per_page); ?>
                        <?php echo pba_render_member_directory_sortable_th('Email', 'email', $sort, $direction, $search, $per_page); ?>
                        <?php echo pba_render_member_directory_sortable_th('Household', 'household', $sort, $direction, $search, $per_page); ?>
                        <?php echo pba_render_member_directory_sortable_th('Committees', 'committees', $sort, $direction, $search, $per_page); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($page_rows)) : ?>
                        <tr>
                            <td colspan="4">No members found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($page_rows as $row) : ?>
                            <?php
                            $name = trim($row['first_name'] . ' ' . $row['last_name']);
                            $email = trim((string) $row['email_address']);
                            $household = (string) $row['household_label'];
                            $committees = (array) $row['committee_labels'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($name !== '' ? $name : 'Unnamed member'); ?></strong>
                                </td>
                                <td>
                                    <?php if ($email !== '') : ?>
                                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                    <?php else : ?>
                                        <span class="pba-member-directory-muted">No email listed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($household !== '' ? $household : ''); ?>
                                </td>
                                <td>
                                    <?php if (!empty($committees)) : ?>
                                        <?php echo esc_html(implode(', ', $committees)); ?>
                                    <?php else : ?>
                                        <span class="pba-member-directory-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo pba_render_member_directory_pagination($page, $total_pages, $search, $per_page, $sort, $direction); ?>
    </div>
    <?php
    echo pba_admin_list_render_resizable_table_script();

    return ob_get_clean();
}

function pba_render_member_directory_sortable_th($label, $column, $current_sort, $current_direction, $search, $per_page) {
    $next_direction = ($current_sort === $column && $current_direction === 'asc') ? 'desc' : 'asc';

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $current_sort,
        'current_direction' => $current_direction,
        'url' => pba_get_member_directory_url(array(
            'directory_search' => $search,
            'directory_per_page' => $per_page,
            'directory_page' => 1,
            'directory_sort' => $column,
            'directory_direction' => $next_direction,
        )),
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_member_directory_pagination($current_page, $total_pages, $search, $per_page, $sort, $direction) {
    if ($total_pages <= 1) {
        return '';
    }

    $window = 2;
    $start_page = max(1, $current_page - $window);
    $end_page = min($total_pages, $current_page + $window);

    ob_start();
    ?>
    <div class="pba-member-directory-pagination">
        <div class="pba-member-directory-muted">
            Page <?php echo esc_html((string) $current_page); ?> of <?php echo esc_html((string) $total_pages); ?>
        </div>

        <div class="pba-member-directory-page-links">
            <?php if ($current_page > 1) : ?>
                <a class="pba-member-directory-page-link" href="<?php echo esc_url(pba_get_member_directory_url(array(
                    'directory_page' => $current_page - 1,
                    'directory_search' => $search,
                    'directory_per_page' => $per_page,
                    'directory_sort' => $sort,
                    'directory_direction' => $direction,
                ))); ?>">Previous</a>
            <?php else : ?>
                <span class="pba-member-directory-page-link disabled">Previous</span>
            <?php endif; ?>

            <?php if ($start_page > 1) : ?>
                <a class="pba-member-directory-page-link" href="<?php echo esc_url(pba_get_member_directory_url(array(
                    'directory_page' => 1,
                    'directory_search' => $search,
                    'directory_per_page' => $per_page,
                    'directory_sort' => $sort,
                    'directory_direction' => $direction,
                ))); ?>">1</a>
                <?php if ($start_page > 2) : ?>
                    <span class="pba-member-directory-muted">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++) : ?>
                <?php if ($i === $current_page) : ?>
                    <span class="pba-member-directory-page-link current"><?php echo esc_html((string) $i); ?></span>
                <?php else : ?>
                    <a class="pba-member-directory-page-link" href="<?php echo esc_url(pba_get_member_directory_url(array(
                        'directory_page' => $i,
                        'directory_search' => $search,
                        'directory_per_page' => $per_page,
                        'directory_sort' => $sort,
                        'directory_direction' => $direction,
                    ))); ?>"><?php echo esc_html((string) $i); ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages) : ?>
                <?php if ($end_page < ($total_pages - 1)) : ?>
                    <span class="pba-member-directory-muted">…</span>
                <?php endif; ?>
                <a class="pba-member-directory-page-link" href="<?php echo esc_url(pba_get_member_directory_url(array(
                    'directory_page' => $total_pages,
                    'directory_search' => $search,
                    'directory_per_page' => $per_page,
                    'directory_sort' => $sort,
                    'directory_direction' => $direction,
                ))); ?>"><?php echo esc_html((string) $total_pages); ?></a>
            <?php endif; ?>

            <?php if ($current_page < $total_pages) : ?>
                <a class="pba-member-directory-page-link" href="<?php echo esc_url(pba_get_member_directory_url(array(
                    'directory_page' => $current_page + 1,
                    'directory_search' => $search,
                    'directory_per_page' => $per_page,
                    'directory_sort' => $sort,
                    'directory_direction' => $direction,
                ))); ?>">Next</a>
            <?php else : ?>
                <span class="pba-member-directory-page-link disabled">Next</span>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_get_member_directory_url($args = array()) {
    $base_url = home_url('/member-directory/');

    $query_args = array(
        'directory_search' => isset($args['directory_search']) ? (string) $args['directory_search'] : '',
        'directory_per_page' => isset($args['directory_per_page']) ? (int) $args['directory_per_page'] : 25,
        'directory_page' => isset($args['directory_page']) ? max(1, (int) $args['directory_page']) : 1,
        'directory_sort' => isset($args['directory_sort']) ? (string) $args['directory_sort'] : 'name',
        'directory_direction' => isset($args['directory_direction']) ? (string) $args['directory_direction'] : 'asc',
    );

    foreach ($query_args as $key => $value) {
        if (
            $value === '' ||
            $value === null ||
            ($key === 'directory_page' && (int) $value === 1) ||
            ($key === 'directory_per_page' && (int) $value === 25) ||
            ($key === 'directory_sort' && (string) $value === 'name') ||
            ($key === 'directory_direction' && (string) $value === 'asc')
        ) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, $base_url);
}

function pba_member_directory_get_household_labels($people_rows) {
    $household_ids = array();

    foreach ($people_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id > 0) {
            $household_ids[] = $household_id;
        }
    }

    $household_ids = array_values(array_unique($household_ids));

    if (empty($household_ids)) {
        return array();
    }

    $household_rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,pb_street_number,pb_street_name',
        'household_id' => 'in.(' . implode(',', array_map('intval', $household_ids)) . ')',
        'limit'        => count($household_ids),
    ));

    if (is_wp_error($household_rows) || !is_array($household_rows)) {
        return array();
    }

    $label_by_household_id = array();
    foreach ($household_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1) {
            continue;
        }

        $street_number = isset($row['pb_street_number']) ? trim((string) $row['pb_street_number']) : '';
        $street_name = isset($row['pb_street_name']) ? trim((string) $row['pb_street_name']) : '';
        $label_by_household_id[$household_id] = trim($street_number . ' ' . $street_name);
    }

    $labels_by_person_id = array();
    foreach ($people_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;

        if ($person_id > 0 && $household_id > 0 && isset($label_by_household_id[$household_id])) {
            $labels_by_person_id[$person_id] = $label_by_household_id[$household_id];
        }
    }

    return $labels_by_person_id;
}

function pba_member_directory_get_committee_labels($people_rows) {
    $person_ids = array();

    foreach ($people_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        if ($person_id > 0) {
            $person_ids[] = $person_id;
        }
    }

    $person_ids = array_values(array_unique($person_ids));

    if (empty($person_ids)) {
        return array();
    }

    $membership_rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_id,committee_id,committee_role,is_active',
        'person_id' => 'in.(' . implode(',', array_map('intval', $person_ids)) . ')',
        'is_active' => 'eq.true',
        'limit'     => max(count($person_ids) * 5, count($person_ids)),
    ));

    if (is_wp_error($membership_rows) || !is_array($membership_rows) || empty($membership_rows)) {
        return array();
    }

    $committee_ids = array();
    foreach ($membership_rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        if ($committee_id > 0) {
            $committee_ids[] = $committee_id;
        }
    }

    $committee_ids = array_values(array_unique($committee_ids));
    if (empty($committee_ids)) {
        return array();
    }

    $committee_rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_id,committee_name,status',
        'committee_id' => 'in.(' . implode(',', array_map('intval', $committee_ids)) . ')',
        'limit'        => count($committee_ids),
    ));

    if (is_wp_error($committee_rows) || !is_array($committee_rows)) {
        return array();
    }

    $committee_names = array();
    foreach ($committee_rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        $committee_name = isset($row['committee_name']) ? trim((string) $row['committee_name']) : '';
        $status = isset($row['status']) ? trim((string) $row['status']) : '';

        if ($committee_id > 0 && $committee_name !== '' && ($status === '' || $status === 'Active')) {
            $committee_names[$committee_id] = $committee_name;
        }
    }

    $labels_by_person_id = array();
    foreach ($membership_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;

        if ($person_id < 1 || $committee_id < 1 || !isset($committee_names[$committee_id])) {
            continue;
        }

        $label = $committee_names[$committee_id];
        if (!empty($row['committee_role'])) {
            $label .= ' (' . trim((string) $row['committee_role']) . ')';
        }

        if (!isset($labels_by_person_id[$person_id])) {
            $labels_by_person_id[$person_id] = array();
        }

        $labels_by_person_id[$person_id][] = $label;
    }

    foreach ($labels_by_person_id as $person_id => $labels) {
        $labels = array_values(array_unique($labels));
        natcasesort($labels);
        $labels_by_person_id[$person_id] = array_values($labels);
    }

    return $labels_by_person_id;
}
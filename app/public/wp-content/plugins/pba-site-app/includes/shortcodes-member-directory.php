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

    pba_member_directory_enqueue_styles();

    $request_args = pba_get_member_directory_request_args();
    $data = pba_get_member_directory_list_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load the member directory right now.</p>';
    }

    ob_start();
    ?>
    <div class="pba-member-directory-wrap">
        <div id="pba-member-directory-root">
            <?php echo pba_render_member_directory_page_shell($data, $request_args); ?>
        </div>
    </div>

    <?php
    echo pba_admin_list_render_ajax_script(array(
        'root_id' => 'pba-member-directory-root',
        'form_id' => 'pba-member-directory-search-form',
        'shell_selector' => '.pba-member-directory-list-shell',
        'loading_selector' => '.pba-admin-list-grid-wrap',
        'ajax_link_attr' => 'data-member-directory-ajax-link',
        'partial_param' => 'pba_member_directory_partial',
    ));

    return ob_get_clean();
}

function pba_member_directory_enqueue_styles() {
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
        'pba-member-directory-styles',
        $base_url . 'pba-member-directory.css',
        array('pba-admin-list-styles'),
        file_exists($base_path . 'pba-member-directory.css') ? (string) filemtime($base_path . 'pba-member-directory.css') : '1.0.0'
    );
}

function pba_render_member_directory_page_shell($data, $request_args) {
    ob_start();
    ?>
    <?php echo pba_render_member_directory_hero($data, $request_args); ?>

    <div class="pba-admin-list-card">
        <?php echo pba_render_member_directory_toolbar($request_args); ?>

        <div class="pba-member-directory-list-shell">
            <?php echo pba_render_member_directory_list_shell($data, $request_args); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_member_directory_hero($data, $request_args) {
    ob_start();
    ?>
    <div class="pba-admin-list-hero">
        <div class="pba-admin-list-hero-top">
            <div>
                <p>Find contact and household information for active PBA members, and use the search and sorting tools below to quickly locate the person you need.</p>
            </div>
        </div>
        <div class="pba-admin-list-kpis">
            <div class="pba-admin-list-kpi">
                <span class="pba-admin-list-kpi-label">Filtered Members</span>
                <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['total_filtered'])); ?></span>
            </div>
            <div class="pba-admin-list-kpi">
                <span class="pba-admin-list-kpi-label">On This Page</span>
                <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n(count($data['page_rows']))); ?></span>
            </div>
            <div class="pba-admin-list-kpi">
                <span class="pba-admin-list-kpi-label">Page</span>
                <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($data['pagination']['current_page'])); ?> / <?php echo esc_html(number_format_i18n($data['pagination']['total_pages'])); ?></span>
            </div>
            <div class="pba-admin-list-kpi">
                <span class="pba-admin-list-kpi-label">Page Size</span>
                <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n($request_args['per_page'])); ?></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_member_directory_toolbar($request_args) {
    ob_start();
    ?>
    <div class="pba-admin-list-toolbar">
        <form method="get" class="pba-member-directory-search" id="pba-member-directory-search-form">
            <input type="hidden" name="directory_page" value="1">
            <input type="hidden" name="directory_sort" value="<?php echo esc_attr($request_args['sort']); ?>">
            <input type="hidden" name="directory_direction" value="<?php echo esc_attr($request_args['direction']); ?>">

            <div class="pba-admin-list-field">
                <label for="directory_search">Search</label>
                <input type="text" id="directory_search" name="directory_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Name, email, household, or committee">
            </div>

            <div class="pba-admin-list-field">
                <label for="directory_per_page">Rows</label>
                <select id="directory_per_page" name="directory_per_page">
                    <option value="25" <?php selected($request_args['per_page'], 25); ?>>25</option>
                    <option value="50" <?php selected($request_args['per_page'], 50); ?>>50</option>
                    <option value="100" <?php selected($request_args['per_page'], 100); ?>>100</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                <button type="submit" class="pba-admin-list-btn">Apply</button>
                <a href="<?php echo esc_url(pba_get_member_directory_base_url()); ?>" class="pba-admin-list-btn secondary">Reset</a>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function pba_get_member_directory_request_args() {
    $allowed_sort_columns = array(
        'name',
        'email',
        'household',
        'committees',
    );

    $allowed_sort_directions = array('asc', 'desc');
    $allowed_per_page = array(25, 50, 100);

    $search = isset($_GET['directory_search']) ? sanitize_text_field(wp_unslash($_GET['directory_search'])) : '';
    $sort = isset($_GET['directory_sort']) ? sanitize_key(wp_unslash($_GET['directory_sort'])) : 'name';
    $direction = isset($_GET['directory_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['directory_direction']))) : 'asc';
    $page = isset($_GET['directory_page']) ? max(1, absint($_GET['directory_page'])) : 1;
    $per_page = isset($_GET['directory_per_page']) ? absint($_GET['directory_per_page']) : 25;

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
        'search' => $search,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $page,
        'per_page' => $per_page,
    );
}

function pba_get_member_directory_base_url() {
    return home_url('/member-directory/');
}

function pba_get_member_directory_list_url($overrides = array()) {
    $args = pba_get_member_directory_request_args();

    $query_args = array(
        'directory_search' => $args['search'],
        'directory_sort' => $args['sort'],
        'directory_direction' => $args['direction'],
        'directory_page' => $args['page'],
        'directory_per_page' => $args['per_page'],
    );

    foreach ($overrides as $key => $value) {
        $query_args[$key] = $value;
    }

    foreach ($query_args as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, pba_get_member_directory_base_url());
}

function pba_get_member_directory_list_data($request_args) {
    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,household_id,first_name,last_name,email_address,status',
        'status' => 'eq.Active',
        'order'  => 'last_name.asc,first_name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return is_wp_error($rows) ? $rows : new WP_Error('pba_member_directory_load_failed', 'Unable to load member directory.');
    }

    $household_labels = pba_member_directory_get_household_labels($rows);
    $committee_labels = pba_member_directory_get_committee_labels($rows);

    $prepared_rows = array();
    foreach ($rows as $row) {
        $prepared_rows[] = pba_prepare_member_directory_row($row, $household_labels, $committee_labels);
    }

    $filtered_rows = pba_filter_member_directory_rows($prepared_rows, $request_args);
    $sorted_rows = pba_sort_member_directory_rows($filtered_rows, $request_args['sort'], $request_args['direction']);
    $pagination = pba_paginate_member_directory_rows($sorted_rows, $request_args['page'], $request_args['per_page']);

    return array(
        'all_rows' => $prepared_rows,
        'filtered_rows' => $sorted_rows,
        'page_rows' => $pagination['rows'],
        'pagination' => $pagination,
        'total_filtered' => count($sorted_rows),
    );
}

function pba_prepare_member_directory_row($row, $household_labels, $committee_labels) {
    $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
    $first_name = trim((string) ($row['first_name'] ?? ''));
    $last_name = trim((string) ($row['last_name'] ?? ''));
    $name = trim($first_name . ' ' . $last_name);
    $email = trim((string) ($row['email_address'] ?? ''));
    $household = isset($household_labels[$person_id]) ? trim((string) $household_labels[$person_id]) : '';
    $committees = isset($committee_labels[$person_id]) && is_array($committee_labels[$person_id]) ? $committee_labels[$person_id] : array();
    $committee_text = !empty($committees) ? implode(', ', $committees) : '';

    return array(
        'person_id' => $person_id,
        'name' => $name,
        'name_sort' => strtolower($name),
        'email' => $email,
        'email_sort' => strtolower($email),
        'household' => $household,
        'household_sort' => strtolower($household),
        'committees' => $committees,
        'committee_text' => $committee_text,
        'committee_sort' => strtolower($committee_text),
    );
}

function pba_filter_member_directory_rows($rows, $request_args) {
    $search = strtolower(trim((string) $request_args['search']));

    if ($search === '') {
        return $rows;
    }

    return array_values(array_filter($rows, function ($row) use ($search) {
        $haystack = strtolower(implode(' ', array(
            (string) ($row['name'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['household'] ?? ''),
            (string) ($row['committee_text'] ?? ''),
        )));

        return strpos($haystack, $search) !== false;
    }));
}

function pba_sort_member_directory_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'email':
                $value_a = (string) ($a['email_sort'] ?? '');
                $value_b = (string) ($b['email_sort'] ?? '');
                break;

            case 'household':
                $value_a = (string) ($a['household_sort'] ?? '');
                $value_b = (string) ($b['household_sort'] ?? '');
                break;

            case 'committees':
                $value_a = (string) ($a['committee_sort'] ?? '');
                $value_b = (string) ($b['committee_sort'] ?? '');
                break;

            case 'name':
            default:
                $value_a = (string) ($a['name_sort'] ?? '');
                $value_b = (string) ($b['name_sort'] ?? '');
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

function pba_paginate_member_directory_rows($rows, $page, $per_page) {
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

function pba_render_member_directory_list_shell($data, $request_args) {
    ob_start();

    $pagination = $data['pagination'];
    $page_rows = $data['page_rows'];
    ?>
    <div class="pba-admin-list-resultsbar">
        <div>
            Showing <?php echo esc_html(number_format_i18n($pagination['start_number'])); ?>–<?php echo esc_html(number_format_i18n($pagination['end_number'])); ?> of <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?> members
        </div>
        <div class="pba-admin-list-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-admin-list-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
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

        <table class="pba-admin-list-table pba-member-directory-table">
            <thead>
                <tr>
                    <?php echo pba_render_member_directory_sortable_th('Name', 'name', $request_args); ?>
                    <?php echo pba_render_member_directory_sortable_th('Email', 'email', $request_args); ?>
                    <?php echo pba_render_member_directory_sortable_th('Household', 'household', $request_args); ?>
                    <?php echo pba_render_member_directory_sortable_th('Committees', 'committees', $request_args); ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="4" class="pba-admin-list-empty">No members found for the current filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($page_rows as $row) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($row['name'] !== '' ? $row['name'] : 'Unnamed member'); ?></strong>
                            </td>
                            <td>
                                <?php if ($row['email'] !== '') : ?>
                                    <a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a>
                                <?php else : ?>
                                    <span class="pba-admin-list-muted">No email listed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row['household'] !== '' ? $row['household'] : '—'); ?></td>
                            <td>
                                <?php if ($row['committee_text'] !== '') : ?>
                                    <?php echo esc_html($row['committee_text']); ?>
                                <?php else : ?>
                                    <span class="pba-admin-list-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pba-member-directory-mobile-cards">
        <?php if (empty($page_rows)) : ?>
            <div class="pba-member-directory-mobile-card">
                <div class="pba-admin-list-empty" style="padding:0;">No members found for the current filters.</div>
            </div>
        <?php else : ?>
            <?php foreach ($page_rows as $row) : ?>
                <div class="pba-member-directory-mobile-card">
                    <h3><?php echo esc_html($row['name'] !== '' ? $row['name'] : 'Unnamed member'); ?></h3>

                    <div class="pba-member-directory-mobile-row">
                        <span class="pba-member-directory-mobile-label">Email</span>
                        <?php if ($row['email'] !== '') : ?>
                            <a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a>
                        <?php else : ?>
                            <span class="pba-admin-list-muted">No email listed</span>
                        <?php endif; ?>
                    </div>

                    <div class="pba-member-directory-mobile-row">
                        <span class="pba-member-directory-mobile-label">Household</span>
                        <?php echo esc_html($row['household'] !== '' ? $row['household'] : '—'); ?>
                    </div>

                    <div class="pba-member-directory-mobile-row">
                        <span class="pba-member-directory-mobile-label">Committees</span>
                        <?php if ($row['committee_text'] !== '') : ?>
                            <?php echo esc_html($row['committee_text']); ?>
                        <?php else : ?>
                            <span class="pba-admin-list-muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php echo pba_render_member_directory_pagination($pagination); ?>
    <?php

    return ob_get_clean();
}

function pba_render_member_directory_sortable_th($label, $column, $request_args) {
    $url = pba_get_member_directory_list_url(array(
        'directory_sort' => $column,
        'directory_direction' => ($request_args['sort'] === $column && $request_args['direction'] === 'asc') ? 'desc' : 'asc',
        'directory_page' => 1,
    ));

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $request_args['sort'],
        'current_direction' => $request_args['direction'],
        'url' => $url,
        'link_attr' => 'data-member-directory-ajax-link',
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_member_directory_pagination($pagination) {
    return pba_admin_list_render_pagination(array(
        'pagination' => $pagination,
        'url_builder' => 'pba_get_member_directory_list_url',
        'page_param' => 'directory_page',
        'container_class' => 'pba-admin-list-pagination',
        'muted_class' => 'pba-admin-list-muted',
        'links_class' => 'pba-admin-list-page-links',
        'link_class' => 'pba-admin-list-page-link',
        'current_class' => 'current',
        'ajax_link_attr' => 'data-member-directory-ajax-link',
        'prev_label' => 'Prev',
        'next_label' => 'Next',
    ));
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

if (isset($_GET['pba_member_directory_partial']) && sanitize_text_field(wp_unslash($_GET['pba_member_directory_partial'])) === '1') {
    add_action('template_redirect', 'pba_maybe_render_member_directory_partial');
}

function pba_maybe_render_member_directory_partial() {
    if (!is_user_logged_in()) {
        return;
    }

    pba_member_directory_enqueue_styles();

    $request_args = pba_get_member_directory_request_args();
    $data = pba_get_member_directory_list_data($request_args);

    if (is_wp_error($data)) {
        wp_die('Unable to load member directory.', 500);
    }

    echo pba_render_member_directory_page_shell($data, $request_args);
    exit;
}
<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/pba-admin-list-ui.php';

$member_edit_file = dirname(__FILE__) . '/member-admin-actions.php';
if (file_exists($member_edit_file)) {
    require_once $member_edit_file;
}

add_action('init', 'pba_register_members_shortcode');

if (isset($_GET['pba_members_partial']) && sanitize_text_field(wp_unslash($_GET['pba_members_partial'])) === '1') {
    add_action('template_redirect', 'pba_maybe_render_members_partial');
}

function pba_register_members_shortcode() {
    add_shortcode('pba_members_admin', 'pba_render_members_shortcode');
    add_shortcode('pba_members', 'pba_render_members_shortcode');
}

function pba_maybe_render_members_partial() {
    if (!is_user_logged_in()) {
        return;
    }

    if (!current_user_can('pba_manage_roles')) {
        return;
    }

    $view = isset($_GET['member_view']) ? sanitize_text_field(wp_unslash($_GET['member_view'])) : 'list';
    if ($view !== 'list') {
        return;
    }

    pba_members_admin_enqueue_styles();

    $request_args = pba_get_members_list_request_args();
    $data = pba_get_members_list_data($request_args);

    if (is_wp_error($data)) {
        wp_die('Unable to load members.', 500);
    }

    echo pba_render_members_dynamic_content($data, $request_args);
    exit;
}

function pba_render_members_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!current_user_can('pba_manage_roles')) {
        return '<p>You do not have permission to access this page.</p>';
    }

    pba_members_admin_enqueue_styles();

    $view = isset($_GET['member_view']) ? sanitize_text_field(wp_unslash($_GET['member_view'])) : 'list';

    $person_id = 0;
    if (isset($_GET['person_id'])) {
        $person_id = absint($_GET['person_id']);
    } elseif (isset($_GET['member_id'])) {
        $person_id = absint($_GET['member_id']);
    }

    if ($view === 'edit' && $person_id > 0) {
        return pba_render_member_edit_view($person_id);
    }

    return pba_render_members_list_view();
}

function pba_members_admin_enqueue_styles() {
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
        'pba-members-admin-styles',
        $base_url . 'pba-members-admin.css',
        array('pba-admin-list-styles'),
        file_exists($base_path . 'pba-members-admin.css') ? (string) filemtime($base_path . 'pba-members-admin.css') : '1.0.0'
    );
}

function pba_render_members_status_message() {
    $status = isset($_GET['pba_members_status']) ? sanitize_text_field(wp_unslash($_GET['pba_members_status'])) : '';

    if ($status === '') {
        return '';
    }

    $success_messages = array(
        'member_saved'                  => 'Member saved successfully.',
        'member_disabled'               => 'Member disabled successfully.',
        'member_enabled'                => 'Member enabled successfully.',
        'invite_cancelled'              => 'Invitation cancelled successfully.',
        'invite_resent'                 => 'Invitation resent successfully.',
        'member_removed_from_household' => 'Member was removed from the household successfully.',
        'member_deleted'                => 'Person record deleted successfully.',
        'roles_updated'                 => 'Member roles updated successfully.',
        'password_reset_sent'           => 'Password reset email sent successfully.',
        'member_removed'                => 'Member removed from household successfully.',
        'member_updated'                => 'Member updated successfully.',
    );

    $error_messages = array(
        'invalid_request'                            => 'We could not process that request.',
        'save_failed'                                => 'We could not save that member.',
        'remove_from_household_failed'               => 'We could not remove that member from the household.',
        'remove_from_household_blocked_house_admin'  => 'Household Admins cannot be removed from a household here.',
        'remove_from_household_blocked_last_admin'   => 'The last active Household Admin cannot be removed from the household.',
        'delete_person_failed'                       => 'We could not delete that person record.',
        'delete_person_blocked_house_admin'          => 'Household Admins cannot be hard deleted here.',
        'delete_person_blocked_wp_user'              => 'This person is linked to a WordPress user and cannot be hard deleted.',
        'delete_person_blocked_committees'           => 'This person has active committee assignments and cannot be hard deleted.',
        'delete_person_blocked_household'            => 'Remove this person from their household before hard deleting.',
        'roles_update_failed'                        => 'We could not update the member roles.',
        'password_reset_failed'                      => 'We could not send the password reset email.',
        'disable_failed'                             => 'We could not disable that member.',
        'enable_failed'                              => 'We could not enable that member.',
        'remove_failed'                              => 'We could not remove that member from the household.',
        'delete_failed'                              => 'We could not hard delete that member.',
        'protected_member'                           => 'That member is protected and cannot be changed with this action.',
        'member_update_failed'                       => 'We could not save that member.',
        'invalid_member_input'                       => 'Please provide the required member fields.',
        'invalid_member_email'                       => 'Please provide a valid email address.',
        'cancel_failed'                              => 'We could not cancel that invitation.',
        'resend_failed'                              => 'We could not resend that invitation.',
    );

    if (isset($success_messages[$status])) {
        if (function_exists('pba_shared_render_message')) {
            return pba_shared_render_message('success', 'Success', $success_messages[$status]);
        }

        return '<div class="pba-message success"><div class="pba-message-title">Success</div><div class="pba-message-body">' . esc_html($success_messages[$status]) . '</div></div>';
    }

    $text = isset($error_messages[$status]) ? $error_messages[$status] : ucfirst(str_replace('_', ' ', $status));

    if (function_exists('pba_shared_render_message')) {
        return pba_shared_render_message('error', 'Please review', $text);
    }

    return '<div class="pba-message error"><div class="pba-message-title">Please review</div><div class="pba-message-body">' . esc_html($text) . '</div></div>';
}

function pba_get_members_base_url() {
    global $post;

    if ($post instanceof WP_Post) {
        $permalink = get_permalink($post);
        if (!empty($permalink)) {
            return $permalink;
        }
    }

    return home_url('/members/');
}

function pba_get_members_list_request_args() {
    $allowed_sort_columns = array(
        'name',
        'email',
        'status',
        'roles',
        'household',
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
        'search' => $search,
        'status_filter' => $status_filter,
        'sort' => $sort,
        'direction' => $direction,
        'page' => $page,
        'per_page' => $per_page,
    );
}

function pba_get_members_list_url($overrides = array()) {
    $args = pba_get_members_list_request_args();

    $query_args = array(
        'member_search' => $args['search'],
        'member_status_filter' => $args['status_filter'],
        'member_sort' => $args['sort'],
        'member_direction' => $args['direction'],
        'member_page' => $args['page'],
        'member_per_page' => $args['per_page'],
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

function pba_render_members_list_view() {
    $request_args = pba_get_members_list_request_args();
    $data = pba_get_members_list_data($request_args);

    if (is_wp_error($data)) {
        return '<p>Unable to load members.</p>';
    }

    ob_start();
    ?>
    <style>
        html.pba-members-cursor-reset,
        html.pba-members-cursor-reset *,
        body.pba-members-cursor-reset,
        body.pba-members-cursor-reset *,
        #pba-members-admin-root,
        #pba-members-admin-root * {
            cursor: auto !important;
        }

        #pba-members-admin-root.is-busy,
        #pba-members-admin-root.is-busy * {
            cursor: wait !important;
        }

        .pba-member-edit-wrap .pba-summary-value {
            font-size: 18px;
            line-height: 1.35;
            font-weight: 600;
        }

        .pba-member-edit-wrap .pba-summary-label {
            font-size: 12px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .pba-maintenance-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .pba-maintenance-note {
            margin: 0 0 14px;
            color: #666;
        }

        .pba-btn.danger {
            background: #8b1e2d;
            border-color: #8b1e2d;
            color: #fff;
        }

        .pba-btn.danger:hover,
        .pba-btn.danger:focus {
            background: #721826;
            border-color: #721826;
            color: #fff;
        }
    </style>

    <div class="pba-members-wrap pba-page-wrap">
        <?php echo pba_render_members_status_message(); ?>

        <div id="pba-members-admin-root">
            <?php echo pba_render_members_dynamic_content($data, $request_args); ?>
        </div>
    </div>

    <?php
    echo pba_admin_list_render_resizable_table_script();

    echo pba_admin_list_render_ajax_script(array(
        'root_id' => 'pba-members-admin-root',
        'form_id' => 'pba-members-search-form',
        'shell_selector' => '.pba-members-admin-list-shell',
        'loading_selector' => '.pba-admin-list-grid-wrap',
        'ajax_link_attr' => 'data-members-ajax-link',
        'partial_param' => 'pba_members_partial',
    ));
    ?>
    <script>
    (function () {
        var root = document.getElementById('pba-members-admin-root');

        function clearCursorEverywhere() {
            var nodes;
            var i;

            document.documentElement.classList.add('pba-members-cursor-reset');
            document.body.classList.add('pba-members-cursor-reset');

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
            var link = event.target.closest('[data-members-ajax-link="1"]');
            if (link) {
                setBusy();
            }
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target.closest('#pba-members-search-form');
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

function pba_render_members_dynamic_content($data, $request_args) {
    $status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : array();
    $pagination = isset($data['pagination']) && is_array($data['pagination']) ? $data['pagination'] : array(
        'total_rows' => 0,
        'start_number' => 0,
        'end_number' => 0,
    );

    ob_start();
    ?>
    <div class="pba-members-admin-list-shell">
        <div class="pba-admin-list-hero">
            <div class="pba-admin-list-hero-top">
                <div>
                    <h2 style="margin:0 0 6px;">Members</h2>
                    <p>Manage member accounts, household assignments, and role access.</p>
                </div>
                <div class="pba-admin-list-badge">
                    Total Members: <?php echo esc_html(number_format_i18n((int) ($pagination['total_rows'] ?? 0))); ?>
                </div>
            </div>

            <div class="pba-admin-list-kpis">
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Active</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['active'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Inactive</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['inactive'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Linked WP Users</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['linked_wp_users'] ?? 0))); ?></span>
                </div>
                <div class="pba-admin-list-kpi">
                    <span class="pba-admin-list-kpi-label">Email Verified</span>
                    <span class="pba-admin-list-kpi-value"><?php echo esc_html(number_format_i18n((int) ($data['summary']['email_verified'] ?? 0))); ?></span>
                </div>
            </div>
        </div>

        <div class="pba-admin-list-card">
            <form id="pba-members-search-form" class="pba-admin-list-toolbar" method="get" action="<?php echo esc_url(pba_get_members_base_url()); ?>">
                <div class="pba-admin-list-toolbar-grid">
                    <div class="pba-admin-list-field">
                        <label for="pba-member-search">Search</label>
                        <input id="pba-member-search" type="text" name="member_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Search by name, email, household, or role">
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-member-status-filter">Status</label>
                        <select id="pba-member-status-filter" name="member_status_filter">
                            <option value="">All statuses</option>
                            <?php foreach ($status_options as $status_option) : ?>
                                <option value="<?php echo esc_attr($status_option); ?>" <?php selected($request_args['status_filter'], $status_option); ?>>
                                    <?php echo esc_html($status_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-admin-list-field">
                        <label for="pba-member-per-page">Rows per page</label>
                        <select id="pba-member-per-page" name="member_per_page">
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
                    <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(pba_get_members_base_url()); ?>">Reset</a>
                </div>
            </form>

            <?php echo pba_render_members_table($data, $request_args); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_members_table($data, $request_args) {
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
            Showing <?php echo esc_html(number_format_i18n((int) ($pagination['start_number'] ?? 0))); ?>–<?php echo esc_html(number_format_i18n((int) ($pagination['end_number'] ?? 0))); ?> of <?php echo esc_html(number_format_i18n((int) ($pagination['total_rows'] ?? 0))); ?> members
        </div>
        <div class="pba-admin-list-filter-summary">
            <?php if ($request_args['search'] !== '') : ?>
                <span class="pba-admin-list-chip">Search: <?php echo esc_html($request_args['search']); ?></span>
            <?php endif; ?>
            <?php if ($request_args['status_filter'] !== '') : ?>
                <span class="pba-admin-list-chip">Status: <?php echo esc_html($request_args['status_filter']); ?></span>
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

        <table class="pba-admin-list-table pba-table pba-members-table pba-resizable-table" id="pba-members-admin-table" data-resize-key="pbaMembersAdminColumnWidthsV9" data-min-col-width="100">
        <colgroup data-pba-resizable-cols="1">
                <col style="width: 110px;">
                <col style="width: 130px;">
                <col style="width: 100px;">
                <col style="width: 125px;">
                <col style="width: 130px;">
                <col style="width: 160px;">
            </colgroup>
            <thead>
                <tr>
                    <?php echo pba_render_members_sortable_th('Member', 'name', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Email', 'email', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Status / Verified', 'status', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Roles', 'roles', $request_args); ?>
                    <?php echo pba_render_members_sortable_th('Household', 'household', $request_args); ?>
                    <th data-resizable="false">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($page_rows)) : ?>
                    <tr>
                        <td colspan="6" class="pba-admin-list-empty">No members found for the current filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($page_rows as $row) : ?>
                        <?php
                        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
                        $full_name = trim((string) ($row['full_name'] ?? ''));
                        $email = trim((string) ($row['email_address'] ?? ''));
                        $status = trim((string) ($row['status'] ?? ''));
                        $roles = isset($row['role_names']) && is_array($row['role_names']) ? $row['role_names'] : array();
                        $household_label = trim((string) ($row['household_label'] ?? ''));
                        $email_verified = !empty($row['email_verified']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($full_name !== '' ? $full_name : 'Unnamed member'); ?></strong>
                                <div class="pba-admin-list-muted">Person ID <?php echo esc_html((string) $person_id); ?></div>
                            </td>
                            <td><?php echo esc_html($email !== '' ? $email : '—'); ?></td>
                            <td>
                                <div class="pba-members-status-stack">
                                    <?php echo pba_render_members_status_badge($status); ?>
                                    <?php echo pba_render_members_boolean_badge($email_verified, 'Verified', 'Not verified'); ?>
                                </div>
                            </td>
                            <td><?php echo esc_html(!empty($roles) ? implode(', ', $roles) : '—'); ?></td>
                            <td><?php echo esc_html($household_label !== '' ? $household_label : '—'); ?></td>
                            <td>
                                <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'member_view' => 'edit',
                                    'person_id'   => $person_id,
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
    $next_direction = ($request_args['sort'] === $column && $request_args['direction'] === 'asc') ? 'desc' : 'asc';

    $url = pba_get_members_list_url(array(
        'member_sort' => $column,
        'member_direction' => $next_direction,
        'member_page' => 1,
    ));

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $request_args['sort'],
        'current_direction' => $request_args['direction'],
        'url' => $url,
        'link_attr' => 'data-members-ajax-link',
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_members_pagination($pagination) {
    return pba_admin_list_render_pagination(array(
        'pagination' => $pagination,
        'url_builder' => function ($overrides) {
            return pba_get_members_list_url($overrides);
        },
        'page_param' => 'member_page',
        'container_class' => 'pba-admin-list-pagination',
        'muted_class' => 'pba-admin-list-muted',
        'links_class' => 'pba-admin-list-page-links',
    ));
}

function pba_get_members_list_data($request_args) {
    $person_rows = pba_supabase_get('Person', array(
        'select' => 'person_id,first_name,last_name,email_address,status,wp_user_id,email_verified,household_id,last_modified_at',
        'limit'  => 10000,
    ));

    if (is_wp_error($person_rows)) {
        return $person_rows;
    }

    $person_rows = is_array($person_rows) ? $person_rows : array();

    $person_ids = array();
    $household_ids = array();

    foreach ($person_rows as $row) {
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

    $role_map = pba_get_members_role_map($person_ids);
    $household_map = pba_get_members_household_map($household_ids);

    $rows = array();
    $summary = array(
        'active' => 0,
        'inactive' => 0,
        'linked_wp_users' => 0,
        'email_verified' => 0,
    );

    foreach ($person_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        $first_name = trim((string) ($row['first_name'] ?? ''));
        $last_name = trim((string) ($row['last_name'] ?? ''));
        $full_name = trim($first_name . ' ' . $last_name);
        $status = trim((string) ($row['status'] ?? ''));
        $wp_user_id = isset($row['wp_user_id']) ? (int) $row['wp_user_id'] : 0;
        $email_verified = !empty($row['email_verified']);

        if ($status === 'Active') {
            $summary['active']++;
        } else {
            $summary['inactive']++;
        }

        if ($wp_user_id > 0) {
            $summary['linked_wp_users']++;
        }

        if ($email_verified) {
            $summary['email_verified']++;
        }

        $rows[] = array(
            'person_id' => $person_id,
            'full_name' => $full_name,
            'email_address' => (string) ($row['email_address'] ?? ''),
            'status' => $status,
            'role_names' => isset($role_map[$person_id]) ? $role_map[$person_id] : array(),
            'wp_user_id' => $wp_user_id > 0 ? (string) $wp_user_id : '',
            'email_verified' => $email_verified,
            'household_label' => ($household_id > 0 && isset($household_map[$household_id])) ? $household_map[$household_id] : '',
            'last_modified_at' => (string) ($row['last_modified_at'] ?? ''),
        );
    }

    $status_options = array();
    foreach ($rows as $row) {
        $row_status = trim((string) ($row['status'] ?? ''));
        if ($row_status !== '') {
            $status_options[$row_status] = $row_status;
        }
    }
    natcasesort($status_options);

    $rows = pba_filter_members_rows($rows, $request_args);
    $rows = pba_sort_members_rows($rows, $request_args['sort'], $request_args['direction']);

    $pagination = pba_paginate_members_rows($rows, $request_args['page'], $request_args['per_page']);
    $page_rows = array_slice($rows, $pagination['offset'], $pagination['per_page']);

    return array(
        'summary' => $summary,
        'rows' => $rows,
        'page_rows' => $page_rows,
        'pagination' => $pagination,
        'status_options' => array_values($status_options),
    );
}

function pba_get_members_role_map($person_ids) {
    $map = array();

    if (empty($person_ids)) {
        return $map;
    }

    $role_links = pba_supabase_get('Person_to_Role', array(
        'select'    => 'person_id,role_id,is_active',
        'person_id' => 'in.(' . implode(',', array_map('intval', $person_ids)) . ')',
        'is_active' => 'eq.true',
        'limit'     => 10000,
    ));

    if (is_wp_error($role_links) || !is_array($role_links) || empty($role_links)) {
        return $map;
    }

    $role_ids = array();
    foreach ($role_links as $link) {
        $role_id = isset($link['role_id']) ? (int) $link['role_id'] : 0;
        if ($role_id > 0) {
            $role_ids[] = $role_id;
        }
    }

    $role_ids = array_values(array_unique($role_ids));
    $role_name_map = array();

    if (!empty($role_ids)) {
        $role_rows = pba_supabase_get('Role', array(
            'select'  => 'role_id,role_name',
            'role_id' => 'in.(' . implode(',', array_map('intval', $role_ids)) . ')',
            'limit'   => count($role_ids),
        ));

        if (!is_wp_error($role_rows) && is_array($role_rows)) {
            foreach ($role_rows as $role_row) {
                $role_id = isset($role_row['role_id']) ? (int) $role_row['role_id'] : 0;
                $role_name = trim((string) ($role_row['role_name'] ?? ''));
                if ($role_id > 0 && $role_name !== '') {
                    $role_name_map[$role_id] = $role_name;
                }
            }
        }
    }

    foreach ($role_links as $link) {
        $person_id = isset($link['person_id']) ? (int) $link['person_id'] : 0;
        $role_id = isset($link['role_id']) ? (int) $link['role_id'] : 0;

        if ($person_id < 1 || $role_id < 1 || !isset($role_name_map[$role_id])) {
            continue;
        }

        if (!isset($map[$person_id])) {
            $map[$person_id] = array();
        }

        $map[$person_id][] = $role_name_map[$role_id];
    }

    foreach ($map as $person_id => $role_names) {
        $role_names = array_values(array_unique($role_names));
        natcasesort($role_names);
        $map[$person_id] = array_values($role_names);
    }

    return $map;
}

function pba_get_members_household_map($household_ids) {
    $map = array();

    if (empty($household_ids)) {
        return $map;
    }

    $household_rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,pb_street_number,pb_street_name',
        'household_id' => 'in.(' . implode(',', array_map('intval', $household_ids)) . ')',
        'limit'        => count($household_ids),
    ));

    if (is_wp_error($household_rows) || !is_array($household_rows)) {
        return $map;
    }

    foreach ($household_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1) {
            continue;
        }

        $street_number = trim((string) ($row['pb_street_number'] ?? ''));
        $street_name = trim((string) ($row['pb_street_name'] ?? ''));
        $map[$household_id] = trim($street_number . ' ' . $street_name);
    }

    return $map;
}

function pba_filter_members_rows($rows, $request_args) {
    $search = strtolower(trim((string) ($request_args['search'] ?? '')));
    $status_filter = trim((string) ($request_args['status_filter'] ?? ''));

    return array_values(array_filter($rows, function ($row) use ($search, $status_filter) {
        if ($status_filter !== '' && (string) ($row['status'] ?? '') !== $status_filter) {
            return false;
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['full_name'] ?? ''),
                (string) ($row['email_address'] ?? ''),
                (string) ($row['status'] ?? ''),
                !empty($row['email_verified']) ? 'verified' : 'not verified',
                implode(' ', isset($row['role_names']) && is_array($row['role_names']) ? $row['role_names'] : array()),
                (string) ($row['household_label'] ?? ''),
            )));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }));
}

function pba_sort_members_rows($rows, $sort, $direction) {
    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'email':
                $a_value = strtolower((string) ($a['email_address'] ?? ''));
                $b_value = strtolower((string) ($b['email_address'] ?? ''));
                break;
            case 'status':
                $a_value = strtolower((string) ($a['status'] ?? '')) . '|' . (!empty($a['email_verified']) ? '1' : '0');
                $b_value = strtolower((string) ($b['status'] ?? '')) . '|' . (!empty($b['email_verified']) ? '1' : '0');
                break;
            case 'roles':
                $a_value = strtolower(implode(', ', isset($a['role_names']) ? $a['role_names'] : array()));
                $b_value = strtolower(implode(', ', isset($b['role_names']) ? $b['role_names'] : array()));
                break;
            case 'household':
                $a_value = strtolower((string) ($a['household_label'] ?? ''));
                $b_value = strtolower((string) ($b['household_label'] ?? ''));
                break;
            case 'name':
            default:
                $a_value = strtolower((string) ($a['full_name'] ?? ''));
                $b_value = strtolower((string) ($b['full_name'] ?? ''));
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

function pba_paginate_members_rows($rows, $page, $per_page) {
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

function pba_render_members_status_badge($status) {
    $status = trim((string) $status);
    $class = 'default';

    if ($status === 'Active') {
        $class = 'accepted';
    } elseif ($status === 'Inactive' || $status === 'Disabled' || $status === 'Unregistered') {
        $class = 'disabled';
    } elseif ($status === 'Pending') {
        $class = 'pending';
    }

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($status !== '' ? $status : 'Unknown', $class);
    }

    return '<span class="pba-status-badge ' . esc_attr($class) . '">' . esc_html($status !== '' ? $status : 'Unknown') . '</span>';
}

function pba_render_members_boolean_badge($value, $true_label = 'Yes', $false_label = 'No') {
    $label = $value ? $true_label : $false_label;
    $class = $value ? 'accepted' : 'default';

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($label, $class);
    }

    return '<span class="pba-status-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

/* ===== Restored inline edit view ===== */

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

function pba_render_members_summary_card($label, $value, $note = '') {
    if (function_exists('pba_shared_render_summary_card')) {
        return pba_shared_render_summary_card($label, $value, $note);
    }

    ob_start();
    ?>
    <div class="pba-summary-card">
        <div class="pba-summary-label"><?php echo esc_html($label); ?></div>
        <div class="pba-summary-value"><?php echo esc_html((string) $value); ?></div>
        <?php if ($note !== '') : ?>
            <div class="pba-summary-note"><?php echo esc_html($note); ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_member_edit_view($member_id) {
    $member_rows = pba_supabase_get('Person', array(
        'select'    => 'person_id,household_id,first_name,last_name,email_address,status,wp_user_id,email_verified,last_modified_at,directory_visibility_level',
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
    $invite_data = function_exists('pba_get_member_invite_data_by_person') ? pba_get_member_invite_data_by_person($member_id) : null;

    $wp_user_id = isset($member['wp_user_id']) && $member['wp_user_id'] !== null && $member['wp_user_id'] !== '' ? (string) $member['wp_user_id'] : '';
    $email_verified = !empty($member['email_verified']) ? 'Yes' : 'No';
    $status = (string) ($member['status'] ?? '');
    $display_name = trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? '')));
    $can_hard_delete = function_exists('pba_admin_can_hard_delete_person') ? pba_admin_can_hard_delete_person((int) $member['person_id']) : false;

    ob_start();
    ?>
    <div class="pba-member-edit-wrap pba-page-wrap">
        <p>
            <a class="pba-admin-list-btn secondary" href="<?php echo esc_url(pba_get_members_base_url()); ?>">&larr; Back to Members</a>
        </p>

        <?php echo pba_render_members_status_message(); ?>

        <div class="pba-section">
            <h3 style="margin:0 0 18px;"><?php echo esc_html($display_name !== '' ? $display_name : ('Member #' . (int) $member['person_id'])); ?></h3>
            <div class="pba-summary-grid">
                <?php echo pba_render_members_summary_card('Status', $status !== '' ? $status : '—'); ?>
                <?php echo pba_render_members_summary_card('Linked WP User ID', $wp_user_id !== '' ? $wp_user_id : 'Not linked'); ?>
                <?php echo pba_render_members_summary_card('Email Verified', $email_verified); ?>
                <?php echo pba_render_members_summary_card('Last Modified', function_exists('pba_format_datetime_display') ? pba_format_datetime_display($member['last_modified_at'] ?? '') : (string) ($member['last_modified_at'] ?? '')); ?>
                <?php if (is_array($invite_data) && !empty($invite_data['expires_at_gmt'])) : ?>
                    <?php echo pba_render_members_summary_card('Invite Expires', date('m/d/y h:i A', (int) $invite_data['expires_at_gmt'])); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($status, array('Active', 'Disabled', 'Pending', 'Expired'), true)) : ?>
            <div class="pba-section">
                <div class="pba-actions">
                    <?php if ($status === 'Active') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                            <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                            <input type="hidden" name="action" value="pba_admin_disable_member">
                            <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                            <button type="submit" class="pba-admin-list-btn secondary">Disable</button>
                        </form>
                    <?php elseif ($status === 'Disabled') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                            <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                            <input type="hidden" name="action" value="pba_admin_enable_member">
                            <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                            <button type="submit" class="pba-admin-list-btn secondary">Enable</button>
                        </form>
                    <?php elseif ($status === 'Pending') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form" onsubmit="return confirm('Cancel this invite?');">
                            <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                            <input type="hidden" name="action" value="pba_admin_cancel_invite">
                            <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                            <button type="submit" class="pba-admin-list-btn secondary">Cancel Invite</button>
                        </form>
                    <?php elseif ($status === 'Expired') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-action-form">
                            <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                            <input type="hidden" name="action" value="pba_admin_resend_invite">
                            <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                            <button type="submit" class="pba-admin-list-btn secondary">Resend Invite</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="pba-section">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-member-edit-form">
                <?php wp_nonce_field('pba_member_admin_action', 'pba_member_admin_nonce'); ?>
                <input type="hidden" name="action" value="pba_save_member_admin">
                <input type="hidden" name="member_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">

                <table class="pba-table">
                    <tr>
                        <th><label for="household_id">Household</label></th>
                        <td>
                            <div class="pba-field">
                                <select name="household_id" id="household_id">
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
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="first_name">First Name</label></th>
                        <td><div class="pba-field"><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($member['first_name'] ?? ''); ?>" required></div></td>
                    </tr>

                    <tr>
                        <th><label for="last_name">Last Name</label></th>
                        <td><div class="pba-field"><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($member['last_name'] ?? ''); ?>" required></div></td>
                    </tr>

                    <tr>
                        <th><label for="email_address">Email Address</label></th>
                        <td><div class="pba-field"><input type="email" name="email_address" id="email_address" value="<?php echo esc_attr($member['email_address'] ?? ''); ?>"></div></td>
                    </tr>

                    <tr>
                        <th><label for="directory_visibility_level">Directory Listing</label></th>
                        <td>
                            <div class="pba-field">
                                <select name="directory_visibility_level" id="directory_visibility_level">
                                    <option value="hidden" <?php selected(($member['directory_visibility_level'] ?? 'hidden'), 'hidden'); ?>>Hide from directory</option>
                                    <option value="name_only" <?php selected(($member['directory_visibility_level'] ?? ''), 'name_only'); ?>>Show name only</option>
                                    <option value="name_email" <?php selected(($member['directory_visibility_level'] ?? ''), 'name_email'); ?>>Show name and email</option>
                                </select>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="status">Status</label></th>
                        <td>
                            <div class="pba-field">
                                <select name="status" id="status">
                                    <option value="Unregistered" <?php selected($member['status'] ?? '', 'Unregistered'); ?>>Unregistered</option>
                                    <option value="Pending" <?php selected($member['status'] ?? '', 'Pending'); ?>>Pending</option>
                                    <option value="Active" <?php selected($member['status'] ?? '', 'Active'); ?>>Active</option>
                                    <option value="Disabled" <?php selected($member['status'] ?? '', 'Disabled'); ?>>Disabled</option>
                                    <option value="Expired" <?php selected($member['status'] ?? '', 'Expired'); ?>>Expired</option>
                                </select>
                            </div>
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
                                    <div class="pba-section" style="margin:0 0 10px; padding:12px;">
                                        <label>
                                            <input type="checkbox" name="committee_ids[]" value="<?php echo esc_attr($committee_id); ?>" <?php checked($selected); ?>>
                                            <?php echo esc_html($committee['committee_name']); ?>
                                        </label>
                                        <div class="pba-field" style="margin-top:6px;">
                                            <input
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
                    <button type="submit" class="pba-btn">Save Member</button>
                    <a class="pba-btn secondary" href="<?php echo esc_url(pba_get_members_base_url()); ?>">Cancel</a>
                </p>
            </form>
        </div>

        <div class="pba-section">
            <div class="pba-section-heading">
                <h3>Administrative Maintenance</h3>
                <p class="pba-maintenance-note">Use these actions carefully. Removing from household is reversible. Hard delete permanently removes the person record and related assignments.</p>
            </div>

            <div class="pba-maintenance-actions">
                <?php if ((int) ($member['household_id'] ?? 0) > 0) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this person from their household?');">
                        <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                        <input type="hidden" name="action" value="pba_admin_remove_member_from_household">
                        <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                        <button type="submit" class="pba-btn secondary">Remove from Household</button>
                    </form>
                <?php else : ?>
                    <span class="pba-admin-list-muted">This person is not currently assigned to a household.</span>
                <?php endif; ?>

                <?php if ($can_hard_delete) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Hard delete this person record permanently? This cannot be undone.');">
                        <?php wp_nonce_field('pba_admin_member_action', 'pba_admin_member_action_nonce'); ?>
                        <input type="hidden" name="action" value="pba_admin_hard_delete_person">
                        <input type="hidden" name="person_id" value="<?php echo esc_attr((int) $member['person_id']); ?>">
                        <button type="submit" class="pba-btn danger">Hard Delete Person</button>
                    </form>
                <?php else : ?>
                    <span class="pba-admin-list-muted">Hard delete is blocked when the person is still linked to a household, has active committee assignments, or is a Household Admin.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
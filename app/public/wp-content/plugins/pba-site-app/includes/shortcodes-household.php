<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/pba-admin-list-ui.php';

add_action('init', 'pba_register_household_shortcode');

function pba_register_household_shortcode() {
    add_shortcode('pba_household_dashboard', 'pba_render_household_dashboard');
}

function pba_household_get_status_meta($status) {
    $status = (string) $status;

    switch ($status) {
        case 'Active':
            return array(
                'label' => 'Accepted',
                'class' => 'accepted',
            );
        case 'Pending':
            return array(
                'label' => 'Pending',
                'class' => 'pending',
            );
        case 'Expired':
            return array(
                'label' => 'Expired',
                'class' => 'expired',
            );
        case 'Disabled':
            return array(
                'label' => 'Disabled',
                'class' => 'disabled',
            );
        default:
            return array(
                'label' => $status !== '' ? $status : 'Unknown',
                'class' => 'default',
            );
    }
}

function pba_household_render_status_badge($status) {
    $meta = pba_household_get_status_meta($status);

    if (function_exists('pba_shared_render_status_badge')) {
        return pba_shared_render_status_badge($meta['label'], $meta['class']);
    }

    return sprintf(
        '<span class="pba-status-badge %1$s">%2$s</span>',
        esc_attr($meta['class']),
        esc_html($meta['label'])
    );
}

function pba_household_render_message($status, $duplicate_messages) {
    if ($status === '') {
        return '';
    }

    $messages = array(
        'account_created'                              => array('type' => 'success', 'title' => 'Success', 'text' => 'Your House Admin account has been created.'),
        'invite_created'                               => array('type' => 'success', 'title' => 'Success', 'text' => 'Invitation record(s) and email(s) were created successfully.'),
        'invite_created_email_partial'                 => array('type' => 'error',   'title' => 'Please review', 'text' => 'Invitation record(s) were created, but at least one invitation email could not be sent.'),
        'invite_created_with_duplicates'               => array('type' => 'error',   'title' => 'Please review', 'text' => 'Some invitations were created, but one or more people had already been invited.'),
        'invite_created_email_partial_with_duplicates' => array('type' => 'error',   'title' => 'Please review', 'text' => 'Some invitations were created, but there were duplicate invitees or email delivery failures.'),
        'already_invited'                              => array('type' => 'error',   'title' => 'Please review', 'text' => 'No new invitations were created because the invitee(s) had already been invited.'),
        'no_invites_created'                           => array('type' => 'error',   'title' => 'Please review', 'text' => 'No invitation records were created.'),
        'invalid_invite_row'                           => array('type' => 'error',   'title' => 'Please review', 'text' => 'Please complete every field in each row, with no leading or trailing spaces.'),
        'invalid_invite_name'                          => array('type' => 'error',   'title' => 'Please review', 'text' => 'Please enter valid first and last names for all invite rows.'),
        'invalid_invite_email'                         => array('type' => 'error',   'title' => 'Please review', 'text' => 'Please enter a valid email address for all invite rows.'),
        'duplicate_invite_email'                       => array('type' => 'error',   'title' => 'Please review', 'text' => 'The same email address was entered more than once in the invite table.'),
        'member_disabled'                              => array('type' => 'success', 'title' => 'Success', 'text' => 'The member was disabled successfully.'),
        'member_enabled'                               => array('type' => 'success', 'title' => 'Success', 'text' => 'The member was enabled successfully.'),
        'invite_cancelled'                             => array('type' => 'success', 'title' => 'Success', 'text' => 'The pending invitation was cancelled successfully.'),
        'invite_resent'                                => array('type' => 'success', 'title' => 'Success', 'text' => 'The invitation was resent successfully.'),
        'member_removed'                               => array('type' => 'success', 'title' => 'Success', 'text' => 'The household member was removed successfully.'),
        'disable_failed'                               => array('type' => 'error',   'title' => 'Please review', 'text' => 'We could not disable that member.'),
        'enable_failed'                                => array('type' => 'error',   'title' => 'Please review', 'text' => 'We could not enable that member.'),
        'cancel_failed'                                => array('type' => 'error',   'title' => 'Please review', 'text' => 'We could not cancel that invitation.'),
        'resend_failed'                                => array('type' => 'error',   'title' => 'Please review', 'text' => 'We could not resend that invitation.'),
        'remove_failed'                                => array('type' => 'error',   'title' => 'Please review', 'text' => 'We could not remove that household member.'),
        'remove_blocked_house_admin'                   => array('type' => 'error',   'title' => 'Please review', 'text' => 'Household Admins are protected and cannot be managed from this page.'),
        'remove_blocked_last_admin'                    => array('type' => 'error',   'title' => 'Please review', 'text' => 'The last active Household Admin cannot be removed or disabled.'),
    );

    $message = isset($messages[$status])
        ? $messages[$status]
        : array(
            'type'  => 'error',
            'title' => 'Please review',
            'text'  => str_replace('_', ' ', $status),
        );

    $list_items = array();
    if (!empty($duplicate_messages) && in_array($status, array('invite_created_with_duplicates', 'invite_created_email_partial_with_duplicates', 'already_invited'), true)) {
        $list_items = $duplicate_messages;
    }

    if (function_exists('pba_shared_render_message')) {
        return pba_shared_render_message($message['type'], $message['title'], $message['text'], $list_items);
    }

    ob_start();
    ?>
    <div class="pba-message <?php echo esc_attr($message['type']); ?>">
        <div class="pba-message-title"><?php echo esc_html($message['title']); ?></div>
        <div class="pba-message-body"><?php echo esc_html($message['text']); ?></div>
        <?php if (!empty($list_items)) : ?>
            <ul class="pba-duplicate-list">
                <?php foreach ($list_items as $item) : ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_household_render_summary_card($label, $value, $note) {
    if (function_exists('pba_shared_render_summary_card')) {
        return pba_shared_render_summary_card($label, $value, $note);
    }

    ob_start();
    ?>
    <div class="pba-summary-card">
        <div class="pba-summary-label"><?php echo esc_html($label); ?></div>
        <div class="pba-summary-value"><?php echo esc_html((string) $value); ?></div>
        <div class="pba-summary-note"><?php echo esc_html($note); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

if (!function_exists('pba_household_person_has_role_name')) {
    function pba_household_person_has_role_name($person_id, $role_name) {
        $person_id = (int) $person_id;
        $role_name = trim((string) $role_name);

        if ($person_id < 1 || $role_name === '') {
            return false;
        }

        $rows = pba_supabase_get('Person_to_Role', array(
            'select'    => 'role_id',
            'person_id' => 'eq.' . $person_id,
            'is_active' => 'eq.true',
            'limit'     => 20,
        ));

        if (is_wp_error($rows) || empty($rows)) {
            return false;
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
            return false;
        }

        $role_rows = pba_supabase_get('Role', array(
            'select'  => 'role_id,role_name',
            'role_id' => 'in.(' . implode(',', $role_ids) . ')',
            'limit'   => count($role_ids),
        ));

        if (is_wp_error($role_rows) || empty($role_rows)) {
            return false;
        }

        foreach ($role_rows as $role_row) {
            if (isset($role_row['role_name']) && (string) $role_row['role_name'] === $role_name) {
                return true;
            }
        }

        return false;
    }
}

function pba_get_household_members_request_args() {
    $allowed_sort_columns = array(
        'first_name',
        'last_name',
        'email_address',
        'status',
        'last_modified_at',
    );

    $allowed_sort_directions = array('asc', 'desc');
    $allowed_per_page = array(10, 25, 50, 100);

    $search = isset($_GET['household_members_search']) ? sanitize_text_field(wp_unslash($_GET['household_members_search'])) : '';
    $status_filter = isset($_GET['household_members_status']) ? sanitize_text_field(wp_unslash($_GET['household_members_status'])) : '';
    $sort = isset($_GET['household_members_sort']) ? sanitize_key(wp_unslash($_GET['household_members_sort'])) : 'last_modified_at';
    $direction = isset($_GET['household_members_direction']) ? strtolower(sanitize_text_field(wp_unslash($_GET['household_members_direction']))) : 'desc';
    $page = isset($_GET['household_members_page']) ? max(1, absint($_GET['household_members_page'])) : 1;
    $per_page = isset($_GET['household_members_per_page']) ? absint($_GET['household_members_per_page']) : 10;

    if (!in_array($sort, $allowed_sort_columns, true)) {
        $sort = 'last_modified_at';
    }

    if (!in_array($direction, $allowed_sort_directions, true)) {
        $direction = 'desc';
    }

    if (!in_array($per_page, $allowed_per_page, true)) {
        $per_page = 10;
    }

    if (!in_array($status_filter, array('', 'Active', 'Pending', 'Expired', 'Disabled'), true)) {
        $status_filter = '';
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

function pba_get_household_dashboard_base_url() {
    global $post;

    if ($post instanceof WP_Post) {
        $permalink = get_permalink($post);
        if (!empty($permalink)) {
            return $permalink;
        }
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    return home_url($request_uri);
}

function pba_get_household_members_list_url($overrides = array()) {
    $args = pba_get_household_members_request_args();
    $query_args = $_GET;

    $query_args['household_members_search'] = $args['search'];
    $query_args['household_members_status'] = $args['status_filter'];
    $query_args['household_members_sort'] = $args['sort'];
    $query_args['household_members_direction'] = $args['direction'];
    $query_args['household_members_page'] = $args['page'];
    $query_args['household_members_per_page'] = $args['per_page'];

    foreach ($overrides as $key => $value) {
        $query_args[$key] = $value;
    }

    foreach ($query_args as $key => $value) {
        if (
            $value === '' ||
            $value === null ||
            ($key === 'household_members_page' && (int) $value === 1) ||
            ($key === 'household_members_per_page' && (int) $value === 10) ||
            ($key === 'household_members_sort' && (string) $value === 'last_modified_at') ||
            ($key === 'household_members_direction' && (string) $value === 'desc')
        ) {
            unset($query_args[$key]);
        }
    }

    return add_query_arg($query_args, pba_get_household_dashboard_base_url()) . '#pba-household-members-table';
}

function pba_filter_household_rows_by_request($rows, $request_args) {
    $search = strtolower(trim((string) $request_args['search']));
    $status_filter = trim((string) $request_args['status_filter']);

    return array_values(array_filter($rows, function ($row) use ($search, $status_filter) {
        $status = isset($row['status']) ? (string) $row['status'] : '';

        if ($status_filter !== '' && $status !== $status_filter) {
            return false;
        }

        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string) ($row['first_name'] ?? ''),
                (string) ($row['last_name'] ?? ''),
                (string) ($row['email_address'] ?? ''),
                (string) ($row['status'] ?? ''),
            )));

            if (strpos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }));
}

function pba_sort_household_rows_by_request($rows, $sort, $direction) {
    $rows = is_array($rows) ? array_values($rows) : array();

    usort($rows, function ($a, $b) use ($sort, $direction) {
        switch ($sort) {
            case 'first_name':
                $a_value = strtolower((string) ($a['first_name'] ?? ''));
                $b_value = strtolower((string) ($b['first_name'] ?? ''));
                break;

            case 'last_name':
                $a_value = strtolower((string) ($a['last_name'] ?? ''));
                $b_value = strtolower((string) ($b['last_name'] ?? ''));
                break;

            case 'email_address':
                $a_value = strtolower((string) ($a['email_address'] ?? ''));
                $b_value = strtolower((string) ($b['email_address'] ?? ''));
                break;

            case 'status':
                $a_value = strtolower((string) ($a['status'] ?? ''));
                $b_value = strtolower((string) ($b['status'] ?? ''));
                break;

            case 'last_modified_at':
            default:
                $a_value = strtotime((string) ($a['last_modified_at'] ?? '')) ?: 0;
                $b_value = strtotime((string) ($b['last_modified_at'] ?? '')) ?: 0;
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

function pba_paginate_household_rows($rows, $page, $per_page) {
    $total_rows = count($rows);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page = min(max(1, (int) $page), $total_pages);
    $offset = ($page - 1) * $per_page;

    $start_number = $total_rows > 0 ? $offset + 1 : 0;
    $end_number = min($offset + $per_page, $total_rows);

    return array(
        'current_page' => $page,
        'per_page' => $per_page,
        'offset' => $offset,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'start_number' => $start_number,
        'end_number' => $end_number,
    );
}

function pba_render_household_members_sortable_th($label, $column, $request_args) {
    $next_direction = ($request_args['sort'] === $column && $request_args['direction'] === 'asc') ? 'desc' : 'asc';

    return pba_admin_list_render_sortable_th(array(
        'label' => $label,
        'column' => $column,
        'current_sort' => $request_args['sort'],
        'current_direction' => $request_args['direction'],
        'url' => pba_get_household_members_list_url(array(
            'household_members_sort' => $column,
            'household_members_direction' => $next_direction,
            'household_members_page' => 1,
        )),
        'link_class' => 'pba-admin-list-sort-link',
        'indicator_class' => 'pba-admin-list-sort-indicator',
    ));
}

function pba_render_household_members_pagination($pagination) {
    return pba_admin_list_render_pagination(array(
        'pagination' => array(
            'page' => $pagination['current_page'],
            'total_pages' => $pagination['total_pages'],
        ),
        'url_builder' => function ($overrides) {
            return pba_get_household_members_list_url($overrides);
        },
        'page_param' => 'household_members_page',
        'container_class' => 'pba-admin-list-pagination',
        'muted_class' => 'pba-admin-list-muted',
        'links_class' => 'pba-admin-list-page-links',
    ));
}

function pba_render_household_previous_invitations_table($rows, $title, $request_args, $pagination) {
    ob_start();
    ?>
    <div class="pba-section" id="pba-household-members-table">
        <div class="pba-section-heading">
            <h3><?php echo esc_html($title); ?></h3>
            <p class="pba-section-subtitle">Any Household Admin for this household can manage invites and non-admin members below. Household Admins are protected and cannot be removed or disabled from this page.</p>
        </div>

        <div class="pba-admin-list-resultsbar" style="padding-left:0; padding-right:0; border-bottom:0;">
            <div>
                Showing <?php echo esc_html(number_format_i18n($pagination['start_number'])); ?>–<?php echo esc_html(number_format_i18n($pagination['end_number'])); ?>
                of <?php echo esc_html(number_format_i18n($pagination['total_rows'])); ?> members / invitations
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

        <form method="get" action="<?php echo esc_url(pba_get_household_dashboard_base_url()) . '#pba-household-members-table'; ?>" class="pba-admin-list-toolbar" style="margin-bottom:14px;">
            <div class="pba-admin-list-toolbar-grid">
                <div class="pba-admin-list-field">
                    <label for="household_members_search">Search</label>
                    <input id="household_members_search" type="text" name="household_members_search" value="<?php echo esc_attr($request_args['search']); ?>" placeholder="Search by first name, last name, email, or status">
                </div>

                <div class="pba-admin-list-field">
                    <label for="household_members_status">Status</label>
                    <select id="household_members_status" name="household_members_status">
                        <option value="">All statuses</option>
                        <?php foreach (array('Active', 'Pending', 'Expired', 'Disabled') as $status_option) : ?>
                            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($request_args['status_filter'], $status_option); ?>>
                                <?php echo esc_html($status_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pba-admin-list-field">
                    <label for="household_members_per_page">Rows</label>
                    <select id="household_members_per_page" name="household_members_per_page">
                        <?php foreach (array(10, 25, 50, 100) as $option) : ?>
                            <option value="<?php echo esc_attr((string) $option); ?>" <?php selected($request_args['per_page'], $option); ?>>
                                <?php echo esc_html((string) $option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <input type="hidden" name="household_members_sort" value="<?php echo esc_attr($request_args['sort']); ?>">
            <input type="hidden" name="household_members_direction" value="<?php echo esc_attr($request_args['direction']); ?>">
            <input type="hidden" name="household_members_page" value="1">

            <div class="pba-admin-list-toolbar-actions">
                <button type="submit" class="pba-btn">Apply</button>
                <a class="pba-btn secondary" href="<?php echo esc_url(pba_get_household_dashboard_base_url()) . '#pba-household-members-table'; ?>">Reset</a>
            </div>
        </form>

        <div class="pba-table-wrap">
            <table class="pba-table pba-resizable-table" id="pba-household-members-grid" data-resize-key="pbaHouseholdMembersColumnWidthsV4" data-min-col-width="120">
                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                    <col>
                    <col>
                </colgroup>
                <thead>
                    <tr>
                        <?php echo pba_render_household_members_sortable_th('First Name', 'first_name', $request_args); ?>
                        <?php echo pba_render_household_members_sortable_th('Last Name', 'last_name', $request_args); ?>
                        <?php echo pba_render_household_members_sortable_th('Email Address', 'email_address', $request_args); ?>
                        <?php echo pba_render_household_members_sortable_th('Status', 'status', $request_args); ?>
                        <?php echo pba_render_household_members_sortable_th('Updated', 'last_modified_at', $request_args); ?>
                        <th data-resizable="false">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr>
                            <td colspan="6">No household members or invitations found yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
                            $status = isset($row['status']) ? (string) $row['status'] : '';
                            $is_house_admin = pba_household_person_has_role_name($person_id, 'PBAHouseholdAdmin');
                            ?>
                            <tr>
                                <td><?php echo esc_html(isset($row['first_name']) ? $row['first_name'] : ''); ?></td>
                                <td><?php echo esc_html(isset($row['last_name']) ? $row['last_name'] : ''); ?></td>
                                <td class="pba-email-cell"><?php echo esc_html(isset($row['email_address']) ? $row['email_address'] : ''); ?></td>
                                <td><?php echo pba_household_render_status_badge($status); ?></td>
                                <td><?php echo esc_html(pba_format_datetime_display(isset($row['last_modified_at']) ? $row['last_modified_at'] : '')); ?></td>
                                <td class="pba-action-col">
                                    <?php if ($is_house_admin) : ?>
                                        <span class="pba-admin-list-muted">House Admin</span>
                                    <?php elseif ($status === 'Active') : ?>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                                <?php wp_nonce_field('pba_household_disable_action', 'pba_household_disable_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_household_disable_member">
                                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                                <button type="submit" class="pba-btn secondary pba-action-btn" data-processing-text="Disabling...">Disable</button>
                                            </form>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form" onsubmit="return confirm('Remove this household member?');">
                                                <?php wp_nonce_field('pba_household_remove_action', 'pba_household_remove_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_household_remove_member">
                                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                                <button type="submit" class="pba-btn secondary pba-action-btn" data-processing-text="Removing...">Remove</button>
                                            </form>
                                        </div>
                                    <?php elseif ($status === 'Pending') : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form" onsubmit="return confirm('Cancel this invite?');">
                                            <?php wp_nonce_field('pba_household_cancel_action', 'pba_household_cancel_nonce'); ?>
                                            <input type="hidden" name="action" value="pba_household_cancel_invite">
                                            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                            <button type="submit" class="pba-btn secondary pba-action-btn" data-processing-text="Cancelling...">Cancel</button>
                                        </form>
                                    <?php elseif ($status === 'Expired') : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                            <?php wp_nonce_field('pba_household_resend_action', 'pba_household_resend_nonce'); ?>
                                            <input type="hidden" name="action" value="pba_household_resend_invite">
                                            <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                            <button type="submit" class="pba-btn pba-action-btn" data-processing-text="Resending...">Resend</button>
                                        </form>
                                    <?php elseif ($status === 'Disabled') : ?>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                                <?php wp_nonce_field('pba_household_enable_action', 'pba_household_enable_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_household_enable_member">
                                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                                <button type="submit" class="pba-btn pba-action-btn" data-processing-text="Enabling...">Enable</button>
                                            </form>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form" onsubmit="return confirm('Remove this household member?');">
                                                <?php wp_nonce_field('pba_household_remove_action', 'pba_household_remove_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_household_remove_member">
                                                <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                                <button type="submit" class="pba-btn secondary pba-action-btn" data-processing-text="Removing...">Remove</button>
                                            </form>
                                        </div>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo pba_render_household_members_pagination($pagination); ?>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_household_invite_section() {
    ob_start();
    ?>
    <div class="pba-section">
        <div class="pba-section-heading">
            <h3>Invite Household Members</h3>
            <p class="pba-section-subtitle">Send invitations so members of your household can create site accounts and access the association website.</p>
        </div>

        <div class="pba-callout">
            <strong>Tip:</strong> Any Household Admin for this household can manage invitations and member access. Each person should have a unique email address.
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pba-household-invite-form" class="pba-auth-form" novalidate>
            <?php wp_nonce_field('pba_household_invite_action', 'pba_household_invite_nonce'); ?>
            <input type="hidden" name="action" value="pba_household_send_invites">

            <div id="pba-household-form-error" class="pba-form-error"></div>

            <div class="pba-table-wrap">
                <table class="pba-table" id="pba-household-invite-table">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email Address</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><div class="pba-field"><input type="text" name="invite_first_name[]" required></div></td>
                            <td><div class="pba-field"><input type="text" name="invite_last_name[]" required></div></td>
                            <td><div class="pba-field"><input type="email" name="invite_email[]" required></div></td>
                            <td><button type="button" class="pba-btn remove pba-row-remove-btn">Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pba-actions">
                <button type="button" class="pba-btn secondary" id="pba-household-add-row">Add More</button>
                <button type="submit" class="pba-btn" id="pba-household-invite-all" data-processing-text="Inviting...">Invite All</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function pba_household_format_address($household_id) {
    $household_id = (int) $household_id;

    if ($household_id < 1) {
        return '';
    }

    $rows = pba_supabase_get('Household', array(
        'select'       => 'pb_street_number,pb_street_name',
        'household_id' => 'eq.' . $household_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows) || empty($rows) || !is_array($rows)) {
        return '';
    }

    $household = $rows[0];
    $street_number = isset($household['pb_street_number']) ? trim((string) $household['pb_street_number']) : '';
    $street_name = isset($household['pb_street_name']) ? trim((string) $household['pb_street_name']) : '';
    $street = trim($street_number . ' ' . $street_name);

    if ($street === '') {
        return '';
    }

    return $street . ', Plymouth, MA';
}

function pba_render_household_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_user_has_house_admin_access()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $base_url = plugin_dir_url(__FILE__) . 'css/';
    $base_path = dirname(__FILE__) . '/css/';

    wp_enqueue_style(
        'pba-admin-list-styles',
        $base_url . 'pba-admin-list-styles.css',
        array(),
        file_exists($base_path . 'pba-admin-list-styles.css') ? (string) filemtime($base_path . 'pba-admin-list-styles.css') : '1.0.0'
    );

    $household_id      = (int) pba_get_current_household_id();
    $inviter_person_id = (int) pba_get_current_house_admin_person_id();

    if (empty($household_id) || empty($inviter_person_id)) {
        return '<p>Household context is missing for this account.</p>';
    }

    pba_update_pending_household_invites_to_expired($household_id, $inviter_person_id);

    $accepted_rows = pba_get_people_for_household_by_status($household_id, 0, 'Active');
    $pending_rows  = pba_get_people_for_household_by_status($household_id, 0, 'Pending');
    $expired_rows  = pba_get_people_for_household_by_status($household_id, 0, 'Expired');
    $disabled_rows = pba_get_people_for_household_by_status($household_id, 0, 'Disabled');

    $accepted_rows = is_array($accepted_rows) ? $accepted_rows : array();
    $pending_rows  = is_array($pending_rows) ? $pending_rows : array();
    $expired_rows  = is_array($expired_rows) ? $expired_rows : array();
    $disabled_rows = is_array($disabled_rows) ? $disabled_rows : array();

    $all_rows = array_merge(
        $accepted_rows,
        $pending_rows,
        $expired_rows,
        $disabled_rows
    );

    $request_args = pba_get_household_members_request_args();
    $filtered_rows = pba_filter_household_rows_by_request($all_rows, $request_args);
    $sorted_rows = pba_sort_household_rows_by_request($filtered_rows, $request_args['sort'], $request_args['direction']);
    $pagination = pba_paginate_household_rows($sorted_rows, $request_args['page'], $request_args['per_page']);
    $page_rows = array_slice($sorted_rows, $pagination['offset'], $pagination['per_page']);

    $status = isset($_GET['pba_household_status']) ? sanitize_text_field(wp_unslash($_GET['pba_household_status'])) : '';
    $duplicate_messages = get_transient('pba_household_duplicate_messages_' . get_current_user_id());

    if ($duplicate_messages !== false) {
        delete_transient('pba_household_duplicate_messages_' . get_current_user_id());
    } else {
        $duplicate_messages = array();
    }

    $accepted_count = count($accepted_rows);
    $pending_count  = count($pending_rows);
    $expired_count  = count($expired_rows);
    $disabled_count = count($disabled_rows);

    $household_address = pba_household_format_address($household_id);
    $page_title = 'Manage Household Members';

    if ($household_address !== '') {
        $page_title .= ' for: ' . $household_address;
    }

    ob_start();

    if (function_exists('pba_shared_list_ui_render_styles')) {
        echo pba_shared_list_ui_render_styles();
    }
    ?>
    <div class="pba-page-wrap pba-household-wrap">
        <div class="pba-page-hero">
            <div class="pba-page-eyebrow">Household Access</div>
            <h2 class="pba-page-title"><?php echo esc_html($page_title); ?></h2>
            <p class="pba-page-intro">
                Invite members of your household, review invitation status, and manage household access. Any Household Admin for this household can manage these records.
            </p>
        </div>

        <?php echo pba_household_render_message($status, $duplicate_messages); ?>

        <div class="pba-summary-grid">
            <?php echo pba_household_render_summary_card('Accepted', $accepted_count, 'Current active household members'); ?>
            <?php echo pba_household_render_summary_card('Pending', $pending_count, 'Invitations awaiting acceptance'); ?>
            <?php echo pba_household_render_summary_card('Expired', $expired_count, 'Invitations that can be resent'); ?>
            <?php echo pba_household_render_summary_card('Disabled', $disabled_count, 'People whose access is turned off'); ?>
        </div>

        <?php echo pba_render_household_invite_section(); ?>
        <?php echo pba_render_household_previous_invitations_table($page_rows, 'Household Members and Invitations', $request_args, $pagination); ?>
    </div>
    <?php

    if (function_exists('pba_shared_list_ui_render_household_script')) {
        echo pba_shared_list_ui_render_household_script();
    }

    echo pba_admin_list_render_resizable_table_script();

    return ob_get_clean();
}
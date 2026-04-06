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

function pba_render_households_admin_list_view() {
    $search = isset($_GET['household_search']) ? sanitize_text_field(wp_unslash($_GET['household_search'])) : '';

    $rows = pba_supabase_get('Household', array(
        'select' => 'household_id,pb_street_number,pb_street_name,household_admin_first_name,household_admin_last_name,household_status,owner_occupied,last_modified_at,owner_name_raw',
        'order'  => 'pb_street_name.asc,pb_street_number.asc',
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load households.</p>';
    }

    if ($search !== '') {
        $rows = array_values(array_filter($rows, function ($row) use ($search) {
            $haystack = implode(' ', array(
                isset($row['pb_street_number']) ? $row['pb_street_number'] : '',
                isset($row['pb_street_name']) ? $row['pb_street_name'] : '',
                isset($row['owner_name_raw']) ? $row['owner_name_raw'] : '',
                isset($row['household_admin_first_name']) ? $row['household_admin_first_name'] : '',
                isset($row['household_admin_last_name']) ? $row['household_admin_last_name'] : '',
            ));

            return stripos($haystack, $search) !== false;
        }));
    }

    $household_ids = array();
    foreach ($rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id > 0) {
            $household_ids[] = $household_id;
        }
    }

    $household_stats = pba_get_household_stats_for_admin_list($household_ids);

    ob_start();
    ?>
    <style>
        .pba-households-wrap { max-width: 1400px; margin: 0 auto; }
        .pba-households-search { margin: 18px 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .pba-households-search input[type="text"] { width: 340px; max-width: 100%; padding: 10px 12px; }
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
        .pba-households-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .pba-households-table th,
        .pba-households-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .pba-households-table th { background: #f3f3f3; }
        .pba-households-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }
        .pba-households-message.error { background: #f8e9e9; }
        .pba-households-muted { color: #666; font-size: 13px; }
        .pba-households-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #eef3f8;
            color: #21425c;
            white-space: nowrap;
        }
    </style>

    <div class="pba-households-wrap">
        <p>View and manage household records across the association.</p>

        <?php echo pba_render_households_admin_status_message(); ?>

        <form method="get" class="pba-households-search">
            <input type="text" name="household_search" value="<?php echo esc_attr($search); ?>" placeholder="Search by address, owner, or admin">
            <button type="submit" class="pba-households-btn secondary">Search</button>
        </form>

        <table class="pba-households-table">
            <thead>
                <tr>
                    <th>Address</th>
                    <th>Owner</th>
                    <th>House Admin</th>
                    <th>Status</th>
                    <th>Active Members</th>
                    <th>Total Members</th>
                    <th>Owner Occupied</th>
                    <th>Last Modified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="9">No households found.</td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
                        $address = trim(((string) ($row['pb_street_number'] ?? '')) . ' ' . ((string) ($row['pb_street_name'] ?? '')));
                        $stats = isset($household_stats[$household_id]) ? $household_stats[$household_id] : array(
                            'active_count' => 0,
                            'total_count'  => 0,
                            'house_admin'  => '',
                        );

                        $stored_house_admin = trim(((string) ($row['household_admin_first_name'] ?? '')) . ' ' . ((string) ($row['household_admin_last_name'] ?? '')));
                        $display_house_admin = $stored_house_admin !== '' ? $stored_house_admin : $stats['house_admin'];
                        $owner_name_raw = trim((string) ($row['owner_name_raw'] ?? ''));
                        $household_status = trim((string) ($row['household_status'] ?? ''));
                        $owner_occupied = array_key_exists('owner_occupied', $row) ? $row['owner_occupied'] : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html($address !== '' ? $address : ('Household #' . $household_id)); ?></td>
                            <td><?php echo esc_html($owner_name_raw !== '' ? $owner_name_raw : '—'); ?></td>
                            <td><?php echo esc_html($display_house_admin !== '' ? $display_house_admin : '—'); ?></td>
                            <td><?php echo esc_html($household_status !== '' ? $household_status : '—'); ?></td>
                            <td><?php echo esc_html((string) $stats['active_count']); ?></td>
                            <td><?php echo esc_html((string) $stats['total_count']); ?></td>
                            <td><?php echo esc_html($owner_occupied === null ? '—' : ($owner_occupied ? 'Yes' : 'No')); ?></td>
                            <td><?php echo esc_html(function_exists('pba_format_datetime_display') ? pba_format_datetime_display($row['last_modified_at'] ?? '') : ($row['last_modified_at'] ?? '')); ?></td>
                            <td>
                                <a class="pba-households-btn secondary" href="<?php echo esc_url(add_query_arg(array(
                                    'household_view' => 'edit',
                                    'household_id'   => $household_id,
                                ), home_url('/households/'))); ?>">View / Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
        <h2>Household</h2>
        <p>
            <a class="pba-households-btn secondary" href="<?php echo esc_url(home_url('/households/')); ?>">Back to Households</a>
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
                        <a class="pba-households-btn secondary" href="<?php echo esc_url(home_url('/households/')); ?>">Cancel</a>
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
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', home_url('/households/')));
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
        ), home_url('/households/')));
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
        ), home_url('/households/')));
        exit;
    }

    wp_safe_redirect(add_query_arg(array(
        'household_view'        => 'edit',
        'household_id'          => $household_id,
        'pba_households_status' => 'household_saved',
    ), home_url('/households/')));
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
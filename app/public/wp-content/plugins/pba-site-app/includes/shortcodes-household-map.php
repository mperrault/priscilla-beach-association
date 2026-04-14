<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_household_map_shortcode');
add_action('wp_enqueue_scripts', 'pba_register_household_map_assets');
add_action('wp_ajax_pba_get_household_map_data', 'pba_get_household_map_data_ajax');

function pba_register_household_map_shortcode() {
    add_shortcode('pba_household_map', 'pba_render_household_map_shortcode');
}

function pba_register_household_map_assets() {
    wp_register_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        array(),
        '1.9.4'
    );

    wp_register_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        array(),
        '1.9.4',
        true
    );

    wp_register_script(
        'pba-household-map',
        plugins_url('../assets/js/pba-household-map.js', __FILE__),
        array('leaflet-js'),
        '1.0.5',
        true
    );
}

function pba_render_household_map_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (
        !pba_current_person_has_role('PBABoardMember')
        && !pba_current_person_has_role('PBAAdmin')
    ) {
        return '<p>You do not have permission to access this page.</p>';
    }

    wp_enqueue_style('leaflet-css');
    wp_enqueue_script('leaflet-js');
    wp_enqueue_script('pba-household-map');

    $current_person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;
    $is_admin          = pba_current_person_has_role('PBAAdmin');
    $is_board_member   = pba_current_person_has_role('PBABoardMember');
    $can_view_details  = $is_admin || $is_board_member;

    wp_localize_script('pba-household-map', 'pbaHouseholdMap', array(
        'ajaxUrl'         => admin_url('admin-ajax.php'),
        'nonce'           => wp_create_nonce('pba_household_map_nonce'),
        'detailBaseUrl'   => trailingslashit(home_url('/households/')),
        'currentPersonId' => $current_person_id,
        'canViewDetails'  => $can_view_details ? '1' : '0',
        'mapCenterLat'    => '41.9363',
        'mapCenterLng'    => '-70.5665',
        'mapZoom'         => '16',
    ));

    ob_start();
    ?>
    <style>
        .pba-household-map-wrap {
            max-width: 1200px;
            margin: 0 auto;
        }

        .pba-household-map-card {
            background: #ffffff;
            border: 1px solid #d7d7d7;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pba-household-map-note {
            color: #555;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        .pba-household-map-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
        }

        .pba-household-map-search {
            flex: 1 1 280px;
            min-width: 220px;
        }

        .pba-household-map-search input,
        .pba-household-map-filter select {
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            border: 1px solid #c9d2df;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.35;
            box-sizing: border-box;
        }

        .pba-household-map-filter {
            width: 220px;
            max-width: 100%;
        }

        .pba-household-map-status {
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 6px;
            background: #eef6ee;
            display: none;
        }

        .pba-household-map-status.error {
            background: #f8e9e9;
            color: #8a1f1f;
        }

        .pba-household-map-status.active {
            display: block;
        }

        #pba-household-map-canvas {
            width: 100%;
            min-height: 680px;
            border: 1px solid #d7d7d7;
            border-radius: 10px;
            overflow: hidden;
            background: #f5f7fa;
        }

        .pba-household-map-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 14px;
            color: #555;
            font-size: 13px;
        }

        .pba-household-map-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pba-household-map-legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, 0.18);
        }

        .pba-household-map-popup {
            min-width: 220px;
            line-height: 1.45;
        }

        .pba-household-map-popup-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .pba-household-map-popup-row {
            margin: 2px 0;
        }

        .pba-household-map-popup-link {
            display: inline-block;
            margin-top: 8px;
            color: #0d3b66;
            font-weight: 600;
            text-decoration: none;
        }

        .pba-household-map-popup-link:hover {
            text-decoration: underline;
        }

        /* Keep map interactions isolated from page-level wait cursor styles */
        #pba-household-map-canvas .leaflet-container {
            cursor: grab !important;
        }

        #pba-household-map-canvas .leaflet-container.leaflet-dragging {
            cursor: grabbing !important;
        }

        #pba-household-map-canvas .leaflet-popup-content-wrapper,
        #pba-household-map-canvas .leaflet-popup-content,
        #pba-household-map-canvas .leaflet-popup-tip-container,
        #pba-household-map-canvas .leaflet-popup-tip {
            cursor: default !important;
        }

        #pba-household-map-canvas .leaflet-control a,
        #pba-household-map-canvas .leaflet-bar a,
        #pba-household-map-canvas .leaflet-popup-close-button,
        #pba-household-map-canvas .leaflet-interactive,
        #pba-household-map-canvas .leaflet-marker-icon,
        #pba-household-map-canvas .leaflet-marker-shadow {
            cursor: pointer !important;
        }

        #pba-household-map-canvas .leaflet-popup {
            overflow: visible;
        }

        #pba-household-map-canvas .leaflet-popup-content-wrapper {
            position: relative;
        }

        #pba-household-map-canvas .leaflet-popup-close-button {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 18px !important;
            height: 18px !important;
            padding: 0 !important;
            margin: 0 !important;
            border: 0 !important;
            display: block !important;
            line-height: 18px !important;
            font-size: 16px !important;
            text-align: center;
            text-decoration: none !important;
            box-sizing: border-box;
            overflow: hidden;
        }

        @media (max-width: 700px) {
            #pba-household-map-canvas {
                min-height: 560px;
            }

            .pba-household-map-filter {
                width: 100%;
            }
        }
    </style>

    <div class="pba-household-map-wrap">
        <div class="pba-household-map-card">
            <!-- h2>PBA Household Map</h2 -->
            <p class="pba-household-map-note">
                Browse PBA households on a neighborhood map. Search by address, owner, or household admin.
            </p>

            <div id="pba-household-map-status" class="pba-household-map-status"></div>

            <div class="pba-household-map-toolbar">
                <div class="pba-household-map-search">
                    <input
                        type="text"
                        id="pba-household-map-search"
                        placeholder="Search by address, owner, or household admin"
                        autocomplete="off"
                    >
                </div>

                <div class="pba-household-map-filter">
                    <select id="pba-household-map-filter-status">
                        <option value="">All household statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="pba-household-map-filter">
                    <select id="pba-household-map-filter-occupancy">
                        <option value="">All occupancy types</option>
                        <option value="owner_occupied">Owner occupied</option>
                        <option value="not_owner_occupied">Not owner occupied</option>
                    </select>
                </div>
            </div>

            <div id="pba-household-map-canvas"></div>

            <div class="pba-household-map-legend">
                <span class="pba-household-map-legend-item">
                    <span class="pba-household-map-legend-swatch" style="background:#0d3b66;"></span>
                    Active
                </span>
                <span class="pba-household-map-legend-item">
                    <span class="pba-household-map-legend-swatch" style="background:#6b7280;"></span>
                    Inactive / other
                </span>
                <span class="pba-household-map-legend-item">
                    <span class="pba-household-map-legend-swatch" style="background:#1d4ed8;"></span>
                    Owner occupied
                </span>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_get_household_map_data_ajax() {
    check_ajax_referer('pba_household_map_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => 'You must be logged in to view the household map.',
        ), 401);
    }

    $allowed = pba_current_person_has_role('PBABoardMember')
        || pba_current_person_has_role('PBAAdmin');

    if (!$allowed) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to view the household map.',
        ), 403);
    }

    $select = implode(',', array(
        'household_id',
        'pb_street_number',
        'pb_street_name',
        'household_status',
        'household_admin_first_name',
        'household_admin_last_name',
        'household_admin_email_address',
        'owner_name_raw',
        'owner_occupied',
        'latitude',
        'longitude',
    ));

    $rows = pba_supabase_get('Household', array(
        'select'    => $select,
        'latitude'  => 'not.is.null',
        'longitude' => 'not.is.null',
        'limit'     => 1000,
        'order'     => 'pb_street_name.asc,pb_street_number.asc',
    ));

    if (is_wp_error($rows)) {
        wp_send_json_error(array(
            'message' => 'Unable to load household map data.',
            'error'   => $rows->get_error_message(),
        ), 500);
    }

    if (!is_array($rows)) {
        wp_send_json_success(array());
    }

    $is_admin        = pba_current_person_has_role('PBAAdmin');
    $is_board_member = pba_current_person_has_role('PBABoardMember');
    $can_view_detail = $is_admin || $is_board_member;

    $data = array();

    foreach ($rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        $lat          = isset($row['latitude']) ? (float) $row['latitude'] : 0.0;
        $lng          = isset($row['longitude']) ? (float) $row['longitude'] : 0.0;

        if (
            $household_id < 1
            || $lat === 0.0
            || $lng === 0.0
            || $lng < -70.575
            || $lng > -70.560
            || $lat < 41.930
            || $lat > 41.942
        ) {
            continue;
        }

        $street_number = isset($row['pb_street_number']) ? trim((string) $row['pb_street_number']) : '';
        $street_name   = isset($row['pb_street_name']) ? trim((string) $row['pb_street_name']) : '';
        $address       = trim($street_number . ' ' . $street_name);

        $admin_name = trim(
            ((string) ($row['household_admin_first_name'] ?? '')) . ' ' .
            ((string) ($row['household_admin_last_name'] ?? ''))
        );

        $status = isset($row['household_status']) ? trim((string) $row['household_status']) : '';
        $owner  = isset($row['owner_name_raw']) ? trim((string) $row['owner_name_raw']) : '';

        $data[] = array(
            'household_id'          => $household_id,
            'address'               => $address,
            'street_number'         => $street_number,
            'street_name'           => $street_name,
            'household_status'      => $status,
            'household_admin_name'  => $admin_name,
            'household_admin_email' => $can_view_detail ? trim((string) ($row['household_admin_email_address'] ?? '')) : '',
            'owner_name_raw'        => $owner,
            'owner_occupied'        => !empty($row['owner_occupied']),
            'latitude'              => $lat,
            'longitude'             => $lng,
            'detail_url'            => $can_view_detail
                ? add_query_arg(array(
                    'household_view' => 'edit',
                    'household_id'   => $household_id,
                ), trailingslashit(home_url('/households/')))
                : '',
        );
    }

    wp_send_json_success($data);
}
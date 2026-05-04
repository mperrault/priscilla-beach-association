<?php
/**
 * PBA MassGIS household currency checks.
 *
 * Install:
 * 1. Add the columns in pba_massgis_household_currency.sql to Supabase.
 * 2. Put this file in wp-content/plugins/pba-site-app/includes/massgis-household-currency.php.
 * 3. Require it from the main plugin file:
 *      require_once plugin_dir_path(__FILE__) . 'includes/massgis-household-currency.php';
 * 4. Add the display call shown in pba_household_shortcode_integration.txt.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PBA_MASSGIS_PARCEL_QUERY_URL')) {
    define('PBA_MASSGIS_PARCEL_QUERY_URL', 'https://services1.arcgis.com/hGdibHYSPO59RG1h/ArcGIS/rest/services/Massachusetts_Property_Tax_Parcels/FeatureServer/0/query');
}

if (!defined('PBA_MASSGIS_CHECK_BATCH_SIZE')) {
    define('PBA_MASSGIS_CHECK_BATCH_SIZE', 25);
}

if (!defined('PBA_MASSGIS_LOOKUP_CITY')) {
    define('PBA_MASSGIS_LOOKUP_CITY', 'PLYMOUTH');
}

add_action('init', 'pba_massgis_schedule_household_currency_check');
add_action('pba_massgis_household_currency_check', 'pba_massgis_check_households_batch');
add_action('wp_ajax_pba_massgis_run_household_batch_ajax', 'pba_massgis_handle_run_household_batch_ajax');
/*
 * Keep the admin-post handler as a no-JS fallback, but normal clicks should use AJAX.
 */
add_action('admin_post_pba_massgis_check_household_now', 'pba_massgis_handle_check_household_now');

add_action('wp_ajax_pba_massgis_check_household_ajax', 'pba_massgis_handle_check_household_ajax');
add_action('wp_footer', 'pba_massgis_render_ajax_script');
add_action('admin_post_pba_massgis_resolve_household', 'pba_massgis_handle_resolve_household');

/**
 * Schedules a daily batch check. WP-Cron runs when the site receives traffic.
 * For production reliability, call wp-cron.php from a real server cron as well.
 */
function pba_massgis_schedule_household_currency_check() {
    if (!wp_next_scheduled('pba_massgis_household_currency_check')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pba_massgis_household_currency_check');
    }
}

/**
 * Checks the oldest/un-checked households first.
 */
function pba_massgis_check_households_batch() {
    if (!function_exists('pba_supabase_get')) {
        return new WP_Error('pba_massgis_missing_supabase_get', 'pba_supabase_get() is not available.');
    }

    $rows = pba_supabase_get('Household', array(
        'select' => '*',
        'order'  => 'massgis_last_checked_at.asc.nullsfirst,household_id.asc',
        'limit'  => (int) PBA_MASSGIS_CHECK_BATCH_SIZE,
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return $rows;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1) {
            continue;
        }

        $enabled = isset($row['massgis_check_enabled']) ? (bool) $row['massgis_check_enabled'] : true;
        if (!$enabled) {
            continue;
        }

        pba_massgis_check_one_household($row);
    }

    return true;
}

/**
 * Manual "Check now" handler.
 */
function pba_massgis_handle_check_household_now() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_die('You do not have permission to check this household.', 403);
    }

    $household_id = isset($_POST['household_id']) ? (int) $_POST['household_id'] : 0;
    if ($household_id < 1) {
        wp_die('Missing household ID.', 400);
    }

    check_admin_referer('pba_massgis_check_household_now_' . $household_id, 'pba_massgis_nonce');

    $current_household_id = (int) pba_get_current_household_id();
    $is_admin = function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin');

    if (!$is_admin && $household_id !== $current_household_id) {
        wp_die('You can only check your own household.', 403);
    }

    $row = pba_massgis_get_household_row($household_id);
    if (is_wp_error($row) || empty($row)) {
        wp_safe_redirect(add_query_arg('pba_household_status', 'massgis_lookup_failed', home_url('/household/')));
        exit;
    }

    $result = pba_massgis_check_one_household($row);
    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg('pba_household_status', 'massgis_lookup_failed', home_url('/household/')));
        exit;
    }

    wp_safe_redirect(add_query_arg('pba_household_status', 'massgis_checked', home_url('/household/')));
    exit;
}
function pba_massgis_handle_check_household_ajax() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to check this household.',
        ), 403);
    }

    $household_id = isset($_POST['household_id']) ? (int) $_POST['household_id'] : 0;
    if ($household_id < 1) {
        wp_send_json_error(array(
            'message' => 'Missing household ID.',
        ), 400);
    }

    $nonce = isset($_POST['pba_massgis_nonce']) ? sanitize_text_field(wp_unslash($_POST['pba_massgis_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'pba_massgis_check_household_now_' . $household_id)) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh and try again.',
        ), 403);
    }

    $current_household_id = (int) pba_get_current_household_id();
    $is_admin = function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin');

    if (!$is_admin && $household_id !== $current_household_id) {
        wp_send_json_error(array(
            'message' => 'You can only check your own household.',
        ), 403);
    }

    $row = pba_massgis_get_household_row($household_id);
    if (is_wp_error($row) || empty($row)) {
        wp_send_json_error(array(
            'message' => 'Household could not be loaded.',
        ), 404);
    }

    $result = pba_massgis_check_one_household($row);
    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
        ), 500);
    }

    /*
    * Do not immediately re-fetch from Supabase here.
    * The write may succeed while the immediate read still returns stale values.
    * Instead, merge the exact payload we just wrote into the original row.
    */
    $updated_row = $row;

    if (is_array($result)) {
        $updated_row = array_merge($updated_row, $result);
    }
    if (is_wp_error($updated_row) || empty($updated_row)) {
        wp_send_json_error(array(
            'message' => 'MassGIS check completed, but the updated household could not be loaded.',
        ), 500);
    }

    $context = isset($_POST['pba_massgis_context']) ? sanitize_text_field(wp_unslash($_POST['pba_massgis_context'])) : 'admin';

    if ($context === 'household') {
        if ($context === 'household') {
            $html = pba_render_household_massgis_status_from_row($updated_row);
        } else {
            $html = function_exists('pba_massgis_render_admin_household_badge')
                ? pba_massgis_render_admin_household_badge($updated_row)
                : '';
        }    
    } 
    else {
        $html = function_exists('pba_massgis_render_admin_household_badge')
            ? pba_massgis_render_admin_household_badge($updated_row)
            : '';
    }

    wp_send_json_success(array(
        'message' => 'Check finished.',        
        'html'    => $html,
        'status'  => (string) ($updated_row['massgis_status'] ?? ''),
    ));
}

function pba_massgis_get_household_row($household_id) {
    $rows = pba_supabase_get('Household', array(
        'select'       => '*',
        'household_id' => 'eq.' . (int) $household_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (!is_array($rows) || empty($rows[0])) {
        return new WP_Error('pba_massgis_household_not_found', 'Household not found.');
    }

    return $rows[0];
}

/**
 * Checks a single Household row against MassGIS.
 */
function pba_massgis_check_one_household($household) {
    if (!is_array($household)) {
        return new WP_Error('pba_massgis_invalid_household', 'Invalid household row.');
    }

    $household_id = isset($household['household_id']) ? (int) $household['household_id'] : 0;
    if ($household_id < 1) {
        return new WP_Error('pba_massgis_missing_household_id', 'Missing household ID.');
    }

    $parcel = pba_massgis_lookup_household_parcel($household);
    $checked_at = gmdate('c');

    if (is_wp_error($parcel)) {
        $payload = array(
            'massgis_last_checked_at' => $checked_at,
            'massgis_status'          => 'lookup_failed',
            'massgis_warning'         => $parcel->get_error_message(),
        );

        $updated = pba_massgis_update_household($household_id, $payload);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return $payload;
    }
    if (empty($parcel)) {
        $payload = array(
            'massgis_last_checked_at' => $checked_at,
            'massgis_status'          => 'not_found',
            'massgis_warning'         => 'No MassGIS parcel matched this household address or parcel ID.',
        );

        $updated = pba_massgis_update_household($household_id, $payload);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return $payload;
    }
    $comparison = pba_massgis_compare_household_to_parcel($household, $parcel);
    $status = empty($comparison['warnings']) ? 'current' : 'needs_review';
    $warning = empty($comparison['warnings']) ? '' : implode(' ', $comparison['warnings']);

    $snapshot = array(
        'MAP_PAR_ID' => $parcel['MAP_PAR_ID'] ?? '',
        'LOC_ID'     => $parcel['LOC_ID'] ?? '',
        'SITE_ADDR'  => $parcel['SITE_ADDR'] ?? '',
        'CITY'       => $parcel['CITY'] ?? '',
        'ZIP'        => $parcel['ZIP'] ?? '',
        'OWNER1'     => $parcel['OWNER1'] ?? '',
        'FY'         => $parcel['FY'] ?? '',
        'LAST_EDIT'  => $parcel['LAST_EDIT'] ?? '',
    );

    $payload = array(
        'massgis_last_checked_at'    => $checked_at,
        'massgis_data_last_edit_at'  => pba_massgis_format_last_edit($parcel['LAST_EDIT'] ?? ''),
        'massgis_status'             => $status,
        'massgis_warning'            => $warning,
        'massgis_snapshot_hash'      => md5(wp_json_encode($snapshot)),
        'massgis_loc_id'             => pba_massgis_string($parcel['LOC_ID'] ?? ($household['massgis_loc_id'] ?? '')),
        'massgis_map_par_id'         => pba_massgis_string($parcel['MAP_PAR_ID'] ?? ($household['massgis_map_par_id'] ?? '')),
        'massgis_latest_site_addr'   => pba_massgis_string($parcel['SITE_ADDR'] ?? ''),
        'massgis_latest_owner1'      => pba_massgis_string($parcel['OWNER1'] ?? ''),
        'massgis_latest_city'        => pba_massgis_string($parcel['CITY'] ?? ''),
        'massgis_latest_zip'         => pba_massgis_string($parcel['ZIP'] ?? ''),
        'massgis_latest_fy'          => isset($parcel['FY']) && $parcel['FY'] !== '' ? (int) $parcel['FY'] : null,
    );

    $updated = pba_massgis_update_household($household_id, $payload);

    if (is_wp_error($updated)) {
        return $updated;
    }

    return $payload;
}

/**
 * Parcel lookup priority:
 * 1. LOC_ID
 * 2. MAP_PAR_ID
 * 3. SITE_ADDR + CITY
 */
function pba_massgis_lookup_household_parcel($household) {
    $loc_id = pba_massgis_string($household['massgis_loc_id'] ?? '');
    if ($loc_id !== '') {
        return pba_massgis_query_first("LOC_ID = '" . pba_massgis_arcgis_sql_string($loc_id) . "'");
    }

    $map_par_id = pba_massgis_string($household['massgis_map_par_id'] ?? '');
    if ($map_par_id !== '') {
        return pba_massgis_query_first("MAP_PAR_ID = '" . pba_massgis_arcgis_sql_string($map_par_id) . "'");
    }

    $street_number = pba_massgis_string($household['pb_street_number'] ?? '');
    $street_name = pba_massgis_string($household['pb_street_name'] ?? '');

    if ($street_number === '' && $street_name === '') {
        return new WP_Error('pba_massgis_missing_lookup_key', 'No MassGIS parcel ID or household address is available.');
    }

    $site_addr = trim($street_number . ' ' . $street_name);
    $normalized_like = strtoupper($site_addr);

    $where = "UPPER(SITE_ADDR) LIKE '" . pba_massgis_arcgis_sql_string($normalized_like) . "%'";
    if (PBA_MASSGIS_LOOKUP_CITY !== '') {
        $where .= " AND UPPER(CITY) = '" . pba_massgis_arcgis_sql_string(strtoupper(PBA_MASSGIS_LOOKUP_CITY)) . "'";
    }

    return pba_massgis_query_first($where);
}

function pba_massgis_query_first($where) {
    $query = array(
        'where'             => $where,
        'outFields'         => 'MAP_PAR_ID,LOC_ID,LAST_EDIT,SITE_ADDR,ADDR_NUM,FULL_STR,LOCATION,CITY,ZIP,OWNER1,OWN_ADDR,OWN_CITY,OWN_STATE,OWN_ZIP,FY',
        'returnGeometry'    => 'false',
        'f'                 => 'json',
        'resultRecordCount' => 1,
    );

    $url = add_query_arg($query, PBA_MASSGIS_PARCEL_QUERY_URL);

    $response = wp_remote_get($url, array(
        'timeout' => 20,
        'headers' => array(
            'Accept' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status < 200 || $status >= 300 || !is_array($data)) {
        return new WP_Error('pba_massgis_bad_response', 'MassGIS lookup failed.');
    }

    if (!empty($data['error']['message'])) {
        return new WP_Error('pba_massgis_api_error', 'MassGIS lookup failed: ' . $data['error']['message']);
    }

    if (empty($data['features'][0]['attributes']) || !is_array($data['features'][0]['attributes'])) {
        return array();
    }

    return $data['features'][0]['attributes'];
}

function pba_massgis_compare_household_to_parcel($household, $parcel) {
    $warnings = array();

    $local_address = pba_massgis_normalize_compare(trim(
        pba_massgis_string($household['pb_street_number'] ?? '') . ' ' .
        pba_massgis_string($household['pb_street_name'] ?? '')
    ));
    $massgis_address = pba_massgis_normalize_compare($parcel['SITE_ADDR'] ?? '');

    if ($local_address !== '' && $massgis_address !== '' && $local_address !== $massgis_address) {
        $warnings[] = 'MassGIS site address differs: ' . pba_massgis_string($parcel['SITE_ADDR'] ?? '') . '.';
    }
    $local_owner = pba_massgis_normalize_owner_compare($household['owner_name_raw'] ?? '');
    $massgis_owner = pba_massgis_normalize_owner_compare($parcel['OWNER1'] ?? '');

    if ($local_owner !== '' && $massgis_owner !== '' && $local_owner !== $massgis_owner) {
        $warnings[] = 'MassGIS owner differs: ' . pba_massgis_string($parcel['OWNER1'] ?? '') . '.';
    }
    $old_hash = pba_massgis_string($household['massgis_snapshot_hash'] ?? '');
    if ($old_hash !== '') {
        $snapshot = array(
            'MAP_PAR_ID' => $parcel['MAP_PAR_ID'] ?? '',
            'LOC_ID'     => $parcel['LOC_ID'] ?? '',
            'SITE_ADDR'  => $parcel['SITE_ADDR'] ?? '',
            'CITY'       => $parcel['CITY'] ?? '',
            'ZIP'        => $parcel['ZIP'] ?? '',
            'OWNER1'     => $parcel['OWNER1'] ?? '',
            'FY'         => $parcel['FY'] ?? '',
            'LAST_EDIT'  => $parcel['LAST_EDIT'] ?? '',
        );

        $new_hash = md5(wp_json_encode($snapshot));
        if ($new_hash !== $old_hash) {
            $warnings[] = 'MassGIS parcel details changed since the last accepted snapshot.';
        }
    }

    return array('warnings' => $warnings);
}

function pba_massgis_update_household($household_id, $payload) {
    if (function_exists('pba_supabase_update')) {
        return pba_supabase_update('Household', $payload, array(
            'household_id' => 'eq.' . (int) $household_id,
        ));
    }

    return pba_massgis_supabase_patch('Household', $payload, array(
        'household_id' => 'eq.' . (int) $household_id,
    ));
}

/**
 * Fallback PATCH helper in case the project does not already have pba_supabase_update().
 */
function pba_massgis_supabase_patch($table, $payload, $query_args) {
    if (!defined('SUPABASE_URL')) {
        return new WP_Error('pba_massgis_missing_supabase_url', 'SUPABASE_URL is not defined.');
    }

    $key = '';
    if (defined('SUPABASE_SERVICE_ROLE_KEY')) {
        $key = SUPABASE_SERVICE_ROLE_KEY;
    } elseif (defined('SUPABASE_KEY')) {
        $key = SUPABASE_KEY;
    } elseif (defined('SUPABASE_ANON_KEY')) {
        $key = SUPABASE_ANON_KEY;
    }

    if ($key === '') {
        return new WP_Error('pba_massgis_missing_supabase_key', 'No Supabase API key constant is defined.');
    }

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);
    $url = add_query_arg($query_args, $url);

    $response = wp_remote_request($url, array(
        'method'  => 'PATCH',
        'timeout' => 20,
        'headers' => array(
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=minimal',
        ),
        'body' => wp_json_encode($payload),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        return new WP_Error('pba_massgis_supabase_patch_failed', 'Supabase PATCH failed.', array(
            'status' => $status,
            'body'   => wp_remote_retrieve_body($response),
            'table'  => $table,
        ));
    }

    return true;
}
function pba_render_household_massgis_status_from_row($row) {
    if (!is_array($row)) {
        return '';
    }

    $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
    if ($household_id < 1) {
        return '';
    }

    $status = pba_massgis_string($row['massgis_status'] ?? 'not_checked');
    $warning = pba_massgis_string($row['massgis_warning'] ?? '');
    $checked_at = pba_massgis_string($row['massgis_last_checked_at'] ?? '');
    $site_addr = pba_massgis_string($row['massgis_latest_site_addr'] ?? '');
    $owner = pba_massgis_string($row['massgis_latest_owner1'] ?? '');

    $label = 'MassGIS: Not checked';
    $class = 'pba-massgis-status pba-massgis-status-neutral';

    if ($status === 'current') {
        $label = 'MassGIS: Current';
        $class = 'pba-massgis-status pba-massgis-status-ok';
    } elseif ($status === 'needs_review') {
        $label = 'MassGIS: Needs review';
        $class = 'pba-massgis-status pba-massgis-status-warning';
    } elseif ($status === 'lookup_failed') {
        $label = 'MassGIS: Lookup failed';
        $class = 'pba-massgis-status pba-massgis-status-error';
    } elseif ($status === 'not_found') {
        $label = 'MassGIS: No match found';
        $class = 'pba-massgis-status pba-massgis-status-warning';
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <strong><?php echo esc_html($label); ?></strong>

        <?php if ($warning !== '') : ?>
            <div><?php echo esc_html($warning); ?></div>
        <?php endif; ?>

        <?php if ($site_addr !== '' || $owner !== '') : ?>
            <div class="pba-massgis-meta">
                <?php if ($site_addr !== '') : ?>
                    Latest site address: <?php echo esc_html($site_addr); ?>.
                <?php endif; ?>
                <?php if ($owner !== '') : ?>
                    Latest owner: <?php echo esc_html($owner); ?>.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="pba-massgis-meta">
            <?php if ($checked_at !== '') : ?>
                Last checked: <?php echo esc_html(pba_format_datetime_display($checked_at)); ?>.
            <?php else : ?>
                This household has not been checked against MassGIS yet.
            <?php endif; ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-massgis-form">
            <?php wp_nonce_field('pba_massgis_check_household_now_' . $household_id, 'pba_massgis_nonce'); ?>
            <input type="hidden" name="action" value="pba_massgis_check_household_now">
            <input type="hidden" name="household_id" value="<?php echo esc_attr($household_id); ?>">
            <input type="hidden" name="pba_massgis_context" value="household">
            <button type="submit" class="pba-household-btn secondary" data-processing-text="Checking...">Check MassGIS now</button>
        </form>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Render this near the top of My Household.
 */
function pba_render_household_massgis_status($household_id) {
    $household_id = (int) $household_id;
    if ($household_id < 1 || !function_exists('pba_supabase_get')) {
        return '';
    }

    $row = pba_massgis_get_household_row($household_id);
    if (is_wp_error($row) || empty($row)) {
        return '';
    }

    return pba_render_household_massgis_status_from_row($row);
}
function pba_massgis_normalize_owner_compare($value) {
    $value = strtoupper(pba_massgis_string($value));

    if ($value === '') {
        return '';
    }

    $value = str_replace('&', ' AND ', $value);

    $value = preg_replace('/[.,]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    /*
     * Normalize common ownership suffixes/titles enough to reduce simple
     * punctuation/abbreviation false positives, but do not remove them entirely.
     */
    $value = preg_replace('/\bTRUSTEE\b/', 'TR', $value);
    $value = preg_replace('/\bTRUSTEES\b/', 'TR', $value);
    $value = preg_replace('/\bTRUST\b/', 'TR', $value);
    $value = preg_replace('/\bREVOCABLE\b/', 'REV', $value);
    $value = preg_replace('/\bLIVING\b/', 'LIV', $value);

    return trim($value);
}

function pba_massgis_normalize_compare($value) {
    $value = strtoupper(pba_massgis_string($value));
    $value = preg_replace('/\bROAD\b/', 'RD', $value);
    $value = preg_replace('/\bSTREET\b/', 'ST', $value);
    $value = preg_replace('/\bAVENUE\b/', 'AVE', $value);
    $value = preg_replace('/\bDRIVE\b/', 'DR', $value);
    $value = preg_replace('/\bLANE\b/', 'LN', $value);
    $value = preg_replace('/\bCOURT\b/', 'CT', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function pba_massgis_arcgis_sql_string($value) {
    return str_replace("'", "''", pba_massgis_string($value));
}

function pba_massgis_string($value) {
    if ($value === null) {
        return '';
    }

    return trim((string) $value);
}

function pba_massgis_format_last_edit($value) {
    $value = pba_massgis_string($value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{8}$/', $value)) {
        return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    return $value;
}
function pba_massgis_render_admin_household_badge($household) {
    if (!is_array($household)) {
        return '';
    }

    $household_id = isset($household['household_id']) ? (int) $household['household_id'] : 0;
    $status = pba_massgis_string($household['massgis_status'] ?? 'not_checked');
    $warning = pba_massgis_string($household['massgis_warning'] ?? '');
    $checked_at = pba_massgis_string($household['massgis_last_checked_at'] ?? '');
    $site_addr = pba_massgis_string($household['massgis_latest_site_addr'] ?? '');
    $owner = pba_massgis_string($household['massgis_latest_owner1'] ?? '');

    $label = 'Not checked';
    $class = 'neutral';

    if ($status === 'current') {
        $label = 'Current';
        $class = 'ok';
    } elseif ($status === 'needs_review') {
        $label = 'Needs review';
        $class = 'warning';
    } elseif ($status === 'lookup_failed') {
        $label = 'Lookup failed';
        $class = 'error';
    } elseif ($status === 'not_found') {
        $label = 'No MassGIS match';
        $class = 'warning';
    } elseif ($status === 'disabled') {
        $label = 'Check disabled';
        $class = 'neutral';
    }

    ob_start();
    ?>
    <div class="pba-massgis-admin-cell">
        <style>
            .pba-massgis-admin-cell {
                display: flex;
                flex-direction: column;
                gap: 5px;
                min-width: 150px;
            }
            .pba-massgis-admin-badge {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                padding: 3px 8px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                line-height: 1.2;
                border: 1px solid #d7d7d7;
                background: #f5f5f5;
                color: #333;
            }
            .pba-massgis-admin-badge.ok {
                background: #eef6ee;
                border-color: #b7d7b7;
                color: #245c24;
            }
            .pba-massgis-admin-badge.warning {
                background: #fff8e5;
                border-color: #e5ca76;
                color: #765500;
            }
            .pba-massgis-admin-badge.error {
                background: #f8e9e9;
                border-color: #e2a3a3;
                color: #8a1f1f;
            }
            .pba-massgis-admin-meta {
                font-size: 12px;
                color: #555;
                line-height: 1.35;
            }
            .pba-massgis-admin-warning {
                font-size: 12px;
                color: #765500;
                line-height: 1.35;
            }
            .pba-massgis-admin-actions {
                margin-top: 3px;
            }
            .pba-massgis-admin-actions .pba-btn,
            .pba-massgis-admin-actions .pba-action-btn,
            .pba-massgis-admin-actions button {
                font-size: 12px;
                padding: 5px 8px;
                line-height: 1.2;
            }
        </style>

        <span class="pba-massgis-admin-badge <?php echo esc_attr($class); ?>">
            <?php echo esc_html($label); ?>
        </span>

        <?php if ($checked_at !== '') : ?>
            <div class="pba-massgis-admin-meta">
                Checked: <?php echo esc_html(pba_format_datetime_display($checked_at)); ?>
            </div>
        <?php endif; ?>

        <?php if ($warning !== '') : ?>
            <div class="pba-massgis-admin-warning">
                <?php echo esc_html($warning); ?>
            </div>
        <?php endif; ?>

        <?php if ($site_addr !== '' || $owner !== '') : ?>
            <div class="pba-massgis-admin-meta">
                <?php if ($site_addr !== '') : ?>
                    MassGIS: <?php echo esc_html($site_addr); ?>
                <?php endif; ?>

                <?php if ($owner !== '') : ?>
                    <br>Owner: <?php echo esc_html($owner); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($household_id > 0) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-massgis-admin-actions">
                <?php wp_nonce_field('pba_massgis_check_household_now_' . $household_id, 'pba_massgis_nonce'); ?>
                <input type="hidden" name="action" value="pba_massgis_check_household_now">
                <input type="hidden" name="household_id" value="<?php echo esc_attr($household_id); ?>">
                <input type="hidden" name="pba_massgis_context" value="admin">
                <button type="submit" class="pba-btn secondary" data-processing-text="Checking...">Check</button>
            </form>        
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_massgis_render_ajax_script() {
    if (!is_user_logged_in() || !pba_current_user_has_house_admin_access()) {
        return;
    }
    ?>
    <script>
    (function () {
        const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        function setDetailsVisible(container, visible) {
            if (!container) {
                return;
            }

            container.querySelectorAll(
                '.pba-massgis-admin-meta, .pba-massgis-admin-warning'
            ).forEach(function (el) {
                el.style.display = visible ? '' : 'none';
            });
        }
        function findContainer(form) {
            return form.closest('.pba-massgis-admin-cell') || form.closest('.pba-massgis-status');
        }

        function setMessage(container, message, type) {
            if (!container) {
                return;
            }

            let messageEl = container.querySelector('.pba-massgis-ajax-message');

            if (!messageEl) {
                messageEl = document.createElement('div');
                messageEl.className = 'pba-massgis-ajax-message';
                container.appendChild(messageEl);
            }

            messageEl.textContent = message || '';
            messageEl.dataset.type = type || '';
        }

        function clearMessage(container) {
            if (!container) {
                return;
            }

            const messageEl = container.querySelector('.pba-massgis-ajax-message');
            if (messageEl) {
                messageEl.remove();
            }
        }

        function getButtonReadyText(button) {
            if (!button) {
                return 'Check';
            }

            if (button.dataset.readyText) {
                return button.dataset.readyText;
            }

            const text = (button.textContent || '').trim();

            if (text && text !== 'Checking...' && text !== 'Checked') {
                button.dataset.readyText = text;
                return text;
            }

            button.dataset.readyText = 'Check';
            return 'Check';
        }
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('.pba-massgis-batch-form');

            if (!form) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const button = form.querySelector('button[type="submit"]');
            const messageEl = form.querySelector('.pba-massgis-batch-message');
            const scheduleTextEl = document.querySelector('.pba-massgis-schedule-text');

            if (!button) {
                return;
            }

            const readyText = button.dataset.readyText || 'Run next batch now';
            const controller = new AbortController();

            button.disabled = true;
            button.textContent = 'Running...';

            if (messageEl) {
                messageEl.textContent = 'Checking next MassGIS batch...';
                messageEl.dataset.type = 'info';
            }

            let cancelButton = form.querySelector('.pba-massgis-batch-cancel-btn');
            if (!cancelButton) {
                cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = button.className + ' pba-massgis-batch-cancel-btn';
                cancelButton.textContent = 'Cancel';
                button.insertAdjacentElement('afterend', cancelButton);
            }

            cancelButton.disabled = false;

            cancelButton.addEventListener('click', function () {
                controller.abort();

                button.disabled = false;
                button.textContent = readyText;
                cancelButton.remove();

                if (messageEl) {
                    messageEl.textContent = 'Batch check canceled.';
                    messageEl.dataset.type = 'canceled';
                }
            }, { once: true });

            const formData = new FormData(form);
            formData.set('action', 'pba_massgis_run_household_batch_ajax');

            fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                signal: controller.signal
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    const message = payload && payload.data && payload.data.message
                        ? payload.data.message
                        : 'MassGIS batch check failed.';

                    throw new Error(message);
                }

                button.disabled = false;
                button.textContent = readyText;

                if (cancelButton) {
                    cancelButton.remove();
                }

                if (messageEl) {
                    messageEl.textContent = payload.data && payload.data.message
                        ? payload.data.message
                        : 'MassGIS batch check completed.';
                    messageEl.dataset.type = 'success';
                }

                if (scheduleTextEl && payload.data && payload.data.scheduleText) {
                    scheduleTextEl.textContent = payload.data.scheduleText;
                }
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                button.disabled = false;
                button.textContent = readyText;

                if (cancelButton) {
                    cancelButton.remove();
                }

                if (messageEl) {
                    messageEl.textContent = error.message || 'MassGIS batch check failed.';
                    messageEl.dataset.type = 'error';
                }
            });
        }, true);
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('.pba-massgis-form, .pba-massgis-admin-actions');

            if (!form) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const container = findContainer(form);
            const submitButton = form.querySelector('button[type="submit"]');

            if (!container || !submitButton) {
                return;
            }

            const readyText = getButtonReadyText(submitButton);
            const controller = new AbortController();

            clearMessage(container);
            setDetailsVisible(container, false);
            submitButton.disabled = true;
            submitButton.textContent = 'Checking...';

            form.classList.add('is-checking');

            let cancelButton = form.querySelector('.pba-massgis-cancel-btn');
            if (!cancelButton) {
                cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = submitButton.className + ' pba-massgis-cancel-btn';
                cancelButton.textContent = 'Cancel';
                submitButton.insertAdjacentElement('afterend', cancelButton);
            }

            cancelButton.disabled = false;

            cancelButton.addEventListener('click', function () {
                controller.abort();

                submitButton.disabled = false;
                submitButton.textContent = readyText;
                form.classList.remove('is-checking');

                cancelButton.remove();

                clearMessage(container);
                setDetailsVisible(container, true);
                setMessage(container, 'Check canceled.', 'canceled');
            }, { once: true });

            const formData = new FormData(form);
            formData.set('action', 'pba_massgis_check_household_ajax');

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                signal: controller.signal
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    const message = payload && payload.data && payload.data.message
                        ? payload.data.message
                        : 'MassGIS check failed.';

                    throw new Error(message);
                }

                /*
                 * Replace the MassGIS cell/status box with backend-rendered HTML.
                 * The replacement naturally includes the normal Check button again.
                 * No "Checked" button state is shown.
                 */
                if (payload.data && payload.data.html) {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = payload.data.html.trim();

                    const replacement = wrapper.firstElementChild;
                    if (replacement) {
                        container.replaceWith(replacement);
                        return;
                    }
                }

                /*
                 * Fallback if no replacement HTML came back.
                 */
                submitButton.disabled = false;
                submitButton.textContent = readyText;
                form.classList.remove('is-checking');

                if (cancelButton) {
                    cancelButton.remove();
                }

                clearMessage(container);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                submitButton.disabled = false;
                submitButton.textContent = readyText;
                form.classList.remove('is-checking');

                if (cancelButton) {
                    cancelButton.remove();
                }

                clearMessage(container);
                setDetailsVisible(container, true);
                setMessage(container, error.message || 'MassGIS check failed.', 'error');
            });
        }, true);
    })();
    </script>
    <?php
}
function pba_massgis_handle_resolve_household() {
    if (!is_user_logged_in() || (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles'))) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', home_url('/households/')));
        exit;
    }

    $household_id = isset($_POST['household_id']) ? absint($_POST['household_id']) : 0;
    $resolution = isset($_POST['massgis_resolution']) ? sanitize_text_field(wp_unslash($_POST['massgis_resolution'])) : '';

    if ($household_id < 1) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', home_url('/households/')));
        exit;
    }

    check_admin_referer('pba_massgis_resolve_household_' . $household_id, 'pba_massgis_resolution_nonce');

    $household = pba_massgis_get_household_row($household_id);
    if (is_wp_error($household) || empty($household)) {
        wp_safe_redirect(add_query_arg(array(
            'household_view' => 'edit',
            'household_id' => $household_id,
            'pba_households_status' => 'massgis_resolution_failed',
        ), home_url('/households/')));
        exit;
    }

    $payload = array();

    if ($resolution === 'accept_snapshot') {
        /*
         * Keep the PBA household record as-is.
         * Accept the latest MassGIS snapshot as reviewed/current.
         * PBAMembers are not changed.
         */
        $payload = array(
            'massgis_status' => 'current',
            'massgis_warning' => '',
            'massgis_snapshot_hash' => pba_massgis_hash_latest_household_snapshot($household),
            'massgis_last_checked_at' => !empty($household['massgis_last_checked_at'])
                ? $household['massgis_last_checked_at']
                : gmdate('c'),
            'last_modified_at' => gmdate('c'),
        );
    } elseif ($resolution === 'restrict_invites') {
        /*
         * Apply a safety option without touching existing members.
         * Existing PBAMembers stay attached.
         * New household invites become admin-controlled.
         */
        $payload = array(
            'invite_policy' => 'Restricted',
            'notes' => pba_massgis_append_household_note(
                (string) ($household['notes'] ?? ''),
                'MassGIS review: invitations restricted pending household/member review.'
            ),
            'last_modified_at' => gmdate('c'),
        );
    } elseif ($resolution === 'disable_checks') {
        /*
         * For known MassGIS edge cases.
         * PBAMembers are not changed.
         */
        $payload = array(
            'massgis_check_enabled' => false,
            'massgis_status' => 'disabled',
            'massgis_warning' => 'MassGIS checks disabled for this household by admin review.',
            'last_modified_at' => gmdate('c'),
        );
    } else {
        wp_safe_redirect(add_query_arg(array(
            'household_view' => 'edit',
            'household_id' => $household_id,
            'pba_households_status' => 'invalid_request',
        ), home_url('/households/')));
        exit;
    }

    $result = pba_massgis_update_household($household_id, $payload);

    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg(array(
            'household_view' => 'edit',
            'household_id' => $household_id,
            'pba_households_status' => 'massgis_resolution_failed',
        ), home_url('/households/')));
        exit;
    }

    wp_safe_redirect(add_query_arg(array(
        'household_view' => 'edit',
        'household_id' => $household_id,
        'pba_households_status' => 'massgis_resolution_saved',
    ), home_url('/households/')));
    exit;
}

function pba_massgis_hash_latest_household_snapshot($household) {
    $snapshot = array(
        'MAP_PAR_ID' => $household['massgis_map_par_id'] ?? '',
        'LOC_ID' => $household['massgis_loc_id'] ?? '',
        'SITE_ADDR' => $household['massgis_latest_site_addr'] ?? '',
        'CITY' => $household['massgis_latest_city'] ?? '',
        'ZIP' => $household['massgis_latest_zip'] ?? '',
        'OWNER1' => $household['massgis_latest_owner1'] ?? '',
        'FY' => $household['massgis_latest_fy'] ?? '',
        'LAST_EDIT' => $household['massgis_data_last_edit_at'] ?? '',
    );

    return md5(wp_json_encode($snapshot));
}

function pba_massgis_append_household_note($existing_notes, $new_note) {
    $existing_notes = trim((string) $existing_notes);
    $new_note = trim((string) $new_note);

    if ($new_note === '') {
        return $existing_notes;
    }

    $dated_note = '[' . wp_date('m/d/Y g:i A T') . '] ' . $new_note;

    if ($existing_notes === '') {
        return $dated_note;
    }

    return $existing_notes . "\n\n" . $dated_note;
}

function pba_massgis_handle_run_household_batch_now() {
    if (!is_user_logged_in() || (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles'))) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'invalid_request', home_url('/households/')));
        exit;
    }

    check_admin_referer('pba_massgis_run_household_batch_now', 'pba_massgis_batch_nonce');

    $redirect_url = wp_get_referer();
    if (empty($redirect_url)) {
        $redirect_url = home_url('/households/');
    }

    $redirect_url = remove_query_arg('pba_households_status', $redirect_url);

    if (!function_exists('pba_massgis_check_households_batch')) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'massgis_batch_failed', $redirect_url));
        exit;
    }

    $result = pba_massgis_check_households_batch();

    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg('pba_households_status', 'massgis_batch_failed', $redirect_url));
        exit;
    }

    wp_safe_redirect(add_query_arg('pba_households_status', 'massgis_batch_completed', $redirect_url));
    exit;
}
function pba_massgis_get_next_scheduled_check_text() {
    $next = wp_next_scheduled('pba_massgis_household_currency_check');
    $batch_size = defined('PBA_MASSGIS_CHECK_BATCH_SIZE') ? (int) PBA_MASSGIS_CHECK_BATCH_SIZE : 25;

    if (!$next) {
        return sprintf(
            'MassGIS auto-check: not currently scheduled. Batch size: %d households.',
            $batch_size
        );
    }

    return sprintf(
        'MassGIS auto-check: next run %s. Batch size: %d households.',
        wp_date('m/d/Y g:i A T', $next),
        $batch_size
    );
}
function pba_massgis_render_households_admin_schedule_controls() {
    if (!is_user_logged_in() || (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles'))) {
        return '';
    }

    $schedule_text = function_exists('pba_massgis_get_next_scheduled_check_text')
        ? pba_massgis_get_next_scheduled_check_text()
        : 'MassGIS auto-check schedule unavailable.';

    ob_start();
    ?>
    <div class="pba-massgis-schedule-note">
        <div class="pba-massgis-schedule-text">
            <?php echo esc_html($schedule_text); ?>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-massgis-batch-form">
            <?php wp_nonce_field('pba_massgis_run_household_batch_now', 'pba_massgis_batch_nonce'); ?>
            <input type="hidden" name="action" value="pba_massgis_run_household_batch_ajax">
            <button type="submit" class="pba-admin-list-btn secondary" data-ready-text="Run next batch now">
                Run next batch now
            </button>
            <span class="pba-massgis-batch-message" aria-live="polite"></span>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
function pba_massgis_handle_run_household_batch_ajax() {
    if (!is_user_logged_in() || (!current_user_can('pba_manage_all_households') && !current_user_can('pba_manage_roles'))) {
        wp_send_json_error(array(
            'message' => 'You do not have permission to run the MassGIS batch check.',
        ), 403);
    }

    $nonce = isset($_POST['pba_massgis_batch_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['pba_massgis_batch_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'pba_massgis_run_household_batch_now')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh and try again.',
        ), 403);
    }

    if (!function_exists('pba_massgis_check_households_batch')) {
        wp_send_json_error(array(
            'message' => 'MassGIS batch checker is not available.',
        ), 500);
    }

    $result = pba_massgis_check_households_batch();

    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message(),
        ), 500);
    }

    $schedule_text = function_exists('pba_massgis_get_next_scheduled_check_text')
        ? pba_massgis_get_next_scheduled_check_text()
        : 'MassGIS auto-check schedule unavailable.';

    wp_send_json_success(array(
        'message' => 'MassGIS batch check completed.',
        'scheduleText' => $schedule_text,
    ));
}

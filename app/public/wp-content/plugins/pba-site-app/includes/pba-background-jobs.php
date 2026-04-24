<?php
/**
 * PBA Background Jobs
 *
 * Registers lightweight background maintenance tasks for the PBA site.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register background jobs.
 */
add_action('init', 'pba_register_background_jobs');

function pba_register_background_jobs() {
    if (!wp_next_scheduled('pba_hourly_background_maintenance')) {
        wp_schedule_event(time() + 300, 'hourly', 'pba_hourly_background_maintenance');
    }
}

/**
 * Run hourly background maintenance.
 */
add_action('pba_hourly_background_maintenance', 'pba_run_hourly_background_maintenance');

function pba_run_hourly_background_maintenance() {
    pba_background_expire_pending_invites();
}

/**
 * Expire pending household invitations in the background.
 *
 * This assumes pending invitations are stored as Person rows with:
 * - status = Pending
 * - invitation_expires_at or invite_expires_at if available
 *
 * If your actual expiration column name differs, update the column names below.
 */
function pba_background_expire_pending_invites() {
    if (!function_exists('pba_supabase_get') || !function_exists('pba_supabase_patch')) {
        error_log('[PBA Background Jobs] Supabase helpers are not available.');
        return;
    }

    $now_utc = gmdate('c');

    /*
     * Try the most likely expiration column names.
     * This avoids breaking if only one exists.
     */
    $expiration_columns = array(
        'invitation_expires_at',
        'invite_expires_at',
        'expires_at',
    );

    foreach ($expiration_columns as $expiration_column) {
        $rows = pba_supabase_get('Person', array(
            'select' => 'person_id,status,' . $expiration_column,
            'status' => 'eq.Pending',
            $expiration_column => 'lt.' . $now_utc,
            'limit' => 500,
        ));

        if (is_wp_error($rows)) {
            continue;
        }

        if (empty($rows) || !is_array($rows)) {
            continue;
        }

        foreach ($rows as $row) {
            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;

            if ($person_id < 1) {
                continue;
            }

            $result = pba_supabase_patch(
                'Person',
                array(
                    'status' => 'eq.Pending',
                    'person_id' => 'eq.' . $person_id,
                ),
                array(
                    'status' => 'Expired',
                    'last_modified_at' => $now_utc,
                )
            );

            if (is_wp_error($result)) {
                error_log('[PBA Background Jobs] Failed to expire pending invite for person_id ' . $person_id . ': ' . $result->get_error_message());
            }
        }

        /*
         * If this column worked, do not keep trying alternate column names.
         */
        break;
    }
}

/**
 * Clear scheduled jobs on plugin deactivation.
 *
 * Call this from the main plugin deactivation hook.
 */
function pba_clear_background_jobs() {
    $timestamp = wp_next_scheduled('pba_hourly_background_maintenance');

    while ($timestamp) {
        wp_unschedule_event($timestamp, 'pba_hourly_background_maintenance');
        $timestamp = wp_next_scheduled('pba_hourly_background_maintenance');
    }
}

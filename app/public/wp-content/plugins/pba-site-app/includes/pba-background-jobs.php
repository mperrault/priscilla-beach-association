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
 * Set this to true while debugging.
 * Set to false later to reduce log noise.
 */
if (!defined('PBA_BACKGROUND_JOBS_DEBUG')) {
    define('PBA_BACKGROUND_JOBS_DEBUG', true);
}

/**
 * Cron hook name.
 */
if (!defined('PBA_BACKGROUND_JOBS_HOOK')) {
    define('PBA_BACKGROUND_JOBS_HOOK', 'pba_hourly_background_maintenance');
}

/**
 * Debug logger.
 */
function pba_background_jobs_log($message) {
    if (!defined('PBA_BACKGROUND_JOBS_DEBUG') || !PBA_BACKGROUND_JOBS_DEBUG) {
        return;
    }

    error_log('[PBA Background Jobs] ' . $message);
}

pba_background_jobs_log('File loaded at ' . wp_date('Y-m-d g:i:s A T'));

/**
 * Register hooks.
 */
add_action('init', 'pba_register_background_jobs');
add_action(PBA_BACKGROUND_JOBS_HOOK, 'pba_run_hourly_background_maintenance');

pba_background_jobs_log('WordPress hooks registered. Cron hook=' . PBA_BACKGROUND_JOBS_HOOK);

/**
 * Register background jobs.
 *
 * Important:
 * This does NOT reschedule on every page load.
 * It schedules only when the event is missing.
 */
function pba_register_background_jobs() {
    $timestamp = wp_next_scheduled(PBA_BACKGROUND_JOBS_HOOK);

    if ($timestamp) {
        pba_background_jobs_log(
            'Event already scheduled. Next run: UTC=' .
            gmdate('Y-m-d H:i:s', $timestamp) .
            ' | Local=' .
            wp_date('Y-m-d g:i:s A T', $timestamp) .
            ' | Now=' .
            wp_date('Y-m-d g:i:s A T') .
            ' | Due=' .
            (($timestamp <= time()) ? 'yes' : 'no')
        );

        return;
    }

    $new_timestamp = time() + 300;
    $scheduled = wp_schedule_event($new_timestamp, 'hourly', PBA_BACKGROUND_JOBS_HOOK);

    if ($scheduled === false) {
        pba_background_jobs_log('ERROR: wp_schedule_event returned false. Event was not scheduled.');
        return;
    }

    pba_background_jobs_log(
        'Event scheduled. First run: UTC=' .
        gmdate('Y-m-d H:i:s', $new_timestamp) .
        ' | Local=' .
        wp_date('Y-m-d g:i:s A T', $new_timestamp)
    );
}

/**
 * Manual diagnostic endpoints.
 *
 * These are admin-only and useful in LocalWP/dev.
 *
 * Test hook registration:
 * /?pba_test_background_hook=1
 *
 * Run job directly:
 * /?pba_run_background_jobs_now=1
 *
 * Reset schedule to 5 minutes from now:
 * /?pba_reset_background_schedule=1
 *
 * Spawn WP-Cron:
 * /?pba_spawn_cron_now=1
 */
add_action('init', 'pba_background_jobs_debug_endpoints');

function pba_background_jobs_debug_endpoints() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['pba_test_background_hook'])) {
        pba_background_jobs_log('Manual hook test requested at ' . wp_date('Y-m-d g:i:s A T'));

        do_action(PBA_BACKGROUND_JOBS_HOOK);

        wp_die('PBA background cron hook manually fired. Check debug.log.');
    }

    if (isset($_GET['pba_run_background_jobs_now'])) {
        pba_background_jobs_log('Manual direct job run requested at ' . wp_date('Y-m-d g:i:s A T'));

        pba_run_hourly_background_maintenance();

        wp_die('PBA background jobs manually ran. Check debug.log.');
    }

    if (isset($_GET['pba_reset_background_schedule'])) {
        pba_background_jobs_clear_scheduled_event();

        $new_timestamp = time() + 300;
        $scheduled = wp_schedule_event($new_timestamp, 'hourly', PBA_BACKGROUND_JOBS_HOOK);

        if ($scheduled === false) {
            pba_background_jobs_log('ERROR: Manual schedule reset failed.');
            wp_die('Schedule reset failed. Check debug.log.');
        }

        pba_background_jobs_log(
            'Manual schedule reset. First run: UTC=' .
            gmdate('Y-m-d H:i:s', $new_timestamp) .
            ' | Local=' .
            wp_date('Y-m-d g:i:s A T', $new_timestamp)
        );

        wp_die('PBA background job schedule reset to 5 minutes from now. Check debug.log.');
    }

    if (isset($_GET['pba_spawn_cron_now'])) {
        pba_background_jobs_log('Manual spawn_cron requested at ' . wp_date('Y-m-d g:i:s A T'));

        spawn_cron();

        wp_die('spawn_cron() called. Check debug.log.');
    }
}

/**
 * Run hourly background maintenance.
 */
function pba_run_hourly_background_maintenance() {
    pba_background_jobs_log('Hourly maintenance START at ' . wp_date('Y-m-d g:i:s A T'));

    pba_background_expire_pending_invites();

    pba_background_jobs_log('Hourly maintenance END at ' . wp_date('Y-m-d g:i:s A T'));
}

/**
 * Expire pending household invitations in the background.
 *
 * This assumes pending invitations are stored as Person rows with:
 * - status = Pending
 * - invitation_expires_at, invite_expires_at, or expires_at
 */
function pba_background_expire_pending_invites() {
    pba_background_jobs_log('Expire pending invites ENTER');

    if (!function_exists('pba_supabase_get')) {
        pba_background_jobs_log('ERROR: pba_supabase_get is not available.');
        return;
    }

    if (!function_exists('pba_supabase_update')) {
        pba_background_jobs_log('ERROR: pba_supabase_update is not available.');
        return;
    }
    $now_utc = gmdate('c');

    pba_background_jobs_log('Expire pending invites using now UTC=' . $now_utc);

    $expiration_columns = array(
        'invitation_expires_at',
        'invite_expires_at',
        'expires_at',
    );

    foreach ($expiration_columns as $expiration_column) {
        pba_background_jobs_log('Checking expiration column: ' . $expiration_column);

        $rows = pba_supabase_get('Person', array(
            'select' => 'person_id,status,' . $expiration_column,
            'status' => 'eq.Pending',
            $expiration_column => 'lt.' . $now_utc,
            'limit' => 500,
        ));

        if (is_wp_error($rows)) {
            pba_background_jobs_log(
                'Column check failed for ' .
                $expiration_column .
                ': ' .
                $rows->get_error_message()
            );

            continue;
        }

        if (empty($rows) || !is_array($rows)) {
            pba_background_jobs_log('No expired pending invites found using column: ' . $expiration_column);
            continue;
        }

        pba_background_jobs_log(
            'Found ' .
            count($rows) .
            ' expired pending invite(s) using column: ' .
            $expiration_column
        );

        $expired_count = 0;
        $failed_count = 0;

        foreach ($rows as $row) {
            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;

            if ($person_id < 1) {
                pba_background_jobs_log('Skipping row with missing/invalid person_id.');
                continue;
            }

            $result = pba_supabase_update(
                'Person',
                array(
                    'status' => 'Expired',
                    'last_modified_at' => $now_utc,
                ),
                array(
                    'status' => 'eq.Pending',
                    'person_id' => 'eq.' . $person_id,
                )
            );

            if (is_wp_error($result)) {
                $failed_count++;

                pba_background_jobs_log(
                    'ERROR: Failed to expire pending invite for person_id ' .
                    $person_id .
                    ': ' .
                    $result->get_error_message()
                );

                continue;
            }

            $expired_count++;

            pba_background_jobs_log('Expired pending invite for person_id ' . $person_id);
        }

        pba_background_jobs_log(
            'Expire pending invites completed using column ' .
            $expiration_column .
            '. Expired=' .
            $expired_count .
            ', Failed=' .
            $failed_count
        );

        /*
         * If this column worked, do not keep trying alternate column names.
         */
        break;
    }

    pba_background_jobs_log('Expire pending invites EXIT');
}

/**
 * Clear scheduled jobs on plugin deactivation.
 *
 * Call this from the main plugin deactivation hook.
 */
function pba_clear_background_jobs() {
    pba_background_jobs_log('Clearing scheduled background jobs.');

    pba_background_jobs_clear_scheduled_event();
}

/**
 * Internal helper to clear the scheduled cron event.
 */
function pba_background_jobs_clear_scheduled_event() {
    $timestamp = wp_next_scheduled(PBA_BACKGROUND_JOBS_HOOK);

    while ($timestamp) {
        wp_unschedule_event($timestamp, PBA_BACKGROUND_JOBS_HOOK);

        pba_background_jobs_log(
            'Unscheduled event at UTC=' .
            gmdate('Y-m-d H:i:s', $timestamp) .
            ' | Local=' .
            wp_date('Y-m-d g:i:s A T', $timestamp)
        );

        $timestamp = wp_next_scheduled(PBA_BACKGROUND_JOBS_HOOK);
    }
}
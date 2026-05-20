<?php
/**
 * PBA Pickleball module.
 *
 * Provides the public pickleball page, limited pickleball administration,
 * guest/participant tracking, announcements, and email-list foundation.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_pickleball_shortcodes');
add_action('admin_post_pba_pickleball_update_status', 'pba_handle_pickleball_update_status');
add_action('admin_post_pba_pickleball_add_guest', 'pba_handle_pickleball_add_guest');
add_action('admin_post_pba_pickleball_update_guest', 'pba_handle_pickleball_update_guest');
add_action('admin_post_pba_pickleball_add_member_participant', 'pba_handle_pickleball_add_member_participant');
add_action('admin_post_pba_pickleball_update_participant', 'pba_handle_pickleball_update_participant');
add_action('admin_post_pba_pickleball_remove_participant', 'pba_handle_pickleball_remove_participant');
add_action('admin_post_pba_pickleball_create_announcement', 'pba_handle_pickleball_create_announcement');
add_action('admin_post_pba_pickleball_delete_announcement', 'pba_handle_pickleball_delete_announcement');

function pba_register_pickleball_shortcodes() {
    add_shortcode('pba_pickleball', 'pba_render_pickleball_shortcode');
    add_shortcode('pba_pickleball_admin', 'pba_render_pickleball_admin_shortcode');
}

function pba_current_user_can_manage_pickleball() {
    return is_user_logged_in() && (
        current_user_can('pba_manage_pickleball')
        || current_user_can('pba_manage_roles')
    );
}

function pba_current_user_can_view_pickleball() {
    return current_user_can('pba_view_pickleball') || pba_current_user_can_manage_pickleball();
}

function pba_pickleball_status_table() {
    return 'PickleballStatus';
}

function pba_pickleball_guest_table() {
    return 'PickleballGuest';
}

function pba_pickleball_participant_table() {
    return 'PickleballParticipant';
}

function pba_pickleball_announcement_table() {
    return 'PickleballAnnouncement';
}

function pba_pickleball_status_options() {
    return array(
        'on_as_scheduled'     => 'On as scheduled',
        'cancelled_weather'   => 'Cancelled due to weather',
        'delayed'             => 'Delayed',
        'courts_unavailable'  => 'Courts unavailable',
        'season_not_started'  => 'Season not started',
        'season_ended'        => 'Season ended',
    );
}

function pba_pickleball_status_label($status_key) {
    $options = pba_pickleball_status_options();
    $status_key = sanitize_key($status_key);

    return isset($options[$status_key]) ? $options[$status_key] : $options['season_not_started'];
}

function pba_pickleball_allowed_participant_statuses() {
    return array('active', 'inactive');
}

function pba_pickleball_all_participant_statuses() {
    return array('active', 'inactive', 'removed');
}

function pba_pickleball_get_current_person_id() {
    if (function_exists('pba_current_person_id')) {
        $person_id = (int) pba_current_person_id();
        return $person_id > 0 ? $person_id : null;
    }

    return null;
}

function pba_pickleball_get_current_actor_email() {
    $user = wp_get_current_user();

    if (!$user || empty($user->user_email)) {
        return '';
    }

    return sanitize_email($user->user_email);
}

function pba_pickleball_get_current_actor_display_name() {
    $user = wp_get_current_user();

    if (!$user) {
        return 'PBA';
    }

    if (!empty($user->display_name)) {
        return (string) $user->display_name;
    }

    if (!empty($user->user_login)) {
        return (string) $user->user_login;
    }

    return 'PBA';
}

function pba_pickleball_enqueue_styles() {
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    $path = dirname(__FILE__) . '/css/pba-pickleball.css';

    wp_enqueue_style(
        'pba-pickleball',
        plugin_dir_url(__FILE__) . 'css/pba-pickleball.css',
        array(),
        file_exists($path) ? (string) filemtime($path) : '1.0.0'
    );
}

function pba_pickleball_audit($action_type, $entity_type, $entity_id = null, $args = array()) {
    if (!function_exists('pba_audit_log')) {
        return false;
    }

    return pba_audit_log($action_type, $entity_type, $entity_id, $args);
}

function pba_pickleball_get_current_status() {
    $fallback = array(
        'status_id' => 0,
        'status_key' => 'season_not_started',
        'status_note' => '',
        'updated_at' => '',
        'updated_by_display_name' => '',
    );

    if (!function_exists('pba_supabase_get')) {
        return $fallback;
    }

    $rows = pba_supabase_get(pba_pickleball_status_table(), array(
        'select' => '*',
        'order'  => 'updated_at.desc,status_id.desc',
        'limit'  => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return $fallback;
    }

    return array_merge($fallback, $rows[0]);
}

function pba_pickleball_get_announcements($limit = 5) {
    $limit = max(1, min(50, absint($limit)));

    if (!function_exists('pba_supabase_get')) {
        return array();
    }

    $rows = pba_supabase_get(pba_pickleball_announcement_table(), array(
        'select' => '*',
        'status' => 'eq.active',
        'order'  => 'pinned.desc,created_at.desc',
        'limit'  => $limit,
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_pickleball_format_date($date_value) {
    $date_value = trim((string) $date_value);

    if ($date_value === '') {
        return '';
    }

    $timestamp = strtotime($date_value);

    if (!$timestamp) {
        return '';
    }

    return date_i18n('M j, Y', $timestamp);
}

function pba_render_pickleball_shortcode() {
    pba_pickleball_enqueue_styles();

    $status = pba_pickleball_get_current_status();
    $announcements = pba_pickleball_get_announcements(8);
    $status_key = isset($status['status_key']) ? sanitize_key($status['status_key']) : 'season_not_started';
    $status_note = isset($status['status_note']) ? trim((string) $status['status_note']) : '';

    ob_start();
    ?>
    <div class="pba-pickleball">
        <section class="pba-pickleball-hero">
            <div>
                <p class="pba-pickleball-kicker">PBA Activities</p>
                <!-- h1>Pickleball</h1 -->
                <p>
                    PBA members and approved guests play pickleball on Tuesdays and Thursdays at 9:00 AM
                    at the Association clubhouse courts during warmer weather.
                </p>
            </div>
        </section>

        <section class="pba-pickleball-status pba-pickleball-status-<?php echo esc_attr($status_key); ?>">
            <div>
                <span class="pba-pickleball-section-label">Current Status</span>
                <h2><?php echo esc_html(pba_pickleball_status_label($status_key)); ?></h2>
                <?php if ($status_note !== '') : ?>
                    <p><?php echo esc_html($status_note); ?></p>
                <?php else : ?>
                    <p>Check this page before heading to the courts for the latest pickleball update.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="pba-pickleball-grid">
            <div class="pba-pickleball-panel">
                <span class="pba-pickleball-section-label">Schedule</span>
                <h2>Tuesdays and Thursdays</h2>
                <p>Play begins at 9:00 AM during warmer weather.</p>
            </div>

            <div class="pba-pickleball-panel">
                <span class="pba-pickleball-section-label">Location</span>
                <h2>Association clubhouse courts</h2>
                <p>Open to PBA members and approved pickleball guests.</p>
            </div>
        </section>

        <section class="pba-pickleball-panel">
            <span class="pba-pickleball-section-label">Guidelines</span>
            <ul class="pba-pickleball-guidelines">
                <li>Rotate play so everyone gets court time.</li>
                <li>Bring water and court-safe shoes.</li>
                <li>Keep play friendly and welcoming for mixed skill levels.</li>
                <li>Check weather and court conditions before arriving.</li>
            </ul>
        </section>

        <section class="pba-pickleball-panel">
            <div class="pba-pickleball-section-header">
                <div>
                    <span class="pba-pickleball-section-label">Latest Updates</span>
                    <h2>Pickleball Announcements</h2>
                </div>
            </div>

            <?php if (empty($announcements)) : ?>
                <p class="pba-pickleball-empty">No pickleball announcements have been posted yet.</p>
            <?php else : ?>
                <div class="pba-pickleball-announcements">
                    <?php foreach ($announcements as $announcement) : ?>
                        <?php
                        $title = isset($announcement['message_title']) ? trim((string) $announcement['message_title']) : '';
                        $body = isset($announcement['message_body']) ? trim((string) $announcement['message_body']) : '';
                        $posted_as = isset($announcement['posted_by_display_name']) ? trim((string) $announcement['posted_by_display_name']) : '';
                        $created = isset($announcement['created_at']) ? pba_pickleball_format_date($announcement['created_at']) : '';
                        ?>
                        <article class="pba-pickleball-announcement">
                            <?php if ($title !== '') : ?>
                                <h3><?php echo esc_html($title); ?></h3>
                            <?php endif; ?>
                            <p><?php echo nl2br(esc_html($body)); ?></p>
                            <footer>
                                <?php if ($posted_as !== '') : ?>
                                    <span><?php echo esc_html($posted_as); ?></span>
                                <?php endif; ?>
                                <?php if ($created !== '') : ?>
                                    <span><?php echo esc_html($created); ?></span>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_pickleball_admin_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_user_can_manage_pickleball()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    pba_pickleball_enqueue_styles();

    $status = pba_pickleball_get_current_status();
    $participants_result = pba_pickleball_get_participant_rows();
    $announcements = pba_pickleball_get_announcements(25);
    $recipients = pba_pickleball_get_email_update_recipients();
    $edit_guest = null;

    if (
        isset($_GET['pickleball_view'], $_GET['guest_id'])
        && sanitize_key(wp_unslash($_GET['pickleball_view'])) === 'edit_guest'
    ) {
        $edit_guest = pba_pickleball_get_guest(absint($_GET['guest_id']));
    }

    ob_start();
    ?>
    <div class="pba-pickleball pba-pickleball-admin">
        <section class="pba-pickleball-hero pba-pickleball-admin-hero">
            <div>
                <p class="pba-pickleball-kicker">Activity Admin</p>
                <!-- h1>Manage Pickleball</h1 -->
                <p>Coordinate current status, approved guests, participants, and pickleball-only announcements.</p>
            </div>
        </section>

        <?php echo pba_render_pickleball_status_message(); ?>

        <section class="pba-pickleball-admin-grid">
            <?php echo pba_render_pickleball_status_admin_panel($status); ?>
            <?php echo pba_render_pickleball_email_list_panel($recipients); ?>
        </section>

        <?php
        if (is_array($edit_guest)) {
            echo pba_render_pickleball_guest_form($edit_guest);
        } else {
            echo pba_render_pickleball_guest_form();
        }
        ?>

        <?php echo pba_render_pickleball_member_participant_form(); ?>

        <?php
        if (is_wp_error($participants_result)) {
            echo '<section class="pba-pickleball-panel"><p class="pba-pickleball-empty">Participant data is not available yet. Apply the pickleball database schema, then reload this page.</p></section>';
        } else {
            echo pba_render_pickleball_participant_table($participants_result);
        }
        ?>

        <?php echo pba_render_pickleball_announcement_admin_panel($announcements); ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_pickleball_status_message() {
    $status = isset($_GET['pba_pickleball_status']) ? sanitize_key(wp_unslash($_GET['pba_pickleball_status'])) : '';

    if ($status === '') {
        return '';
    }

    $success = array(
        'status_saved' => 'Pickleball status updated.',
        'guest_added' => 'Guest added.',
        'guest_readded' => 'Guest re-added to the pickleball list.',
        'guest_updated' => 'Guest updated.',
        'guest_deactivated' => 'Guest deactivated.',
        'participant_added' => 'Participant added.',
        'participant_updated' => 'Participant updated.',
        'participant_removed' => 'Participant removed from the pickleball list.',
        'announcement_posted' => 'Announcement posted.',
        'announcement_deleted' => 'Announcement deleted.',
    );

    $errors = array(
        'invalid_request' => 'We could not process that request.',
        'not_allowed' => 'You do not have permission to manage pickleball.',
        'missing_guest_fields' => 'Please provide the guest first name, last name, and email address.',
        'invalid_email' => 'Please provide a valid email address.',
        'guest_exists' => 'That guest email address is already on the pickleball guest list.',
        'guest_save_failed' => 'The guest could not be saved.',
        'participant_save_failed' => 'The participant could not be saved.',
        'member_not_found' => 'No active member was found with that email address.',
        'status_save_failed' => 'The pickleball status could not be saved.',
        'missing_announcement' => 'Please enter an announcement message.',
        'announcement_save_failed' => 'The announcement could not be saved.',
    );

    if (isset($success[$status])) {
        return '<div class="pba-pickleball-alert pba-pickleball-alert-success"><strong>Success</strong><span>' . esc_html($success[$status]) . '</span></div>';
    }

    $message = isset($errors[$status]) ? $errors[$status] : ucfirst(str_replace('_', ' ', $status));

    return '<div class="pba-pickleball-alert pba-pickleball-alert-error"><strong>Please review</strong><span>' . esc_html($message) . '</span></div>';
}

function pba_render_pickleball_status_admin_panel($status) {
    $status_key = isset($status['status_key']) ? sanitize_key($status['status_key']) : 'season_not_started';
    $status_note = isset($status['status_note']) ? (string) $status['status_note'] : '';

    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <span class="pba-pickleball-section-label">Current Status</span>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-form">
            <?php wp_nonce_field('pba_pickleball_update_status', 'pba_pickleball_nonce'); ?>
            <input type="hidden" name="action" value="pba_pickleball_update_status">

            <label for="pba-pickleball-status-key">Status</label>
            <select id="pba-pickleball-status-key" name="status_key">
                <?php foreach (pba_pickleball_status_options() as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($status_key, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="pba-pickleball-status-note">Note</label>
            <textarea id="pba-pickleball-status-note" name="status_note" rows="4" maxlength="600"><?php echo esc_textarea($status_note); ?></textarea>

            <button type="submit" class="pba-pickleball-button">Save Status</button>
        </form>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_email_list_panel($recipients) {
    $email_values = array();

    foreach ($recipients as $recipient) {
        if (!empty($recipient['email_address'])) {
            $email_values[] = sanitize_email($recipient['email_address']);
        }
    }

    $email_values = array_values(array_unique(array_filter($email_values)));
    $mailto = !empty($email_values) ? 'mailto:?bcc=' . rawurlencode(implode(',', $email_values)) : '';

    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <span class="pba-pickleball-section-label">Email Updates</span>
        <h2><?php echo esc_html(count($email_values)); ?> opted-in recipient<?php echo count($email_values) === 1 ? '' : 's'; ?></h2>
        <?php if (empty($email_values)) : ?>
            <p class="pba-pickleball-empty">No active participants are opted in for pickleball email updates.</p>
        <?php else : ?>
            <textarea readonly rows="6" class="pba-pickleball-email-list"><?php echo esc_textarea(implode(",\n", $email_values)); ?></textarea>
            <a class="pba-pickleball-button pba-pickleball-button-secondary" href="<?php echo esc_url($mailto); ?>">Open Email Draft</a>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_guest_form($guest = null) {
    $is_edit = is_array($guest) && !empty($guest['guest_id']);
    $guest_id = $is_edit ? (int) $guest['guest_id'] : 0;
    $status = $is_edit && !empty($guest['status']) ? strtolower((string) $guest['status']) : 'active';
    $email_updates = !$is_edit || !empty($guest['email_updates_enabled']);

    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <div class="pba-pickleball-section-header">
            <div>
                <span class="pba-pickleball-section-label">Guests</span>
                <h2><?php echo $is_edit ? 'Edit Guest' : 'Add Approved Guest'; ?></h2>
            </div>
            <?php if ($is_edit) : ?>
                <a class="pba-pickleball-link" href="<?php echo esc_url(home_url('/pickleball-admin/')); ?>">Cancel Edit</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-form pba-pickleball-form-grid">
            <?php wp_nonce_field($is_edit ? 'pba_pickleball_update_guest_' . $guest_id : 'pba_pickleball_add_guest', 'pba_pickleball_nonce'); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($is_edit ? 'pba_pickleball_update_guest' : 'pba_pickleball_add_guest'); ?>">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="guest_id" value="<?php echo esc_attr($guest_id); ?>">
            <?php endif; ?>

            <label>
                First name
                <input type="text" name="first_name" maxlength="120" required value="<?php echo esc_attr($is_edit ? ($guest['first_name'] ?? '') : ''); ?>">
            </label>

            <label>
                Last name
                <input type="text" name="last_name" maxlength="120" required value="<?php echo esc_attr($is_edit ? ($guest['last_name'] ?? '') : ''); ?>">
            </label>

            <label>
                Email address
                <input type="email" name="email_address" maxlength="255" required value="<?php echo esc_attr($is_edit ? ($guest['email_address'] ?? '') : ''); ?>">
            </label>

            <label>
                Phone number
                <input type="tel" name="phone_number" maxlength="80" value="<?php echo esc_attr($is_edit ? ($guest['phone_number'] ?? '') : ''); ?>">
            </label>

            <label>
                Status
                <select name="status">
                    <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                    <option value="inactive" <?php selected($status, 'inactive'); ?>>Inactive</option>
                </select>
            </label>

            <label class="pba-pickleball-checkbox">
                <input type="checkbox" name="email_updates_enabled" value="1" <?php checked($email_updates); ?>>
                Email updates
            </label>

            <label class="pba-pickleball-full">
                Notes
                <textarea name="notes" rows="4" maxlength="1200"><?php echo esc_textarea($is_edit ? ($guest['notes'] ?? '') : ''); ?></textarea>
            </label>

            <div class="pba-pickleball-form-actions pba-pickleball-full">
                <button type="submit" class="pba-pickleball-button">
                    <?php echo $is_edit ? 'Save Guest' : 'Add Guest'; ?>
                </button>
            </div>
        </form>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_member_participant_form() {
    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <span class="pba-pickleball-section-label">PBA Members</span>
        <h2>Add Member Participant</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-form pba-pickleball-inline-form">
            <?php wp_nonce_field('pba_pickleball_add_member_participant', 'pba_pickleball_nonce'); ?>
            <input type="hidden" name="action" value="pba_pickleball_add_member_participant">

            <label>
                Member email address
                <input type="email" name="email_address" maxlength="255" required>
            </label>

            <label>
                Status
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label>

            <label class="pba-pickleball-checkbox">
                <input type="checkbox" name="email_updates_enabled" value="1" checked>
                Email updates
            </label>

            <button type="submit" class="pba-pickleball-button">Add Member</button>
        </form>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_announcement_admin_panel($announcements) {
    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <span class="pba-pickleball-section-label">Announcements</span>
        <h2>Post Pickleball Update</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-form">
            <?php wp_nonce_field('pba_pickleball_create_announcement', 'pba_pickleball_nonce'); ?>
            <input type="hidden" name="action" value="pba_pickleball_create_announcement">

            <label>
                Title
                <input type="text" name="message_title" maxlength="255">
            </label>

            <label>
                Message
                <textarea name="message_body" rows="5" maxlength="1800" required></textarea>
            </label>

            <label class="pba-pickleball-checkbox">
                <input type="checkbox" name="pinned" value="1">
                Pin announcement
            </label>

            <button type="submit" class="pba-pickleball-button">Post Announcement</button>
        </form>

        <div class="pba-pickleball-admin-announcements">
            <?php if (empty($announcements)) : ?>
                <p class="pba-pickleball-empty">No active pickleball announcements.</p>
            <?php else : ?>
                <?php foreach ($announcements as $announcement) : ?>
                    <?php
                    $announcement_id = isset($announcement['pickleball_announcement_id']) ? (int) $announcement['pickleball_announcement_id'] : 0;
                    $title = isset($announcement['message_title']) ? trim((string) $announcement['message_title']) : '';
                    $body = isset($announcement['message_body']) ? trim((string) $announcement['message_body']) : '';
                    ?>
                    <article class="pba-pickleball-announcement">
                        <?php if ($title !== '') : ?>
                            <h3><?php echo esc_html($title); ?></h3>
                        <?php endif; ?>
                        <p><?php echo nl2br(esc_html($body)); ?></p>
                        <?php if ($announcement_id > 0) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('pba_pickleball_delete_announcement_' . $announcement_id, 'pba_pickleball_nonce'); ?>
                                <input type="hidden" name="action" value="pba_pickleball_delete_announcement">
                                <input type="hidden" name="announcement_id" value="<?php echo esc_attr($announcement_id); ?>">
                                <button type="submit" class="pba-pickleball-text-button">Delete</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_participant_table($data) {
    $participants = isset($data['participants']) && is_array($data['participants']) ? $data['participants'] : array();
    $people = isset($data['people']) && is_array($data['people']) ? $data['people'] : array();
    $guests = isset($data['guests']) && is_array($data['guests']) ? $data['guests'] : array();

    ob_start();
    ?>
    <section class="pba-pickleball-panel">
        <span class="pba-pickleball-section-label">Participants</span>
        <h2>Pickleball Participant List</h2>

        <?php if (empty($participants)) : ?>
            <p class="pba-pickleball-empty">No pickleball participants have been added yet.</p>
        <?php else : ?>
            <div class="pba-pickleball-table-wrap">
                <table class="pba-pickleball-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Email Updates</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant) : ?>
                            <?php echo pba_render_pickleball_participant_row($participant, $people, $guests); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function pba_render_pickleball_participant_row($participant, $people, $guests) {
    $participant_id = isset($participant['participant_id']) ? (int) $participant['participant_id'] : 0;
    $participant_type = isset($participant['participant_type']) ? sanitize_key($participant['participant_type']) : '';
    $status = isset($participant['status']) ? strtolower((string) $participant['status']) : 'active';
    $email_updates = !empty($participant['email_updates_enabled']);
    $name = '';
    $email = '';
    $edit_url = '';

    if ($participant_type === 'pba_guest') {
        $guest_id = isset($participant['guest_id']) ? (int) $participant['guest_id'] : 0;
        $guest = isset($guests[$guest_id]) ? $guests[$guest_id] : array();
        $name = trim((string) ($guest['first_name'] ?? '') . ' ' . (string) ($guest['last_name'] ?? ''));
        $email = isset($guest['email_address']) ? (string) $guest['email_address'] : '';
        $edit_url = add_query_arg(array(
            'pickleball_view' => 'edit_guest',
            'guest_id' => $guest_id,
        ), home_url('/pickleball-admin/'));
    } else {
        $person_id = isset($participant['person_id']) ? (int) $participant['person_id'] : 0;
        $person = isset($people[$person_id]) ? $people[$person_id] : array();
        $name = trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? ''));
        $email = isset($person['email_address']) ? (string) $person['email_address'] : '';
    }

    if ($name === '') {
        $name = 'Participant #' . $participant_id;
    }

    ob_start();
    ?>
    <tr>
        <td data-label="Name"><?php echo esc_html($name); ?></td>
        <td data-label="Email"><?php echo esc_html($email); ?></td>
        <td data-label="Type"><?php echo esc_html($participant_type === 'pba_guest' ? 'Guest' : 'PBA Member'); ?></td>
        <td data-label="Status"><?php echo esc_html(ucfirst($status)); ?></td>
        <td data-label="Email Updates"><?php echo $email_updates ? 'Yes' : 'No'; ?></td>
        <td data-label="Actions">
            <?php if ($edit_url !== '') : ?>
                <a class="pba-pickleball-link" href="<?php echo esc_url($edit_url); ?>">Edit Guest</a>
            <?php endif; ?>
            <?php if ($participant_id > 0) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-row-form">
                    <?php wp_nonce_field('pba_pickleball_update_participant_' . $participant_id, 'pba_pickleball_nonce'); ?>
                    <input type="hidden" name="action" value="pba_pickleball_update_participant">
                    <input type="hidden" name="participant_id" value="<?php echo esc_attr($participant_id); ?>">
                    <select name="status" aria-label="Participant status">
                        <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>Inactive</option>
                    </select>
                    <label class="pba-pickleball-checkbox pba-pickleball-row-checkbox">
                        <input type="checkbox" name="email_updates_enabled" value="1" <?php checked($email_updates); ?>>
                        Email
                    </label>
                    <button type="submit" class="pba-pickleball-text-button">Save</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-pickleball-row-form">
                    <?php wp_nonce_field('pba_pickleball_remove_participant_' . $participant_id, 'pba_pickleball_nonce'); ?>
                    <input type="hidden" name="action" value="pba_pickleball_remove_participant">
                    <input type="hidden" name="participant_id" value="<?php echo esc_attr($participant_id); ?>">
                    <button type="submit" class="pba-pickleball-text-button pba-pickleball-remove-button">Remove</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

function pba_pickleball_admin_redirect($status) {
    $url = add_query_arg('pba_pickleball_status', sanitize_key($status), home_url('/pickleball-admin/'));
    wp_safe_redirect($url);
    exit;
}

function pba_pickleball_require_admin_post($nonce_action) {
    if (!pba_current_user_can_manage_pickleball()) {
        pba_pickleball_admin_redirect('not_allowed');
    }

    $nonce = isset($_POST['pba_pickleball_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['pba_pickleball_nonce']))
        : '';

    if ($nonce === '' || !wp_verify_nonce($nonce, $nonce_action)) {
        pba_pickleball_admin_redirect('invalid_request');
    }
}

function pba_handle_pickleball_update_status() {
    pba_pickleball_require_admin_post('pba_pickleball_update_status');

    $status_key = isset($_POST['status_key']) ? sanitize_key(wp_unslash($_POST['status_key'])) : '';
    $status_note = isset($_POST['status_note']) ? sanitize_textarea_field(wp_unslash($_POST['status_note'])) : '';

    if (!array_key_exists($status_key, pba_pickleball_status_options())) {
        $status_key = 'season_not_started';
    }

    $before = pba_pickleball_get_current_status();
    $payload = array(
        'status_key' => $status_key,
        'status_note' => $status_note !== '' ? $status_note : null,
        'updated_at' => gmdate('c'),
        'updated_by_person_id' => pba_pickleball_get_current_person_id(),
        'updated_by_wp_user_id' => get_current_user_id(),
        'updated_by_display_name' => pba_pickleball_get_current_actor_display_name(),
    );

    $result = pba_supabase_insert(pba_pickleball_status_table(), $payload);

    if (is_wp_error($result)) {
        pba_pickleball_admin_redirect('status_save_failed');
    }

    pba_pickleball_audit('pickleball.status.changed', 'PickleballStatus', isset($result['status_id']) ? (int) $result['status_id'] : null, array(
        'summary' => 'Pickleball status changed to ' . pba_pickleball_status_label($status_key) . '.',
        'before' => $before,
        'after' => $result,
    ));

    pba_pickleball_admin_redirect('status_saved');
}

function pba_handle_pickleball_add_guest() {
    pba_pickleball_require_admin_post('pba_pickleball_add_guest');

    $guest_data = pba_pickleball_get_guest_payload_from_post();

    if (is_wp_error($guest_data)) {
        pba_pickleball_admin_redirect($guest_data->get_error_code());
    }

    $existing = pba_pickleball_get_guest_by_email($guest_data['email_address']);

    if ($existing) {
        $existing_guest_id = isset($existing['guest_id']) ? (int) $existing['guest_id'] : 0;
        $existing_participant = pba_pickleball_get_guest_participant($existing_guest_id);
        $existing_participant_status = is_array($existing_participant) && isset($existing_participant['status'])
            ? strtolower((string) $existing_participant['status'])
            : '';

        if ($existing_participant && $existing_participant_status === 'active') {
            pba_pickleball_admin_redirect('guest_exists');
        }

        $before = array(
            'guest' => $existing,
            'participant' => $existing_participant,
        );

        $guest_data['updated_at'] = gmdate('c');
        $guest_data['updated_by_person_id'] = pba_pickleball_get_current_person_id();
        $guest_data['updated_by_wp_user_id'] = get_current_user_id();

        $updated_guest = pba_supabase_update(
            pba_pickleball_guest_table(),
            $guest_data,
            array('guest_id' => 'eq.' . $existing_guest_id)
        );

        if (is_wp_error($updated_guest)) {
            pba_pickleball_admin_redirect('guest_save_failed');
        }

        $participant = pba_pickleball_save_guest_participant(
            $existing_guest_id,
            $guest_data['status'],
            (bool) $guest_data['email_updates_enabled']
        );

        if (is_wp_error($participant)) {
            pba_pickleball_admin_redirect('participant_save_failed');
        }

        pba_pickleball_audit('pickleball.guest.readded', 'PickleballGuest', $existing_guest_id, array(
            'summary' => 'Pickleball guest re-added: ' . trim($guest_data['first_name'] . ' ' . $guest_data['last_name']) . '.',
            'before' => $before,
            'after' => array(
                'guest' => $guest_data,
                'participant' => $participant,
            ),
        ));

        pba_pickleball_admin_redirect('guest_readded');
    }

    $guest_data['created_by_person_id'] = pba_pickleball_get_current_person_id();
    $guest_data['created_by_wp_user_id'] = get_current_user_id();
    $guest_data['created_at'] = gmdate('c');
    $guest_data['updated_at'] = gmdate('c');

    $guest = pba_supabase_insert(pba_pickleball_guest_table(), $guest_data);

    if (is_wp_error($guest) || empty($guest['guest_id'])) {
        pba_pickleball_admin_redirect('guest_save_failed');
    }

    $participant = pba_pickleball_save_guest_participant(
        (int) $guest['guest_id'],
        $guest_data['status'],
        (bool) $guest_data['email_updates_enabled']
    );

    if (is_wp_error($participant)) {
        pba_pickleball_admin_redirect('participant_save_failed');
    }

    pba_pickleball_audit('pickleball.guest.added', 'PickleballGuest', (int) $guest['guest_id'], array(
        'summary' => 'Pickleball guest added: ' . trim($guest_data['first_name'] . ' ' . $guest_data['last_name']) . '.',
        'after' => $guest,
    ));

    pba_pickleball_admin_redirect('guest_added');
}

function pba_handle_pickleball_update_guest() {
    $guest_id = isset($_POST['guest_id']) ? absint($_POST['guest_id']) : 0;

    if ($guest_id < 1) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    pba_pickleball_require_admin_post('pba_pickleball_update_guest_' . $guest_id);

    $before = pba_pickleball_get_guest($guest_id);

    if (!$before) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    $guest_data = pba_pickleball_get_guest_payload_from_post();

    if (is_wp_error($guest_data)) {
        pba_pickleball_admin_redirect($guest_data->get_error_code());
    }

    $existing = pba_pickleball_get_guest_by_email($guest_data['email_address']);

    if ($existing && (int) $existing['guest_id'] !== $guest_id) {
        pba_pickleball_admin_redirect('guest_exists');
    }

    $guest_data['updated_at'] = gmdate('c');
    $guest_data['updated_by_person_id'] = pba_pickleball_get_current_person_id();
    $guest_data['updated_by_wp_user_id'] = get_current_user_id();

    $updated = pba_supabase_update(
        pba_pickleball_guest_table(),
        $guest_data,
        array('guest_id' => 'eq.' . $guest_id)
    );

    if (is_wp_error($updated)) {
        pba_pickleball_admin_redirect('guest_save_failed');
    }

    $participant = pba_pickleball_save_guest_participant(
        $guest_id,
        $guest_data['status'],
        (bool) $guest_data['email_updates_enabled']
    );

    if (is_wp_error($participant)) {
        pba_pickleball_admin_redirect('participant_save_failed');
    }

    $action = strtolower((string) ($before['status'] ?? '')) !== 'inactive' && $guest_data['status'] === 'inactive'
        ? 'pickleball.guest.deactivated'
        : 'pickleball.guest.updated';

    pba_pickleball_audit($action, 'PickleballGuest', $guest_id, array(
        'summary' => $action === 'pickleball.guest.deactivated' ? 'Pickleball guest deactivated.' : 'Pickleball guest updated.',
        'before' => $before,
        'after' => $guest_data,
    ));

    pba_pickleball_admin_redirect($action === 'pickleball.guest.deactivated' ? 'guest_deactivated' : 'guest_updated');
}

function pba_pickleball_get_guest_payload_from_post() {
    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
    $status = isset($_POST['status']) ? strtolower(sanitize_text_field(wp_unslash($_POST['status']))) : 'active';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
    $email_updates_enabled = !empty($_POST['email_updates_enabled']);

    if ($first_name === '' || $last_name === '' || $email_address === '') {
        return new WP_Error('missing_guest_fields', 'Missing required guest fields.');
    }

    if (!is_email($email_address)) {
        return new WP_Error('invalid_email', 'Invalid email address.');
    }

    if (!in_array($status, pba_pickleball_allowed_participant_statuses(), true)) {
        $status = 'active';
    }

    return array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email_address' => strtolower($email_address),
        'phone_number' => $phone_number !== '' ? $phone_number : null,
        'status' => $status,
        'email_updates_enabled' => $email_updates_enabled,
        'notes' => $notes !== '' ? $notes : null,
    );
}

function pba_pickleball_get_guest($guest_id) {
    $guest_id = (int) $guest_id;

    if ($guest_id < 1) {
        return null;
    }

    $rows = pba_supabase_get(pba_pickleball_guest_table(), array(
        'select' => '*',
        'guest_id' => 'eq.' . $guest_id,
        'limit' => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_pickleball_get_guest_by_email($email_address) {
    $email_address = strtolower(sanitize_email((string) $email_address));

    if ($email_address === '' || !is_email($email_address)) {
        return null;
    }

    $rows = pba_supabase_get(pba_pickleball_guest_table(), array(
        'select' => '*',
        'email_address' => 'eq.' . $email_address,
        'limit' => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_pickleball_get_guest_participant($guest_id) {
    $guest_id = (int) $guest_id;

    if ($guest_id < 1) {
        return null;
    }

    $rows = pba_supabase_get(pba_pickleball_participant_table(), array(
        'select' => '*',
        'guest_id' => 'eq.' . $guest_id,
        'limit' => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_pickleball_save_guest_participant($guest_id, $status, $email_updates_enabled) {
    $guest_id = (int) $guest_id;

    if ($guest_id < 1) {
        return new WP_Error('invalid_guest', 'Invalid guest.');
    }

    return pba_pickleball_save_participant(array(
        'participant_type' => 'pba_guest',
        'guest_id' => $guest_id,
        'person_id' => null,
        'status' => $status,
        'email_updates_enabled' => (bool) $email_updates_enabled,
    ));
}

function pba_handle_pickleball_add_member_participant() {
    pba_pickleball_require_admin_post('pba_pickleball_add_member_participant');

    $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
    $status = isset($_POST['status']) ? strtolower(sanitize_text_field(wp_unslash($_POST['status']))) : 'active';
    $email_updates_enabled = !empty($_POST['email_updates_enabled']);

    if ($email_address === '' || !is_email($email_address)) {
        pba_pickleball_admin_redirect('invalid_email');
    }

    if (!in_array($status, pba_pickleball_allowed_participant_statuses(), true)) {
        $status = 'active';
    }

    $person = pba_pickleball_get_member_person_by_email($email_address);

    if (!$person || empty($person['person_id'])) {
        pba_pickleball_admin_redirect('member_not_found');
    }

    $participant = pba_pickleball_save_participant(array(
        'participant_type' => 'pba_member',
        'person_id' => (int) $person['person_id'],
        'guest_id' => null,
        'status' => $status,
        'email_updates_enabled' => $email_updates_enabled,
    ));

    if (is_wp_error($participant)) {
        pba_pickleball_admin_redirect('participant_save_failed');
    }

    pba_pickleball_audit('pickleball.participant.added', 'PickleballParticipant', isset($participant['participant_id']) ? (int) $participant['participant_id'] : null, array(
        'summary' => 'PBA member added as a pickleball participant.',
        'target_person_id' => (int) $person['person_id'],
        'after' => $participant,
    ));

    pba_pickleball_admin_redirect('participant_added');
}

function pba_pickleball_get_member_person_by_email($email_address) {
    $email_address = strtolower(sanitize_email((string) $email_address));

    if ($email_address === '' || !is_email($email_address)) {
        return null;
    }

    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,first_name,last_name,email_address,status,wp_user_id',
        'email_address' => 'eq.' . $email_address,
        'limit' => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    $status = strtolower((string) ($rows[0]['status'] ?? ''));

    if (in_array($status, array('inactive', 'disabled', 'removed'), true)) {
        return null;
    }

    return $rows[0];
}

function pba_pickleball_save_participant($args) {
    $participant_type = isset($args['participant_type']) ? sanitize_key($args['participant_type']) : '';
    $person_id = !empty($args['person_id']) ? (int) $args['person_id'] : null;
    $guest_id = !empty($args['guest_id']) ? (int) $args['guest_id'] : null;
    $status = isset($args['status']) ? strtolower((string) $args['status']) : 'active';
    $email_updates_enabled = !empty($args['email_updates_enabled']);

    if (!in_array($participant_type, array('pba_member', 'pba_guest'), true)) {
        return new WP_Error('invalid_participant_type', 'Invalid participant type.');
    }

    if (!in_array($status, pba_pickleball_allowed_participant_statuses(), true)) {
        $status = 'active';
    }

    $filters = array();

    if ($participant_type === 'pba_member' && $person_id > 0) {
        $filters['person_id'] = 'eq.' . $person_id;
    } elseif ($participant_type === 'pba_guest' && $guest_id > 0) {
        $filters['guest_id'] = 'eq.' . $guest_id;
    } else {
        return new WP_Error('invalid_participant', 'Invalid participant.');
    }

    $existing_rows = pba_supabase_get(pba_pickleball_participant_table(), array_merge(array(
        'select' => '*',
        'limit' => 1,
    ), $filters));

    if (is_wp_error($existing_rows)) {
        return $existing_rows;
    }

    $payload = array(
        'participant_type' => $participant_type,
        'person_id' => $person_id,
        'guest_id' => $guest_id,
        'status' => $status,
        'email_updates_enabled' => $email_updates_enabled,
        'updated_at' => gmdate('c'),
        'updated_by_person_id' => pba_pickleball_get_current_person_id(),
        'updated_by_wp_user_id' => get_current_user_id(),
    );

    if (!empty($existing_rows[0]['participant_id'])) {
        $participant_id = (int) $existing_rows[0]['participant_id'];

        return pba_supabase_update(
            pba_pickleball_participant_table(),
            $payload,
            array('participant_id' => 'eq.' . $participant_id)
        );
    }

    $payload['created_at'] = gmdate('c');
    $payload['created_by_person_id'] = pba_pickleball_get_current_person_id();
    $payload['created_by_wp_user_id'] = get_current_user_id();

    return pba_supabase_insert(pba_pickleball_participant_table(), $payload);
}

function pba_handle_pickleball_update_participant() {
    $participant_id = isset($_POST['participant_id']) ? absint($_POST['participant_id']) : 0;

    if ($participant_id < 1) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    pba_pickleball_require_admin_post('pba_pickleball_update_participant_' . $participant_id);

    $before = pba_pickleball_get_participant($participant_id);

    if (!$before) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    $status = isset($_POST['status']) ? strtolower(sanitize_text_field(wp_unslash($_POST['status']))) : 'active';
    $email_updates_enabled = !empty($_POST['email_updates_enabled']);

    if (!in_array($status, pba_pickleball_allowed_participant_statuses(), true)) {
        $status = 'active';
    }

    $payload = array(
        'status' => $status,
        'email_updates_enabled' => $email_updates_enabled,
        'updated_at' => gmdate('c'),
        'updated_by_person_id' => pba_pickleball_get_current_person_id(),
        'updated_by_wp_user_id' => get_current_user_id(),
    );

    $updated = pba_supabase_update(
        pba_pickleball_participant_table(),
        $payload,
        array('participant_id' => 'eq.' . $participant_id)
    );

    if (is_wp_error($updated)) {
        pba_pickleball_admin_redirect('participant_save_failed');
    }

    pba_pickleball_audit('pickleball.participant.updated', 'PickleballParticipant', $participant_id, array(
        'summary' => 'Pickleball participant updated.',
        'before' => $before,
        'after' => $payload,
    ));

    pba_pickleball_admin_redirect('participant_updated');
}

function pba_handle_pickleball_remove_participant() {
    $participant_id = isset($_POST['participant_id']) ? absint($_POST['participant_id']) : 0;

    if ($participant_id < 1) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    pba_pickleball_require_admin_post('pba_pickleball_remove_participant_' . $participant_id);

    $before = pba_pickleball_get_participant($participant_id);

    if (!$before) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    $payload = array(
        'status' => 'removed',
        'email_updates_enabled' => false,
        'updated_at' => gmdate('c'),
        'updated_by_person_id' => pba_pickleball_get_current_person_id(),
        'updated_by_wp_user_id' => get_current_user_id(),
    );

    $updated = pba_supabase_update(
        pba_pickleball_participant_table(),
        $payload,
        array('participant_id' => 'eq.' . $participant_id)
    );

    if (is_wp_error($updated)) {
        pba_pickleball_admin_redirect('participant_save_failed');
    }

    pba_pickleball_audit('pickleball.participant.removed', 'PickleballParticipant', $participant_id, array(
        'summary' => 'Pickleball participant removed from the active list.',
        'before' => $before,
        'after' => $payload,
    ));

    pba_pickleball_admin_redirect('participant_removed');
}

function pba_pickleball_get_participant($participant_id) {
    $participant_id = (int) $participant_id;

    if ($participant_id < 1) {
        return null;
    }

    $rows = pba_supabase_get(pba_pickleball_participant_table(), array(
        'select' => '*',
        'participant_id' => 'eq.' . $participant_id,
        'limit' => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return null;
    }

    return $rows[0];
}

function pba_pickleball_get_participant_rows() {
    $participants = pba_supabase_get(pba_pickleball_participant_table(), array(
        'select' => '*',
        'status' => 'neq.removed',
        'order' => 'status.asc,participant_type.asc,created_at.desc',
        'limit' => 500,
    ));

    if (is_wp_error($participants) || !is_array($participants)) {
        return is_wp_error($participants) ? $participants : new WP_Error('participants_unavailable', 'Participants unavailable.');
    }

    $person_ids = array();
    $guest_ids = array();

    foreach ($participants as $participant) {
        if (!empty($participant['person_id'])) {
            $person_ids[] = (int) $participant['person_id'];
        }
        if (!empty($participant['guest_id'])) {
            $guest_ids[] = (int) $participant['guest_id'];
        }
    }

    return array(
        'participants' => $participants,
        'people' => pba_pickleball_get_people_map($person_ids),
        'guests' => pba_pickleball_get_guest_map($guest_ids),
    );
}

function pba_pickleball_get_people_map($person_ids) {
    $person_ids = array_values(array_unique(array_filter(array_map('absint', (array) $person_ids))));

    if (empty($person_ids)) {
        return array();
    }

    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,first_name,last_name,email_address,status',
        'person_id' => 'in.(' . implode(',', $person_ids) . ')',
        'limit' => count($person_ids),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $map = array();

    foreach ($rows as $row) {
        if (!empty($row['person_id'])) {
            $map[(int) $row['person_id']] = $row;
        }
    }

    return $map;
}

function pba_pickleball_get_guest_map($guest_ids) {
    $guest_ids = array_values(array_unique(array_filter(array_map('absint', (array) $guest_ids))));

    if (empty($guest_ids)) {
        return array();
    }

    $rows = pba_supabase_get(pba_pickleball_guest_table(), array(
        'select' => '*',
        'guest_id' => 'in.(' . implode(',', $guest_ids) . ')',
        'limit' => count($guest_ids),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $map = array();

    foreach ($rows as $row) {
        if (!empty($row['guest_id'])) {
            $map[(int) $row['guest_id']] = $row;
        }
    }

    return $map;
}

function pba_pickleball_get_email_update_recipients() {
    $data = pba_pickleball_get_participant_rows();

    if (is_wp_error($data)) {
        return array();
    }

    $recipients = array();

    foreach ($data['participants'] as $participant) {
        $status = strtolower((string) ($participant['status'] ?? ''));

        if ($status !== 'active' || empty($participant['email_updates_enabled'])) {
            continue;
        }

        $type = isset($participant['participant_type']) ? sanitize_key($participant['participant_type']) : '';

        if ($type === 'pba_guest') {
            $guest_id = isset($participant['guest_id']) ? (int) $participant['guest_id'] : 0;
            $guest = isset($data['guests'][$guest_id]) ? $data['guests'][$guest_id] : array();

            if (empty($guest) || strtolower((string) ($guest['status'] ?? '')) !== 'active' || empty($guest['email_updates_enabled'])) {
                continue;
            }

            $email = isset($guest['email_address']) ? sanitize_email($guest['email_address']) : '';
            $name = trim((string) ($guest['first_name'] ?? '') . ' ' . (string) ($guest['last_name'] ?? ''));
        } else {
            $person_id = isset($participant['person_id']) ? (int) $participant['person_id'] : 0;
            $person = isset($data['people'][$person_id]) ? $data['people'][$person_id] : array();
            $person_status = strtolower((string) ($person['status'] ?? ''));

            if (in_array($person_status, array('inactive', 'disabled', 'removed'), true)) {
                continue;
            }

            $email = isset($person['email_address']) ? sanitize_email($person['email_address']) : '';
            $name = trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? ''));
        }

        if ($email === '' || !is_email($email)) {
            continue;
        }

        $recipients[strtolower($email)] = array(
            'name' => $name,
            'email_address' => $email,
            'participant_type' => $type,
        );
    }

    return array_values($recipients);
}

function pba_handle_pickleball_create_announcement() {
    pba_pickleball_require_admin_post('pba_pickleball_create_announcement');

    $title = isset($_POST['message_title']) ? sanitize_text_field(wp_unslash($_POST['message_title'])) : '';
    $body = isset($_POST['message_body']) ? sanitize_textarea_field(wp_unslash($_POST['message_body'])) : '';
    $pinned = !empty($_POST['pinned']);

    if (trim($body) === '') {
        pba_pickleball_admin_redirect('missing_announcement');
    }

    $payload = array(
        'message_title' => $title !== '' ? $title : null,
        'message_body' => $body,
        'status' => 'active',
        'pinned' => $pinned,
        'posted_by_person_id' => pba_pickleball_get_current_person_id(),
        'posted_by_wp_user_id' => get_current_user_id(),
        'posted_by_email_address' => pba_pickleball_get_current_actor_email(),
        'posted_by_display_name' => pba_pickleball_get_current_actor_display_name(),
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    );

    $announcement = pba_supabase_insert(pba_pickleball_announcement_table(), $payload);

    if (is_wp_error($announcement)) {
        pba_pickleball_admin_redirect('announcement_save_failed');
    }

    pba_pickleball_audit('pickleball.announcement.posted', 'PickleballAnnouncement', isset($announcement['pickleball_announcement_id']) ? (int) $announcement['pickleball_announcement_id'] : null, array(
        'summary' => 'Pickleball announcement posted.',
        'after' => $announcement,
    ));

    pba_pickleball_admin_redirect('announcement_posted');
}

function pba_handle_pickleball_delete_announcement() {
    $announcement_id = isset($_POST['announcement_id']) ? absint($_POST['announcement_id']) : 0;

    if ($announcement_id < 1) {
        pba_pickleball_admin_redirect('invalid_request');
    }

    pba_pickleball_require_admin_post('pba_pickleball_delete_announcement_' . $announcement_id);

    $before_rows = pba_supabase_get(pba_pickleball_announcement_table(), array(
        'select' => '*',
        'pickleball_announcement_id' => 'eq.' . $announcement_id,
        'limit' => 1,
    ));

    $before = (!is_wp_error($before_rows) && !empty($before_rows[0])) ? $before_rows[0] : array();

    $updated = pba_supabase_update(
        pba_pickleball_announcement_table(),
        array(
            'status' => 'deleted',
            'updated_at' => gmdate('c'),
        ),
        array('pickleball_announcement_id' => 'eq.' . $announcement_id)
    );

    if (is_wp_error($updated)) {
        pba_pickleball_admin_redirect('announcement_save_failed');
    }

    pba_pickleball_audit('pickleball.announcement.deleted', 'PickleballAnnouncement', $announcement_id, array(
        'summary' => 'Pickleball announcement deleted.',
        'before' => $before,
        'after' => array('status' => 'deleted'),
    ));

    pba_pickleball_admin_redirect('announcement_deleted');
}

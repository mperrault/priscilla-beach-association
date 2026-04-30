<?php
/**
 * PBA Member Announcements
 *
 * Supabase-backed announcement feed for the member-home page.
 *
 * Allows PBAAdmin, PBABoardMember, and PBACommitteeMember users to post
 * announcements visible to all PBAMembers.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pba_member_announcements_table_name')) {
    function pba_member_announcements_table_name() {
        return 'MemberAnnouncement';
    }
}

if (!function_exists('pba_member_announcement_normalize_role_name')) {
    function pba_member_announcement_normalize_role_name($role_name) {
        return strtolower(str_replace(array('_', '-', ' '), '', trim((string) $role_name)));
    }
}

if (!function_exists('pba_get_current_user_role_slugs_for_announcements')) {
    function pba_get_current_user_role_slugs_for_announcements() {
        $user = wp_get_current_user();

        if (!$user || empty($user->ID) || empty($user->roles)) {
            return array();
        }

        return array_values(array_map('strval', (array) $user->roles));
    }
}

if (!function_exists('pba_get_current_person_for_announcements')) {
    function pba_get_current_person_for_announcements() {
        $wp_user_id = get_current_user_id();

        if ($wp_user_id < 1) {
            return null;
        }

        $person_id = get_user_meta($wp_user_id, 'pba_person_id', true);
        $person_id = (int) $person_id;

        if ($person_id > 0) {
            $rows = pba_supabase_get('Person', array(
                'select'    => 'person_id,first_name,last_name,email_address,wp_user_id,status',
                'person_id' => 'eq.' . $person_id,
                'limit'     => 1,
            ));

            if (!is_wp_error($rows) && !empty($rows[0])) {
                return $rows[0];
            }
        }

        $rows = pba_supabase_get('Person', array(
            'select'     => 'person_id,first_name,last_name,email_address,wp_user_id,status',
            'wp_user_id' => 'eq.' . $wp_user_id,
            'limit'      => 1,
        ));

        if (!is_wp_error($rows) && !empty($rows[0])) {
            return $rows[0];
        }

        return null;
    }
}

if (!function_exists('pba_get_current_announcement_person_id')) {
    function pba_get_current_announcement_person_id() {
        $person = pba_get_current_person_for_announcements();

        if (is_array($person) && !empty($person['person_id'])) {
            return (int) $person['person_id'];
        }

        return null;
    }
}

if (!function_exists('pba_get_current_announcement_poster_display_name')) {
    function pba_get_current_announcement_poster_display_name() {
        $person = pba_get_current_person_for_announcements();

        if (is_array($person)) {
            $first_name = isset($person['first_name']) ? trim((string) $person['first_name']) : '';
            $last_name  = isset($person['last_name']) ? trim((string) $person['last_name']) : '';

            $name = trim($first_name . ' ' . $last_name);

            if ($name !== '') {
                return $name;
            }
        }

        $user = wp_get_current_user();

        if ($user && !empty($user->display_name)) {
            return $user->display_name;
        }

        if ($user && !empty($user->user_login)) {
            return $user->user_login;
        }

        return 'PBA';
    }
}

if (!function_exists('pba_get_current_announcement_email_address')) {
    function pba_get_current_announcement_email_address() {
        $person = pba_get_current_person_for_announcements();

        if (is_array($person) && !empty($person['email_address'])) {
            return sanitize_email((string) $person['email_address']);
        }

        $user = wp_get_current_user();

        if ($user && !empty($user->user_email)) {
            return sanitize_email($user->user_email);
        }

        return '';
    }
}

if (!function_exists('pba_get_current_announcement_role_names')) {
    function pba_get_current_announcement_role_names() {
        $role_names = array();

        foreach (pba_get_current_user_role_slugs_for_announcements() as $wp_role) {
            $role_names[] = (string) $wp_role;
        }

        $person_id = pba_get_current_announcement_person_id();

        if ($person_id > 0 && function_exists('pba_get_active_supabase_role_names_for_person')) {
            $supabase_roles = pba_get_active_supabase_role_names_for_person($person_id);

            if (is_array($supabase_roles)) {
                foreach ($supabase_roles as $role_name) {
                    $role_names[] = (string) $role_name;
                }
            }
        }

        return array_values(array_unique(array_filter($role_names)));
    }
}

if (!function_exists('pba_current_user_has_announcement_role')) {
    function pba_current_user_has_announcement_role($target_role_name) {
        $target = pba_member_announcement_normalize_role_name($target_role_name);

        foreach (pba_get_current_announcement_role_names() as $role_name) {
            $normalized = pba_member_announcement_normalize_role_name($role_name);

            if ($normalized === $target) {
                return true;
            }

            if ($target === 'pbaadmin' && $normalized === 'pbaadministrator') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('pba_current_user_can_post_member_announcement')) {
    function pba_current_user_can_post_member_announcement() {
        if (!is_user_logged_in()) {
            return false;
        }

        return (
            pba_current_user_has_announcement_role('PBAAdmin')
            || pba_current_user_has_announcement_role('PBABoardMember')
            || pba_current_user_has_announcement_role('PBACommitteeMember')
        );
    }
}

if (!function_exists('pba_current_user_can_manage_member_announcement')) {
    function pba_current_user_can_manage_member_announcement($announcement) {
        if (!is_user_logged_in()) {
            return false;
        }

        if (pba_current_user_has_announcement_role('PBAAdmin')) {
            return true;
        }

        $current_wp_user_id = get_current_user_id();

        $posted_by_wp_user_id = isset($announcement['posted_by_wp_user_id'])
            ? (int) $announcement['posted_by_wp_user_id']
            : 0;

        return ($current_wp_user_id > 0 && $posted_by_wp_user_id === $current_wp_user_id);
    }
}

if (!function_exists('pba_member_announcement_committee_display_label')) {
    function pba_member_announcement_committee_display_label($committee_name) {
        $committee_name = trim((string) $committee_name);

        if ($committee_name === '') {
            return '';
        }

        $lower = strtolower($committee_name);

        if ($lower === 'board' || $lower === 'pba board' || $lower === 'board of directors') {
            return 'PBA Board';
        }

        if (stripos($committee_name, 'PBA ') === 0) {
            return $committee_name;
        }

        return 'PBA ' . $committee_name;
    }
}

if (!function_exists('pba_get_active_committee_post_as_options_for_person')) {
    function pba_get_active_committee_post_as_options_for_person($person_id) {
        $person_id = (int) $person_id;

        if ($person_id < 1) {
            return array();
        }

        $membership_rows = pba_supabase_get('Person_to_Committee', array(
            'select'    => 'committee_id,committee_role,is_active',
            'person_id' => 'eq.' . $person_id,
            'is_active' => 'eq.true',
            'limit'     => 100,
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
            'committee_id' => 'in.(' . implode(',', $committee_ids) . ')',
            'status'       => 'eq.Active',
            'limit'        => count($committee_ids),
        ));

        if (is_wp_error($committee_rows) || !is_array($committee_rows) || empty($committee_rows)) {
            return array();
        }

        $options = array();

        foreach ($committee_rows as $committee) {
            $committee_name = isset($committee['committee_name'])
                ? trim((string) $committee['committee_name'])
                : '';

            $label = pba_member_announcement_committee_display_label($committee_name);

            if ($label !== '' && strcasecmp($label, 'PBA Board') !== 0) {
                $options[$label] = $label;
            }
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}

if (!function_exists('pba_get_all_active_committee_post_as_options')) {
    function pba_get_all_active_committee_post_as_options() {
        $committee_rows = pba_supabase_get('Committee', array(
            'select' => 'committee_id,committee_name,status',
            'status' => 'eq.Active',
            'order'  => 'committee_name.asc',
            'limit'  => 200,
        ));

        if (is_wp_error($committee_rows) || !is_array($committee_rows) || empty($committee_rows)) {
            return array();
        }

        $options = array();

        foreach ($committee_rows as $committee) {
            $committee_name = isset($committee['committee_name'])
                ? trim((string) $committee['committee_name'])
                : '';

            $label = pba_member_announcement_committee_display_label($committee_name);

            if ($label !== '' && strcasecmp($label, 'PBA Board') !== 0) {
                $options[$label] = $label;
            }
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}

if (!function_exists('pba_get_member_announcement_post_as_options')) {
    function pba_get_member_announcement_post_as_options() {
        $options = array();
        $person_id = pba_get_current_announcement_person_id();

        if (pba_current_user_has_announcement_role('PBAAdmin')) {
            $options['PBA Admin'] = 'PBA Admin';
            $options['PBA Board'] = 'PBA Board';

            foreach (pba_get_all_active_committee_post_as_options() as $value => $label) {
                $options[$value] = $label;
            }
        } else {
            if (pba_current_user_has_announcement_role('PBABoardMember')) {
                $options['PBA Board'] = 'PBA Board';
            }

            if (pba_current_user_has_announcement_role('PBACommitteeMember')) {
                foreach (pba_get_active_committee_post_as_options_for_person($person_id) as $value => $label) {
                    $options[$value] = $label;
                }
            }
        }

        $options = apply_filters('pba_member_announcement_post_as_options', $options, $person_id);

        if (empty($options) && pba_current_user_can_post_member_announcement()) {
            $options['PBA'] = 'PBA';
        }

        return $options;
    }
}

if (!function_exists('pba_get_sanitized_member_announcement_post_as_label')) {
    function pba_get_sanitized_member_announcement_post_as_label($raw_value) {
        $options = pba_get_member_announcement_post_as_options();

        $raw_value = sanitize_text_field((string) $raw_value);

        if (isset($options[$raw_value])) {
            return $options[$raw_value];
        }

        if (!empty($options)) {
            return reset($options);
        }

        return 'PBA';
    }
}

if (!function_exists('pba_get_member_announcements')) {
    function pba_get_member_announcements($limit = 75) {
        $limit = max(1, min(200, absint($limit)));

        $rows = pba_supabase_get(pba_member_announcements_table_name(), array(
            'select' => '*',
            'status' => 'eq.active',
            'order'  => 'pinned.desc,created_at.desc',
            'limit'  => $limit,
        ));

        if (is_wp_error($rows) || !is_array($rows)) {
            return array();
        }

        $now = current_time('timestamp');
        $filtered = array();

        foreach ($rows as $row) {
            $expires_at = isset($row['expires_at']) ? trim((string) $row['expires_at']) : '';

            if ($expires_at !== '') {
                $expires_timestamp = strtotime($expires_at);

                if ($expires_timestamp && $expires_timestamp < $now) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        return $filtered;
    }
}

if (!function_exists('pba_create_member_announcement')) {
    function pba_create_member_announcement($title, $body, $posted_as_label) {
        if (!pba_current_user_can_post_member_announcement()) {
            return new WP_Error('not_allowed', 'You are not allowed to post member announcements.');
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->ID)) {
            return new WP_Error('not_logged_in', 'You must be logged in to post a member announcement.');
        }

        $title = sanitize_text_field((string) $title);
        $body = sanitize_textarea_field((string) $body);
        $posted_as_label = pba_get_sanitized_member_announcement_post_as_label($posted_as_label);

        if (trim($body) === '') {
            return new WP_Error('missing_message', 'Please enter an announcement message.');
        }

        $payload = array(
            'posted_by_person_id'     => pba_get_current_announcement_person_id(),
            'posted_by_wp_user_id'    => (int) $user->ID,
            'posted_by_email_address' => pba_get_current_announcement_email_address(),
            'posted_by_display_name'  => pba_get_current_announcement_poster_display_name(),
            'posted_as_label'         => $posted_as_label,
            'message_title'           => $title !== '' ? $title : null,
            'message_body'            => $body,
            'status'                  => 'active',
            'pinned'                  => false,
            'created_by_ip_address'   => isset($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
                : '',
        );

        $result = pba_supabase_insert(pba_member_announcements_table_name(), $payload);

        if (is_wp_error($result)) {
            return $result;
        }

        do_action('pba_member_announcement_created', $result, $payload);

        return $result;
    }
}

if (!function_exists('pba_get_member_announcement_by_id')) {
    function pba_get_member_announcement_by_id($announcement_id) {
        $announcement_id = (int) $announcement_id;

        if ($announcement_id < 1) {
            return null;
        }

        $rows = pba_supabase_get(pba_member_announcements_table_name(), array(
            'select'                 => '*',
            'member_announcement_id' => 'eq.' . $announcement_id,
            'limit'                  => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_soft_delete_member_announcement')) {
    function pba_soft_delete_member_announcement($announcement_id) {
        $announcement_id = (int) $announcement_id;

        if ($announcement_id < 1) {
            return new WP_Error('invalid_announcement', 'Invalid announcement.');
        }

        $announcement = pba_get_member_announcement_by_id($announcement_id);

        if (!$announcement) {
            return new WP_Error('not_found', 'Announcement was not found.');
        }

        if (!pba_current_user_can_manage_member_announcement($announcement)) {
            return new WP_Error('not_allowed', 'You are not allowed to delete this announcement.');
        }

        $payload = array(
            'status'           => 'deleted',
            'last_modified_at' => gmdate('c'),
        );

        $result = pba_supabase_update(
            pba_member_announcements_table_name(),
            $payload,
            array(
                'member_announcement_id' => 'eq.' . $announcement_id,
            )
        );

        if (is_wp_error($result)) {
            return $result;
        }

        do_action('pba_member_announcement_deleted', $announcement_id, $announcement);

        return $result;
    }
}

if (!function_exists('pba_handle_member_announcement_actions')) {
    function pba_handle_member_announcement_actions() {
        if (!is_user_logged_in()) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (empty($_POST['pba_member_announcement_action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['pba_member_announcement_action']));

        if ($action === 'create') {
            pba_handle_create_member_announcement();
            return;
        }

        if ($action === 'delete') {
            pba_handle_delete_member_announcement();
            return;
        }
    }

    add_action('template_redirect', 'pba_handle_member_announcement_actions');
}

if (!function_exists('pba_handle_create_member_announcement')) {
    function pba_handle_create_member_announcement() {
        if (!pba_current_user_can_post_member_announcement()) {
            pba_member_announcement_redirect_with_status('not_allowed');
        }

        if (
            empty($_POST['pba_member_announcement_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pba_member_announcement_nonce'])),
                'pba_create_member_announcement'
            )
        ) {
            pba_member_announcement_redirect_with_status('invalid_request');
        }

        $title = isset($_POST['message_title'])
            ? sanitize_text_field(wp_unslash($_POST['message_title']))
            : '';

        $body = isset($_POST['message_body'])
            ? sanitize_textarea_field(wp_unslash($_POST['message_body']))
            : '';

        $posted_as_label = isset($_POST['posted_as_label'])
            ? sanitize_text_field(wp_unslash($_POST['posted_as_label']))
            : '';

        if (trim($body) === '') {
            pba_member_announcement_redirect_with_status('missing_message');
        }

        $result = pba_create_member_announcement($title, $body, $posted_as_label);

        if (is_wp_error($result)) {
            error_log('PBA announcement create failed: ' . $result->get_error_message());
            pba_member_announcement_redirect_with_status('create_failed');
        }

        pba_member_announcement_redirect_with_status('created');
    }
}

if (!function_exists('pba_handle_delete_member_announcement')) {
    function pba_handle_delete_member_announcement() {
        if (
            empty($_POST['pba_member_announcement_delete_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pba_member_announcement_delete_nonce'])),
                'pba_delete_member_announcement'
            )
        ) {
            pba_member_announcement_redirect_with_status('invalid_request');
        }

        $announcement_id = isset($_POST['member_announcement_id'])
            ? absint($_POST['member_announcement_id'])
            : 0;

        if ($announcement_id < 1) {
            pba_member_announcement_redirect_with_status('invalid_request');
        }

        $result = pba_soft_delete_member_announcement($announcement_id);

        if (is_wp_error($result)) {
            error_log('PBA announcement delete failed: ' . $result->get_error_message());

            if ($result->get_error_code() === 'not_allowed') {
                pba_member_announcement_redirect_with_status('not_allowed');
            }

            if ($result->get_error_code() === 'not_found') {
                pba_member_announcement_redirect_with_status('not_found');
            }

            pba_member_announcement_redirect_with_status('delete_failed');
        }

        pba_member_announcement_redirect_with_status('deleted');
    }
}

if (!function_exists('pba_member_announcement_redirect_with_status')) {
    function pba_member_announcement_redirect_with_status($status) {
        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('/member-home/');

        $redirect_url = remove_query_arg(
            array('pba_announcement_status'),
            $redirect_url
        );

        $redirect_url = add_query_arg(
            'pba_announcement_status',
            sanitize_key($status),
            $redirect_url
        );

        wp_safe_redirect($redirect_url);
        exit;
    }
}

if (!function_exists('pba_render_member_announcement_status_message')) {
    function pba_render_member_announcement_status_message() {
        if (empty($_GET['pba_announcement_status'])) {
            return '';
        }

        $status = sanitize_key(wp_unslash($_GET['pba_announcement_status']));

        $messages = array(
            'created'         => array('success', 'Announcement posted.'),
            'deleted'         => array('success', 'Announcement deleted.'),
            'missing_message' => array('error', 'Please enter an announcement message.'),
            'invalid_request' => array('error', 'The announcement request was invalid. Please try again.'),
            'not_allowed'     => array('error', 'You are not allowed to perform that announcement action.'),
            'not_found'       => array('error', 'Announcement was not found.'),
            'create_failed'   => array('error', 'Announcement could not be posted. Please try again.'),
            'delete_failed'   => array('error', 'Announcement could not be deleted. Please try again.'),
        );

        if (!isset($messages[$status])) {
            return '';
        }

        $type = $messages[$status][0];
        $message = $messages[$status][1];

        $class = $type === 'success'
            ? 'pba-announcement-status pba-announcement-status-success'
            : 'pba-announcement-status pba-announcement-status-error';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <span class="pba-announcement-status-icon" aria-hidden="true">
                <?php echo $type === 'success' ? '&#10003;' : '&#10005;'; ?>
            </span>
            <span><?php echo esc_html($message); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_render_member_announcement_form')) {
    function pba_render_member_announcement_form() {
        if (!pba_current_user_can_post_member_announcement()) {
            return '';
        }

        $post_as_options = pba_get_member_announcement_post_as_options();

        ob_start();
        ?>
        <section class="pba-announcement-compose-card" aria-labelledby="pba-announcement-compose-title">
            <div class="pba-announcement-compose-header">
                <h3 id="pba-announcement-compose-title" class="pba-announcement-compose-title">
                    Post an Announcement
                </h3>
                <p class="pba-announcement-compose-intro">
                    Share an update with all members. New announcements appear at the top of the feed.
                </p>
            </div>

            <form method="post" class="pba-announcement-compose-form">
                <?php wp_nonce_field('pba_create_member_announcement', 'pba_member_announcement_nonce'); ?>

                <input type="hidden" name="pba_member_announcement_action" value="create">

                <div class="pba-announcement-form-row">
                    <label for="pba-announcement-post-as">Post as</label>
                    <select id="pba-announcement-post-as" name="posted_as_label">
                        <?php foreach ($post_as_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pba-announcement-form-row">
                    <label for="pba-announcement-title">
                        Title <span class="pba-optional-label">(optional)</span>
                    </label>
                    <input
                        id="pba-announcement-title"
                        type="text"
                        name="message_title"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Short announcement title"
                    >
                </div>

                <div class="pba-announcement-form-row">
                    <label for="pba-announcement-body">Message</label>
                    <textarea
                        id="pba-announcement-body"
                        name="message_body"
                        rows="6"
                        required
                        placeholder="Write your announcement here..."
                    ></textarea>
                </div>

                <div class="pba-announcement-form-actions">
                    <button type="submit" class="pba-btn pba-announcement-submit-button">
                        Post Announcement
                    </button>
                </div>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_member_announcement_row_value')) {
    function pba_member_announcement_row_value($row, $key, $default = '') {
        if (is_array($row) && array_key_exists($key, $row)) {
            return $row[$key];
        }

        if (is_object($row) && isset($row->{$key})) {
            return $row->{$key};
        }

        return $default;
    }
}

if (!function_exists('pba_member_announcement_format_date')) {
    function pba_member_announcement_format_date($created_at) {
        if (empty($created_at)) {
            return array('', '');
        }

        try {
            $dt = new DateTime((string) $created_at);
            $dt->setTimezone(new DateTimeZone('America/New_York'));

            return array(
                $dt->format('M j, Y'),
                $dt->format('g:i A'),
            );
        } catch (Exception $e) {
            return array((string) $created_at, '');
        }
    }
}

if (!function_exists('pba_render_member_announcement_item')) {
    function pba_render_member_announcement_item($announcement) {
        $announcement_id = (int) pba_member_announcement_row_value($announcement, 'member_announcement_id', 0);

        $created_at = pba_member_announcement_row_value($announcement, 'created_at', '');
        list($created_label, $created_time_label) = pba_member_announcement_format_date($created_at);

        $title = trim((string) pba_member_announcement_row_value($announcement, 'message_title', ''));
        $body = (string) pba_member_announcement_row_value($announcement, 'message_body', '');

        $posted_as_label = trim((string) pba_member_announcement_row_value($announcement, 'posted_as_label', 'PBA'));
        $posted_by_display_name = trim((string) pba_member_announcement_row_value($announcement, 'posted_by_display_name', 'PBA'));

        if ($posted_as_label === '') {
            $posted_as_label = 'PBA';
        }

        if ($posted_by_display_name === '') {
            $posted_by_display_name = 'PBA';
        }

        $can_manage = is_array($announcement)
            ? pba_current_user_can_manage_member_announcement($announcement)
            : false;

        ob_start();
        ?>
        <article class="pba-announcement-item">
            <div class="pba-announcement-item-top">
                <div class="pba-announcement-meta-pills">
                    <?php if ($created_label !== '') : ?>
                        <span class="pba-announcement-pill pba-announcement-pill-date">
                            <?php echo esc_html($created_label); ?>
                            <?php if ($created_time_label !== '') : ?>
                                <span class="pba-announcement-pill-time"><?php echo esc_html($created_time_label); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <span class="pba-announcement-pill">
                        From: <?php echo esc_html($posted_as_label); ?>
                    </span>

                    <span class="pba-announcement-pill pba-announcement-pill-author">
                        Posted by: <?php echo esc_html($posted_by_display_name); ?>
                    </span>
                </div>

                <?php if ($can_manage && $announcement_id > 0) : ?>
                    <form method="post" class="pba-announcement-delete-form">
                        <?php wp_nonce_field('pba_delete_member_announcement', 'pba_member_announcement_delete_nonce'); ?>
                        <input type="hidden" name="pba_member_announcement_action" value="delete">
                        <input type="hidden" name="member_announcement_id" value="<?php echo esc_attr($announcement_id); ?>">
                        <button
                            type="submit"
                            class="pba-announcement-delete-button"
                            onclick="return confirm('Delete this announcement?');"
                        >
                            Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($title !== '') : ?>
                <h3 class="pba-announcement-title">
                    <?php echo esc_html($title); ?>
                </h3>
            <?php endif; ?>

            <div class="pba-announcement-body">
                <?php echo nl2br(esc_html($body)); ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_render_member_announcements_panel')) {
    function pba_render_member_announcements_panel() {
        $announcements = pba_get_member_announcements(75);
        $can_post = pba_current_user_can_post_member_announcement();

        $grid_class = 'pba-announcements-grid';
        if ($can_post) {
            $grid_class .= ' pba-announcements-grid-has-compose';
        }

        ob_start();
        ?>
        <section class="pba-section pba-announcements-shell" aria-labelledby="pba-announcements-title">
            <div class="pba-announcements-header">
                <div>
                    <h2 id="pba-announcements-title" class="pba-announcements-title">
                        PBA Announcements
                    </h2>
                    <p class="pba-announcements-subtitle">
                        Updates posted for PBA members.
                    </p>
                </div>
            </div>

            <?php echo pba_render_member_announcement_status_message(); ?>

            <div class="<?php echo esc_attr($grid_class); ?>">
                <?php if ($can_post) : ?>
                    <div class="pba-announcements-compose-column">
                        <?php echo pba_render_member_announcement_form(); ?>
                    </div>
                <?php endif; ?>

                <div class="pba-announcements-feed-card">
                    <div class="pba-announcements-feed-header">
                        <h3 class="pba-announcements-feed-title">Recent Announcements</h3>
                        <p class="pba-announcements-feed-note">Newest posts appear first.</p>
                    </div>

                    <div class="pba-announcements-panel">
                        <?php if (empty($announcements)) : ?>
                            <div class="pba-announcements-empty">
                                No announcements have been posted yet.
                            </div>
                        <?php else : ?>
                            <?php foreach ($announcements as $announcement) : ?>
                                <?php echo pba_render_member_announcement_item($announcement); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
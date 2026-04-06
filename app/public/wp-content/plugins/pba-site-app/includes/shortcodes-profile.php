<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_profile_shortcode');
add_action('admin_post_pba_save_profile', 'pba_handle_save_profile');

function pba_register_profile_shortcode() {
    add_shortcode('pba_profile', 'pba_render_profile_shortcode');
}

function pba_render_profile_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!function_exists('pba_get_current_person_record')) {
        return '<p>Profile is temporarily unavailable.</p>';
    }

    $person = pba_get_current_person_record();

    if (!$person || empty($person['person_id'])) {
        return '<p>Unable to load your profile.</p>';
    }

    $person_id = (int) $person['person_id'];
    $role_names = function_exists('pba_get_current_person_role_names') ? pba_get_current_person_role_names() : array();

    $committee_labels = array();
    if (function_exists('pba_get_committee_labels_for_person_in_app')) {
        $committee_labels = pba_get_committee_labels_for_person_in_app($person_id);
    } elseif (function_exists('pba_get_current_person_committee_rows')) {
        $committee_rows = pba_get_current_person_committee_rows();
        foreach ($committee_rows as $row) {
            if (!empty($row['committee_name'])) {
                $committee_labels[] = (string) $row['committee_name'];
            }
        }
    }

    $household_label = '';
    if (!empty($person['household_id']) && function_exists('pba_get_household_label')) {
        $household_label = pba_get_household_label((int) $person['household_id']);
    }

    $status_message = '';
    $status = isset($_GET['pba_profile_status']) ? sanitize_text_field(wp_unslash($_GET['pba_profile_status'])) : '';

    if ($status === 'profile_saved') {
        $status_message = '<div class="pba-profile-message">Profile updated successfully.</div>';
    } elseif ($status === 'save_failed') {
        $status_message = '<div class="pba-profile-message error">Unable to save your profile.</div>';
    } elseif ($status === 'invalid_request') {
        $status_message = '<div class="pba-profile-message error">Invalid profile update request.</div>';
    }

    $password_url = home_url('/change-password/');
    
    ob_start();
    ?>
    <style>
        .pba-profile-wrap {
            max-width: 1000px;
            margin: 0 auto;
        }

        .pba-profile-section {
            margin: 0 0 24px;
            padding: 20px;
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .pba-profile-section h3 {
            margin: 0 0 14px;
            font-size: 22px;
        }

        .pba-profile-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pba-profile-table th,
        .pba-profile-table td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        .pba-profile-table th {
            width: 240px;
        }

        .pba-profile-input {
            width: 360px;
            max-width: 100%;
            padding: 8px 10px;
        }

        .pba-profile-actions {
            margin-top: 18px;
        }

        .pba-profile-btn {
            display: inline-block;
            padding: 10px 14px;
            border: 1px solid #0d3b66;
            border-radius: 4px;
            background: #0d3b66;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .pba-profile-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-profile-btn:hover {
            background: #0b3154;
            color: #fff;
        }

        .pba-profile-btn.secondary:hover {
            background: #f5f8fb;
            color: #0d3b66;
        }

        .pba-profile-muted {
            color: #666;
        }

        .pba-profile-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }

        .pba-profile-message.error {
            background: #f8e9e9;
        }
    </style>

    <div class="pba-profile-wrap">
        <!-- h2>My Profile</h2 -->
        <p>Update your basic account information below.</p>

        <?php echo $status_message; ?>

        <div class="pba-profile-section">
            <h3>Profile Information</h3>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pba_save_profile_action', 'pba_save_profile_nonce'); ?>
                <input type="hidden" name="action" value="pba_save_profile">

                <table class="pba-profile-table">
                    <tr>
                        <th><label for="first_name">First Name</label></th>
                        <td>
                            <input
                                class="pba-profile-input"
                                type="text"
                                name="first_name"
                                id="first_name"
                                value="<?php echo esc_attr($person['first_name'] ?? ''); ?>"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th><label for="last_name">Last Name</label></th>
                        <td>
                            <input
                                class="pba-profile-input"
                                type="text"
                                name="last_name"
                                id="last_name"
                                value="<?php echo esc_attr($person['last_name'] ?? ''); ?>"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_address">Email Address</label></th>
                        <td>
                            <input
                                class="pba-profile-input"
                                type="email"
                                name="email_address"
                                id="email_address"
                                value="<?php echo esc_attr($person['email_address'] ?? ''); ?>"
                            >
                        </td>
                    </tr>
                </table>

                <div class="pba-profile-actions">
                    <button type="submit" class="pba-profile-btn">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="pba-profile-section">
            <h3>Association Information</h3>

            <table class="pba-profile-table">
                <tr>
                    <th>Status</th>
                    <td><?php echo esc_html($person['status'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Email Verified</th>
                    <td><?php echo !empty($person['email_verified']) ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th>Household</th>
                    <td><?php echo esc_html($household_label !== '' ? $household_label : ''); ?></td>
                </tr>
                <tr>
                    <th>PBA Roles</th>
                    <td><?php echo esc_html(!empty($role_names) ? implode(', ', $role_names) : ''); ?></td>
                </tr>
                <tr>
                    <th>Committees</th>
                    <td><?php echo esc_html(!empty($committee_labels) ? implode(', ', $committee_labels) : ''); ?></td>
                </tr>
                <tr>
                    <th>Last Modified</th>
                    <td>
                        <?php
                        if (function_exists('pba_format_datetime_display')) {
                            echo esc_html(pba_format_datetime_display($person['last_modified_at'] ?? ''));
                        } else {
                            echo esc_html($person['last_modified_at'] ?? '');
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <p class="pba-profile-muted" style="margin-top:14px;">
                Contact an administrator to change household, roles, or committee assignments.
            </p>
        </div>

        <div class="pba-profile-section">
            <h3>Security</h3>
            <p>
                Password changes are managed through your WordPress account profile.
            </p>
            <p>
                <a class="pba-profile-btn secondary" href="<?php echo esc_url($password_url); ?>">Change Password</a>
            </p>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_handle_save_profile() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login/'));
        exit;
    }

    if (
        !isset($_POST['pba_save_profile_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pba_save_profile_nonce'])), 'pba_save_profile_action')
    ) {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'invalid_request', home_url('/profile/')));
        exit;
    }

    if (!function_exists('pba_get_current_person_record')) {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'save_failed', home_url('/profile/')));
        exit;
    }

    $person = pba_get_current_person_record();

    if (!$person || empty($person['person_id'])) {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'save_failed', home_url('/profile/')));
        exit;
    }

    $person_id = (int) $person['person_id'];
    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';

    if ($first_name === '' || $last_name === '') {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'invalid_request', home_url('/profile/')));
        exit;
    }

    $update_data = array(
        'first_name' => $first_name,
        'last_name'  => $last_name,
    );

    if ($email_address !== '') {
        $update_data['email_address'] = $email_address;
    }

    $updated = pba_supabase_update('Person', $update_data, array(
        'person_id' => 'eq.' . $person_id,
    ));

    if (is_wp_error($updated)) {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'save_failed', home_url('/profile/')));
        exit;
    }

    $user = wp_get_current_user();

    if ($user && !empty($user->ID)) {
        wp_update_user(array(
            'ID'         => $user->ID,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_email' => $email_address !== '' ? $email_address : $user->user_email,
        ));
    }

    wp_safe_redirect(add_query_arg('pba_profile_status', 'profile_saved', home_url('/profile/')));
    exit;
}
<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_change_password_shortcode');
add_action('admin_post_pba_change_password', 'pba_handle_change_password');

function pba_register_change_password_shortcode() {
    add_shortcode('pba_change_password', 'pba_render_change_password_shortcode');
}

function pba_render_change_password_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    $status = isset($_GET['pba_password_status']) ? sanitize_text_field(wp_unslash($_GET['pba_password_status'])) : '';
    $message_html = '';

    if ($status === 'password_changed') {
        $message_html = '<div class="pba-password-message">Your password has been updated successfully.</div>';
    } elseif ($status === 'invalid_request') {
        $message_html = '<div class="pba-password-message error">Invalid password change request.</div>';
    } elseif ($status === 'password_mismatch') {
        $message_html = '<div class="pba-password-message error">The new password fields do not match.</div>';
    } elseif ($status === 'password_too_short') {
        $message_html = '<div class="pba-password-message error">Your new password must be at least 12 characters long.</div>';
    } elseif ($status === 'wrong_current_password') {
        $message_html = '<div class="pba-password-message error">Your current password is incorrect.</div>';
    } elseif ($status === 'same_as_old') {
        $message_html = '<div class="pba-password-message error">Your new password must be different from your current password.</div>';
    } elseif ($status === 'save_failed') {
        $message_html = '<div class="pba-password-message error">Unable to change your password.</div>';
    }

    ob_start();
    ?>
    <style>
        .pba-change-password-wrap {
            max-width: 760px;
            margin: 0 auto;
        }

        .pba-change-password-card {
            margin: 0 0 24px;
            padding: 20px;
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .pba-change-password-card h2,
        .pba-change-password-card h3 {
            margin-top: 0;
        }

        .pba-change-password-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pba-change-password-table th,
        .pba-change-password-table td {
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #eee;
        }

        .pba-change-password-table th {
            width: 240px;
        }

        .pba-change-password-input {
            width: 360px;
            max-width: 100%;
            padding: 8px 10px;
        }

        .pba-change-password-actions {
            margin-top: 18px;
        }

        .pba-change-password-btn {
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

        .pba-change-password-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-change-password-btn:hover {
            background: #0b3154;
            color: #fff;
        }

        .pba-change-password-btn.secondary:hover {
            background: #f5f8fb;
            color: #0d3b66;
        }

        .pba-password-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 6px;
        }

        .pba-password-message.error {
            background: #f8e9e9;
        }

        .pba-change-password-help {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>

    <div class="pba-change-password-wrap">
        <div class="pba-change-password-card">
            <h2>Change Password</h2>
            <p>Use the form below to update your password.</p>

            <?php echo $message_html; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pba_change_password_action', 'pba_change_password_nonce'); ?>
                <input type="hidden" name="action" value="pba_change_password">

                <table class="pba-change-password-table">
                    <tr>
                        <th><label for="current_password">Current Password</label></th>
                        <td>
                            <input
                                class="pba-change-password-input"
                                type="password"
                                name="current_password"
                                id="current_password"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th><label for="new_password">New Password</label></th>
                        <td>
                            <input
                                class="pba-change-password-input"
                                type="password"
                                name="new_password"
                                id="new_password"
                                required
                            >
                            <div class="pba-change-password-help">
                                Use at least 12 characters.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="confirm_new_password">Confirm New Password</label></th>
                        <td>
                            <input
                                class="pba-change-password-input"
                                type="password"
                                name="confirm_new_password"
                                id="confirm_new_password"
                                required
                            >
                        </td>
                    </tr>
                </table>

                <div class="pba-change-password-actions">
                    <button type="submit" class="pba-change-password-btn">Update Password</button>
                    <a class="pba-change-password-btn secondary" href="<?php echo esc_url(home_url('/profile/')); ?>">Back to Profile</a>
                </div>
            </form>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_handle_change_password() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login/'));
        exit;
    }

    if (
        !isset($_POST['pba_change_password_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pba_change_password_nonce'])), 'pba_change_password_action')
    ) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'invalid_request', home_url('/change-password/')));
        exit;
    }

    $current_password = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
    $confirm_new_password = isset($_POST['confirm_new_password']) ? (string) wp_unslash($_POST['confirm_new_password']) : '';

    if ($new_password === '' || $confirm_new_password === '' || $current_password === '') {
        wp_safe_redirect(add_query_arg('pba_password_status', 'invalid_request', home_url('/change-password/')));
        exit;
    }

    if ($new_password !== $confirm_new_password) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'password_mismatch', home_url('/change-password/')));
        exit;
    }

    if (strlen($new_password) < 12) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'password_too_short', home_url('/change-password/')));
        exit;
    }

    if ($new_password === $current_password) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'same_as_old', home_url('/change-password/')));
        exit;
    }

    $user = wp_get_current_user();

    if (!$user || empty($user->ID)) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'save_failed', home_url('/change-password/')));
        exit;
    }

    $fresh_user = get_user_by('id', $user->ID);

    if (!$fresh_user || !wp_check_password($current_password, $fresh_user->user_pass, $fresh_user->ID)) {
        wp_safe_redirect(add_query_arg('pba_password_status', 'wrong_current_password', home_url('/change-password/')));
        exit;
    }

    wp_set_password($new_password, $fresh_user->ID);

    wp_set_current_user($fresh_user->ID);
    wp_set_auth_cookie($fresh_user->ID, true);

    wp_safe_redirect(add_query_arg('pba_password_status', 'password_changed', home_url('/change-password/')));
    exit;
}

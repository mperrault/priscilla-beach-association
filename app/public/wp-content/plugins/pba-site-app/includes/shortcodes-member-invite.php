<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'pba_logout_current_user_for_member_invite');

function pba_logout_current_user_for_member_invite() {
    if (!is_user_logged_in()) {
        return;
    }

    if (is_admin()) {
        return;
    }

    $invite_token = isset($_GET['invite_token']) ? sanitize_text_field(wp_unslash($_GET['invite_token'])) : '';

    if ($invite_token === '') {
        return;
    }

    if (!is_page('member-invite-accept')) {
        return;
    }

    wp_logout();

    wp_safe_redirect(
        add_query_arg(
            array(
                'invite_token' => rawurlencode($invite_token),
            ),
            home_url('/member-invite-accept/')
        )
    );
    exit;
}

add_shortcode('pba_member_invite_accept', 'pba_render_member_invite_accept');

function pba_render_member_invite_accept() {
    $invite_token = isset($_GET['invite_token']) ? sanitize_text_field(wp_unslash($_GET['invite_token'])) : '';
    $status       = isset($_GET['pba_invite_status']) ? sanitize_text_field(wp_unslash($_GET['pba_invite_status'])) : '';

    if ($invite_token === '') {
        return '<p>Invitation token is missing.</p>';
    }

    $invite_data = get_transient(pba_member_invite_token_key($invite_token));

    if (!is_array($invite_data) || empty($invite_data['person_id']) || empty($invite_data['email'])) {
        return '<p>This invitation is invalid or has expired.</p>';
    }

    $first_name = isset($invite_data['first_name']) ? $invite_data['first_name'] : '';
    $last_name  = isset($invite_data['last_name']) ? $invite_data['last_name'] : '';
    $email      = isset($invite_data['email']) ? $invite_data['email'] : '';

    $directory_visibility_level = isset($_GET['directory_visibility_level'])
        ? sanitize_text_field(wp_unslash($_GET['directory_visibility_level']))
        : 'hidden';

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        $directory_visibility_level = 'hidden';
    }

    ob_start();
    ?>
    <style>
        .pba-directory-pref-group {
            display: grid;
            gap: 12px;
            margin-top: 8px;
        }

        .pba-directory-pref-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-weight: 600;
            line-height: 1.4;
        }

        .pba-directory-pref-option input[type="radio"] {
            margin: 0;
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }

        .pba-directory-pref-help {
            margin: 8px 0 0;
            color: #666;
            font-size: 14px;
        }
    </style>

    <div class="pba-auth-wrap">
        <div class="pba-auth-card">
            <h1>New Member Login</h1>
            <p>Please confirm your details below, choose your directory preference, and create your password to complete your PBA site membership.</p>

            <?php if ($status === 'missing_password') : ?>
                <div class="pba-form-notice pba-form-notice-error">Please enter both password fields.</div>
            <?php elseif ($status === 'password_mismatch') : ?>
                <div class="pba-form-notice pba-form-notice-error">The password fields do not match.</div>
            <?php elseif ($status === 'password_too_short') : ?>
                <div class="pba-form-notice pba-form-notice-error">Password must be at least 8 characters long.</div>
            <?php elseif ($status === 'missing_directory_visibility') : ?>
                <div class="pba-form-notice pba-form-notice-error">Please choose your member directory preference.</div>
            <?php elseif ($status === 'invalid_directory_visibility') : ?>
                <div class="pba-form-notice pba-form-notice-error">Please choose a valid member directory preference.</div>
            <?php elseif ($status === 'user_exists') : ?>
                <div class="pba-form-notice pba-form-notice-error">An account already exists for that email address.</div>
            <?php elseif ($status === 'already_accepted') : ?>
                <div class="pba-form-notice pba-form-notice-error">This invitation has already been accepted.</div>
            <?php elseif ($status === 'person_update_failed') : ?>
                <div class="pba-form-notice pba-form-notice-error">We could not finish setting up your membership. Please contact the PBA Admin.</div>
            <?php elseif ($status !== '') : ?>
                <div class="pba-form-notice pba-form-notice-error"><?php echo esc_html(str_replace('_', ' ', $status)); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-auth-form">
                <?php wp_nonce_field('pba_member_invite_accept_action', 'pba_member_invite_accept_nonce'); ?>
                <input type="hidden" name="action" value="pba_accept_member_invite">
                <input type="hidden" name="invite_token" value="<?php echo esc_attr($invite_token); ?>">

                <div class="pba-form-row-inline">
                    <div class="pba-field-name">
                        <label for="pba-member-first-name">First Name</label>
                        <input id="pba-member-first-name" type="text" value="<?php echo esc_attr($first_name); ?>" readonly>
                    </div>

                    <div class="pba-field-name">
                        <label for="pba-member-last-name">Last Name</label>
                        <input id="pba-member-last-name" type="text" value="<?php echo esc_attr($last_name); ?>" readonly>
                    </div>
                </div>

                <div class="pba-form-row">
                    <div class="pba-field-email">
                        <label for="pba-member-email">Email Address</label>
                        <input id="pba-member-email" type="email" value="<?php echo esc_attr($email); ?>" readonly>
                    </div>
                </div>

                <div class="pba-form-row">
                    <div class="pba-field">
                        <label>Member Directory Preference</label>

                        <div class="pba-directory-pref-group">
                            <label class="pba-directory-pref-option">
                                <input type="radio" name="directory_visibility_level" value="hidden" <?php checked($directory_visibility_level, 'hidden'); ?>>
                                <span>Hide my information from the member directory</span>
                            </label>

                            <label class="pba-directory-pref-option">
                                <input type="radio" name="directory_visibility_level" value="name_only" <?php checked($directory_visibility_level, 'name_only'); ?>>
                                <span>Show only my name</span>
                            </label>

                            <label class="pba-directory-pref-option">
                                <input type="radio" name="directory_visibility_level" value="name_email" <?php checked($directory_visibility_level, 'name_email'); ?>>
                                <span>Show my name and email</span>
                            </label>
                        </div>

                        <p class="pba-directory-pref-help">
                            You can ask a PBA Admin to update this later if your preference changes.
                        </p>
                    </div>
                </div>

                <div class="pba-form-row-inline">
                    <div class="pba-field-login-password">
                        <label for="pba-invite-password">Password</label>
                        <input id="pba-invite-password" type="password" name="password" required>
                    </div>

                    <div class="pba-field-login-password">
                        <label for="pba-invite-password-verify">Confirm Password</label>
                        <input id="pba-invite-password-verify" type="password" name="password_verify" required>
                    </div>
                </div>

                <div class="pba-form-actions">
                    <button type="submit">Create My Member Account</button>
                </div>
            </form>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
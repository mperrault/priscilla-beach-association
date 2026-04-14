<?php
/**
 * Template Name: PBA Reset Password Page
 */

get_header();

$status      = isset($_GET['pba_reset_status']) ? sanitize_text_field(wp_unslash($_GET['pba_reset_status'])) : '';
$email_token = isset($_GET['email_token']) ? sanitize_text_field(wp_unslash($_GET['email_token'])) : '';
$email_value = isset($_GET['email']) ? sanitize_text_field(wp_unslash($_GET['email'])) : '';

$show_password_reset_form = false;
$reset_data = null;

if ($email_token !== '') {
    $reset_data = get_transient('pba_password_reset_' . $email_token);

    if (is_array($reset_data) && !empty($reset_data['email']) && !empty($reset_data['user_id'])) {
        $show_password_reset_form = true;
    }
}

$messages = array(
    'missing_email'        => 'Please enter your email address.',
    'invalid_email'        => 'Please enter a valid email address.',
    'email_not_found'      => 'We could not find an account with that email address.',
    'email_send_failed'    => 'We found your account, but could not send the reset email right now.',
    'check_email'          => 'If an account exists for that email address, a password reset link has been sent.',
    'invalid_token'        => 'This password reset link is invalid or has expired.',
    'missing_password'     => 'Please enter and verify your new password.',
    'password_mismatch'    => 'The password fields do not match.',
    'password_too_short'   => 'Password must be at least 8 characters.',
    'reset_failed'         => 'We could not reset your password right now.',
    'password_reset'       => 'Your password was reset successfully. You may now sign in.',
    'invalid_nonce'        => 'Security check failed. Please try again.',
);

$is_success_message = in_array($status, array('check_email', 'password_reset'), true);
?>

<main class="site-main">
  <div class="pba-auth-wrap">

    <?php if (isset($messages[$status])) : ?>
      <div class="pba-form-notice <?php echo $is_success_message ? 'pba-form-notice-success' : 'pba-form-notice-error'; ?>">
        <?php echo esc_html($messages[$status]); ?>
      </div>
    <?php endif; ?>

    <?php if ($show_password_reset_form) : ?>
      <section class="pba-auth-card">
        <h1>Reset Your Password</h1>
        <p>Enter your new password below.</p>

        <form class="pba-auth-form pba-password-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_reset_password_confirm">
          <input type="hidden" name="email_token" value="<?php echo esc_attr($email_token); ?>">
          <?php wp_nonce_field('pba_reset_password_confirm_action', 'pba_reset_password_confirm_nonce'); ?>

          <div class="pba-form-row pba-field-login-email">
            <label>Email Address</label>
            <input type="text" value="<?php echo esc_attr($reset_data['email']); ?>" readonly>
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-reset-password">New Password</label>
            <input
              type="password"
              id="pba-reset-password"
              name="password"
              maxlength="128"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-reset-password-verify">Verify New Password</label>
            <input
              type="password"
              id="pba-reset-password-verify"
              name="password_verify"
              maxlength="128"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="pba-form-actions">
            <button type="submit">Reset Password</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/login/')); ?>'">Cancel</button>
          </div>
        </form>
      </section>
    <?php else : ?>
      <section class="pba-auth-card">
        <h1>Forgot Your Password?</h1>
        <p>Enter your email address and we will send you a secure link to reset your password.</p>

        <form class="pba-auth-form pba-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_reset_password_request">
          <?php wp_nonce_field('pba_reset_password_request_action', 'pba_reset_password_request_nonce'); ?>

          <div class="pba-form-row pba-field-login-email">
            <label for="pba-reset-email">Email Address</label>
            <input
              type="email"
              id="pba-reset-email"
              name="email"
              maxlength="254"
              autocomplete="email"
              value="<?php echo esc_attr($email_value); ?>"
              required
            >
          </div>

          <div class="pba-form-actions">
            <button type="submit">Send Reset Link</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/login/')); ?>'">Back to Login</button>
          </div>
        </form>
      </section>
    <?php endif; ?>

  </div>
</main>

<?php get_footer(); ?>

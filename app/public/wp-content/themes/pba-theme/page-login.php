<?php
/**
 * Template Name: PBA Login Page
 */

get_header();

$status = isset($_GET['pba_register_status']) ? sanitize_text_field(wp_unslash($_GET['pba_register_status'])) : '';
$email_token = isset($_GET['email_token']) ? sanitize_text_field(wp_unslash($_GET['email_token'])) : '';

$show_password_setup = false;
$setup_data = null;

// Check if email_token exists in the URL to determine if we should show the password setup form
if ($email_token !== '') {
    $setup_data = get_transient('pba_house_admin_email_verify_' . $email_token);

    if (is_array($setup_data) && !empty($setup_data['email'])) {
        $show_password_setup = true; // Show the password setup form if the email token is valid
    }
}

$messages = array(
    'missing_fields'       => 'Please complete all required fields.',
    'invalid_email'        => 'Please enter a valid email address.',
    'invalid_house_number' => 'Please enter a valid house number.',
    'invalid_street'       => 'Please select a valid street name.',
    'invalid_nonce'        => 'Security check failed. Please try again.',
    'lookup_failed'        => 'We could not verify your House Admin record right now.',
    'no_match'             => 'We could not find a matching House Admin record for the information entered.',
    'user_exists'          => 'An account already exists for that email address.',
    'check_email'          => 'We found your House Admin record. Check your email for a secure link to finish setting up your account.',
    'email_send_failed'    => 'We verified your record, but could not send the email right now.',
    'invalid_token'        => 'This setup link is invalid or has expired.',
    'missing_password'     => 'Please enter and verify your password.',
    'password_mismatch'    => 'The password fields do not match.',
    'password_too_short'   => 'Password must be at least 8 characters.',
    'create_failed'        => 'We could not create your account right now.',
    'account_created'      => 'Your account was created successfully. You may now sign in.',
);

$is_success_message = ($status === 'account_created' || $status === 'check_email');
?>

<main class="site-main">
  <div class="pba-auth-wrap">

    <!-- Display success or error message -->
    <?php if (isset($messages[$status])): ?>
      <div class="pba-form-notice <?php echo $is_success_message ? 'pba-form-notice-success' : 'pba-form-notice-error'; ?>">
        <?php echo esc_html($messages[$status]); ?>
      </div>
    <?php endif; ?>

    <!-- Display the Set My Password form -->
    <?php if ($show_password_setup): ?>
      <section class="pba-auth-card">
        <h1>Set Your Password</h1>
        <p>Your House Admin record was verified. Create your password to finish setting up your account.</p>

        <form class="pba-auth-form pba-password-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_house_admin_create_user">
          <input type="hidden" name="email_token" value="<?php echo esc_attr($email_token); ?>">
          <?php wp_nonce_field('pba_house_admin_create_user_action', 'pba_house_admin_create_user_nonce'); ?>

          <div class="pba-form-row pba-field-login-email">
            <label>Email Address</label>
            <input type="text" value="<?php echo esc_attr($setup_data['email']); ?>" readonly>
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-password">Password</label>
            <input
              type="password"
              id="pba-password"
              name="password"
              maxlength="128"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-password-verify">Verify Password</label>
            <input
              type="password"
              id="pba-password-verify"
              name="password_verify"
              maxlength="128"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="pba-form-actions">
            <button type="submit">Create Account</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/login/')); ?>'">Cancel</button>
          </div>
        </form>
      </section>
    <?php else: ?>

      <!-- Login Form for Existing Users -->
      <section class="pba-auth-card">
        <h1>Member Login</h1>
        <p>Please sign in to access member-only content.</p>

        <form class="pba-auth-form pba-login-form" method="post" action="<?php echo esc_url(wp_login_url(home_url('/member-home/'))); ?>">
          <div class="pba-form-row pba-field-login-email">
            <label for="pba-login-email">Email Address</label>
            <input
              type="email"
              id="pba-login-email"
              name="log"
              maxlength="254"
              autocomplete="username"
              required
            >
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-login-password">Password</label>
            <input
              type="password"
              id="pba-login-password"
              name="pwd"
              maxlength="128"
              autocomplete="current-password"
              required
            >
          </div>

          <input type="hidden" name="redirect_to" value="">
          <div class="pba-form-actions">
            <button type="submit">Submit</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/')); ?>'">Cancel</button>
          </div>
        </form>

        <div class="pba-register-toggle">
          <p>Are you a <strong>PBA House Admin</strong> and need an account?</p>
          <p><a href="#" id="show-pba-register">Register here</a>.</p>
        </div>
      </section>

      <!-- House Admin Registration Form -->
      <section class="pba-auth-card pba-register-card" id="pba-register-section" style="display:none;">
        <h2>House Admin Registration</h2>
        <p>Please complete this form to request a new account.</p>

        <form class="pba-auth-form pba-register-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_house_admin_verify">
          <?php wp_nonce_field('pba_house_admin_verify_action', 'pba_house_admin_verify_nonce'); ?>

          <div class="pba-form-row pba-form-row-inline">
            <div class="pba-field-name">
              <label for="pba-first-name">First Name</label>
              <input
                type="text"
                id="pba-first-name"
                name="first_name"
                maxlength="50"
                autocomplete="given-name"
                required
              >
            </div>

            <div class="pba-field-name">
              <label for="pba-last-name">Last Name</label>
              <input
                type="text"
                id="pba-last-name"
                name="last_name"
                maxlength="50"
                autocomplete="family-name"
                required
              >
            </div>
          </div>

          <div class="pba-form-section-label">Property Address</div>

          <div class="pba-form-row pba-form-row-inline">
            <div class="pba-field-house-number">
              <label for="pba-house-number">House Number</label>
              <input
                type="text"
                id="pba-house-number"
                name="house_number"
                maxlength="10"
                required
              >
            </div>

            <div class="pba-field-street">
              <label for="pba-street-name">Street Name</label>
              <select
                id="pba-street-name"
                name="street_name"
                required
              >
                <option value="">Select a street name</option>
                <option value="Arlington Rd">Arlington Rd</option>
                <option value="Charlemont Rd">Charlemont Rd</option>
                <option value="Cochituate Rd">Cochituate Rd</option>
                <option value="Emerson Rd">Emerson Rd</option>
                <option value="Farmhurst Rd">Farmhurst Rd</option>
                <option value="John Alden Rd">John Alden Rd</option>
                <option value="Morse Rd">Morse Rd</option>
                <option value="Priscilla Beach Rd">Priscilla Beach Rd</option>
                <option value="Quaker Rd">Quaker Rd</option>
                <option value="Robbins Hill Rd">Robbins Hill Rd</option>
                <option value="Rocky Hill Rd">Rocky Hill Rd</option>
                <option value="Warrendale Rd">Warrendale Rd</option>
                <option value="Wellington Rd">Wellington Rd</option>
              </select>
            </div>
          </div>

          <div class="pba-form-row pba-field-email">
            <label for="pba-register-email">Email Address</label>
            <input
              type="email"
              id="pba-register-email"
              name="register_email"
              maxlength="254"
              autocomplete="email"
              required
            >
          </div>

          <div class="pba-form-help">
            We will verify your House Admin information and send a secure email link to finish setting up your account.
          </div>

          <div class="pba-form-actions">
            <button type="submit">Continue</button>
            <button type="button" id="cancel-pba-register">Cancel</button>
          </div>
        </form>
      </section>

    <?php endif; ?>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var showLink = document.getElementById('show-pba-register');
  var registerSection = document.getElementById('pba-register-section');
  var cancelButton = document.getElementById('cancel-pba-register');

  if (showLink && registerSection) {
    showLink.addEventListener('click', function (e) {
      e.preventDefault();
      registerSection.style.display = 'block';
      registerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (cancelButton && registerSection) {
    cancelButton.addEventListener('click', function () {
      registerSection.style.display = 'none';
    });
  }
});
</script>

<?php get_footer(); ?>
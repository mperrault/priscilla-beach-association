<?php
/**
 * Template Name: PBA Login Page
 */

get_header();

$status      = isset($_GET['pba_register_status']) ? sanitize_text_field(wp_unslash($_GET['pba_register_status'])) : '';
$email_token = isset($_GET['email_token']) ? sanitize_text_field(wp_unslash($_GET['email_token'])) : '';
$login_email = isset($_GET['login_email']) ? sanitize_text_field(wp_unslash($_GET['login_email'])) : '';

$session_expired = isset($_GET['session_expired']) && $_GET['session_expired'] === '1';
if ($session_expired && $status === '') {
    $status = 'session_expired';
}

$show_password_setup = false;
$setup_data = null;

if ($email_token !== '') {
    $setup_data = get_transient('pba_house_admin_email_verify_' . $email_token);

    if (is_array($setup_data) && !empty($setup_data['email'])) {
        $show_password_setup = true;
    }
}

$directory_visibility_options = array(
    'hidden'    => 'Hide from directory',
    'name_only' => 'Show name only',
    'name_email' => 'Show name and email',
);

$messages = array(
    'missing_fields'        => 'Please complete all required fields.',
    'invalid_email'         => 'Please enter a valid email address.',
    'invalid_house_number'  => 'Please enter a valid house number.',
    'invalid_street'        => 'Please select a valid street name.',
    'invalid_nonce'         => 'Security check failed. Please try again.',
    'lookup_failed'         => 'We could not verify your House Admin record right now.',
    'no_match'              => 'We could not find a match for the information entered.',
    'user_exists'           => 'An account already exists for that email address.',
    'check_email'           => 'We found your House Admin record. Check your email for a secure link to finish setting up your account.',
    'email_send_failed'     => 'We verified your record, but could not send the email right now.',
    'invalid_token'         => 'This setup link is invalid or has expired.',
    'missing_password'      => 'Please enter and verify your password.',
    'password_mismatch'     => 'The password fields do not match.',
    'password_too_short'    => 'Password must be at least 8 characters.',
    'create_failed'         => 'We could not create your account right now.',
    'account_created'       => 'Your account was created successfully. You may now sign in.',
    'login_failed'          => 'The email address or password you entered is incorrect.',
    'account_disabled'      => 'Your membership has been disabled. Please contact the PBA Admin.',
    'missing_login_fields'  => 'Please enter both email address and password.',
    'person_exists'         => 'We found an existing registration for this household and email address. Please contact the PBA Admin if you need assistance.',
    'session_expired'       => 'Your session expired. Please sign in again.',
    'password_reset'        => 'Your password was reset successfully. You may now sign in.',
    'invalid_directory_visibility' => 'Please select a valid directory visibility option.',
);

$is_success_message = in_array($status, array('account_created', 'check_email', 'password_reset'), true);
?>

<style>
  .pba-auth-wrap {
    max-width: 980px;
  }

  .pba-auth-card {
    padding-top: 8px;
  }

  .pba-auth-card h1 {
    margin-bottom: 8px;
  }

  .pba-auth-card > p {
    margin-top: 0;
    margin-bottom: 24px;
  }

  .pba-login-form .pba-form-row {
    margin-bottom: 18px;
  }

  .pba-login-form .pba-form-actions {
    margin-top: 12px;
  }

  .pba-form-help {
    margin-top: 14px !important;
  }

  .pba-directory-visibility-help {
    margin-top: 6px;
    color: #4b5563;
    font-size: 0.95rem;
    line-height: 1.4;
  }

  .pba-account-types {
    margin-top: 34px;
    padding-top: 26px;
    border-top: 1px solid #d9dde3;
  }

  .pba-account-types h2 {
    margin: 0 0 6px;
    font-size: clamp(1.55rem, 2.4vw, 2rem);
    line-height: 1.15;
  }

  .pba-account-types-intro {
    margin: 0 0 18px;
    color: #374151;
  }

  .pba-account-type-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
  }

  .pba-account-type-card {
    border: 1px solid #d9dde3;
    border-radius: 8px;
    padding: 24px;
    background: #fff;
  }

  .pba-account-type-icon {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    background: #dbeafe;
    color: #1d4fa3;
  }

  .pba-account-type-icon-member {
    background: #dcfce7;
    color: #166534;
    font-size: 28px;
  }

  .pba-account-type-card h3 {
    margin: 0 0 8px;
    font-size: 1.25rem;
    line-height: 1.25;
  }

  .pba-account-type-card p {
    margin: 0 0 12px;
  }

  .pba-account-type-note {
    color: #4b5563;
    font-size: 0.95rem;
  }

  .pba-account-type-button {
    margin-top: 6px;
  }

  @media (max-width: 760px) {
    .pba-account-type-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<main class="site-main">
  <div class="pba-auth-wrap">

    <?php if (isset($messages[$status])) : ?>
      <?php if ($is_success_message) : ?>
        <div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #34a853;border-radius:10px;background:#e6f4ea;color:#1e4620;" role="status" aria-live="polite">
          <div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#34a853;color:#fff;font-weight:700;flex:0 0 auto;">✓</div>
          <div><div style="font-weight:700;margin-bottom:2px;">Success</div><div><?php echo esc_html($messages[$status]); ?></div></div>
        </div>
      <?php else : ?>
        <div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #d93025;border-radius:10px;background:#fce8e6;color:#5f2120;" role="alert" aria-live="assertive">
          <div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#d93025;color:#fff;font-weight:700;flex:0 0 auto;">×</div>
          <div><div style="font-weight:700;margin-bottom:2px;">Please review</div><div><?php echo esc_html($messages[$status]); ?></div></div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($show_password_setup) : ?>
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
            <input type="password" id="pba-password" name="password" maxlength="128" autocomplete="new-password" required>
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-password-verify">Verify Password</label>
            <input type="password" id="pba-password-verify" name="password_verify" maxlength="128" autocomplete="new-password" required>
          </div>

          <div class="pba-form-row pba-field-directory-visibility">
            <label for="pba-directory-visibility">Directory Visibility</label>
            <select id="pba-directory-visibility" name="directory_visibility_level" required>
              <?php foreach ($directory_visibility_options as $visibility_value => $visibility_label) : ?>
                <option value="<?php echo esc_attr($visibility_value); ?>" <?php selected($visibility_value, 'hidden'); ?>>
                  <?php echo esc_html($visibility_label); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="pba-directory-visibility-help">
              Choose how your information should appear in the member directory. You can change this later from your profile.
            </div>
          </div>

          <div class="pba-form-actions">
            <button type="submit">Create Account</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/login/')); ?>'">Cancel</button>
          </div>
        </form>
      </section>
    <?php else : ?>
      <?php
      $logged_out = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
      ?>

      <?php if ($logged_out) : ?>
        <div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #34a853;border-radius:10px;background:#e6f4ea;color:#1e4620;" role="status" aria-live="polite">
          <div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#34a853;color:#fff;font-weight:700;flex:0 0 auto;">✓</div>
          <div><div style="font-weight:700;margin-bottom:2px;">Signed out</div><div>You have been logged out successfully.</div></div>
        </div>
      <?php endif; ?>

      <section class="pba-auth-card" id="pba-login-section">
        <h1>Log in</h1>
        <p>Log in to access your account.</p>

        <form class="pba-auth-form pba-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_member_login">
          <?php wp_nonce_field('pba_member_login_action', 'pba_member_login_nonce'); ?>

          <div class="pba-form-row pba-field-login-email">
            <label for="pba-login-email">Email Address</label>
            <input type="email" id="pba-login-email" name="log" maxlength="254" autocomplete="username" value="<?php echo esc_attr($login_email); ?>" required>
          </div>

          <div class="pba-form-row pba-field-login-password">
            <label for="pba-login-password">Password</label>
            <input type="password" id="pba-login-password" name="pwd" maxlength="128" autocomplete="current-password" required>
          </div>

          <div class="pba-form-actions">
            <button type="submit">Log in</button>
            <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/')); ?>'">Cancel</button>
          </div>

          <div class="pba-form-help">
            <a href="<?php echo esc_url(home_url('/reset-password/')); ?>">Forgot your password?</a>
          </div>
        </form>

        <div class="pba-account-types">
          <h2>Don’t have an account?</h2>
          <p class="pba-account-types-intro">Choose the option that describes you.</p>

          <div class="pba-account-type-grid">
            <div class="pba-account-type-card">
              <div class="pba-account-type-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M8 21.5L24 8L40 21.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M13 20V39H35V20" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M24 25.5C27.0376 25.5 29.5 23.0376 29.5 20C29.5 16.9624 27.0376 14.5 24 14.5C20.9624 14.5 18.5 16.9624 18.5 20C18.5 23.0376 20.9624 25.5 24 25.5Z" stroke="currentColor" stroke-width="3"/>
                  <path d="M16.5 35C17.4 30.8 20.1 28.5 24 28.5C27.9 28.5 30.6 30.8 31.5 35" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
              </div>

              <h3>PBA House Admin</h3>
              <p>Create your account to get started.</p>
              <button type="button" id="show-pba-register" class="pba-account-type-button">
                Register as a PBA House Admin
              </button>
            </div>

            <div class="pba-account-type-card">
              <div class="pba-account-type-icon pba-account-type-icon-member" aria-hidden="true">👥</div>
              <h3>House Member</h3>
              <p>Contact your PBA House Admin to request access.</p>
              <p class="pba-account-type-note">House Members are invited by their PBA House Admin.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="pba-auth-card pba-register-card" id="pba-register-section" style="display:none;">
        <h2>House Admin Registration</h2>
        <p>Please complete this form to request a new account.</p>

        <form class="pba-auth-form pba-register-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="pba_house_admin_verify">
          <?php wp_nonce_field('pba_house_admin_verify_action', 'pba_house_admin_verify_nonce'); ?>

          <div class="pba-form-row pba-form-row-inline">
            <div class="pba-field-name">
              <label for="pba-first-name">First Name</label>
              <input type="text" id="pba-first-name" name="first_name" maxlength="50" autocomplete="given-name" required>
            </div>

            <div class="pba-field-name">
              <label for="pba-last-name">Last Name</label>
              <input type="text" id="pba-last-name" name="last_name" maxlength="50" autocomplete="family-name" required>
            </div>
          </div>

          <div class="pba-form-section-label">Property Address</div>

          <div class="pba-form-row pba-form-row-inline">
            <div class="pba-field-house-number">
              <label for="pba-house-number">House Number</label>
              <input type="text" id="pba-house-number" name="house_number" maxlength="10" required>
            </div>

            <div class="pba-field-street">
              <label for="pba-street-name">Street Name</label>
              <select id="pba-street-name" name="street_name" required>
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
                <option value="Theatre Colony Way">Theatre Colony Way</option>
                <option value="Warrendale Rd">Warrendale Rd</option>
                <option value="Wellington Rd">Wellington Rd</option>
              </select>
            </div>
          </div>

          <div class="pba-form-row pba-field-email">
            <label for="pba-register-email">Email Address</label>
            <input type="email" id="pba-register-email" name="register_email" maxlength="254" autocomplete="email" required>
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
  var loginSection = document.getElementById('pba-login-section');

  if (showLink && registerSection) {
    showLink.addEventListener('click', function (e) {
      e.preventDefault();

      if (loginSection) {
        loginSection.style.display = 'none';
      }

      registerSection.style.display = 'block';
      registerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  if (cancelButton && registerSection) {
    cancelButton.addEventListener('click', function () {
      registerSection.style.display = 'none';

      if (loginSection) {
        loginSection.style.display = 'block';
      }
    });
  }
});
</script>

<?php get_footer(); ?>
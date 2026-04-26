<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_profile_shortcode');
add_action('admin_post_pba_save_profile', 'pba_handle_save_profile');

function pba_register_profile_shortcode() {
    add_shortcode('pba_profile', 'pba_render_profile_shortcode');
}

function pba_get_current_person_role_labels_for_display() {
    $role_slugs = function_exists('pba_get_current_person_role_names') ? pba_get_current_person_role_names() : array();

    if (empty($role_slugs) || !is_array($role_slugs)) {
        return array();
    }

    $role_defs = function_exists('pba_get_role_definitions')
        ? pba_get_role_definitions()
        : array();

    $labels = array();

    foreach ($role_slugs as $role_slug) {
        $role_slug = (string) $role_slug;

        if ($role_slug === '') {
            continue;
        }

        if (isset($role_defs[$role_slug]['label']) && $role_defs[$role_slug]['label'] !== '') {
            $labels[] = (string) $role_defs[$role_slug]['label'];
        } else {
            $labels[] = $role_slug;
        }
    }

    return array_values(array_unique($labels));
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
    $role_labels = pba_get_current_person_role_labels_for_display();

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
        $status_message = '<div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #34a853;border-radius:10px;background:#e6f4ea;color:#1e4620;">'
            . '<div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#34a853;color:#fff;font-weight:700;flex:0 0 auto;">✓</div>'
            . '<div><div style="font-weight:700;margin-bottom:2px;">Success</div><div>Profile updated successfully.</div></div>'
            . '</div>';
    } elseif ($status === 'save_failed') {
        $status_message = '<div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #d93025;border-radius:10px;background:#fce8e6;color:#5f2120;">'
            . '<div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#d93025;color:#fff;font-weight:700;flex:0 0 auto;">×</div>'
            . '<div><div style="font-weight:700;margin-bottom:2px;">Please review</div><div>Unable to save your profile.</div></div>'
            . '</div>';
    } elseif ($status === 'invalid_request') {
        $status_message = '<div style="display:flex;align-items:center;gap:14px;margin:18px 0 24px;padding:18px 22px;border:1px solid #d93025;border-radius:10px;background:#fce8e6;color:#5f2120;">'
            . '<div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#d93025;color:#fff;font-weight:700;flex:0 0 auto;">×</div>'
            . '<div><div style="font-weight:700;margin-bottom:2px;">Please review</div><div>Invalid profile update request.</div></div>'
            . '</div>';
    }
    $password_url = home_url('/change-password/');
    $status_label = trim((string) ($person['status'] ?? ''));
    $email_verified_label = !empty($person['email_verified']) ? 'Yes' : 'No';
    $role_count = count($role_names);
    $committee_count = count($committee_labels);
    $directory_visibility_level = isset($person['directory_visibility_level'])
        ? trim((string) $person['directory_visibility_level'])
        : 'hidden';

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        $directory_visibility_level = 'hidden';
    }

    $directory_visibility_label = 'Hidden';
    if ($directory_visibility_level === 'name_only') {
        $directory_visibility_label = 'Name only';
    } elseif ($directory_visibility_level === 'name_email') {
        $directory_visibility_label = 'Name and email';
    }

    ob_start();
    ?>
    <style>
        .pba-profile-wrap {
            max-width: 1200px;
            margin: 0 auto;
            color: #17324a;
        }

        .pba-profile-message {
            margin: 0 0 16px;
            padding: 12px 16px;
            background: #eef6ee;
            border-radius: 10px;
        }

        .pba-profile-message.error {
            background: #f8e9e9;
        }

        .pba-profile-hero {
            margin: 0 0 20px;
            padding: 24px;
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f9fc 100%);
            box-shadow: 0 8px 24px rgba(14, 46, 76, 0.06);
        }

        .pba-profile-hero-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .pba-profile-hero-title {
            margin: 0 0 8px;
            font-size: 30px;
            line-height: 1.15;
            font-weight: 700;
            color: #102a43;
        }

        .pba-profile-hero p {
            margin: 0;
            color: #4e6477;
            max-width: 760px;
        }

        .pba-profile-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .pba-profile-kpi {
            padding: 16px 18px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e3ebf3;
            box-shadow: 0 6px 18px rgba(14, 46, 76, 0.05);
        }

        .pba-profile-kpi-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #5f7386;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .pba-profile-kpi-value {
            font-size: 24px;
            line-height: 1.15;
            font-weight: 700;
            color: #102a43;
        }

        .pba-profile-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
        }

        .pba-profile-card {
            border: 1px solid #dde7f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(14, 46, 76, 0.05);
            overflow: hidden;
        }

        .pba-profile-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5edf5;
            background: #fbfdff;
        }

        .pba-profile-card-header h3 {
            margin: 0;
            font-size: 22px;
            line-height: 1.2;
            color: #102a43;
        }

        .pba-profile-card-header p {
            margin: 8px 0 0;
            color: #5f7386;
        }

        .pba-profile-card-body {
            padding: 20px;
        }

        .pba-profile-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pba-profile-table th,
        .pba-profile-table td {
            padding: 14px 8px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #edf2f7;
        }

        .pba-profile-table tr:last-child th,
        .pba-profile-table tr:last-child td {
            border-bottom: none;
        }

        .pba-profile-table th {
            width: 240px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #607487;
        }

        .pba-profile-input {
            width: 420px;
            max-width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #cdd9e5;
            border-radius: 12px;
            background: #ffffff;
            color: #17324a;
            box-sizing: border-box;
        }

        .pba-profile-actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pba-profile-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 16px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            line-height: 1.2;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .pba-profile-btn:hover {
            background: #0b3154;
            border-color: #0b3154;
            transform: translateY(-1px);
            color: #fff;
        }

        .pba-profile-btn[disabled] {
            opacity: 0.72;
            cursor: wait;
            transform: none;
        }

        .pba-profile-btn.secondary {
            background: #ffffff;
            color: #0d3b66;
            border: 1px solid #c9d8e6;
            border-radius: 999px;
            min-height: 38px;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(13, 59, 102, 0.04);
        }

        .pba-profile-btn.secondary:hover {
            background: #f3f8fc;
            color: #0b3154;
            border-color: #9fb8cd;
            box-shadow: 0 4px 12px rgba(13, 59, 102, 0.10);
            transform: translateY(-1px);
        }

        .pba-profile-btn.secondary:focus {
            outline: 2px solid #9fc2df;
            outline-offset: 2px;
        }

        .pba-profile-muted {
            color: #5f7386;
        }

        .pba-profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            background: #eef3f8;
            color: #21425c;
        }

        .pba-profile-badge.status-active,
        .pba-profile-badge.verified-yes {
            background: #eaf7ef;
            color: #21633f;
        }

        .pba-profile-badge.status-inactive,
        .pba-profile-badge.verified-no {
            background: #f7eee7;
            color: #8f4a1f;
        }

        .pba-profile-pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pba-profile-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eef4fa;
            color: #31536f;
            font-size: 12px;
            font-weight: 700;
        }

        .pba-profile-note {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #edf2f7;
            color: #5f7386;
        }

        body.pba-profile-submitting,
        html.pba-profile-submitting {
            cursor: wait !important;
        }

        body.pba-profile-submitting * {
            cursor: wait !important;
        }

        @media (max-width: 900px) {
            .pba-profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .pba-profile-hero {
                padding: 20px;
            }

            .pba-profile-hero-title {
                font-size: 26px;
            }

            .pba-profile-table,
            .pba-profile-table tbody,
            .pba-profile-table tr,
            .pba-profile-table th,
            .pba-profile-table td {
                display: block;
                width: 100%;
            }

            .pba-profile-table th {
                padding-bottom: 4px;
                border-bottom: none;
            }

            .pba-profile-table td {
                padding-top: 0;
            }
        }
    </style>

    <div class="pba-profile-wrap">
        <?php echo $status_message; ?>

        <div class="pba-profile-hero">
            <div class="pba-profile-hero-top">
                <div>
                    <p>Review and update your account details, and see your association information in one place.</p>
                </div>
            </div>

            <div class="pba-profile-kpis">
                <div class="pba-profile-kpi">
                    <span class="pba-profile-kpi-label">Status</span>
                    <span class="pba-profile-kpi-value"><?php echo esc_html($status_label !== '' ? $status_label : '—'); ?></span>
                </div>
                <div class="pba-profile-kpi">
                    <span class="pba-profile-kpi-label">Email Verified</span>
                    <span class="pba-profile-kpi-value"><?php echo esc_html($email_verified_label); ?></span>
                </div>
                <div class="pba-profile-kpi">
                    <span class="pba-profile-kpi-label">PBA Roles</span>
                    <span class="pba-profile-kpi-value"><?php echo esc_html(number_format_i18n($role_count)); ?></span>
                </div>
                <div class="pba-profile-kpi">
                    <span class="pba-profile-kpi-label">Committees</span>
                    <span class="pba-profile-kpi-value"><?php echo esc_html(number_format_i18n($committee_count)); ?></span>
                </div>
            </div>
        </div>

        <div class="pba-profile-grid">
            <div class="pba-profile-card">
                <div class="pba-profile-card-header">
                    <h3>Profile Information</h3>
                    <p>Update your basic account information below.</p>
                </div>

                <div class="pba-profile-card-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pba-profile-form">
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
                            <tr>
                                <th><label for="directory_visibility_level">Member Directory Visibility</label></th>
                                <td>
                                    <select
                                        class="pba-profile-input"
                                        name="directory_visibility_level"
                                        id="directory_visibility_level"
                                    >
                                        <option value="hidden" <?php selected($directory_visibility_level, 'hidden'); ?>>Hide from directory</option>
                                        <option value="name_only" <?php selected($directory_visibility_level, 'name_only'); ?>>Show name only</option>
                                        <option value="name_email" <?php selected($directory_visibility_level, 'name_email'); ?>>Show name and email</option>
                                    </select>
                                    <div class="pba-profile-muted" style="margin-top:8px;">
                                        Choose what other PBA members can see in the member directory.
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <div class="pba-profile-actions">
                            <button type="submit" class="pba-profile-btn" id="pba-profile-save-btn" data-processing-text="Saving...">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pba-profile-card">
                <div class="pba-profile-card-header">
                    <h3>Security</h3>
                    <p>Manage your password and account access.</p>
                </div>

                <div class="pba-profile-card-body">
                    <table class="pba-profile-table">
                        <tr>
                            <th>Password</th>
                            <td>Change your password by selecting the Change Password button below.</td>
                        </tr>
                    </table>

                    <div class="pba-profile-actions">
                        <a class="pba-profile-btn secondary" href="<?php echo esc_url($password_url); ?>">Change Password</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="pba-profile-card" style="margin-top: 20px;">
            <div class="pba-profile-card-header">
                <h3>Association Information</h3>
                <p>Your current membership, household, role, and committee details.</p>
            </div>

            <div class="pba-profile-card-body">
                <table class="pba-profile-table">
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php
                            $status_class = 'status-inactive';
                            if (strtolower($status_label) === 'active') {
                                $status_class = 'status-active';
                            }
                            ?>
                            <span class="pba-profile-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_label !== '' ? $status_label : '—'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Email Verified</th>
                        <td>
                            <span class="pba-profile-badge <?php echo !empty($person['email_verified']) ? 'verified-yes' : 'verified-no'; ?>">
                                <?php echo esc_html($email_verified_label); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Member Directory Visibility</th>
                        <td><?php echo esc_html($directory_visibility_label); ?></td>
                    </tr>
                    <tr>
                        <th>Household</th>
                        <td><?php echo esc_html($household_label !== '' ? $household_label : '—'); ?></td>
                    </tr>
                    <tr>
                        <th>PBA Roles</th>
                        <td>
                            <?php if (!empty($role_labels)) : ?>
                                <div class="pba-profile-pill-list">
                                    <?php foreach ($role_labels as $role_label) : ?>
                                        <span class="pba-profile-pill"><?php echo esc_html($role_label); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Committees</th>
                        <td>
                            <?php if (!empty($committee_labels)) : ?>
                                <div class="pba-profile-pill-list">
                                    <?php foreach ($committee_labels as $committee_label) : ?>
                                        <span class="pba-profile-pill"><?php echo esc_html($committee_label); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
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

                <div class="pba-profile-note">
                    Contact an administrator to change household, roles, or committee assignments.
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('pba-profile-form');
            var saveBtn = document.getElementById('pba-profile-save-btn');

            if (!form || !saveBtn) {
                return;
            }

            form.addEventListener('submit', function () {
                document.documentElement.classList.add('pba-profile-submitting');
                document.body.classList.add('pba-profile-submitting');

                saveBtn.disabled = true;

                if (!saveBtn.dataset.originalText) {
                    saveBtn.dataset.originalText = saveBtn.textContent;
                }

                saveBtn.textContent = saveBtn.getAttribute('data-processing-text') || 'Saving...';
            });
        })();
    </script>
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
    $directory_visibility_level = isset($_POST['directory_visibility_level'])
        ? sanitize_text_field(wp_unslash($_POST['directory_visibility_level']))
        : 'hidden';

    if ($first_name === '' || $last_name === '') {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'invalid_request', home_url('/profile/')));
        exit;
    }

    if (!in_array($directory_visibility_level, array('hidden', 'name_only', 'name_email'), true)) {
        wp_safe_redirect(add_query_arg('pba_profile_status', 'invalid_request', home_url('/profile/')));
        exit;
    }

    $update_data = array(
        'first_name'                 => $first_name,
        'last_name'                  => $last_name,
        'directory_visibility_level' => $directory_visibility_level,
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
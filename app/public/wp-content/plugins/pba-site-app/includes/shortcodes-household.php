<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_household_shortcode');

function pba_register_household_shortcode() {
    add_shortcode('pba_household_dashboard', 'pba_render_household_dashboard');
}

function pba_render_household_previous_invitations_table($rows, $title) {
    ob_start();
    ?>
    <div class="pba-household-section">
        <h3><?php echo esc_html($title); ?></h3>
        <table class="pba-household-table">
            <colgroup>
                <col>
                <col>
                <col>
                <col style="width: 130px;">
                <col style="width: 170px;">
                <col style="width: 170px;">
            </colgroup>
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Last Status Update</th>
                    <th class="pba-household-action-col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="6">None.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
                        $status = isset($row['status']) ? (string) $row['status'] : '';
                        $display_status = $status === 'Active' ? 'Accepted' : $status;
                        ?>
                        <tr>
                            <td><?php echo esc_html(isset($row['first_name']) ? $row['first_name'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['last_name']) ? $row['last_name'] : ''); ?></td>
                            <td><?php echo esc_html(isset($row['email_address']) ? $row['email_address'] : ''); ?></td>
                            <td><?php echo esc_html($display_status); ?></td>
                            <td><?php echo esc_html(pba_format_datetime_display(isset($row['last_modified_at']) ? $row['last_modified_at'] : '')); ?></td>
                            <td class="pba-household-action-col">
                                <?php if ($status === 'Active') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                        <?php wp_nonce_field('pba_household_disable_action', 'pba_household_disable_nonce'); ?>
                                        <input type="hidden" name="action" value="pba_household_disable_member">
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        <button type="submit" class="pba-household-btn secondary pba-household-action-btn" data-processing-text="Disabling...">Disable</button>
                                    </form>
                                <?php elseif ($status === 'Pending') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                        <?php wp_nonce_field('pba_household_cancel_action', 'pba_household_cancel_nonce'); ?>
                                        <input type="hidden" name="action" value="pba_household_cancel_invite">
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        <button type="submit" class="pba-household-btn secondary pba-household-action-btn" data-processing-text="Cancelling...">Cancel</button>
                                    </form>
                                <?php elseif ($status === 'Expired') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                        <?php wp_nonce_field('pba_household_resend_action', 'pba_household_resend_nonce'); ?>
                                        <input type="hidden" name="action" value="pba_household_resend_invite">
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        <button type="submit" class="pba-household-btn pba-household-action-btn" data-processing-text="Resending...">Resend</button>
                                    </form>
                                <?php elseif ($status === 'Disabled') : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-household-action-form">
                                        <?php wp_nonce_field('pba_household_enable_action', 'pba_household_enable_nonce'); ?>
                                        <input type="hidden" name="action" value="pba_household_enable_member">
                                        <input type="hidden" name="person_id" value="<?php echo esc_attr($person_id); ?>">
                                        <button type="submit" class="pba-household-btn pba-household-action-btn" data-processing-text="Enabling...">Enable</button>
                                    </form>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_household_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_user_has_house_admin_access()) {
        return '<p>You do not have permission to access this page.</p>';
    }

    $household_id      = (int) pba_get_current_household_id();
    $inviter_person_id = (int) pba_get_current_house_admin_person_id();

    if (empty($household_id) || empty($inviter_person_id)) {
        return '<p>Household context is missing for this account.</p>';
    }

    pba_update_pending_household_invites_to_expired($household_id, $inviter_person_id);

    $accepted_rows = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Active');
    $pending_rows  = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Pending');
    $expired_rows  = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Expired');
    $disabled_rows = pba_get_people_for_household_by_status($household_id, $inviter_person_id, 'Disabled');

    $previous_rows = array_merge(
        is_array($accepted_rows) ? $accepted_rows : array(),
        is_array($pending_rows) ? $pending_rows : array(),
        is_array($expired_rows) ? $expired_rows : array(),
        is_array($disabled_rows) ? $disabled_rows : array()
    );

    usort($previous_rows, function ($a, $b) {
        $a_id = isset($a['person_id']) ? (int) $a['person_id'] : 0;
        $b_id = isset($b['person_id']) ? (int) $b['person_id'] : 0;
        return $b_id <=> $a_id;
    });

    $status = isset($_GET['pba_household_status']) ? sanitize_text_field(wp_unslash($_GET['pba_household_status'])) : '';
    $duplicate_messages = get_transient('pba_household_duplicate_messages_' . get_current_user_id());

    if ($duplicate_messages !== false) {
        delete_transient('pba_household_duplicate_messages_' . get_current_user_id());
    } else {
        $duplicate_messages = array();
    }

    ob_start();
    ?>
    <style>
        .pba-household-wrap { max-width: 1100px; margin: 0 auto; }
        .pba-household-message { padding: 12px 16px; margin: 0 0 20px; border-radius: 6px; background: #eef6ee; }
        .pba-household-message.error { background: #f8e9e9; }
        .pba-household-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; table-layout: fixed; }
        .pba-household-table th, .pba-household-table td { border: 1px solid #d7d7d7; padding: 10px; text-align: left; vertical-align: middle; }
        .pba-household-table th { background: #f3f3f3; }
        .pba-household-section { margin: 28px 0; }
        .pba-household-actions { margin: 14px 0 22px; display: flex; gap: 10px; flex-wrap: wrap; }
        .pba-household-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #ffffff;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.3;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        .pba-household-btn:hover {
            background: #0b3154;
            border-color: #0b3154;
        }
        .pba-household-btn.secondary,
        .pba-household-btn.remove {
            background: #ffffff;
            color: #0d3b66;
            border-color: #0d3b66;
        }
        .pba-household-btn.secondary:hover,
        .pba-household-btn.remove:hover {
            background: #f3f7fb;
            color: #0d3b66;
            border-color: #0d3b66;
        }
        .pba-household-btn[disabled] {
            opacity: 0.7;
            cursor: wait;
        }
        .pba-household-action-col {
            width: 170px;
        }
        .pba-household-action-btn {
            min-width: 130px;
        }
        .pba-household-note { color: #555; margin-top: -8px; margin-bottom: 18px; }
        .pba-household-table .pba-household-field { max-width: 100%; min-width: 0; margin: 0; }
        .pba-household-table .pba-household-field input { width: 100%; max-width: 100%; }
        .pba-household-table .pba-field-name,
        .pba-household-table .pba-field-email { max-width: 100%; }
        .pba-household-form-error {
            display: none;
            margin: 0 0 18px;
            padding: 12px 14px;
            border-radius: 4px;
            background: #fff1f1;
            border: 1px solid #e2a3a3;
            color: #8a1f1f;
        }
        .pba-household-form-error.active { display: block; }
        .pba-household-duplicate-list {
            margin: 10px 0 0 18px;
            padding: 0;
        }
        body.pba-household-submitting,
        html.pba-household-submitting {
            cursor: wait !important;
        }
        body.pba-household-submitting * {
            cursor: wait !important;
        }
    </style>

    <div class="pba-household-wrap">
        <!-- h2>My Household</h2 -->
        <p class="pba-household-note">Invite household members to become site members.</p>

        <?php if ($status === 'account_created') : ?>
            <div class="pba-household-message">Your House Admin account has been created.</div>
        <?php elseif ($status === 'invite_created') : ?>
            <div class="pba-household-message">Invitation record(s) and email(s) created successfully.</div>
        <?php elseif ($status === 'invite_created_email_partial') : ?>
            <div class="pba-household-message error">Invitation record(s) were created, but at least one invitation email could not be sent.</div>
        <?php elseif ($status === 'invite_created_with_duplicates') : ?>
            <div class="pba-household-message error">
                Some invitations were created, but one or more people were already invited.
                <?php if (!empty($duplicate_messages)) : ?>
                    <ul class="pba-household-duplicate-list">
                        <?php foreach ($duplicate_messages as $msg) : ?>
                            <li><?php echo esc_html($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'invite_created_email_partial_with_duplicates') : ?>
            <div class="pba-household-message error">
                Some invitations were created, but there were duplicate invitees or email delivery failures.
                <?php if (!empty($duplicate_messages)) : ?>
                    <ul class="pba-household-duplicate-list">
                        <?php foreach ($duplicate_messages as $msg) : ?>
                            <li><?php echo esc_html($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'already_invited') : ?>
            <div class="pba-household-message error">
                No new invitations were created because the invitee(s) were already invited.
                <?php if (!empty($duplicate_messages)) : ?>
                    <ul class="pba-household-duplicate-list">
                        <?php foreach ($duplicate_messages as $msg) : ?>
                            <li><?php echo esc_html($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'no_invites_created') : ?>
            <div class="pba-household-message error">No invitation records were created.</div>
        <?php elseif ($status === 'invalid_invite_row') : ?>
            <div class="pba-household-message error">Please complete every field in each row, with no leading or trailing spaces.</div>
        <?php elseif ($status === 'invalid_invite_name') : ?>
            <div class="pba-household-message error">Please enter valid first and last names for all invite rows.</div>
        <?php elseif ($status === 'invalid_invite_email') : ?>
            <div class="pba-household-message error">Please enter a valid email address for all invite rows.</div>
        <?php elseif ($status === 'duplicate_invite_email') : ?>
            <div class="pba-household-message error">The same email address was entered more than once in the invite table.</div>
        <?php elseif ($status === 'member_disabled') : ?>
            <div class="pba-household-message">The member was disabled successfully.</div>
        <?php elseif ($status === 'member_enabled') : ?>
            <div class="pba-household-message">The member was enabled successfully.</div>
        <?php elseif ($status === 'invite_cancelled') : ?>
            <div class="pba-household-message">The pending invitation was cancelled successfully.</div>
        <?php elseif ($status === 'invite_resent') : ?>
            <div class="pba-household-message">The invitation was resent successfully.</div>
        <?php elseif ($status === 'disable_failed') : ?>
            <div class="pba-household-message error">We could not disable that member.</div>
        <?php elseif ($status === 'enable_failed') : ?>
            <div class="pba-household-message error">We could not enable that member.</div>
        <?php elseif ($status === 'cancel_failed') : ?>
            <div class="pba-household-message error">We could not cancel that invitation.</div>
        <?php elseif ($status === 'resend_failed') : ?>
            <div class="pba-household-message error">We could not resend that invitation.</div>
        <?php elseif ($status !== '') : ?>
            <div class="pba-household-message error"><?php echo esc_html(str_replace('_', ' ', $status)); ?></div>
        <?php endif; ?>

        <div class="pba-household-section">
            <h3>Invite Household Members</h3>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pba-household-invite-form" class="pba-auth-form" novalidate>
                <?php wp_nonce_field('pba_household_invite_action', 'pba_household_invite_nonce'); ?>
                <input type="hidden" name="action" value="pba_household_send_invites">

                <div id="pba-household-form-error" class="pba-household-form-error"></div>

                <table class="pba-household-table" id="pba-household-invite-table">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email Address</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="pba-household-field pba-field-name">
                                    <input type="text" name="invite_first_name[]" required>
                                </div>
                            </td>
                            <td>
                                <div class="pba-household-field pba-field-name">
                                    <input type="text" name="invite_last_name[]" required>
                                </div>
                            </td>
                            <td>
                                <div class="pba-household-field pba-field-email">
                                    <input type="email" name="invite_email[]" required>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="pba-household-btn remove pba-row-remove-btn">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="pba-household-actions">
                    <button type="button" class="pba-household-btn secondary" id="pba-household-add-row">Add More</button>
                    <button type="submit" class="pba-household-btn" id="pba-household-invite-all" data-processing-text="Inviting...">Invite All</button>
                </div>
            </form>
        </div>

        <?php echo pba_render_household_previous_invitations_table($previous_rows, 'Previous Invitations'); ?>
        <?php
            echo '<pre>';
            echo 'wp user id: ' . get_current_user_id() . PHP_EOL;
            echo 'pba_person_id: ' . get_user_meta(get_current_user_id(), 'pba_person_id', true) . PHP_EOL;
            echo 'pba_household_id: ' . get_user_meta(get_current_user_id(), 'pba_household_id', true) . PHP_EOL;
            echo '</pre>';
        ?>
    
    </div>

    <script>
        (function () {
            var form = document.getElementById('pba-household-invite-form');
            var addRowBtn = document.getElementById('pba-household-add-row');
            var tableBody = document.querySelector('#pba-household-invite-table tbody');
            var errorBox = document.getElementById('pba-household-form-error');
            var actionForms = document.querySelectorAll('.pba-household-action-form');

            if (!form || !addRowBtn || !tableBody || !errorBox) {
                return;
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function showError(message) {
                errorBox.innerHTML = escapeHtml(message);
                errorBox.classList.add('active');
            }

            function clearError() {
                errorBox.innerHTML = '';
                errorBox.classList.remove('active');
            }

            function createRow() {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><div class="pba-household-field pba-field-name"><input type="text" name="invite_first_name[]" required></div></td>' +
                    '<td><div class="pba-household-field pba-field-name"><input type="text" name="invite_last_name[]" required></div></td>' +
                    '<td><div class="pba-household-field pba-field-email"><input type="email" name="invite_email[]" required></div></td>' +
                    '<td><button type="button" class="pba-household-btn remove pba-row-remove-btn">Remove</button></td>';
                return tr;
            }

            function bindRemoveButtons() {
                var buttons = tableBody.querySelectorAll('.pba-row-remove-btn');
                buttons.forEach(function (btn) {
                    if (btn.dataset.bound === '1') {
                        return;
                    }

                    btn.dataset.bound = '1';
                    btn.addEventListener('click', function () {
                        var rows = tableBody.querySelectorAll('tr');
                        if (rows.length <= 1) {
                            var inputs = tableBody.querySelectorAll('input');
                            inputs.forEach(function (input) {
                                input.value = '';
                            });
                            clearError();
                            return;
                        }

                        var row = btn.closest('tr');
                        if (row) {
                            row.remove();
                            clearError();
                        }
                    });
                });
            }

            function isValidName(value) {
                if (value !== value.trim()) {
                    return false;
                }

                if (value.length < 1 || value.length > 50) {
                    return false;
                }

                return /^[A-Za-z][A-Za-z' -]*$/.test(value);
            }

            function isValidEmail(value) {
                if (value !== value.trim()) {
                    return false;
                }

                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            }

            function validateRows() {
                var rows = tableBody.querySelectorAll('tr');

                if (!rows.length) {
                    return 'Please add at least one household member.';
                }

                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var firstNameInput = row.querySelector('input[name="invite_first_name[]"]');
                    var lastNameInput = row.querySelector('input[name="invite_last_name[]"]');
                    var emailInput = row.querySelector('input[name="invite_email[]"]');

                    var firstName = firstNameInput ? firstNameInput.value : '';
                    var lastName = lastNameInput ? lastNameInput.value : '';
                    var email = emailInput ? emailInput.value : '';

                    if (!firstName || !lastName || !email) {
                        return 'Please complete all fields in every row before inviting.';
                    }

                    if (!isValidName(firstName)) {
                        return 'Please enter a valid first name in row ' + (i + 1) + '.';
                    }

                    if (!isValidName(lastName)) {
                        return 'Please enter a valid last name in row ' + (i + 1) + '.';
                    }

                    if (!isValidEmail(email)) {
                        return 'Please enter a valid email address in row ' + (i + 1) + '.';
                    }
                }

                return '';
            }

            function setSubmittingState(targetForm) {
                if (!targetForm) {
                    return;
                }

                document.documentElement.classList.add('pba-household-submitting');
                document.body.classList.add('pba-household-submitting');

                var buttons = targetForm.querySelectorAll('button, input[type="submit"]');
                buttons.forEach(function (btn) {
                    btn.disabled = true;

                    var processingText = btn.getAttribute('data-processing-text');
                    if (!processingText) {
                        processingText = 'Processing...';
                    }

                    if (btn.tagName === 'BUTTON') {
                        if (!btn.dataset.originalText) {
                            btn.dataset.originalText = btn.textContent;
                        }
                        btn.textContent = processingText;
                    } else if (btn.tagName === 'INPUT' && btn.type === 'submit') {
                        if (!btn.dataset.originalValue) {
                            btn.dataset.originalValue = btn.value;
                        }
                        btn.value = processingText;
                    }
                });
            }

            addRowBtn.addEventListener('click', function () {
                tableBody.appendChild(createRow());
                bindRemoveButtons();
                clearError();
            });

            form.addEventListener('submit', function (event) {
                clearError();

                var validationMessage = validateRows();
                if (validationMessage) {
                    event.preventDefault();
                    showError(validationMessage);
                    return;
                }

                setSubmittingState(form);
            });

            actionForms.forEach(function (actionForm) {
                actionForm.addEventListener('submit', function () {
                    setSubmittingState(actionForm);
                });
            });

            bindRemoveButtons();
        })();
    </script>
    <?php

    return ob_get_clean();
}
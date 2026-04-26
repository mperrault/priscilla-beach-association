<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_shared_list_ui_render_styles() {
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    ob_start();
    ?>
    <style>
        .pba-page-wrap {
            max-width: 1180px;
            margin: 0 auto;
            color: #16324f;
        }

        .pba-page-hero {
            margin: 0 0 24px;
            padding: 24px 28px;
            border: 1px solid #d8e2ee;
            border-radius: 14px;
            background: linear-gradient(135deg, #f7fbff 0%, #edf4fb 100%);
            box-shadow: 0 10px 24px rgba(13, 59, 102, 0.06);
        }

        .pba-page-eyebrow {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #426488;
        }

        .pba-page-title {
            margin: 0 0 8px;
            font-size: 32px;
            line-height: 1.15;
            color: #0d3b66;
        }

        .pba-page-intro {
            margin: 0;
            max-width: 760px;
            font-size: 16px;
            line-height: 1.6;
            color: #35506b;
        }

        .pba-message {
            margin: 0 0 22px;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid transparent;
        }

        .pba-message.success {
            background: #eef8f0;
            border-color: #b9dfc0;
            color: #245b2d;
        }

        .pba-message.error {
            background: #fff3f3;
            border-color: #e6b5b5;
            color: #8a1f1f;
        }

        .pba-message-title {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pba-message-body {
            font-size: 15px;
            line-height: 1.5;
        }

        .pba-duplicate-list {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .pba-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin: 0 0 28px;
        }

        .pba-summary-card {
            padding: 18px 18px 16px;
            border: 1px solid #d8e2ee;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(13, 59, 102, 0.05);
        }

        .pba-summary-label {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #5a7692;
        }

        .pba-summary-value {
            margin: 0;
            font-size: 34px;
            line-height: 1;
            font-weight: 700;
            color: #0d3b66;
        }

        .pba-summary-note {
            margin: 8px 0 0;
            font-size: 14px;
            color: #5b7188;
        }

        .pba-section {
            margin: 0 0 28px;
            padding: 24px 28px;
            border: 1px solid #d8e2ee;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(13, 59, 102, 0.05);
        }

        .pba-section-heading {
            margin: 0 0 18px;
        }

        .pba-section-heading h3 {
            margin: 0 0 6px;
            font-size: 24px;
            line-height: 1.2;
            color: #0d3b66;
        }

        .pba-section-subtitle {
            margin: 0;
            font-size: 15px;
            line-height: 1.55;
            color: #55708a;
        }

        .pba-callout {
            margin: 0 0 20px;
            padding: 14px 16px;
            border-left: 4px solid #0d3b66;
            border-radius: 8px;
            background: #f5f9fd;
            color: #35506b;
            font-size: 15px;
            line-height: 1.55;
        }

        .pba-callout strong {
            color: #0d3b66;
        }

        .pba-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .pba-table {
            width: 100%;
            min-width: 760px;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
            table-layout: fixed;
        }

        .pba-table th,
        .pba-table td {
            border-right: 1px solid #dbe5ef;
            border-bottom: 1px solid #dbe5ef;
            padding: 12px 12px;
            text-align: left;
            vertical-align: middle;
            background: #ffffff;
        }

        .pba-table th:first-child,
        .pba-table td:first-child {
            border-left: 1px solid #dbe5ef;
        }

        .pba-table thead th {
            background: #f4f8fc;
            color: #27486b;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pba-table thead tr:first-child th:first-child {
            border-top-left-radius: 10px;
        }

        .pba-table thead tr:first-child th:last-child {
            border-top-right-radius: 10px;
        }

        .pba-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        .pba-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .pba-table tbody tr:nth-child(even) td {
            background: #fbfdff;
        }

        .pba-field {
            max-width: 100%;
            min-width: 0;
            margin: 0;
        }

        .pba-field input,
        .pba-field select,
        .pba-field textarea {
            width: 100%;
            max-width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #c8d5e3;
            border-radius: 8px;
            background: #ffffff;
            color: #16324f;
            font-size: 14px;
            box-sizing: border-box;
        }

        .pba-field input:focus,
        .pba-field select:focus,
        .pba-field textarea:focus {
            outline: none;
            border-color: #0d3b66;
            box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.12);
        }

        .pba-email-cell {
            overflow-wrap: anywhere;
        }

        .pba-actions {
            margin: 18px 0 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .pba-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 16px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.3;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .pba-btn:hover,
        .pba-btn:focus {
            background: #0b3154;
            border-color: #0b3154;
            color: #ffffff;
            outline: none;
        }

        .pba-btn.secondary,
        .pba-btn.remove {
            background: #ffffff;
            color: #0d3b66;
            border-color: #0d3b66;
        }

        .pba-btn.secondary:hover,
        .pba-btn.secondary:focus,
        .pba-btn.remove:hover,
        .pba-btn.remove:focus {
            background: #f3f7fb;
            color: #0d3b66;
            border-color: #0d3b66;
            outline: none;
        }

        .pba-btn[disabled] {
            opacity: 0.7;
            cursor: wait;
        }

        .pba-action-col {
            width: 170px;
        }

        .pba-action-btn {
            min-width: 130px;
        }

        .pba-form-error {
            display: none;
            align-items: flex-start;
            gap: 14px;
            margin: 18px 0 24px;
            padding: 18px 22px;
            border: 1px solid #d93025;
            border-radius: 10px;
            background: #fce8e6;
            color: #5f2120;
        }

        .pba-form-error.active {
            display: flex;
        }

        .pba-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .pba-status-badge.accepted {
            background: #eaf7ec;
            color: #256c35;
        }

        .pba-status-badge.pending {
            background: #fff6df;
            color: #8a6500;
        }

        .pba-status-badge.expired {
            background: #f3f1ff;
            color: #5a46b6;
        }

        .pba-status-badge.disabled {
            background: #f0f2f5;
            color: #51606f;
        }

        .pba-status-badge.default {
            background: #eef3f8;
            color: #35506b;
        }

        .pba-home-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }
        .pba-home-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            margin: 0;
        }

        .pba-home-card-title {
            margin: 0 0 10px;
            font-size: 20px;
            line-height: 1.25;
            color: #17324a;
        }

        .pba-home-card-text {
            margin: 0 0 16px;
            color: #55708a;
            line-height: 1.55;
            flex: 1 1 auto;
        }

        .pba-home-card-actions {
            margin-top: auto;
            display: flex;
            justify-content: flex-start;
        }

        body.pba-submitting,
        html.pba-submitting {
            cursor: wait !important;
        }

        @media (max-width: 980px) {
            .pba-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .pba-page-hero,
            .pba-section {
                padding: 18px 18px;
            }

            .pba-page-title {
                font-size: 28px;
            }

            .pba-summary-grid {
                grid-template-columns: 1fr;
            }

            .pba-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .pba-actions .pba-btn {
                width: 100%;
            }

            .pba-home-card-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

function pba_shared_list_ui_render_household_script() {
    static $printed = false;

    if ($printed) {
        return '';
    }

    $printed = true;

    ob_start();
    ?>
    <script>
        (function () {
            function initHouseholdDashboard(root) {
                if (!root || root.dataset.pbaHouseholdInit === '1') {
                    return;
                }

                root.dataset.pbaHouseholdInit = '1';

                var form = root.querySelector('#pba-household-invite-form');
                var addRowBtn = root.querySelector('#pba-household-add-row');
                var tableBody = root.querySelector('#pba-household-invite-table tbody');
                var errorBox = root.querySelector('#pba-household-form-error');
                var actionForms = root.querySelectorAll('.pba-household-action-form');

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
                    errorBox.setAttribute('role', 'alert');
                    errorBox.setAttribute('aria-live', 'assertive');
                    errorBox.innerHTML =
                        '<div style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#d93025;color:#fff;font-weight:700;flex:0 0 auto;">×</div>' +
                        '<div><div style="font-weight:700;margin-bottom:2px;">Please review</div><div>' + escapeHtml(message) + '</div></div>';
                    errorBox.classList.add('active');
                }

                function clearError() {
                    errorBox.innerHTML = '';
                    errorBox.removeAttribute('role');
                    errorBox.removeAttribute('aria-live');
                    errorBox.classList.remove('active');
                }
                
                function createRow() {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><div class="pba-field"><input type="text" name="invite_first_name[]" required></div></td>' +
                        '<td><div class="pba-field"><input type="text" name="invite_last_name[]" required></div></td>' +
                        '<td><div class="pba-field"><input type="email" name="invite_email[]" required></div></td>' +
                        '<td><button type="button" class="pba-btn remove pba-row-remove-btn">Remove</button></td>';
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
                                var nextFocusTarget = row.nextElementSibling
                                    ? row.nextElementSibling.querySelector('input')
                                    : (row.previousElementSibling ? row.previousElementSibling.querySelector('input') : null);

                                row.remove();
                                clearError();

                                if (nextFocusTarget) {
                                    nextFocusTarget.focus();
                                }
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
                    var seenEmails = {};

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
                        var normalizedEmail = email.toLowerCase();

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

                        if (seenEmails[normalizedEmail]) {
                            return 'The same email address appears more than once in the invite table.';
                        }

                        seenEmails[normalizedEmail] = true;
                    }

                    return '';
                }

                function setSubmittingState(targetForm) {
                    if (!targetForm) {
                        return;
                    }

                    document.documentElement.classList.add('pba-submitting');
                    document.body.classList.add('pba-submitting');

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
                    var newRow = createRow();
                    tableBody.appendChild(newRow);
                    bindRemoveButtons();
                    clearError();

                    var firstInput = newRow.querySelector('input[name="invite_first_name[]"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
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
            }

            document.addEventListener('DOMContentLoaded', function () {
                var dashboards = document.querySelectorAll('.pba-household-wrap');
                dashboards.forEach(function (dashboard) {
                    initHouseholdDashboard(dashboard);
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}

function pba_shared_render_status_badge($label, $class) {
    ob_start();
    ?>
    <span class="pba-status-badge <?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></span>
    <?php
    return ob_get_clean();
}

function pba_shared_render_message($type, $title, $text, $list_items = array()) {
    $type = $type === 'error' ? 'error' : 'success';

    ob_start();
    ?>
    <div class="pba-message <?php echo esc_attr($type); ?>">
        <div class="pba-message-title"><?php echo esc_html($title); ?></div>
        <div class="pba-message-body"><?php echo esc_html($text); ?></div>

        <?php if (!empty($list_items)) : ?>
            <ul class="pba-duplicate-list">
                <?php foreach ($list_items as $item) : ?>
                    <li><?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function pba_shared_render_summary_card($label, $value, $note) {
    ob_start();
    ?>
    <div class="pba-summary-card">
        <div class="pba-summary-label"><?php echo esc_html($label); ?></div>
        <div class="pba-summary-value"><?php echo esc_html((string) $value); ?></div>
        <div class="pba-summary-note"><?php echo esc_html($note); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

function pba_shared_render_page_hero($eyebrow, $title, $intro) {
    ob_start();
    ?>
    <div class="pba-page-hero">
        <?php if ($eyebrow !== '') : ?>
            <div class="pba-page-eyebrow"><?php echo esc_html($eyebrow); ?></div>
        <?php endif; ?>
        <h2 class="pba-page-title"><?php echo esc_html($title); ?></h2>
        <?php if ($intro !== '') : ?>
            <p class="pba-page-intro"><?php echo esc_html($intro); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function pba_shared_render_section_heading($title, $subtitle = '') {
    ob_start();
    ?>
    <div class="pba-section-heading">
        <h3><?php echo esc_html($title); ?></h3>
        <?php if ($subtitle !== '') : ?>
            <p class="pba-section-subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function pba_shared_render_section_open($extra_classes = '') {
    $class = trim('pba-section ' . $extra_classes);
    return '<div class="' . esc_attr($class) . '">';
}

function pba_shared_render_section_close() {
    return '</div>';
}
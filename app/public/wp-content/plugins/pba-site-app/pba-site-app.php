<?php
/**
 * Plugin Name: PBA Site App
 * Description: Custom site application logic for PBA.
 * Version: 1.0.0
 * Author: PBA
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/theme-hooks.php';
require_once __DIR__ . '/includes/supabase-api.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/document-permissions.php';
require_once __DIR__ . '/includes/auth-handlers.php';
require_once __DIR__ . '/includes/auth-session-timeout.php';
require_once __DIR__ . '/includes/invite-handlers.php';
require_once __DIR__ . '/includes/shortcodes-member-invite.php';
require_once __DIR__ . '/includes/menuing.php';
require_once __DIR__ . '/includes/shared-list-ui.php';
require_once __DIR__ . '/includes/shortcodes-household.php';
require_once __DIR__ . '/includes/admin-menu.php';
require_once __DIR__ . '/includes/admin-members.php';
require_once __DIR__ . '/includes/admin-committees.php';
require_once __DIR__ . '/includes/document-permissions.php';
require_once __DIR__ . '/includes/document-folder-handlers.php';
require_once __DIR__ . '/includes/document-item-handlers.php';
require_once __DIR__ . '/includes/shortcodes-documents.php';
require_once __DIR__ . '/includes/shortcodes-member-home.php';
require_once __DIR__ . '/includes/shortcodes-members.php';
require_once __DIR__ . '/includes/shortcodes-committees.php';
require_once __DIR__ . '/includes/member-admin-handlers.php';
require_once __DIR__ . '/includes/committee-admin-handlers.php';
require_once __DIR__ . '/includes/member-admin-actions.php';
require_once __DIR__ . '/includes/shortcodes-member-directory.php';
require_once __DIR__ . '/includes/shortcodes-profile.php';
require_once __DIR__ . '/includes/shortcodes-change-password.php';
require_once __DIR__ . '/includes/shortcodes-governing-documents.php';
require_once __DIR__ . '/includes/shortcodes-meeting-info.php';
require_once __DIR__ . '/includes/shortcodes-households-admin.php';
require_once __DIR__ . '/includes/shortcodes-member-resources.php';
require_once __DIR__ . '/includes/pba-admin-list-ui.php';
require_once __DIR__ . '/includes/pba-admin-list-styles.php';
require_once __DIR__ . '/includes/user-link-sync.php';
require_once __DIR__ . '/includes/shortcodes-household-map.php';
require_once __DIR__ . '/includes/audit-log.php';
require_once __DIR__ . '/includes/shortcodes-audit-log.php';
require_once __DIR__ . '/includes/pba-background-jobs.php';
require_once __DIR__ . '/includes/document-viewer.php';
require_once __DIR__ . '/includes/document-storage.php';
require_once __DIR__ . '/includes/member-announcements.php';
require_once __DIR__ . '/includes/photo-functions.php';
require_once __DIR__ . '/includes/photo-image-processing.php';
require_once __DIR__ . '/includes/photo-storage.php';
require_once __DIR__ . '/includes/photo-audit.php';
require_once __DIR__ . '/includes/photo-upload-handlers.php';
require_once __DIR__ . '/includes/photo-admin-handlers.php';
require_once __DIR__ . '/includes/shortcodes-photos.php';
require_once __DIR__ . '/includes/shortcodes-photo-upload.php';
require_once __DIR__ . '/includes/shortcodes-photo-admin.php';
require_once __DIR__ . '/includes/massgis-household-currency.php';

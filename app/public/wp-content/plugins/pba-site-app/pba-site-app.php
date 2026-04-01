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
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/supabase-api.php';
require_once __DIR__ . '/includes/auth-handlers.php';
require_once __DIR__ . '/includes/invite-handlers.php';
require_once __DIR__ . '/includes/shortcodes-member-invite.php';
require_once __DIR__ . '/includes/menuing.php';
require_once __DIR__ . '/includes/shortcodes-household.php';
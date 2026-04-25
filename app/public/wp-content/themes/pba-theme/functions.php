<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'pba_theme_scripts');
add_action('after_setup_theme', 'pba_theme_setup');
add_action('wp_head', 'pba_add_favicon_tags');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (in_array($errno, array(E_DEPRECATED, E_USER_DEPRECATED), true)) {
        error_log('PHP DEPRECATED: ' . $errstr . ' in ' . $errfile . ':' . $errline);
        error_log(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
    }
    return false;
});

add_action('deprecated_function_run', function () {
    error_log("=== deprecated_function_run ===");
    error_log(print_r(wp_debug_backtrace_summary(), true));
});

error_log(
    'PBA TEST DEBUG LOG ENTRY | CONTEXT=' .
    ((defined('DOING_CRON') && DOING_CRON) ? 'cron' : 'web') .
    ' | URI=' .
    ($_SERVER['REQUEST_URI'] ?? 'unknown')
);

function pba_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    register_nav_menus(array(
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ));
}

function pba_theme_scripts() {
    wp_enqueue_style('pba-theme-style', get_stylesheet_uri(), array(), '1.0');
}

function pba_add_favicon_tags() {
    $favicon_url = get_stylesheet_directory_uri() . '/assets/images/favicon-pba.png';
    ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($favicon_url); ?>">
    <link rel="shortcut icon" href="<?php echo esc_url($favicon_url); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($favicon_url); ?>">
    <?php
}
add_action('wp_head', 'pba_output_favicon_links');
add_action('admin_head', 'pba_output_favicon_links');
add_action('login_head', 'pba_output_favicon_links');

function pba_output_favicon_links() {
    $favicon_url = content_url('uploads/pba-favicon.png');

    echo '<link rel="icon" type="image/png" href="' . esc_url($favicon_url) . '">' . "\n";
    echo '<link rel="shortcut icon" type="image/png" href="' . esc_url($favicon_url) . '">' . "\n";
}

register_deactivation_hook(__FILE__, 'pba_plugin_deactivate');

function pba_plugin_deactivate() {
    if (function_exists('pba_clear_background_jobs')) {
        pba_clear_background_jobs();
    }
}


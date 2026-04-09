<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'pba_theme_scripts');
add_action('after_setup_theme', 'pba_theme_setup');
add_action('wp_head', 'pba_add_favicon_tags');

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
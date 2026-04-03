<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'pba_register_admin_menu');

function pba_register_admin_menu() {
    add_menu_page(
        'PBA Admin',
        'PBA Admin',
        'manage_options',
        'pba-admin',
        'pba_render_admin_dashboard_page',
        'dashicons-admin-home',
        30
    );

    add_submenu_page(
        'pba-admin',
        'Members',
        'Members',
        'manage_options',
        'pba-admin-members',
        'pba_render_admin_members_page'
    );

    add_submenu_page(
        'pba-admin',
        'Committees',
        'Committees',
        'manage_options',
        'pba-admin-committees',
        'pba_render_admin_committees_page'
    );
}

function pba_render_admin_dashboard_page() {
    ?>
    <div class="wrap">
        <h1>PBA Admin</h1>
        <p>Use the menu on the left to manage Members and Committees.</p>
        <ul>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-members')); ?>">Manage Members</a></li>
            <li><a href="<?php echo esc_url(admin_url('admin.php?page=pba-admin-committees')); ?>">Manage Committees</a></li>
        </ul>
    </div>
    <?php
}
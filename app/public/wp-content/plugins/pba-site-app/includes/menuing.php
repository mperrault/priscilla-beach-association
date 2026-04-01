<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'pba_filter_nav_menu_items_by_role', 10, 2);

function pba_filter_nav_menu_items_by_role($items, $args) {
    $household_url = trailingslashit(home_url('/household/'));
    $can_see = pba_current_user_has_house_admin_access();

    foreach ($items as $index => $item) {
        $item_url = isset($item->url) ? trailingslashit($item->url) : '';
        $title    = isset($item->title) ? trim((string) $item->title) : '';

        if ($item_url === $household_url || strcasecmp($title, 'Household') === 0) {
            if (!$can_see) {
                unset($items[$index]);
            }
        }
    }

    return $items;
}

function pba_get_logged_in_menu_items() {
    $items = array(
        array(
            'label' => 'Home',
            'url'   => home_url('/'),
        ),
        array(
            'label' => 'Calendar',
            'url'   => home_url('/calendar/'),
        ),
    );

    if (is_user_logged_in()) {
        $items[] = array(
            'label' => 'Member Directory',
            'url'   => home_url('/member-directory/'),
        );
    }

    if (pba_user_has_role('pba_house_admin')) {
        $items[] = array(
            'label' => 'Household',
            'url'   => home_url('/household/'),
        );
    }

    if (pba_user_has_role('pba_board_member')) {
        $items[] = array(
            'label' => 'Board',
            'url'   => home_url('/board/'),
        );
    }

    if (pba_user_has_role('pba_committee_member')) {
        $items[] = array(
            'label' => 'Committee',
            'url'   => home_url('/committee/'),
        );
    }

    return $items;
}

function pba_user_has_role($role_slug) {
    if (!is_user_logged_in()) {
        return false;
    }

    $user = wp_get_current_user();

    return in_array($role_slug, (array) $user->roles, true);
}

function pba_render_logged_in_menu() {
    $items = pba_get_logged_in_menu_items();

    if (empty($items)) {
        return '';
    }

    $current_url = trailingslashit(home_url(add_query_arg(array(), $GLOBALS['wp']->request)));

    $html = '<ul class="pba-custom-menu">';

    foreach ($items as $item) {
        $item_url = trailingslashit($item['url']);
        $active_class = ($item_url === $current_url) ? ' class="current-menu-item"' : '';

        $html .= '<li' . $active_class . '>';
        $html .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
        $html .= '</li>';
    }

    $html .= '</ul>';

    return $html;
}

function pba_get_welcome_name() {
    if (!is_user_logged_in()) {
        return '';
    }

    $user = wp_get_current_user();

    if (!empty($user->first_name)) {
        return $user->first_name;
    }

    if (!empty($user->display_name)) {
        return $user->display_name;
    }

    return $user->user_login;
}
<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'pba_filter_nav_menu_items_by_role', 10, 2);

function pba_filter_nav_menu_items_by_role($items, $args) {
    $household_url = trailingslashit(home_url('/my-household/'));
    $board_docs_url = trailingslashit(home_url('/board-documents/'));
    $committee_docs_url = trailingslashit(home_url('/committee-documents/'));

    $can_see_household = pba_current_user_has_house_admin_access();
    $can_see_board = pba_current_person_has_role('PBABoardMember') || pba_current_person_has_role('PBAAdmin');
    $can_see_committee = pba_current_person_has_role('PBACommitteeMember') || pba_current_person_has_role('PBAAdmin');

    foreach ($items as $index => $item) {
        $item_url = isset($item->url) ? trailingslashit($item->url) : '';
        $title    = isset($item->title) ? trim((string) $item->title) : '';

        if ($item_url === $household_url || strcasecmp($title, 'Household') === 0 || strcasecmp($title, 'My Household') === 0) {
            if (!$can_see_household) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $board_docs_url || strcasecmp($title, 'Board') === 0 || strcasecmp($title, 'Board Documents') === 0) {
            if (!$can_see_board) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $committee_docs_url || strcasecmp($title, 'Committee') === 0 || strcasecmp($title, 'Committee Documents') === 0) {
            if (!$can_see_committee) {
                unset($items[$index]);
            }
            continue;
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
            'url'   => home_url('/eventscalendar/'),
        ),
    );

    if (is_user_logged_in()) {
        $items[] = array(
            'label' => 'Member Directory',
            'url'   => home_url('/member-directory/'),
        );
    }

    if (pba_current_user_has_house_admin_access()) {
        $items[] = array(
            'label' => 'My Household',
            'url'   => home_url('/my-household/'),
        );
    }

    if (pba_current_person_has_role('PBABoardMember') || pba_current_person_has_role('PBAAdmin')) {
        $items[] = array(
            'label' => 'Board Documents',
            'url'   => home_url('/board-documents/'),
        );
    }

    if (pba_current_person_has_role('PBACommitteeMember') || pba_current_person_has_role('PBAAdmin')) {
        $items[] = array(
            'label' => 'Committee Documents',
            'url'   => home_url('/committee-documents/'),
        );
    }

    if (pba_current_person_has_role('PBAAdmin')) {
        $items[] = array(
            'label' => 'Members',
            'url'   => home_url('/members/'),
        );
        $items[] = array(
            'label' => 'Committees',
            'url'   => home_url('/committees/'),
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
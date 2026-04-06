<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'pba_filter_nav_menu_items_by_role', 10, 2);

function pba_filter_nav_menu_items_by_role($items, $args) {
    $household_url = trailingslashit(home_url('/household/'));
    $households_url = trailingslashit(home_url('/households/'));
    $board_docs_url = trailingslashit(home_url('/board-documents/'));
    $committee_docs_url = trailingslashit(home_url('/committee-documents/'));
    $governing_docs_url = trailingslashit(home_url('/governing-documents/'));
    $members_url = trailingslashit(home_url('/members/'));
    $committees_url = trailingslashit(home_url('/committees/'));
    $profile_url = trailingslashit(home_url('/profile/'));

    $can_see_household = function_exists('pba_current_user_has_house_admin_access') && pba_current_user_has_house_admin_access();
    $can_see_board = is_user_logged_in() && current_user_can('pba_view_board_docs');
    $can_see_committee = is_user_logged_in() && current_user_can('pba_view_committee_docs');
    $can_see_governing = is_user_logged_in() && current_user_can('pba_view_governing_documents');
    $is_admin = is_user_logged_in() && current_user_can('pba_manage_roles');

    foreach ($items as $index => $item) {
        $item_url = isset($item->url) ? trailingslashit($item->url) : '';
        $title = isset($item->title) ? trim((string) $item->title) : '';

        if ($item_url === $profile_url || strcasecmp($title, 'Profile') === 0) {
            if (!is_user_logged_in()) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $household_url || strcasecmp($title, 'Household') === 0 || strcasecmp($title, 'My Household') === 0) {
            if (!$can_see_household) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $households_url || strcasecmp($title, 'Households') === 0) {
            if (!$is_admin) {
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

        if ($item_url === $governing_docs_url || strcasecmp($title, 'Governing Documents') === 0) {
            if (!$can_see_governing) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $members_url || strcasecmp($title, 'Members') === 0) {
            if (!$is_admin) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $committees_url || strcasecmp($title, 'Committees') === 0) {
            if (!$is_admin) {
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
            'url'   => home_url('/member-home/'),
        ),
        array(
            'label' => 'Calendar',
            'url'   => home_url('/calendar/'),
        ),
    );

    $can_see_household = function_exists('pba_current_user_has_house_admin_access') && pba_current_user_has_house_admin_access();
    $can_see_board = is_user_logged_in() && current_user_can('pba_view_board_docs');
    $can_see_committee = is_user_logged_in() && current_user_can('pba_view_committee_docs');
    $can_see_governing = is_user_logged_in() && current_user_can('pba_view_governing_documents');
    $is_admin = is_user_logged_in() && current_user_can('pba_manage_roles');

    if (is_user_logged_in()) {
        $items[] = array(
            'label' => 'Member Directory',
            'url'   => home_url('/member-directory/'),
        );
    }

    if ($can_see_governing) {
        $items[] = array(
            'label' => 'Governing Documents',
            'url'   => home_url('/governing-documents/'),
        );
    }

    if ($can_see_household) {
        $items[] = array(
            'label' => 'My Household',
            'url'   => home_url('/household/'),
        );
    }

    if ($can_see_board) {
        $items[] = array(
            'label' => 'Board Documents',
            'url'   => home_url('/board-documents/'),
        );
    }

    if ($can_see_committee) {
        $items[] = array(
            'label' => 'Committee Documents',
            'url'   => home_url('/committee-documents/'),
        );
    }

    if ($is_admin) {
        $items[] = array(
            'label' => 'Members',
            'url'   => home_url('/members/'),
        );

        $items[] = array(
            'label' => 'Households',
            'url'   => home_url('/households/'),
        );

        $items[] = array(
            'label' => 'Committees',
            'url'   => home_url('/committees/'),
        );
    }

    if (is_user_logged_in()) {
        $items[] = array(
            'label' => 'Profile',
            'url'   => home_url('/profile/'),
        );
    }

    return $items;
}

function pba_render_logged_in_menu() {
    $items = pba_get_logged_in_menu_items();

    if (empty($items)) {
        return '';
    }

    $request_path = isset($GLOBALS['wp']->request) ? $GLOBALS['wp']->request : '';
    $current_url = trailingslashit(home_url(add_query_arg(array(), $request_path)));

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
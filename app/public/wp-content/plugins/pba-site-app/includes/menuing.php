<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_nav_menu_objects', 'pba_filter_nav_menu_items_by_role', 10, 2);

function pba_get_menu_visibility_state() {
    static $state = null;

    if ($state !== null) {
        return $state;
    }

    $is_logged_in = is_user_logged_in();

    $state = array(
        'is_logged_in'             => $is_logged_in,
        'can_see_household'        => $is_logged_in && function_exists('pba_current_user_has_house_admin_access') && pba_current_user_has_house_admin_access(),
        'can_see_board'            => $is_logged_in && current_user_can('pba_view_board_docs'),
        'can_see_committee'        => $is_logged_in && current_user_can('pba_view_committee_docs'),
        'can_see_governing'        => $is_logged_in && current_user_can('pba_view_governing_documents'),
        'can_see_member_resources' => function_exists('pba_current_person_can_view_member_resources')
            ? pba_current_person_can_view_member_resources()
            : $is_logged_in,
        'is_admin'                 => $is_logged_in && current_user_can('pba_manage_roles'),
    );

    return $state;
}

function pba_filter_nav_menu_items_by_role($items, $args) {
    $household_url = trailingslashit(home_url('/household/'));
    $households_url = trailingslashit(home_url('/households/'));
    $board_docs_url = trailingslashit(home_url('/board-documents/'));
    $committee_docs_url = trailingslashit(home_url('/committee-documents/'));
    $governing_docs_url = trailingslashit(home_url('/governing-documents/'));
    $member_resources_url = trailingslashit(home_url('/member-resources/'));
    $members_url = trailingslashit(home_url('/members/'));
    $committees_url = trailingslashit(home_url('/committees/'));
    $audit_log_url = trailingslashit(home_url('/audit-log/'));
    $profile_url = trailingslashit(home_url('/profile/'));

    $state = pba_get_menu_visibility_state();

    foreach ($items as $index => $item) {
        $item_url = isset($item->url) ? trailingslashit($item->url) : '';
        $title = isset($item->title) ? trim((string) $item->title) : '';

        if ($item_url === $profile_url || strcasecmp($title, 'Profile') === 0) {
            if (!$state['is_logged_in']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $member_resources_url || strcasecmp($title, 'Member Resources') === 0) {
            if (!$state['can_see_member_resources']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $household_url || strcasecmp($title, 'Household') === 0 || strcasecmp($title, 'My Household') === 0) {
            if (!$state['can_see_household']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $households_url || strcasecmp($title, 'Households') === 0) {
            if (!$state['is_admin']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $board_docs_url || strcasecmp($title, 'Board') === 0 || strcasecmp($title, 'Board Documents') === 0) {
            if (!$state['can_see_board']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $committee_docs_url || strcasecmp($title, 'Committee') === 0 || strcasecmp($title, 'Committee Documents') === 0) {
            if (!$state['can_see_committee']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $governing_docs_url || strcasecmp($title, 'Governing Documents') === 0) {
            if (!$state['can_see_governing']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $members_url || strcasecmp($title, 'Members') === 0) {
            if (!$state['is_admin']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $committees_url || strcasecmp($title, 'Committees') === 0) {
            if (!$state['is_admin']) {
                unset($items[$index]);
            }
            continue;
        }

        if ($item_url === $audit_log_url || strcasecmp($title, 'Audit Log') === 0) {
            if (!$state['is_admin']) {
                unset($items[$index]);
            }
            continue;
        }
    }

    return $items;
}

function pba_get_logged_in_menu_items() {
    $state = pba_get_menu_visibility_state();

    $items = array(
        array(
            'label' => 'Home',
            'url'   => home_url('/member-home/'),
        ),
        array(
            'label' => 'Calendar',
            'url'   => home_url('/calendar/'),
        ),
        array(
            'label' => 'Directory',
            'url'   => home_url('/member-directory/'),
        ),
    );

    if ($state['can_see_household']) {
        $items[] = array(
            'label' => 'My Household',
            'url'   => home_url('/household/'),
        );
    }

    $documents_children = array();

    if ($state['can_see_governing']) {
        $documents_children[] = array(
            'label' => 'Governing Documents',
            'url'   => home_url('/governing-documents/'),
        );
    }

    if ($state['can_see_board']) {
        $documents_children[] = array(
            'label' => 'Board Documents',
            'url'   => home_url('/board-documents/'),
        );
    }

    if ($state['can_see_committee']) {
        $documents_children[] = array(
            'label' => 'Committee Documents',
            'url'   => home_url('/committee-documents/'),
        );
    }

    if ($state['can_see_member_resources']) {
        $documents_children[] = array(
            'label' => 'Member Resources',
            'url'   => home_url('/member-resources/'),
        );
    }

    if (!empty($documents_children)) {
        $items[] = array(
            'label'    => 'Documents',
            'url'      => '#',
            'children' => $documents_children,
        );
    }

    $admin_children = array();

    if ($state['is_admin']) {
        $admin_children[] = array(
            'label' => 'Members',
            'url'   => home_url('/members/'),
        );
        $admin_children[] = array(
            'label' => 'Households',
            'url'   => home_url('/households/'),
        );
        $admin_children[] = array(
            'label' => 'Committees',
            'url'   => home_url('/committees/'),
        );
        $admin_children[] = array(
            'label' => 'Audit Log',
            'url'   => home_url('/audit-log/'),
        );
    }

    if ($state['is_logged_in']) {
        $admin_children[] = array(
            'label' => 'Profile',
            'url'   => home_url('/profile/'),
        );
    }

    if (!empty($admin_children)) {
        $items[] = array(
            'label'    => 'Admin',
            'url'      => '#',
            'children' => $admin_children,
        );
    }

    return $items;
}

function pba_get_current_request_url() {
    static $current_url = null;

    if ($current_url !== null) {
        return $current_url;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $current_url = $request_uri !== ''
        ? trailingslashit(home_url($request_uri))
        : trailingslashit(home_url('/'));

    return $current_url;
}

function pba_flatten_menu_items_for_footer($items) {
    $flat_items = array();

    foreach ($items as $item) {
        if (!empty($item['children']) && is_array($item['children'])) {
            foreach ($item['children'] as $child) {
                $flat_items[] = $child;
            }
            continue;
        }

        $flat_items[] = $item;
    }

    return $flat_items;
}

function pba_render_logged_in_menu($context = 'header') {
    $items = pba_get_logged_in_menu_items();

    if (empty($items)) {
        return '';
    }

    $context = ($context === 'footer') ? 'footer' : 'header';
    $current_url = pba_get_current_request_url();

    if ($context === 'footer') {
        $flat_items = pba_flatten_menu_items_for_footer($items);
        $html = '<ul class="pba-custom-menu pba-footer-menu pba-footer-menu-flat">';

        foreach ($flat_items as $item) {
            $item_url = isset($item['url']) ? $item['url'] : '#';
            $item_url_compare = trailingslashit($item_url);
            $class_attr = ($item_url_compare === $current_url) ? ' class="current-menu-item"' : '';

            $html .= '<li' . $class_attr . '>';
            $html .= '<a href="' . esc_url($item_url) . '">' . esc_html($item['label']) . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    $html = '<ul class="pba-custom-menu pba-portal-menu">';

    foreach ($items as $item) {
        $has_children = !empty($item['children']) && is_array($item['children']);
        $item_url = isset($item['url']) ? $item['url'] : '#';
        $item_url_compare = $has_children ? '' : trailingslashit($item_url);
        $is_active = (!$has_children && $item_url_compare === $current_url);
        $child_active = false;

        if ($has_children) {
            foreach ($item['children'] as $child) {
                $child_url_compare = trailingslashit($child['url']);
                if ($child_url_compare === $current_url) {
                    $child_active = true;
                    break;
                }
            }
        }

        $classes = array();

        if ($is_active || $child_active) {
            $classes[] = 'current-menu-item';
        }

        if ($has_children) {
            $classes[] = 'menu-item-has-children';
        }

        $class_attr = empty($classes) ? '' : ' class="' . esc_attr(implode(' ', $classes)) . '"';

        $html .= '<li' . $class_attr . '>';

        if ($has_children) {
            $dropdown_id = 'pba-submenu-' . sanitize_title($item['label']);

            $html .= '<button class="pba-menu-dropdown-toggle" type="button" aria-expanded="false" aria-haspopup="true" aria-controls="' . esc_attr($dropdown_id) . '">';
            $html .= '<span>' . esc_html($item['label']) . '</span>';
            $html .= '<span class="pba-menu-caret" aria-hidden="true">▾</span>';
            $html .= '</button>';

            $html .= '<ul class="sub-menu" id="' . esc_attr($dropdown_id) . '">';

            foreach ($item['children'] as $child) {
                $child_url_compare = trailingslashit($child['url']);
                $child_class = ($child_url_compare === $current_url) ? ' class="current-menu-item"' : '';

                $html .= '<li' . $child_class . '>';
                $html .= '<a href="' . esc_url($child['url']) . '">' . esc_html($child['label']) . '</a>';
                $html .= '</li>';
            }

            $html .= '</ul>';
        } else {
            $html .= '<a href="' . esc_url($item_url) . '">' . esc_html($item['label']) . '</a>';
        }

        $html .= '</li>';
    }

    $html .= '</ul>';

    static $script_rendered = false;

    if (!$script_rendered) {
        $script_rendered = true;
        $html .= "<script>
(function () {
    function closeAllMenus(menu) {
        menu.querySelectorAll('li.menu-item-has-children.is-open').forEach(function (item) {
            item.classList.remove('is-open');
            var toggle = item.querySelector('.pba-menu-dropdown-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initPortalMenu(menu) {
        if (!menu || menu.dataset.pbaMenuInit === '1') {
            return;
        }

        menu.dataset.pbaMenuInit = '1';

        menu.addEventListener('click', function (event) {
            var toggle = event.target.closest('.pba-menu-dropdown-toggle');

            if (!toggle || !menu.contains(toggle)) {
                return;
            }

            event.preventDefault();

            var parent = toggle.closest('li.menu-item-has-children');
            var isOpen = parent.classList.contains('is-open');

            closeAllMenus(menu);

            if (!isOpen) {
                parent.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                closeAllMenus(menu);
            }
        });
    }

    function initAllPortalMenus() {
        document.querySelectorAll('.pba-portal-menu').forEach(initPortalMenu);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.pba-portal-menu').forEach(closeAllMenus);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllPortalMenus);
    } else {
        initAllPortalMenus();
    }
})();
</script>";
    }

    return $html;
}
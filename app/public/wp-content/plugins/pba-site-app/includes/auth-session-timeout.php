<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PBA_SESSION_IDLE_TIMEOUT')) {
    define('PBA_SESSION_IDLE_TIMEOUT', 30 * MINUTE_IN_SECONDS);
}

if (!defined('PBA_SESSION_NON_REMEMBER_TIMEOUT')) {
    define('PBA_SESSION_NON_REMEMBER_TIMEOUT', 60 * MINUTE_IN_SECONDS);
}

if (!defined('PBA_SESSION_REMEMBER_TIMEOUT')) {
    define('PBA_SESSION_REMEMBER_TIMEOUT', 12 * HOUR_IN_SECONDS);
}

add_action('wp_login', function ($user_login, $user) {
    if ($user instanceof WP_User) {
        update_user_meta($user->ID, 'pba_last_activity', time());
    }
}, 10, 2);

if (!function_exists('pba_get_login_page_url')) {
    function pba_get_login_page_url() {
        $page = get_page_by_path('login');

        if ($page instanceof WP_Post) {
            return get_permalink($page);
        }

        return wp_login_url();
    }
}

if (!function_exists('pba_is_session_timeout_exempt_request')) {
    function pba_is_session_timeout_exempt_request() {
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        return false;
    }
}

if (!function_exists('pba_get_current_request_uri')) {
    function pba_get_current_request_uri() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return '';
        }

        return (string) wp_unslash($_SERVER['REQUEST_URI']);
    }
}

if (!function_exists('pba_is_login_request')) {
    function pba_is_login_request() {
        $request_uri = pba_get_current_request_uri();

        if ($request_uri === '') {
            return false;
        }

        if (stripos($request_uri, 'wp-login.php') !== false) {
            return true;
        }

        $login_url = pba_get_login_page_url();
        $login_path = wp_parse_url($login_url, PHP_URL_PATH);
        $request_path = wp_parse_url(home_url($request_uri), PHP_URL_PATH);

        if (!$login_path || !$request_path) {
            return false;
        }

        return untrailingslashit($request_path) === untrailingslashit($login_path);
    }
}

if (!function_exists('pba_auth_cookie_expiration')) {
    function pba_auth_cookie_expiration($length, $user_id, $remember) {
        if ($remember) {
            return PBA_SESSION_REMEMBER_TIMEOUT;
        }

        return PBA_SESSION_NON_REMEMBER_TIMEOUT;
    }
}
add_filter('auth_cookie_expiration', 'pba_auth_cookie_expiration', 10, 3);

if (!function_exists('pba_enforce_idle_session_timeout')) {
    function pba_enforce_idle_session_timeout() {
        if (!is_user_logged_in()) {
            return;
        }

        if (pba_is_session_timeout_exempt_request()) {
            return;
        }

        if (is_admin()) {
            return;
        }

        if (pba_is_login_request()) {
            return;
        }

        $user_id = get_current_user_id();

        if ($user_id < 1) {
            return;
        }

        $meta_key = 'pba_last_activity';
        $now = time();
        $last_activity = (int) get_user_meta($user_id, $meta_key, true);

        if ($last_activity > 0 && ($now - $last_activity) > PBA_SESSION_IDLE_TIMEOUT) {
            delete_user_meta($user_id, $meta_key);
            wp_logout();

            $redirect_url = add_query_arg(
                'session_expired',
                '1',
                pba_get_login_page_url()
            );

            wp_safe_redirect($redirect_url);
            exit;
        }

        update_user_meta($user_id, $meta_key, $now);
    }
}
add_action('init', 'pba_enforce_idle_session_timeout', 1);

if (!function_exists('pba_clear_last_activity_on_logout')) {
    function pba_clear_last_activity_on_logout() {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            delete_user_meta($user_id, 'pba_last_activity');
        }
    }
}
add_action('wp_logout', 'pba_clear_last_activity_on_logout');

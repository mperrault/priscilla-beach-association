<?php
/*
Plugin Name: PBA Custom Roles
Description: Creates custom WordPress roles for the Priscilla Beach Association site and maps Supabase roles to WordPress roles.
Version: 1.0.0
Author: PBA
*/

if (!defined('ABSPATH')) {
    exit;
}

final class PBA_Custom_Roles {
    const VERSION = '1.0.0';
    const OPTION_VERSION = 'pba_custom_roles_version';

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('init', [__CLASS__, 'maybe_upgrade_roles']);
    }

    /**
     * WordPress role definitions.
     *
     * Key = WP role slug
     * label = human-readable label in WP Admin
     * capabilities = capabilities granted to that role
     */
    public static function get_role_definitions() {
        return [
            'pba_anonymous' => [
                'label' => 'Anonymous',
                'capabilities' => [
                    'read' => false,
                ],
            ],

            'pba_member' => [
                'label' => 'PBA Member',
                'capabilities' => [
                    'read'         => true,
                    'upload_files' => false,
                    'edit_posts'   => false,
                    'edit_pages'   => false,
                    'publish_posts'=> false,
                    'publish_pages'=> false,
                ],
            ],

            'pba_house_admin' => [
                'label' => 'House Admin',
                'capabilities' => [
                    'read'                   => true,
                    'pba_view_household_page' => true,
                    'pba_send_invites_through_household_page' => true,
                    //'upload_files'           => true,

                    // Posts
                    //'edit_posts'             => true,
                    //'edit_published_posts'   => true,
                    //'publish_posts'          => true,
                    //'delete_posts'           => true,
                    //'delete_published_posts' => true,

                    // Pages
                    //'edit_pages'             => true,
                    //'edit_published_pages'   => true,
                    //'publish_pages'          => true,
                    //'delete_pages'           => true,
                    //'delete_published_pages' => true,

                    // Administrative restrictions
                    /*
                    'list_users'             => false,
                    'create_users'           => false,
                    'delete_users'           => false,
                    'promote_users'          => false,
                    'edit_users'             => false,
                    'manage_options'         => false,
                    'install_plugins'        => false,
                    'activate_plugins'       => false,
                    'delete_plugins'         => false,
                    'install_themes'         => false,
                    'switch_themes'          => false,
                    'edit_theme_options'     => false,
                    */
                ],
            ],

            'pba_board_member' => [
                'label' => 'PBA Board Member',
                'capabilities' => [
                    'read'                   => true,
                    'upload_files'           => true,
                    'edit_posts'             => true,
                    'edit_published_posts'   => true,
                    'publish_posts'          => true,
                    'delete_posts'           => false,
                    'delete_published_posts' => false,
                    'edit_pages'             => false,
                    'publish_pages'          => false,
                ],
            ],

            'pba_committee_member' => [
                'label' => 'PBA Committee Member',
                'capabilities' => [
                    'read'                   => true,
                    'upload_files'           => true,
                    'edit_posts'             => true,
                    'edit_published_posts'   => true,
                    'publish_posts'          => false,
                    'delete_posts'           => false,
                    'delete_published_posts' => false,
                    'edit_pages'             => false,
                    'publish_pages'          => false,
                ],
            ],
        ];
    }

    /**
     * Exact mapping from Supabase role_name to WP role slug.
     *
     * Left side must exactly match Supabase role_name.
     */
    public static function get_supabase_to_wp_role_map() {
        return [
            'Anonymous'         => 'pba_anonymous',
            'PBAMember'         => 'pba_member',
            'PBAHouseholdAdmin' => 'pba_house_admin',
            'PBABoardMember'    => 'pba_board_member',
            'PBACommiteeMember' => 'pba_committee_member', // matches Supabase spelling exactly
        ];
    }

    public static function activate() {
        self::create_or_update_roles();
        update_option(self::OPTION_VERSION, self::VERSION);
    }

    public static function maybe_upgrade_roles() {
        $installed_version = get_option(self::OPTION_VERSION);
        if ($installed_version !== self::VERSION) {
            self::create_or_update_roles();
            update_option(self::OPTION_VERSION, self::VERSION);
        }
    }

    public static function create_or_update_roles() {
        $definitions = self::get_role_definitions();

        foreach ($definitions as $role_slug => $config) {
            $label = $config['label'];
            $caps  = $config['capabilities'];

            if (!get_role($role_slug)) {
                add_role($role_slug, $label, $caps);
            }

            $role = get_role($role_slug);
            if (!$role) {
                continue;
            }

            $all_known_caps = self::get_all_known_capabilities($definitions);

            foreach ($all_known_caps as $cap) {
                $role->remove_cap($cap);
            }

            foreach ($caps as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    private static function get_all_known_capabilities($definitions) {
        $all_caps = [];

        foreach ($definitions as $config) {
            if (!empty($config['capabilities']) && is_array($config['capabilities'])) {
                $all_caps = array_merge($all_caps, array_keys($config['capabilities']));
            }
        }

        return array_unique($all_caps);
    }

    /**
     * Translate a Supabase role_name into the corresponding WP role slug.
     */
    public static function map_supabase_role_to_wp_role($supabase_role_name) {
        $map = self::get_supabase_to_wp_role_map();
        return isset($map[$supabase_role_name]) ? $map[$supabase_role_name] : null;
    }

    /**
     * Assign a WP role to a user from a Supabase role_name.
     *
     * WARNING:
     * set_role() replaces the user's existing role.
     * If you need multiple simultaneous roles, this should be changed.
     */
    public static function sync_user_role_from_supabase($user_id, $supabase_role_name) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $wp_role = self::map_supabase_role_to_wp_role($supabase_role_name);
        if (!$wp_role) {
            return false;
        }

        $user->set_role($wp_role);
        return true;
    }
}

PBA_Custom_Roles::init();

/**
 * Helper wrapper function:
 * pba_sync_user_role_from_supabase($user_id, $supabase_role_name);
 */
function pba_sync_user_role_from_supabase($user_id, $supabase_role_name) {
    return PBA_Custom_Roles::sync_user_role_from_supabase($user_id, $supabase_role_name);
}

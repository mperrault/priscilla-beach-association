<?php
/*
Plugin Name: PBA Custom Roles
Description: Creates custom WordPress roles for the Priscilla Beach Association site and maps Supabase roles to WordPress roles.
Version: 1.2.0
Author: PBA
*/

if (!defined('ABSPATH')) {
    exit;
}

final class PBA_Custom_Roles {
    const VERSION = '1.2.0';
    const OPTION_VERSION = 'pba_custom_roles_version';

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('init', [__CLASS__, 'maybe_upgrade_roles']);
    }

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
                    'read'                    => true,
                    'upload_files'            => false,
                    'edit_posts'              => false,
                    'edit_pages'              => false,
                    'publish_posts'           => false,
                    'publish_pages'           => false,
                    'pba_view_member_pages'   => true,
                ],
            ],

            'pba_house_admin' => [
                'label' => 'House Admin',
                'capabilities' => [
                    'read'                                    => true,
                    'upload_files'                            => false,
                    'edit_posts'                              => false,
                    'edit_pages'                              => false,
                    'publish_posts'                           => false,
                    'publish_pages'                           => false,
                    'pba_view_member_pages'                   => true,
                    'pba_view_household_page'                 => true,
                    'pba_send_invites_through_household_page' => true,
                    'pba_manage_own_household'                => true,
                ],
            ],

            'pba_board_member' => [
                'label' => 'PBA Board Member',
                'capabilities' => [
                    'read'                        => true,
                    'upload_files'                => false,
                    'edit_posts'                  => false,
                    'edit_published_posts'        => false,
                    'publish_posts'               => false,
                    'delete_posts'                => false,
                    'delete_published_posts'      => false,
                    'edit_pages'                  => false,
                    'publish_pages'               => false,
                    'pba_view_member_pages'       => true,
                    'pba_view_board_materials'    => true,
                    'pba_manage_board_folders'    => true,
                    'pba_upload_board_documents'  => true,
                ],
            ],

            'pba_committee_member' => [
                'label' => 'PBA Committee Member',
                'capabilities' => [
                    'read'                             => true,
                    'upload_files'                     => false,
                    'edit_posts'                       => false,
                    'edit_published_posts'             => false,
                    'publish_posts'                    => false,
                    'delete_posts'                     => false,
                    'delete_published_posts'           => false,
                    'edit_pages'                       => false,
                    'publish_pages'                    => false,
                    'pba_view_member_pages'            => true,
                    'pba_view_committee_materials'     => true,
                    'pba_manage_committee_folders'     => true,
                    'pba_upload_committee_documents'   => true,
                ],
            ],

            'pba_admin' => [
                'label' => 'PBA Admin',
                'capabilities' => [
                    'read'                                     => true,
                    'upload_files'                             => false,
                    'edit_posts'                               => false,
                    'edit_pages'                               => false,
                    'publish_posts'                            => false,
                    'publish_pages'                            => false,
                    'pba_view_member_pages'                    => true,
                    'pba_view_household_page'                  => true,
                    'pba_send_invites_through_household_page'  => true,
                    'pba_manage_own_household'                 => true,
                    'pba_view_board_materials'                 => true,
                    'pba_view_committee_materials'             => true,
                    'pba_manage_board_folders'                 => true,
                    'pba_upload_board_documents'               => true,
                    'pba_manage_committee_folders'             => true,
                    'pba_upload_committee_documents'           => true,
                    'pba_manage_members'                       => true,
                    'pba_manage_committees'                    => true,
                    'pba_manage_households'                    => true,
                    'pba_view_admin_navigation'                => true,
                ],
            ],
        ];
    }

    public static function get_supabase_to_wp_role_map() {
        return [
            'Anonymous'          => 'pba_anonymous',
            'PBAMember'          => 'pba_member',
            'PBAHouseholdAdmin'  => 'pba_house_admin',
            'PBABoardMember'     => 'pba_board_member',
            'PBACommitteeMember' => 'pba_committee_member',
            'PBACommiteeMember'  => 'pba_committee_member',
            'PBAAdmin'           => 'pba_admin',
        ];
    }

    public static function get_wp_role_priority_map() {
        return [
            'pba_anonymous'        => 10,
            'pba_member'           => 20,
            'pba_house_admin'      => 30,
            'pba_committee_member' => 40,
            'pba_board_member'     => 50,
            'pba_admin'            => 60,
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

    public static function map_supabase_role_to_wp_role($supabase_role_name) {
        $map = self::get_supabase_to_wp_role_map();
        return isset($map[$supabase_role_name]) ? $map[$supabase_role_name] : null;
    }

    public static function map_supabase_roles_to_wp_roles($supabase_role_names) {
        $supabase_role_names = is_array($supabase_role_names) ? $supabase_role_names : array($supabase_role_names);
        $mapped = [];

        foreach ($supabase_role_names as $supabase_role_name) {
            $wp_role = self::map_supabase_role_to_wp_role($supabase_role_name);
            if ($wp_role) {
                $mapped[] = $wp_role;
            }
        }

        return array_values(array_unique($mapped));
    }

    public static function choose_highest_priority_wp_role($wp_roles) {
        $wp_roles = is_array($wp_roles) ? $wp_roles : array($wp_roles);
        $priority_map = self::get_wp_role_priority_map();

        $best_role = null;
        $best_priority = -1;

        foreach ($wp_roles as $wp_role) {
            if (!isset($priority_map[$wp_role])) {
                continue;
            }

            $priority = (int) $priority_map[$wp_role];

            if ($priority > $best_priority) {
                $best_priority = $priority;
                $best_role = $wp_role;
            }
        }

        return $best_role;
    }

    public static function sync_user_role_from_supabase($user_id, $supabase_role_name) {
        return self::sync_user_roles_from_supabase($user_id, array($supabase_role_name));
    }

    public static function sync_user_roles_from_supabase($user_id, $supabase_role_names) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $wp_roles = self::map_supabase_roles_to_wp_roles($supabase_role_names);
        $chosen_wp_role = self::choose_highest_priority_wp_role($wp_roles);

        if (!$chosen_wp_role) {
            return false;
        }

        $user->set_role($chosen_wp_role);
        return $chosen_wp_role;
    }
}

PBA_Custom_Roles::init();

function pba_sync_user_role_from_supabase($user_id, $supabase_role_name) {
    return PBA_Custom_Roles::sync_user_role_from_supabase($user_id, $supabase_role_name);
}

function pba_sync_user_roles_from_supabase($user_id, $supabase_role_names) {
    return PBA_Custom_Roles::sync_user_roles_from_supabase($user_id, $supabase_role_names);
}
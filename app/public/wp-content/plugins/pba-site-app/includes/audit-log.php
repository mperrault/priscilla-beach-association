<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pba_get_audit_request_id')) {
    function pba_get_audit_request_id() {
        static $request_id = null;

        if ($request_id !== null) {
            return $request_id;
        }

        if (function_exists('wp_generate_uuid4')) {
            $request_id = wp_generate_uuid4();
        } else {
            $request_id = md5(uniqid('pba_audit_', true));
        }

        return $request_id;
    }
}

if (!function_exists('pba_audit_normalize_json_value')) {
    function pba_audit_normalize_json_value($value, $default = array()) {
        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(wp_json_encode($value), true);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return $default;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return $decoded;
                }

                return array(
                    'value' => $decoded,
                );
            }

            return array(
                'value' => $trimmed,
            );
        }

        return array(
            'value' => $value,
        );
    }
}

if (!function_exists('pba_audit_json_encode_or_null')) {
    function pba_audit_json_encode_or_null($value) {
        if ($value === null) {
            return null;
        }

        return wp_json_encode(pba_audit_normalize_json_value($value, array()));
    }
}

if (!function_exists('pba_audit_get_actor_context')) {
    function pba_audit_get_actor_context() {
        $actor_person_id = null;
        $actor_roles = array();
        $actor_email = null;
        $actor_wp_user_id = null;

        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();

            if ($wp_user instanceof WP_User) {
                $actor_wp_user_id = (int) $wp_user->ID;
                $actor_email = !empty($wp_user->user_email) ? (string) $wp_user->user_email : null;
            }

            if (function_exists('pba_current_person_id')) {
                $current_person_id = (int) pba_current_person_id();
                if ($current_person_id > 0) {
                    $actor_person_id = $current_person_id;
                }
            }

            if ($actor_person_id === null && $actor_wp_user_id !== null && $actor_wp_user_id > 0) {
                $person_id_meta = get_user_meta($actor_wp_user_id, 'pba_person_id', true);
                if ($person_id_meta !== '') {
                    $person_id_from_meta = (int) $person_id_meta;
                    if ($person_id_from_meta > 0) {
                        $actor_person_id = $person_id_from_meta;
                    }
                }
            }

            if (function_exists('pba_get_current_person_role_names')) {
                $role_names = pba_get_current_person_role_names();
                if (is_array($role_names)) {
                    $actor_roles = array_values(array_unique(array_map('strval', $role_names)));
                }
            } elseif ($wp_user instanceof WP_User && !empty($wp_user->roles) && is_array($wp_user->roles)) {
                $actor_roles = array_values(array_unique(array_map('strval', $wp_user->roles)));
            }
        }

        return array(
            'actor_person_id' => $actor_person_id,
            'actor_wp_user_id' => $actor_wp_user_id,
            'actor_email_address' => $actor_email,
            'actor_role_names' => $actor_roles,
        );
    }
}

if (!function_exists('pba_audit_get_ip_address')) {
    function pba_audit_get_ip_address() {
        $candidates = array();

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', (string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach ($forwarded as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim((string) wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('pba_audit_get_request_context')) {
    function pba_audit_get_request_context() {
        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';

        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        return array(
            'request_id' => pba_get_audit_request_id(),
            'ip_address' => pba_audit_get_ip_address(),
            'user_agent' => $user_agent !== '' ? $user_agent : null,
            'request_method' => $request_method !== '' ? $request_method : null,
            'request_uri' => $request_uri !== '' ? $request_uri : null,
        );
    }
}

if (!function_exists('pba_audit_error_log')) {
    function pba_audit_error_log($message, $context = array()) {
        $payload = '[PBA Audit] ' . $message;

        if (!empty($context)) {
            $payload .= ' | ' . wp_json_encode($context);
        }

        error_log($payload);
    }
}

if (!function_exists('pba_audit_build_payload')) {
    function pba_audit_build_payload($action_type, $entity_type, $entity_id = null, $args = array()) {
        $defaults = array(
            'entity_label' => null,
            'target_person_id' => null,
            'target_household_id' => null,
            'target_committee_id' => null,
            'target_document_folder_id' => null,
            'target_document_item_id' => null,
            'result_status' => 'success',
            'summary' => '',
            'before' => null,
            'after' => null,
            'details' => array(),
        );

        $args = wp_parse_args($args, $defaults);

        $actor = pba_audit_get_actor_context();
        $request = pba_audit_get_request_context();

        $allowed_result_statuses = array('success', 'failure', 'denied');
        $result_status = sanitize_text_field((string) $args['result_status']);

        if (!in_array($result_status, $allowed_result_statuses, true)) {
            $result_status = 'success';
        }

        return array(
            'request_id' => (string) $request['request_id'],
            'actor_person_id' => $actor['actor_person_id'],
            'actor_wp_user_id' => $actor['actor_wp_user_id'],
            'actor_email_address' => $actor['actor_email_address'],
            'actor_role_names' => wp_json_encode((array) $actor['actor_role_names']),

            'action_type' => sanitize_text_field((string) $action_type),
            'entity_type' => sanitize_text_field((string) $entity_type),
            'entity_id' => $entity_id !== null ? (int) $entity_id : null,
            'entity_label' => $args['entity_label'] !== null ? sanitize_text_field((string) $args['entity_label']) : null,

            'target_person_id' => $args['target_person_id'] !== null ? (int) $args['target_person_id'] : null,
            'target_household_id' => $args['target_household_id'] !== null ? (int) $args['target_household_id'] : null,
            'target_committee_id' => $args['target_committee_id'] !== null ? (int) $args['target_committee_id'] : null,
            'target_document_folder_id' => $args['target_document_folder_id'] !== null ? (int) $args['target_document_folder_id'] : null,
            'target_document_item_id' => $args['target_document_item_id'] !== null ? (int) $args['target_document_item_id'] : null,

            'result_status' => $result_status,
            'summary' => sanitize_textarea_field((string) $args['summary']),

            'before_json' => pba_audit_json_encode_or_null($args['before']),
            'after_json' => pba_audit_json_encode_or_null($args['after']),
            'details_json' => wp_json_encode(pba_audit_normalize_json_value($args['details'], array())),

            'ip_address' => $request['ip_address'],
            'user_agent' => $request['user_agent'],
            'request_method' => $request['request_method'],
            'request_uri' => $request['request_uri'],
        );
    }
}

if (!function_exists('pba_audit_log')) {
    function pba_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_supabase_insert')) {
            pba_audit_error_log('Audit insert skipped because pba_supabase_insert() is unavailable.', array(
                'action_type' => $action_type,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
            ));
            return false;
        }

        $payload = pba_audit_build_payload($action_type, $entity_type, $entity_id, $args);
        $result = pba_supabase_insert('AuditLog', $payload);

        if (is_wp_error($result)) {
            pba_audit_error_log('Audit insert failed.', array(
                'action_type' => $action_type,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'error_code' => $result->get_error_code(),
                'error_message' => $result->get_error_message(),
            ));
            return false;
        }

        return $result;
    }
}
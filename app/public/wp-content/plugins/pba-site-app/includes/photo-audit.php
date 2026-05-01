<?php
/**
 * PBA Photo Feature - Audit Logging
 *
 * Writes photo-specific audit records to PhotoAuditLog.
 */

if (!defined('ABSPATH')) {
    exit;
}

function pba_photo_audit_log($args = array()) {
    $defaults = array(
        'action_type'   => '',
        'entity_type'   => '',
        'entity_id'     => null,
        'entity_label'  => null,
        'photo_id'      => null,
        'collection_id' => null,
        'result_status' => 'success',
        'summary'       => '',
        'before_json'   => null,
        'after_json'    => null,
        'details_json'  => array(),
    );

    $args = wp_parse_args($args, $defaults);

    $action_type = sanitize_text_field((string) $args['action_type']);
    $entity_type = sanitize_text_field((string) $args['entity_type']);

    if ($action_type === '' || $entity_type === '') {
        return new WP_Error(
            'pba_photo_audit_missing_required_fields',
            'Photo audit log requires action_type and entity_type.'
        );
    }

    $result_status = sanitize_key((string) $args['result_status']);

    if (!in_array($result_status, array('success', 'failure'), true)) {
        $result_status = 'success';
    }

    $row = array(
        'actor_person_id'    => pba_photo_get_current_person_id(),
        'actor_wp_user_id'   => pba_photo_get_current_wp_user_id(),
        'actor_email_address'=> pba_photo_get_current_actor_email(),
        'actor_role_names'   => pba_photo_get_current_actor_role_names(),

        'action_type'        => $action_type,
        'entity_type'        => $entity_type,
        'entity_id'          => $args['entity_id'] !== null ? (int) $args['entity_id'] : null,
        'entity_label'       => $args['entity_label'] !== null ? sanitize_text_field((string) $args['entity_label']) : null,

        'photo_id'           => $args['photo_id'] !== null ? (int) $args['photo_id'] : null,
        'collection_id'      => $args['collection_id'] !== null ? (int) $args['collection_id'] : null,

        'result_status'      => $result_status,
        'summary'            => sanitize_textarea_field((string) $args['summary']),

        'before_json'        => $args['before_json'],
        'after_json'         => $args['after_json'],
        'details_json'       => is_array($args['details_json']) ? $args['details_json'] : array(),

        'ip_address'         => pba_photo_get_request_ip_address(),
        'user_agent'         => pba_photo_get_request_user_agent(),
    );

    $insert_result = pba_photo_supabase_insert('PhotoAuditLog', $row);

    if (is_wp_error($insert_result)) {
        error_log('PBA Photo audit log failed: ' . $insert_result->get_error_message());
        return $insert_result;
    }

    return $insert_result;
}

function pba_photo_audit_upload_success($photo_id, $photo_row) {
    $label = '';

    if (is_array($photo_row)) {
        if (!empty($photo_row['title'])) {
            $label = $photo_row['title'];
        } elseif (!empty($photo_row['original_filename'])) {
            $label = $photo_row['original_filename'];
        }
    }

    return pba_photo_audit_log(array(
        'action_type'   => 'photo.uploaded',
        'entity_type'   => 'Photo',
        'entity_id'     => $photo_id,
        'entity_label'  => $label,
        'photo_id'      => $photo_id,
        'result_status' => 'success',
        'summary'       => 'Uploaded photo for admin review.',
        'after_json'    => $photo_row,
        'details_json'  => array(
            'status' => 'pending',
        ),
    ));
}

function pba_photo_audit_upload_failure($summary, $details = array()) {
    return pba_photo_audit_log(array(
        'action_type'   => 'photo.upload.failed',
        'entity_type'   => 'Photo',
        'result_status' => 'failure',
        'summary'       => $summary,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}

function pba_photo_audit_processing_failure($summary, $details = array()) {
    return pba_photo_audit_log(array(
        'action_type'   => 'photo.processing.failed',
        'entity_type'   => 'Photo',
        'result_status' => 'failure',
        'summary'       => $summary,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}

function pba_photo_audit_storage_upload_failure($summary, $details = array()) {
    return pba_photo_audit_log(array(
        'action_type'   => 'photo.storage.upload.failed',
        'entity_type'   => 'Photo',
        'result_status' => 'failure',
        'summary'       => $summary,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}

function pba_photo_audit_admin_action($action_type, $photo_id, $before, $after, $summary, $details = array()) {
    $label = '';

    if (is_array($after) && !empty($after['title'])) {
        $label = $after['title'];
    } elseif (is_array($before) && !empty($before['title'])) {
        $label = $before['title'];
    } elseif (is_array($after) && !empty($after['original_filename'])) {
        $label = $after['original_filename'];
    } elseif (is_array($before) && !empty($before['original_filename'])) {
        $label = $before['original_filename'];
    }

    return pba_photo_audit_log(array(
        'action_type'   => $action_type,
        'entity_type'   => 'Photo',
        'entity_id'     => $photo_id,
        'entity_label'  => $label,
        'photo_id'      => $photo_id,
        'result_status' => 'success',
        'summary'       => $summary,
        'before_json'   => $before,
        'after_json'    => $after,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}

function pba_photo_audit_collection_action($action_type, $collection_id, $before, $after, $summary, $details = array()) {
    $label = '';

    if (is_array($after) && !empty($after['name'])) {
        $label = $after['name'];
    } elseif (is_array($before) && !empty($before['name'])) {
        $label = $before['name'];
    }

    return pba_photo_audit_log(array(
        'action_type'   => $action_type,
        'entity_type'   => 'PhotoCollection',
        'entity_id'     => $collection_id,
        'entity_label'  => $label,
        'collection_id' => $collection_id,
        'result_status' => 'success',
        'summary'       => $summary,
        'before_json'   => $before,
        'after_json'    => $after,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}
<?php
/**
 * PBA Photo Feature - Admin Handlers
 *
 * Handles PBAAdmin review, approval, denial, unpublish, republish, and delete actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_photo_admin_approve', 'pba_handle_photo_admin_approve');
add_action('admin_post_pba_photo_admin_deny', 'pba_handle_photo_admin_deny');
add_action('admin_post_pba_photo_admin_unpublish', 'pba_handle_photo_admin_unpublish');
add_action('admin_post_pba_photo_admin_republish', 'pba_handle_photo_admin_republish');
add_action('admin_post_pba_photo_admin_delete', 'pba_handle_photo_admin_delete');
add_action('admin_post_pba_photo_collection_create', 'pba_handle_photo_collection_create');
add_action('admin_post_pba_photo_collection_update', 'pba_handle_photo_collection_update');
add_action('admin_post_pba_photo_collection_activate', 'pba_handle_photo_collection_activate');
add_action('admin_post_pba_photo_collection_deactivate', 'pba_handle_photo_collection_deactivate');
add_action('admin_post_pba_photo_collection_delete', 'pba_handle_photo_collection_delete');
add_action('admin_post_pba_photo_admin_update_library_photo', 'pba_handle_photo_admin_update_library_photo');

function pba_photo_admin_redirect($message = '', $tab = 'pending') {
    $args = array();

    if ($message !== '') {
        $args['pba_photo_admin_message'] = sanitize_key($message);
    }

    if ($tab !== '') {
        $args['tab'] = sanitize_key($tab);
    }

    wp_safe_redirect(add_query_arg($args, home_url('/photo-admin/')));
    exit;
}

function pba_photo_admin_require_manage_access() {
    if (!pba_photo_current_user_can_manage()) {
        pba_photo_admin_redirect('not_allowed', 'pending');
    }
}

function pba_photo_admin_verify_nonce($action) {
    $nonce_name = 'pba_photo_admin_nonce';

    if (
        empty($_POST[$nonce_name]) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $action)
    ) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.admin.security_failed',
            'entity_type'   => 'Photo',
            'result_status' => 'failure',
            'summary'       => 'Photo admin action failed nonce validation.',
            'details_json'  => array(
                'nonce_action' => $action,
            ),
        ));

        pba_photo_admin_redirect('security_error', 'pending');
    }
}

function pba_photo_admin_get_posted_photo_id() {
    return isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;
}

function pba_photo_admin_get_photo($photo_id) {
    $photo_id = (int) $photo_id;

    if ($photo_id <= 0) {
        return new WP_Error('pba_photo_invalid_id', 'Invalid photo ID.');
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'   => '*',
        'photo_id' => 'eq.' . $photo_id,
        'limit'    => 1,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (!is_array($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return new WP_Error('pba_photo_not_found', 'Photo not found.');
    }

    return $rows[0];
}

function pba_photo_admin_update_photo($photo_id, $updates) {
    $photo_id = (int) $photo_id;

    $updates['last_modified_at'] = gmdate('c');

    $result = pba_photo_supabase_update('Photo', array(
        'photo_id' => 'eq.' . $photo_id,
    ), $updates);

    if (is_wp_error($result)) {
        return $result;
    }

    if (is_array($result) && !empty($result[0]) && is_array($result[0])) {
        return $result[0];
    }

    return $result;
}

function pba_photo_admin_get_posted_collection_ids() {
    if (empty($_POST['collection_ids']) || !is_array($_POST['collection_ids'])) {
        return array();
    }

    $ids = array();

    foreach ($_POST['collection_ids'] as $collection_id) {
        $collection_id = absint($collection_id);

        if ($collection_id > 0) {
            $ids[] = $collection_id;
        }
    }

    return array_values(array_unique($ids));
}

function pba_photo_admin_get_collection_names_by_ids($collection_ids) {
    if (empty($collection_ids)) {
        return array();
    }

    $clean_ids = array();

    foreach ($collection_ids as $collection_id) {
        $collection_id = (int) $collection_id;

        if ($collection_id > 0) {
            $clean_ids[] = $collection_id;
        }
    }

    if (empty($clean_ids)) {
        return array();
    }

    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select'        => 'collection_id,name',
        'collection_id' => 'in.(' . implode(',', $clean_ids) . ')',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $names = array();

    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }

        $names[] = $row['name'];
    }

    sort($names);

    return $names;
}

function pba_photo_admin_get_photo_collection_ids($photo_id) {
    $photo_id = (int) $photo_id;

    $rows = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'   => 'collection_id',
        'photo_id' => 'eq.' . $photo_id,
        'order'    => 'collection_id.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $ids = array();

    foreach ($rows as $row) {
        if (is_array($row) && isset($row['collection_id'])) {
            $ids[] = (int) $row['collection_id'];
        }
    }

    return $ids;
}

function pba_photo_admin_replace_photo_collections($photo_id, $collection_ids) {
    $photo_id = (int) $photo_id;

    $delete_result = pba_photo_supabase_delete('PhotoCollectionPhoto', array(
        'photo_id' => 'eq.' . $photo_id,
    ));

    if (is_wp_error($delete_result)) {
        return $delete_result;
    }

    $collection_ids = array_values(array_unique(array_map('intval', $collection_ids)));

    foreach ($collection_ids as $collection_id) {
        if ($collection_id <= 0) {
            continue;
        }

        $insert_result = pba_photo_supabase_insert('PhotoCollectionPhoto', array(
            'photo_id'       => $photo_id,
            'collection_id'  => $collection_id,
            'sort_order'     => 0,
        ));

        if (is_wp_error($insert_result)) {
            return $insert_result;
        }
    }

    return true;
}

function pba_handle_photo_admin_approve() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_approve');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'pending');
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $caption = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : '';
    $photographer_name = isset($_POST['photographer_name']) ? sanitize_text_field(wp_unslash($_POST['photographer_name'])) : '';
    $collection_ids = pba_photo_admin_get_posted_collection_ids();

    $updates = array(
        'title'                  => $title !== '' ? $title : null,
        'caption'                => $caption !== '' ? $caption : null,
        'photographer_name'      => $photographer_name !== '' ? $photographer_name : null,
        'status'                 => 'approved',
        'visibility'             => 'public',
        'approved_at'            => gmdate('c'),
        'approved_by_person_id'  => pba_photo_get_current_person_id(),
        'approved_by_wp_user_id' => get_current_user_id(),
        'denied_at'              => null,
        'denied_by_person_id'    => null,
        'denied_by_wp_user_id'   => null,
        'denial_reason'          => null,
        'unpublished_at'         => null,
        'unpublished_by_person_id' => null,
        'unpublished_by_wp_user_id' => null,
        'unpublish_reason'       => null,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.approve.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Failed to approve photo.',
            'before_json'   => $before,
            'details_json'  => array(
                'error' => $after->get_error_message(),
            ),
        ));

        pba_photo_admin_redirect('approve_failed', 'pending');
    }

    $collection_result = pba_photo_admin_replace_photo_collections($photo_id, $collection_ids);

    if (is_wp_error($collection_result)) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.collection.assign.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Photo was approved, but collection assignment failed.',
            'before_json'   => $before,
            'after_json'    => $after,
            'details_json'  => array(
                'collection_ids' => $collection_ids,
                'error'          => $collection_result->get_error_message(),
            ),
        ));

        pba_photo_admin_redirect('collection_assign_failed', 'approved');
    }

    $collection_names = pba_photo_admin_get_collection_names_by_ids($collection_ids);

    pba_photo_audit_admin_action(
        'photo.approved',
        $photo_id,
        $before,
        $after,
        'Approved photo for public display.',
        array(
            'collection_ids'   => $collection_ids,
            'collection_names' => $collection_names,
        )
    );

    pba_photo_admin_redirect('approved', 'approved');
}

function pba_handle_photo_admin_deny() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_deny');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'pending');
    }

    $reason = isset($_POST['denial_reason'])
        ? sanitize_textarea_field(wp_unslash($_POST['denial_reason']))
        : '';

    $storage_result = pba_photo_storage_delete_for_photo($before);
    $storage_deleted_at = null;
    $storage_delete_error = null;

    if (is_wp_error($storage_result)) {
        $storage_delete_error = $storage_result->get_error_message();

        pba_photo_audit_log(array(
            'action_type'   => 'photo.storage.delete.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Failed to delete stored image while denying photo.',
            'before_json'   => $before,
            'details_json'  => array(
                'error'        => $storage_delete_error,
                'storage_path' => isset($before['storage_path']) ? $before['storage_path'] : null,
            ),
        ));

        pba_photo_admin_redirect('storage_delete_failed', 'pending');
    }

    $storage_deleted_at = gmdate('c');

    $updates = array(
        'status'                  => 'denied',
        'visibility'              => 'hidden',
        'denied_at'               => gmdate('c'),
        'denied_by_person_id'     => pba_photo_get_current_person_id(),
        'denied_by_wp_user_id'    => get_current_user_id(),
        'denial_reason'           => $reason !== '' ? $reason : null,
        'storage_deleted_at'      => $storage_deleted_at,
        'storage_delete_error'    => $storage_delete_error,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.deny.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Stored image was deleted, but photo denial database update failed.',
            'before_json'   => $before,
            'details_json'  => array(
                'error' => $after->get_error_message(),
            ),
        ));

        pba_photo_admin_redirect('deny_failed', 'pending');
    }

    pba_photo_admin_replace_photo_collections($photo_id, array());

    pba_photo_audit_admin_action(
        'photo.denied',
        $photo_id,
        $before,
        $after,
        'Denied photo and removed stored image.',
        array(
            'denial_reason' => $reason,
        )
    );

    pba_photo_admin_redirect('denied', 'denied');
}

function pba_handle_photo_admin_unpublish() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_unpublish');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'approved');
    }

    $reason = isset($_POST['unpublish_reason'])
        ? sanitize_textarea_field(wp_unslash($_POST['unpublish_reason']))
        : '';

    $updates = array(
        'status'                    => 'unpublished',
        'visibility'                => 'hidden',
        'unpublished_at'            => gmdate('c'),
        'unpublished_by_person_id'  => pba_photo_get_current_person_id(),
        'unpublished_by_wp_user_id' => get_current_user_id(),
        'unpublish_reason'          => $reason !== '' ? $reason : null,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_admin_redirect('unpublish_failed', 'approved');
    }

    pba_photo_audit_admin_action(
        'photo.unpublished',
        $photo_id,
        $before,
        $after,
        'Unpublished photo.',
        array(
            'unpublish_reason' => $reason,
        )
    );

    pba_photo_admin_redirect('unpublished', 'unpublished');
}

function pba_handle_photo_admin_republish() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_republish');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'unpublished');
    }

    if (!empty($before['storage_deleted_at'])) {
        pba_photo_admin_redirect('cannot_republish_deleted_storage', 'unpublished');
    }

    $updates = array(
        'status'                    => 'approved',
        'visibility'                => 'public',
        'approved_at'               => gmdate('c'),
        'approved_by_person_id'     => pba_photo_get_current_person_id(),
        'approved_by_wp_user_id'    => get_current_user_id(),
        'unpublished_at'            => null,
        'unpublished_by_person_id'  => null,
        'unpublished_by_wp_user_id' => null,
        'unpublish_reason'          => null,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_admin_redirect('republish_failed', 'unpublished');
    }

    pba_photo_audit_admin_action(
        'photo.republished',
        $photo_id,
        $before,
        $after,
        'Republished photo.',
        array()
    );

    pba_photo_admin_redirect('republished', 'approved');
}

function pba_handle_photo_admin_delete() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_delete');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'pending');
    }

    $previous_status = isset($before['status']) ? $before['status'] : 'pending';
    $delete_reason = isset($_POST['delete_reason'])
        ? sanitize_textarea_field(wp_unslash($_POST['delete_reason']))
        : '';

    $existing_collection_ids = pba_photo_admin_get_photo_collection_ids($photo_id);
    $existing_collection_names = pba_photo_admin_get_collection_names_by_ids($existing_collection_ids);

    $storage_deleted_at = !empty($before['storage_deleted_at']) ? $before['storage_deleted_at'] : null;

    if (empty($before['storage_deleted_at']) && !empty($before['storage_path'])) {
        $storage_result = pba_photo_storage_delete_for_photo($before);

        if (is_wp_error($storage_result)) {
            pba_photo_audit_log(array(
                'action_type'   => 'photo.storage.delete.failed',
                'entity_type'   => 'Photo',
                'entity_id'     => $photo_id,
                'photo_id'      => $photo_id,
                'result_status' => 'failure',
                'summary'       => 'Failed to delete stored image while deleting photo.',
                'before_json'   => $before,
                'details_json'  => array(
                    'previous_status' => $previous_status,
                    'storage_path'    => isset($before['storage_path']) ? $before['storage_path'] : null,
                    'error'           => $storage_result->get_error_message(),
                ),
            ));

            pba_photo_admin_redirect('storage_delete_failed', $previous_status);
        }

        $storage_deleted_at = gmdate('c');
    }

    $updates = array(
        'status'                  => 'deleted',
        'visibility'              => 'hidden',
        'deleted_at'              => gmdate('c'),
        'deleted_by_person_id'    => pba_photo_get_current_person_id(),
        'deleted_by_wp_user_id'   => get_current_user_id(),
        'delete_reason'           => $delete_reason !== '' ? $delete_reason : null,
        'storage_deleted_at'      => $storage_deleted_at,
        'storage_delete_error'    => null,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_admin_redirect('delete_failed', $previous_status);
    }

    pba_photo_admin_replace_photo_collections($photo_id, array());

    pba_photo_audit_admin_action(
        'photo.deleted',
        $photo_id,
        $before,
        $after,
        'Deleted photo and removed stored image.',
        array(
            'previous_status'          => $previous_status,
            'removed_collection_ids'   => $existing_collection_ids,
            'removed_collection_names' => $existing_collection_names,
            'delete_reason'            => $delete_reason,
        )
    );

    pba_photo_admin_redirect('deleted', 'deleted');
}
function pba_photo_admin_get_posted_collection_id() {
    return isset($_POST['collection_id']) ? absint($_POST['collection_id']) : 0;
}

function pba_photo_admin_get_collection($collection_id) {
    $collection_id = (int) $collection_id;

    if ($collection_id <= 0) {
        return new WP_Error('pba_photo_collection_invalid_id', 'Invalid collection ID.');
    }

    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select'        => '*',
        'collection_id' => 'eq.' . $collection_id,
        'limit'         => 1,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (!is_array($rows) || empty($rows[0]) || !is_array($rows[0])) {
        return new WP_Error('pba_photo_collection_not_found', 'Collection not found.');
    }

    return $rows[0];
}

function pba_photo_admin_collection_redirect($message = '') {
    pba_photo_admin_redirect($message, 'collections');
}

function pba_photo_admin_normalize_collection_slug($name, $slug = '') {
    $slug = trim((string) $slug);

    if ($slug === '') {
        $slug = $name;
    }

    $slug = pba_photo_slugify($slug);

    return $slug !== '' ? $slug : 'collection';
}

function pba_handle_photo_collection_create() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_collection_create');

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

    if ($name === '') {
        pba_photo_collection_audit_failure(
            'photo.collection.create.failed',
            'Collection create failed because name was missing.',
            array('reason' => 'missing_name')
        );

        pba_photo_admin_collection_redirect('collection_name_required');
    }

    $row = array(
        'name'                  => $name,
        'slug'                  => pba_photo_admin_normalize_collection_slug($name, $slug),
        'description'           => $description !== '' ? $description : null,
        'is_active'             => true,
        'display_order'         => $display_order,
        'created_by_person_id'  => pba_photo_get_current_person_id(),
        'created_by_wp_user_id' => get_current_user_id(),
    );

    $result = pba_photo_supabase_insert('PhotoCollection', $row);

    if (is_wp_error($result)) {
        pba_photo_collection_audit_failure(
            'photo.collection.create.failed',
            'Collection create failed.',
            array(
                'name'  => $name,
                'slug'  => $row['slug'],
                'error' => $result->get_error_message(),
            )
        );

        pba_photo_admin_collection_redirect('collection_create_failed');
    }

    $created = is_array($result) && !empty($result[0]) && is_array($result[0])
        ? $result[0]
        : $row;

    $collection_id = isset($created['collection_id']) ? (int) $created['collection_id'] : null;

    pba_photo_audit_collection_action(
        'photo.collection.created',
        $collection_id,
        null,
        $created,
        'Created photo collection "' . $name . '".',
        array()
    );

    pba_photo_admin_collection_redirect('collection_created');
}

function pba_handle_photo_collection_update() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_collection_update');

    $collection_id = pba_photo_admin_get_posted_collection_id();
    $before = pba_photo_admin_get_collection($collection_id);

    if (is_wp_error($before)) {
        pba_photo_admin_collection_redirect('collection_not_found');
    }

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $display_order = isset($_POST['display_order']) ? intval($_POST['display_order']) : 0;

    if ($name === '') {
        pba_photo_admin_collection_redirect('collection_name_required');
    }

    $updates = array(
        'name'             => $name,
        'slug'             => pba_photo_admin_normalize_collection_slug($name, $slug),
        'description'      => $description !== '' ? $description : null,
        'display_order'    => $display_order,
        'last_modified_at' => gmdate('c'),
    );

    $result = pba_photo_supabase_update('PhotoCollection', array(
        'collection_id' => 'eq.' . $collection_id,
    ), $updates);

    if (is_wp_error($result)) {
        pba_photo_collection_audit_failure(
            'photo.collection.update.failed',
            'Collection update failed.',
            array(
                'collection_id' => $collection_id,
                'error'         => $result->get_error_message(),
            ),
            $collection_id,
            $before
        );

        pba_photo_admin_collection_redirect('collection_update_failed');
    }

    $after = is_array($result) && !empty($result[0]) && is_array($result[0])
        ? $result[0]
        : array_merge($before, $updates);

    pba_photo_audit_collection_action(
        'photo.collection.updated',
        $collection_id,
        $before,
        $after,
        'Updated photo collection "' . $name . '".',
        array()
    );

    pba_photo_admin_collection_redirect('collection_updated');
}

function pba_handle_photo_collection_activate() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_collection_activate');

    pba_photo_handle_collection_active_toggle(true);
}

function pba_handle_photo_collection_deactivate() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_collection_deactivate');

    pba_photo_handle_collection_active_toggle(false);
}

function pba_photo_handle_collection_active_toggle($make_active) {
    $collection_id = pba_photo_admin_get_posted_collection_id();
    $before = pba_photo_admin_get_collection($collection_id);

    if (is_wp_error($before)) {
        pba_photo_admin_collection_redirect('collection_not_found');
    }

    $updates = array(
        'is_active'        => (bool) $make_active,
        'last_modified_at' => gmdate('c'),
    );

    $result = pba_photo_supabase_update('PhotoCollection', array(
        'collection_id' => 'eq.' . $collection_id,
    ), $updates);

    if (is_wp_error($result)) {
        pba_photo_collection_audit_failure(
            $make_active ? 'photo.collection.activate.failed' : 'photo.collection.deactivate.failed',
            $make_active ? 'Collection activation failed.' : 'Collection deactivation failed.',
            array(
                'collection_id' => $collection_id,
                'error'         => $result->get_error_message(),
            ),
            $collection_id,
            $before
        );

        pba_photo_admin_collection_redirect($make_active ? 'collection_activate_failed' : 'collection_deactivate_failed');
    }

    $after = is_array($result) && !empty($result[0]) && is_array($result[0])
        ? $result[0]
        : array_merge($before, $updates);

    pba_photo_audit_collection_action(
        $make_active ? 'photo.collection.activated' : 'photo.collection.deactivated',
        $collection_id,
        $before,
        $after,
        $make_active
            ? 'Activated photo collection "' . $before['name'] . '".'
            : 'Deactivated photo collection "' . $before['name'] . '".',
        array()
    );

    pba_photo_admin_collection_redirect($make_active ? 'collection_activated' : 'collection_deactivated');
}

function pba_handle_photo_collection_delete() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_collection_delete');

    $collection_id = pba_photo_admin_get_posted_collection_id();
    $before = pba_photo_admin_get_collection($collection_id);

    if (is_wp_error($before)) {
        pba_photo_admin_collection_redirect('collection_not_found');
    }

    $assigned_photo_ids = pba_photo_admin_get_photo_ids_for_collection($collection_id);

    /*
     * Delete only the collection metadata. Photos are not deleted.
     * Because PhotoCollectionPhoto has on-delete cascade, collection assignments
     * are removed automatically when the collection row is deleted.
     */
    $result = pba_photo_supabase_delete('PhotoCollection', array(
        'collection_id' => 'eq.' . $collection_id,
    ));

    if (is_wp_error($result)) {
        pba_photo_collection_audit_failure(
            'photo.collection.delete.failed',
            'Collection delete failed.',
            array(
                'collection_id' => $collection_id,
                'error'         => $result->get_error_message(),
            ),
            $collection_id,
            $before
        );

        pba_photo_admin_collection_redirect('collection_delete_failed');
    }

    pba_photo_audit_collection_action(
        'photo.collection.deleted',
        $collection_id,
        $before,
        null,
        'Deleted photo collection "' . $before['name'] . '". Photos were not deleted.',
        array(
            'assigned_photo_ids'   => $assigned_photo_ids,
            'assigned_photo_count' => count($assigned_photo_ids),
            'photos_deleted'       => false,
        )
    );

    pba_photo_admin_collection_redirect('collection_deleted');
}

function pba_photo_admin_get_photo_ids_for_collection($collection_id) {
    $collection_id = (int) $collection_id;

    if ($collection_id <= 0) {
        return array();
    }

    $rows = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'        => 'photo_id',
        'collection_id' => 'eq.' . $collection_id,
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $ids = array();

    foreach ($rows as $row) {
        if (is_array($row) && !empty($row['photo_id'])) {
            $ids[] = (int) $row['photo_id'];
        }
    }

    return array_values(array_unique($ids));
}

function pba_photo_collection_audit_failure($action_type, $summary, $details = array(), $collection_id = null, $before = null) {
    return pba_photo_audit_log(array(
        'action_type'   => $action_type,
        'entity_type'   => 'PhotoCollection',
        'entity_id'     => $collection_id,
        'collection_id' => $collection_id,
        'result_status' => 'failure',
        'summary'       => $summary,
        'before_json'   => $before,
        'details_json'  => is_array($details) ? $details : array(),
    ));
}
function pba_handle_photo_admin_update_library_photo() {
    pba_photo_admin_require_manage_access();
    pba_photo_admin_verify_nonce('pba_photo_admin_update_library_photo');

    $photo_id = pba_photo_admin_get_posted_photo_id();
    $before = pba_photo_admin_get_photo($photo_id);

    if (is_wp_error($before)) {
        pba_photo_admin_redirect('photo_not_found', 'approved');
    }

    $current_status = isset($before['status']) ? pba_photo_sanitize_status($before['status']) : 'approved';

    if (!in_array($current_status, array('approved', 'unpublished'), true)) {
        pba_photo_admin_redirect('library_update_invalid_status', $current_status);
    }

    $title = isset($_POST['title'])
        ? sanitize_text_field(wp_unslash($_POST['title']))
        : '';

    $caption = isset($_POST['caption'])
        ? sanitize_textarea_field(wp_unslash($_POST['caption']))
        : '';

    $photographer_name = isset($_POST['photographer_name'])
        ? sanitize_text_field(wp_unslash($_POST['photographer_name']))
        : '';

    $sort_order = isset($_POST['sort_order'])
        ? intval($_POST['sort_order'])
        : 0;

    $is_featured = !empty($_POST['is_featured']);
    $collection_ids = pba_photo_admin_get_posted_collection_ids();

    $previous_collection_ids = pba_photo_admin_get_photo_collection_ids($photo_id);
    $previous_collection_names = pba_photo_admin_get_collection_names_by_ids($previous_collection_ids);
    $new_collection_names = pba_photo_admin_get_collection_names_by_ids($collection_ids);

    $updates = array(
        'title'             => $title !== '' ? $title : null,
        'caption'           => $caption !== '' ? $caption : null,
        'photographer_name' => $photographer_name !== '' ? $photographer_name : null,
        'is_featured'       => (bool) $is_featured,
        'sort_order'        => $sort_order,
    );

    $after = pba_photo_admin_update_photo($photo_id, $updates);

    if (is_wp_error($after)) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.library.update.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Failed to update photo library metadata.',
            'before_json'   => $before,
            'details_json'  => array(
                'error' => $after->get_error_message(),
            ),
        ));

        pba_photo_admin_redirect('library_update_failed', $current_status);
    }

    $collection_result = pba_photo_admin_replace_photo_collections($photo_id, $collection_ids);

    if (is_wp_error($collection_result)) {
        pba_photo_audit_log(array(
            'action_type'   => 'photo.library.collection.update.failed',
            'entity_type'   => 'Photo',
            'entity_id'     => $photo_id,
            'photo_id'      => $photo_id,
            'result_status' => 'failure',
            'summary'       => 'Photo metadata was updated, but collection assignment failed.',
            'before_json'   => $before,
            'after_json'    => $after,
            'details_json'  => array(
                'previous_collection_ids'   => $previous_collection_ids,
                'previous_collection_names' => $previous_collection_names,
                'new_collection_ids'        => $collection_ids,
                'new_collection_names'      => $new_collection_names,
                'error'                     => $collection_result->get_error_message(),
            ),
        ));

        pba_photo_admin_redirect('library_collection_update_failed', $current_status);
    }

    pba_photo_audit_admin_action(
        'photo.library.updated',
        $photo_id,
        $before,
        $after,
        'Updated photo library metadata and collection assignments.',
        array(
            'previous_collection_ids'   => $previous_collection_ids,
            'previous_collection_names' => $previous_collection_names,
            'new_collection_ids'        => $collection_ids,
            'new_collection_names'      => $new_collection_names,
            'is_featured'               => (bool) $is_featured,
            'sort_order'                => $sort_order,
        )
    );

    pba_photo_admin_redirect('library_updated', $current_status);
}
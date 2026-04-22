<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_share_document_with_members', 'pba_handle_share_document_with_members');
add_action('admin_post_pba_unshare_document_with_members', 'pba_handle_unshare_document_with_members');

function pba_current_person_has_active_committee_assignment($committee_id) {
    $person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;
    $committee_id = (int) $committee_id;

    if ($person_id < 1 || $committee_id < 1) {
        return false;
    }

    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'       => 'person_to_committee_id',
        'person_id'    => 'eq.' . $person_id,
        'committee_id' => 'eq.' . $committee_id,
        'is_active'    => 'eq.true',
        'limit'        => 1,
    ));

    return !is_wp_error($rows) && !empty($rows);
}

function pba_get_current_person_committee_ids() {
    static $committee_ids_cache = null;
    static $has_loaded_committee_ids = false;

    if ($has_loaded_committee_ids) {
        return $committee_ids_cache;
    }

    $person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;

    if ($person_id < 1) {
        $committee_ids_cache = array();
        $has_loaded_committee_ids = true;
        return $committee_ids_cache;
    }

    $rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'committee_id',
        'person_id' => 'eq.' . $person_id,
        'is_active' => 'eq.true',
        'order'     => 'committee_id.asc',
    ));

    if (is_wp_error($rows) || empty($rows)) {
        $committee_ids_cache = array();
        $has_loaded_committee_ids = true;
        return $committee_ids_cache;
    }

    $ids = array();

    foreach ($rows as $row) {
        if (!empty($row['committee_id'])) {
            $ids[] = (int) $row['committee_id'];
        }
    }

    $committee_ids_cache = array_values(array_unique($ids));
    $has_loaded_committee_ids = true;

    return $committee_ids_cache;
}

function pba_get_current_person_committee_rows() {
    static $committee_rows_cache = null;
    static $has_loaded_committee_rows = false;

    if ($has_loaded_committee_rows) {
        return $committee_rows_cache;
    }

    $committee_ids = pba_get_current_person_committee_ids();

    if (empty($committee_ids)) {
        $committee_rows_cache = array();
        $has_loaded_committee_rows = true;
        return $committee_rows_cache;
    }

    $rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_id,committee_name,status,display_order',
        'committee_id' => 'in.(' . implode(',', array_map('intval', $committee_ids)) . ')',
        'limit'        => count($committee_ids),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        $committee_rows_cache = array();
        $has_loaded_committee_rows = true;
        return $committee_rows_cache;
    }

    usort($rows, function ($a, $b) {
        $a_order = isset($a['display_order']) && $a['display_order'] !== null ? (int) $a['display_order'] : 999999;
        $b_order = isset($b['display_order']) && $b['display_order'] !== null ? (int) $b['display_order'] : 999999;

        if ($a_order === $b_order) {
            return strcmp((string) ($a['committee_name'] ?? ''), (string) ($b['committee_name'] ?? ''));
        }

        return $a_order <=> $b_order;
    });

    $committee_rows_cache = $rows;
    $has_loaded_committee_rows = true;

    return $committee_rows_cache;
}

function pba_get_document_folder($folder_id) {
    static $folder_cache = array();

    $folder_id = (int) $folder_id;

    if ($folder_id < 1) {
        return false;
    }

    if (array_key_exists($folder_id, $folder_cache)) {
        return $folder_cache[$folder_id];
    }

    $rows = pba_supabase_get('Document_Folder', array(
        'select'             => 'document_folder_id,folder_name,folder_scope,committee_id,parent_folder_id,display_order,is_active,created_by_person_id,notes,last_modified_at',
        'document_folder_id' => 'eq.' . $folder_id,
        'limit'              => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        $folder_cache[$folder_id] = false;
        return false;
    }

    $folder_cache[$folder_id] = $rows[0];
    return $folder_cache[$folder_id];
}

function pba_get_document_item($document_item_id) {
    static $item_cache = array();

    $document_item_id = (int) $document_item_id;

    if ($document_item_id < 1) {
        return false;
    }

    if (array_key_exists($document_item_id, $item_cache)) {
        return $item_cache[$document_item_id];
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select'           => 'document_item_id,document_folder_id,file_name,file_url,mime_type,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at,document_title,document_date,document_category,document_version,visible_to_all_members,shared_with_members_at,shared_with_members_by_person_id,member_summary',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit'            => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        $item_cache[$document_item_id] = false;
        return false;
    }

    $item_cache[$document_item_id] = $rows[0];
    return $item_cache[$document_item_id];
}

function pba_get_active_document_item($document_item_id) {
    $document_item = pba_get_document_item($document_item_id);

    if (!$document_item || empty($document_item['is_active'])) {
        return false;
    }

    return $document_item;
}

function pba_current_person_can_manage_board_folders() {
    return
        (function_exists('pba_current_person_is_admin') && pba_current_person_is_admin()) ||
        (function_exists('pba_current_person_is_board_member') && pba_current_person_is_board_member());
}

function pba_current_person_can_manage_committee_folder($committee_id) {
    if (function_exists('pba_current_person_is_admin') && pba_current_person_is_admin()) {
        return true;
    }

    return pba_current_person_has_active_committee_assignment((int) $committee_id);
}

function pba_current_person_can_manage_folder($folder_id) {
    $folder = pba_get_document_folder($folder_id);

    if (!$folder || empty($folder['is_active'])) {
        return false;
    }

    $scope = isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '';

    if ($scope === 'Board') {
        return pba_current_person_can_manage_board_folders();
    }

    if ($scope === 'Committee') {
        return pba_current_person_can_manage_committee_folder((int) ($folder['committee_id'] ?? 0));
    }

    return false;
}

function pba_current_person_can_create_folder($folder_scope_type, $committee_id = 0) {
    $folder_scope_type = (string) $folder_scope_type;
    $committee_id = (int) $committee_id;

    if ($folder_scope_type === 'Board') {
        return pba_current_person_can_manage_board_folders();
    }

    if ($folder_scope_type === 'Committee') {
        return pba_current_person_can_manage_committee_folder($committee_id);
    }

    return false;
}

function pba_current_person_can_view_folder($folder_id) {
    return pba_current_person_can_manage_folder($folder_id);
}

function pba_get_active_document_folders($folder_scope_type, $committee_id = null) {
    $args = array(
        'select'       => 'document_folder_id,folder_name,folder_scope,committee_id,parent_folder_id,display_order,is_active,created_by_person_id,notes,last_modified_at',
        'folder_scope' => 'eq.' . $folder_scope_type,
        'is_active'    => 'eq.true',
        'order'        => 'display_order.asc,folder_name.asc',
    );

    if ($committee_id === null) {
        $args['committee_id'] = 'is.null';
    } else {
        $args['committee_id'] = 'eq.' . (int) $committee_id;
    }

    $rows = pba_supabase_get('Document_Folder', $args);

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_get_committee_name($committee_id) {
    $committee_id = (int) $committee_id;

    if ($committee_id < 1) {
        return '';
    }

    $rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_name',
        'committee_id' => 'eq.' . $committee_id,
        'limit'        => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0]['committee_name'])) {
        return '';
    }

    return (string) $rows[0]['committee_name'];
}

function pba_current_person_can_upload_to_folder($folder_id) {
    return pba_current_person_can_manage_folder($folder_id);
}

function pba_current_person_can_rename_folder($folder_id) {
    return pba_current_person_can_manage_folder($folder_id);
}

function pba_current_person_can_delete_folder($folder_id) {
    return pba_current_person_can_manage_folder($folder_id);
}

function pba_get_document_origin_context($document_item_id) {
    $document_item = pba_get_document_item($document_item_id);

    if (!$document_item || empty($document_item['document_folder_id'])) {
        return false;
    }

    $folder_id = (int) $document_item['document_folder_id'];
    $folder = pba_get_document_folder($folder_id);

    if (!$folder || empty($folder['is_active'])) {
        return false;
    }

    $committee_id = isset($folder['committee_id']) ? (int) $folder['committee_id'] : 0;
    $committee_name = $committee_id > 0 ? pba_get_committee_name($committee_id) : '';

    return array(
        'document_item'                    => $document_item,
        'document_item_id'                 => isset($document_item['document_item_id']) ? (int) $document_item['document_item_id'] : 0,
        'document_folder_id'               => $folder_id,
        'folder_name'                      => isset($folder['folder_name']) ? (string) $folder['folder_name'] : '',
        'folder_scope_type'                => isset($folder['folder_scope']) ? (string) $folder['folder_scope'] : '',
        'committee_id'                     => $committee_id,
        'committee_name'                   => $committee_name,
        'is_active'                        => !empty($document_item['is_active']),
        'visible_to_all_members'           => !empty($document_item['visible_to_all_members']),
        'member_summary'                   => isset($document_item['member_summary']) ? (string) $document_item['member_summary'] : '',
        'shared_with_members_at'           => isset($document_item['shared_with_members_at']) ? (string) $document_item['shared_with_members_at'] : '',
        'shared_with_members_by_person_id' => isset($document_item['shared_with_members_by_person_id']) ? (int) $document_item['shared_with_members_by_person_id'] : 0,
    );
}

if (!function_exists('pba_document_permission_get_item_snapshot')) {
    function pba_document_permission_get_item_snapshot($document_item_id) {
        $document_item_id = (int) $document_item_id;

        if ($document_item_id < 1) {
            return null;
        }

        $rows = pba_supabase_get('Document_Item', array(
            'select'           => 'document_item_id,document_folder_id,file_name,file_url,mime_type,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at,document_title,document_date,document_category,document_version,visible_to_all_members,shared_with_members_at,shared_with_members_by_person_id,member_summary',
            'document_item_id' => 'eq.' . $document_item_id,
            'limit'            => 1,
        ));

        if (is_wp_error($rows) || empty($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}

if (!function_exists('pba_document_permission_get_item_label')) {
    function pba_document_permission_get_item_label($item_row) {
        if (!is_array($item_row)) {
            return '';
        }

        $title = trim((string) ($item_row['document_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $file_name = trim((string) ($item_row['file_name'] ?? ''));
        if ($file_name !== '') {
            return $file_name;
        }

        $document_item_id = isset($item_row['document_item_id']) ? (int) $item_row['document_item_id'] : 0;
        return $document_item_id > 0 ? 'Document #' . $document_item_id : '';
    }
}

if (!function_exists('pba_document_permission_audit_log')) {
    function pba_document_permission_audit_log($action_type, $entity_type, $entity_id = null, $args = array()) {
        if (!function_exists('pba_audit_log')) {
            return;
        }

        pba_audit_log($action_type, $entity_type, $entity_id, $args);
    }
}

function pba_current_person_can_view_member_resources() {
    if (!is_user_logged_in()) {
        return false;
    }

    if (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_admin')) {
        return true;
    }

    $allowed_roles = array(
        'pba_member',
        'pba_house_admin',
        'pba_board_member',
        'pba_committee_member',
        'pba_admin',
    );

    foreach ($allowed_roles as $role_slug) {
        if (function_exists('pba_current_person_has_role') && pba_current_person_has_role($role_slug)) {
            return true;
        }
    }

    return false;
}

function pba_current_person_can_share_document_with_members($document_item_id) {
    if (!is_user_logged_in()) {
        return false;
    }

    $document_item = pba_get_active_document_item($document_item_id);

    if (!$document_item) {
        return false;
    }

    if (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_admin')) {
        return true;
    }

    $context = pba_get_document_origin_context($document_item_id);

    if (!$context || empty($context['is_active'])) {
        return false;
    }

    $scope = isset($context['folder_scope_type']) ? (string) $context['folder_scope_type'] : '';

    if ($scope === 'Board') {
        return function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_board_member');
    }

    if ($scope === 'Committee') {
        if (!function_exists('pba_current_person_has_role') || !pba_current_person_has_role('pba_committee_member')) {
            return false;
        }

        $committee_id = isset($context['committee_id']) ? (int) $context['committee_id'] : 0;

        if ($committee_id < 1) {
            return false;
        }

        return pba_current_person_has_active_committee_assignment($committee_id);
    }

    return false;
}

function pba_get_member_resource_rows() {
    $rows = pba_supabase_get('Document_Item', array(
        'select'                 => 'document_item_id,document_folder_id,file_name,file_url,mime_type,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at,document_title,document_date,document_category,document_version,visible_to_all_members,shared_with_members_at,shared_with_members_by_person_id,member_summary',
        'is_active'              => 'eq.true',
        'visible_to_all_members' => 'eq.true',
        'order'                  => 'document_date.desc,shared_with_members_at.desc,document_title.asc',
        'limit'                  => 500,
    ));

    if (is_wp_error($rows)) {
        return $rows;
    }

    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $folder_ids = array();

    foreach ($rows as $row) {
        $folder_id = isset($row['document_folder_id']) ? (int) $row['document_folder_id'] : 0;
        if ($folder_id > 0) {
            $folder_ids[] = $folder_id;
        }
    }

    $folder_ids = array_values(array_unique($folder_ids));
    $folders_by_id = array();

    if (!empty($folder_ids)) {
        $folder_rows = pba_supabase_get('Document_Folder', array(
            'select'             => 'document_folder_id,folder_name,folder_scope,committee_id,is_active',
            'document_folder_id' => 'in.(' . implode(',', array_map('intval', $folder_ids)) . ')',
            'limit'              => count($folder_ids),
        ));

        if (!is_wp_error($folder_rows) && is_array($folder_rows)) {
            foreach ($folder_rows as $folder_row) {
                $folder_id = isset($folder_row['document_folder_id']) ? (int) $folder_row['document_folder_id'] : 0;
                if ($folder_id > 0) {
                    $folders_by_id[$folder_id] = $folder_row;
                }
            }
        }
    }

    $committee_ids = array();

    foreach ($folders_by_id as $folder_row) {
        if (empty($folder_row['is_active'])) {
            continue;
        }

        $scope = isset($folder_row['folder_scope']) ? (string) $folder_row['folder_scope'] : '';
        $committee_id = isset($folder_row['committee_id']) ? (int) $folder_row['committee_id'] : 0;
        if ($scope === 'Committee' && $committee_id > 0) {
            $committee_ids[] = $committee_id;
        }
    }

    $committee_ids = array_values(array_unique($committee_ids));
    $committee_names = array();

    if (!empty($committee_ids)) {
        $committee_rows = pba_supabase_get('Committee', array(
            'select'       => 'committee_id,committee_name,status',
            'committee_id' => 'in.(' . implode(',', array_map('intval', $committee_ids)) . ')',
            'limit'        => count($committee_ids),
        ));

        if (!is_wp_error($committee_rows) && is_array($committee_rows)) {
            foreach ($committee_rows as $committee_row) {
                $committee_id = isset($committee_row['committee_id']) ? (int) $committee_row['committee_id'] : 0;
                $committee_name = isset($committee_row['committee_name']) ? trim((string) $committee_row['committee_name']) : '';
                if ($committee_id > 0 && $committee_name !== '') {
                    $committee_names[$committee_id] = $committee_name;
                }
            }
        }
    }

    $enriched_rows = array();

    foreach ($rows as $row) {
        $folder_id = isset($row['document_folder_id']) ? (int) $row['document_folder_id'] : 0;
        if ($folder_id < 1 || !isset($folders_by_id[$folder_id])) {
            continue;
        }

        $folder_row = $folders_by_id[$folder_id];
        if (empty($folder_row['is_active'])) {
            continue;
        }

        $scope = isset($folder_row['folder_scope']) ? (string) $folder_row['folder_scope'] : '';
        $committee_id = isset($folder_row['committee_id']) ? (int) $folder_row['committee_id'] : 0;

        $row['folder_name'] = isset($folder_row['folder_name']) ? (string) $folder_row['folder_name'] : '';
        $row['folder_scope_type'] = $scope;
        $row['committee_id'] = $committee_id;
        $row['committee_name'] = ($scope === 'Committee' && isset($committee_names[$committee_id])) ? $committee_names[$committee_id] : '';

        $enriched_rows[] = $row;
    }

    usort($enriched_rows, function ($a, $b) {
        $a_date = isset($a['document_date']) ? strtotime((string) $a['document_date']) : false;
        $b_date = isset($b['document_date']) ? strtotime((string) $b['document_date']) : false;
        $a_shared = isset($a['shared_with_members_at']) ? strtotime((string) $a['shared_with_members_at']) : false;
        $b_shared = isset($b['shared_with_members_at']) ? strtotime((string) $b['shared_with_members_at']) : false;

        $a_sort = $a_date !== false ? $a_date : ($a_shared !== false ? $a_shared : 0);
        $b_sort = $b_date !== false ? $b_date : ($b_shared !== false ? $b_shared : 0);

        if ($a_sort === $b_sort) {
            $a_title = isset($a['document_title']) ? (string) $a['document_title'] : '';
            $b_title = isset($b['document_title']) ? (string) $b['document_title'] : '';
            return strcasecmp($a_title, $b_title);
        }

        return $b_sort <=> $a_sort;
    });

    return $enriched_rows;
}

function pba_render_member_share_toggle_cell($document_item_id) {
    $document_item_id = (int) $document_item_id;

    if ($document_item_id < 1) {
        return '';
    }

    $context = pba_get_document_origin_context($document_item_id);

    if (!$context) {
        return '<span class="pba-doc-share-status">Unavailable</span>';
    }

    if (empty($context['is_active'])) {
        return '<span class="pba-doc-share-status">Inactive document</span>';
    }

    $can_manage = pba_current_person_can_share_document_with_members($document_item_id);
    $is_shared = !empty($context['visible_to_all_members']);
    $status_label = $is_shared ? 'Shared with members' : 'Private';
    $share_time = !empty($context['shared_with_members_at']) ? pba_format_datetime_display($context['shared_with_members_at']) : '';

    ob_start();
    ?>
    <div class="pba-doc-share-cell">
        <div class="pba-doc-share-status <?php echo $is_shared ? 'is-shared' : 'is-private'; ?>">
            <?php echo esc_html($status_label); ?>
        </div>
        <?php if ($is_shared && $share_time !== '') : ?>
            <div class="pba-doc-share-meta"><?php echo esc_html($share_time); ?></div>
        <?php endif; ?>
        <?php if ($can_manage) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-doc-share-form">
                <?php if ($is_shared) : ?>
                    <?php wp_nonce_field('pba_unshare_document_with_members_' . $document_item_id, 'pba_doc_share_nonce'); ?>
                    <input type="hidden" name="action" value="pba_unshare_document_with_members">
                    <button type="submit" class="pba-documents-btn secondary pba-doc-share-button">Remove member access</button>
                <?php else : ?>
                    <?php wp_nonce_field('pba_share_document_with_members_' . $document_item_id, 'pba_doc_share_nonce'); ?>
                    <input type="hidden" name="action" value="pba_share_document_with_members">
                    <button type="submit" class="pba-documents-btn secondary pba-doc-share-button">Share with members</button>
                <?php endif; ?>
                <input type="hidden" name="document_item_id" value="<?php echo esc_attr((string) $document_item_id); ?>">
                <?php if (isset($_SERVER['REQUEST_URI'])) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr(wp_unslash($_SERVER['REQUEST_URI'])); ?>">
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_handle_share_document_with_members() {
    pba_handle_member_share_update(true);
}

function pba_handle_unshare_document_with_members() {
    pba_handle_member_share_update(false);
}

function pba_handle_member_share_update($share_with_members) {
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to perform this action.');
    }

    $document_item_id = isset($_POST['document_item_id']) ? (int) $_POST['document_item_id'] : 0;
    $redirect_to = isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '';

    if ($document_item_id < 1) {
        pba_member_share_redirect($redirect_to, 'invalid_document_delete');
    }

    $nonce_action = ($share_with_members ? 'pba_share_document_with_members_' : 'pba_unshare_document_with_members_') . $document_item_id;

    if (empty($_POST['pba_doc_share_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pba_doc_share_nonce'])), $nonce_action)) {
        pba_member_share_redirect($redirect_to, 'invalid_nonce');
    }

    $before = pba_document_permission_get_item_snapshot($document_item_id);
    $document_item = pba_get_document_item($document_item_id);

    if (!$document_item) {
        pba_member_share_redirect($redirect_to, 'invalid_document');
    }

    if (empty($document_item['is_active'])) {
        pba_member_share_redirect($redirect_to, 'inactive_document');
    }

    if (!pba_current_person_can_share_document_with_members($document_item_id)) {
        pba_member_share_redirect($redirect_to, 'permission_denied');
    }

    $origin_context = pba_get_document_origin_context($document_item_id);
    $folder_id = ($origin_context && isset($origin_context['document_folder_id'])) ? (int) $origin_context['document_folder_id'] : null;
    $committee_id = ($origin_context && isset($origin_context['committee_id']) && (int) $origin_context['committee_id'] > 0)
        ? (int) $origin_context['committee_id']
        : null;

    $person_id = function_exists('pba_current_person_id') ? (int) pba_current_person_id() : 0;
    $payload = array(
        'visible_to_all_members' => $share_with_members,
    );

    if ($share_with_members) {
        $payload['shared_with_members_at'] = current_time('c', true);
        $payload['shared_with_members_by_person_id'] = $person_id > 0 ? $person_id : null;
    } else {
        $payload['shared_with_members_at'] = null;
        $payload['shared_with_members_by_person_id'] = null;
    }

    $result = pba_member_resources_supabase_update('Document_Item', $payload, array(
        'document_item_id' => 'eq.' . $document_item_id,
    ));

    if (is_wp_error($result)) {
        pba_document_permission_audit_log(
            $share_with_members ? 'document.shared' : 'document.unshared',
            'Document_Item',
            $document_item_id,
            array(
                'entity_label'              => pba_document_permission_get_item_label($before ?: $document_item),
                'target_committee_id'       => $committee_id,
                'target_document_folder_id' => $folder_id,
                'target_document_item_id'   => $document_item_id,
                'result_status'             => 'failure',
                'summary'                   => $share_with_members
                    ? 'Failed to share document with members.'
                    : 'Failed to remove member access from document.',
                'before'                    => $before,
                'details'                   => array(
                    'error_code'    => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                    'share_with_members' => (bool) $share_with_members,
                ),
            )
        );

        pba_member_share_redirect($redirect_to, $share_with_members ? 'share_failed' : 'unshare_failed');
    }

    $after = pba_document_permission_get_item_snapshot($document_item_id);

    pba_document_permission_audit_log(
        $share_with_members ? 'document.shared' : 'document.unshared',
        'Document_Item',
        $document_item_id,
        array(
            'entity_label'              => pba_document_permission_get_item_label($after ?: $before ?: $document_item),
            'target_committee_id'       => $committee_id,
            'target_document_folder_id' => $folder_id,
            'target_document_item_id'   => $document_item_id,
            'summary'                   => $share_with_members
                ? 'Document shared with members.'
                : 'Member access removed from document.',
            'before'                    => $before,
            'after'                     => $after,
        )
    );

    pba_member_share_redirect($redirect_to, $share_with_members ? 'shared_with_members' : 'removed_from_member_resources');
}

function pba_member_resources_supabase_update($table, $payload, $where_args = array()) {
    if (function_exists('pba_supabase_update')) {
        return pba_supabase_update($table, $payload, $where_args);
    }

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);

    if (!empty($where_args)) {
        $url = add_query_arg($where_args, $url);
    }

    $response = wp_remote_request($url, array(
        'method'  => 'PATCH',
        'timeout' => 20,
        'headers' => array(
            'apikey'        => SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ),
        'body' => wp_json_encode($payload),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return new WP_Error('supabase_patch_failed', 'Supabase PATCH failed', array(
            'status' => $status,
            'body'   => $body,
            'table'  => $table,
            'query'  => $where_args,
        ));
    }

    if ($body === '' || $data === null) {
        return array();
    }

    return $data;
}

function pba_member_share_redirect($redirect_to, $status) {
    $redirect_url = $redirect_to !== '' ? $redirect_to : home_url('/member-resources/');
    $redirect_url = add_query_arg('pba_documents_status', sanitize_key($status), $redirect_url);
    wp_safe_redirect($redirect_url);
    exit;
}
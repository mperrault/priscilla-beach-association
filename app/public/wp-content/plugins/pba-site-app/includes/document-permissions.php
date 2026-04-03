<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_current_person_is_admin() {
    return pba_current_person_has_role('PBAAdmin');
}

function pba_current_person_is_board_member() {
    return pba_current_person_has_role('PBABoardMember');
}

function pba_current_person_is_committee_member() {
    return pba_current_person_has_role('PBACommitteeMember');
}

function pba_current_person_id() {
    $person = pba_get_current_person_record();

    if (!$person || empty($person['person_id'])) {
        return 0;
    }

    return (int) $person['person_id'];
}

function pba_current_person_has_active_committee_assignment($committee_id) {
    $person_id = pba_current_person_id();
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

    $person_id = pba_current_person_id();

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
    $folder_id = (int) $folder_id;

    if ($folder_id < 1) {
        return false;
    }

    $rows = pba_supabase_get('Document_Folder', array(
        'select'             => 'document_folder_id,folder_name,folder_scope,committee_id,parent_folder_id,display_order,is_active,created_by_person_id,notes,last_modified_at',
        'document_folder_id' => 'eq.' . $folder_id,
        'limit'              => 1,
    ));

    if (is_wp_error($rows) || empty($rows[0])) {
        return false;
    }

    return $rows[0];
}

function pba_current_person_can_manage_board_folders() {
    return pba_current_person_is_admin() || pba_current_person_is_board_member();
}

function pba_current_person_can_manage_committee_folder($committee_id) {
    if (pba_current_person_is_admin()) {
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

    if ($scope === 'Admin') {
        return pba_current_person_is_admin();
    }

    if ($scope === 'Board') {
        return pba_current_person_can_manage_board_folders();
    }

    if ($scope === 'Committee') {
        return pba_current_person_can_manage_committee_folder((int) ($folder['committee_id'] ?? 0));
    }

    return false;
}

function pba_current_person_can_create_folder($folder_scope, $committee_id = 0) {
    $folder_scope = (string) $folder_scope;
    $committee_id = (int) $committee_id;

    if ($folder_scope === 'Admin') {
        return pba_current_person_is_admin();
    }

    if ($folder_scope === 'Board') {
        return pba_current_person_can_manage_board_folders();
    }

    if ($folder_scope === 'Committee') {
        return pba_current_person_can_manage_committee_folder($committee_id);
    }

    return false;
}

function pba_current_person_can_view_folder($folder_id) {
    return pba_current_person_can_manage_folder($folder_id);
}

function pba_get_active_document_folders($folder_scope, $committee_id = null) {
    $args = array(
        'select'    => 'document_folder_id,folder_name,folder_scope,committee_id,parent_folder_id,display_order,is_active,created_by_person_id,notes,last_modified_at',
        'folder_scope' => 'eq.' . $folder_scope,
        'is_active' => 'eq.true',
        'order'     => 'display_order.asc,folder_name.asc',
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
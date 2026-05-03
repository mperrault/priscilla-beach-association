<?php
/**
 * PBA Photo Feature - Upload Handlers
 *
 * Handles PBAMember photo submissions.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_pba_photo_upload', 'pba_handle_photo_upload');
add_action('admin_post_nopriv_pba_photo_upload', 'pba_handle_photo_upload');

function pba_handle_photo_upload() {
    $redirect_url = home_url('/photo-upload/');

    if (!pba_photo_current_user_can_upload()) {
        pba_photo_audit_upload_failure('Photo upload failed because the user was not allowed to upload.', array(
            'reason' => 'not_allowed_or_not_logged_in',
        ));

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'not_allowed',
            ),
            $redirect_url
        ));
        exit;
    }

    if (
        empty($_POST['pba_photo_upload_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pba_photo_upload_nonce'])), 'pba_photo_upload')
    ) {
        pba_photo_audit_upload_failure('Photo upload failed nonce validation.');

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'security_error',
            ),
            $redirect_url
        ));
        exit;
    }

    if (empty($_FILES['pba_photo_file'])) {
        pba_photo_audit_upload_failure('Photo upload failed because no file was submitted.');

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'missing_file',
            ),
            $redirect_url
        ));
        exit;
    }

    $title = isset($_POST['pba_photo_title'])
        ? sanitize_text_field(wp_unslash($_POST['pba_photo_title']))
        : '';

    $caption = isset($_POST['pba_photo_caption'])
        ? sanitize_textarea_field(wp_unslash($_POST['pba_photo_caption']))
        : '';

    $photographer_name = isset($_POST['pba_photo_photographer_name'])
        ? sanitize_text_field(wp_unslash($_POST['pba_photo_photographer_name']))
        : '';

    $suggested_collection_id = isset($_POST['pba_photo_suggested_collection_id'])
        ? absint($_POST['pba_photo_suggested_collection_id'])
        : 0;

    $permission_confirmed = !empty($_POST['pba_photo_permission_confirmed']);

    if (!$permission_confirmed) {
        pba_photo_audit_upload_failure('Photo upload failed because permission confirmation was not checked.');

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'permission_required',
            ),
            $redirect_url
        ));
        exit;
    }

    $processed = pba_photo_process_uploaded_photo($_FILES['pba_photo_file']);

    if (is_wp_error($processed)) {
        pba_photo_audit_processing_failure(
            $processed->get_error_message(),
            array(
                'error_code' => $processed->get_error_code(),
                'error_data' => $processed->get_error_data(),
            )
        );

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'processing_failed',
            ),
            $redirect_url
        ));
        exit;
    }

    $storage_path = pba_photo_generate_storage_path('jpg');
    $thumbnail_storage_path = str_replace('/photo-', '/thumb-', $storage_path);

    $upload_result = pba_photo_storage_upload_file(
        $processed['processed_path'],
        $storage_path,
        $processed['processed_mime_type']
    );

    if (is_wp_error($upload_result)) {
        pba_photo_audit_storage_upload_failure(
            $upload_result->get_error_message(),
            array(
                'error_code'    => $upload_result->get_error_code(),
                'storage_path'  => $storage_path,
                'original_file' => isset($processed['original_filename']) ? $processed['original_filename'] : null,
                'error_data'    => $upload_result->get_error_data(),
            )
        );

        pba_photo_cleanup_processed_file($processed);

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'storage_failed',
            ),
            $redirect_url
        ));
        exit;
    }
    $thumbnail_upload_result = pba_photo_storage_upload_file(
        $processed['thumbnail_path'],
        $thumbnail_storage_path,
        $processed['thumbnail_mime_type']
    );

    if (is_wp_error($thumbnail_upload_result)) {
        pba_photo_storage_delete_file($storage_path, PBA_PHOTO_STORAGE_BUCKET);

        pba_photo_audit_storage_upload_failure(
            $thumbnail_upload_result->get_error_message(),
            array(
                'error_code'             => $thumbnail_upload_result->get_error_code(),
                'storage_path'           => $storage_path,
                'thumbnail_storage_path' => $thumbnail_storage_path,
                'original_file'          => isset($processed['original_filename']) ? $processed['original_filename'] : null,
                'error_data'             => $thumbnail_upload_result->get_error_data(),
            )
        );

        pba_photo_cleanup_processed_file($processed);

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'storage_failed',
            ),
            $redirect_url
        ));
        exit;
    }
    $current_user = wp_get_current_user();

    $photo_row = array(
        'uploaded_by_person_id'     => pba_photo_get_current_person_id(),
        'uploaded_by_wp_user_id'    => get_current_user_id(),
        'uploader_name'             => ($current_user && $current_user->exists()) ? $current_user->display_name : null,
        'uploader_email'            => ($current_user && $current_user->exists()) ? $current_user->user_email : null,

        'title'                     => $title !== '' ? $title : null,
        'caption'                   => $caption !== '' ? $caption : null,
        'photographer_name'         => $photographer_name !== '' ? $photographer_name : null,
        'suggested_collection_id'   => $suggested_collection_id > 0 ? $suggested_collection_id : null,

        'storage_bucket'            => PBA_PHOTO_STORAGE_BUCKET,
        'storage_path'              => $storage_path,

        'original_filename'         => isset($processed['original_filename']) ? $processed['original_filename'] : null,
        'original_file_size_bytes'  => isset($processed['original_file_size_bytes']) ? (int) $processed['original_file_size_bytes'] : null,

        'processed_file_size_bytes' => isset($processed['processed_size_bytes']) ? (int) $processed['processed_size_bytes'] : null,
        'processed_width'           => isset($processed['processed_width']) ? (int) $processed['processed_width'] : null,
        'processed_height'          => isset($processed['processed_height']) ? (int) $processed['processed_height'] : null,
        'mime_type'                 => isset($processed['processed_mime_type']) ? $processed['processed_mime_type'] : 'image/jpeg',

        'status'                    => 'pending',
        'visibility'                => 'public',
        'is_featured'               => false,
        'sort_order'                => 0,
        'thumbnail_storage_path'     => $thumbnail_storage_path,
        'thumbnail_file_size_bytes'  => isset($processed['thumbnail_size_bytes']) ? (int) $processed['thumbnail_size_bytes'] : null,
        'thumbnail_width'            => isset($processed['thumbnail_width']) ? (int) $processed['thumbnail_width'] : null,
        'thumbnail_height'           => isset($processed['thumbnail_height']) ? (int) $processed['thumbnail_height'] : null,
        'thumbnail_mime_type'        => isset($processed['thumbnail_mime_type']) ? $processed['thumbnail_mime_type'] : 'image/jpeg',
    );

    $insert_result = pba_photo_supabase_insert('Photo', $photo_row);

    pba_photo_cleanup_processed_file($processed);

    if (is_wp_error($insert_result)) {
        /*
         * The file has already been uploaded. Try to remove it so orphaned files
         * do not consume Supabase Storage capacity.
         */
        pba_photo_storage_delete_file($storage_path, PBA_PHOTO_STORAGE_BUCKET);
        pba_photo_storage_delete_file($thumbnail_storage_path, PBA_PHOTO_STORAGE_BUCKET);
        pba_photo_audit_upload_failure(
            'Photo database insert failed after storage upload.',
            array(
                'error_message' => $insert_result->get_error_message(),
                'error_code'    => $insert_result->get_error_code(),
                'storage_path'  => $storage_path,
                'error_data'    => $insert_result->get_error_data(),
            )
        );

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'database_failed',
            ),
            $redirect_url
        ));
        exit;
    }

    $photo_id = null;
    $inserted_row = null;

    if (is_array($insert_result) && !empty($insert_result[0]) && is_array($insert_result[0])) {
        $inserted_row = $insert_result[0];

        if (isset($inserted_row['photo_id'])) {
            $photo_id = (int) $inserted_row['photo_id'];
        }
    }

    if (!$photo_id) {
        /*
         * The database insert apparently succeeded, but did not return the new row.
         * Remove the uploaded storage file so we do not leave an orphan.
         */
        pba_photo_storage_delete_file($storage_path, PBA_PHOTO_STORAGE_BUCKET);
        pba_photo_storage_delete_file($thumbnail_storage_path, PBA_PHOTO_STORAGE_BUCKET);
        pba_photo_audit_upload_failure(
            'Photo database insert succeeded but no photo_id was returned.',
            array(
                'storage_path'  => $storage_path,
                'insert_result' => $insert_result,
            )
        );

        wp_safe_redirect(add_query_arg(
            array(
                'pba_photo_message' => 'database_failed',
            ),
            $redirect_url
        ));
        exit;
    }

    pba_photo_audit_upload_success($photo_id, $inserted_row ? $inserted_row : $photo_row);

    wp_safe_redirect(add_query_arg(
        array(
            'pba_photo_message' => 'upload_success',
        ),
        $redirect_url
    ));
    exit;
}

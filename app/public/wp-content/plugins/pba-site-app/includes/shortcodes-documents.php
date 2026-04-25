<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_document_shortcodes');

add_filter('query_vars', function($vars) {
    $vars[] = 'pba_document_view';
    return $vars;
});

add_action('template_redirect', function() {

    $document_item_id = absint(get_query_var('pba_document_view'));

    if ($document_item_id < 1) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    if (!function_exists('pba_supabase_get')) {
        wp_die('Data access error.');
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select' => '*',
        'document_item_id' => 'eq.' . $document_item_id,
        'limit' => 1,
    ));

    if (empty($rows)) {
        wp_die('Document not found.');
    }

    $doc = $rows[0];

    $file_url  = $doc['file_url'];
    $file_name = !empty($doc['file_name']) ? $doc['file_name'] : 'Document';

    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo esc_html($file_name); ?></title>

        <?php
        $pba_favicon_url = get_stylesheet_directory_uri() . '/assets/images/favicon-pba.png';
        $pba_favicon_ver = file_exists(get_stylesheet_directory() . '/assets/images/favicon-pba.png')
            ? filemtime(get_stylesheet_directory() . '/assets/images/favicon-pba.png')
            : time();
        ?>

        <link rel="icon" type="image/png" href="<?php echo esc_url($pba_favicon_url . '?v=' . $pba_favicon_ver); ?>">
        <link rel="shortcut icon" type="image/png" href="<?php echo esc_url($pba_favicon_url . '?v=' . $pba_favicon_ver); ?>">

        <style>
            html, body {
                margin:0;
                padding:0;
                height:100%;
                background:#f5f7f8;
                font-family: Arial, sans-serif;
            }
            .pba-doc-header {
                height:48px;
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:0 16px;
                background:#ffffff;
                border-bottom:1px solid #d9e0e4;
            }
            .pba-doc-title {
                font-weight:600;
                font-size:14px;
            }
            .pba-doc-download {
                color:#1d5f7a;
                text-decoration:none;
                font-weight:600;
            }
            iframe {
                width:100%;
                height:calc(100vh - 48px);
                border:0;
            }
        </style>
    </head>
    <body>
        <div class="pba-doc-header">
            <div class="pba-doc-title"><?php echo esc_html($file_name); ?></div>
            <a class="pba-doc-download" href="<?php echo esc_url($file_url); ?>" download>Download</a>
        </div>
        <object
            data="<?php echo esc_url($file_url); ?>"
            type="application/pdf"
            style="width:100%; height:calc(100vh - 48px); border:0;"
            aria-label="<?php echo esc_attr($file_name); ?>">
            <p>
                Unable to preview this document.
                <a href="<?php echo esc_url($file_url); ?>">Open document</a>
            </p>
        </object>    
    </body>
    </html>
    <?php
    exit;
});

function pba_register_document_shortcodes() {

    $base_url = plugin_dir_url(__FILE__) . 'css/';
    $base_path = dirname(__FILE__) . '/css/';

    wp_enqueue_style(
        'pba-documents',
        $base_url . 'pba-documents.css',
        array(),
        file_exists($base_path . 'pba-documents.css') ? (string) filemtime($base_path . 'pba-documents.css') : '1.0.0'
    );
    add_shortcode('pba_board_documents', 'pba_render_board_documents_shortcode');
    add_shortcode('pba_committee_documents', 'pba_render_committee_documents_shortcode');
}

function pba_get_document_viewer_url($document_item_id) {
    $document_item_id = absint($document_item_id);

    if ($document_item_id < 1) {
        return '';
    }

    return add_query_arg('pba_document_view', $document_item_id, home_url('/'));
}

function pba_render_documents_status_message() {
    $status = isset($_GET['pba_documents_status']) ? sanitize_text_field(wp_unslash($_GET['pba_documents_status'])) : '';

    if ($status === '') {
        return '';
    }

    $messages = array(
        'document_uploaded'             => 'Document uploaded successfully.',
        'document_saved'                => 'Document metadata saved successfully.',
        'document_deleted'              => 'Document deactivated successfully.',
        'document_restored'             => 'Document restored successfully.',
        'document_permanently_deleted'  => 'Document permanently deleted.',
        'folder_created'                => 'Folder created successfully.',
        'folder_renamed'                => 'Folder renamed successfully.',
        'folder_deleted'                => 'Folder deactivated successfully.',
        'shared_with_members'           => 'Document is now visible in Member Resources.',
        'removed_from_member_resources' => 'Document was removed from Member Resources.',
        'share_failed'                  => 'The document could not be shared with members.',
        'unshare_failed'                => 'The document could not be removed from Member Resources.',
        'permission_denied'             => 'You do not have permission to perform that action.',
        'missing_document_file'         => 'Please choose a file to upload.',
        'empty_document_file'           => 'The selected file appears to be empty.',
        'document_file_too_large'       => 'The selected file is too large. Maximum allowed size is ' . pba_get_document_upload_max_size_label() . '.',
        'invalid_document_file_type'    => 'That file type is not allowed. Allowed types: PDF, Word, Excel, PowerPoint, TXT, CSV, JPG, JPEG, PNG.',
        'invalid_document_date'         => 'Please enter a valid document date.',
        'invalid_document_edit'         => 'Please choose a valid document to edit.',
        'document_edit_failed'          => 'The document metadata could not be saved.',
        'document_upload_failed'        => 'The file upload failed.',
        'document_record_create_failed' => 'The file uploaded, but the document record could not be saved.',
        'invalid_document_folder'       => 'Please choose a valid folder.',
        'invalid_document_delete'       => 'Please choose a valid document.',
        'invalid_document_restore'      => 'Please choose a valid document to restore.',
        'document_not_found'            => 'The selected document could not be found.',
        'document_delete_failed'        => 'The document could not be deleted.',
        'document_restore_failed'       => 'The document could not be restored.',
        'invalid_folder_input'          => 'Please enter a valid folder name.',
        'invalid_folder_rename'         => 'Please enter a valid new folder name.',
        'folder_create_failed'          => 'The folder could not be created.',
        'folder_rename_failed'          => 'The folder could not be renamed.',
        'folder_delete_failed'          => 'The folder could not be deactivated.',
    );

    $message = isset($messages[$status]) ? $messages[$status] : ucfirst(str_replace('_', ' ', $status));
    $success_statuses = array(
        'document_uploaded',
        'document_saved',
        'document_deleted',
        'document_restored',
        'document_permanently_deleted',
        'folder_created',
        'folder_renamed',
        'folder_deleted',
        'shared_with_members',
        'removed_from_member_resources',
    );
    $class = in_array($status, $success_statuses, true) ? 'pba-documents-message' : 'pba-documents-message error';

    return '<div class="' . esc_attr($class) . '">' . esc_html($message) . '</div>';
}

function pba_get_document_items_for_folder($folder_id, $is_active = true) {
    $folder_id = (int) $folder_id;

    if ($folder_id < 1) {
        return array();
    }

    $rows = pba_supabase_get('Document_Item', array(
        'select'             => 'document_item_id,document_folder_id,file_name,file_url,mime_type,document_title,document_date,document_category,document_version,uploaded_by_person_id,last_modified_by_person_id,is_active,notes,last_modified_at,visible_to_all_members,shared_with_members_at,shared_with_members_by_person_id,member_summary',
        'document_folder_id' => 'eq.' . $folder_id,
        'is_active'          => 'eq.' . ($is_active ? 'true' : 'false'),
        'order'              => 'document_date.desc,last_modified_at.desc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_render_document_upload_form($page_slug, $folder_id, $committee_id = 0) {
    if (!pba_current_person_can_upload_to_folder($folder_id)) {
        return '';
    }

    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="pba-documents-upload-form">
        <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
        <input type="hidden" name="action" value="pba_upload_document_item">
        <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
        <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder_id); ?>">
        <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">

        <div class="pba-documents-upload-grid">
            <div class="pba-documents-field">
                <label class="pba-documents-label">File</label>
                <input type="file" name="document_file" required>
            </div>
            <div class="pba-documents-field">
                <label class="pba-documents-label">Title</label>
                <input type="text" name="document_title" placeholder="Optional document title" class="pba-documents-input">
            </div>
            <div class="pba-documents-field">
                <label class="pba-documents-label">Date</label>
                <input type="date" name="document_date" class="pba-documents-input">
            </div>
            <div class="pba-documents-field">
                <label class="pba-documents-label">Category</label>
                <input type="text" name="document_category" placeholder="Agenda, Minutes, Budget..." class="pba-documents-input">
            </div>
            <div class="pba-documents-field">
                <label class="pba-documents-label">Version</label>
                <input type="text" name="document_version" placeholder="Draft 1, Final..." class="pba-documents-input">
            </div>
            <div class="pba-documents-field">
                <label class="pba-documents-label">Notes</label>
                <input type="text" name="notes" placeholder="Optional notes" class="pba-documents-input">
            </div>
        </div>

        <div class="pba-documents-toolbar-actions">
            <button type="submit" class="pba-documents-btn">Upload</button>
        </div>

        <div class="pba-documents-muted pba-documents-upload-help">
            Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, JPG, JPEG, PNG. Max <?php echo esc_html(pba_get_document_upload_max_size_label()); ?>.
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function pba_render_documents_table_controls($table_id, $label) {
    ob_start();
    ?>
    <div class="pba-documents-table-shell" data-table-id="<?php echo esc_attr($table_id); ?>">
        <div class="pba-documents-table-toolbar">
            <div class="pba-documents-table-toolbar-left">
                <h5 class="pba-documents-subsection-title"><?php echo esc_html($label); ?></h5>
            </div>
            <div class="pba-documents-table-toolbar-right">
                <input
                    type="search"
                    class="pba-documents-table-search pba-documents-input"
                    placeholder="Search documents..."
                    aria-label="<?php echo esc_attr($label); ?> search"
                >
                <select class="pba-documents-table-sort pba-documents-input" aria-label="<?php echo esc_attr($label); ?> sort">
                    <option value="date_desc">Newest date</option>
                    <option value="date_asc">Oldest date</option>
                    <option value="modified_desc">Recently modified</option>
                    <option value="modified_asc">Least recently modified</option>
                    <option value="title_asc">Title A-Z</option>
                    <option value="title_desc">Title Z-A</option>
                    <option value="category_asc">Category A-Z</option>
                    <option value="category_desc">Category Z-A</option>
                </select>
                <select class="pba-documents-table-page-size pba-documents-input" aria-label="<?php echo esc_attr($label); ?> page size">
                    <option value="5">5 / page</option>
                    <option value="10" selected>10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                </select>
            </div>
        </div>
    <?php
    return ob_get_clean();
}

function pba_render_document_items_table($items, $page_slug, $committee_id = 0, $is_active = true) {
    $table_id = 'pba-doc-table-' . wp_generate_uuid4();

    ob_start();
    echo pba_render_documents_table_controls($table_id, $is_active ? 'Active Documents' : 'Inactive Documents');
    ?>
        <div class="pba-documents-table-wrap">
            <table class="pba-documents-table" id="<?php echo esc_attr($table_id); ?>">
                <thead>
                    <tr>
                        <th>Title / File</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Member Access</th>
                        <th>Last Modified</th>
                        <th>Last Modified By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr class="pba-documents-empty-row">
                            <td colspan="7"><?php echo $is_active ? 'No active documents uploaded yet.' : 'No inactive documents.'; ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $display_title = !empty($item['document_title']) ? $item['document_title'] : ($item['file_name'] ?? '');
                            $document_date = !empty($item['document_date']) ? $item['document_date'] : '';
                            $title_text = strtolower(trim((string) $display_title));
                            $file_name_text = strtolower(trim((string) ($item['file_name'] ?? '')));
                            $category_text = strtolower(trim((string) ($item['document_category'] ?? '')));
                            $version_text = strtolower(trim((string) ($item['document_version'] ?? '')));
                            $notes_text = strtolower(trim((string) ($item['notes'] ?? '')));
                            $mime_text = strtolower(trim((string) ($item['mime_type'] ?? '')));
                            $modified_display = pba_format_datetime_display($item['last_modified_at'] ?? '');
                            $modified_by_person_id = isset($item['last_modified_by_person_id']) ? (int) $item['last_modified_by_person_id'] : 0;
                            $modified_by_label = $modified_by_person_id > 0 ? pba_get_person_display_name($modified_by_person_id) : '';
                            $member_summary = trim((string) ($item['member_summary'] ?? ''));
                            if ($modified_by_label === '') {
                                $modified_by_label = '—';
                            }
                            ?>
                            <tr
                                class="pba-documents-data-row"
                                data-title="<?php echo esc_attr($title_text); ?>"
                                data-file-name="<?php echo esc_attr($file_name_text); ?>"
                                data-date="<?php echo esc_attr($document_date); ?>"
                                data-category="<?php echo esc_attr($category_text); ?>"
                                data-version="<?php echo esc_attr($version_text); ?>"
                                data-type="<?php echo esc_attr($mime_text); ?>"
                                data-notes="<?php echo esc_attr($notes_text); ?>"
                                data-modified="<?php echo esc_attr((string) ($item['last_modified_at'] ?? '')); ?>"
                                <?php if ($is_active) : ?>
                                    data-item-json="<?php echo esc_attr(wp_json_encode(array(
                                        'document_item_id'          => (int) ($item['document_item_id'] ?? 0),
                                        'document_title'            => (string) ($item['document_title'] ?? ''),
                                        'document_date'             => (string) ($item['document_date'] ?? ''),
                                        'document_category'         => (string) ($item['document_category'] ?? ''),
                                        'document_version'          => (string) ($item['document_version'] ?? ''),
                                        'notes'                     => (string) ($item['notes'] ?? ''),
                                        'member_summary'            => (string) ($item['member_summary'] ?? ''),
                                        'last_modified_display'     => (string) $modified_display,
                                        'last_modified_by_display'  => (string) $modified_by_label,
                                    ))); ?>"
                                <?php endif; ?>
                            >
                                <td>
                                    <div class="pba-documents-title-cell">
                                        <?php if (!empty($item['file_url'])) : ?>
                                            <a href="<?php echo esc_url(pba_get_document_viewer_url($item['document_item_id'])); ?>" target="_blank" rel="noopener">
                                                <?php echo esc_html($display_title); ?>    
                                            </a>    
                                       <?php else : ?>
                                            <?php echo esc_html($display_title); ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($item['document_title']) && !empty($item['file_name'])) : ?>
                                        <div class="pba-documents-muted"><?php echo esc_html($item['file_name']); ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['document_version']) || !empty($item['notes']) || $member_summary !== '') : ?>
                                        <div class="pba-documents-title-meta">
                                            <?php if (!empty($item['document_version'])) : ?>
                                                <span class="pba-documents-inline-chip">Version: <?php echo esc_html($item['document_version']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['notes'])) : ?>
                                                <span class="pba-documents-inline-chip">Notes: <?php echo esc_html($item['notes']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($member_summary !== '') : ?>
                                                <span class="pba-documents-inline-chip">Member Summary: <?php echo esc_html($member_summary); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pba-documents-inline-actions">
                                        <?php if ($is_active) : ?>
                                            <button
                                                type="button"
                                                class="pba-documents-btn secondary pba-documents-open-editor"
                                            >
                                                Edit Metadata
                                            </button>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline" onsubmit="return confirm('Deactivate this document?');">
                                                <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_deactivate_document_item">
                                                <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
                                                <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
                                                <input type="hidden" name="document_item_id" value="<?php echo esc_attr((int) $item['document_item_id']); ?>">
                                                <button type="submit" class="pba-documents-btn secondary">Deactivate</button>
                                            </form>
                                        <?php else : ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline">
                                                <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_restore_document_item">
                                                <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
                                                <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
                                                <input type="hidden" name="document_item_id" value="<?php echo esc_attr((int) $item['document_item_id']); ?>">
                                                <button type="submit" class="pba-documents-btn secondary">Restore</button>
                                            </form>

                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline" onsubmit="return confirm('Permanently delete this document and remove the file from WordPress uploads?');">
                                                <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
                                                <input type="hidden" name="action" value="pba_delete_document_item_permanently">
                                                <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
                                                <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
                                                <input type="hidden" name="document_item_id" value="<?php echo esc_attr((int) $item['document_item_id']); ?>">
                                                <button type="submit" class="pba-documents-btn secondary">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($document_date); ?></td>
                                <td><?php echo esc_html($item['document_category'] ?? ''); ?></td>
                                <td><?php echo esc_html($item['mime_type'] ?? ''); ?></td>
                                <td class="pba-documents-member-access-cell"><?php echo function_exists('pba_render_member_share_toggle_cell') ? pba_render_member_share_toggle_cell((int) ($item['document_item_id'] ?? 0)) : '—'; ?></td>
                                <td><?php echo esc_html($modified_display); ?></td>
                                <td><?php echo esc_html($modified_by_label); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pba-documents-table-footer">
            <div class="pba-documents-table-summary" aria-live="polite"></div>
            <div class="pba-documents-pagination">
                <button type="button" class="pba-documents-btn secondary pba-documents-page-prev">Previous</button>
                <span class="pba-documents-page-status"></span>
                <button type="button" class="pba-documents-btn secondary pba-documents-page-next">Next</button>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_documents_metadata_drawer($page_slug, $committee_id = 0) {
    ob_start();
    ?>
    <div class="pba-documents-metadata-drawer" style="display:none;">
        <div class="pba-documents-metadata-drawer-head">
            <h5 class="pba-documents-subsection-title">Edit Document Metadata</h5>
            <button type="button" class="pba-documents-btn secondary pba-documents-close-editor">Close</button>
        </div>

        <div class="pba-documents-readonly-meta">
            <div class="pba-documents-readonly-meta-item">
                <span class="pba-documents-readonly-meta-label">Last Modified</span>
                <span class="pba-documents-readonly-meta-value" data-meta-field="last_modified_display">—</span>
            </div>
            <div class="pba-documents-readonly-meta-item">
                <span class="pba-documents-readonly-meta-label">Last Modified By</span>
                <span class="pba-documents-readonly-meta-value" data-meta-field="last_modified_by_display">—</span>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-metadata-form pba-documents-metadata-drawer-form">
            <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
            <input type="hidden" name="action" value="pba_save_document_item_metadata">
            <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
            <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
            <input type="hidden" name="document_item_id" value="">

            <div class="pba-documents-editor-grid">
                <div class="pba-documents-field">
                    <label class="pba-documents-label">Title</label>
                    <input type="text" name="document_title" value="" class="pba-documents-input">
                </div>
                <div class="pba-documents-field">
                    <label class="pba-documents-label">Date</label>
                    <input type="date" name="document_date" value="" class="pba-documents-input">
                </div>
                <div class="pba-documents-field">
                    <label class="pba-documents-label">Category</label>
                    <input type="text" name="document_category" value="" class="pba-documents-input">
                </div>
                <div class="pba-documents-field">
                    <label class="pba-documents-label">Version</label>
                    <input type="text" name="document_version" value="" class="pba-documents-input">
                </div>
                <div class="pba-documents-field pba-documents-editor-span">
                    <label class="pba-documents-label">Notes</label>
                    <input type="text" name="notes" value="" class="pba-documents-input">
                    <div class="pba-documents-muted">Use notes for internal context. Member Resources falls back to notes when no separate member summary is available.</div>
                </div>
            </div>

            <div class="pba-documents-editor-actions">
                <button type="submit" class="pba-documents-btn secondary">Save Metadata</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_documents_folder_card($folder, $page_slug, $committee_id = 0) {
    $folder_id = (int) ($folder['document_folder_id'] ?? 0);
    $active_items = pba_get_document_items_for_folder($folder_id, true);
    $inactive_items = pba_get_document_items_for_folder($folder_id, false);
    $active_count = is_array($active_items) ? count($active_items) : 0;
    $inactive_count = is_array($inactive_items) ? count($inactive_items) : 0;
    $can_upload = pba_current_person_can_upload_to_folder($folder_id);

    ob_start();
    ?>
    <div class="pba-documents-folder-card">
        <button type="button" class="pba-documents-folder-toggle" aria-expanded="false">
            <span class="pba-documents-folder-toggle-main">
                <span class="pba-documents-folder-title"><?php echo esc_html($folder['folder_name'] ?? ''); ?></span>
                <span class="pba-documents-folder-badges">
                    <span class="pba-documents-badge"><?php echo esc_html($active_count); ?> active</span>
                    <?php if ($inactive_count > 0) : ?>
                        <span class="pba-documents-badge muted"><?php echo esc_html($inactive_count); ?> inactive</span>
                    <?php endif; ?>
                </span>
            </span>
            <span class="pba-documents-folder-toggle-meta">
                <span class="pba-documents-muted">Last modified: <?php echo esc_html(pba_format_datetime_display($folder['last_modified_at'] ?? '')); ?></span>
                <span class="pba-documents-folder-toggle-label">Open</span>
            </span>
        </button>

        <div class="pba-documents-folder-panel" style="display:none;">
            <div class="pba-documents-folder-tabs" role="tablist">
                <button type="button" class="pba-documents-folder-tab is-active" data-tab="documents">Documents</button>
                <?php if ($can_upload) : ?>
                    <button type="button" class="pba-documents-folder-tab" data-tab="upload">Upload</button>
                <?php endif; ?>
                <button type="button" class="pba-documents-folder-tab" data-tab="settings">Settings</button>
            </div>

            <div class="pba-documents-folder-tab-panel is-active" data-panel="documents">
                <?php echo pba_render_document_items_table($active_items, $page_slug, $committee_id, true); ?>
                <?php echo pba_render_documents_metadata_drawer($page_slug, $committee_id); ?>

                <div class="pba-documents-inactive-toggle-wrap">
                    <button type="button" class="pba-documents-btn secondary pba-documents-toggle-inactive" data-state="closed">
                        <?php echo $inactive_count > 0 ? 'Show Inactive Documents (' . (int) $inactive_count . ')' : 'Show Inactive Documents'; ?>
                    </button>
                </div>

                <div class="pba-documents-inactive-panel" style="display:none;">
                    <?php echo pba_render_document_items_table($inactive_items, $page_slug, $committee_id, false); ?>
                </div>
            </div>

            <?php if ($can_upload) : ?>
                <div class="pba-documents-folder-tab-panel" data-panel="upload" style="display:none;">
                    <?php echo pba_render_document_upload_form($page_slug, $folder_id, $committee_id); ?>
                </div>
            <?php endif; ?>

            <div class="pba-documents-folder-tab-panel" data-panel="settings" style="display:none;">
                <div class="pba-documents-settings-grid">
                    <div class="pba-documents-settings-card">
                        <h5 class="pba-documents-subsection-title">Rename Folder</h5>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-settings-form">
                            <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                            <input type="hidden" name="action" value="pba_rename_document_folder">
                            <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
                            <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
                            <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder_id); ?>">
                            <input type="text" name="folder_name" value="<?php echo esc_attr($folder['folder_name'] ?? ''); ?>" class="pba-documents-input" required>
                            <button type="submit" class="pba-documents-btn secondary">Rename</button>
                        </form>
                    </div>

                    <div class="pba-documents-settings-card">
                        <h5 class="pba-documents-subsection-title">Deactivate Folder</h5>
                        <p class="pba-documents-muted">Deactivate this folder if it should no longer be used for active board or committee materials.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Deactivate this folder?');">
                            <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                            <input type="hidden" name="action" value="pba_deactivate_document_folder">
                            <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
                            <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
                            <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder_id); ?>">
                            <button type="submit" class="pba-documents-btn secondary">Deactivate Folder</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function pba_get_committee_documents_all_active_committees() {
    $rows = pba_supabase_get('Committee', array(
        'select' => 'committee_id,committee_name,committee_description,status,last_modified_at',
        'status' => 'eq.Active',
        'order'  => 'committee_name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $filtered_rows = array();

    foreach ($rows as $row) {
        $committee_name = trim((string) ($row['committee_name'] ?? ''));

        if (strcasecmp($committee_name, 'Board of Directors') === 0) {
            continue;
        }

        $filtered_rows[] = $row;
    }

    if (function_exists('pba_sort_committees_for_display')) {
        $filtered_rows = pba_sort_committees_for_display($filtered_rows);
    }

    return $filtered_rows;
}

function pba_render_committee_documents_committee_cards($committees, $selected_committee_id = 0, $restricted = false) {
    if (empty($committees) || !is_array($committees)) {
        return '';
    }

    ob_start();
    ?>
    <div class="pba-documents-committee-grid">
        <?php foreach ($committees as $committee) : ?>
            <?php
            $committee_id = isset($committee['committee_id']) ? (int) $committee['committee_id'] : 0;
            $committee_name = (string) ($committee['committee_name'] ?? '');
            $committee_description = (string) ($committee['committee_description'] ?? '');
            $is_selected = $committee_id > 0 && $committee_id === (int) $selected_committee_id;
            $open_href = add_query_arg('committee_id', $committee_id);
            $open_href .= '#pba-committee-create-folder';
            ?>
            <div class="pba-documents-committee-card<?php echo $restricted ? ' restricted' : ''; ?><?php echo $is_selected ? ' is-selected' : ''; ?>">
                <div class="pba-documents-committee-card-head">
                    <div class="pba-documents-committee-card-title-wrap">
                        <h4 class="pba-documents-committee-card-title"><?php echo esc_html($committee_name); ?></h4>

                        <?php if ($restricted) : ?>
                            <span class="pba-documents-badge muted">Restricted</span>
                        <?php elseif ($is_selected) : ?>
                            <span class="pba-documents-badge">Selected</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($committee_description !== '') : ?>
                    <p class="pba-documents-committee-card-text"><?php echo esc_html($committee_description); ?></p>
                <?php else : ?>
                    <p class="pba-documents-committee-card-text pba-documents-muted">
                        <?php echo $restricted ? 'Documents are limited to assigned committee members and administrators.' : 'Open this committee to browse and manage folders and documents.'; ?>
                    </p>
                <?php endif; ?>

                <div class="pba-documents-committee-card-actions">
                    <?php if ($restricted) : ?>
                        <span class="pba-documents-restricted-note">You are not currently assigned to this committee.</span>
                    <?php else : ?>
                        <a
                            class="pba-documents-btn<?php echo $is_selected ? ' secondary' : ''; ?>"
                            href="<?php echo esc_url($open_href); ?>"
                        >
                            Open
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_render_documents_common_styles_and_script() {
    ob_start();
    ?>
    <style>
        html { scroll-behavior: smooth; }
        .pba-documents-wrap { max-width: 1240px; margin: 0 auto; padding: 0; }
        .pba-documents-page-intro { margin: 0 0 18px; color: #4b5563; font-size: 15px; line-height: 1.6; }
        .pba-documents-message { margin: 0 0 20px; padding: 14px 16px; border: 1px solid #cfe4d2; border-radius: 12px; background: #edf8ef; color: #1f5130; }
        .pba-documents-message.error { border-color: #eccccc; background: #fbefef; color: #8a1f1f; }
        .pba-documents-section { margin: 0 0 22px; padding: 18px 20px; border: 1px solid #d9e2ec; border-radius: 18px; background: #ffffff; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06); scroll-margin-top: 24px; }
        .pba-documents-section-title { margin: 0 0 14px; font-size: 1.2rem; line-height: 1.3; color: #0f172a; }
        .pba-documents-subsection-title { margin: 0; font-size: 1rem; line-height: 1.3; color: #0f172a; }
        .pba-documents-action-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .pba-documents-folder-list { display: grid; grid-template-columns: 1fr; gap: 16px; }
        .pba-documents-folder-card { border: 1px solid #d9e2ec; border-radius: 18px; background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); overflow: hidden; }
        .pba-documents-folder-toggle { width: 100%; border: 0; background: transparent; padding: 18px 20px; display: flex; justify-content: space-between; gap: 18px; align-items: center; cursor: pointer; text-align: left; }
        .pba-documents-folder-toggle:hover, .pba-documents-folder-toggle:focus { background: #f8fbff; outline: none; }
        .pba-documents-folder-toggle-main, .pba-documents-folder-toggle-meta { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .pba-documents-folder-toggle-meta { align-items: flex-end; text-align: right; }
        .pba-documents-folder-title { font-size: 1.08rem; font-weight: 700; color: #0f172a; line-height: 1.3; }
        .pba-documents-folder-badges { display: flex; flex-wrap: wrap; gap: 8px; }
        .pba-documents-badge { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 10px; border-radius: 999px; background: #e8f1fb; color: #0d3b66; font-size: 13px; font-weight: 700; }
        .pba-documents-badge.muted { background: #eef2f7; color: #475569; }
        .pba-documents-folder-toggle-label { color: #0d3b66; font-weight: 700; }
        .pba-documents-folder-card.is-open .pba-documents-folder-toggle-label { color: #0b3154; }
        .pba-documents-folder-panel { padding: 0 20px 20px; border-top: 1px solid #e5edf5; }
        .pba-documents-folder-tabs { display: flex; gap: 10px; flex-wrap: wrap; padding: 16px 0 14px; }
        .pba-documents-folder-tab { border: 1px solid #d9e2ec; background: #fff; color: #0d3b66; border-radius: 999px; padding: 8px 14px; cursor: pointer; font-weight: 700; }
        .pba-documents-folder-tab.is-active { background: #0d3b66; border-color: #0d3b66; color: #fff; }
        .pba-documents-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .pba-documents-settings-card { border: 1px solid #d9e2ec; border-radius: 14px; background: #fff; padding: 16px; }
        .pba-documents-settings-form { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; align-items: center; }
        .pba-documents-form-inline { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0; }
        .pba-documents-inline-actions { display: flex; flex-wrap: nowrap; gap: 8px; align-items: center; margin-top: 10px; }
        .pba-documents-inline-actions .pba-documents-form-inline { flex-wrap: nowrap; }
        .pba-documents-inline-actions .pba-documents-btn { white-space: nowrap; }        .pba-documents-input, .pba-documents-upload-form input[type="file"] { width: 100%; max-width: 100%; min-height: 40px; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; box-sizing: border-box; }
        .pba-documents-input:focus, .pba-documents-upload-form input[type="file"]:focus { outline: none; border-color: #0d3b66; box-shadow: 0 0 0 3px rgba(13, 59, 102, 0.12); }
        
        .pba-documents-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 9px 14px; border: 1px solid #0d3b66; background: #0d3b66; color: #ffffff; border-radius: 10px; text-decoration: none; cursor: pointer; font-weight: 600; line-height: 1.2; transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease, transform 0.18s ease; }
        .pba-documents-btn:hover, .pba-documents-btn:focus { background: #0b3154; border-color: #0b3154; color: #ffffff; transform: translateY(-1px); }
        .pba-documents-btn.secondary { background: #ffffff; color: #0d3b66; }
        .pba-documents-btn.secondary:hover, .pba-documents-btn.secondary:focus { background: #f7fbff; color: #0b3154; border-color: #0b3154; }
        .pba-documents-btn.secondary.is-expanded { background: #0d3b66; color: #ffffff; border-color: #0d3b66; }
        
        .pba-documents-muted { color: #64748b; font-size: 13px; line-height: 1.5; }
        .pba-documents-title-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .pba-documents-inline-chip { display: inline-flex; align-items: center; min-height: 26px; padding: 3px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 12px; line-height: 1.3; }
        .pba-documents-upload-grid, .pba-documents-editor-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .pba-documents-field { min-width: 0; }
        .pba-documents-editor-span { grid-column: 1 / -1; }
        .pba-documents-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #334155; }
        .pba-documents-upload-help { margin-top: 10px; }
        .pba-documents-toolbar-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .pba-documents-table-shell { margin-top: 10px; }
        .pba-documents-table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
        .pba-documents-table-toolbar-left, .pba-documents-table-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .pba-documents-table-toolbar-right .pba-documents-input { width: auto; min-width: 180px; }
        .pba-documents-table-wrap { overflow-x: auto; border: 1px solid #d9e2ec; border-radius: 14px; background: #ffffff; }
        .pba-documents-table { width: 100%; min-width: 1080px; border-collapse: separate; border-spacing: 0; margin: 0; }
        .pba-documents-table th, .pba-documents-table td { border-bottom: 1px solid #e5edf5; padding: 12px 12px; text-align: left; vertical-align: top; font-size: 14px; line-height: 1.45; }
        .pba-documents-table thead th { position: sticky; top: 0; background: #f8fafc; color: #334155; font-weight: 700; z-index: 1; }
        .pba-documents-table tbody tr:last-child td { border-bottom: none; }
        .pba-documents-title-cell a {
            color: #0d3b66;
            font-weight: 700;
            text-decoration: none;
        }

        .pba-documents-title-cell a:hover,
        .pba-documents-title-cell a:focus {
            color: #0b3154;
            text-decoration: underline;
        }        
        .pba-documents-title-cell a:hover, .pba-documents-title-cell a:focus { text-decoration: underline; }
        .pba-documents-editor-actions { margin-top: 8px; }
        .pba-documents-table-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .pba-documents-table-summary, .pba-documents-page-status { color: #475569; font-size: 14px; }
        .pba-documents-pagination { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .pba-documents-inactive-toggle-wrap { margin-top: 14px; }
        .pba-documents-inactive-panel { margin-top: 12px; padding-top: 4px; }
        .pba-documents-metadata-drawer { margin-top: 14px; border: 1px solid #dbeafe; background: #f7fbff; border-radius: 14px; padding: 16px; }
        .pba-documents-metadata-drawer-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .pba-documents-readonly-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 14px; }
        .pba-documents-readonly-meta-item { border: 1px solid #d9e2ec; border-radius: 12px; background: #ffffff; padding: 12px; }
        .pba-documents-readonly-meta-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .pba-documents-readonly-meta-value { color: #0f172a; font-weight: 600; line-height: 1.4; }
        .pba-doc-share-cell { display: flex; flex-direction: column; gap: 8px; min-width: 170px; }
        .pba-doc-share-status { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; line-height: 1; width: fit-content; }
        .pba-doc-share-status.is-shared { background: #eaf6ec; color: #25613a; }
        .pba-doc-share-status.is-private { background: #eef2f7; color: #475569; }
        .pba-doc-share-meta { color: #64748b; font-size: 12px; line-height: 1.4; }
        .pba-doc-share-form { display: inline-flex; flex-direction: column; gap: 8px; margin: 0; }
        .pba-doc-share-button { width: 100%; justify-content: center; }
        .pba-documents-committee-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .pba-documents-committee-card { display: flex; flex-direction: column; gap: 12px; min-height: 220px; padding: 18px; border: 1px solid #d9e2ec; border-radius: 18px; background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); }
        .pba-documents-committee-card.is-selected { border-color: #0d3b66; box-shadow: 0 10px 24px rgba(13, 59, 102, 0.12); }
        .pba-documents-committee-card.restricted { background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); border-color: #d7dee7; }
        .pba-documents-committee-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .pba-documents-committee-card-title-wrap { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
        .pba-documents-committee-card-title { margin: 0; color: #0f172a; font-size: 1.05rem; line-height: 1.35; }
        .pba-documents-committee-card-text { margin: 0; color: #475569; font-size: 14px; line-height: 1.6; flex: 1 1 auto; }
        .pba-documents-committee-card-actions { margin-top: auto; display: flex; align-items: flex-end; justify-content: flex-start; }
        .pba-documents-restricted-note { display: inline-flex; align-items: center; min-height: 40px; padding: 9px 12px; border: 1px solid #d7dee7; border-radius: 10px; background: #ffffff; color: #64748b; font-weight: 600; line-height: 1.4; }
        @media (max-width: 900px) {
            .pba-documents-section, .pba-documents-folder-toggle, .pba-documents-folder-panel { padding-left: 16px; padding-right: 16px; }
            .pba-documents-folder-toggle { flex-direction: column; align-items: stretch; }
            .pba-documents-folder-toggle-meta { align-items: flex-start; text-align: left; }
            .pba-documents-table-toolbar-right { width: 100%; }
            .pba-documents-table-toolbar-right .pba-documents-input { width: 100%; min-width: 0; }
        }
        @media (max-width: 640px) {
            .pba-documents-action-bar, .pba-documents-settings-form, .pba-documents-toolbar-actions { width: 100%; }
            .pba-documents-btn { width: 100%; }
            .pba-documents-table-footer { flex-direction: column; align-items: stretch; }
            .pba-documents-pagination { justify-content: space-between; }
            .pba-documents-metadata-drawer-head { flex-direction: column; align-items: stretch; }
            .pba-documents-restricted-note { width: 100%; }
        }
    </style>

    <script>
        (function () {
            function normalize(value) {
                return String(value || '').toLowerCase().trim();
            }

            function parseDate(value) {
                if (!value) {
                    return 0;
                }

                var time = Date.parse(value);
                return Number.isNaN(time) ? 0 : time;
            }

            function initDocumentsTable(shell) {
                if (!shell || shell.dataset.pbaDocumentsReady === '1') {
                    return;
                }

                shell.dataset.pbaDocumentsReady = '1';

                var tableId = shell.getAttribute('data-table-id');
                var table = tableId ? document.getElementById(tableId) : null;
                if (!table) {
                    return;
                }

                var tbody = table.querySelector('tbody');
                if (!tbody) {
                    return;
                }

                var searchInput = shell.querySelector('.pba-documents-table-search');
                var sortSelect = shell.querySelector('.pba-documents-table-sort');
                var pageSizeSelect = shell.querySelector('.pba-documents-table-page-size');
                var prevButton = shell.querySelector('.pba-documents-page-prev');
                var nextButton = shell.querySelector('.pba-documents-page-next');
                var pageStatus = shell.querySelector('.pba-documents-page-status');
                var summary = shell.querySelector('.pba-documents-table-summary');

                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.pba-documents-data-row'));
                var emptyRow = tbody.querySelector('tr.pba-documents-empty-row');
                var currentPage = 1;

                function compareRows(aRow, bRow, sortValue) {
                    if (sortValue === 'title_asc' || sortValue === 'title_desc') {
                        var at = aRow.dataset.title || '';
                        var bt = bRow.dataset.title || '';
                        return sortValue === 'title_asc' ? at.localeCompare(bt) : bt.localeCompare(at);
                    }

                    if (sortValue === 'category_asc' || sortValue === 'category_desc') {
                        var ac = aRow.dataset.category || '';
                        var bc = bRow.dataset.category || '';
                        return sortValue === 'category_asc' ? ac.localeCompare(bc) : bc.localeCompare(ac);
                    }

                    if (sortValue === 'modified_asc' || sortValue === 'modified_desc') {
                        var am = parseDate(aRow.dataset.modified || '');
                        var bm = parseDate(bRow.dataset.modified || '');
                        return sortValue === 'modified_asc' ? am - bm : bm - am;
                    }

                    var ad = parseDate(aRow.dataset.date || '');
                    var bd = parseDate(bRow.dataset.date || '');

                    if (sortValue === 'date_asc') {
                        return ad - bd;
                    }

                    return bd - ad;
                }

                function matchesSearch(row, term) {
                    if (!term) {
                        return true;
                    }

                    var haystack = [
                        row.dataset.title,
                        row.dataset.fileName,
                        row.dataset.date,
                        row.dataset.category,
                        row.dataset.version,
                        row.dataset.type,
                        row.dataset.notes
                    ].join(' ');

                    return normalize(haystack).indexOf(term) !== -1;
                }

                function render() {
                    var term = normalize(searchInput ? searchInput.value : '');
                    var sortValue = sortSelect ? sortSelect.value : 'date_desc';
                    var pageSize = parseInt(pageSizeSelect ? pageSizeSelect.value : '10', 10);

                    if (!pageSize || pageSize < 1) {
                        pageSize = 10;
                    }

                    var filtered = rows.filter(function (row) {
                        return matchesSearch(row, term);
                    });

                    filtered.sort(function (a, b) {
                        return compareRows(a, b, sortValue);
                    });

                    var totalItems = filtered.length;
                    var totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

                    if (currentPage > totalPages) {
                        currentPage = totalPages;
                    }

                    if (currentPage < 1) {
                        currentPage = 1;
                    }

                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;
                    var visibleRows = filtered.slice(startIndex, endIndex);

                    rows.forEach(function (row) {
                        row.style.display = 'none';
                    });

                    if (emptyRow) {
                        emptyRow.style.display = totalItems === 0 ? '' : 'none';
                    }

                    visibleRows.forEach(function (row) {
                        row.style.display = '';
                    });

                    if (summary) {
                        if (totalItems === 0) {
                            summary.textContent = 'No matching documents.';
                        } else {
                            summary.textContent = 'Showing ' + (startIndex + 1) + '-' + Math.min(endIndex, totalItems) + ' of ' + totalItems + ' documents';
                        }
                    }

                    if (pageStatus) {
                        pageStatus.textContent = totalItems === 0 ? 'Page 0 of 0' : 'Page ' + currentPage + ' of ' + totalPages;
                    }

                    if (prevButton) {
                        prevButton.disabled = currentPage <= 1 || totalItems === 0;
                    }

                    if (nextButton) {
                        nextButton.disabled = currentPage >= totalPages || totalItems === 0;
                    }
                }

                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        currentPage = 1;
                        render();
                    });
                }

                if (sortSelect) {
                    sortSelect.addEventListener('change', function () {
                        currentPage = 1;
                        render();
                    });
                }

                if (pageSizeSelect) {
                    pageSizeSelect.addEventListener('change', function () {
                        currentPage = 1;
                        render();
                    });
                }

                if (prevButton) {
                    prevButton.addEventListener('click', function () {
                        currentPage -= 1;
                        render();
                    });
                }

                if (nextButton) {
                    nextButton.addEventListener('click', function () {
                        currentPage += 1;
                        render();
                    });
                }

                render();
            }

            function initMetadataDrawer(panel) {
                if (!panel || panel.dataset.pbaDrawerReady === '1') {
                    return;
                }

                panel.dataset.pbaDrawerReady = '1';

                var drawer = panel.querySelector('.pba-documents-metadata-drawer');
                if (!drawer) {
                    return;
                }

                var form = drawer.querySelector('form');
                var closeButton = drawer.querySelector('.pba-documents-close-editor');

                function fillDrawerFromRow(row) {
                    var raw = row.getAttribute('data-item-json') || '';
                    var data = {};

                    try {
                        data = JSON.parse(raw);
                    } catch (error) {
                        data = {};
                    }

                    var itemIdInput = form.querySelector('input[name="document_item_id"]');
                    var titleInput = form.querySelector('input[name="document_title"]');
                    var dateInput = form.querySelector('input[name="document_date"]');
                    var categoryInput = form.querySelector('input[name="document_category"]');
                    var versionInput = form.querySelector('input[name="document_version"]');
                    var notesInput = form.querySelector('input[name="notes"]');

                    if (itemIdInput) { itemIdInput.value = data.document_item_id || ''; }
                    if (titleInput) { titleInput.value = data.document_title || ''; }
                    if (dateInput) { dateInput.value = data.document_date || ''; }
                    if (categoryInput) { categoryInput.value = data.document_category || ''; }
                    if (versionInput) { versionInput.value = data.document_version || ''; }
                    if (notesInput) { notesInput.value = data.notes || ''; }

                    var modifiedField = drawer.querySelector('[data-meta-field="last_modified_display"]');
                    var modifiedByField = drawer.querySelector('[data-meta-field="last_modified_by_display"]');

                    if (modifiedField) { modifiedField.textContent = data.last_modified_display || '—'; }
                    if (modifiedByField) { modifiedByField.textContent = data.last_modified_by_display || '—'; }
                }

                panel.addEventListener('click', function (event) {
                    var button = event.target.closest('.pba-documents-open-editor');
                    if (!button) {
                        return;
                    }

                    var row = button.closest('tr.pba-documents-data-row');
                    if (!row) {
                        return;
                    }

                    fillDrawerFromRow(row);
                    drawer.style.display = '';
                    drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });

                if (closeButton) {
                    closeButton.addEventListener('click', function () {
                        drawer.style.display = 'none';
                    });
                }
            }

            function initFolderCard(card) {
                if (!card || card.dataset.pbaFolderReady === '1') {
                    return;
                }

                card.dataset.pbaFolderReady = '1';

                var toggle = card.querySelector('.pba-documents-folder-toggle');
                var panel = card.querySelector('.pba-documents-folder-panel');
                var label = card.querySelector('.pba-documents-folder-toggle-label');

                if (toggle && panel) {
                    toggle.addEventListener('click', function () {
                        var list = card.parentNode;
                        var isOpen = card.classList.contains('is-open');

                        if (list) {
                            Array.prototype.forEach.call(list.querySelectorAll('.pba-documents-folder-card.is-open'), function (otherCard) {
                                if (otherCard !== card) {
                                    otherCard.classList.remove('is-open');
                                    var otherPanel = otherCard.querySelector('.pba-documents-folder-panel');
                                    var otherToggle = otherCard.querySelector('.pba-documents-folder-toggle');
                                    var otherLabel = otherCard.querySelector('.pba-documents-folder-toggle-label');

                                    if (otherPanel) { otherPanel.style.display = 'none'; }
                                    if (otherToggle) { otherToggle.setAttribute('aria-expanded', 'false'); }
                                    if (otherLabel) { otherLabel.textContent = 'Open'; }
                                }
                            });
                        }

                        if (isOpen) {
                            card.classList.remove('is-open');
                            panel.style.display = 'none';
                            toggle.setAttribute('aria-expanded', 'false');
                            if (label) { label.textContent = 'Open'; }
                        } else {
                            card.classList.add('is-open');
                            panel.style.display = '';
                            toggle.setAttribute('aria-expanded', 'true');
                            if (label) { label.textContent = 'Close'; }

                            Array.prototype.forEach.call(card.querySelectorAll('.pba-documents-table-shell'), initDocumentsTable);
                            Array.prototype.forEach.call(card.querySelectorAll('.pba-documents-folder-tab-panel[data-panel="documents"]'), initMetadataDrawer);
                        }
                    });
                }

                Array.prototype.forEach.call(card.querySelectorAll('.pba-documents-folder-tabs'), function (tabsWrap) {
                    var tabs = tabsWrap.querySelectorAll('.pba-documents-folder-tab');
                    var cardScope = tabsWrap.parentNode;

                    Array.prototype.forEach.call(tabs, function (tab) {
                        tab.addEventListener('click', function () {
                            var target = tab.getAttribute('data-tab');
                            var panels = cardScope.querySelectorAll('.pba-documents-folder-tab-panel');

                            Array.prototype.forEach.call(tabs, function (otherTab) {
                                otherTab.classList.remove('is-active');
                            });

                            Array.prototype.forEach.call(panels, function (panel) {
                                panel.classList.remove('is-active');
                                panel.style.display = 'none';
                            });

                            tab.classList.add('is-active');

                            var targetPanel = cardScope.querySelector('.pba-documents-folder-tab-panel[data-panel="' + target + '"]');
                            if (targetPanel) {
                                targetPanel.classList.add('is-active');
                                targetPanel.style.display = '';
                                Array.prototype.forEach.call(targetPanel.querySelectorAll('.pba-documents-table-shell'), initDocumentsTable);
                                if (target === 'documents') {
                                    initMetadataDrawer(targetPanel);
                                }
                            }
                        });
                    });
                });

                Array.prototype.forEach.call(card.querySelectorAll('.pba-documents-toggle-inactive'), function (button) {
                    button.addEventListener('click', function () {
                        var wrap = button.closest('.pba-documents-folder-tab-panel');
                        if (!wrap) {
                            return;
                        }

                        var panel = wrap.querySelector('.pba-documents-inactive-panel');
                        if (!panel) {
                            return;
                        }

                        var isOpen = panel.style.display !== 'none';

                        if (isOpen) {
                            panel.style.display = 'none';
                            button.classList.remove('is-expanded');
                            button.textContent = button.textContent.replace('Hide', 'Show');
                            button.setAttribute('data-state', 'closed');
                        } else {
                            panel.style.display = '';
                            button.classList.add('is-expanded');
                            if (button.textContent.indexOf('Show') === 0) {
                                button.textContent = button.textContent.replace('Show', 'Hide');
                            }
                            button.setAttribute('data-state', 'open');
                            Array.prototype.forEach.call(panel.querySelectorAll('.pba-documents-table-shell'), initDocumentsTable);
                        }
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                Array.prototype.forEach.call(document.querySelectorAll('.pba-documents-folder-card'), initFolderCard);
            });
        }());
    </script>
    <?php
    return ob_get_clean();
}

function pba_render_documents_storage_gauge_if_available() {
    if (!function_exists('pba_render_document_storage_gauge')) {
        return '';
    }

    return pba_render_document_storage_gauge();
}

function pba_render_board_documents_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_person_can_manage_board_folders()) {
        return '<p>You do not have permission to access Board Documents.</p>';
    }

    $folders = pba_get_active_document_folders('Board', null);

    ob_start();
    echo pba_render_documents_common_styles_and_script();
    ?>
    <div class="pba-documents-wrap">
        <p class="pba-documents-page-intro">
            Browse and manage board folders for agendas, minutes, budgets, and supporting materials. Store documents in one or more folders.
        </p>

        <?php echo pba_render_documents_status_message(); ?>
        <?php echo pba_render_documents_storage_gauge_if_available(); ?>
        <div class="pba-documents-section">
            <h3 class="pba-documents-section-title">Create Folder</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-action-bar">
                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                <input type="hidden" name="action" value="pba_create_document_folder">
                <input type="hidden" name="page_slug" value="board-documents">
                <input type="hidden" name="folder_scope_type" value="Board">
                <input type="hidden" name="committee_id" value="0">

                <input type="text" name="folder_name" class="pba-documents-input" placeholder="New board folder name" required>
                <button type="submit" class="pba-documents-btn">Create Folder</button>
            </form>
        </div>

        <div class="pba-documents-section">
            <h3 class="pba-documents-section-title">Folders</h3>

            <?php if (empty($folders)) : ?>
                <p>No board folders found.</p>
            <?php else : ?>
                <div class="pba-documents-folder-list">
                    <?php foreach ($folders as $folder) : ?>
                        <?php echo pba_render_documents_folder_card($folder, 'board-documents', 0); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function pba_render_committee_documents_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    if (!pba_current_person_is_admin() && !pba_current_person_is_committee_member()) {
        return '<p>You do not have permission to access Committee Documents.</p>';
    }

    $is_admin = pba_current_person_is_admin();
    $selected_committee_id = isset($_GET['committee_id']) ? absint($_GET['committee_id']) : 0;

    $all_committees = pba_get_committee_documents_all_active_committees();
    $assigned_committee_ids = $is_admin ? array() : pba_get_current_person_committee_ids();

    if (!is_array($assigned_committee_ids)) {
        $assigned_committee_ids = array();
    }

    $assigned_committee_ids = array_map('intval', $assigned_committee_ids);

    $assigned_committees = array();
    $other_committees = array();

    foreach ($all_committees as $committee) {
        $committee_id = isset($committee['committee_id']) ? (int) $committee['committee_id'] : 0;

        if ($committee_id < 1) {
            continue;
        }

        if ($is_admin || in_array($committee_id, $assigned_committee_ids, true)) {
            $assigned_committees[] = $committee;
        } else {
            $other_committees[] = $committee;
        }
    }

    if ($selected_committee_id < 1 && !empty($assigned_committees)) {
        $selected_committee_id = (int) $assigned_committees[0]['committee_id'];
    }

    $valid_committee_ids = array();
    foreach ($assigned_committees as $committee) {
        $committee_id = isset($committee['committee_id']) ? (int) $committee['committee_id'] : 0;
        if ($committee_id > 0) {
            $valid_committee_ids[] = $committee_id;
        }
    }

    if ($selected_committee_id > 0 && !in_array($selected_committee_id, $valid_committee_ids, true)) {
        $selected_committee_id = !empty($assigned_committees) ? (int) $assigned_committees[0]['committee_id'] : 0;
    }

    $committee_name = $selected_committee_id > 0 ? pba_get_committee_name($selected_committee_id) : '';
    $folders = $selected_committee_id > 0 ? pba_get_active_document_folders('Committee', $selected_committee_id) : array();
    $can_create_folder = $selected_committee_id > 0 && ($is_admin || pba_current_person_has_active_committee_assignment($selected_committee_id));

    ob_start();
    echo pba_render_documents_common_styles_and_script();
    ?>
    <div class="pba-documents-wrap">
        <p class="pba-documents-page-intro">
            Browse committee folders and documents. Your assigned committees appear first, while other active committees are shown for awareness with restricted access.
        </p>

        <?php echo pba_render_documents_status_message(); ?>
        <?php echo pba_render_documents_storage_gauge_if_available(); ?>
        <div class="pba-documents-section">
            <h3 class="pba-documents-section-title"><?php echo $is_admin ? 'Committees' : 'My Committees'; ?></h3>

            <?php if (empty($assigned_committees)) : ?>
                <p>No assigned committees found.</p>
            <?php else : ?>
                <?php echo pba_render_committee_documents_committee_cards($assigned_committees, $selected_committee_id, false); ?>
            <?php endif; ?>
        </div>

        <?php if (!$is_admin && !empty($other_committees)) : ?>
            <div class="pba-documents-section">
                <h3 class="pba-documents-section-title">Other Committees</h3>
                <p class="pba-documents-page-intro">
                    These committees are visible for awareness, but documents are limited to assigned committee members and administrators.
                </p>
                <?php echo pba_render_committee_documents_committee_cards($other_committees, 0, true); ?>
            </div>
        <?php endif; ?>

        <?php if ($selected_committee_id > 0 && $can_create_folder) : ?>
            <div class="pba-documents-section" id="pba-committee-create-folder">
                <h3 class="pba-documents-section-title">Create Folder</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-action-bar">
                    <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                    <input type="hidden" name="action" value="pba_create_document_folder">
                    <input type="hidden" name="page_slug" value="committee-documents">
                    <input type="hidden" name="folder_scope_type" value="Committee">
                    <input type="hidden" name="committee_id" value="<?php echo esc_attr($selected_committee_id); ?>">

                    <input type="text" name="folder_name" class="pba-documents-input" placeholder="New committee folder name" required>
                    <button type="submit" class="pba-documents-btn">Create Folder</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($selected_committee_id > 0) : ?>
            <div class="pba-documents-section">
                <h3 class="pba-documents-section-title">
                    Folders<?php echo $committee_name !== '' ? ' — ' . esc_html($committee_name) : ''; ?>
                </h3>

                <?php if (empty($folders)) : ?>
                    <p>No folders found for this committee.</p>
                <?php else : ?>
                    <div class="pba-documents-folder-list">
                        <?php foreach ($folders as $folder) : ?>
                            <?php echo pba_render_documents_folder_card($folder, 'committee-documents', $selected_committee_id); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="pba-documents-section">
                <h3 class="pba-documents-section-title">Folders</h3>
                <p>Select a committee above to view its folders and documents.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
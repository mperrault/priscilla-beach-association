<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_document_shortcodes');

function pba_register_document_shortcodes() {
    add_shortcode('pba_board_documents', 'pba_render_board_documents_shortcode');
    add_shortcode('pba_committee_documents', 'pba_render_committee_documents_shortcode');
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
        'folder_created'                => 'Folder created successfully.',
        'folder_renamed'                => 'Folder renamed successfully.',
        'folder_deleted'                => 'Folder deactivated successfully.',
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
        'document_delete_failed'        => 'The document could not be deactivated.',
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
        'folder_created',
        'folder_renamed',
        'folder_deleted',
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
        'select'             => 'document_item_id,document_folder_id,file_name,file_url,mime_type,document_title,document_date,document_category,document_version,uploaded_by_person_id,is_active,notes,last_modified_at',
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
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-top:10px;">
        <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
        <input type="hidden" name="action" value="pba_upload_document_item">
        <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
        <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder_id); ?>">
        <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">

        <div class="pba-documents-upload-grid">
            <div><input type="file" name="document_file" required></div>
            <div><input type="text" name="document_title" placeholder="Document title (optional)" class="pba-documents-input"></div>
            <div><input type="date" name="document_date" class="pba-documents-input"></div>
            <div><input type="text" name="document_category" placeholder="Category, e.g. Agenda, Minutes, Budget" class="pba-documents-input"></div>
            <div><input type="text" name="document_version" placeholder="Version, e.g. Draft 1, Final" class="pba-documents-input"></div>
            <div><input type="text" name="notes" placeholder="Optional notes" class="pba-documents-input"></div>
        </div>
        <button type="submit" class="pba-documents-btn">Upload</button>
        <div class="pba-documents-muted" style="margin-top:8px;">
            Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, JPG, JPEG, PNG. Max <?php echo esc_html(pba_get_document_upload_max_size_label()); ?>.
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function pba_render_document_metadata_editor($item, $page_slug, $committee_id = 0) {
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-metadata-form">
        <?php wp_nonce_field('pba_document_item_action', 'pba_document_item_nonce'); ?>
        <input type="hidden" name="action" value="pba_save_document_item_metadata">
        <input type="hidden" name="page_slug" value="<?php echo esc_attr($page_slug); ?>">
        <input type="hidden" name="committee_id" value="<?php echo esc_attr((int) $committee_id); ?>">
        <input type="hidden" name="document_item_id" value="<?php echo esc_attr((int) $item['document_item_id']); ?>">

        <div class="pba-documents-editor-grid">
            <div>
                <label class="pba-documents-label">Title</label>
                <input type="text" name="document_title" value="<?php echo esc_attr($item['document_title'] ?? ''); ?>" class="pba-documents-input">
            </div>
            <div>
                <label class="pba-documents-label">Date</label>
                <input type="date" name="document_date" value="<?php echo esc_attr($item['document_date'] ?? ''); ?>" class="pba-documents-input">
            </div>
            <div>
                <label class="pba-documents-label">Category</label>
                <input type="text" name="document_category" value="<?php echo esc_attr($item['document_category'] ?? ''); ?>" class="pba-documents-input">
            </div>
            <div>
                <label class="pba-documents-label">Version</label>
                <input type="text" name="document_version" value="<?php echo esc_attr($item['document_version'] ?? ''); ?>" class="pba-documents-input">
            </div>
            <div class="pba-documents-editor-span">
                <label class="pba-documents-label">Notes</label>
                <input type="text" name="notes" value="<?php echo esc_attr($item['notes'] ?? ''); ?>" class="pba-documents-input">
            </div>
        </div>

        <div class="pba-documents-editor-actions">
            <button type="submit" class="pba-documents-btn secondary">Save Metadata</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function pba_render_document_items_table($items, $page_slug, $committee_id = 0, $is_active = true) {
    $table_id = 'pba-doc-table-' . wp_generate_uuid4();

    ob_start();
    ?>
    <table class="pba-documents-table" id="<?php echo esc_attr($table_id); ?>" style="margin-top:10px;">
        <thead>
            <tr>
                <th>Title / File</th>
                <th>Date</th>
                <th>Category</th>
                <th>Version</th>
                <th>Type</th>
                <th>Notes</th>
                <th>Last Modified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) : ?>
                <tr>
                    <td colspan="8"><?php echo $is_active ? 'No active documents uploaded yet.' : 'No inactive documents.'; ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $display_title = !empty($item['document_title']) ? $item['document_title'] : ($item['file_name'] ?? '');
                    $document_date = !empty($item['document_date']) ? $item['document_date'] : '';
                    $editor_id = 'pba-doc-editor-' . (int) $item['document_item_id'] . '-' . wp_rand(1000, 9999);
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['file_url'])) : ?>
                                <a href="<?php echo esc_url($item['file_url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($display_title); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($display_title); ?>
                            <?php endif; ?>
                            <?php if (!empty($item['document_title']) && !empty($item['file_name'])) : ?>
                                <div class="pba-documents-muted"><?php echo esc_html($item['file_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($document_date); ?></td>
                        <td><?php echo esc_html($item['document_category'] ?? ''); ?></td>
                        <td><?php echo esc_html($item['document_version'] ?? ''); ?></td>
                        <td><?php echo esc_html($item['mime_type'] ?? ''); ?></td>
                        <td><?php echo esc_html($item['notes'] ?? ''); ?></td>
                        <td><?php echo esc_html(pba_format_datetime_display($item['last_modified_at'] ?? '')); ?></td>
                        <td>
                            <?php if ($is_active) : ?>
                                <button
                                    type="button"
                                    class="pba-documents-btn secondary pba-documents-toggle-editor"
                                    data-target="<?php echo esc_attr($editor_id); ?>"
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
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($is_active) : ?>
                        <tr id="<?php echo esc_attr($editor_id); ?>" class="pba-documents-editor-row" style="display:none;">
                            <td colspan="8">
                                <div class="pba-documents-editor-panel">
                                    <?php echo pba_render_document_metadata_editor($item, $page_slug, $committee_id); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

function pba_render_documents_common_styles_and_script() {
    ob_start();
    ?>
    <style>
        .pba-documents-wrap { max-width: 1100px; margin: 0 auto; }
        .pba-documents-message { margin: 0 0 16px; padding: 12px 16px; background: #eef6ee; border-radius: 6px; }
        .pba-documents-message.error { background: #f8e9e9; }
        .pba-documents-section { margin: 24px 0; }
        .pba-documents-folder-card { margin: 18px 0; padding: 16px; border: 1px solid #d7d7d7; border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .pba-documents-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .pba-documents-table th, .pba-documents-table td { border: 1px solid #d7d7d7; padding: 10px; text-align: left; vertical-align: top; }
        .pba-documents-table th { background: #f3f3f3; }
        .pba-documents-form-inline { display: inline-block; margin-right: 8px; margin-top: 4px; }
        .pba-documents-input { width: 260px; max-width: 100%; padding: 8px 10px; }
        .pba-documents-btn {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .pba-documents-btn.secondary { background: #fff; color: #0d3b66; }
        .pba-documents-muted { color: #666; font-size: 13px; }
        .pba-documents-row-space { margin-top: 10px; }
        .pba-documents-nav a { margin-right: 12px; }

        .pba-documents-upload-grid,
        .pba-documents-editor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .pba-documents-editor-span {
            grid-column: 1 / -1;
        }

        .pba-documents-label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .pba-documents-editor-row td {
            background: #fafcff;
        }

        .pba-documents-editor-panel {
            border: 1px solid #d7e5f2;
            background: #f7fbff;
            border-radius: 8px;
            padding: 14px;
        }

        .pba-documents-editor-actions {
            margin-top: 6px;
        }
    </style>
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.pba-documents-toggle-editor');
            if (!button) {
                return;
            }

            var targetId = button.getAttribute('data-target');
            if (!targetId) {
                return;
            }

            var row = document.getElementById(targetId);
            if (!row) {
                return;
            }

            var isOpen = row.style.display !== 'none';
            row.style.display = isOpen ? 'none' : 'table-row';
            button.textContent = isOpen ? 'Edit Metadata' : 'Cancel';
        });
    </script>
    <?php
    return ob_get_clean();
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
        <!-- h2>Board Documents</h2 -->
        <p>Manage folders and documents for Board materials such as agendas, minutes, and supporting files.</p>

        <?php echo pba_render_documents_status_message(); ?>

        <div class="pba-documents-section">
            <h3>Create Folder</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                <input type="hidden" name="action" value="pba_create_document_folder">
                <input type="hidden" name="page_slug" value="board-documents">
                <input type="hidden" name="folder_scope" value="Board">
                <input type="hidden" name="committee_id" value="0">

                <input type="text" name="folder_name" class="pba-documents-input" placeholder="New board folder name" required>
                <button type="submit" class="pba-documents-btn">Create Folder</button>
            </form>
        </div>

        <div class="pba-documents-section">
            <h3>Folders</h3>

            <?php if (empty($folders)) : ?>
                <p>No Board folders found.</p>
            <?php else : ?>
                <?php foreach ($folders as $folder) : ?>
                    <?php
                    $active_items = pba_get_document_items_for_folder((int) $folder['document_folder_id'], true);
                    $inactive_items = pba_get_document_items_for_folder((int) $folder['document_folder_id'], false);
                    ?>
                    <div class="pba-documents-folder-card">
                        <h4><?php echo esc_html($folder['folder_name'] ?? ''); ?></h4>
                        <p class="pba-documents-muted">Last modified: <?php echo esc_html(pba_format_datetime_display($folder['last_modified_at'] ?? '')); ?></p>

                        <div class="pba-documents-row-space">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline">
                                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                                <input type="hidden" name="action" value="pba_rename_document_folder">
                                <input type="hidden" name="page_slug" value="board-documents">
                                <input type="hidden" name="committee_id" value="0">
                                <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder['document_folder_id']); ?>">
                                <input type="text" name="folder_name" value="<?php echo esc_attr($folder['folder_name'] ?? ''); ?>" class="pba-documents-input" required>
                                <button type="submit" class="pba-documents-btn secondary">Rename</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline" onsubmit="return confirm('Deactivate this folder?');">
                                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                                <input type="hidden" name="action" value="pba_deactivate_document_folder">
                                <input type="hidden" name="page_slug" value="board-documents">
                                <input type="hidden" name="committee_id" value="0">
                                <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder['document_folder_id']); ?>">
                                <button type="submit" class="pba-documents-btn secondary">Deactivate Folder</button>
                            </form>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Upload Document</h5>
                            <?php echo pba_render_document_upload_form('board-documents', (int) $folder['document_folder_id'], 0); ?>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Active Documents</h5>
                            <?php echo pba_render_document_items_table($active_items, 'board-documents', 0, true); ?>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Inactive Documents</h5>
                            <?php echo pba_render_document_items_table($inactive_items, 'board-documents', 0, false); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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

    $committee_id = isset($_GET['committee_id']) ? absint($_GET['committee_id']) : 0;
    $committees = pba_get_current_person_committee_rows();

    if (!pba_current_person_is_admin()) {
        if ($committee_id < 1 && !empty($committees)) {
            $committee_id = (int) $committees[0]['committee_id'];
        }

        if ($committee_id > 0 && !pba_current_person_has_active_committee_assignment($committee_id)) {
            return '<p>You do not have permission to manage that committee.</p>';
        }
    }

    if ($committee_id < 1) {
        return '<p>No committee selected.</p>';
    }

    $committee_name = pba_get_committee_name($committee_id);
    $folders = pba_get_active_document_folders('Committee', $committee_id);

    ob_start();
    echo pba_render_documents_common_styles_and_script();
    ?>
    <div class="pba-documents-wrap">
        <h2>Committee Documents</h2>

        <?php if (!empty($committees)) : ?>
            <div class="pba-documents-nav">
                <?php foreach ($committees as $committee) : ?>
                    <a href="<?php echo esc_url(add_query_arg('committee_id', (int) $committee['committee_id'])); ?>">
                        <?php echo esc_html($committee['committee_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p>Manage folders and documents for <strong><?php echo esc_html($committee_name); ?></strong>.</p>

        <?php echo pba_render_documents_status_message(); ?>

        <div class="pba-documents-section">
            <h3>Create Folder</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                <input type="hidden" name="action" value="pba_create_document_folder">
                <input type="hidden" name="page_slug" value="committee-documents">
                <input type="hidden" name="folder_scope" value="Committee">
                <input type="hidden" name="committee_id" value="<?php echo esc_attr($committee_id); ?>">

                <input type="text" name="folder_name" class="pba-documents-input" placeholder="New committee folder name" required>
                <button type="submit" class="pba-documents-btn">Create Folder</button>
            </form>
        </div>

        <div class="pba-documents-section">
            <h3>Folders</h3>

            <?php if (empty($folders)) : ?>
                <p>No folders found for this committee.</p>
            <?php else : ?>
                <?php foreach ($folders as $folder) : ?>
                    <?php
                    $active_items = pba_get_document_items_for_folder((int) $folder['document_folder_id'], true);
                    $inactive_items = pba_get_document_items_for_folder((int) $folder['document_folder_id'], false);
                    ?>
                    <div class="pba-documents-folder-card">
                        <h4><?php echo esc_html($folder['folder_name'] ?? ''); ?></h4>
                        <p class="pba-documents-muted">Last modified: <?php echo esc_html(pba_format_datetime_display($folder['last_modified_at'] ?? '')); ?></p>

                        <div class="pba-documents-row-space">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline">
                                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                                <input type="hidden" name="action" value="pba_rename_document_folder">
                                <input type="hidden" name="page_slug" value="committee-documents">
                                <input type="hidden" name="committee_id" value="<?php echo esc_attr($committee_id); ?>">
                                <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder['document_folder_id']); ?>">
                                <input type="text" name="folder_name" value="<?php echo esc_attr($folder['folder_name'] ?? ''); ?>" class="pba-documents-input" required>
                                <button type="submit" class="pba-documents-btn secondary">Rename</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-documents-form-inline" onsubmit="return confirm('Deactivate this folder?');">
                                <?php wp_nonce_field('pba_document_folder_action', 'pba_document_folder_nonce'); ?>
                                <input type="hidden" name="action" value="pba_deactivate_document_folder">
                                <input type="hidden" name="page_slug" value="committee-documents">
                                <input type="hidden" name="committee_id" value="<?php echo esc_attr($committee_id); ?>">
                                <input type="hidden" name="folder_id" value="<?php echo esc_attr($folder['document_folder_id']); ?>">
                                <button type="submit" class="pba-documents-btn secondary">Deactivate Folder</button>
                            </form>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Upload Document</h5>
                            <?php echo pba_render_document_upload_form('committee-documents', (int) $folder['document_folder_id'], $committee_id); ?>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Active Documents</h5>
                            <?php echo pba_render_document_items_table($active_items, 'committee-documents', $committee_id, true); ?>
                        </div>

                        <div class="pba-documents-row-space">
                            <h5>Inactive Documents</h5>
                            <?php echo pba_render_document_items_table($inactive_items, 'committee-documents', $committee_id, false); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

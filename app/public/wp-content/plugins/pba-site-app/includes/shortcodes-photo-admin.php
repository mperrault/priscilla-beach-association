<?php
/**
 * PBA Photo Feature - Admin Shortcode
 *
 * Shortcode: [pba_photo_admin]
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('pba_photo_admin', 'pba_render_photo_admin_shortcode');

function pba_render_photo_admin_shortcode() {
    pba_enqueue_photo_assets();
    if (!pba_photo_current_user_can_manage()) {
        return pba_photo_render_notice(
            'error',
            'You do not have permission to manage photos.'
        );
    }

    $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'pending';

    if (!in_array($active_tab, array('pending', 'approved', 'unpublished', 'denied', 'deleted', 'collections', 'audit'), true)) {
        $active_tab = 'pending';
    }
    $message = isset($_GET['pba_photo_admin_message'])
        ? sanitize_key(wp_unslash($_GET['pba_photo_admin_message']))
        : '';

    $usage = pba_photo_get_storage_usage_summary();
    $counts = pba_photo_admin_get_status_counts();

    ob_start();
    ?>
    <div class="pba-photo-page pba-photo-admin-page">
        <div class="pba-photo-page-header">
            <div class="pba-photo-page-header-copy">
                <p class="pba-photo-page-kicker">Photo Administration</p>
                <p class="pba-photo-page-subtitle">
                    Review uploaded photos, approve photos for the public gallery, and manage storage usage.
                </p>
            </div>

            <div class="pba-photo-page-actions">
                <a class="pba-button pba-button-secondary" href="<?php echo esc_url(home_url('/photos/')); ?>">
                    View Public Photos
                </a>
                <a class="pba-button pba-button-secondary" href="<?php echo esc_url(home_url('/photo-upload/')); ?>">
                    Upload Photo
                </a>
            </div>
        </div>

        <?php echo pba_photo_render_admin_message($message); ?>

        <?php echo pba_photo_render_storage_gauge($usage); ?>

        <?php echo pba_photo_render_admin_tabs($active_tab, $counts); ?>

        <div class="pba-photo-admin-tab-panel">
            <?php
                if ($active_tab === 'pending') {
                    echo pba_photo_render_admin_photo_list('pending');
                } elseif ($active_tab === 'approved') {
                    echo pba_photo_render_admin_photo_list('approved');
                } elseif ($active_tab === 'unpublished') {
                    echo pba_photo_render_admin_photo_list('unpublished');
                } elseif ($active_tab === 'denied') {
                    echo pba_photo_render_admin_photo_list('denied');
                } elseif ($active_tab === 'deleted') {
                    echo pba_photo_render_admin_photo_list('deleted');
                } elseif ($active_tab === 'collections') {
                    echo pba_photo_render_collections_admin();
                } elseif ($active_tab === 'audit') {
                    echo pba_photo_render_audit_admin();
                }  
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_admin_get_status_counts() {
    $statuses = array('pending', 'approved', 'unpublished', 'denied', 'deleted');
    $counts = array();

    foreach ($statuses as $status) {
        $rows = pba_photo_supabase_select('Photo', array(
            'select' => 'photo_id',
            'status' => 'eq.' . $status,
        ));

        $counts[$status] = is_array($rows) ? count($rows) : 0;
    }

    $collection_rows = pba_photo_supabase_select('PhotoCollection', array(
        'select' => 'collection_id',
    ));

    $counts['collections'] = is_array($collection_rows) ? count($collection_rows) : 0;

    $audit_rows = pba_photo_supabase_select('PhotoAuditLog', array(
        'select' => 'photo_audit_log_id',
    ));

    $counts['audit'] = is_array($audit_rows) ? count($audit_rows) : 0;

    return $counts;
}
function pba_photo_admin_get_photos_by_status($status, $limit = 60) {
    $status = pba_photo_sanitize_status($status);

    $order = 'created_at.desc';

    if ($status === 'approved') {
        $order = 'approved_at.desc,created_at.desc';
    } elseif ($status === 'unpublished') {
        $order = 'unpublished_at.desc,created_at.desc';
    } elseif ($status === 'denied') {
        $order = 'denied_at.desc,created_at.desc';
    } elseif ($status === 'deleted') {
        $order = 'deleted_at.desc,created_at.desc';
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select' => '*',
        'status' => 'eq.' . $status,
        'order'  => $order,
        'limit'  => max(1, (int) $limit),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_admin_get_active_collections() {
    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select'    => 'collection_id,name,slug,display_order,is_active',
        'is_active' => 'eq.true',
        'order'     => 'display_order.asc,name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_admin_get_photo_collection_map($photo_ids) {
    if (empty($photo_ids)) {
        return array();
    }

    $clean_ids = array();

    foreach ($photo_ids as $photo_id) {
        $photo_id = (int) $photo_id;

        if ($photo_id > 0) {
            $clean_ids[] = $photo_id;
        }
    }

    if (empty($clean_ids)) {
        return array();
    }

    $rows = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'   => 'photo_id,collection_id',
        'photo_id' => 'in.(' . implode(',', $clean_ids) . ')',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    $map = array();

    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['photo_id'], $row['collection_id'])) {
            continue;
        }

        $photo_id = (int) $row['photo_id'];
        $collection_id = (int) $row['collection_id'];

        if (!isset($map[$photo_id])) {
            $map[$photo_id] = array();
        }

        $map[$photo_id][] = $collection_id;
    }

    return $map;
}

function pba_photo_render_storage_gauge($usage) {
    $ok = !empty($usage['ok']);
    $used_bytes = isset($usage['used_bytes']) ? (int) $usage['used_bytes'] : 0;
    $limit_bytes = isset($usage['limit_bytes']) ? (int) $usage['limit_bytes'] : PBA_PHOTO_STORAGE_LIMIT_BYTES;
    $remaining_bytes = isset($usage['remaining_bytes']) ? (int) $usage['remaining_bytes'] : max(0, $limit_bytes - $used_bytes);
    $used_percent = isset($usage['used_percent']) ? (float) $usage['used_percent'] : 0;
    $photo_count = isset($usage['photo_count']) ? (int) $usage['photo_count'] : 0;
    $average_photo_bytes = isset($usage['average_photo_bytes']) ? (int) $usage['average_photo_bytes'] : 0;
    $estimated_remaining_photos = isset($usage['estimated_remaining_photos']) ? $usage['estimated_remaining_photos'] : null;

    $level = pba_photo_get_gauge_level($used_percent);

    ob_start();
    ?>
    <div class="pba-photo-storage-card pba-photo-storage-<?php echo esc_attr($level); ?>">
        <div class="pba-photo-storage-main">
            <div>
                <div class="pba-photo-storage-title">Photo Storage</div>
                <div class="pba-photo-storage-subtitle">
                    <?php if ($ok) : ?>
                        <?php echo esc_html(pba_photo_format_bytes($used_bytes)); ?>
                        used of
                        <?php echo esc_html(pba_photo_format_bytes($limit_bytes)); ?>
                    <?php else : ?>
                        Storage usage could not be calculated.
                    <?php endif; ?>
                </div>
            </div>

            <div class="pba-photo-storage-percent">
                <?php echo esc_html(number_format($used_percent, 1)); ?>%
            </div>
        </div>

        <div class="pba-photo-storage-bar" aria-hidden="true">
            <div class="pba-photo-storage-bar-fill" style="width: <?php echo esc_attr(min(100, max(0, $used_percent))); ?>%;"></div>
        </div>

        <div class="pba-photo-storage-stats">
            <div>
                <span>Remaining</span>
                <strong><?php echo esc_html(pba_photo_format_bytes($remaining_bytes)); ?></strong>
            </div>
            <div>
                <span>Stored photos</span>
                <strong><?php echo esc_html(number_format($photo_count)); ?></strong>
            </div>
            <div>
                <span>Average size</span>
                <strong><?php echo esc_html($average_photo_bytes > 0 ? pba_photo_format_bytes($average_photo_bytes) : '—'); ?></strong>
            </div>
            <div>
                <span>Estimated remaining photos</span>
                <strong><?php echo esc_html($estimated_remaining_photos !== null ? number_format((int) $estimated_remaining_photos) : '—'); ?></strong>
            </div>
        </div>

        <?php if (!$ok && !empty($usage['error'])) : ?>
            <div class="pba-photo-storage-error">
                <?php echo esc_html($usage['error']); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_tabs($active_tab, $counts) {
    $tabs = array(
        'pending'     => 'Pending Review',
        'approved'    => 'Approved',
        'unpublished' => 'Unpublished',
        'denied'      => 'Denied',
        'deleted'     => 'Deleted',
        'collections' => 'Collections',
        'audit'       => 'Audit',
    );    
    ob_start();
    ?>
    <div class="pba-photo-admin-tabs">
        <?php foreach ($tabs as $tab => $label) : ?>
            <?php
            $url = add_query_arg(array('tab' => $tab), home_url('/photo-admin/'));
            $class = $tab === $active_tab ? 'pba-photo-admin-tab is-active' : 'pba-photo-admin-tab';
            $count = isset($counts[$tab]) ? (int) $counts[$tab] : 0;
            ?>
            <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                <span><?php echo esc_html($label); ?></span>
                <strong><?php echo esc_html(number_format($count)); ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_photo_list($status) {
    $photos = pba_photo_admin_get_photos_by_status($status);
    $collections = pba_photo_admin_get_active_collections();

    $photo_ids = array();

    foreach ($photos as $photo) {
        if (isset($photo['photo_id'])) {
            $photo_ids[] = (int) $photo['photo_id'];
        }
    }

    $collection_map = pba_photo_admin_get_photo_collection_map($photo_ids);

    if (empty($photos)) {
        return '<div class="pba-photo-empty-state">No photos found for this status.</div>';
    }

    ob_start();
    ?>
    <div class="pba-photo-admin-list">
        <?php foreach ($photos as $photo) : ?>
            <?php echo pba_photo_render_admin_photo_card($photo, $status, $collections, $collection_map); ?>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_photo_card($photo, $status, $collections, $collection_map) {
    $photo_id = isset($photo['photo_id']) ? (int) $photo['photo_id'] : 0;
    $title = !empty($photo['title']) ? $photo['title'] : 'Untitled photo';
    $filename = !empty($photo['original_filename']) ? $photo['original_filename'] : '';
    $caption = !empty($photo['caption']) ? $photo['caption'] : '';
    $photographer = !empty($photo['photographer_name']) ? $photo['photographer_name'] : '';
    $uploader = !empty($photo['uploader_name']) ? $photo['uploader_name'] : '';
    $uploader_email = !empty($photo['uploader_email']) ? $photo['uploader_email'] : '';
    $created_at = !empty($photo['created_at']) ? $photo['created_at'] : '';
    $processed_size = !empty($photo['processed_file_size_bytes']) ? (int) $photo['processed_file_size_bytes'] : 0;
    $dimensions = '';

    if (!empty($photo['processed_width']) && !empty($photo['processed_height'])) {
        $dimensions = (int) $photo['processed_width'] . ' × ' . (int) $photo['processed_height'];
    }

    $selected_collection_ids = isset($collection_map[$photo_id]) ? $collection_map[$photo_id] : array();

    if (empty($selected_collection_ids) && !empty($photo['suggested_collection_id'])) {
        $selected_collection_ids = array((int) $photo['suggested_collection_id']);
    }

    $image_url = '';

    if (!empty($photo['storage_path']) && empty($photo['storage_deleted_at'])) {
        $signed_url = pba_photo_storage_create_signed_url($photo['storage_path'], 3600, !empty($photo['storage_bucket']) ? $photo['storage_bucket'] : PBA_PHOTO_STORAGE_BUCKET);

        if (!is_wp_error($signed_url)) {
            $image_url = $signed_url;
        }
    }

    ob_start();
    ?>
    <article class="pba-photo-admin-card">
        <div class="pba-photo-admin-card-media">
            <?php if ($image_url !== '') : ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
            <?php else : ?>
                <div class="pba-photo-admin-image-missing">
                    Image not available
                </div>
            <?php endif; ?>
        </div>

        <div class="pba-photo-admin-card-body">
            <div class="pba-photo-admin-card-topline">
                <span class="pba-photo-status-pill pba-photo-status-<?php echo esc_attr($status); ?>">
                    <?php echo esc_html(ucfirst($status)); ?>
                </span>
                <?php if ($processed_size > 0) : ?>
                    <span class="pba-photo-admin-muted">
                        <?php echo esc_html(pba_photo_format_bytes($processed_size)); ?>
                    </span>
                <?php endif; ?>
                <?php if ($dimensions !== '') : ?>
                    <span class="pba-photo-admin-muted">
                        <?php echo esc_html($dimensions); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($photo['is_featured'])) : ?>
                    <span class="pba-photo-featured-pill">Featured</span>
                <?php endif; ?>

                <?php if (isset($photo['sort_order']) && (int) $photo['sort_order'] !== 0) : ?>
                    <span class="pba-photo-admin-muted">
                        Sort: <?php echo esc_html((int) $photo['sort_order']); ?>
                    </span>
                <?php endif; ?>    
            </div>

            <h2 class="pba-photo-admin-card-title">
                <?php echo esc_html($title); ?>
            </h2>

            <div class="pba-photo-admin-meta">
                <?php if ($filename !== '') : ?>
                    <div><strong>File:</strong> <?php echo esc_html($filename); ?></div>
                <?php endif; ?>
                <?php if ($uploader !== '' || $uploader_email !== '') : ?>
                    <div>
                        <strong>Uploaded by:</strong>
                        <?php echo esc_html(trim($uploader . ($uploader_email !== '' ? ' <' . $uploader_email . '>' : ''))); ?>
                    </div>
                <?php endif; ?>
                <?php if ($created_at !== '') : ?>
                    <div><strong>Uploaded:</strong> <?php echo esc_html(pba_photo_format_admin_date($created_at)); ?></div>
                <?php endif; ?>
                <?php if (!empty($photo['denial_reason'])) : ?>
                    <div><strong>Denial reason:</strong> <?php echo esc_html($photo['denial_reason']); ?></div>
                <?php endif; ?>
                <?php if (!empty($photo['unpublish_reason'])) : ?>
                    <div><strong>Unpublish reason:</strong> <?php echo esc_html($photo['unpublish_reason']); ?></div>
                <?php endif; ?>
                <?php if (!empty($photo['delete_reason'])) : ?>
                    <div><strong>Delete reason:</strong> <?php echo esc_html($photo['delete_reason']); ?></div>
                <?php endif; ?>
            </div>

            <?php if (in_array($status, array('pending', 'approved', 'unpublished'), true)) : ?>
                <?php echo pba_photo_render_admin_edit_form($photo, $status, $collections, $selected_collection_ids, $caption, $photographer); ?>
            <?php else : ?>
                <?php echo pba_photo_render_admin_readonly_details($caption, $photographer, $selected_collection_ids, $collections); ?>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_edit_form($photo, $status, $collections, $selected_collection_ids, $caption, $photographer) {
    $photo_id = isset($photo['photo_id']) ? (int) $photo['photo_id'] : 0;
    $title = !empty($photo['title']) ? $photo['title'] : '';
    $sort_order = isset($photo['sort_order']) ? (int) $photo['sort_order'] : 0;
    $is_featured = !empty($photo['is_featured']);

    ob_start();
    ?>
    <div class="pba-photo-admin-forms">
        <?php if ($status === 'pending') : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-form">
                <input type="hidden" name="action" value="pba_photo_admin_approve">
                <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                <?php wp_nonce_field('pba_photo_admin_approve', 'pba_photo_admin_nonce'); ?>

                <?php echo pba_photo_render_admin_metadata_fields($title, $caption, $photographer, $collections, $selected_collection_ids); ?>

                <div class="pba-photo-admin-actions">
                    <button type="submit" class="pba-button pba-button-primary">Approve</button>
                </div>
            </form>

            <div class="pba-photo-admin-secondary-actions">
                <?php echo pba_photo_render_deny_form($photo_id); ?>
                <?php echo pba_photo_render_delete_form($photo_id, 'Delete Pending Photo'); ?>
            </div>

        <?php elseif (in_array($status, array('approved', 'unpublished'), true)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-form pba-photo-library-edit-form">
                <input type="hidden" name="action" value="pba_photo_admin_update_library_photo">
                <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                <?php wp_nonce_field('pba_photo_admin_update_library_photo', 'pba_photo_admin_nonce'); ?>

                <?php echo pba_photo_render_admin_library_fields($title, $caption, $photographer, $collections, $selected_collection_ids, $sort_order, $is_featured); ?>

                <div class="pba-photo-admin-actions">
                    <button type="submit" class="pba-button pba-button-primary">
                        Save Photo Details
                    </button>
                </div>
            </form>

            <div class="pba-photo-admin-secondary-actions">
                <?php if ($status === 'approved') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-inline-form">
                        <input type="hidden" name="action" value="pba_photo_admin_unpublish">
                        <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                        <?php wp_nonce_field('pba_photo_admin_unpublish', 'pba_photo_admin_nonce'); ?>

                        <label>
                            Reason for unpublishing
                            <textarea name="unpublish_reason" rows="2" placeholder="Optional"></textarea>
                        </label>

                        <div class="pba-photo-admin-actions">
                            <button type="submit" class="pba-button pba-button-secondary">Unpublish</button>
                        </div>
                    </form>

                    <?php echo pba_photo_render_delete_form($photo_id, 'Delete Approved Photo'); ?>

                <?php elseif ($status === 'unpublished') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-inline-form">
                        <input type="hidden" name="action" value="pba_photo_admin_republish">
                        <input type="hidden" name="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                        <?php wp_nonce_field('pba_photo_admin_republish', 'pba_photo_admin_nonce'); ?>

                        <div class="pba-photo-admin-actions">
                            <button type="submit" class="pba-button pba-button-primary">Republish</button>
                        </div>
                    </form>

                    <?php echo pba_photo_render_delete_form($photo_id, 'Delete Unpublished Photo'); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_metadata_fields($title, $caption, $photographer, $collections, $selected_collection_ids) {
    ob_start();
    ?>
    <div class="pba-photo-admin-field-grid">
        <label>
            Title
            <input type="text" name="title" maxlength="150" value="<?php echo esc_attr($title); ?>" placeholder="Photo title">
        </label>

        <label>
            Photographer / Source
            <input type="text" name="photographer_name" maxlength="150" value="<?php echo esc_attr($photographer); ?>" placeholder="Optional">
        </label>

        <label class="pba-photo-admin-field-full">
            Caption
            <textarea name="caption" rows="3" placeholder="Optional caption"><?php echo esc_textarea($caption); ?></textarea>
        </label>

        <div class="pba-photo-admin-field-full">
            <div class="pba-photo-admin-label">Collections</div>
            <div class="pba-photo-admin-collection-list">
                <?php if (empty($collections)) : ?>
                    <span class="pba-photo-admin-muted">No active collections found.</span>
                <?php else : ?>
                    <?php foreach ($collections as $collection) : ?>
                        <?php
                        $collection_id = isset($collection['collection_id']) ? (int) $collection['collection_id'] : 0;
                        $checked = in_array($collection_id, $selected_collection_ids, true);
                        ?>
                        <label class="pba-photo-admin-collection-option">
                            <input
                                type="checkbox"
                                name="collection_ids[]"
                                value="<?php echo esc_attr($collection_id); ?>"
                                <?php checked($checked); ?>
                            >
                            <span><?php echo esc_html($collection['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
function pba_photo_render_admin_library_fields($title, $caption, $photographer, $collections, $selected_collection_ids, $sort_order, $is_featured) {
    ob_start();
    ?>
    <div class="pba-photo-admin-field-grid">
        <label>
            Title
            <input type="text" name="title" maxlength="150" value="<?php echo esc_attr($title); ?>" placeholder="Photo title">
        </label>

        <label>
            Photographer / Source
            <input type="text" name="photographer_name" maxlength="150" value="<?php echo esc_attr($photographer); ?>" placeholder="Optional">
        </label>

        <label class="pba-photo-admin-field-full">
            Caption
            <textarea name="caption" rows="3" placeholder="Optional caption"><?php echo esc_textarea($caption); ?></textarea>
        </label>

        <label>
            Public Sort Order
            <input type="number" name="sort_order" value="<?php echo esc_attr((int) $sort_order); ?>" step="1">
        </label>

        <div class="pba-photo-featured-control">
            <label class="pba-photo-admin-checkbox-label">
                <input type="checkbox" name="is_featured" value="1" <?php checked($is_featured); ?>>
                <span>Feature this photo</span>
            </label>
            <p>Featured photos appear before regular photos in the public gallery.</p>
        </div>

        <div class="pba-photo-admin-field-full">
            <div class="pba-photo-admin-label">Collections</div>
            <div class="pba-photo-admin-collection-list">
                <?php if (empty($collections)) : ?>
                    <span class="pba-photo-admin-muted">No active collections found.</span>
                <?php else : ?>
                    <?php foreach ($collections as $collection) : ?>
                        <?php
                        $collection_id = isset($collection['collection_id']) ? (int) $collection['collection_id'] : 0;
                        $checked = in_array($collection_id, $selected_collection_ids, true);
                        ?>
                        <label class="pba-photo-admin-collection-option">
                            <input
                                type="checkbox"
                                name="collection_ids[]"
                                value="<?php echo esc_attr($collection_id); ?>"
                                <?php checked($checked); ?>
                            >
                            <span><?php echo esc_html($collection['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="pba-photo-admin-help">
                A photo may belong to multiple collections.
            </p>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_render_deny_form($photo_id) {
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-inline-form">
        <input type="hidden" name="action" value="pba_photo_admin_deny">
        <input type="hidden" name="photo_id" value="<?php echo esc_attr((int) $photo_id); ?>">
        <?php wp_nonce_field('pba_photo_admin_deny', 'pba_photo_admin_nonce'); ?>

        <label>
            Reason for denial
            <textarea name="denial_reason" rows="2" placeholder="Optional"></textarea>
        </label>

        <div class="pba-photo-admin-actions">
            <button type="submit" class="pba-button pba-button-secondary" onclick="return confirm('Deny this photo? The stored image will be deleted.');">
                Deny
            </button>
        </div>
    </form>
    <?php

    return ob_get_clean();
}

function pba_photo_render_delete_form($photo_id, $button_label) {
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-admin-inline-form">
        <input type="hidden" name="action" value="pba_photo_admin_delete">
        <input type="hidden" name="photo_id" value="<?php echo esc_attr((int) $photo_id); ?>">
        <?php wp_nonce_field('pba_photo_admin_delete', 'pba_photo_admin_nonce'); ?>

        <label>
            Reason for delete
            <textarea name="delete_reason" rows="2" placeholder="Optional"></textarea>
        </label>

        <div class="pba-photo-admin-actions">
            <button type="submit" class="pba-button pba-button-danger" onclick="return confirm('Delete this photo? This will remove the stored image and cannot be undone.');">
                <?php echo esc_html($button_label); ?>
            </button>
        </div>
    </form>
    <?php

    return ob_get_clean();
}

function pba_photo_render_admin_readonly_details($caption, $photographer, $selected_collection_ids, $collections) {
    $collection_names = array();

    foreach ($collections as $collection) {
        $collection_id = isset($collection['collection_id']) ? (int) $collection['collection_id'] : 0;

        if (in_array($collection_id, $selected_collection_ids, true)) {
            $collection_names[] = $collection['name'];
        }
    }

    ob_start();
    ?>
    <div class="pba-photo-admin-readonly">
        <?php if ($caption !== '') : ?>
            <div><strong>Caption:</strong> <?php echo esc_html($caption); ?></div>
        <?php endif; ?>

        <?php if ($photographer !== '') : ?>
            <div><strong>Photographer / Source:</strong> <?php echo esc_html($photographer); ?></div>
        <?php endif; ?>

        <?php if (!empty($collection_names)) : ?>
            <div><strong>Collections:</strong> <?php echo esc_html(implode(', ', $collection_names)); ?></div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_format_admin_date($date_value) {
    $timestamp = strtotime($date_value);

    if (!$timestamp) {
        return $date_value;
    }

    return date_i18n('M j, Y g:i A', $timestamp);
}

function pba_photo_render_admin_message($message) {
    if ($message === '') {
        return '';
    }

    $success_messages = array(
        'approved'    => 'Photo approved and added to the public photo workflow.',
        'denied'      => 'Photo denied and stored image removed.',
        'unpublished' => 'Photo unpublished.',
        'republished' => 'Photo republished.',
        'deleted'     => 'Photo deleted and stored image removed.',
        'collection_created'     => 'Collection created.',
        'collection_updated'     => 'Collection updated.',
        'collection_activated'   => 'Collection activated.',
        'collection_deactivated' => 'Collection deactivated.',
        'collection_deleted'     => 'Collection deleted. Photos were not deleted.',
        'library_updated' => 'Photo details and collection assignments updated.',
    );

    $error_messages = array(
        'not_allowed'                       => 'You do not have permission to manage photos.',
        'security_error'                    => 'The admin action could not be verified. Please try again.',
        'photo_not_found'                   => 'The selected photo could not be found.',
        'approve_failed'                    => 'The photo could not be approved.',
        'deny_failed'                       => 'The photo could not be denied.',
        'delete_failed'                     => 'The photo could not be deleted.',
        'unpublish_failed'                  => 'The photo could not be unpublished.',
        'republish_failed'                  => 'The photo could not be republished.',
        'storage_delete_failed'             => 'The stored image could not be deleted. The photo was not updated.',
        'collection_assign_failed'          => 'The photo was approved, but collection assignment failed.',
        'cannot_republish_deleted_storage'  => 'This photo cannot be republished because the stored image was already deleted.',
        'collection_name_required'     => 'Collection name is required.',
        'collection_not_found'         => 'The selected collection could not be found.',
        'collection_create_failed'     => 'The collection could not be created.',
        'collection_update_failed'     => 'The collection could not be updated.',
        'collection_activate_failed'   => 'The collection could not be activated.',
        'collection_deactivate_failed' => 'The collection could not be deactivated.',
        'collection_delete_failed'     => 'The collection could not be deleted.',
        'library_update_invalid_status'       => 'Only approved or unpublished photos can be updated from the library view.',
        'library_update_failed'               => 'The photo details could not be updated.',
        'library_collection_update_failed'    => 'The photo details were updated, but collection assignment failed.',
        );

    if (isset($success_messages[$message])) {
        return pba_photo_render_notice('success', $success_messages[$message]);
    }

    if (isset($error_messages[$message])) {
        return pba_photo_render_notice('error', $error_messages[$message]);
    }

    return '';
}
function pba_photo_render_collections_admin() {
    $collections = pba_photo_admin_get_all_collections_for_management();

    ob_start();
    ?>
    <div class="pba-photo-collections-admin">
        <section class="pba-photo-collection-create-card">
            <div class="pba-photo-collection-section-header">
                <div>
                    <h2>Create Collection</h2>
                    <p>Add a new public photo collection. Inactive collections are hidden from the public Photos page.</p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-collection-form">
                <input type="hidden" name="action" value="pba_photo_collection_create">
                <?php wp_nonce_field('pba_photo_collection_create', 'pba_photo_admin_nonce'); ?>

                <div class="pba-photo-collection-form-grid">
                    <label>
                        Name <span class="pba-required">*</span>
                        <input type="text" name="name" maxlength="150" required placeholder="Example: Beach">
                    </label>

                    <label>
                        Slug
                        <input type="text" name="slug" maxlength="150" placeholder="Example: beach">
                    </label>

                    <label>
                        Display Order
                        <input type="number" name="display_order" value="0" step="1">
                    </label>

                    <label class="pba-photo-collection-field-full">
                        Description
                        <textarea name="description" rows="3" placeholder="Optional public collection description"></textarea>
                    </label>
                </div>

                <div class="pba-photo-admin-actions">
                    <button type="submit" class="pba-button pba-button-primary">
                        Create Collection
                    </button>
                </div>
            </form>
        </section>

        <section class="pba-photo-collection-list-card">
            <div class="pba-photo-collection-section-header">
                <div>
                    <h2>Manage Collections</h2>
                    <p>Edit, activate, deactivate, or delete existing collections.</p>
                </div>
            </div>

            <?php if (empty($collections)) : ?>
                <div class="pba-photo-empty-state">No collections found.</div>
            <?php else : ?>
                <div class="pba-photo-collection-admin-list">
                    <?php foreach ($collections as $collection) : ?>
                        <?php echo pba_photo_render_collection_admin_row($collection); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_admin_get_all_collections_for_management() {
    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select' => '*',
        'order'  => 'display_order.asc,name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_render_collection_admin_row($collection) {
    $collection_id = isset($collection['collection_id']) ? (int) $collection['collection_id'] : 0;
    $name = !empty($collection['name']) ? $collection['name'] : '';
    $slug = !empty($collection['slug']) ? $collection['slug'] : '';
    $description = !empty($collection['description']) ? $collection['description'] : '';
    $display_order = isset($collection['display_order']) ? (int) $collection['display_order'] : 0;
    $is_active = !empty($collection['is_active']);
    $photo_count = pba_photo_admin_get_collection_photo_count($collection_id);

    ob_start();
    ?>
    <article class="pba-photo-collection-admin-row">
        <div class="pba-photo-collection-admin-summary">
            <div>
                <div class="pba-photo-collection-title-line">
                    <h3><?php echo esc_html($name); ?></h3>
                    <span class="pba-photo-collection-status <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
                        <?php echo esc_html($is_active ? 'Active' : 'Inactive'); ?>
                    </span>
                </div>

                <div class="pba-photo-collection-meta">
                    <span>Slug: <strong><?php echo esc_html($slug); ?></strong></span>
                    <span>Order: <strong><?php echo esc_html($display_order); ?></strong></span>
                    <span>Photos: <strong><?php echo esc_html(number_format($photo_count)); ?></strong></span>
                </div>

                <?php if ($description !== '') : ?>
                    <p class="pba-photo-collection-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pba-photo-collection-form pba-photo-collection-edit-form">
            <input type="hidden" name="action" value="pba_photo_collection_update">
            <input type="hidden" name="collection_id" value="<?php echo esc_attr($collection_id); ?>">
            <?php wp_nonce_field('pba_photo_collection_update', 'pba_photo_admin_nonce'); ?>

            <div class="pba-photo-collection-form-grid">
                <label>
                    Name <span class="pba-required">*</span>
                    <input type="text" name="name" maxlength="150" required value="<?php echo esc_attr($name); ?>">
                </label>

                <label>
                    Slug
                    <input type="text" name="slug" maxlength="150" value="<?php echo esc_attr($slug); ?>">
                </label>

                <label>
                    Display Order
                    <input type="number" name="display_order" value="<?php echo esc_attr($display_order); ?>" step="1">
                </label>

                <label class="pba-photo-collection-field-full">
                    Description
                    <textarea name="description" rows="3"><?php echo esc_textarea($description); ?></textarea>
                </label>
            </div>

            <div class="pba-photo-admin-actions">
                <button type="submit" class="pba-button pba-button-primary">
                    Save Changes
                </button>
            </div>
        </form>

        <div class="pba-photo-collection-row-actions">
            <?php if ($is_active) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="pba_photo_collection_deactivate">
                    <input type="hidden" name="collection_id" value="<?php echo esc_attr($collection_id); ?>">
                    <?php wp_nonce_field('pba_photo_collection_deactivate', 'pba_photo_admin_nonce'); ?>
                    <button type="submit" class="pba-button pba-button-secondary">
                        Deactivate
                    </button>
                </form>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="pba_photo_collection_activate">
                    <input type="hidden" name="collection_id" value="<?php echo esc_attr($collection_id); ?>">
                    <?php wp_nonce_field('pba_photo_collection_activate', 'pba_photo_admin_nonce'); ?>
                    <button type="submit" class="pba-button pba-button-secondary">
                        Activate
                    </button>
                </form>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="pba_photo_collection_delete">
                <input type="hidden" name="collection_id" value="<?php echo esc_attr($collection_id); ?>">
                <?php wp_nonce_field('pba_photo_collection_delete', 'pba_photo_admin_nonce'); ?>
                <button
                    type="submit"
                    class="pba-button pba-button-danger"
                    onclick="return confirm('Delete this collection? Photos will not be deleted, but they will no longer belong to this collection.');"
                >
                    Delete Collection
                </button>
            </form>
        </div>
    </article>
    <?php

    return ob_get_clean();
}

function pba_photo_admin_get_collection_photo_count($collection_id) {
    $collection_id = (int) $collection_id;

    if ($collection_id <= 0) {
        return 0;
    }

    $rows = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'        => 'photo_collection_photo_id',
        'collection_id' => 'eq.' . $collection_id,
    ));

    return is_array($rows) ? count($rows) : 0;
}
function pba_photo_render_audit_admin() {
    $filters = pba_photo_get_audit_filters();
    $logs = pba_photo_get_audit_rows($filters);

    ob_start();
    ?>
    <section class="pba-photo-audit-admin">
        <div class="pba-photo-audit-header-card">
            <div class="pba-photo-collection-section-header">
                <div>
                    <h2>Photo Audit Log</h2>
                    <p>Review photo uploads, approvals, deletions, collection changes, and storage-related events.</p>
                </div>
            </div>

            <?php echo pba_photo_render_audit_filters($filters); ?>
        </div>

        <?php if (empty($logs)) : ?>
            <div class="pba-photo-empty-state">No photo audit records found.</div>
        <?php else : ?>
            <div class="pba-photo-audit-table-wrap">
                <table class="pba-photo-audit-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Result</th>
                            <th>Action</th>
                            <th>Actor</th>
                            <th>Entity</th>
                            <th>Summary</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <?php echo pba_photo_render_audit_row($log); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return ob_get_clean();
}

function pba_photo_get_audit_filters() {
    $filters = array(
        'action_type'   => '',
        'result_status' => '',
        'entity_type'   => '',
        'photo_id'      => '',
        'collection_id' => '',
    );

    foreach ($filters as $key => $default) {
        if (!isset($_GET[$key])) {
            continue;
        }

        $value = sanitize_text_field(wp_unslash($_GET[$key]));

        if (in_array($key, array('photo_id', 'collection_id'), true)) {
            $value = $value !== '' ? (string) absint($value) : '';
        } elseif ($key === 'result_status') {
            $value = sanitize_key($value);
            if (!in_array($value, array('', 'success', 'failure'), true)) {
                $value = '';
            }
        } else {
            $value = sanitize_text_field($value);
        }

        $filters[$key] = $value;
    }

    return $filters;
}

function pba_photo_get_audit_rows($filters, $limit = 100) {
    $query = array(
        'select' => '*',
        'order'  => 'created_at.desc',
        'limit'  => max(1, (int) $limit),
    );

    if (!empty($filters['action_type'])) {
        $query['action_type'] = 'eq.' . $filters['action_type'];
    }

    if (!empty($filters['result_status'])) {
        $query['result_status'] = 'eq.' . $filters['result_status'];
    }

    if (!empty($filters['entity_type'])) {
        $query['entity_type'] = 'eq.' . $filters['entity_type'];
    }

    if (!empty($filters['photo_id'])) {
        $query['photo_id'] = 'eq.' . absint($filters['photo_id']);
    }

    if (!empty($filters['collection_id'])) {
        $query['collection_id'] = 'eq.' . absint($filters['collection_id']);
    }

    $rows = pba_photo_supabase_select('PhotoAuditLog', $query);

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_render_audit_filters($filters) {
    $action_options = pba_photo_get_audit_action_options();
    $entity_options = array(
        ''                => 'All entities',
        'Photo'           => 'Photo',
        'PhotoCollection' => 'Photo Collection',
    );

    $result_options = array(
        ''        => 'All results',
        'success' => 'Success',
        'failure' => 'Failure',
    );

    ob_start();
    ?>
    <form method="get" action="<?php echo esc_url(home_url('/photo-admin/')); ?>" class="pba-photo-audit-filter-form">
        <input type="hidden" name="tab" value="audit">

        <label>
            Action
            <select name="action_type">
                <?php foreach ($action_options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['action_type'], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Result
            <select name="result_status">
                <?php foreach ($result_options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['result_status'], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Entity
            <select name="entity_type">
                <?php foreach ($entity_options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filters['entity_type'], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Photo ID
            <input type="number" name="photo_id" min="1" value="<?php echo esc_attr($filters['photo_id']); ?>" placeholder="Any">
        </label>

        <label>
            Collection ID
            <input type="number" name="collection_id" min="1" value="<?php echo esc_attr($filters['collection_id']); ?>" placeholder="Any">
        </label>

        <div class="pba-photo-audit-filter-actions">
            <button type="submit" class="pba-button pba-button-primary">
                Apply Filters
            </button>
            <a class="pba-button pba-button-secondary" href="<?php echo esc_url(add_query_arg(array('tab' => 'audit'), home_url('/photo-admin/'))); ?>">
                Clear
            </a>
        </div>
    </form>
    <?php

    return ob_get_clean();
}

function pba_photo_get_audit_action_options() {
    return array(
        ''                                      => 'All actions',

        'photo.uploaded'                        => 'Photo uploaded',
        'photo.upload.failed'                   => 'Photo upload failed',
        'photo.processing.failed'               => 'Photo processing failed',
        'photo.storage.upload.failed'           => 'Storage upload failed',

        'photo.approved'                        => 'Photo approved',
        'photo.approve.failed'                  => 'Photo approve failed',
        'photo.denied'                          => 'Photo denied',
        'photo.deny.failed'                     => 'Photo deny failed',
        'photo.unpublished'                     => 'Photo unpublished',
        'photo.republished'                     => 'Photo republished',
        'photo.deleted'                         => 'Photo deleted',
        'photo.storage.delete.failed'           => 'Storage delete failed',

        'photo.library.updated'                 => 'Photo library updated',
        'photo.library.update.failed'           => 'Photo library update failed',
        'photo.library.collection.update.failed'=> 'Photo library collection update failed',

        'photo.collection.created'              => 'Collection created',
        'photo.collection.updated'              => 'Collection updated',
        'photo.collection.activated'            => 'Collection activated',
        'photo.collection.deactivated'          => 'Collection deactivated',
        'photo.collection.deleted'              => 'Collection deleted',

        'photo.collection.create.failed'        => 'Collection create failed',
        'photo.collection.update.failed'        => 'Collection update failed',
        'photo.collection.activate.failed'      => 'Collection activate failed',
        'photo.collection.deactivate.failed'    => 'Collection deactivate failed',
        'photo.collection.delete.failed'        => 'Collection delete failed',

        'photo.admin.security_failed'           => 'Admin security failed',
    );
}

function pba_photo_render_audit_row($log) {
    $created_at = !empty($log['created_at']) ? pba_photo_format_admin_date($log['created_at']) : '';
    $result_status = !empty($log['result_status']) ? sanitize_key($log['result_status']) : 'success';
    $action_type = !empty($log['action_type']) ? $log['action_type'] : '';
    $entity_type = !empty($log['entity_type']) ? $log['entity_type'] : '';
    $entity_id = isset($log['entity_id']) && $log['entity_id'] !== null ? (int) $log['entity_id'] : null;
    $entity_label = !empty($log['entity_label']) ? $log['entity_label'] : '';
    $summary = !empty($log['summary']) ? $log['summary'] : '';

    $actor_parts = array();

    if (!empty($log['actor_email_address'])) {
        $actor_parts[] = $log['actor_email_address'];
    }

    if (!empty($log['actor_wp_user_id'])) {
        $actor_parts[] = 'WP #' . (int) $log['actor_wp_user_id'];
    }

    if (!empty($log['actor_person_id'])) {
        $actor_parts[] = 'Person #' . (int) $log['actor_person_id'];
    }

    $actor = !empty($actor_parts) ? implode(' · ', $actor_parts) : 'System / Unknown';

    $entity_parts = array();

    if ($entity_type !== '') {
        $entity_parts[] = $entity_type;
    }

    if ($entity_id !== null) {
        $entity_parts[] = '#' . $entity_id;
    }

    if ($entity_label !== '') {
        $entity_parts[] = $entity_label;
    }

    if (!empty($log['photo_id'])) {
        $entity_parts[] = 'Photo #' . (int) $log['photo_id'];
    }

    if (!empty($log['collection_id'])) {
        $entity_parts[] = 'Collection #' . (int) $log['collection_id'];
    }

    $entity_display = !empty($entity_parts) ? implode(' · ', $entity_parts) : '—';

    $details = pba_photo_audit_compact_json(array(
        'before'  => isset($log['before_json']) ? $log['before_json'] : null,
        'after'   => isset($log['after_json']) ? $log['after_json'] : null,
        'details' => isset($log['details_json']) ? $log['details_json'] : null,
    ));

    ob_start();
    ?>
    <tr>
        <td data-label="Date">
            <?php echo esc_html($created_at); ?>
        </td>

        <td data-label="Result">
            <span class="pba-photo-audit-result pba-photo-audit-result-<?php echo esc_attr($result_status); ?>">
                <?php echo esc_html(ucfirst($result_status)); ?>
            </span>
        </td>

        <td data-label="Action">
            <code><?php echo esc_html($action_type); ?></code>
        </td>

        <td data-label="Actor">
            <?php echo esc_html($actor); ?>
        </td>

        <td data-label="Entity">
            <?php echo esc_html($entity_display); ?>
        </td>

        <td data-label="Summary">
            <?php echo esc_html($summary); ?>
        </td>

        <td data-label="Details">
            <?php if ($details !== '') : ?>
                <details class="pba-photo-audit-details">
                    <summary>View</summary>
                    <pre><?php echo esc_html($details); ?></pre>
                </details>
            <?php else : ?>
                —
            <?php endif; ?>
        </td>
    </tr>
    <?php

    return ob_get_clean();
}

function pba_photo_audit_compact_json($value) {
    if ($value === null || $value === '' || $value === array()) {
        return '';
    }

    /*
     * Supabase json/jsonb values may come back as arrays or JSON strings.
     */
    if (is_string($value)) {
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $value = $decoded;
        }
    }

    if (!is_array($value)) {
        return '';
    }

    $clean = array();

    foreach ($value as $key => $item) {
        if ($item === null || $item === '' || $item === array()) {
            continue;
        }

        if (is_string($item)) {
            $decoded = json_decode($item, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $item = $decoded;
            }
        }

        $clean[$key] = $item;
    }

    if (empty($clean)) {
        return '';
    }

    return wp_json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
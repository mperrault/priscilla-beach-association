<?php
/**
 * PBA Photo Feature - Member Upload Shortcode
 *
 * Shortcode: [pba_photo_upload]
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('pba_photo_upload', 'pba_render_photo_upload_shortcode');

function pba_render_photo_upload_shortcode() {
    pba_enqueue_photo_assets();
    if (!pba_photo_current_user_can_upload()) {
        return pba_photo_render_notice(
            'error',
            'Please log in with your PBA member account to upload photos.'
        );
    }

    $collections = pba_photo_get_active_collections_for_select();
    $message = isset($_GET['pba_photo_message'])
        ? sanitize_key(wp_unslash($_GET['pba_photo_message']))
        : '';

    ob_start();
    ?>
    <div class="pba-photo-page pba-photo-upload-page">
        <div class="pba-photo-page-header">
            <div class="pba-photo-page-header-copy">
                <p class="pba-photo-page-kicker">Member Photo Submission</p>
                <p class="pba-photo-page-subtitle">
                    Submit a photo for PBAAdmin review. Approved photos may appear on the public Photos page.
                </p>
            </div>

            <div class="pba-photo-page-actions">
                <a class="pba-button pba-button-secondary" href="<?php echo esc_url(home_url('/photos/')); ?>">
                    View Photos
                </a>
            </div>
        </div>

        <div class="pba-photo-info-panel">
            <div class="pba-photo-info-item">
                <div class="pba-photo-info-label">Review</div>
                <div class="pba-photo-info-text">All uploaded photos are reviewed by a PBAAdmin before being shown publicly.</div>
            </div>
            <div class="pba-photo-info-item">
                <div class="pba-photo-info-label">File types</div>
                <div class="pba-photo-info-text">JPG, PNG, or WebP. Maximum upload size: <?php echo esc_html(pba_photo_format_bytes(PBA_PHOTO_MAX_UPLOAD_BYTES, 0)); ?>.</div>
            </div>
            <div class="pba-photo-info-item">
                <div class="pba-photo-info-label">Storage</div>
                <div class="pba-photo-info-text">Photos are resized and compressed before storage to conserve space.</div>
            </div>
        </div>

        <?php echo pba_photo_render_upload_message($message); ?>

        <form class="pba-photo-upload-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="pba_photo_upload">
            <?php wp_nonce_field('pba_photo_upload', 'pba_photo_upload_nonce'); ?>

            <div class="pba-photo-form-card">
                <div class="pba-photo-form-grid">
                    <div class="pba-photo-form-row pba-photo-form-row-full">
                        <label for="pba_photo_file">Photo <span class="pba-required">*</span></label>

                        <div class="pba-photo-file-picker" data-pba-photo-file-picker>
                            <input
                                id="pba_photo_file"
                                class="pba-photo-native-file-input"
                                name="pba_photo_file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                            >
                            <label for="pba_photo_file" class="pba-button pba-button-secondary pba-photo-file-button">
                                Choose Photo
                            </label>
                            <span class="pba-photo-file-name" data-pba-photo-file-name>No file selected</span>
                        </div>
                    </div>

                    <div class="pba-photo-form-row">
                        <label for="pba_photo_title">Title</label>
                        <input
                            id="pba_photo_title"
                            name="pba_photo_title"
                            type="text"
                            maxlength="150"
                            placeholder="Example: Sunset at Priscilla Beach"
                        >
                    </div>

                    <div class="pba-photo-form-row">
                        <label for="pba_photo_photographer_name">Photographer / Source</label>
                        <input
                            id="pba_photo_photographer_name"
                            name="pba_photo_photographer_name"
                            type="text"
                            maxlength="150"
                            placeholder="Optional"
                        >
                    </div>

                    <div class="pba-photo-form-row pba-photo-form-row-full">
                        <label for="pba_photo_caption">Caption</label>
                        <textarea
                            id="pba_photo_caption"
                            name="pba_photo_caption"
                            rows="5"
                            placeholder="Optional short description"
                        ></textarea>
                    </div>

                    <div class="pba-photo-form-row">
                        <label for="pba_photo_suggested_collection_id">Suggested Collection</label>
                        <select id="pba_photo_suggested_collection_id" name="pba_photo_suggested_collection_id">
                            <option value="0">No suggestion</option>
                            <?php foreach ($collections as $collection) : ?>
                                <option value="<?php echo esc_attr((int) $collection['collection_id']); ?>">
                                    <?php echo esc_html($collection['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pba-photo-form-row">
                        <div class="pba-photo-side-note">
                            <div class="pba-photo-side-note-title">Collection note</div>
                            <div class="pba-photo-side-note-text">
                                You may suggest a collection, but a PBAAdmin will make the final assignment.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pba-photo-form-divider"></div>

                <div class="pba-photo-form-row pba-photo-checkbox-row">
                    <label for="pba_photo_permission_confirmed" class="pba-photo-checkbox-label">
                        <input id="pba_photo_permission_confirmed" type="checkbox" name="pba_photo_permission_confirmed" value="1" required>
                        <span>
                            I confirm that PBA may display this photo on the Priscilla Beach Association website.
                        </span>
                    </label>
                </div>

                <div class="pba-photo-form-actions">
                    <button class="pba-button pba-button-primary" type="submit">
                        Submit Photo for Review
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_get_active_collections_for_select() {
    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select'    => 'collection_id,name,slug,display_order',
        'is_active' => 'eq.true',
        'order'     => 'display_order.asc,name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_render_upload_message($message) {
    if ($message === '') {
        return '';
    }

    switch ($message) {
        case 'upload_success':
            return pba_photo_render_notice(
                'success',
                'Thank you. Your photo has been submitted for PBAAdmin review.'
            );

        case 'not_allowed':
            return pba_photo_render_notice(
                'error',
                'You do not have permission to upload photos.'
            );

        case 'security_error':
            return pba_photo_render_notice(
                'error',
                'The photo upload could not be verified. Please try again.'
            );

        case 'missing_file':
            return pba_photo_render_notice(
                'error',
                'Please select a photo to upload.'
            );

        case 'permission_required':
            return pba_photo_render_notice(
                'error',
                'Please confirm that PBA may display the photo before submitting.'
            );

        case 'processing_failed':
            return pba_photo_render_notice(
                'error',
                'The photo could not be processed. Please try a different JPG, PNG, or WebP image.'
            );

        case 'storage_failed':
            return pba_photo_render_notice(
                'error',
                'The photo could not be stored. Please try again.'
            );

        case 'database_failed':
            return pba_photo_render_notice(
                'error',
                'The photo was processed, but the submission could not be saved. Please try again.'
            );

        default:
            return '';
    }
}

function pba_photo_render_notice($type, $message) {
    $type = $type === 'success' ? 'success' : 'error';

    $class = $type === 'success'
        ? 'pba-photo-notice pba-photo-notice-success'
        : 'pba-photo-notice pba-photo-notice-error';

    $icon = $type === 'success' ? '✓' : '×';

    return sprintf(
        '<div class="%1$s"><span class="pba-photo-notice-icon" aria-hidden="true">%2$s</span><span>%3$s</span></div>',
        esc_attr($class),
        esc_html($icon),
        esc_html($message)
    );
}

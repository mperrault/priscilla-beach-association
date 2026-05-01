<?php
/**
 * PBA Photo Feature - Public Photos Shortcode
 *
 * Shortcode: [pba_photos]
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('pba_photos', 'pba_render_photos_shortcode');

function pba_render_photos_shortcode() {
    if (function_exists('pba_enqueue_photo_assets')) {
        pba_enqueue_photo_assets();
    }

    $selected_collection_slug = isset($_GET['collection'])
        ? sanitize_title(wp_unslash($_GET['collection']))
        : '';

    $collections = pba_photo_public_get_active_collections();
    $selected_collection = null;

    if ($selected_collection_slug !== '') {
        foreach ($collections as $collection) {
            if (!empty($collection['slug']) && $collection['slug'] === $selected_collection_slug) {
                $selected_collection = $collection;
                break;
            }
        }
    }

    $photos = pba_photo_public_get_approved_photos($selected_collection);

    ob_start();
    ?>
    <div class="pba-photo-page pba-public-photos-page">
        <div class="pba-photo-page-header">
            <div class="pba-photo-page-header-copy">
                <p class="pba-photo-page-kicker">Community Photos</p>
                <p class="pba-photo-page-subtitle">
                    Browse approved photos from the Priscilla Beach Association community.
                </p>
            </div>

            <div class="pba-photo-page-actions">
                <?php if (pba_photo_current_user_can_upload()) : ?>
                    <a class="pba-button pba-button-primary" href="<?php echo esc_url(home_url('/photo-upload/')); ?>">
                        Upload Photo
                    </a>
                <?php endif; ?>

                <?php if (pba_photo_current_user_can_manage()) : ?>
                    <a class="pba-button pba-button-secondary" href="<?php echo esc_url(home_url('/photo-admin/')); ?>">
                        Photo Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php echo pba_photo_public_render_collection_filters($collections, $selected_collection_slug); ?>

        <?php if ($selected_collection) : ?>
            <div class="pba-photo-collection-intro">
                <h2><?php echo esc_html($selected_collection['name']); ?></h2>
                <?php if (!empty($selected_collection['description'])) : ?>
                    <p><?php echo esc_html($selected_collection['description']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php echo pba_photo_public_render_grid($photos); ?>

        <?php echo pba_photo_public_render_lightbox(); ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_get_active_collections() {
    $rows = pba_photo_supabase_select('PhotoCollection', array(
        'select'    => 'collection_id,name,slug,description,display_order,is_active',
        'is_active' => 'eq.true',
        'order'     => 'display_order.asc,name.asc',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return $rows;
}

function pba_photo_public_get_approved_photos($selected_collection = null, $limit = 120) {
    if (is_array($selected_collection) && !empty($selected_collection['collection_id'])) {
        return pba_photo_public_get_approved_photos_for_collection(
            (int) $selected_collection['collection_id'],
            $limit
        );
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'             => '*',
        'status'             => 'eq.approved',
        'visibility'         => 'eq.public',
        'storage_deleted_at' => 'is.null',
        'order'              => 'is_featured.desc,sort_order.asc,approved_at.desc,created_at.desc',
        'limit'              => max(1, (int) $limit),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return pba_photo_public_prepare_photos_for_display($rows);
}

function pba_photo_public_get_approved_photos_for_collection($collection_id, $limit = 120) {
    $collection_id = (int) $collection_id;

    if ($collection_id <= 0) {
        return array();
    }

    $joins = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'        => 'photo_id,sort_order,created_at',
        'collection_id' => 'eq.' . $collection_id,
        'order'         => 'sort_order.asc,created_at.desc',
        'limit'         => max(1, (int) $limit),
    ));

    if (is_wp_error($joins) || !is_array($joins) || empty($joins)) {
        return array();
    }

    $join_order = array();
    $photo_ids = array();

    foreach ($joins as $index => $join) {
        if (!is_array($join) || empty($join['photo_id'])) {
            continue;
        }

        $photo_id = (int) $join['photo_id'];
        $photo_ids[] = $photo_id;

        $join_order[$photo_id] = array(
            'collection_sort_order' => isset($join['sort_order']) ? (int) $join['sort_order'] : 0,
            'join_index'            => (int) $index,
        );
    }

    $photo_ids = array_values(array_unique($photo_ids));

    if (empty($photo_ids)) {
        return array();
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'             => '*',
        'photo_id'           => 'in.(' . implode(',', $photo_ids) . ')',
        'status'             => 'eq.approved',
        'visibility'         => 'eq.public',
        'storage_deleted_at' => 'is.null',
        'limit'              => max(1, (int) $limit),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    usort($rows, function ($a, $b) use ($join_order) {
        $a_id = isset($a['photo_id']) ? (int) $a['photo_id'] : 0;
        $b_id = isset($b['photo_id']) ? (int) $b['photo_id'] : 0;

        $a_featured = !empty($a['is_featured']) ? 1 : 0;
        $b_featured = !empty($b['is_featured']) ? 1 : 0;

        if ($a_featured !== $b_featured) {
            return $b_featured <=> $a_featured;
        }

        $a_collection_sort = isset($join_order[$a_id]['collection_sort_order'])
            ? (int) $join_order[$a_id]['collection_sort_order']
            : 0;

        $b_collection_sort = isset($join_order[$b_id]['collection_sort_order'])
            ? (int) $join_order[$b_id]['collection_sort_order']
            : 0;

        if ($a_collection_sort !== $b_collection_sort) {
            return $a_collection_sort <=> $b_collection_sort;
        }

        $a_photo_sort = isset($a['sort_order']) ? (int) $a['sort_order'] : 0;
        $b_photo_sort = isset($b['sort_order']) ? (int) $b['sort_order'] : 0;

        if ($a_photo_sort !== $b_photo_sort) {
            return $a_photo_sort <=> $b_photo_sort;
        }

        $a_approved = !empty($a['approved_at']) ? strtotime($a['approved_at']) : 0;
        $b_approved = !empty($b['approved_at']) ? strtotime($b['approved_at']) : 0;

        return $b_approved <=> $a_approved;
    });

    return pba_photo_public_prepare_photos_for_display($rows);
}

function pba_photo_public_prepare_photos_for_display($rows) {
    $photos = array();

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (empty($row['storage_path'])) {
            continue;
        }

        $signed_url = pba_photo_storage_create_signed_url(
            $row['storage_path'],
            3600,
            !empty($row['storage_bucket']) ? $row['storage_bucket'] : PBA_PHOTO_STORAGE_BUCKET
        );

        if (is_wp_error($signed_url)) {
            continue;
        }

        $row['_signed_url'] = $signed_url;
        $photos[] = $row;
    }

    return $photos;
}

function pba_photo_public_render_collection_filters($collections, $selected_collection_slug) {
    $all_url = home_url('/photos/');
    $all_class = $selected_collection_slug === ''
        ? 'pba-photo-filter-pill is-active'
        : 'pba-photo-filter-pill';

    ob_start();
    ?>
    <div class="pba-photo-filter-wrap" aria-label="Photo collections">
        <a class="<?php echo esc_attr($all_class); ?>" href="<?php echo esc_url($all_url); ?>">
            All Photos
        </a>

        <?php foreach ($collections as $collection) : ?>
            <?php
            if (empty($collection['slug']) || empty($collection['name'])) {
                continue;
            }

            $slug = sanitize_title($collection['slug']);
            $url = add_query_arg(array('collection' => $slug), home_url('/photos/'));
            $class = $selected_collection_slug === $slug
                ? 'pba-photo-filter-pill is-active'
                : 'pba-photo-filter-pill';
            ?>
            <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html($collection['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_render_grid($photos) {
    if (empty($photos)) {
        return '<div class="pba-photo-empty-state">No approved photos are available yet.</div>';
    }

    ob_start();
    ?>
    <div class="pba-public-photo-grid">
        <?php foreach ($photos as $photo) : ?>
            <?php echo pba_photo_public_render_card($photo); ?>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_render_card($photo) {
    $photo_id = !empty($photo['photo_id']) ? (int) $photo['photo_id'] : 0;
    $title = !empty($photo['title']) ? $photo['title'] : 'Priscilla Beach photo';
    $caption = !empty($photo['caption']) ? $photo['caption'] : '';
    $photographer = !empty($photo['photographer_name']) ? $photo['photographer_name'] : '';
    $uploaded_by = !empty($photo['uploader_name']) ? $photo['uploader_name'] : '';
    $approved_at = !empty($photo['approved_at']) ? $photo['approved_at'] : '';
    $image_url = !empty($photo['_signed_url']) ? $photo['_signed_url'] : '';

    $meta_parts = array();

    if ($photographer !== '') {
        $meta_parts[] = 'Photo: ' . $photographer;
    } elseif ($uploaded_by !== '') {
        $meta_parts[] = 'Submitted by ' . $uploaded_by;
    }

    if ($approved_at !== '') {
        $formatted_date = pba_photo_public_format_date($approved_at);

        if ($formatted_date !== '') {
            $meta_parts[] = $formatted_date;
        }
    }

    $meta = implode(' · ', $meta_parts);

    ob_start();
    ?>
    <article class="pba-public-photo-card">
        <button
            class="pba-public-photo-button"
            type="button"
            data-pba-lightbox-open
            data-photo-id="<?php echo esc_attr($photo_id); ?>"
            data-photo-src="<?php echo esc_url($image_url); ?>"
            data-photo-title="<?php echo esc_attr($title); ?>"
            data-photo-caption="<?php echo esc_attr($caption); ?>"
            data-photo-meta="<?php echo esc_attr($meta); ?>"
            aria-label="<?php echo esc_attr('Open photo: ' . $title); ?>"
        >
            <span class="pba-public-photo-image-wrap">
                <img
                    src="<?php echo esc_url($image_url); ?>"
                    alt="<?php echo esc_attr($title); ?>"
                    loading="lazy"
                >
            </span>

            <span class="pba-public-photo-overlay">
                <span class="pba-public-photo-title"><?php echo esc_html($title); ?></span>

                <?php if ($meta !== '') : ?>
                    <span class="pba-public-photo-meta"><?php echo esc_html($meta); ?></span>
                <?php endif; ?>
            </span>
        </button>

        <?php if ($caption !== '') : ?>
            <div class="pba-public-photo-caption">
                <?php echo esc_html($caption); ?>
            </div>
        <?php endif; ?>
    </article>
    <?php

    return ob_get_clean();
}

function pba_photo_public_render_lightbox() {
    ob_start();
    ?>
    <div class="pba-photo-lightbox" data-pba-lightbox hidden>
        <div class="pba-photo-lightbox-backdrop" data-pba-lightbox-close></div>

        <div class="pba-photo-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="pba-photo-lightbox-title">
            <button class="pba-photo-lightbox-close" type="button" data-pba-lightbox-close aria-label="Close photo">
                ×
            </button>

            <div class="pba-photo-lightbox-media">
                <img src="" alt="" data-pba-lightbox-image>
            </div>

            <div class="pba-photo-lightbox-content">
                <h2 id="pba-photo-lightbox-title" data-pba-lightbox-title></h2>
                <p class="pba-photo-lightbox-meta" data-pba-lightbox-meta></p>
                <p class="pba-photo-lightbox-caption" data-pba-lightbox-caption></p>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_format_date($date_value) {
    $timestamp = strtotime($date_value);

    if (!$timestamp) {
        return '';
    }

    return date_i18n('M j, Y', $timestamp);
}

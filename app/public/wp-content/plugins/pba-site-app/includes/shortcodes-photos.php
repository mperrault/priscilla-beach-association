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

    $current_page = pba_photo_public_get_current_gallery_page();
    $page_size = pba_photo_public_get_gallery_page_size();

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

    $total_count = pba_photo_public_get_approved_photo_count($selected_collection);
    $total_pages = max(1, (int) ceil($total_count / $page_size));

    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $page_size;

    $photos = pba_photo_public_get_approved_photos($selected_collection, $page_size, $offset);

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

        <?php echo pba_photo_public_render_pagination($selected_collection_slug, $current_page, $page_size, $total_count); ?>

        <?php echo pba_photo_public_render_grid($photos); ?>

        <?php echo pba_photo_public_render_pagination($selected_collection_slug, $current_page, $page_size, $total_count); ?>

        <?php echo pba_photo_public_render_lightbox(); ?>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_get_gallery_page_size() {
    return 24;
}

function pba_photo_public_get_current_gallery_page() {
    $page = isset($_GET['gallery_page'])
        ? absint($_GET['gallery_page'])
        : 1;

    return max(1, $page);
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

function pba_photo_public_get_approved_photo_count($selected_collection = null) {
    if (is_array($selected_collection) && !empty($selected_collection['collection_id'])) {
        return pba_photo_public_get_approved_photo_count_for_collection((int) $selected_collection['collection_id']);
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'             => 'photo_id',
        'status'             => 'eq.approved',
        'visibility'         => 'eq.public',
        'storage_deleted_at' => 'is.null',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return 0;
    }

    return count($rows);
}

function pba_photo_public_get_approved_photo_count_for_collection($collection_id) {
    $collection_id = (int) $collection_id;

    if ($collection_id <= 0) {
        return 0;
    }

    $joins = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'        => 'photo_id',
        'collection_id' => 'eq.' . $collection_id,
    ));

    if (is_wp_error($joins) || !is_array($joins) || empty($joins)) {
        return 0;
    }

    $photo_ids = array();

    foreach ($joins as $join) {
        if (is_array($join) && !empty($join['photo_id'])) {
            $photo_ids[] = (int) $join['photo_id'];
        }
    }

    $photo_ids = array_values(array_unique($photo_ids));

    if (empty($photo_ids)) {
        return 0;
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'             => 'photo_id',
        'photo_id'           => 'in.(' . implode(',', $photo_ids) . ')',
        'status'             => 'eq.approved',
        'visibility'         => 'eq.public',
        'storage_deleted_at' => 'is.null',
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return 0;
    }

    return count($rows);
}

function pba_photo_public_get_approved_photos($selected_collection = null, $limit = 24, $offset = 0) {
    if (is_array($selected_collection) && !empty($selected_collection['collection_id'])) {
        return pba_photo_public_get_approved_photos_for_collection(
            (int) $selected_collection['collection_id'],
            $limit,
            $offset
        );
    }

    $rows = pba_photo_supabase_select('Photo', array(
        'select'             => '*',
        'status'             => 'eq.approved',
        'visibility'         => 'eq.public',
        'storage_deleted_at' => 'is.null',
        'order'              => 'is_featured.desc,sort_order.asc,approved_at.desc,created_at.desc',
        'limit'              => max(1, (int) $limit),
        'offset'             => max(0, (int) $offset),
    ));

    if (is_wp_error($rows) || !is_array($rows)) {
        return array();
    }

    return pba_photo_public_prepare_photos_for_display($rows);
}

function pba_photo_public_get_approved_photos_for_collection($collection_id, $limit = 24, $offset = 0) {
    $collection_id = (int) $collection_id;
    $limit = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    if ($collection_id <= 0) {
        return array();
    }

    $joins = pba_photo_supabase_select('PhotoCollectionPhoto', array(
        'select'        => 'photo_id,sort_order,created_at',
        'collection_id' => 'eq.' . $collection_id,
        'order'         => 'sort_order.asc,created_at.desc',
        'limit'         => $limit,
        'offset'        => $offset,
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
        'limit'              => $limit,
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

        $bucket = !empty($row['storage_bucket']) ? $row['storage_bucket'] : PBA_PHOTO_STORAGE_BUCKET;

        if (function_exists('pba_photo_storage_get_public_url')) {
            $display_url = pba_photo_storage_get_public_url($row['storage_path'], $bucket);

            $thumbnail_url = '';
            if (!empty($row['thumbnail_storage_path'])) {
                $thumbnail_url = pba_photo_storage_get_public_url($row['thumbnail_storage_path'], $bucket);
            }

            if ($thumbnail_url === '') {
                $thumbnail_url = $display_url;
            }

            $row['_display_url'] = $display_url;
            $row['_thumbnail_url'] = $thumbnail_url;
            $row['_signed_url'] = $display_url;
        } else {
            $signed_url = pba_photo_storage_create_signed_url(
                $row['storage_path'],
                3600,
                $bucket
            );

            if (is_wp_error($signed_url)) {
                continue;
            }

            $row['_display_url'] = $signed_url;
            $row['_thumbnail_url'] = $signed_url;
            $row['_signed_url'] = $signed_url;
        }

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

    $display_url = !empty($photo['_display_url']) ? $photo['_display_url'] : (!empty($photo['_signed_url']) ? $photo['_signed_url'] : '');
    $thumbnail_url = !empty($photo['_thumbnail_url']) ? $photo['_thumbnail_url'] : $display_url;

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
            data-photo-src="<?php echo esc_url($display_url); ?>"
            data-photo-title="<?php echo esc_attr($title); ?>"
            data-photo-caption="<?php echo esc_attr($caption); ?>"
            data-photo-meta="<?php echo esc_attr($meta); ?>"
            aria-label="<?php echo esc_attr('Open photo: ' . $title); ?>"
        >
            <span class="pba-public-photo-image-wrap">
                <img
                    src="<?php echo esc_url($thumbnail_url); ?>"
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

function pba_photo_public_render_pagination($selected_collection_slug, $current_page, $page_size, $total_count) {
    $current_page = max(1, (int) $current_page);
    $page_size = max(1, (int) $page_size);
    $total_count = max(0, (int) $total_count);

    if ($total_count <= $page_size) {
        return '';
    }

    $total_pages = max(1, (int) ceil($total_count / $page_size));
    $current_page = min($current_page, $total_pages);

    $start = (($current_page - 1) * $page_size) + 1;
    $end = min($total_count, $current_page * $page_size);

    $previous_page = max(1, $current_page - 1);
    $next_page = min($total_pages, $current_page + 1);

    ob_start();
    ?>
    <div class="pba-public-photo-pagination">
        <div class="pba-public-photo-pagination-summary">
            Showing <?php echo esc_html(number_format($start)); ?>–<?php echo esc_html(number_format($end)); ?>
            of <?php echo esc_html(number_format($total_count)); ?> photos
        </div>

        <div class="pba-public-photo-pagination-actions">
            <?php if ($current_page > 1) : ?>
                <a class="pba-button pba-button-secondary" href="<?php echo esc_url(pba_photo_public_build_pagination_url($selected_collection_slug, $previous_page)); ?>">
                    Previous
                </a>
            <?php else : ?>
                <span class="pba-button pba-button-secondary is-disabled" aria-disabled="true">
                    Previous
                </span>
            <?php endif; ?>

            <span class="pba-public-photo-page-indicator">
                Page <?php echo esc_html(number_format($current_page)); ?>
                of <?php echo esc_html(number_format($total_pages)); ?>
            </span>

            <?php if ($current_page < $total_pages) : ?>
                <a class="pba-button pba-button-secondary" href="<?php echo esc_url(pba_photo_public_build_pagination_url($selected_collection_slug, $next_page)); ?>">
                    Next
                </a>
            <?php else : ?>
                <span class="pba-button pba-button-secondary is-disabled" aria-disabled="true">
                    Next
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function pba_photo_public_build_pagination_url($selected_collection_slug, $page) {
    $args = array();

    $selected_collection_slug = sanitize_title((string) $selected_collection_slug);

    if ($selected_collection_slug !== '') {
        $args['collection'] = $selected_collection_slug;
    }

    if ((int) $page > 1) {
        $args['gallery_page'] = max(1, (int) $page);
    }

    return add_query_arg($args, home_url('/photos/'));
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
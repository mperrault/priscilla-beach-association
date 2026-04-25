<?php

if (!defined('ABSPATH')) {
    exit;
}

function pba_get_document_storage_stats() {
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']) . 'pba-documents';

    if (!is_dir($base_dir)) {
        wp_mkdir_p($base_dir);
    }

    $total = @disk_total_space($base_dir);
    $free  = @disk_free_space($base_dir);

    if (!$total || !$free || $total <= 0) {
        return false;
    }

    $used = $total - $free;
    $used_percent = min(100, max(0, round(($used / $total) * 100, 1)));

    return array(
        'path'         => $base_dir,
        'total'        => $total,
        'free'         => $free,
        'used'         => $used,
        'used_percent' => $used_percent,
    );
}

function pba_format_storage_size($bytes) {
    $bytes = (float) $bytes;
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function pba_render_document_storage_gauge() {
    $stats = pba_get_document_storage_stats();

    if (!$stats) {
        return '';
    }

    $percent = (float) $stats['used_percent'];

    ob_start();
    ?>
    <div class="pba-storage-card">
        <div class="pba-storage-header">
            <strong>Document Storage</strong>
            <span><?php echo esc_html($percent); ?>% used</span>
        </div>

        <div class="pba-storage-bar" aria-label="Document storage usage">
            <div class="pba-storage-fill" style="width: <?php echo esc_attr($percent); ?>%;"></div>
        </div>

        <div class="pba-storage-meta">
            <?php echo esc_html(pba_format_storage_size($stats['free'])); ?> available of
            <?php echo esc_html(pba_format_storage_size($stats['total'])); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

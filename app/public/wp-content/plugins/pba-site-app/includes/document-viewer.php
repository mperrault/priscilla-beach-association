<?php
if (!defined('ABSPATH')) {
    exit;
}

function pba_register_document_viewer_query_var($vars) {
    $vars[] = 'pba_document_view';
    return $vars;
}
add_filter('query_vars', 'pba_register_document_viewer_query_var');

function pba_handle_document_viewer() {
    $document_item_id = absint(get_query_var('pba_document_view'));

    if (!$document_item_id) {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    /*
     * TODO:
     * Replace this section with your existing document lookup logic.
     * You need:
     * - file name
     * - file URL
     * - permission check result
     */

    $document = pba_get_document_item_for_viewer($document_item_id);

    if (!$document) {
        wp_die('Document not found.', 'Document not found', ['response' => 404]);
    }

    if (!pba_current_user_can_view_document_item($document_item_id)) {
        wp_die('You do not have permission to view this document.', 'Access denied', ['response' => 403]);
    }

    $file_name = !empty($document['file_name']) ? $document['file_name'] : 'Document';
    $file_url  = !empty($document['file_url']) ? $document['file_url'] : '';

    if (!$file_url) {
        wp_die('Document file is missing.', 'Document unavailable', ['response' => 404]);
    }

    $site_icon = get_site_icon_url(32);
    if (!$site_icon) {
        $site_icon = get_template_directory_uri() . '/favicon.ico';
    }

    nocache_headers();
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($file_name); ?></title>
        <link rel="icon" href="<?php echo esc_url($site_icon); ?>">
        <style>
            html,
            body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                background: #f5f7f8;
                font-family: Arial, sans-serif;
            }

            .pba-document-viewer-header {
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 0 18px;
                background: #ffffff;
                border-bottom: 1px solid #d9e0e4;
                box-sizing: border-box;
            }

            .pba-document-viewer-title {
                font-size: 15px;
                font-weight: 600;
                color: #1f2d36;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .pba-document-viewer-download {
                font-size: 14px;
                color: #1d5f7a;
                text-decoration: none;
                font-weight: 600;
                white-space: nowrap;
            }

            .pba-document-viewer-frame {
                width: 100%;
                height: calc(100vh - 48px);
                border: 0;
                display: block;
                background: #ffffff;
            }
        </style>
    </head>
    <body>
        <div class="pba-document-viewer-header">
            <div class="pba-document-viewer-title">
                <?php echo esc_html($file_name); ?>
            </div>
            <a class="pba-document-viewer-download" href="<?php echo esc_url($file_url); ?>" download>
                Download
            </a>
        </div>

        <iframe
            class="pba-document-viewer-frame"
            src="<?php echo esc_url($file_url); ?>"
            title="<?php echo esc_attr($file_name); ?>">
        </iframe>
    </body>
    </html>
    <?php
    exit;
}
add_action('template_redirect', 'pba_handle_document_viewer');

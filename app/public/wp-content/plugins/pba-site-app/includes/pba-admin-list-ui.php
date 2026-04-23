<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pba_admin_list_render_ajax_script')) {
    function pba_admin_list_render_ajax_script($args) {
        $root_id = isset($args['root_id']) ? (string) $args['root_id'] : '';
        $form_id = isset($args['form_id']) ? (string) $args['form_id'] : '';
        $shell_selector = isset($args['shell_selector']) ? (string) $args['shell_selector'] : '';
        $loading_selector = isset($args['loading_selector']) ? (string) $args['loading_selector'] : '';
        $ajax_link_attr = isset($args['ajax_link_attr']) ? (string) $args['ajax_link_attr'] : '';
        $partial_param = isset($args['partial_param']) ? (string) $args['partial_param'] : '';

        if (
            $root_id === '' ||
            $form_id === '' ||
            $shell_selector === '' ||
            $loading_selector === '' ||
            $ajax_link_attr === '' ||
            $partial_param === ''
        ) {
            return '';
        }

        ob_start();
        ?>
        <script>
        (function () {
            var root = document.getElementById(<?php echo wp_json_encode($root_id); ?>);
            var activeRequest = 0;

            if (!root || !window.fetch || !window.URL) {
                return;
            }

            function getForm() {
                return root.querySelector('#' + <?php echo wp_json_encode($form_id); ?>);
            }

            function getShell() {
                return root.querySelector(<?php echo wp_json_encode($shell_selector); ?>);
            }

            function getLoadingWrap() {
                var shell = getShell();
                if (!shell) {
                    return null;
                }

                return shell.querySelector(<?php echo wp_json_encode($loading_selector); ?>);
            }

            function clearBusyState(scope) {
                var target = (scope && scope.querySelector) ? scope : root;
                var shell = target.querySelector(<?php echo wp_json_encode($shell_selector); ?>);
                var wrap = shell ? shell.querySelector(<?php echo wp_json_encode($loading_selector); ?>) : null;

                if (wrap) {
                    wrap.classList.remove('is-loading');
                }

                if (shell) {
                    shell.classList.remove('is-busy');
                    shell.removeAttribute('aria-busy');
                }

                document.documentElement.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting');
                document.body.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting', 'pba-table-resizing');
            }

            function setLoading(isLoading) {
                var wrap = getLoadingWrap();
                var shell = getShell();

                if (wrap) {
                    wrap.classList.toggle('is-loading', !!isLoading);
                }

                if (shell) {
                    shell.classList.toggle('is-busy', !!isLoading);

                    if (isLoading) {
                        shell.setAttribute('aria-busy', 'true');
                    } else {
                        shell.removeAttribute('aria-busy');
                    }
                }

                if (isLoading) {
                    document.documentElement.classList.add('pba-loading');
                    document.body.classList.add('pba-loading');
                } else {
                    document.documentElement.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting');
                    document.body.classList.remove('pba-loading', 'pba-submitting', 'pba-household-submitting', 'pba-table-resizing');
                }
            }

            function buildFormUrl(form) {
                var actionUrl = form.action || window.location.pathname;
                var parsed = new URL(actionUrl, window.location.origin);
                var params = new URLSearchParams(new FormData(form));
                parsed.search = params.toString();
                return parsed.toString();
            }

            function bindInteractiveElements() {
                var form = getForm();
                var pageLinks;

                if (form && form.dataset.bound !== '1') {
                    form.dataset.bound = '1';

                    form.addEventListener('submit', function (event) {
                        event.preventDefault();
                        var url = buildFormUrl(form);
                        window.history.pushState({}, '', url);
                        fetchIntoRoot(url);
                    });
                }

                pageLinks = root.querySelectorAll('[' + <?php echo wp_json_encode($ajax_link_attr); ?> + '="1"]');

                pageLinks.forEach(function (link) {
                    if (link.dataset.bound === '1') {
                        return;
                    }

                    link.dataset.bound = '1';

                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        window.history.pushState({}, '', link.href);
                        fetchIntoRoot(link.href);
                    });
                });
            }

            function reinitializeDynamicUi() {
                bindInteractiveElements();

                if (window.pbaInitResizableTables) {
                    window.pbaInitResizableTables(root);
                }

                clearBusyState(root);
            }

            function fetchIntoRoot(url) {
                var requestId = ++activeRequest;
                var parsed = new URL(url, window.location.origin);

                setLoading(true);
                parsed.searchParams.set(<?php echo wp_json_encode($partial_param); ?>, '1');

                window.fetch(parsed.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.text();
                })
                .then(function (html) {
                    if (requestId !== activeRequest) {
                        return;
                    }

                    root.innerHTML = html;
                    reinitializeDynamicUi();
                })
                .catch(function () {
                    clearBusyState(root);
                    window.location.href = url;
                })
                .finally(function () {
                    if (requestId === activeRequest) {
                        clearBusyState(root);
                    }
                });
            }

            window.addEventListener('popstate', function () {
                fetchIntoRoot(window.location.href);
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    reinitializeDynamicUi();
                });
            } else {
                reinitializeDynamicUi();
            }

            window.addEventListener('pageshow', function () {
                clearBusyState(root);
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_admin_list_render_resizable_table_script')) {
    function pba_admin_list_render_resizable_table_script() {
        static $printed = false;

        if ($printed) {
            return '';
        }

        $printed = true;

        ob_start();
        ?>
        <script>
        (function () {
            if (window.pbaInitResizableTables) {
                return;
            }

            function initTable(table) {
                var headRow;
                var ths;
                var storageKey;
                var minWidth;
                var colgroup;
                var cols = [];

                if (!table || table.dataset.pbaResizableReady === '1') {
                    return;
                }

                if (window.matchMedia && window.matchMedia('(max-width: 680px)').matches) {
                    return;
                }

                headRow = table.querySelector('thead tr');
                if (!headRow) {
                    return;
                }

                ths = Array.prototype.slice.call(headRow.children);
                if (!ths.length) {
                    return;
                }

                storageKey = table.getAttribute('data-resize-key') || ('pbaTableWidths:' + (table.id || 'table'));
                minWidth = parseInt(table.getAttribute('data-min-col-width'), 10);

                if (!minWidth || minWidth < 60) {
                    minWidth = 90;
                }

                colgroup = table.querySelector('colgroup[data-pba-resizable-cols="1"]');

                if (!colgroup) {
                    colgroup = document.createElement('colgroup');
                    colgroup.setAttribute('data-pba-resizable-cols', '1');

                    ths.forEach(function () {
                        var col = document.createElement('col');
                        colgroup.appendChild(col);
                        cols.push(col);
                    });

                    table.insertBefore(colgroup, table.firstChild);
                } else {
                    cols = Array.prototype.slice.call(colgroup.children);
                }

                if (cols.length !== ths.length) {
                    while (colgroup.firstChild) {
                        colgroup.removeChild(colgroup.firstChild);
                    }

                    cols = [];

                    ths.forEach(function () {
                        var col = document.createElement('col');
                        colgroup.appendChild(col);
                        cols.push(col);
                    });
                }

                function currentColWidth(index) {
                    var width = parseInt(cols[index].style.width, 10);
                    if (width && width >= minWidth) {
                        return width;
                    }

                    width = Math.round(cols[index].getBoundingClientRect().width);
                    if (width && width >= minWidth) {
                        return width;
                    }

                    width = Math.round(ths[index].getBoundingClientRect().width);
                    return Math.max(minWidth, width || minWidth);
                }

                function setDefaultWidthsFromExistingCols() {
                    ths.forEach(function (th, index) {
                        cols[index].style.width = currentColWidth(index) + 'px';
                    });
                }

                function loadWidths() {
                    var raw;
                    var widths;

                    try {
                        raw = window.localStorage.getItem(storageKey);

                        if (!raw) {
                            setDefaultWidthsFromExistingCols();
                            return;
                        }

                        widths = JSON.parse(raw);

                        if (!Array.isArray(widths) || widths.length !== cols.length) {
                            setDefaultWidthsFromExistingCols();
                            return;
                        }

                        widths.forEach(function (width, index) {
                            if (typeof width === 'number' && width >= minWidth) {
                                cols[index].style.width = width + 'px';
                            } else {
                                cols[index].style.width = currentColWidth(index) + 'px';
                            }
                        });
                    } catch (e) {
                        setDefaultWidthsFromExistingCols();
                    }
                }

                function saveWidths() {
                    try {
                        var widths = cols.map(function (col, index) {
                            var parsed = parseInt(col.style.width, 10);

                            if (!parsed || parsed < minWidth) {
                                parsed = currentColWidth(index);
                            }

                            return parsed;
                        });

                        window.localStorage.setItem(storageKey, JSON.stringify(widths));
                    } catch (e) {
                    }
                }

                function lockTableWidth() {
                    var width = Math.round(table.getBoundingClientRect().width);
                    if (width > 0) {
                        table.style.width = width + 'px';
                        table.style.minWidth = width + 'px';
                    }
                }

                function unlockTableWidth() {
                    table.style.width = '';
                    table.style.minWidth = '';
                }

                loadWidths();

                ths.forEach(function (th, index) {
                    var isResizable = th.getAttribute('data-resizable') !== 'false';
                    var handle;

                    th.classList.add('pba-resizable-th');

                    if (!isResizable) {
                        th.classList.add('pba-resizable-th-fixed');
                        return;
                    }

                    handle = th.querySelector('.pba-col-resizer');

                    if (!handle) {
                        handle = document.createElement('span');
                        handle.className = 'pba-col-resizer';
                        handle.setAttribute('aria-hidden', 'true');
                        th.appendChild(handle);
                    }

                    handle.addEventListener('mousedown', function (event) {
                        var startX = event.clientX;
                        var startWidth = currentColWidth(index);

                        event.preventDefault();
                        event.stopPropagation();

                        lockTableWidth();
                        document.body.classList.add('pba-table-resizing');
                        handle.classList.add('is-active');

                        function onMouseMove(moveEvent) {
                            var delta = moveEvent.clientX - startX;
                            cols[index].style.width = Math.max(minWidth, startWidth + delta) + 'px';
                        }

                        function onMouseUp() {
                            document.body.classList.remove('pba-table-resizing');
                            handle.classList.remove('is-active');
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                            saveWidths();
                            unlockTableWidth();
                        }

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    });
                });

                table.dataset.pbaResizableReady = '1';
            }

            window.pbaInitResizableTables = function (scope) {
                var root = (scope && scope.querySelectorAll) ? scope : document;
                var tables = root.querySelectorAll('.pba-resizable-table');

                tables.forEach(function (table) {
                    initTable(table);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    window.pbaInitResizableTables(document);
                });
            } else {
                window.pbaInitResizableTables(document);
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_admin_list_render_sortable_th')) {
    function pba_admin_list_render_sortable_th($args) {
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $column = isset($args['column']) ? (string) $args['column'] : '';
        $current_sort = isset($args['current_sort']) ? (string) $args['current_sort'] : '';
        $current_direction = isset($args['current_direction']) ? (string) $args['current_direction'] : 'asc';
        $url = isset($args['url']) ? (string) $args['url'] : '';
        $link_attr = isset($args['link_attr']) ? (string) $args['link_attr'] : 'data-admin-list-ajax-link';
        $link_class = isset($args['link_class']) ? (string) $args['link_class'] : '';
        $indicator_class = isset($args['indicator_class']) ? (string) $args['indicator_class'] : '';

        $is_current = ($current_sort === $column);
        $indicator = '↕';

        if ($is_current) {
            $indicator = ($current_direction === 'asc') ? '↑' : '↓';
        }

        ob_start();
        ?>
        <th>
            <a class="<?php echo esc_attr($link_class); ?>" <?php echo esc_attr($link_attr); ?>="1" href="<?php echo esc_url($url); ?>">
                <?php echo esc_html($label); ?>
                <span class="<?php echo esc_attr($indicator_class); ?>"><?php echo esc_html($indicator); ?></span>
            </a>
        </th>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('pba_admin_list_render_pagination')) {
    function pba_admin_list_render_pagination($args) {
        $pagination = isset($args['pagination']) && is_array($args['pagination']) ? $args['pagination'] : array();
        $url_builder = isset($args['url_builder']) && is_callable($args['url_builder']) ? $args['url_builder'] : null;
        $page_param = isset($args['page_param']) ? (string) $args['page_param'] : 'page';
        $container_class = isset($args['container_class']) ? (string) $args['container_class'] : '';
        $muted_class = isset($args['muted_class']) ? (string) $args['muted_class'] : '';
        $links_class = isset($args['links_class']) ? (string) $args['links_class'] : '';

        if (!$url_builder || empty($pagination['total_pages']) || (int) $pagination['total_pages'] <= 1) {
            return '';
        }

        $current_page = isset($pagination['page']) ? max(1, (int) $pagination['page']) : 1;
        $total_pages = isset($pagination['total_pages']) ? max(1, (int) $pagination['total_pages']) : 1;
        $window = 2;
        $start_page = max(1, $current_page - $window);
        $end_page = min($total_pages, $current_page + $window);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($container_class); ?>">
            <span class="<?php echo esc_attr($muted_class); ?>">
                Page <?php echo esc_html(number_format_i18n($current_page)); ?> of <?php echo esc_html(number_format_i18n($total_pages)); ?>
            </span>

            <div class="<?php echo esc_attr($links_class); ?>">
                <?php if ($current_page > 1) : ?>
                    <a class="pba-admin-list-page-link" data-admin-list-ajax-link="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $current_page - 1))); ?>">Previous</a>
                <?php else : ?>
                    <span class="pba-admin-list-page-link current" style="opacity:.55;">Previous</span>
                <?php endif; ?>

                <?php if ($start_page > 1) : ?>
                    <a class="pba-admin-list-page-link" data-admin-list-ajax-link="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => 1))); ?>">1</a>
                    <?php if ($start_page > 2) : ?>
                        <span class="pba-admin-list-muted">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($page = $start_page; $page <= $end_page; $page++) : ?>
                    <?php if ($page === $current_page) : ?>
                        <span class="pba-admin-list-page-link current"><?php echo esc_html(number_format_i18n($page)); ?></span>
                    <?php else : ?>
                        <a class="pba-admin-list-page-link" data-admin-list-ajax-link="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $page))); ?>"><?php echo esc_html(number_format_i18n($page)); ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages) : ?>
                    <?php if ($end_page < ($total_pages - 1)) : ?>
                        <span class="pba-admin-list-muted">…</span>
                    <?php endif; ?>
                    <a class="pba-admin-list-page-link" data-admin-list-ajax-link="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $total_pages))); ?>"><?php echo esc_html(number_format_i18n($total_pages)); ?></a>
                <?php endif; ?>

                <?php if ($current_page < $total_pages) : ?>
                    <a class="pba-admin-list-page-link" data-admin-list-ajax-link="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $current_page + 1))); ?>">Next</a>
                <?php else : ?>
                    <span class="pba-admin-list-page-link current" style="opacity:.55;">Next</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
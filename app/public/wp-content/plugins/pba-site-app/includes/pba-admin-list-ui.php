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

        if ($root_id === '' || $form_id === '' || $shell_selector === '' || $loading_selector === '' || $ajax_link_attr === '' || $partial_param === '') {
            return '';
        }

        ob_start();
        ?>
        <script>
            (function () {
                var root = document.getElementById(<?php echo wp_json_encode($root_id); ?>);

                if (!root || !window.fetch || !window.URL) {
                    return;
                }

                function getForm() {
                    return root.querySelector('#' + <?php echo wp_json_encode($form_id); ?>);
                }

                function getShell() {
                    return root.querySelector(<?php echo wp_json_encode($shell_selector); ?>);
                }

                function bindInteractiveElements() {
                    var form = getForm();

                    if (form && form.dataset.bound !== '1') {
                        form.dataset.bound = '1';

                        form.addEventListener('submit', function (event) {
                            event.preventDefault();
                            var url = buildFormUrl(form);
                            window.history.pushState({}, '', url);
                            fetchIntoRoot(url);
                        });
                    }

                    var pageLinks = root.querySelectorAll('[' + <?php echo wp_json_encode($ajax_link_attr); ?> + '="1"]');

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

                function setLoading(isLoading) {
                    var shell = getShell();

                    if (!shell) {
                        return;
                    }

                    var wrap = shell.querySelector(<?php echo wp_json_encode($loading_selector); ?>);

                    if (!wrap) {
                        return;
                    }

                    if (isLoading) {
                        wrap.classList.add('is-loading');
                    } else {
                        wrap.classList.remove('is-loading');
                    }
                }

                function buildFormUrl(form) {
                    var actionUrl = form.action || window.location.pathname;
                    var parsed = new URL(actionUrl, window.location.origin);
                    var params = new URLSearchParams(new FormData(form));
                    parsed.search = params.toString();
                    return parsed.toString();
                }

                function fetchIntoRoot(url) {
                    setLoading(true);

                    var parsed = new URL(url, window.location.origin);
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
                        root.innerHTML = html;
                        bindInteractiveElements();
                    })
                    .catch(function () {
                        window.location.href = url;
                    })
                    .finally(function () {
                        setLoading(false);
                    });
                }

                window.addEventListener('popstate', function () {
                    fetchIntoRoot(window.location.href);
                });

                bindInteractiveElements();
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
        $link_class = isset($args['link_class']) ? (string) $args['link_class'] : '';
        $current_class = isset($args['current_class']) ? (string) $args['current_class'] : 'current';
        $ajax_link_attr = isset($args['ajax_link_attr']) ? (string) $args['ajax_link_attr'] : 'data-admin-list-ajax-link';
        $prev_label = isset($args['prev_label']) ? (string) $args['prev_label'] : 'Prev';
        $next_label = isset($args['next_label']) ? (string) $args['next_label'] : 'Next';

        $total_pages = isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1;
        $current_page = isset($pagination['current_page']) ? (int) $pagination['current_page'] : 1;

        if ($total_pages <= 1 || !$url_builder) {
            return '';
        }

        $pages_to_show = array();

        for ($page = 1; $page <= $total_pages; $page++) {
            if ($page === 1 || $page === $total_pages || abs($page - $current_page) <= 2) {
                $pages_to_show[] = $page;
            }
        }

        $pages_to_show = array_values(array_unique($pages_to_show));
        sort($pages_to_show);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($container_class); ?>">
            <div class="<?php echo esc_attr($muted_class); ?>">
                Page <?php echo esc_html(number_format_i18n($current_page)); ?> of <?php echo esc_html(number_format_i18n($total_pages)); ?>
            </div>
            <div class="<?php echo esc_attr($links_class); ?>">
                <?php if ($current_page > 1) : ?>
                    <a class="<?php echo esc_attr($link_class); ?>" <?php echo esc_attr($ajax_link_attr); ?>="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $current_page - 1))); ?>"><?php echo esc_html($prev_label); ?></a>
                <?php endif; ?>

                <?php
                $last_rendered = 0;
                foreach ($pages_to_show as $page_number) :
                    if ($last_rendered > 0 && $page_number > ($last_rendered + 1)) {
                        echo '<span class="' . esc_attr($muted_class) . '">…</span>';
                    }

                    $classes = $link_class;
                    if ($page_number === $current_page) {
                        $classes .= ' ' . $current_class;
                    }
                    ?>
                    <a class="<?php echo esc_attr(trim($classes)); ?>" <?php echo esc_attr($ajax_link_attr); ?>="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $page_number))); ?>"><?php echo esc_html(number_format_i18n($page_number)); ?></a>
                    <?php
                    $last_rendered = $page_number;
                endforeach;
                ?>

                <?php if ($current_page < $total_pages) : ?>
                    <a class="<?php echo esc_attr($link_class); ?>" <?php echo esc_attr($ajax_link_attr); ?>="1" href="<?php echo esc_url(call_user_func($url_builder, array($page_param => $current_page + 1))); ?>"><?php echo esc_html($next_label); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
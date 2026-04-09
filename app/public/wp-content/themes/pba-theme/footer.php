<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-top">
            <div class="site-footer-branding">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-footer-brand-link">
                    <?php bloginfo('name'); ?>
                </a>
            </div>
        </div>

        <div class="site-footer-navigation-wrap">
            <?php if (is_user_logged_in()) : ?>
                <nav class="site-footer-navigation logged-in-footer-navigation" aria-label="Footer Member Navigation">
                    <?php echo pba_render_logged_in_menu('footer'); ?>
                </nav>
            <?php else : ?>
                <nav class="site-footer-navigation public-footer-navigation" aria-label="Footer Primary Navigation">
                    <?php
                    if (has_nav_menu('primary')) {
                        wp_nav_menu(array(
                            'theme_location' => 'primary',
                            'container'      => false,
                            'menu_class'     => 'pba-custom-menu pba-footer-menu',
                            'fallback_cb'    => false,
                        ));
                    } else {
                        echo '<ul class="pba-custom-menu pba-footer-menu">';
                        echo '<li><a href="' . esc_url(home_url('/')) . '">Home</a></li>';
                        echo '<li><a href="' . esc_url(home_url('/about/')) . '">About</a></li>';
                        echo '<li><a href="' . esc_url(home_url('/calendar/')) . '">Calendar</a></li>';
                        echo '<li><a href="' . esc_url(home_url('/login/')) . '">Login</a></li>';
                        echo '</ul>';
                    }
                    ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</footer>

<div class="pba-top-loader" aria-hidden="true"></div>

<script>
(function () {
    if (window.pbaPageLoadingInit) {
        return;
    }

    window.pbaPageLoadingInit = true;

    function shouldShowLoadingForClick(link, event) {
        if (!link) {
            return false;
        }

        if (event.defaultPrevented) {
            return false;
        }

        if (event.button !== 0) {
            return false;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        var href = link.getAttribute('href') || '';

        if (href === '' || href === '#') {
            return false;
        }

        if (href.indexOf('javascript:') === 0) {
            return false;
        }

        return true;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');

        if (!shouldShowLoadingForClick(link, event)) {
            return;
        }

        if (document.body) {
            document.body.classList.add('pba-page-loading');
        }
    }, true);

    window.addEventListener('pageshow', function () {
        if (document.body) {
            document.body.classList.remove('pba-page-loading');
        }
    });

    window.addEventListener('beforeunload', function () {
        if (document.body) {
            document.body.classList.add('pba-page-loading');
        }
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
<footer class="site-footer">
    <div class="site-footer-inner">

        <div class="site-footer-branding">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <?php bloginfo('name'); ?>
            </a>
        </div>

        <div class="site-footer-navigation-wrap">
            <?php if (is_user_logged_in()) : ?>
                <nav class="site-footer-navigation logged-in-footer-navigation" aria-label="Footer Member Navigation">
                    <?php echo pba_render_logged_in_menu(); ?>
                </nav>

                <div class="pba-footer-user-tools">
                    <span class="pba-footer-welcome-text">
                        Welcome, <?php echo esc_html(pba_get_welcome_name()); ?>
                    </span>

                    <a class="pba-footer-logout-link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                        Logout
                    </a>
                </div>
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

<?php wp_footer(); ?>
</body>
</html>
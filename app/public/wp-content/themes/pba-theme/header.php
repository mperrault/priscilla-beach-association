<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="site-header-inner">

        <div class="site-branding">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <?php bloginfo('name'); ?>
            </a>
        </div>

        <div class="site-navigation-wrap">
            <?php if (is_user_logged_in()) : ?>
                <nav class="site-navigation logged-in-navigation" aria-label="Member Navigation">
                    <?php echo pba_render_logged_in_menu(); ?>
                </nav>

                <div class="pba-header-user-tools">

                    <span class="pba-welcome-text">
                        Welcome, <?php echo esc_html(pba_get_welcome_name()); ?>
                    </span>

                    <a class="pba-logout-link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                        Logout
                    </a>
                </div>
                <div class="pba-header-user-tools">
                    <a
                        class="pba-facebook-link"
                        href="https://www.facebook.com/PriscillaBeachAssociation/"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Visit our Facebook page"
                    >
                    </a>
                </div>
            <?php else : ?>
                <nav class="site-navigation public-navigation" aria-label="Primary Navigation">
                    <?php
                    if (has_nav_menu('primary')) {
                        wp_nav_menu(array(
                            'theme_location' => 'primary',
                            'container'      => false,
                            'menu_class'     => 'pba-custom-menu',
                            'fallback_cb'    => false,
                        ));
                    } else {
                        echo '<ul class="pba-custom-menu">';
                        echo '<li><a href="' . esc_url(home_url('/')) . '">Home</a></li>';
                        echo '<li><a href="' . esc_url(home_url('/about/')) . '">About</a></li>';
                        echo '<li><a href="' . esc_url(home_url('/calendar/')) . '">Calendar</a></li>';
                        echo '</ul>';
                    }
                    ?>
                </nav>

                <div class="pba-header-user-tools">
                    <a
                        class="pba-facebook-link"
                        href="https://www.facebook.com/PriscillaBeachAssociation/"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Visit our Facebook page"
                    >
                        f
                    </a>

                    <a class="header-login-btn pba-login-button" href="<?php echo esc_url(home_url('/login/')); ?>">
                        Login
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</header>
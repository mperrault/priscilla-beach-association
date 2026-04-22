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
    <div class="site-header-inner pba-header-shell">

        <div class="pba-header-topbar">
            <div class="site-branding">
                <a class="pba-site-branding-link" href="<?php echo esc_url(home_url('/')); ?>">
                    <img
                        class="pba-site-logo"
                        src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/favicon-pba.png'); ?>"
                        alt="PBA logo"
                    >
                    <span class="pba-site-title"><?php bloginfo('name'); ?></span>
                </a>
            </div>

            <div class="pba-header-user-tools pba-header-user-tools-top">
                <?php if (is_user_logged_in()) : ?>
                    <span class="pba-welcome-text">
                        Welcome, <?php echo esc_html(pba_get_welcome_name()); ?>
                    </span>
                <?php endif; ?>

                <a
                    class="pba-facebook-link"
                    href="https://www.facebook.com/PriscillaBeachAssociation/"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Visit our Facebook page"
                    title="Visit our Facebook page"
                >
                    <span class="pba-facebook-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false" aria-hidden="true">
                            <path d="M13.5 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.2-1.5 1.5-1.5h1.7V4.6c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.3H8v3.1h2.3v8h3.2z"></path>
                        </svg>
                    </span>
                    <span class="pba-facebook-label">Facebook</span>
                </a>

                <?php if (is_user_logged_in()) : ?>
                    <?php
                    $logout_url = add_query_arg(
                        array(
                            'action' => 'pba_member_logout',
                        ),
                        admin_url('admin-post.php')
                    );

                    $logout_url = wp_nonce_url($logout_url, 'pba_member_logout_action');
                    ?>
                    <a class="pba-logout-link" href="<?php echo esc_url($logout_url); ?>">
                        Logout
                    </a>
                <?php else : ?>
                    <a class="header-login-btn pba-login-button" href="<?php echo esc_url(home_url('/login/')); ?>">
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="pba-header-navrow">
            <nav class="site-navigation" aria-label="<?php echo is_user_logged_in() ? esc_attr__('Member Navigation', 'pba-theme') : esc_attr__('Primary Navigation', 'pba-theme'); ?>">
                <?php
                if (is_user_logged_in() && function_exists('pba_render_logged_in_menu')) {
                    echo pba_render_logged_in_menu();
                } else {
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
                }
                ?>
            </nav>
        </div>

    </div>
</header>
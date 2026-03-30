<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
  <div class="pba-container pba-header-row">
    <nav class="main-nav" aria-label="Primary Navigation">
      <?php

    wp_nav_menu(array(
        'theme_location' => 'primary',
        'menu' => 'Default Menu',
        'fallback_cb'    => false,
        'container'      => 'nav',
        'container_class'=> 'site-nav primary-nav',
    ));
      ?>
    </nav>

    <div class="header-socials" aria-label="Header actions">
      <a href="https://www.facebook.com/PriscillaBeachAssociation/" class="social-icon" aria-label="Facebook" title="Facebook">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/icons/facebook.svg'); ?>" alt="Facebook">
      </a>

      <!-- a href="<?php echo esc_url(home_url('/login/')); ?>" class="header-login-btn">Login</a -->
      </div>
  </div>
</header>
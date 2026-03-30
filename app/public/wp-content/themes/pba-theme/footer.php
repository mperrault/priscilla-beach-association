<footer class="site-footer">
  <div class="pba-container">
    <p><strong>Priscilla Beach Association</strong></p>
    <p>All Rights Reserved © PBA</p>
    <p>COPYRIGHT © 2014 - 2026 - PRISCILLA BEACH ASSOCIATION</p>
    <p>Priscilla Beach, Plymouth, MA</p>

    <?php
    wp_nav_menu(array(
        'theme_location' => 'footer',
        'container'      => 'nav',
        'container_class'=> 'footer-nav',
        'fallback_cb'    => false,
    ));
    ?>
  </div>
</footer>

<?php wp_footer(); ?>


</body>
</html>
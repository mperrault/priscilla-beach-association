<?php get_header(); ?>

<section class="hero-image-wrap">
  <div
    class="hero-image"
    style="background-image: url('<?php echo esc_url(home_url('/wp-content/uploads/2022/05/IMG_4352-scaled.jpeg')); ?>');"
  >
    <div class="hero-overlay">
      <h1 class="hero-title-black">Priscilla Beach Association</h1>
      <div>
        <div>
          <p class="hero-subtitle-white">The Priscilla Beach Association (PBA) is a collective of property owners in the Priscilla Beach neighborhood in Plymouth, MA</p>
        </div>
        <div>
          <p></p>
        </div>
        <div>
          <p></p>
        </div>
        <div>
          <img src="/wp-content/uploads/2026/03/PBA_GolfOuting.png" width="350" height="250"></img>
        </div>

      </div>
    </div>
  </div>
</section>

<main class="pba-container">
  <section class="home-columns">
    <div class="home-col">
      <h2>ABOUT US</h2>
      <p>PBA was formed on August 11, 1951, to promote and foster the social and civic welfare of the residents and owners of real estate in Priscilla Beach.</p>
      <p><a class="pba-button" href="<?php echo esc_url(home_url('/about/')); ?>">LEARN MORE HERE</a></p>
    </div>

    <div class="home-col">
      <h2>THE PRISCILLA BEACH ASSOCIATION</h2>
      <p>The PBA is dedicated to the protection, preservation, and improvement of Priscilla Beach as a private beach for the interest of the property owners.</p>
      <p>The homeowners are represented by a president, vice-president, secretary, treasurer and four board members elected from within the association membership.</p>
    </div>
  </section>

  <section class="home-docs">
    <h2>Important Pages &amp; Documents</h2>
    <h3>BY-LAWS</h3>
    <p><a class="pba-button" href="<?php echo esc_url(home_url('/by-laws/')); ?>">LEARN MORE</a></p>
  </section>
</main>

<?php get_footer(); ?>
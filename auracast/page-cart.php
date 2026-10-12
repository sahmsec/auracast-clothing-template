<?php
/** Cart page. */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="auracast-cart-page container">
<?php while ( have_posts() ) : the_post(); echo do_shortcode( '[woocommerce_cart]' ); endwhile; ?>
</main>
<?php get_footer(); ?>

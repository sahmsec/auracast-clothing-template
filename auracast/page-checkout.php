<?php defined( 'ABSPATH' ) || exit; get_header( 'checkout' ); ?>
<main id="main-content" class="checkout-page-main"><h1 class="screen-reader-text"><?php esc_html_e( 'Auracast Checkout', 'auracast' ); ?></h1><?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?></main>
<?php get_footer( 'checkout' ); ?>

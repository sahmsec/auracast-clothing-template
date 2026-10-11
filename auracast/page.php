<?php defined( 'ABSPATH' ) || exit; get_header(); ?>
<main id="main-content" class="page-content container"><?php while ( have_posts() ) : the_post(); ?><header class="page-heading"><h1><?php the_title(); ?></h1></header><div class="entry-content"><?php the_content(); ?></div><?php endwhile; ?></main>
<?php get_footer(); ?>

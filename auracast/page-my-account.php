<?php
defined( 'ABSPATH' ) || exit;
if ( is_user_logged_in() ) { get_header(); }
else { get_header( 'account' ); }
?>
<main id="main-content" class="<?php echo is_user_logged_in() ? 'page-content container' : 'account-auth-main'; ?>"><?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?></main>
<?php if ( is_user_logged_in() ) { get_footer(); } else { get_footer( 'account' ); } ?>

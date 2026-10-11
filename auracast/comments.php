<?php defined( 'ABSPATH' ) || exit; if ( post_password_required() ) { return; } ?>
<section class="comments-area prose"><h2><?php esc_html_e( 'Comments', 'auracast' ); ?></h2><?php if ( have_comments() ) : ?><ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true ) ); ?></ol><?php the_comments_pagination(); ?><?php endif; ?><?php comment_form(); ?></section>

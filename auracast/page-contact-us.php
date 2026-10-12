<?php defined( 'ABSPATH' ) || exit; get_header(); ?>
<main id="main-content" class="page-content container"><header class="page-heading"><h1>Contact information</h1></header><div class="entry-content"><p>Trading name: AURACAST</p>
<?php if ( auracast_setting( 'contact_email' ) ) : ?><p>Email: <a href="mailto:<?php echo esc_attr( sanitize_email( auracast_setting( 'contact_email' ) ) ); ?>"><?php echo esc_html( auracast_setting( 'contact_email' ) ); ?></a></p><?php endif; ?>
<?php if ( auracast_setting( 'instagram_url' ) ) : ?><p>Instagram: <a href="<?php echo esc_url( auracast_setting( 'instagram_url' ) ); ?>" target="_blank" rel="noopener">AURACAST</a></p><?php endif; ?>
<?php if ( auracast_setting( 'contact_phone' ) ) : ?><p>Phone number: <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', auracast_setting( 'contact_phone' ) ) ); ?>"><?php echo esc_html( auracast_setting( 'contact_phone' ) ); ?></a></p><?php endif; ?>
<?php if ( auracast_setting( 'contact_address' ) ) : ?><p><?php echo nl2br( esc_html( auracast_setting( 'contact_address' ) ) ); ?></p><?php endif; ?>
</div></main>
<?php get_footer(); ?>

<?php
/** Catalog ordering with a labelable control and preserved active facets. @version 9.7.0 */
defined( 'ABSPATH' ) || exit;
?>
<form class="woocommerce-ordering" method="get"><select name="orderby" id="collection-sort" class="orderby" aria-label="Sort products"><?php foreach ( $catalog_orderby_options as $id => $name ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select><input type="hidden" name="paged" value="1"><?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?></form>

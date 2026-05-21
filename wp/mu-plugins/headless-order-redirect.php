<?php
/**
 * Plugin Name: Headless Order Redirect
 * Description: Clears SPA cart session state when the native WC order-received page renders.
 * Version:     0.2.0
 * Author:      WCHS Contributors
 *
 * Post-checkout confirmation and purchase tracking live on the native
 * /checkout/order-received/ page (see headless-thankyou-tracking.php).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'woocommerce_thankyou',
	function () {
		?>
		<script>
		try {
			sessionStorage.removeItem('wchs_cart_token');
			sessionStorage.removeItem('wchs_store_nonce');
			localStorage.removeItem('wchs_shadow_cart_v1');
		} catch(e) {}
		</script>
		<?php
	},
	50
);

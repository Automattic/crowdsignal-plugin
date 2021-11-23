<?php
/**
 * File containing the view for step 2 of the setup wizard.
 *
 * @package Crowdsignal_Forms\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( $is_connected ) {
	$crowdsignal_forms_msg = 'connected';
} else {
	$crowdsignal_forms_msg = 'api-key-not-added';
}
?>
<script type='text/javascript'>
window.close();
if (window.opener && !window.opener.closed) {
	var querystring = window.opener.location.search;
	querystring += ( querystring ? '&' : '?' ) + 'message=<?php echo esc_js( $crowdsignal_forms_msg ); ?>';
	window.opener.location.search = querystring;
}
</script>
<noscript><h3><?php esc_html_e( "You're ready to start using Crowdsignal!", 'polldaddy' ); ?></h3></noscript>

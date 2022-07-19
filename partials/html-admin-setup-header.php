<?php
/**
 * File containing the view used in the header of the setup pages.
 *
 * @package Crowdsignal_Forms\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div>
	<div id="cs-plugin-container">
		<div class='cs-lower'>
			<h1 id='crowdsignal__logo'><?php esc_html_e( 'Crowdsignal Settings', 'crowdsignal-forms' ); ?></h1>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['msg'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				switch ( $_GET['msg'] ) {
					case 'disconnect-fail':
						echo '<div class="error fade crowdsignal-message"><p>' .
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is svg from internal lib
						esc_html__( 'Could not disconnect. Please try again.', 'crowdsignal-forms' ) .
						'</p></div>';
						break;
					case 'disconnected':
						echo '<div class="updated fade crowdsignal-message"><p>' .
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is svg from internal lib
						esc_html__( 'Successfully disconnected from Crowdsignal.', 'crowdsignal-forms' ) .
						'</p></div>';
						break;
					case 'connected':
						echo '<div class="updated crowdsignal-message"><p>' .
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is svg from internal lib
						esc_html__( 'Success! Your Crowdsignal account is successfully connected! You are ready!', 'crowdsignal-forms' ) .
						'</p></div>';
						break;
					case 'api-key-added':
						echo '<div class="updated crowdsignal-message"><p>' .
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is svg from internal lib
						esc_html__( 'You have been connected to Crowdsignal.', 'crowdsignal-forms' ) .
						'</p></div>';
						break;
					case 'api-key-not-added':
						echo '<div class="error fade crowdsignal-message"><p>' .
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is svg from internal lib
						esc_html__( 'Your API key has not been updated, please try again.', 'crowdsignal-forms' ) .
						'</p></div>';
						break;
				}
			}
			?>

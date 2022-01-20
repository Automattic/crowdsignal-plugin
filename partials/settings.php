<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package polldaddy
 */

?>
<?php // phpcs:ignoreFile -- too many legacy warnings, needs full linter ?>
			<div class='cs-settings-container'>
				<div class="cs-card cs-section-header is-compact">
					<div class="cs-section-header__label">
						<span class="cs-section-header__label-text"><?php esc_html_e( 'Account Settings', 'crowdsignal-forms' ); ?></span>
					</div>
				</div>

				<div class="cs-card cs-section-header is-compact">
					<div class="cs-form-settings-group">
						<h2><?php esc_html_e( 'API Key', 'crowdsignal-forms' ); ?></h2>
						<p>
			<?php
			printf(
				/* translators: Placeholder is the text "Crowdsignal". */
				esc_html__( 'Your website is connected to a %s account to collect responses and data from your visitors.', 'crowdsignal-forms' ),
				'<a href="https://crowdsignal.com/">Crowdsignal</a>'
			);
			?>
						<br />
			<?php
			printf(
				/* translators: Placeholder is the text "Crowdsignal acount page". */
				esc_html__( 'Visit your %s to find out more about your settings.', 'crowdsignal-forms' ),
				sprintf(
					'<a href="https://crowdsignal.com/account/">%s</a>',
					esc_html__( 'Crowdsignal account page', 'crowdsignal-forms' )
				)
			);
			?>
						</p>
			<?php if ( ! $api_key ) { ?>
						<p>
						<?php esc_html_e( 'If you have a Crowdsignal account, click the "Get API Key" button to connect. This will open a new window.', 'crowdsignal-forms' ); ?>
						<form id="cs-connect-form" class="crowdsignal-options" method="post" action="https://app.crowdsignal.com/get-api-key/" target="CSCONNECT">
						<input type="hidden" name="get_api_key" value="<?php echo esc_attr( get_option( 'crowdsignal_api_key_secret' ) ); ?>" />
						<input type="hidden" name="ref" value="<?php echo esc_attr( admin_url( 'options-general.php?page=crowdsignal-settings' ) ); ?>" />
						<input type="submit" value="<?php esc_html_e( 'Get API Key', 'crowdsignal-forms' ); ?>" class="cs-button is-primary" />
						</form>
						</p>
			<?php } ?>
						<form class="crowdsignal-options" method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=crowdsignal-settings' ) ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used for basic flow.
			if ( ! empty( $_GET['settings-updated'] ) ) {
				echo '<div class="updated fade crowdsignal-updated"><p>' . esc_html__( 'Settings successfully saved', 'crowdsignal-forms' ) . '</p></div>';
			}

			?>
							<div style='padding-bottom:16px;' id="settings-general" class="settings_panel">
								<table class="form-table settings parent-settings">
									<tr valign="top" class="">
										<th scope="row"><label for="setting-crowdsignal_api_key"><?php esc_html_e( 'Your Crowdsignal API Key', 'crowdsignal-forms' ); ?></a></th>
										<td><input
									<?php echo $api_key ? 'readonly' : ''; ?>
										id="setting-crowdsignal_api_key"
										class="regular-text"
										type="text"
										name="crowdsignal_api_key"
										value="<?php echo esc_attr( $api_key ); ?>"
									/>
										</td>
										<td>
										<?php
										if ( $api_key ) {
											wp_nonce_field( 'disconnect-api-key' );
											?>
											<input type="hidden" name="action" value="disconnect" />
											<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Disconnect', 'crowdsignal-forms' ); ?>" />
											<?php
										} else {
											wp_nonce_field( 'add-api-key' );
											?>
											<input type="hidden" name="action" value="update" />
											<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Connect', 'crowdsignal-forms' ); ?>" />
											<?php
										}
										?>
										</td>
									</tr>
								</table>
							</div>
						</form>
					</div>
				</div>
			</div>

<?php if ( ! $api_key ) { ?>
<script>
let CSCONNECT = null;
const showConnect = ( title ) => {
	// window size, match standard iPhone screen size
	const widthRatio = 1/2;
	const heightRatio = 3/4;

	// Fixes dual-screen position
	const dualScreenLeft = window.screenLeft !==  undefined
		? window.screenLeft // Most browsers
		: window.screenX; // Firefox
	const dualScreenTop = window.screenTop !==  undefined
		? window.screenTop // Most browsers
		: window.screenY; // Firefox

	const width = window.innerWidth
		? window.innerWidth
		: document.documentElement.clientWidth
			? document.documentElement.clientWidth
			: screen.width;
	const height = window.innerHeight
		? window.innerHeight
		: document.documentElement.clientHeight
			? document.documentElement.clientHeight
			: screen.height;

	const popupWidth = width * widthRatio;
	const popupHeight = height * heightRatio;

	const left = ( (width / 2 ) - ( popupWidth / 2 ) ) + dualScreenLeft;
	const top = ( (height / 2 ) - ( popupHeight / 2 ) ) + dualScreenTop;

	CSCONNECT = window.open( 'about:blank', title,
		`
		location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no,
		width=${ parseInt( popupWidth, 10 ) },
		height=${ parseInt( popupHeight, 10 )  },
		top=${ parseInt( top, 10 ) },
		left=${ parseInt( left, 10 ) }
		`
	)

	if ( window.focus ) CSCONNECT.focus();
}
( function( form ) {
	form.onsubmit = function() {
		showConnect( 'CSCONNECT' );
	}
} )( document.getElementById( 'cs-connect-form' ) );
</script>
<?php } ?>

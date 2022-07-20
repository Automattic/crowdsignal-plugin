<?php
/**
 * File containing the view for step 1 of the setup wizard.
 *
 * @package Crowdsignal_Forms\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<br />
			<div class='cs-settings-container'>
				<div class="cs-card cs-section-header is-compact">
					<div class="cs-section-header__label">
						<span class="cs-section-header__label-text"><?php esc_html_e( 'Getting Started', 'crowdsignal-forms' ); ?></span>
					</div>
				</div>

				<div class="cs-card cs-section-header is-compact">

					<div class="cs-form-settings-group crowdsignal-forms" style='text-align: center; width: 100%'>
						<div class="crowdsignal-setup__content">
							<div class="crowdsignal-setup__description">
								<h1><?php esc_html_e( 'Welcome to Crowdsignal', 'crowdsignal-forms' ); ?></h1>
								<p><?php echo wp_kses_post( 'To collect and manage responses you need to connect the plugin to <a href="https://crowdsignal.com">Crowdsignal</a>. <br />It will take less than a minute and it’s free.', 'polldaddy' ); ?></p>
							</div>

							<div class="wrap crowdsignal-settings-wrap">
								<form id="cs-connect-form" class="crowdsignal-options" method="post" action="https://app.crowdsignal.com/get-api-key/" target="CSCONNECT">
									<input type="hidden" name="get_api_key" value="<?php echo esc_attr( get_option( 'crowdsignal_api_key_secret' ) ); ?>" />
									<input type="hidden" name="ref" value="<?php echo esc_attr( admin_url( 'options-general.php?page=crowdsignal-settings' ) ); ?>" />
									<input type="submit" value="<?php esc_html_e( 'Let’s get started', 'polldaddy' ); ?>" class="cs-button is-primary" />
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>

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
		height=${ parseInt( popupHeight, 10 ) },
		top=${ parseInt( top, 10 ) },
		left=${ parseInt( left, 10 ) }
		`
	);

	if ( window.focus ) CSCONNECT.focus();
}
( function( form ) {
	form.onsubmit = function() {
		showConnect( 'CSCONNECT' );
	}
} )( document.getElementById( 'cs-connect-form' ) );
</script>

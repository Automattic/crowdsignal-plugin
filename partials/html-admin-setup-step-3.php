<?php
/**
 * File containing the view for step 3 of the setup wizard.
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
						<span class="cs-section-header__label-text"><?php esc_html_e( 'Getting Started', 'polldaddy' ); ?></span>
					</div>
				</div>

				<div class="cs-card cs-section-header is-compact">
					<div class="cs-form-settings-group" style='width: 100%'>
						<h2><?php echo wp_kses_post( __( 'First time using Crowdsignal?', 'polldaddy' ) ); ?></h2>
						<div class="crowdsignal-setup__middle">
							<p>
							<?php echo wp_kses_post( __( 'You can use Crowdsignal blocks right in your editor. Search for Crowdsignal in the blocks library and add the blocks to your page. Here is a short video to get you started:', 'polldaddy' ) ); ?>
							</p>

							<div class="crowdsignal-setup__video-container">
								<div class="crowdsignal-setup__video">
									<iframe src="https://videopress.com/v/jWTs90Dg?autoplay=0&hd=1" frameborder="0" allowfullscreen></iframe>
								</div>
							</div>
							<p>
							<?php
echo wp_kses_post(
	sprintf(
		// translators: Argument is a link to Crowdsignal's support page.
		__(
			'Do you want to know more about Crowdsignal and our blocks? <a href="%1s" target="_blank">Learn more</a>.',
			'polldaddy'
		),
		'https://crowdsignal.com/support/'
	)
);
?>
							</p>
						</div>
					</div>
				</div>
			</div>

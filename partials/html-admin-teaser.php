<?php
/**
 * File containing the view for the teaser
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
					<span class="cs-section-header__label-text"><?php esc_html_e( 'Crowdsignal Blocks', 'polldaddy' ); ?></span>
				</div>
			</div>

			<div class="cs-card cs-section-header is-compact">
				<div class="cs-form-settings-group" style='width: 100%'>
					<h2><?php echo wp_kses_post( __( 'First time using Crowdsignal?', 'polldaddy' ) ); ?></h2>
					<div class="crowdsignal-setup__middle">
						<p>
			<?php
			printf(
				/* translators: Placeholder is the text "second plugin" and "Install the plugin". */
				esc_html__( 'We have a %1$s called Crowdsignal Forms that allows you to create Crowdsignal blocks right inside of your WordPress editor. %2$s, then search for Crowdsignal in the blocks library and add Crowdsignal blocks, like a Poll block or Feedback button.', 'polldaddy' ),
				sprintf(
					'<a href="https://wordpress.org/plugins/crowdsignal-forms/">%s</a>',
					esc_html__( 'second plugin', 'polldaddy' )
				),
				sprintf(
					'<a href="' . admin_url( 'plugin-install.php?s=crowdsignal+forms&tab=search&type=term' ) . '">%s</a>',
					esc_html__( 'Install the plugin', 'polldaddy' )
				)
			);
			?>
						</p>
						<p><?php _e( 'Here is a short video to get you started:', 'polldaddy' ); ?></p>


						<div class="crowdsignal-setup__video-container">
							<div class="crowdsignal-setup__video">
								<iframe src="https://videopress.com/v/jWTs90Dg?autoplay=0&hd=1" frameborder="0" allowfullscreen></iframe>
							</div>
						</div>


						<p>
				<?php
					echo wp_kses_post(
						sprintf(
							'<a href="' . admin_url( 'admin.php?page=polls&action=landing-page' ) . '">%s</a>',
							__(
								'See an overview of our Crowdsignal blocks.',
								'polldaddy'
							)
						)
					);
					?>
						</p>
						<p>
				<?php
				printf(
					/* translators: Placeholder is the text "website plugins page". */
					esc_html__( 'Install the Crowdsignal Forms plugin directly from your %s.', 'polldaddy' ),
					sprintf(
						'<a href="' . admin_url( 'plugin-install.php?s=crowdsignal+forms&tab=search&type=term' ) . '">%s</a>',
						esc_html__( 'website plugins page', 'polldaddy' )
					)
				);
				?>
						</p>
					</div>
				</div>
			</div>

<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package polldaddy
 */

?>
			<br />
			<div id='crowdsignal_advanced_link'><a href="#" id='toggle_advanced_setting_link'><?php _e( 'Advanced Settings' ); ?></a></div>
			<br />

<script>
jQuery(document).ready( function() {
	jQuery( '#toggle_advanced_setting_link' ).click( function() {
		jQuery( '#crowdsignal__advanced_page' ).toggle( 100 );
		return false;
	})
});
</script>

			<div style='display:none' id="crowdsignal__advanced_page">

				<?php if ( $controller->multiple_accounts ) { ?>
				<div class='cs-settings-container'>
					<div class="cs-card cs-section-header is-compact">
						<div class="cs-form-settings-group" style='width: 100%'>
							<h2><?php echo wp_kses_post( __( 'Multiuser Account', 'polldaddy' ) ); ?></h2>
							<div class="crowdsignal-setup__middle">
								<form action="" method="post">
									<p><input type='checkbox' name='crowdsignal_multiuser' value='1' <?php echo $controller->multiple_accounts ? 'checked' : ''; ?> /> <span class='crowdsignal__advanced_text'><?php _e( 'You have granted authors, editors and admin users of this site access to your Crowdsignal account.', 'polldaddy' ); ?></span></p>
									<p class='crowdsignal__advanced_desc'><?php _e( 'Warning: This is a deprecated feature and wont’t be supported in future. If you disable the Multi-User-Access feature, you won’t be able to activate it again.', 'polldaddy' ); ?></p>
									<p class="submit">
									<?php wp_nonce_field( 'polldaddy-reset' . $current_user->ID ); ?>
									<input type="hidden" name="action" value="multi-account" />
									<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save', 'polldaddy' ) ); ?>" />
									</p>
								</form>
							</div>
						</div>
					</div>
				</div>
				<br />
				<?php } ?>

<?php
global $current_user;
$fields = array( 'polldaddy_api_key', 'pd-rating-comments', 'pd-rating-comments-id', 'pd-rating-comments-pos', 'pd-rating-exclude-post-ids', 'pd-rating-pages', 'pd-rating-pages-id', 'pd-rating-posts', 'pd-rating-posts-id', 'pd-rating-posts-index', 'pd-rating-posts-index-id', 'pd-rating-posts-index-pos', 'pd-rating-posts-pos', 'pd-rating-title-filter', 'pd-rating-usercode', 'pd-rich-snippets', 'pd-usercode-' . $current_user->ID );
$show_reset_form = false;
foreach( $fields as $field ) {
	$value = get_option( $field );
	if ( $value != false ) {
		$show_reset_form = true;
	}
	$settings[ $field ] = $value;
}
$previous_settings = get_option( 'polldaddy_settings' );
if ( ! $show_reset_form && empty( $previous_settings ) ) {
	return;
}
?>

				<div class='cs-settings-container'>
					<div class="cs-card cs-section-header is-compact">
						<div class="" style='width: 100%'>
							<h2><?php echo wp_kses_post( __( 'Reset Connection Settings', 'polldaddy' ) ); ?></h2>
							<div class="crowdsignal-setup__middle">
								<p class='crowdsignal__advanced_text'><?php _e( 'If you are experiencing problems connecting to the Crowdsignal website resetting your connection settings may help. A backup will be made. After resetting, link your account again with the same API key.', 'polldaddy' ); ?></p>
								<p class='crowdsignal__advanced_text'><?php _e( 'The following settings will be reset:', 'polldaddy' ); ?></p>
								<?php if ( $show_reset_form ) { ?>
								<table>
<?php
foreach( $settings as $key => $value ) {
	if ( $value != '' ) {
		if ( strpos( $key, 'usercode' ) ) {
			$value = "***********" . substr( $value, -4 );
		} elseif ( in_array( $key, array( 'pd-rating-pages-id', 'pd-rating-comments-id', 'pd-rating-posts-id' ) ) ) {
			$value = "$value (<a href='https://app.crowdsignal.com/ratings/{$value}/edit/'>" . __( 'Edit', 'polldaddy' ) . "</a>)";
		}
?>
									<tr>
										<th style="text-align: left"><?php echo esc_html( $key ); ?>:</th>
										<td><?php echo $value; ?></td>
									</tr>
<?php
	}
}
?>
								</table>
								<p><?php _e( "* The usercode is like a password, keep it secret.", 'polldaddy' ); ?></p>
								<form action="" method="post">
									<p class="submit">
									<?php wp_nonce_field( 'polldaddy-reset' . $current_user->ID ); ?>
									<input type="hidden" name="action" value="reset-account" />
									<input type="hidden" name="account" value="import" />
									<p><input type="checkbox" name="email" value="1" /> <?php _e( 'Send me an email with the connection settings for future reference' ); ?></p>
									<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Reset API Connection', 'polldaddy' ) ); ?>" />
									</p>
								</form>
								<br />
<?php
								}
if ( is_array( $previous_settings ) && !empty( $previous_settings ) ) {
?>
								<h2><?php _e( 'Restore Previous Settings', 'polldaddy' ); ?></h2>
								<p><?php _e( 'The connection settings for this site were reset but a backup was made. The following settings can be restored:', 'polldaddy' ); ?></p>
								<table>
<?php
	foreach( $previous_settings as $key => $value ) {
		if ( $value != '' ) {
			if ( strpos( $key, 'usercode' ) ) {
				$value = "***********" . substr( $value, -4 );
			} elseif ( in_array( $key, array( 'pd-rating-pages-id', 'pd-rating-comments-id', 'pd-rating-posts-id' ) ) ) {
				$value = "$value (<a href='https://app.crowdsignal.com/ratings/{$value}/edit/'>" . __( 'Edit', 'polldaddy' ) . "</a>)";
			}
?>
									<tr>
										<th style="text-align: left"><?php echo esc_html( $key ); ?>:</th>
										<td><?php echo $value; ?></td>
									</tr>
<?php
		}
	}
?>
								</table>
								<p><?php _e( "* The usercode is like a password, keep it secret.", 'polldaddy' ); ?></p>
								<form action="" method="post">
									<p class="submit">
									<?php wp_nonce_field( 'polldaddy-restore' . $current_user->ID ); ?>
									<input type="hidden" name="action" value="restore-account" />
									<input type="hidden" name="account" value="import" />
									<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Restore API Settings', 'polldaddy' ) ); ?>" />
									</p>
								</form>
								<br />
<?php
	if (
		$show_reset_form
		&& isset( $settings[ 'pd-rating-posts-id' ] )
		&& isset( $previous_settings[ 'pd-rating-posts-id' ] )
		&& $settings[ 'pd-rating-posts-id' ] != $previous_settings[ 'pd-rating-posts-id' ]
	) {
?>
								<h2><?php _e( 'Restore Ratings Settings', 'polldaddy' ); ?></h2>
								<p><?php _e( 'Different rating settings detected. If you are missing ratings on your posts, pages or comments you can restore the original rating settings by clicking the button below.', 'polldaddy' ); ?></p>
								<p><?php _e( 'This tells the plugin to look for this data in a different rating in your Crowdsignal account.', 'polldaddy' ); ?></p>
								<form action="" method="post">
									<p class="submit">
									<?php wp_nonce_field( 'polldaddy-restore-ratings' . $current_user->ID ); ?>
									<input type="hidden" name="action" value="restore-ratings" />
									<input type="hidden" name="account" value="import" />
									<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Restore Ratings Only', 'polldaddy' ) ); ?>" />
									</p>
								</form>
								<br />
<?php
	}
}
?>
							</div>
						</div>
					</div>
				</div>
			</div>


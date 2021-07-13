<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package polldaddy
 */

?>
<?php // phpcs:ignoreFile -- too many legacy warnings, needs full linter ?>
<div id="options-page">
	<div class="icon32" id="icon-options-general"></div>
	<h2>
		<?php _e( 'Crowdsignal Settings', 'polldaddy' ); ?>
	</h2>
	<?php if ( $controller->is_admin || $controller->multiple_accounts ) { ?>
		<h3><?php _e( 'Account Info', 'polldaddy' ); ?></h3>
		<p><?php _e( '<em>Crowdsignal</em> and <em>WordPress.com</em> are now connected using <a href="http://en.support.wordpress.com/wpcc-faq/">WordPress.com Connect</a>. If you have a WordPress.com account you can use it to login to <a href="https://app.crowdsignal.com/">Crowdsignal.com</a>. Click on the Crowdsignal "sign in" button, authorize the connection and create your new Crowdsignal account.', 'polldaddy' ); ?></p>
		<p><?php _e( 'Login to the Crowdsignal website and scroll to the end of your <a href="https://app.crowdsignal.com/account/#apikey">account page</a> to create or retrieve an API key.', 'polldaddy' ); ?></p>
		<?php if ( isset( $account_email ) && $account_email != false ) { ?>
			<p><?php printf( __( 'Your account is currently linked to this API key: <strong>%s</strong>', 'polldaddy' ), WP_POLLDADDY__PARTNERGUID ); ?></p>
			<br />
			<h3><?php _e( 'Link to a different Crowdsignal account', 'polldaddy' ); ?></h3>
		<?php } else { ?>
			<br />
			<h3><?php _e( 'Link to your Crowdsignal account', 'polldaddy' ); ?></h3>
		<?php } ?>
		<form action="" method="post">
			<table class="form-table">
				<tbody>
					<tr class="form-field form-required">
						<th valign="top" scope="row">
							<label for="polldaddy-key">
								<?php _e( 'Crowdsignal.com API Key', 'polldaddy' ); ?>
							</label>
						</th>
						<td>
							<input type="text" name="polldaddy_key" id="polldaddy-key" aria-required="true" size="20" value="<?php if ( isset( $_POST[ 'polldaddy_key' ] ) ) echo esc_attr( $_POST[ 'polldaddy_key' ] ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<?php wp_nonce_field( 'polldaddy-account' ); ?>
				<input type="hidden" name="action" value="import-account" />
				<input type="hidden" name="account" value="import" />
				<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Link Account', 'polldaddy' ) ); ?>" />
			</p>
		</form>
		<?php
	}

	if ( is_object( $poll ) ) {
		?>
		<h3><?php _e( 'General Settings', 'polldaddy' ); ?></h3>
		<form action="" method="post">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th valign="top" scope="row">
							<label><?php _e( 'Default poll settings', 'polldaddy' ); ?></label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span>poll-defaults</span></legend>
								<?php
								foreach ( array(  'randomiseAnswers' => __( 'Randomize answer order', 'polldaddy' ), 'otherAnswer' => __( 'Allow other answers', 'polldaddy' ), 'multipleChoice' => __( 'Multiple choice', 'polldaddy' ), 'sharing' => __( 'Sharing', 'polldaddy' ) ) as $option => $label ) :
									$checked = 'yes' === $poll->$option ? ' checked="checked"' : '';
									?>
									<label for="<?php echo $option; ?>"><input type="checkbox"<?php echo $checked; ?> value="1" id="<?php echo $option; ?>" name="<?php echo $option; ?>" /> <?php echo esc_html( $label ); ?></label><br />
								<?php  endforeach; ?>
								<br class="clear" />
								<br class="clear" />
								<div class="field">
									<label for="resultsType" class="pd-label">
										<?php _e( 'Results Display', 'polldaddy' ); ?>
									</label>
									<select id="resultsType" name="resultsType">
										<option <?php echo $poll->resultsType == 'show' ? 'selected="selected"':''; ?> value="show"><?php _e( 'Show', 'polldaddy' ); ?></option>
										<option <?php echo $poll->resultsType == 'hide' ? 'selected="selected"':''; ?> value="hide"><?php _e( 'Hide', 'polldaddy' ); ?></option>
										<option <?php echo $poll->resultsType == 'percent' ? 'selected="selected"':''; ?> value="percent"><?php _e( 'Percentages', 'polldaddy' ); ?></option>
									</select>
								</div>
								<br class="clear" />
								<div class="field">
									<label for="styleID" class="pd-label"><?php _e( 'Poll style', 'polldaddy' ); ?></label>
									<select id="styleID" name="styleID">
										<?php
										foreach ( (array) $options as $styleID => $label ) :
											$selected = $styleID == $poll->styleID ? ' selected="selected"' : ''; ?>
											<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<br class="clear" />
								<div class="field">
									<label for="blockRepeatVotersType" class="pd-label"><?php _e( 'Repeat Voting', 'polldaddy' ); ?></label>
									<select id="poll-block-repeat" name="blockRepeatVotersType">
										<option <?php echo $poll->blockRepeatVotersType == 'off' ? 'selected="selected"':''; ?> value="off"><?php _e( 'Off', 'polldaddy' ); ?></option>
										<option <?php echo $poll->blockRepeatVotersType == 'cookie' ? 'selected="selected"':''; ?> value="cookie"><?php _e( 'Cookie', 'polldaddy' ); ?></option>
										<option <?php echo $poll->blockRepeatVotersType == 'cookieip' ? 'selected="selected"':''; ?> value="cookieip"><?php _e( 'Cookie & IP address', 'polldaddy' ); ?></option>
									</select>
								</div>
								<br  class="clear" />
								<div class="field">
									<label for="blockExpiration" class="pd-label"><?php _e( 'Block expiration limit', 'polldaddy' ); ?></label>
									<select id="blockExpiration" name="blockExpiration">
										<option value="3600" <?php echo $poll->blockExpiration == 3600 ? 'selected="selected"':''; ?>><?php printf( __( '%d hour', 'polldaddy' ), 1 ); ?></option>
										<option value="10800" <?php echo (int) $poll->blockExpiration == 10800 ? 'selected="selected"' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 3 ); ?></option>
										<option value="21600" <?php echo (int) $poll->blockExpiration == 21600 ? 'selected="selected"' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 6 ); ?></option>
										<option value="43200" <?php echo (int) $poll->blockExpiration == 43200 ? 'selected="selected"' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 12 ); ?></option>
										<option value="86400" <?php echo (int) $poll->blockExpiration == 86400 ? 'selected="selected"' : ''; ?>><?php printf( __( '%d day', 'polldaddy' ), 1 ); ?></option>
										<option value="604800" <?php echo (int) $poll->blockExpiration == 604800 ? 'selected="selected"' : ''; ?>><?php printf( __( '%d week', 'polldaddy' ), 1 ); ?></option>
									</select>
								</div>
								<br class="clear" />
							</fieldset>
						</td>
					</tr>
					<?php $controller->plugin_options_add(); ?>
				</tbody>
			</table>
			<p class="submit">
				<?php wp_nonce_field( 'polldaddy-account' ); ?>
				<input type="hidden" name="action" value="update-options" />
				<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Options', 'polldaddy' ) ); ?>" />
			</p>
		</form>
		<?php
	} // is_object( $poll )
	?>
	<div class="extra-stuff">
		<?php
		global $current_user;
		$fields = array( 'polldaddy_api_key', 'pd-rating-comments', 'pd-rating-comments-id', 'pd-rating-comments-pos', 'pd-rating-exclude-post-ids', 'pd-rating-pages', 'pd-rating-pages-id', 'pd-rating-posts', 'pd-rating-posts-id', 'pd-rating-posts-index', 'pd-rating-posts-index-id', 'pd-rating-posts-index-pos', 'pd-rating-posts-pos', 'pd-rating-title-filter', 'pd-rating-usercode', 'pd-rich-snippets', 'pd-usercode-' . $current_user->ID );
		$show_reset_form = false;
		foreach( $fields as $field ) {
			$value = get_option( $field );
			if ( $value != false )
				$show_reset_form = true;
			$settings[ $field ] = $value;
		}
		if ( $show_reset_form ) {
			echo "<h3>" . __( 'Reset Connection Settings', 'polldaddy' ) . "</h3>";
			echo "<p>" . __( 'If you are experiencing problems connecting to the Crowdsignal website resetting your connection settings may help. A backup will be made. After resetting, link your account again with the same API key.', 'polldaddy' ) . "</p>";
			echo "<p>" . __( 'The following settings will be reset:', 'polldaddy' ) . "</p>";
			echo "<table>";
			foreach( $settings as $key => $value ) {
				if ( $value != '' ) {
					if ( strpos( $key, 'usercode' ) ) {
						$value = "***********" . substr( $value, -4 );
					} elseif ( in_array( $key, array( 'pd-rating-pages-id', 'pd-rating-comments-id', 'pd-rating-posts-id' ) ) ) {
						$value = "$value (<a href='https://app.crowdsignal.com/ratings/{$value}/edit/'>" . __( 'Edit', 'polldaddy' ) . "</a>)";
					}
					?>
					<tr>
						<th style="text-align: right"><?php echo esc_html( $key ); ?>:</th>
						<td><?php echo $value; ?></td>
					</tr>
					<?php
				}
			}
			echo "</table>";
			echo "<p>" . __( "* The usercode is like a password, keep it secret.", 'polldaddy' ) . "</p>";
			?>
			<form action="" method="post">
				<p class="submit">
					<?php wp_nonce_field( 'polldaddy-reset' . $current_user->ID ); ?>
					<input type="hidden" name="action" value="reset-account" />
					<input type="hidden" name="account" value="import" />
					<p><input type="checkbox" name="email" value="1" /> <?php _e( 'Send me an email with the connection settings for future reference' ); ?></p>
					<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Reset', 'polldaddy' ) ); ?>" />
				</p>
			</form>
			<br />
			<?php
		}
		$previous_settings = get_option( 'polldaddy_settings' );
		if ( is_array( $previous_settings ) && !empty( $previous_settings ) ) {
			echo "<h3>" . __( 'Restore Previous Settings', 'polldaddy' ) . "</h3>";
			echo "<p>" . __( 'The connection settings for this site were reset but a backup was made. The following settings can be restored:', 'polldaddy' ) . "</p>";
			echo "<table>";
			foreach( $previous_settings as $key => $value ) {
				if ( $value != '' ) {
					if ( strpos( $key, 'usercode' ) ) {
						$value = "***********" . substr( $value, -4 );
					} elseif ( in_array( $key, array( 'pd-rating-pages-id', 'pd-rating-comments-id', 'pd-rating-posts-id' ) ) ) {
						$value = "$value (<a href='https://app.crowdsignal.com/ratings/{$value}/edit/'>" . __( 'Edit', 'polldaddy' ) . "</a>)";
					}
					?>
					<tr>
						<th style="text-align: right"><?php echo esc_html( $key ); ?>:</th>
						<td><?php echo $value; ?></td>
					</tr>
					<?php
				}
			}
			echo "</table>";
			echo "<p>" . __( "* The usercode is like a password, keep it secret.", 'polldaddy' ) . "</p>";
			?>
			<form action="" method="post">
				<p class="submit">
					<?php wp_nonce_field( 'polldaddy-restore' . $current_user->ID ); ?>
					<input type="hidden" name="action" value="restore-account" />
					<input type="hidden" name="account" value="import" />
					<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Restore', 'polldaddy' ) ); ?>" />
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
				echo "<h3>" . __( 'Restore Ratings Settings', 'polldaddy' ) . "</h3>";
				echo "<p>" . __( 'Different rating settings detected. If you are missing ratings on your posts, pages or comments you can restore the original rating settings by clicking the button below.', 'polldaddy' ) . "</p>";
				echo "<p>" . __( 'This tells the plugin to look for this data in a different rating in your Crowdsignal account.', 'polldaddy' ) . "</p>";
				?>
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

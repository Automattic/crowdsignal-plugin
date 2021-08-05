<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package crowdsignal
 */

?>
<h2 id="polldaddy-header"><?php esc_html_e( 'Crowdsignal', 'polldaddy' ); ?></h2>
<p>
	<?php
	/* translators: name of the rating being deleted */
	printf( __( 'Before you can use the Crowdsignal plugin, you need to enter your <a href="%s">Crowdsignal.com</a> account details.', 'polldaddy' ), 'https://crowdsignal.com/' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed input
	?>
</p>

<form action="" method="post">
	<table class="form-table">
		<tbody>
			<tr class="form-field form-required">
				<th valign="top" scope="row">
					<label for="polldaddy-email"><?php esc_html_e( 'Crowdsignal Email Address', 'polldaddy' ); ?></label>
				</th>
				<td>
					<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" />
				</td>
			</tr>
			<tr class="form-field form-required">
				<th valign="top" scope="row">
					<label for="polldaddy-password"><?php esc_html_e( 'Crowdsignal Password', 'polldaddy' ); ?></label>
				</th>
				<td>
					<input type="password" name="polldaddy_password" id="polldaddy-password" aria-required="true" size="40" />
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<?php wp_nonce_field( 'polldaddy-account' ); ?>
		<input type="hidden" name="action" value="account" />
		<input type="hidden" name="account" value="import" />
		<input class="button-secondary" type="submit" value="<?php echo esc_attr( __( 'Submit', 'polldaddy' ) ); ?>" />
	</p>
</form>

<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package crowdsignal
 */

?>
<div class="error" id="polldaddy-error">
	<?php
	foreach ( $error_codes as $error_code ) : // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer
		foreach ( $errors->get_error_messages( $error_code ) as $error_message ) : // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer
			?>
			<p>
				<?php
				// don't know why is this ternary here, truthy output (first) was unescaped. Leaving as it was, but strange.
				echo $errors->get_error_data( $error_code ) ? esc_html( $error_message ) : esc_html( $error_message ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer
				?>
			</p>
			<?php
		endforeach;
	endforeach;
	?>
</div>

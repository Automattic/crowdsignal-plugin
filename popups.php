<?php
function pd_video_shortcodes_help($video_form) {
	return '
	<table class="describe"><tbody>
		<tr>
			<th valign="top" scope="row" class="label">
				<span class="alignleft"><label for="insertonly[href]">' . __('URL', 'polldaddy') . '</label></span>
				<span class="alignright"><abbr title="required" class="required">*</abbr></span>
			</th>
			<td class="field"><input type="text" id="insertonly[href]" name="insertonly[href]" value="" /></td>
		</tr>
		<tr>
			<td colspan="2">
				<p>' . __('Paste your YouTube or Google Video URL above, or use the examples below.', 'polldaddy') . '</p>
				<ul class="short-code-list">
					<li>' . sprintf( __('<a href="%s" target="_blank">YouTube instructions</a> %s', 'polldaddy'), 'http://support.wordpress.com/videos/youtube/', '<code>[youtube=http://www.youtube.com/watch?v=cXXm696UbKY]</code>' )  .'</li>
					<li>' . sprintf( __('<a href="%s" target="_blank">Google instructions</a> %s', 'polldaddy') , 'http://support.wordpress.com/videos/google-video/', '<code>[googlevideo=http://video.google.com/googleplayer.swf?docId=-8459301055248673864]</code>' ) . '</li>
					<li>' . sprintf( __('<a href="%s" target="_blank">DailyMotion instructions</a> %s', 'polldaddy'), 'http://support.wordpress.com/videos/dailymotion/', '<code>[dailymotion id=5zYRy1JLhuGlP3BGw]</code>' ) . '</li>
				</ul>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr( __('Insert into Poll', 'polldaddy') ) . '" />
			</td>
		</tr>
	</tbody></table>
	';
}

function pd_audio_shortcodes_help($audio_form) {
	return '
	<table class="describe"><tbody>
		<tr>
			<th valign="top" scope="row" class="label">
				<span class="alignleft"><label for="insertonly[href]">' . __('Audio File URL', 'polldaddy') . '</label></span>
				<span class="alignright"><abbr title="required" class="required">*</abbr></span>
			</th>
			<td class="field"><input id="insertonly[href]" name="insertonly[href]" value="" type="text" aria-required="true"></td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="submit" class="button" name="insertonlybutton" value="' . esc_attr( __('Insert into Poll', 'polldaddy') ) . '" />
			</td>
		</tr>
	</tbody></table>
	';
}

function pd_image_shortcodes_help($image_form) {
	return '
	<h4 class="media-sub-title">' . __('Insert an image from another web site', 'polldaddy') . '</h4>
	<table class="describe"><tbody>
		<tr>
			<th valign="top" scope="row" class="label" style="width:130px;">
				<span class="alignleft"><label for="src">' . __('Image URL', 'polldaddy') . '</label></span>
				<span class="alignright"><abbr id="status_img" title="required" class="required">*</abbr></span>
			</th>
			<td class="field"><input id="src" name="src" value="" type="text" aria-required="true" onblur="addExtImage.getImageData()" /></td>
		</tr>

		<tr>
			<th valign="top" scope="row" class="label">
				<span class="alignleft"><label for="title">' . __('Image Title', 'polldaddy') . '</label></span>
				<span class="alignright"><abbr title="required" class="required">*</abbr></span>
			</th>
			<td class="field"><input id="alt" name="alt" value="" type="hidden" /><input id="url" name="url" value="" type="hidden" /><input id="caption" name="caption" value="" type="hidden" /><input id="title" name="title" value="" type="text" aria-required="true" /></td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="button" value="' . esc_attr( __('Insert into Poll', 'polldaddy') ) . '" onclick="addExtImage.insert()" style="color: rgb(187, 187, 187);" id="go_button" class="button">
			</td>
		</tr>
	</tbody></table>
	';
}

function polldaddy_popups_init() {
	if( isset( $_REQUEST['polls_media'] ) ){
		add_filter( 'type_url_form_video', 'pd_video_shortcodes_help');
		add_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help');
		add_filter( 'type_url_form_image', 'pd_image_shortcodes_help');
	}
}

add_action( 'admin_init', 'polldaddy_popups_init' );
?>

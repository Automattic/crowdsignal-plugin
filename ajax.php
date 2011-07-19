<?php

add_action( 'wp_ajax_myajax-submit', 'myajax_submit' );

class Polldaddy_Ajax {

	function Polldaddy_Ajax() {
		$this->__construct();
	}

	function __construct() {
		// Catch AJAX
		add_action( 'wp_ajax_polls_upload_image', array( &$this, 'ajax_upload_image' ) );
		add_action( 'wp_ajax_polls_add_answer', array( &$this, 'ajax_add_answer' ) );

		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) ) {
			$guid = get_option( 'polldaddy_api_key' );
			if ( !$guid || !is_string( $guid ) )
				$guid = false;
			define( 'WP_POLLDADDY__PARTNERGUID', $guid );
		}
	}

	function ajax_upload_image() {
		require_once dirname( __FILE__ ) . '/polldaddy-client.php';

		check_admin_referer( 'send-media' );

		$attach_id = $user_code = 0;
		$name = $url = '';

		if ( isset( $_POST['attach-id'] ) )
			$attach_id = (int) $_POST['attach-id'];

		if ( isset( $_POST['uc'] ) )
			$user_code = $_POST['uc'];

		if ( isset( $_POST['url'] ) )
			$url = $_POST['url'];

		$parts     = pathinfo( $url );
		$name      = $parts['basename'];
		$polldaddy = new api_client( WP_POLLDADDY__PARTNERGUID, $user_code );
		$response  = $polldaddy->upload_image( $name, $url, 'poll', ($attach_id>1000?$attach_id:0) );

		if ( is_a( $response, "Polldaddy_Media" ) )
			echo urldecode( $response->upload_result ).'||'.$attach_id;
		die();
	}

	function ajax_add_answer() {
		check_admin_referer( 'add-answer' );

		$a     = 0;
		$popup = 0;
		$src   = '';

		if ( isset( $_POST['aa'] ) )
			$a = (int) $_POST['aa'];

		if ( isset( $_POST['src'] ) )
			$src = $_POST['src'];

		if ( isset( $_POST['popup'] ) )
			$popup = $_POST['popup'];

		$response = '<li>
				<table class="answer">

						<tr>
							<th>
								<span class="handle" title="' . esc_attr( __( 'click and drag to reorder' ) ) . '"><img src="' . $src . 'img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span>
							</th>
							<td class="answer-input">
								<input type="text" autocomplete="off" placeholder="' . esc_attr( __( 'Enter an answer here', 'polldaddy' ) ) .'" value="" tabindex="2" size="30" name="answer[new' . $a .']" />
							</td>';

		if ( $popup > 0 ) {
			$response .= '<td class="answer-media-icons" style="width:55px !important;">
								<ul class="answer-media" style="min-width: 30px;">
									<li class="media-preview" style="width: 20px; height: 16px; padding-left: 5px;"></li>
									<li><a href="#" class="delete-answer delete" title="' . esc_attr( 'delete this answer' ) .'"><img src="' . $src . 'img/icon-clear-search.png" width="16" height="16" /></a></li>
								</ul>';
		}
		else {
			$response .= '<td class="answer-media-icons">
								<ul class="answer-media">
									<li class="media-preview" style="width: 20px; height: 16px; padding-left: 5px;"></li>
									<li><a title="' . esc_attr( __( 'Add an Image', 'polldaddy' ) ) . '" class="thickbox media image" id="add_poll_image' . $a .'" href="#"><img style="vertical-align:middle;" alt="' . esc_attr( __( 'Add an Image', 'polldaddy' ) ) . '" src="images/media-button-image.gif"></a></a></li>
									<li><a title="' . esc_attr( __( 'Add Audio', 'polldaddy' ) ) . '" class="thickbox media video" id="add_poll_video' . $a .'" href="#"><img style="vertical-align:middle;" alt="' . esc_attr( __( 'Add Audio', 'polldaddy' ) ) . '" src="images/media-button-video.gif"></a></a></li>
									<li><a title="' . esc_attr( __( 'Add Video', 'polldaddy' ) ) . '" class="thickbox media audio" id="add_poll_audio' . $a .'" href="#"><img style="vertical-align:middle;" alt="' . esc_attr( __( 'Add Video', 'polldaddy' ) ) . '" src="images/media-button-music.gif"></a></li>
									<li><a href="#" class="delete-answer delete" title="' . esc_attr( 'delete this answer' ) . '"><img src="' . $src . 'img/icon-clear-search.png" width="16" height="16" /></a></li>
								</ul>';
		}

		$response .= '<input type="hidden" value="" id="hMC' . $a .'" name="media[' . $a .']">
									<input type="hidden" value="" id="hMT' . $a .'" name="mediaType[' . $a .']">

							</td>
						</tr>

				</table>

			</li>';

		echo $response;
		die();
	}
}

function polldaddy_ajax_init() {
	global $polldaddy_ajax;

	$polldaddy_ajax = new Polldaddy_Ajax();
}

add_action( 'init', 'polldaddy_ajax_init' );
?>

<?php

add_action( 'wp_ajax_myajax-submit', 'myajax_submit' );

class Polldaddy_Ajax {
	public function __construct() {
		// Catch AJAX
		add_action( 'wp_ajax_polls_upload_image', array( &$this, 'ajax_upload_image' ) );
		
		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) ) { 
			$guid = get_option( 'polldaddy_api_key' );
			if ( !$guid || !is_string( $guid ) )
				$guid = false;
			define( 'WP_POLLDADDY__PARTNERGUID', $guid );			
		}
	}
	
	public function ajax_upload_image() {
		require_once dirname( __FILE__ ) . '/polldaddy-client.php';
		
		//check_admin_referer( 'send-media' );
		
		$attach_id = $user_code = 0;
		$name = $url = '';
		
		if ( isset( $_POST['attach-id'] ) )
			$attach_id = (int) $_POST['attach-id'];
			
		if ( isset( $_POST['uc'] ) )
			$user_code = $_POST['uc'];
			
		if ( isset( $_POST['url'] ) )
			$url = $_POST['url'];
			
		$parts = pathinfo( $url );
		
		$name = $parts['basename'];
		
		//$file = $_FILES['upload'];		
		//$data = base64_encode( fread( fopen( $file['tmp_name'], "rb"), filesize( $file['tmp_name'] ) ) );
		
		$polldaddy = new api_client( WP_POLLDADDY__PARTNERGUID, $user_code );
		
		$response = $polldaddy->upload_image( $name, $url, 'poll', ($attach_id>1000?$attach_id:0) );
		
		print_r($polldaddy->get_xml());
		if ( $response )
			echo urldecode( $response->upload_result ).'||'.$attach_id;
		die();
	}
}

function polldaddy_ajax_init() {
	global $polldaddy_ajax;

	$polldaddy_ajax = new Polldaddy_Ajax();
}

add_action( 'init', 'polldaddy_ajax_init' );
?>
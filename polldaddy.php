<?php

/*
Plugin Name: PollDaddy Polls
Description: Create and manage PollDaddy polls in WordPress
Author: Automattic, Inc.
Author URL: http://automattic.com/
Version: 1.7
*/

// You can hardcode your PollDaddy PartnerGUID (API Key) here
//define( 'WP_POLLDADDY__PARTNERGUID', '12345...' );

if ( !defined( 'WP_POLLDADDY__CLASS' ) )
	define( 'WP_POLLDADDY__CLASS', 'WP_PollDaddy' );

if ( !defined( 'WP_POLLDADDY__POLLDADDY_CLIENT_PATH' ) )
	define( 'WP_POLLDADDY__POLLDADDY_CLIENT_PATH', dirname( __FILE__ ) . '/polldaddy-client.php' );

// TODO: when user changes PollDaddy password, userCode changes
class WP_PollDaddy {
	var $errors;
	var $polldaddy_client_class = 'api_client';
	var $base_url = false;
	var $use_ssl = 0;
	var $scheme = 'https';
	var $version = '1.7';

	var $polldaddy_clients = array();

	function &get_client( $api_key, $userCode = null ) {
		if ( isset( $this->polldaddy_clients[$api_key] ) ) {
			if ( !is_null( $userCode ) )
				$this->polldaddy_clients[$api_key]->userCode = $userCode;
			return $this->polldaddy_clients[$api_key];
		}
		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;
		$this->polldaddy_clients[$api_key] = new $this->polldaddy_client_class( $api_key, $userCode );
		return $this->polldaddy_clients[$api_key];
	}

	function admin_menu() {
		$this->errors = new WP_Error;

		if ( !$this->base_url )
			$this->base_url = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';	

		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) ) {
			$guid = get_option( 'polldaddy_api_key' );
			if ( !$guid || !is_string( $guid ) )
				$guid = false;
			define( 'WP_POLLDADDY__PARTNERGUID', $guid );
		}

		if ( !WP_POLLDADDY__PARTNERGUID ) {
			$this->use_ssl = (int) get_option( 'polldaddy_use_ssl' );	
			
			if ( function_exists( 'add_object_page' ) ) // WP 2.7+
				$hook = add_object_page( __( 'Polls' ), __( 'Polls' ), 'edit_posts', 'polls', array( &$this, 'api_key_page' ), "{$this->base_url}polldaddy.png" );
			else
				$hook = add_management_page( __( 'Polls' ), __( 'Polls' ), 'edit_posts', 'polls', array( &$this, 'api_key_page' ) );
			
			add_action( "load-$hook", array( &$this, 'api_key_page_load' ) );
			if ( empty( $_GET['page'] ) || 'polls' != $_GET['page'] )
				add_action( 'admin_notices', create_function( '', 'echo "<div class=\"error\"><p>" . sprintf( "You need to <a href=\"%s\">input your PollDaddy.com account details</a>.", "edit.php?page=polls" ) . "</p></div>";' ) );
			return false;
		}

		if ( function_exists( 'add_object_page' ) ) // WP 2.7+
			$hook = add_object_page( __( 'Polls' ), __( 'Polls' ), 'edit_posts', 'polls', array( &$this, 'management_page' ), "{$this->base_url}polldaddy.png" );
		else
			$hook = add_management_page( __( 'Polls' ), __( 'Polls' ), 'edit_posts', 'polls', array( &$this, 'management_page' ) );
		add_action( "load-$hook", array( &$this, 'management_page_load' ) );

		// Hack-a-lack-a
		add_submenu_page( 'polls', __( 'Edit Polls' ), __( 'Edit' ), 'edit_posts', 'polls' );
		add_submenu_page( 'polls', __( 'Add New Poll' ), __( 'Add New' ), 'edit_posts', 'polls&amp;action=create-poll', array( &$this, 'management_page' ) );
		add_submenu_page( 'polls', __( 'Custom Styles' ), __( 'Custom Styles' ), 'edit_posts', 'polls&amp;action=list-styles', array( &$this, 'management_page' ) );

		add_action( 'media_buttons', array( &$this, 'media_buttons' ) );
	}

	function api_key_page_load() {
		if ( 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || empty( $_POST['action'] ) || 'account' != $_POST['action'] )
			return false;

		check_admin_referer( 'polldaddy-account' );

		$polldaddy_email = stripslashes( $_POST['polldaddy_email'] );
		$polldaddy_password = stripslashes( $_POST['polldaddy_password'] );
		
		if ( !$polldaddy_email )
			$this->errors->add( 'polldaddy_email', __( 'Email address required' ) );

		if ( !$polldaddy_password )
			$this->errors->add( 'polldaddy_password', __( 'Password required' ) );

		if ( $this->errors->get_error_codes() )
			return false;

		if ( !empty( $_POST['polldaddy_use_ssl_checkbox'] ) ) {
			if ( $polldaddy_use_ssl = (int) $_POST['polldaddy_use_ssl'] ) {
				$this->use_ssl = 0; //checked (by default)
			} else {
				$this->use_ssl = 1; //unchecked
				$this->scheme = 'http';
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
		}

		$details = array( 
			'uName' => get_bloginfo( 'name' ),
			'uEmail' => $polldaddy_email,
			'uPass' => $polldaddy_password,
			'partner_userid' => $GLOBALS['current_user']->ID
		);

		if ( function_exists( 'wp_remote_post' ) ) { // WP 2.7+
			$polldaddy_api_key = wp_remote_post( $this->scheme . '://api.polldaddy.com/key.php', array(
				'body' => $details
			) );
			if ( is_wp_error( $polldaddy_api_key ) ) {
				$this->errors = $polldaddy_api_key;
				return false;
			}
			$response_code = wp_remote_retrieve_response_code( $polldaddy_api_key );
			if ( 200 != $response_code ) {
				$this->errors->add( 'http_code', __( 'Could not connect to PollDaddy API Key service' ) );
				return false;
			}
			$polldaddy_api_key = wp_remote_retrieve_body( $polldaddy_api_key );
		} else {
			$fp = fsockopen(
				'api.polldaddy.com',
				80,
				$err_num,
				$err_str,
				3
			);

			if ( !$fp ) {
				$this->errors->add( 'connect', __( "Can't connect to PollDaddy.com" ) );
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, 3 );

			global $wp_version;

			$request_body = http_build_query( $details, null, '&' );

			$request  = "POST /key.php HTTP/1.0\r\n";
			$request .= "Host: api.polldaddy.com\r\n";
			$request .= "User-agent: WordPress/$wp_version\r\n";
			$request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
			$request .= 'Content-Length: ' . strlen( $request_body ) . "\r\n";

			fwrite( $fp, "$request\r\n$request_body" );

			$response = '';
			while ( !feof( $fp ) )
				$response .= fread( $fp, 4096 );
			fclose( $fp );
			list($headers, $polldaddy_api_key) = explode( "\r\n\r\n", $response, 2 );
		}

		if( isset( $polldaddy_api_key ) && strlen( $polldaddy_api_key ) > 0 ){
			update_option( 'polldaddy_api_key', $polldaddy_api_key );
		}
		else{
			$this->errors->add( 'polldaddy_api_key', __( 'Login to PollDaddy failed.  Double check your email address and password.' ) );
			if ( 1 !== $this->use_ssl ) {
				$this->errors->add( 'polldaddy_api_key', __( 'If your email address and password are correct, your host may not support secure logins.' ) );
				$this->errors->add( 'polldaddy_api_key', __( 'In that case, you may be able to log in to PollDaddy by unchecking the "Use SSL to Log in" checkbox.' ) );
				$this->use_ssl = 0;
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
			return false;
		}

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->get_usercode( $GLOBALS['current_user']->ID ) ) {
			$this->parse_errors( $polldaddy );
			$this->errors->add( 'GetUserCode', __( 'Account could not be accessed.  Are your email address and password correct?' ) );
			return false;
		}
		
		wp_redirect( add_query_arg( array( 'page' => 'polls' ), wp_get_referer() ) );
		exit;
	}

	function parse_errors( &$polldaddy ) {
		if ( $polldaddy->errors )
			foreach ( $polldaddy->errors as $code => $error )
				$this->errors->add( $code, $error );
		if ( isset( $this->errors->errors[4] ) ) {
			$this->errors->errors[4] = array( sprintf( __( 'Obsolete PollDaddy User API Key:  <a href="%s">Sign in again to re-authenticate</a>' ), add_query_arg( array( 'action' => 'signup', 'reaction' => empty( $_GET['action'] ) ? false : $_GET['action'] ) ) ) );
			$this->errors->add_data( true, 4 );
		}
	}

	function print_errors() {
		if ( !$error_codes = $this->errors->get_error_codes() )
			return;
?>

<div class="error">

<?php

		foreach ( $error_codes as $error_code ) :
			foreach ( $this->errors->get_error_messages( $error_code ) as $error_message ) :
?>

	<p><?php echo $this->errors->get_error_data( $error_code ) ? $error_message : wp_specialchars( $error_message ); ?></p>

<?php
			endforeach;
		endforeach;
		
		$this->errors = new WP_Error;
?>

</div>
<br class="clear" />

<?php
	}

	function api_key_page() {
		$this->print_errors();
?>

<div class="wrap">

	<h2><?php _e( 'PollDaddy Account' ); ?></h2>

	<p><?php printf( __('Before you can use the PollDaddy plugin, you need to enter your <a href="%s">PollDaddy.com</a> account details.' ), 'http://polldaddy.com/' ); ?></p>

	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-email">PollDaddy Email Address</label>
					</th>
					<td>
						<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" value="<?php if ( isset( $_POST['polldaddy_email'] ) ) echo attribute_escape( $_POST['polldaddy_email'] ); ?>" />
					</td>
				</tr>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-password">PollDaddy Password</label>
					</th>
					<td>
						<input type="password" name="polldaddy_password" id="polldaddy-password" aria-required="true" size="40" />
					</td>
				</tr>
				<?php
				$checked = '';
				if ( $this->use_ssl == 0 )
					$checked = 'checked="checked"';
				?>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-use-ssl">Use SSL to Log in</label>
					</th>
					<td>
						<input type="checkbox" name="polldaddy_use_ssl" id="polldaddy-use-ssl" value="1" <?php echo $checked ?> style="width: auto"/>
						<label for="polldaddy-use-ssl">This ensures a secure login to your PollDaddy account.  Only uncheck if you are having problems logging in.</label>
						<input type="hidden" name="polldaddy_use_ssl_checkbox" value="1" />
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<?php wp_nonce_field( 'polldaddy-account' ); ?>
			<input type="hidden" name="action" value="account" />
			<input type="hidden" name="account" value="import" />
			<input type="submit" value="<?php echo attribute_escape( __( 'Submit' ) ); ?>" />
		</p>
	</form>
</div>

<?php
	}

	function media_buttons() {
		$title = __( 'Add Poll' );
		echo "<a href='admin.php?page=polls&amp;iframe&amp;TB_iframe=true' id='add_poll' class='thickbox' title='$title'><img src='{$this->base_url}polldaddy.png' alt='$title' /></a>";
	}

	function management_page_load() {
		global $current_user;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID );	
		$polldaddy->reset();
		
		if ( !defined( 'WP_POLLDADDY__USERCODE' ) )
			define( 'WP_POLLDADDY__USERCODE', $polldaddy->get_usercode( $current_user->ID ) );

		wp_reset_vars( array( 'action', 'poll', 'style' ) );
		global $action, $poll, $style;

		if ( !WP_POLLDADDY__USERCODE )
			$action = 'signup';

		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		wp_enqueue_script( 'polls', "{$this->base_url}polldaddy.js", array( 'jquery', 'jquery-ui-sortable' ), $this->version );
		wp_enqueue_script( 'polls-common', "{$this->base_url}common.js", array(), $this->version );
		
		switch ( $action ) :
			case 'edit' :
			case 'edit-poll' :
			case 'create-poll' :
				wp_enqueue_script( 'polls-style', "http://i.polldaddy.com/js/poll-style-picker.js", array(), $this->version );
				break;
			case 'edit-style' :
			case 'create-style' :
				wp_enqueue_script( 'polls-style', "http://i.polldaddy.com/js/style-editor.js", array(), $this->version );
				wp_enqueue_script( 'polls-style-color', "http://i.polldaddy.com/js/jscolor.js", array(), $this->version );
				wp_enqueue_style( 'polls', "{$this->base_url}style-editor.css", array(), $this->version );
				break;
		endswitch;
		
		wp_enqueue_script( 'admin-forms' );
		add_thickbox();

		wp_enqueue_style( 'polls', "{$this->base_url}polldaddy.css", array( 'global', 'wp-admin' ), $this->version );
		add_action( 'admin_body_class', array( &$this, 'admin_body_class' ) );

		add_action( 'admin_notices', array( &$this, 'management_page_notices' ) );

		$query_args = array();
		$args = array();
		
		$allowedtags = array(
			'a' => array(
				'href' => array (),
				'title' => array (),
				'target' => array ()),
			'img' => array(
				'alt' => array (),
				'align' => array (),
				'border' => array (),
				'class' => array (),
				'height' => array (),
				'hspace' => array (),
				'longdesc' => array (),
				'vspace' => array (),
				'src' => array (),
				'width' => array ()),
			'abbr' => array(
				'title' => array ()),
			'acronym' => array(
				'title' => array ()),
			'b' => array(),
			'blockquote' => array(
				'cite' => array ()),
			'cite' => array (),
			'em' => array (), 
			'i' => array (),
			'q' => array( 
				'cite' => array ()),
			'strike' => array(),
			'strong' => array()
		);

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		switch ( $action ) :
		case 'signup' : // sign up for first time
		case 'account' : // reauthenticate
			if ( !$is_POST )
				return;

			check_admin_referer( 'polldaddy-account' );

			if ( $new_args = $this->management_page_load_signup() )
				$query_args = array_merge( $query_args, $new_args );
			if ( $this->errors->get_error_codes() )
				return false;

			wp_reset_vars( array( 'action' ) );
			if ( !empty( $_GET['reaction'] ) )
				$query_args['action'] = $_GET['reaction'];
			elseif ( !empty( $_GET['action'] ) && 'signup' != $_GET['action'] )
				$query_args['action'] = $_GET['action'];
			else
				$query_args['action'] = false;
			break;
		case 'delete' :
			if ( empty( $poll ) )
				return;

			if ( is_array( $poll ) )
				check_admin_referer( 'delete-poll_bulk' );
			else
				check_admin_referer( "delete-poll_$poll" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );

			foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
				$polldaddy->reset();
				$poll_object = $polldaddy->get_poll( $poll );

				if ( !$this->can_edit( $poll_object ) ) {
					$this->errors->add( 'permission', __( 'You are not allowed to delete this poll.' ) );
					return false;
				}

				// Send Poll Author credentials
				if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
					$polldaddy->reset();
					if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) { 
						$this->errors->add( 'no_usercode', __( 'Invalid Poll Author' ) );
					}
					$polldaddy->userCode = $userCode;
				}

				$polldaddy->reset();
				$polldaddy->delete_poll( $poll_id );
			}

			$query_args['message'] = 'deleted';
			$query_args['deleted'] = count( (array) $poll );
			break;
		case 'open' :
			if ( empty( $poll ) )
				return;

			if ( is_array( $poll ) )
				check_admin_referer( 'action-poll_bulk' );
			else
				check_admin_referer( "open-poll_$poll" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );

			foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
				$polldaddy->reset();
				$poll_object = $polldaddy->get_poll( $poll );

				if ( !$this->can_edit( $poll_object ) ) {
					$this->errors->add( 'permission', __( 'You are not allowed to delete this poll.' ) );
					return false;
				}

				// Send Poll Author credentials
				if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
					$polldaddy->reset();
					if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) { 
						$this->errors->add( 'no_usercode', __( 'Invalid Poll Author' ) );
					}
					$polldaddy->userCode = $userCode;
				}

				$polldaddy->reset();
				$polldaddy->open_poll( $poll_id );
			}

			$query_args['message'] = 'opened';
			$query_args['opened'] = count( (array) $poll );
			break;
		case 'close' :
			if ( empty( $poll ) )
				return;

			if ( is_array( $poll ) )
				check_admin_referer( 'action-poll_bulk' );
			else
				check_admin_referer( "close-poll_$poll" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );

			foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
				$polldaddy->reset();
				$poll_object = $polldaddy->get_poll( $poll );

				if ( !$this->can_edit( $poll_object ) ) {
					$this->errors->add( 'permission', __( 'You are not allowed to delete this poll.' ) );
					return false;
				}

				// Send Poll Author credentials
				if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
					$polldaddy->reset();
					if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) { 
						$this->errors->add( 'no_usercode', __( 'Invalid Poll Author' ) );
					}
					$polldaddy->userCode = $userCode;
				}

				$polldaddy->reset();
				$polldaddy->close_poll( $poll_id );
			}

			$query_args['message'] = 'closed';
			$query_args['closed'] = count( (array) $poll );
			break;
		case 'edit-poll' : // TODO: use polldaddy_poll
			if ( !$is_POST || !$poll = (int) $poll )
				return;

			check_admin_referer( "edit-poll_$poll" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
			$polldaddy->reset();

			$poll_object = $polldaddy->get_poll( $poll );
			$this->parse_errors( $polldaddy );

			if ( !$this->can_edit( $poll_object ) ) {
				$this->errors->add( 'permission', __( 'You are not allowed to edit this poll.' ) );
				return false;
			}

			// Send Poll Author credentials
			
			if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
				$polldaddy->reset();
				if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) {	
					$this->errors->add( 'no_usercode', __( 'Invalid Poll Author' ) );
				}
				$this->parse_errors( $polldaddy );
				$polldaddy->userCode = $userCode;
			}

			if ( !$poll_object ) 
				$this->errors->add( 'GetPoll', __( 'Poll not found' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$poll_data = get_object_vars( $poll_object );
			foreach ( $poll_data as $key => $value )
				if ( '_' === $key[0] )
					unset( $poll_data[$key] );

			foreach ( array( 'multipleChoice', 'randomiseAnswers', 'otherAnswer', 'sharing' ) as $option ) {
				if ( isset( $_POST[$option] ) && $_POST[$option] )
					$poll_data[$option] = 'yes';
				else
					$poll_data[$option] = 'no';
			}

			$blocks = array( 'off', 'cookie', 'cookieIP' );
			if ( isset( $_POST['blockRepeatVotersType'] ) && in_array( $_POST['blockRepeatVotersType'], $blocks ) )
				$poll_data['blockRepeatVotersType'] = $_POST['blockRepeatVotersType'];

			$results = array( 'show', 'percent', 'hide' );
			if ( isset( $_POST['resultsType'] ) && in_array( $_POST['resultsType'], $results ) )
				$poll_data['resultsType'] = $_POST['resultsType'];
			$poll_data['question'] = stripslashes( $_POST['question'] );

			if ( empty( $_POST['answer'] ) || !is_array( $_POST['answer'] ) )
				$this->errors->add( 'answer', __( 'Invalid answers' ) );

			$answers = array();
			foreach ( $_POST['answer'] as $answer_id => $answer ) {
				if ( !$answer = trim( stripslashes( $answer ) ) )
					continue;
					
				$args['text'] = wp_kses( $answer, $allowedtags );
				
				if ( is_numeric( $answer_id ) )
					$answers[] = polldaddy_poll_answer( $args, $answer_id );
				else
					$answers[] = polldaddy_poll_answer( $args );
			}

			if ( 2 > count( $answers ) )
				$this->errors->add( 'answer', __( 'You must include at least 2 answers' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$poll_data['answers'] = $answers;
			
			$poll_data['question'] = wp_kses( $poll_data['question'], $allowedtags );
			
			if ( isset ( $_POST['styleID'] ) ){
				if ( $_POST['styleID'] == 'x' ){
					$this->errors->add( 'UpdatePoll', __( 'Please choose a poll style' ) );
					return false;
				}
			}
			$poll_data['styleID'] = (int) $_POST['styleID'];

			$polldaddy->reset();

			$update_response = $polldaddy->update_poll( $poll, $poll_data );

			$this->parse_errors( $polldaddy );

			if ( !$update_response )
				$this->errors->add( 'UpdatePoll', __( 'Poll could not be updated' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$query_args['message'] = 'updated';
			if ( isset($_POST['iframe']) )
				$query_args['iframe'] = '';
			break;
		case 'create-poll' :
			if ( !$is_POST )
				return;

			check_admin_referer( 'create-poll' );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
			$polldaddy->reset();

			$answers = array();
			foreach ( $_POST['answer'] as $answer ){
				if ( !$answer = trim( stripslashes( $answer ) ) )
					continue;

				$args['text'] = wp_kses( $answer, $allowedtags );

				$answers[] = polldaddy_poll_answer( $args );
			}

			if ( !$answers )
				return false;

			$poll_data = _polldaddy_poll_defaults();
			foreach ( $poll_data as $key => $value )
				if ( isset($_POST[$key]) )
					$poll_data[$key] = stripslashes( $_POST[$key] );

			$poll_data['answers'] = $answers;
			
			$poll_data['question'] = wp_kses( $poll_data['question'], $allowedtags );
			
			if ( isset ( $_POST['styleID'] ) ){
				if ( $_POST['styleID'] == 'x' ){
			        $this->errors->add( 'UpdatePoll', __( 'Please choose a poll style' ) );
			        return false;
				}
			}
			$poll_data['styleID'] = $_POST['styleID'];
			
			$poll = $polldaddy->create_poll( $poll_data );
			$this->parse_errors( $polldaddy );

			if ( !$poll || empty( $poll->_id ) )
				$this->errors->add( 'CreatePoll', __( 'Poll could not be created' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$query_args['message'] = 'created';
			$query_args['action'] = 'edit-poll';
			$query_args['poll'] = $poll->_id;
			if ( isset($_POST['iframe']) )
				$query_args['iframe'] = '';
			break;
		case 'delete-style' :
			if ( empty( $style ) )
				return;

			if ( is_array( $style ) )
				check_admin_referer( 'action-style_bulk' );
			else
				check_admin_referer( "delete-style_$style" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );

			foreach ( (array) $_REQUEST['style'] as $style_id ) {
				$polldaddy->reset();
				$polldaddy->delete_style( $style_id );
			}

			$query_args['message'] = 'deleted-style';
			$query_args['deleted'] = count( (array) $style );
			break;
		case 'edit-style' :
			if ( !$is_POST || !$style = (int) $style )
				return;
			
			check_admin_referer( "edit-style$style" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
			$polldaddy->reset();

			$style_data = _polldaddy_style_defaults();

			if ( isset($_POST['style-title'] ) )
				$style_data['title'] = stripslashes( trim ( (string) $_POST['style-title'] ) ); 

			if ( isset($_POST['CSSXML'] ) )
				$style_data['css'] = urlencode( stripslashes( trim ( (string) $_POST['CSSXML'] ) ) );

			$update_response = $polldaddy->update_style( $style, $style_data );

			$this->parse_errors( $polldaddy );

			if ( !$update_response )
				$this->errors->add( 'UpdateStyle', __( 'Style could not be updated' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$query_args['message'] = 'updated-style';
			if ( isset($_POST['iframe']) )
				$query_args['iframe'] = '';
			break;
		case 'create-style' :
			if ( !$is_POST )
				return;
			
			check_admin_referer( 'create-style' );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
			$polldaddy->reset();

			$style_data = _polldaddy_style_defaults();

			if ( isset($_POST['style-title'] ) )
				$style_data['title'] = stripslashes( strip_tags( trim ( (string) $_POST['style-title'] ) ) ); 

			if ( isset($_POST['CSSXML'] ) )
				$style_data['css'] = urlencode( stripslashes( trim ( (string) $_POST['CSSXML'] ) ) );

			$style = $polldaddy->create_style( $style_data );
			$this->parse_errors( $polldaddy );
			
			if ( !$style || empty( $style->_id ) )
				$this->errors->add( 'CreateStyle', __( 'Style could not be created' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$query_args['message'] = 'created-style';
			$query_args['action'] = 'edit-style';
			$query_args['style'] = $style->_id;
			if ( isset($_POST['iframe']) )
				$query_args['iframe'] = '';
			break;
		default :
			return;
		endswitch;

		wp_redirect( add_query_arg( $query_args, wp_get_referer() ) );
		exit;
	}

	function management_page_load_signup() {
		switch ( $_POST['account'] ) :
		case 'import' :
			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID );
			$polldaddy->reset();
			$email = trim( stripslashes( $_POST['polldaddy_email'] ) );
			$password = trim( stripslashes( $_POST['polldaddy_password'] ) );

			if ( !is_email( $email ) )
				$this->errors->add( 'polldaddy_email', __( 'Email address required' ) );

			if ( !$password )
				$this->errors->add( 'polldaddy_password', __( 'Password required' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			if ( !$polldaddy->initiate( $email, $password, $GLOBALS['user_ID'] ) ) {
				$this->parse_errors( $polldaddy );
				$this->errors->add( 'import-account', __( 'Account could not be imported.  Are your email address and password correct?' ) );
				return false;
			}
			break;
		default :
			return;
		endswitch;
	}

	function admin_body_class( $class ) {
		if ( isset( $_GET['iframe'] ) )
			$class .= 'poll-preview-iframe ';
		if ( isset( $_GET['TB_iframe'] ) )
			$class .= 'poll-preview-iframe-editor ';
		return $class;
	}

	function management_page_notices() {
		$message = false;
		switch ( (string) @$_GET['message'] ) :
		case 'deleted' :
			$deleted = (int) $_GET['deleted'];
			if ( 1 == $deleted )
				$message = __( 'Poll deleted.' );
			else
				$message = sprintf( __ngettext( '%s Poll Deleted.', '%s Polls Deleted.', $deleted ), number_format_i18n( $deleted ) );
			break;
		case 'opened' :
			$opened = (int) $_GET['opened'];
			if ( 1 == $opened )
				$message = __( 'Poll opened.' );
			else
				$message = sprintf( __ngettext( '%s Poll Opened.', '%s Polls Opened.', $opened ), number_format_i18n( $opened ) );
			break;
		case 'closed' :
			$closed = (int) $_GET['closed'];
			if ( 1 == $closed )
				$message = __( 'Poll closed.' );
			else
				$message = sprintf( __ngettext( '%s Poll Closed.', '%s Polls Closed.', $closed ), number_format_i18n( $closed ) );
			break;
		case 'updated' :
			$message = __( 'Poll updated.' );
			break;
		case 'created' :
			$message = __( 'Poll created.' );
			if ( isset( $_GET['iframe'] ) )
				$message .= ' <input type="button" class="button polldaddy-send-to-editor" value="' . attribute_escape( __( 'Send to Editor' ) ) . '" />';
			break;
		case 'updated-style' :
			$message = __( 'Custom Style updated.' );
			break;
		case 'created-style' :
			$message = __( 'Custom Style created.' );
			break;
		case 'deleted-style' :
			$deleted = (int) $_GET['deleted'];
			if ( 1 == $deleted )
				$message = __( 'Custom Style deleted.' );
			else
				$message = sprintf( __ngettext( '%s Style Deleted.', '%s Custom Styles Deleted.', $deleted ), number_format_i18n( $deleted ) );
			break;
		endswitch;

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $is_POST ) {
			switch ( $GLOBALS['action'] ) :
			case 'create-poll' :
				$message = __( 'Error: An error has occurred;  Poll not created.' );
				break;
			case 'edit-poll' :
				$message = __( 'Error: An error has occurred;  Poll not updated.' );
				break;
			case 'account' :
				if ( 'import' == $_POST['account'] )
					$message = __( 'Error: An error has occurred;  Account could not be imported.  Perhaps your email address or password is incorrect?' );
				else
					$message = __( 'Error: An error has occurred;  Account could not be created.' );
				break;
			endswitch;
		}

		if ( !$message )
			return;
?>
		<div class='updated'><p><?php echo $message; ?></p></div>
<?php
		$this->print_errors();
	}

	function management_page() {
		global $action, $poll, $style;
		$poll = (int) $poll;
		$style = (int) $style;
		
?>

	<div class="wrap" id="manage-polls">

<?php
		switch ( $action ) :
		case 'signup' :
		case 'account' :
			$this->signup();
			break;
		case 'preview' :
?>

		<h2 id="preview-header"><?php printf( __( 'Poll Preview (<a href="%s">Edit Poll</a>, <a href="%s">List Polls</a>)' ),
			clean_url( add_query_arg( array( 'action' => 'edit', 'poll' => $poll, 'message' => false ) ) ),
			clean_url( add_query_arg( array( 'action' => false, 'poll' => false, 'message' => false ) ) )
		); ?></h2>

<?php
			echo do_shortcode( "[polldaddy poll=$poll cb=1]" );
			break;
		case 'results' :
?>

		<h2><?php printf( __( 'Poll Results (<a href="%s">Edit</a>)' ), clean_url( add_query_arg( array( 'action' => 'edit', 'poll' => $poll, 'message' => false ) ) ) ); ?></h2>

<?php
			$this->poll_results_page( $poll );
			break;
		case 'edit' :
		case 'edit-poll' :
?>

		<h2><?php printf( __('Edit Poll (<a href="%s">List Polls</a>)'), clean_url( add_query_arg( array( 'action' => false, 'poll' => false, 'message' => false ) ) ) ); ?></h2>

<?php

			$this->poll_edit_form( $poll );
			break;
		case 'create-poll' :
?>

		<h2><?php printf( __('Create Poll (<a href="%s">List Polls</a>)'), clean_url( add_query_arg( array( 'action' => false, 'poll' => false, 'message' => false ) ) ) ); ?></h2>

<?php
			$this->poll_edit_form();
			break;
		case 'list-styles' :
?>

		<h2><?php printf( __('Custom Styles (<a href="%s">Add New</a>)'), clean_url( add_query_arg( array( 
			'action' => 'create-style', 'poll' => false, 'message' => false ) ) ) ); ?></h2>

<?php
			$this->styles_table();
			break;
		case 'edit-style' :
?>

		<h2><?php printf( __('Edit Style (<a href="%s">List Styles</a>)'), clean_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?></h2>

<?php

			$this->style_edit_form( $style );
			break;
		case 'create-style' :
?>

		<h2><?php printf( __('Create Style (<a href="%s">List Styles</a>)'), clean_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?></h2>

<?php
			$this->style_edit_form();
			break;
		default :

?>

		<h2 id="poll-list-header"><?php printf( __( 'Polls (<a href="%s">Add New</a>)' ), clean_url( add_query_arg( array(
			'action' => 'create-poll',
			'poll' => false,
			'message' => false
		) ) ) ); ?></h2>

<?php 
			$this->polls_table( isset( $_GET['view'] ) && 'user' == $_GET['view'] ? 'user' : 'blog' );
		endswitch;
?>

	</div>

<?php

	}

	function polls_table( $view = 'blog' ) {
		$page = absint($_GET['paged']);
		if ( !$page )
			$page = 1;
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
		$polldaddy->reset();
		if ( 'user' == $view )
			$polls_object = $polldaddy->get_polls( ( $page - 1 ) * 10 + 1, $page * 10 );
		else
			$polls_object = $polldaddy->get_polls_by_parent_id( ( $page - 1 ) * 10 + 1, $page * 10 );
		$this->parse_errors( $polldaddy );
		$this->print_errors();
		$polls = & $polls_object->poll;
		$total_polls = $polls_object->_total;
		$class = '';

		$page_links = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'total' => ceil( $total_polls / 10 ),
			'current' => $page
		) );
?>

		<ul class="subsubsub">
			<li><a href="<?php echo clean_url( add_query_arg( array( 'view' => false, 'paged' => false ) ) ); ?>"<?php if ( 'blog' == $view ) echo ' class="current"'; ?>><?php _e( "All Blog's Polls" ); ?></a> | </li>
			<li><a href="<?php echo clean_url( add_query_arg( array( 'view' => 'user', 'paged' => false ) ) ); ?>"<?php if ( 'user' == $view ) echo ' class="current"'; ?>><?php _e( "All My Polls" ); ?></a></li>
		</ul>
		<form method="post" action="">
		<div class="tablenav">
			<div class="alignleft">
				<select name="action">
					<option selected="selected" value=""><?php _e( 'Actions' ); ?></option>
					<option value="delete"><?php _e( 'Delete' ); ?></option>
					<option value="close"><?php _e( 'Close' ); ?></option>
					<option value="open"><?php _e( 'Open' ); ?></option>
				</select>
				<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply' ); ?>" />
				<?php wp_nonce_field( 'action-poll_bulk' ); ?>
			</div>
			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>
		<br class="clear" />
		<table class="widefat">
			<thead>
				<tr>
					<th id="cb" class="manage-column column-cb check-column" scope="col" /><input type="checkbox" /></th>
					<th id="title" class="manage-column column-title" scope="col">Poll</th>
					<th id="votes" class="manage-column column-vote" scope="col">Votes</th>
					<th id="date" class="manage-column column-date" scope="col">Created</th>
				</tr>
			</thead>
			<tbody>

<?php
		if ( $polls ) :
			foreach ( $polls as $poll ) :
				$poll_id = (int) $poll->_id;
				
				$poll->___content = trim( strip_tags( $poll->___content ) );
                if( strlen( $poll->___content ) == 0 ){
                	$poll->___content = '-- empty HTML tag --';
                }				
				
				$poll_closed = (int) $poll->_closed;

				if ( $this->can_edit( $poll ) )
					$edit_link = clean_url( add_query_arg( array( 'action' => 'edit', 'poll' => $poll_id, 'message' => false ) ) );
				else
					$edit_link = false;

				$class = $class ? '' : ' class="alternate"';
				$results_link = clean_url( add_query_arg( array( 'action' => 'results', 'poll' => $poll_id, 'message' => false ) ) );
				$delete_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'poll' => $poll_id, 'message' => false ) ), "delete-poll_$poll_id" ) );
				$open_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'open', 'poll' => $poll_id, 'message' => false ) ), "open-poll_$poll_id" ) );
				$close_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'close', 'poll' => $poll_id, 'message' => false ) ), "close-poll_$poll_id" ) );
				$preview_link = clean_url( add_query_arg( array( 'action' => 'preview', 'poll' => $poll_id, 'message' => false ) ) ); //, 'iframe' => '', 'TB_iframe' => 'true' ) ) );
				list($poll_time) = explode( '.', $poll->_created );
				$poll_time = strtotime( $poll_time );
?>

				<tr<?php echo $class; ?>>
					<th class="check-column" scope="row"><input type="checkbox" value="<?php echo (int) $poll_id; ?>" name="poll[]" /></th>
					<td class="post-title column-title">
<?php					if ( $edit_link ) : ?>
						<strong><a class="row-title" href="<?php echo $edit_link; ?>"><?php echo wp_specialchars( $poll->___content ); ?></a></strong>
						<span class="edit"><a href="<?php echo $edit_link; ?>"><?php _e( 'Edit' ); ?></a> | </span>
<?php					else : ?>
						<strong><?php echo wp_specialchars( $poll->___content ); ?></strong>
<?php					endif; ?>

						<span class="results"><a href="<?php echo $results_link; ?>"><?php _e( 'Results' ); ?></a> | </span>
						<span class="delete"><a class="delete-poll delete" href="<?php echo $delete_link; ?>"><?php _e( 'Delete' ); ?></a> | </span>
<?php if ( $poll_closed == 2 ) : ?>
						<span class="open"><a class="open-poll" href="<?php echo $open_link; ?>"><?php _e( 'Open' ); ?></a> | </span>	
<?php else : ?>
						<span class="close"><a class="close-poll" href="<?php echo $close_link; ?>"><?php _e( 'Close' ); ?></a> | </span>
<?php endif; ?>
<?php if ( isset( $_GET['iframe'] ) ) : ?>
						<span class="view"><a href="<?php echo $preview_link; ?>"><?php _e( 'Preview' ); ?></a> | </span>
						<span class="editor">
							<a href="#" class="polldaddy-send-to-editor"><?php _e( 'Send to editor' ); ?></a>
							<input type="hidden" class="polldaddy-poll-id hack" value="<?php echo (int) $poll_id; ?>" /> |
						</span>
<?php else : ?>
						<span class="view"><a class="thickbox" href="<?php echo $preview_link; ?>"><?php _e( 'Preview' ); ?></a> | </span>
<?php endif; ?>
						<span class="shortcode"><a href="#" class="polldaddy-show-shortcode"><?php _e( 'HTML code' ); ?></a></span>
					</td>
					<td class="poll-votes column-vote"><?php echo number_format_i18n( $poll->_responses ); ?></td>
					<td class="date column-date"><abbr title="<?php echo date( __('Y/m/d g:i:s A'), $poll_time ); ?>"><?php echo date( __('Y/m/d'), $poll_time ); ?></abbr></td>
				</tr>
				<tr class="polldaddy-shortcode-row" style="display: none;">
					<td colspan="4">
						<h4><?php _e( 'Shortcode' ); ?></h4>
						<pre>[polldaddy poll=<?php echo (int) $poll_id; ?>]</pre>

						<h4><?php _e( 'JavaScript' ); ?></h4>
						<pre>&lt;script type="text/javascript" language="javascript"
  src="http://static.polldaddy.com/p/<?php echo (int) $poll_id; ?>.js"&gt;&lt;/script&gt;
&lt;noscript&gt;
 &lt;a href="http://answers.polldaddy.com/poll/<?php echo (int) $poll_id; ?>/"&gt;<?php echo trim( strip_tags( $poll->___content ) ); ?>&lt;/a&gt;&lt;br/&gt;
 &lt;span style="font:9px;"&gt;(&lt;a href="http://www.polldaddy.com"&gt;polls&lt;/a&gt;)&lt;/span&gt;
&lt;/noscript&gt;</pre>
					</td>
				</tr>

<?php
			endforeach;
		elseif ( $total_polls ) : // $polls
?>

				<tr>
					<td colspan="4"><?php printf( __( 'What are you doing here?  <a href="%s">Go back</a>.' ), clean_url( add_query_arg( 'paged', false ) ) ); ?></td>
				</tr>

<?php
		else : // $polls
?>

				<tr>
					<td colspan="4"><?php printf( __( 'No polls yet.  <a href="%s">Create one</a>' ), clean_url( add_query_arg( array( 'action' => 'create-poll' ) ) ) ); ?></td>
				</tr>
<?php		endif; // $polls ?>

			</tbody>
		</table>
		</form>
		<div class="tablenav">
			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>
		<br class="clear" />

<?php
	}

	function poll_edit_form( $poll_id = 0 ) {
		$poll_id = (int) $poll_id;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
		$polldaddy->reset();

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $poll_id ) {
			$poll = $polldaddy->get_poll( $poll_id );
			$this->parse_errors( $polldaddy );

			if ( !$this->can_edit( $poll ) ) {
				$this->errors->add( 'permission', __( 'You are not allowed to edit this poll.' ) );
			}
		} else {
			$poll = polldaddy_poll( array(), null, false );
		}

		$question = $is_POST ? attribute_escape( stripslashes( $_POST['question'] ) ) : attribute_escape( $poll->question );

		$this->print_errors();
?>

<form action="" method="post">
<div id="poststuff"><div id="post-body" class="has-sidebar has-right-sidebar">

<div class="inner-sidebar" id="side-info-column">
	<div id="submitdiv" class="postbox">
		<h3><?php _e( 'Publish' ); ?></h3>
		<div class="inside">
			<div id="major-publishing-actions">
				<p id="publishing-action">
					<?php wp_nonce_field( $poll_id ? "edit-poll_$poll_id" : 'create-poll' ); ?>
					<input type="hidden" name="action" value="<?php echo $poll_id ? 'edit-poll' : 'create-poll'; ?>" />
					<input type="hidden" class="polldaddy-poll-id" name="poll" value="<?php echo $poll_id; ?>" />
					<input type="submit" class="button-primary" value="<?php echo attribute_escape( __( 'Save Poll' ) ); ?>" />

<?php if ( isset( $_GET['iframe'] ) && $poll_id ) : ?>

					<input type="button" class="button polldaddy-send-to-editor" value="<?php echo attribute_escape( __( 'Send to Editor' ) ); ?>" />

<?php endif; ?>

				</p>
				<br class="clear" />
			</div>
		</div>
	</div>

	<div class="postbox">
		<h3><?php _e( 'Poll results' ); ?></h3>
		<div class="inside">
			<ul class="poll-options">

<?php
			foreach ( array( 'show' => __( 'Show results to voters' ), 'percent' => __( 'Only show percentages' ), 'hide' => __( 'Hide all results' ) ) as $value => $label ) :
				if ( $is_POST )
					$checked = $value === $_POST['resultsType'] ? ' checked="checked"' : '';
				else
					$checked = $value === $poll->resultsType ? ' checked="checked"' : '';
?>

				<li>
				<label for="resultsType-<?php echo $value; ?>"><input type="radio"<?php echo $checked; ?> value="<?php echo $value; ?>" name="resultsType" id="resultsType-<?php echo $value; ?>" /> <?php echo wp_specialchars( $label ); ?></label>
				</li>

<?php			endforeach; ?>

			</ul>
		</div>
	</div>

	<div class="postbox">
		<h3><?php _e( 'Block repeat voters' ); ?></h3>
		<div class="inside">
			<ul class="poll-options">

<?php
			foreach ( array( 'off' => __( "Don't block repeat voters" ), 'cookie' => __( 'Block by cookie (recommended)' ), 'cookieIP' => __( 'Block by cookie and by IP address' ) ) as $value => $label ) :
				if ( $is_POST )
					$checked = $value === $_POST['blockRepeatVotersType'] ? ' checked="checked"' : '';
				else
					$checked = $value === $poll->blockRepeatVotersType ? ' checked="checked"' : '';
?>

				<li>
					<label for="blockRepeatVotersType-<?php echo $value; ?>"><input type="radio"<?php echo $checked; ?> value="<?php echo $value; ?>" name="blockRepeatVotersType" id="blockRepeatVotersType-<?php echo $value; ?>" /> <?php echo wp_specialchars( $label ); ?></label>
				</li>

<?php			endforeach; ?>

			</ul>
			<p>Note: Blocking by cookie and IP address can be problematic for some voters.</p>
		</div>
	</div>
</div>


<div id="post-body-content" class="has-sidebar-content">

	<div id="titlediv">
		<div id="titlewrap">
			<input type="text" autocomplete="off" id="title" value="<?php echo $question; ?>" tabindex="1" size="30" name="question" />
		</div>
	</div>

	<div id="answersdiv" class="postbox">
		<h3><?php _e( 'Answers' ); ?></h3>

		<div id="answerswrap" class="inside">
		<ul id="answers">
<?php
		$a = 0;
		$answers = array();
		if ( $is_POST && $_POST['answer'] ) {
			foreach( $_POST['answer'] as $answer_id => $answer )
				$answers[attribute_escape($answer_id)] = attribute_escape( stripslashes($answer) );
		} elseif ( isset( $poll->answers->answer ) ) {
			foreach ( $poll->answers->answer as $answer )
				$answers[(int) $answer->_id] = attribute_escape( $answer->text );
		}

		foreach ( $answers as $answer_id => $answer ) :
			$a++;
			$delete_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete-answer', 'poll' => $poll_id, 'answer' => $answer_id, 'message' => false ) ), "delete-answer_$answer_id" ) );
?>

			<li>
				<span class="handle" title="<?php echo attribute_escape( 'click and drag to move' ); ?>">&#x2195;</span>
				<div><input type="text" autocomplete="off" id="answer-<?php echo $answer_id; ?>" value="<?php echo $answer; ?>" tabindex="2" size="30" name="answer[<?php echo $answer_id; ?>]" /></div>
				<a href="<?php echo $delete_link; ?>" class="delete-answer delete" title="<?php echo attribute_escape( 'delete this answer' ); ?>">&times;</a>
			</li>

<?php
		endforeach;

		while ( 3 - $a > 0 ) :
			$a++;
?>

			<li>
				<span class="handle" title="<?php echo attribute_escape( 'click and drag to move' ); ?>">&#x2195;</span>
				<div><input type="text" autocomplete="off" value="" tabindex="2" size="30" name="answer[new<?php echo $a; ?>]" /></div>
				<a href="#" class="delete-answer delete" title="<?php echo attribute_escape( 'delete this answer' ); ?>">&times;</a>
			</li>

<?php
		endwhile;
?>

		</ul>

		<p id="add-answer-holder">
			<button class="button"><?php echo wp_specialchars( __( 'Add another' ) ); ?></button>
		</p>

		<ul id="answer-options">

<?php
		foreach ( array( 'multipleChoice' => __( 'Multiple choice' ), 'randomiseAnswers' => __( 'Randomize answer order' ), 'otherAnswer' => __( 'Allow other answers' ), 'sharing' => __( "'Share This' link" ) ) as $option => $label ) :
			if ( $is_POST )
				$checked = 'yes' === $_POST[$option] ? ' checked="checked"' : '';
			else
				$checked = 'yes' === $poll->$option ? ' checked="checked"' : '';
?>

			<li>
				<label for="<?php echo $option; ?>"><input type="checkbox"<?php echo $checked; ?> value="yes" id="<?php echo $option; ?>" name="<?php echo $option; ?>" /> <?php echo wp_specialchars( $label ); ?></label>
			</li>

<?php		endforeach; ?>

		</ul>
		</div>
	</div>

	<div id="design" class="postbox">

<?php	$style_ID = (int) ( $is_POST ? $_POST['styleID'] : $poll->styleID );	

		$iframe_view = false;
		if ( isset($_GET['iframe']) )
			$iframe_view = true;
			
		$polldaddy->reset();
		$styles = $polldaddy->get_styles();

		$show_custom = false;
		if( isset( $styles ) && count( $styles ) > 0 ){
			$show_custom = true;
		}			

		if ( $style_ID > 18 ){
			$standard_style_ID = 0;
			$custom_style_ID = $style_ID;
		}
		else{
			$standard_style_ID = $style_ID;
			$custom_style_ID = 0;
		}
?>

		<h3><?php _e( 'Design' ); ?></h3>
		<input type="hidden" name="styleID" id="styleID" value="<?php echo $style_ID ?>">
		<div class="inside">
			<?php if ( $iframe_view ){ ?>
			<div id="design_standard" style="padding:0px;">
				<div class="hide-if-no-js">
					<table class="pollStyle">
						<thead>
							<tr>
								<th>
									<div style="display:none;">
										<input type="radio" name="styleTypeCB" id="regular" onclick="javascript:pd_build_styles( 0 );"/>
									</div>
								</th>
							</tr>
						</thead>
						<tr>
							<td class="selector">
								<table class="st_selector">
									<tr>
										<td class="dir_left">
											<a href="javascript:pd_move('prev');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#171;</a>
										</td>
										<td class="img"><div class="st_image_loader"><div id="st_image" onmouseover="st_results(this, 'show');" onmouseout="st_results(this, 'hide');"></div></div></td>
										<td class="dir_right">
											<a href="javascript:pd_move('next');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#187;</a>
										</td>
									</tr>
									<tr>
										<td></td>
										<td class="counter">
											<div id="st_number"></div>
										</td>
										<td></td>
									</tr>
									<tr>
										<td></td>
										<td class="title">
											<div id="st_name"></div>
										</td>
										<td></td>
									</tr>
									<tr>
										<td></td>
										<td>
											<div id="st_sizes"></div>
										</td>
										<td></td>
									</tr>
									<tr>
										<td colspan="3">
											<div id="st_description"></div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>

				<p class="hide-if-js" id="no-js-styleID">
					<select name="styleID">

				<?php 	foreach ( $options as $styleID => $label ) :
						$selected = $styleID == $standard_style_ID ? ' selected="selected"' : ''; ?>
						<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo wp_specialchars( $label ); ?></option>
				<?php 	endforeach; ?>

					</select>
				</p>				
			</div>
			<?php if ( $show_custom ){ ?>
			<div id="design_custom">
				<p class="hide-if-no-js">
					<table class="pollStyle">
						<thead>
							<tr>
								<th>
									<div style="display:none;">
										<?php $disabled = $show_custom == false ? ' disabled="true"' : ''; ?>
										<input type="radio" name="styleTypeCB" id="custom" onclick="javascript:pd_change_style($('customSelect').value);" <?php echo $disabled; ?>></input>
										<label onclick="javascript:pd_change_style($('customSelect').value);">Custom Style</label>
									</div>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="customSelect">
									<table>
										<tr>
											<td><?php $hide = $show_custom == true ? ' style="display:block;"' : ' style="display:none;"'; ?>
											<select id="customSelect" name="customSelect" onclick="pd_change_style(this.value);" <?php echo $hide ?>>
												<?php 	$selected = $custom_style_ID == 0 ? ' selected="selected"' : ''; ?>
														<option value="x"<?php echo $selected; ?>>Please choose a custom style...</option>
												<?php 	foreach ( $styles->style as $style ) :
														$selected = $style->_id == $custom_style_ID ? ' selected="selected"' : ''; ?>
														<option value="<?php echo (int) $style->_id; ?>"<?php echo $selected; ?>><?php echo wp_specialchars( $style->title ); ?></option>
												<?php	endforeach;?>
											</select>
											<div id="styleIDErr" class="formErr" style="display:none;">Please choose a style.</div></td>
										</tr>
										<tr>
											<td><?php $extra = $show_custom == false ? 'You currently have no custom styles created.' : ''; ?>
												<p><?php echo $extra ?></p>
												<p>Did you know we have a new editor for building your own custom poll styles? Find out more <a href="http://support.polldaddy.com/custom-poll-styles/" target="_blank">here</a>.</p>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
				</p>
			</div>
			<div id="design_options">
				<a href="#" class="polldaddy-show-design-options"><?php _e( 'Custom Styles' ); ?></a>
			</div>
			<?php }}else{?>
				<div class="design_standard">
					<div class="hide-if-no-js">
					<table class="pollStyle">
						<thead>
							<tr>
								<th class="cb">
									<input type="radio" name="styleTypeCB" id="regular" onclick="javascript:pd_build_styles( 0 );"/>
								</th>
								<th>
									<label for="skin" onclick="javascript:pd_build_styles( 0 );">PollDaddy Style</label>
								</th>
								<th/>
								<th class="cb">
									<?php $disabled = $show_custom == false ? ' disabled="true"' : ''; ?>
									<input type="radio" name="styleTypeCB" id="custom" onclick="javascript:pd_change_style($('customSelect').value);" <?php echo $disabled; ?>></input>
								</th>
								<th>
									<label onclick="javascript:pd_change_style($('customSelect').value);">Custom Style</label>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td/>
								<td class="selector">
									<table class="st_selector">
										<tr>
											<td class="dir_left">
												<a href="javascript:pd_move('prev');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#171;</a>
											</td>
											<td class="img"><div class="st_image_loader"><div id="st_image" onmouseover="st_results(this, 'show');" onmouseout="st_results(this, 'hide');"></div></div></td>
											<td class="dir_right">
												<a href="javascript:pd_move('next');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#187;</a>
											</td>
										</tr>
										<tr>
											<td></td>
											<td class="counter">
												<div id="st_number"></div>
											</td>
											<td></td>
										</tr>
										<tr>
											<td></td>
											<td class="title">
												<div id="st_name"></div>
											</td>
											<td></td>
										</tr>
										<tr>
											<td></td>
											<td>
												<div id="st_sizes"></div>
											</td>
											<td></td>
										</tr>
										<tr>
											<td colspan="3">
												<div id="st_description"></div>
											</td>
										</tr>
									</table>
								</td>
								<td width="100"></td>
								<td/>
								<td class="customSelect">
									<table>
										<tr>
											<td><?php $hide = $show_custom == true ? ' style="display:block;"' : ' style="display:none;"'; ?>
											<select id="customSelect" name="customSelect" onclick="pd_change_style(this.value);" <?php echo $hide ?>>
												<?php 	$selected = $custom_style_ID == 0 ? ' selected="selected"' : ''; ?>
														<option value="x"<?php echo $selected; ?>>Please choose a custom style...</option>
												<?php 	foreach ( $styles->style as $style ) :
														$selected = $style->_id == $custom_style_ID ? ' selected="selected"' : ''; ?>
														<option value="<?php echo (int) $style->_id; ?>"<?php echo $selected; ?>><?php echo wp_specialchars( $style->title ); ?></option>
												<?php	endforeach;?>
											</select>
											<div id="styleIDErr" class="formErr" style="display:none;">Please choose a style.</div></td>
										</tr>
										<tr>
											<td><?php $extra = $show_custom == false ? 'You currently have no custom styles created.' : ''; ?>
												<p><?php echo $extra ?></p>
												<p>Did you know we have a new editor for building your own custom poll styles? Find out more <a href="http://support.polldaddy.com/custom-poll-styles/" target="_blank">here</a>.</p>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</tbody>
					</table>
					</div>
					<p class="hide-if-js" id="no-js-styleID">
						<select name="styleID">

					<?php 	foreach ( $options as $styleID => $label ) :
							$selected = $styleID == $standard_style_ID ? ' selected="selected"' : ''; ?>
							<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo wp_specialchars( $label ); ?></option>
					<?php 	endforeach; ?>

						</select>
					</p>
				</div>	
			<?php } ?>
			<script language="javascript">
			current_pos = 0;
			pd_build_styles( current_pos );
			<?php if( $style_ID > 0 && $style_ID <= 1000 ){ ?>
			pd_pick_style( <?php echo $style_ID ?> );
			<?php }else{ ?>
			pd_change_style( <?php echo $style_ID ?> );
			<?php } ?>
			</script>
		</div>
	
	</div>

</div>
</div></div>
</form>
<br class="clear" />

<?php
	}

	function poll_results_page( $poll_id ) {
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
		$polldaddy->reset();

		$results = $polldaddy->get_poll_results( $poll_id );
?>

		<table class="poll-results widefat">
			<thead>
				<tr>
					<th scope="col" class="column-title"><?php _e( 'Answer' ); ?></th>
					<th scope="col" class="column-vote"><?php _e( 'Votes' ); ?></th>
				</tr>
			</thead>
			<tbody>

<?php
		$class = '';
		foreach ( $results->answers as $answer ) :
			$answer->___content = trim( strip_tags( $answer->___content ) );
            if( strlen( $answer->___content ) == 0 ){
            	$answer->___content = '-- empty HTML tag --';
            }
			
			$class = $class ? '' : ' class="alternate"';
			$content = $results->others && 'Other answer...' === $answer->___content ? sprintf( __( 'Other (<a href="%s">see below</a>)' ), '#other-answers-results' ) : wp_specialchars( $answer->___content );

?>

				<tr<?php echo $class; ?>>
					<th scope="row" class="column-title"><?php echo $content; ?></th>
					<td class="column-vote">
						<div class="result-holder">
							<span class="result-bar" style="width: <?php echo number_format( $answer->_percent, 2 ); ?>%;">&nbsp;</span>
							<span class="result-total alignleft"><?php echo number_format_i18n( $answer->_total ); ?></span>
							<span class="result-percent alignright"><?php echo number_format_i18n( $answer->_percent ); ?>%</span>
						</div>
					</td>
				</tr>
<?php
		endforeach;
?>

			</tbody>
		</table>

<?php

		if ( !$results->others )
			return;
?>

		<table id="other-answers-results" class="poll-others widefat">
			<thead>
				<tr>
					<th scope="col" class="column-title"><?php _e( 'Other Answer' ); ?></th>
					<th scope="col" class="column-vote"><?php _e( 'Votes' ); ?></th>
				</tr>
			</thead>
			<tbody>

<?php
		$class = '';
		$others = array_count_values( $results->others );
		arsort( $others );
		foreach ( $others as $other => $freq ) :
			$class = $class ? '' : ' class="alternate"';
?>

				<tr<?php echo $class; ?>>
					<th scope="row" class="column-title"><?php echo wp_specialchars( $other ); ?></th>
					<td class="column-vote"><?php echo number_format_i18n( $freq ); ?></td>
				</tr>
<?php
		endforeach;
?>

			</tbody>
		</table>

<?php
	}
	
	function styles_table() {
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
		$polldaddy->reset();
		
		$styles_object = $polldaddy->get_styles();
			
		$this->parse_errors( $polldaddy );
		$this->print_errors();
		$styles = & $styles_object->style;
		$class = '';
?>

		<form method="post" action="">
		<div class="tablenav">
			<div class="alignleft">
				<select name="action">
					<option selected="selected" value=""><?php _e( 'Actions' ); ?></option>
					<option value="delete-style"><?php _e( 'Delete' ); ?></option>
				</select>
				<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply' ); ?>" />
				<?php wp_nonce_field( 'action-style_bulk' ); ?>
			</div>
			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>
		<br class="clear" />
		<table class="widefat">
			<thead>
				<tr>
					<th id="cb" class="manage-column column-cb check-column" scope="col" /><input type="checkbox" /></th>
					<th id="title" class="manage-column column-title" scope="col">Style</th>
					<th id="date" class="manage-column column-date" scope="col">Last Modified</th>
				</tr>
			</thead>
			<tbody>

<?php
		if ( $styles ) :
			foreach ( $styles as $style ) :
				if( (int) $style->_type == 1 ):
					$style_id = (int) $style->_id;			

					$class = $class ? '' : ' class="alternate"';
					$edit_link = clean_url( add_query_arg( array( 'action' => 'edit-style', 'style' => $style_id, 'message' => false ) ) );
					$delete_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete-style', 'style' => $style_id, 'message' => false ) ), "delete-style_$style_id" ) );
					list($style_time) = explode( '.', $style->date );
					$style_time = strtotime( $style_time );
	?>

					<tr<?php echo $class; ?>>
						<th class="check-column" scope="row"><input type="checkbox" value="<?php echo (int) $style_id; ?>" name="style[]" /></th>
						<td class="post-title column-title">
	<?php					if ( $edit_link ) : ?>
							<strong><a class="row-title" href="<?php echo $edit_link; ?>"><?php echo wp_specialchars( $style->title ); ?></a></strong>
							<span class="edit"><a href="<?php echo $edit_link; ?>"><?php _e( 'Edit' ); ?></a> | </span>
	<?php					else : ?>
							<strong><?php echo wp_specialchars( $style->title ); ?></strong>
	<?php					endif; ?>

							<span class="delete"><a class="delete-poll delete" href="<?php echo $delete_link; ?>"><?php _e( 'Delete' ); ?></a></span>
						</td>
						<td class="date column-date"><abbr title="<?php echo date( __('Y/m/d g:i:s A'), $style_time ); ?>"><?php echo date( __('Y/m/d'), $style_time ); ?></abbr></td>
					</tr>

	<?php
				endif;
			endforeach;
		else : // $styles
?>

				<tr>
					<td colspan="4"><?php printf( __( 'No custom styles yet.  <a href="%s">Create one</a>' ), clean_url( add_query_arg( array( 'action' => 'create-style' ) ) ) ); ?></td>
				</tr>
<?php		endif; // $styles ?>

			</tbody>
		</table>
		</form>
		<div class="tablenav">
			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>
		<br class="clear" />

<?php
	}
		
	function style_edit_form( $style_id = 105 ) {		
		$style_id = (int) $style_id;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
		$polldaddy->reset();

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );
		
		if ( $style_id ) {
			$style = $polldaddy->get_style( $style_id );
			$this->parse_errors( $polldaddy );
		} else {
			$style = polldaddy_style( array(), null, false );
		}

		$style->css = trim( urldecode( $style->css ) );

		if ( $start = stripos( $style->css, '<data>' ) ){
			$style->css = substr( $style->css, $start );
		}

		$style->css = addslashes( $style->css );

		$preload_style_id = 0;
		$preload_style = null;

		if ( isset ( $_REQUEST['preload'] ) )
		{
			$preload_style_id = (int) $_REQUEST['preload'];

			if ( $preload_style_id > 1000 || $preload_style_id < 100 )
			{
				$preload_style_id = 0;
			}
			
			if ( $preload_style_id > 0 ) {
				$polldaddy->reset();
				$preload_style = $polldaddy->get_style( $preload_style_id );
				$this->parse_errors( $polldaddy );
			}
			
			$preload_style->css = trim( urldecode( $preload_style->css ) );

			if ( $start = stripos( $preload_style->css, '<data>' ) ){
				$preload_style->css = substr( $preload_style->css, $start );
			}

			$style->css = addslashes( $preload_style->css );
		}
		
		$this->print_errors();
		
		echo '<script language="javascript">var CSSXMLString = "' . $style->css .'";</script>';
	?>

	<form action="" method="post">
	<div id="poststuff">
		<div id="post-body">
			<br/>
			<table width="100%">
				<tr>
					<td colspan="2">
						<table width="100%">
							<tr>
								<td valign="middle" width="8%">
									<label class="CSSE_title_label"><?php _e( 'Style Name' ); ?></label>
								</td>
								<td>
									<div id="titlediv" style="margin:0px;">
										<div id="titlewrap">
											<input type="text" autocomplete="off" id="title" value="<?php echo $style_id > 1000 ? $style->title : ''; ?>" tabindex="1" size="30" name="style-title"></input>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td width="13%">
						<label class="CSSE_title_label"><?php _e( 'Preload Basic Style' ); ?></label>
					</td>
					<td>
						<div class="CSSE_preload">				
							<select id="preload_value">
								<option value="0"></option>
								<option value="102">Aluminum</option>
								<option value="105">Plain White</option>
								<option value="108">Plain Black</option>
								<option value="111">Paper</option>
								<option value="114">Skull Dark</option>
								<option value="117">Skull Light</option>
								<option value="157">Micro</option>
							</select>
							<a tabindex="4" id="style-preload" href="javascript:preload_pd_style();" class="button"><?php echo attribute_escape( __( 'Load Style' ) ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<td width="13%">
						<p>Choose a part to edit...</p>
					</td>
					<td>
						<select id="styleName" onchange="renderStyleEdit(this.value);">
							<option value="pds-box" selected="selected">Poll Box</option>
							<option value="pds-question-top">Question</option>
							<option value="pds-answer-group">Answer Group</option>
							<option value="pds-answer-input">Answer Check</option>
							<option value="pds-answer">Answers</option>
							<option value="pds-textfield">Other Input</option>
							<option value="pds-vote-button">Vote Button</option>
							<option value="pds-link">Links</option>											
							<option value="pds-answer-feedback">Result Background</option>
							<option value="pds-answer-feedback-bar">Result Bar</option>
							<option value="pds-totalvotes-inner">Total Votes</option>
						</select>
					</td>
				</tr>
			</table>
			<table width="100%">
				<tr>
					<td valign="top">
						<table class="CSSE_main">
							<tr>
								<td class="CSSE_main_l" valign="top">
									<div class="off" id="D_Font">
										<a href="javascript:CSSE_changeView('Font');" id="A_Font" class="Aoff">Font</a>
									</div>
									<div class="on" id="D_Background">
										<a href="javascript:CSSE_changeView('Background');" id="A_Background" class="Aon">Background</a>
									</div>
									<div class="off" id="D_Border">
										<a href="javascript:CSSE_changeView('Border');" id="A_Border" class="Aoff">Border</a>
									</div>
									<div class="off" id="D_Margin">
										<a href="javascript:CSSE_changeView('Margin');" id="A_Margin" class="Aoff">Margin</a>
									</div>
									<div class="off" id="D_Padding">
										<a href="javascript:CSSE_changeView('Padding');" id="A_Padding" class="Aoff">Padding</a>
									</div>
									<div class="off" id="D_Scale">
										<a href="javascript:CSSE_changeView('Scale');" id="A_Scale" class="Aoff">Width</a>
									</div>
									<div class="off" id="D_Height">
										<a href="javascript:CSSE_changeView('Height');" id="A_Height" class="Aoff">Height</a>
									</div>
								</td>
								<td class="CSSE_main_r" valign="top">
									<table class="CSSE_sub">
										<tr>
											<td class="top"/>
										</tr>
										<tr>
											<td class="mid">
	<!-- Font Table -->
												<table class="CSSE_edit" id="editFont" style="display:none;">
													<tr>
														<td width="85">Font Size:</td>
														<td>
															<select id="font-size" onchange="bind(this);">
																<option value="6px">6px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="18px">18px</option>
																<option value="20px">20px</option>
																<option value="24px">24px</option>
																<option value="30px">30px</option>
																<option value="36px">36px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Font Size</td>
														<td>
															<select id="font-family" onchange="bind(this);">
																<option value="Arial">Arial</option>
																<option value="Comic Sans MS">Comic Sans MS</option>
																<option value="Courier">Courier</option>
																<option value="Georgia">Georgia</option>
																<option value="Lucida Grande">Lucida Grande</option>
																<option value="Trebuchet MS">Trebuchet MS</option>
																<option value="Times">Times</option>
																<option value="Verdana">Verdana</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Color (#hex):</td>
														<td>
															<input type="text" maxlength="11" id="color" class="elmColor jscolor-picker" onblur="bind(this);" style="float:left;"/>
														</td>
													</tr>
													<tr>
														<td>Bold:</td>
														<td>
															<input type="checkbox" id="font-weight" value="bold" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td>Italic:</td>
														<td>
															<input type="checkbox" id="font-style" value="italic" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td>Underline:</td>
														<td>
															<input type="checkbox" id="text-decoration" value="underline" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td>Line Height:</td>
														<td>
															<select id="line-height" onchange="bind(this);">
																<option value="6px">6px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="18px">18px</option>
																<option value="20px">20px</option>
																<option value="24px">24px</option>
																<option value="30px">30px</option>
																<option value="36px">36px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Align:</td>
														<td>
															<select id="text-align" onchange="bind(this);">
																<option value="left">Left</option>
																<option value="center">Center</option>
																<option value="right">Right</option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Background Table -->
												<table class="CSSE_edit" id="editBackground" style="display:none;">
													<tr>
														<td width="85">Color (#hex):</td>
														<td>
															<input type="text" maxlength="11" id="background-color" class="elmColor jscolor-picker" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td>Image URL: <a class="noteLink" title="Click here for more information" onclick="showNote('noteImageURL',this, 'Image URL');">(?)</a></td>
														<td>
															<input type="text" id="background-image" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td>Image Repeat:</td>
														<td>
															<select id="background-repeat" onchange="bind(this);">
																<option value="repeat">repeat</option>
																<option value="no-repeat">no-repeat</option>
																<option value="repeat-x">repeat-x</option>
																<option value="repeat-y">repeat-y</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Image Position:</td>
														<td>
															<select id="background-position" onchange="bind(this);">
																<option value="left top">left top</option>
																<option value="left center">left center</option>
																<option value="left bottom">left bottom</option>
																<option value="center top">center top</option>
																<option value="center center">center center</option>
																<option value="center bottom">center bottom</option>
																<option value="right top">right top</option>
																<option value="right center">right center</option>
																<option value="right bottom">right bottom</option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Border Table -->
												<table class="CSSE_edit" id="editBorder" style="display:none;">
													<tr>
														<td width="85">Width:</td>
														<td>
															<select id="border-width" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Style:</td>
														<td>
															<select id="border-style" onchange="bind(this);">
																<option value="none">none</option>
																<option value="solid">solid</option>
																<option value="dotted">dotted</option>
																<option value="dashed">dashed</option>
																<option value="double">double</option>
																<option value="groove">groove</option>
																<option value="inset">inset</option>
																<option value="outset">outset</option>
																<option value="ridge">ridge</option>
																<option value="hidden">hidden</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Color (#hex):</td>
														<td>
															<input type="text" maxlength="11" class="elmColor jscolor-picker" id="border-color" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td width="85">Rounded Corners:</td>
														<td>
															<select id="border-radius" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
															<br/>
															Not supported in Internet Explorer.
														</td>
													</tr>
												</table>
	<!-- Margin Table -->
												<table class="CSSE_edit" id="editMargin" style="display:none;">
													<tr>
														<td width="85">Top: </td>
														<td>
															<select id="margin-top" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Right:</td>
														<td>
															<select id="margin-right" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Bottom:</td>
														<td>
															<select id="margin-bottom" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Left:</td>
														<td>
															<select id="margin-left" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Padding Table -->
												<table class="CSSE_edit" id="editPadding" style="display:none;">
													<tr>
														<td width="85">Top:</td>
														<td>
															<select id="padding-top" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Right:</td>
														<td>
															<select id="padding-right" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Bottom:</td>
														<td>
															<select id="padding-bottom" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
													<tr>
														<td>Left:</td>
														<td>
															<select id="padding-left" onchange="bind(this);">
																<option value="0px">0px</option>
																<option value="1px">1px</option>
																<option value="2px">2px</option>
																<option value="3px">3px</option>
																<option value="4px">4px</option>
																<option value="5px">5px</option>
																<option value="6px">6px</option>
																<option value="7px">7px</option>
																<option value="8px">8px</option>
																<option value="9px">9px</option>
																<option value="10px">10px</option>
																<option value="11px">11px</option>
																<option value="12px">12px</option>
																<option value="13px">13px</option>
																<option value="14px">14px</option>
																<option value="15px">15px</option>
																<option value="16px">16px</option>
																<option value="17px">17px</option>
																<option value="18px">18px</option>
																<option value="19px">19px</option>
																<option value="20px">20px</option>
																<option value="21px">21px</option>
																<option value="22px">22px</option>
																<option value="23px">23px</option>
																<option value="24px">24px</option>
																<option value="25px">25px</option>
																<option value="26px">26px</option>
																<option value="27px">27px</option>
																<option value="28px">28px</option>
																<option value="29px">29px</option>
																<option value="30px">30px</option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Scale Table -->
												<table class="CSSE_edit" id="editScale" style="display:none;">
													<tr>
														<td width="85">Width (px):  <a class="noteLink" title="Click here for more information" onclick="showNote('noteWidth',this, 'Width');">(?)</a></td>
														<td>
															<input type="text" maxlength="4" class="elmColor" id="width" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td width="85"></td>
														<td>
															If you change the width of the<br/> poll you may also need to change<br/> the width of your answers.
														</td>
													</tr>
												</table>

	<!-- Height Table -->
												<table class="CSSE_edit" id="editHeight" style="display:none;">
													<tr>
														<td width="85">Height (px):</td>
														<td>
															<input type="text" maxlength="4" class="elmColor" id="height" onblur="bind(this);"/>
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<tr>
											<td class="btm"/>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
					<td width="10"> </td>
					<td valign="top">
						<div style="overflow-x:auto;width:633px;">
							<!-- POLL XHTML START -->
									<div class="pds-box" id="pds-box">
										<div class="pds-box-outer">
											<div class="pds-box-inner">
												<div class="pds-box-top">
													<div class="pds-question">
														<div class="pds-question-outer">
															<div class="pds-question-inner">
																<div class="pds-question-top" id="pds-question-top">Do you mostly use the internet at work, in school or at home?</div>
															</div>
														</div>
													</div>
													<div>
							<!-- divAnswers -->
														<div id="divAnswers">
															<span id="pds-answer143974">

																<span class="pds-answer-group" id="pds-answer-group">
																	<span class="pds-answer-input" id="pds-answer-input">
																		<input type="radio" name="PDI_answer" value="1" id="p1" class="pds-checkbox"/>
																	</span>
																	<label for="p1" class="pds-answer" id="pds-answer"><span class="pds-answer-span">I use it in school.</span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group1">
																	<span class="pds-answer-input" id="pds-answer-input1">
																		<input type="radio" name="PDI_answer" value="2" id="p2" class="pds-checkbox"/>
																	</span>
																	<label for="p2" class="pds-answer" id="pds-answer1"><span class="pds-answer-span">I use it at home.</span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group2">
																	<span class="pds-answer-input" id="pds-answer-input2">
																		<input type="radio" name="PDI_answer" value="3" id="p3" class="pds-checkbox"/>
																	</span>
																	<label for="p3" class="pds-answer" id="pds-answer2"><span class="pds-answer-span">I use it every where I go, at work and home and anywhere else that I can!</span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group3">
																	<span class="pds-answer-input" id="pds-answer-input3">
																		<input type="radio" name="PDI_answer" value="4" id="p4" class="pds-checkbox"/>
																	</span>
																	<label for="p4" class="pds-answer" id="pds-answer3"><span class="pds-answer-span">Other:</span></label>
																	<span class="pds-clear"></span>
																	<span class="pds-answer-other">
																		<input type="text" name="PDI_OtherText1761982" id="pds-textfield" maxlength="80" class="pds-textfield"/>
																	</span>
																	<span class="pds-clear"></span>
																</span>

															</span>
															<br/>
															<div class="pds-vote" id="pds-links">
																<div class="pds-votebutton-outer">
																	<a href="javascript:renderStyleEdit('pds-answer-feedback');" id="pds-vote-button" style="display:block;float:left;" class="pds-vote-button"><span>Vote</span></a>
																	<span class="pds-links">
																		<div style="padding: 0px 0px 0px 15px; float:left;"><a href="javascript:renderStyleEdit('pds-answer-feedback');" class="pds-link" id="pds-link">View Results</a></div>
																		<span class="pds-clear"></span>
																	</span>
																	<span class="pds-clear"></span>
																</div>
															</div>

														</div>
							<!-- End divAnswers -->
							<!-- divResults -->
														<div id="divResults">

															<div class="pds-answer-group" id="pds-answer-group4">
																<label for="PDI_feedback1" class="pds-answer" id="pds-answer4"><span class="pds-answer-text">I use it in school!</span><xsl:text> </xsl:text><span class="pds-feedback-per"><strong>46%</strong></span><xsl:text> </xsl:text><span class="pds-feedback-votes">(620 votes)</span></label>
																<span class="pds-clear"></span>
																<div id="pds-answer-feedback">
																	<div style="width:46%;" id="pds-answer-feedback-bar" class="pds-answer-feedback-bar"></div>
																</div>
																<span class="pds-clear"></span>
															</div>

															<div class="pds-answer-group" id="pds-answer-group5">
																<label for="PDI_feedback2" class="pds-answer" id="pds-answer5"><span class="pds-answer-text">I use it at home.</span><xsl:text> </xsl:text><span class="pds-feedback-per"><strong>30%</strong></span><xsl:text> </xsl:text><span class="pds-feedback-votes">(400 votes)</span></label>
																<span class="pds-clear"></span>
																<div id="pds-answer-feedback2">
																	<div style="width:46%;" id="pds-answer-feedback-bar2" class="pds-answer-feedback-bar"></div>
																</div>
																<span class="pds-clear"></span>
															</div>

															<div class="pds-answer-group" id="pds-answer-group6">
																<label for="PDI_feedback3" class="pds-answer" id="pds-answer6"><span class="pds-answer-text">I use it every where I go, at work and home and anywhere else that I can!</span><xsl:text> </xsl:text><span class="pds-feedback-per"><strong>16%</strong></span><xsl:text> </xsl:text><span class="pds-feedback-votes">(220 votes)</span></label>
																<span class="pds-clear"></span>
																<div id="pds-answer-feedback3">
																	<div style="width:16%;" id="pds-answer-feedback-bar3" class="pds-answer-feedback-bar"></div>
																</div>
																<span class="pds-clear"></span>
															</div>

															<div class="pds-answer-group" id="pds-answer-group7">
																<label for="PDI_feedback4" class="pds-answer" id="pds-answer7"><span class="pds-answer-text">Other</span><xsl:text> </xsl:text><span class="pds-feedback-per"><strong>8%</strong></span><xsl:text> </xsl:text><span class="pds-feedback-votes">(110 votes)</span></label>
																<span class="pds-clear"></span>
																<div id="pds-answer-feedback4">
																	<div style="width:8%;" id="pds-answer-feedback-bar4" class="pds-answer-feedback-bar"></div>
																</div>
																<span class="pds-clear"></span>
															</div>

														</div>
							<!-- End divResults -->
														<span class="pds-clear"></span>
														<div style="height: 10px;"></div>
														<div id="pds-totalvotes-inner">Total Votes: <strong>1,350</strong></div>
													</div>
													<div class="pds-vote" id="pds-links-back">
														<div class="pds-totalvotes-outer">
																<span class="pds-links-back">
																	<br/>
																	<a href="javascript:" class="pds-link" id="pds-link1">Comments <strong>(19)</strong></a> 
																	<xsl:text> </xsl:text>
																	<a href="javascript:renderStyleEdit('pds-box');" class="pds-link" id="pds-link2">Return To Poll</a>
																	<span class="pds-clear"></span>
																</span>
																<span class="pds-clear"></span>
														</div>
													</div>
													</div>
											</div>
										</div>
									</div>
							<!-- POLL XHTML END -->
						</div>
					</td>
				</tr>
			</table>
			<div id="editBox"></div>
			
			<p class="pds-clear"></p>
			
			<?php wp_nonce_field( $style_id > 1000 ? "edit-style$style_id" : 'create-style' ); ?>
			<input type="hidden" name="action" value="<?php echo $style_id > 1000 ? 'edit-style' : 'create-style'; ?>" />
			<input type="hidden" class="polldaddy-style-id" name="style" value="<?php echo $style_id; ?>" />
			<input type="submit" class="button-primary" value="<?php echo attribute_escape( __( 'Save Style' ) ); ?>" />
						
		</div>
	</div>		
	<textarea id="S_www" name="CSSXML" style="display:none;width: 1000px; height: 500px;" rows="10" cols="10"> </textarea>
	</form>
	<div id="S_js"/>
	<div id="P_tab"/>
<script type="text/javascript" language="javascript">window.onload = function() {
	var CSSXML;
	loadStyle();
	showResults( false );
	renderStyleEdit( $('styleName').value );
}</script>
	<div id="noteImageURL" style="display:none">
You can place a link to an image here and it<br/>
will appear in your poll. You must use a URL<br/>
linking to an image already hosted somewhere<br/>
on the internet.
</div>
	<div id="noteWidth" style="display:none">
To control the width of this element you must<br/>
enter a numeric value in pixels. 
</div>
	<br class="clear" />

	<?php
	}

	function signup() {
		return $this->api_key_page();
	}

	function can_edit( &$poll ) {
		global $current_user;
		if ( empty( $poll->_owner ) )
			return true;

		if ( $current_user->ID == $poll->_owner )
			return true;

		return current_user_can( 'edit_others_posts' );
	}
}

function polldaddy_loader() {
	global $polldaddy_object;
	$polldaddy_class = WP_POLLDADDY__CLASS;
	$polldaddy_object = new $polldaddy_class;
	add_action( 'admin_menu', array( &$polldaddy_object, 'admin_menu' ) );
}

add_action( 'init', 'polldaddy_loader' );

function polldaddy_shortcode($atts, $content=null) {
	extract(shortcode_atts(array(
		'poll' => 'empty',
		'cb' => '',
	), $atts));

	$poll = (int) $poll;
	$cb = ( $cb == 1 ? '?cb=' . mktime() : '' );
		
	return "<script type='text/javascript' language='javascript' charset='utf-8' src='http://s3.polldaddy.com/p/$poll.js$cb'></script><noscript> <a href='http://answers.polldaddy.com/poll/$poll/'>View Poll</a></noscript>";
}

add_shortcode('polldaddy', 'polldaddy_shortcode');

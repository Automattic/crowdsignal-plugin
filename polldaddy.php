<?php

/*
Plugin Name: PollDaddy Polls
Description: Create and manage PollDaddy polls in WordPress
Author: Automattic, Inc.
Author URL: http://automattic.com/
Version: 1.2
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
	var $polldaddy_client_class = 'PollDaddy_Client';
	var $base_url = false;
	var $use_ssl = 0;
	var $scheme = 'https';
	var $version = '1.2';

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
		add_submenu_page( 'polls', __( 'Edit Polls' ), __( 'Edit' ), 'edit_posts', 'polls' ); //, array( &$this, 'management_page' ) );
		add_submenu_page( 'polls', __( 'Add New Poll' ), __( 'Add New' ), 'edit_posts', 'polls&amp;action=create-poll', array( &$this, 'management_page' ) );

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
				$this->use_ssl = 2;
			} else {
				$this->use_ssl = 1;
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
				$this->use_ssl = 2;
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
			return false;
		}

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->GetUserCode( $GLOBALS['current_user']->ID ) ) {
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

				if ( $this->use_ssl > 0 ) {
					$checked = '';
					if ( $this->use_ssl == 2 )
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
				</tr><?php
				}
				?>
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
			define( 'WP_POLLDADDY__USERCODE', $polldaddy->GetUserCode( $current_user->ID ) );

		wp_reset_vars( array( 'action', 'poll' ) );
		global $action, $poll;

		if ( !WP_POLLDADDY__USERCODE )
			$action = 'signup';

		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		wp_enqueue_script( 'polls', "{$this->base_url}polldaddy.js", array( 'jquery', 'jquery-ui-sortable' ), $this->version );
		wp_enqueue_script( 'admin-forms' );
		add_thickbox();

		wp_enqueue_style( 'polls', "{$this->base_url}polldaddy.css", array( 'global', 'wp-admin' ), $this->version );
		add_action( 'admin_body_class', array( &$this, 'admin_body_class' ) );

		add_action( 'admin_notices', array( &$this, 'management_page_notices' ) );

		$query_args = array();

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
				$poll_object = $polldaddy->GetPoll( $poll );

				if ( !$this->can_edit( $poll_object ) ) {
					$this->errors->add( 'permission', __( 'You are not allowed to delete this poll.' ) );
					return false;
				}

				// Send Poll Author credentials
				if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
					$polldaddy->reset();
					if ( !$userCode = $polldaddy->GetUserCode( $poll_object->_owner ) ) { 
						$this->errors->add( 'no_usercode', __( 'Invalid Poll Author' ) );
					}
					$polldaddy->userCode = $userCode;
				}

				$polldaddy->reset();
				$polldaddy->DeletePoll( $poll_id );
			}

			$query_args['message'] = 'deleted';
			$query_args['deleted'] = count( (array) $poll );
			break;
		case 'edit-poll' : // TODO: use polldaddy_poll
			if ( !$is_POST || !$poll = (int) $poll )
				return;

			check_admin_referer( "edit-poll_$poll" );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, WP_POLLDADDY__USERCODE );
			$polldaddy->reset();

			$poll_object = $polldaddy->GetPoll( $poll );
			$this->parse_errors( $polldaddy );

			if ( !$this->can_edit( $poll_object ) ) {
				$this->errors->add( 'permission', __( 'You are not allowed to edit this poll.' ) );
				return false;
			}

			// Send Poll Author credentials
			
			if ( !empty( $poll_object->_owner ) && $current_user->ID != $poll_object->_owner ) {
				$polldaddy->reset();
				if ( !$userCode = $polldaddy->GetUserCode( $poll_object->_owner ) ) {	
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

			foreach ( array( 'multipleChoice', 'randomiseAnswers', 'otherAnswer' ) as $option ) {
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

				if ( is_numeric( $answer_id ) )
					$answers[] = polldaddy_poll_answer( $answer, $answer_id );
				else
					$answers[] = polldaddy_poll_answer( $answer );
			}

			if ( 2 > count( $answers ) )
				$this->errors->add( 'answer', __( 'You must include at least 2 answers' ) );

			if ( $this->errors->get_error_codes() )
				return false;

			$poll_data['answers'] = $answers;
			$poll_data['styleID'] = (int) $_POST['styleID'];

			$polldaddy->reset();

			$update_response = $polldaddy->UpdatePoll( $poll, $poll_data );

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
			foreach ( $_POST['answer'] as $answer )
				if ( $answer = trim( stripslashes( $answer ) ) )
					$answers[] = polldaddy_poll_answer( $answer );
			if ( !$answers )
				return false;

			$poll_data = _polldaddy_poll_defaults();
			foreach ( $poll_data as $key => $value )
				if ( isset($_POST[$key]) )
					$poll_data[$key] = stripslashes( $_POST[$key] );

			$poll_data['answers'] = $answers;

			$poll = $polldaddy->CreatePoll( $poll_data );
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

			if ( !$polldaddy->Initiate( $email, $password, $GLOBALS['user_ID'] ) ) {
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
		case 'updated' :
			$message = __( 'Poll updated.' );
			break;
		case 'created' :
			$message = __( 'Poll created.' );
			if ( isset( $_GET['iframe'] ) )
				$message .= ' <input type="button" class="button polldaddy-send-to-editor" value="' . attribute_escape( __( 'Send to Editor' ) ) . '" />';
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
		global $action, $poll;
		$poll = (int) $poll;

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
			echo do_shortcode( "[polldaddy poll=$poll]" );
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
			$polls_object = $polldaddy->listPolls( ( $page - 1 ) * 10 + 1, $page * 10 );
		else
			$polls_object = $polldaddy->listPollsByBlog( ( $page - 1 ) * 10 + 1, $page * 10 );
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
				</select>
				<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply' ); ?>" />
				<?php wp_nonce_field( 'delete-poll_bulk' ); ?>
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

				if ( $this->can_edit( $poll ) )
					$edit_link = clean_url( add_query_arg( array( 'action' => 'edit', 'poll' => $poll_id, 'message' => false ) ) );
				else
					$edit_link = false;

				$class = $class ? '' : ' class="alternate"';
				$results_link = clean_url( add_query_arg( array( 'action' => 'results', 'poll' => $poll_id, 'message' => false ) ) );
				$delete_link = clean_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'poll' => $poll_id, 'message' => false ) ), "delete-poll_$poll_id" ) );
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
 &lt;a href="http://answers.polldaddy.com/poll/1000076/"&gt;<?php echo wp_specialchars( $poll->___content ); ?>&lt;/a&gt;&lt;br/&gt;
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
			$poll = $polldaddy->GetPoll( $poll_id );
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
				$answers[(int) $answer->_id] = attribute_escape( $answer->___content );
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
		foreach ( array( 'multipleChoice' => __( 'Multiple choice' ), 'randomiseAnswers' => __( 'Randomize answer order' ), 'otherAnswer' => __( 'Allow other answers' ) ) as $option => $label ) :
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

<?php	$style_ID = (int) ( $is_POST ? $_POST['styleID'] : $poll->styleID ); ?>

		<h3><?php _e( 'Design' ); ?></h3>

		<div class="inside">
			<div class="hide-if-no-js">
				<a class="alignleft" href="#previous">&#171;</a>
				<a class="alignright" href="#next">&#187;</a>
				<img src="http://polldaddy.com/images/<?php echo $style_ID; ?>.gif" />
				<img class="hide-if-js" src="http://polldaddy.com/images/<?php echo 1 + $style_ID; ?>.gif" />
			</div>

			<p class="hide-if-js" id="no-js-styleID">
				<select name="styleID">

<?php
				$options = array(
					0 => 'Grey Plastic Standard',
					1 => 'White Plastic Standard',
					2 => 'Black Plastic Standard',
					3 => 'Simple Grey',
					4 => 'Simple White',
					5 => 'Simple Dark',
					6 => 'Thinking 1',
					7 => 'Thinking 2',
					8 => 'Manga',
					9 => 'Working 1',
					10 => 'Working 2',
					11 => 'SideBar Narrow (Dark)',
					12 => 'SideBar Narrow (Light)',
					13 => 'SideBar Narrow (Grey)',
					14 => 'Skulls',
					15 => 'Music',
					16 => 'Sunset',
					17 => 'Pink Butterflies',
					18 => 'Map'
				);
				foreach ( $options as $styleID => $label ) :
					$selected = $styleID == $style_ID ? ' selected="selected"' : '';
?>
					<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo wp_specialchars( $label ); ?></option>
<?php				endforeach; ?>

				</select>
			</p>
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

		$results = $polldaddy->GetPollResults( $poll_id );
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
	), $atts));

	$poll = (int) $poll;
	
	return "<script type='text/javascript' language='javascript' charset='utf-8' src='http://s3.polldaddy.com/p/$poll.js'></script><noscript> <a href='http://answers.polldaddy.com/poll/$poll/'>View Poll</a></noscript>";
}

add_shortcode('polldaddy', 'polldaddy_shortcode');

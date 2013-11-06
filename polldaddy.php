<?php

/*
Plugin Name: Polldaddy Polls & Ratings
Plugin URI: http://wordpress.org/extend/plugins/polldaddy/
Description: Create and manage Polldaddy polls and ratings in WordPress
Author: Automattic, Inc.
Author URL: http://polldaddy.com/
Version: 2.0.21
*/

// You can hardcode your Polldaddy PartnerGUID (API Key) here
//define( 'WP_POLLDADDY__PARTNERGUID', '12345…' );

class WP_Polldaddy {
	var $errors;
	var $base_url;
	var $is_admin;
	var $is_author;
	var $scheme;
	var $version;
	var $polldaddy_client_class;
	var $polldaddy_clients;
	var $id;
	var $multiple_accounts;
	var $user_code;
	var $rating_user_code;
	var $has_feedback_menu;
	
	function __construct() {
		global $current_user;
		$this->log( 'Created WP_Polldaddy Object: constructor' );
		$this->errors                 = new WP_Error;
		$this->scheme                 = 'https';
		$this->version                = '2.0.19';
		$this->multiple_accounts      = true;
		$this->polldaddy_client_class = 'api_client';
		$this->polldaddy_clients      = array();
		$this->is_admin               = (bool) current_user_can( 'manage_options' );
		$this->is_author              = (bool) current_user_can( 'edit_posts' );
		$this->is_editor              = (bool) current_user_can( 'delete_others_pages' );
		$this->user_code              = null;
		$this->rating_user_code       = null;
		$this->id                     = ($current_user instanceof WP_User) ? intval( $current_user->ID ): 0;
		$this->has_feedback_menu      = false;
		
		if ( class_exists( 'Jetpack' ) ) {
			if ( method_exists( 'Jetpack', 'is_active' ) && Jetpack::is_active() ) {
				$jetpack_active_modules = get_option('jetpack_active_modules');
				if ( $jetpack_active_modules && in_array( 'contact-form', $jetpack_active_modules ) )
					$this->has_feedback_menu = true;
			}
		}
	}

	function &get_client( $api_key, $userCode = null ) {
		if ( isset( $this->polldaddy_clients[$api_key] ) ) {
			if ( !is_null( $userCode ) )
				$this->polldaddy_clients[$api_key]->userCode = $userCode;
			return $this->polldaddy_clients[$api_key];
		}
		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;
		$this->polldaddy_clients[$api_key] = $this->config_client( new $this->polldaddy_client_class( $api_key, $userCode ) );
		return $this->polldaddy_clients[$api_key];
	}

	function config_client( $client ) {

		return $client;
	}

	function admin_menu() {	
		add_action( 'admin_head', array( &$this, 'do_admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'menu_alter' ) );

		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) ) {
			$guid = get_option( 'polldaddy_api_key' );
			if ( !$guid || !is_string( $guid ) )
				$guid = false;
			define( 'WP_POLLDADDY__PARTNERGUID', $guid );

		}
		
		$capability = 'edit_posts';
		$icon       = "{$this->base_url}img/pd-wp-icon-gray.png";
		$function   = array( &$this, 'management_page' );
					
		if ( !WP_POLLDADDY__PARTNERGUID ) {
			foreach( array( 'polls' => __( 'Polls', 'polldaddy' ), 'ratings' => __( 'Ratings', 'polldaddy' ) ) as $menu_slug => $menu_title ) {
				$hook = add_object_page( $menu_title, $menu_title, $capability, $menu_slug, array( &$this, 'api_key_page' ), $icon );
				add_action( "load-$hook", array( &$this, 'api_key_page_load' ) );
			}
			return false;
		}
		
		$hook = add_object_page( __( 'Feedback', 'polldaddy' ), __( 'Feedback', 'polldaddy' ), $capability, 'feedback', $function, $icon );
		add_action( "load-$hook", array( &$this, 'management_page_load' ) );
		
		foreach( array( 'polls' => __( 'Polls', 'polldaddy' ), 'ratings' => __( 'Ratings', 'polldaddy' ) ) as $menu_slug => $page_title ) {
			$menu_title  = $page_title;
			
			$hook = add_object_page( $menu_title, $menu_title, $capability, $menu_slug, $function, $icon );
			add_action( "load-$hook", array( &$this, 'management_page_load' ) );
			
			add_submenu_page( 'feedback', $page_title, $page_title, $capability, $menu_slug, $function );			
			add_options_page( $page_title, $page_title, $menu_slug == 'ratings' ? 'manage_options' : $capability, $menu_slug.'&action=options', $function );
		}
		
		remove_submenu_page( 'feedback', 'feedback' );
		remove_menu_page( 'polls' );
		remove_menu_page( 'ratings' );
		
		if ( $this->has_feedback_menu ) {		
			add_submenu_page( 'feedback', __( 'Feedback', 'polldaddy' ), __( 'Feedback', 'polldaddy' ), 'edit_posts', 'edit.php?post_type=feedback' );			
			remove_menu_page( 'edit.php?post_type=feedback' );		
		}
		
		add_action( 'media_buttons', array( &$this, 'media_buttons' ) );		
	}
	
	function menu_alter() {}

	function do_admin_css() {

		$scheme =  get_user_option( 'admin_color' );

		if ( $scheme == 'classic' ) {
			$color = "blue";
		} else {
			$color = "gray";
		}

		include 'admin-style.php';
	}

	function api_key_page_load() {

		if ( 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || empty( $_POST['action'] ) || 'account' != $_POST['action'] )
			return false;

		check_admin_referer( 'polldaddy-account' );

		$polldaddy_email = stripslashes( $_POST['polldaddy_email'] );
		$polldaddy_password = stripslashes( $_POST['polldaddy_password'] );

		if ( !$polldaddy_email )
			$this->errors->add( 'polldaddy_email', __( 'Email address required', 'polldaddy' ) );

		if ( !$polldaddy_password )
			$this->errors->add( 'polldaddy_password', __( 'Password required', 'polldaddy' ) );

		if ( $this->errors->get_error_codes() )
			return false;

		$details = array(
			'uName'          => get_bloginfo( 'name' ),
			'uEmail'         => $polldaddy_email,
			'uPass'          => $polldaddy_password,
			'partner_userid' => $this->id
		);
		if ( function_exists( 'wp_remote_post' ) ) { // WP 2.7+
			$polldaddy_api_key = wp_remote_post( $this->scheme . '://api.polldaddy.com/key.php', array(
					'body' => $details
				) );
			if ( is_wp_error( $polldaddy_api_key ) ) {
				$this->errors = $polldaddy_api_key;
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
				$this->errors->add( 'connect', __( "Can't connect to Polldaddy.com", 'polldaddy' ) );
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, 3 );

			global $wp_version;

			$request_body = http_build_query( $details, null, '&' );

			$request  = "POST /key.php HTTP/1.0\r\n";
			$request .= "Host: api.polldaddy.com\r\n";
			$request .= "User-agent: WordPress/$wp_version\r\n";
			$request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ) . "\r\n";
			$request .= 'Content-Length: ' . strlen( $request_body ) . "\r\n";

			fwrite( $fp, "$request\r\n$request_body" );

			$response = '';
			while ( !feof( $fp ) )
				$response .= fread( $fp, 4096 );
			fclose( $fp );
			list( $headers, $polldaddy_api_key ) = explode( "\r\n\r\n", $response, 2 );
		}

		if ( !$polldaddy_api_key ) {
			$this->errors->add( 'polldaddy_password', __( 'Invalid Account', 'polldaddy' ) );
			return false;
		}

		update_option( 'polldaddy_api_key', $polldaddy_api_key );

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->get_usercode( $this->id ) ) {
			$this->parse_errors( $polldaddy );
			$this->errors->add( 'GetUserCode', __( 'Account could not be accessed.  Are your email address and password correct?', 'polldaddy' ) );
			return false;
		}

		return true;
	}

	function parse_errors( &$polldaddy ) {
		if ( $polldaddy->errors )
			foreach ( $polldaddy->errors as $code => $error )
				$this->errors->add( $code, $error );
				
			if ( isset( $this->errors->errors[4] ) ) {
				//need to get latest usercode
				update_option( 'pd-usercode-'.$this->id, '' );
				$this->set_api_user_code();
			}
	}

	function print_errors() {
		if ( !$error_codes = $this->errors->get_error_codes() )
			return;
?>

<div class="error" id="polldaddy-error">

<?php

		foreach ( $error_codes as $error_code ) :
			foreach ( $this->errors->get_error_messages( $error_code ) as $error_message ) :
?>

	<p><?php echo $this->errors->get_error_data( $error_code ) ? $error_message : esc_html( $error_message ); ?></p>

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
	<h2 id="polldaddy-header"><?php _e( 'Polldaddy', 'polldaddy' ); ?></h2>

	<p><?php printf( __( 'Before you can use the Polldaddy plugin, you need to enter your <a href="%s">Polldaddy.com</a> account details.', 'polldaddy' ), 'http://polldaddy.com/' ); ?></p>

	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-email"><?php _e( 'Polldaddy Email Address', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" />
					</td>
				</tr>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-password"><?php _e( 'Polldaddy Password', 'polldaddy' ); ?></label>
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
</div>

<?php
	}

	function media_buttons() {
		$title = __( 'Add Poll', 'polldaddy' );
		echo " <a href='admin.php?page=polls&iframe&TB_iframe=true' onclick='return false;' id='add_poll' class='button thickbox' title='" . esc_attr( $title ) . "'><img src='{$this->base_url}img/polldaddy@2x.png' width='15' height='15' alt='" . esc_attr( $title ) . "' style='margin: -2px 0 0 -1px; padding: 0 2px 0 0; vertical-align: middle;' /> " . esc_html( $title ) . "</a>";
	}

	function set_api_user_code() {

		$this->user_code = get_option( 'pd-usercode-'.$this->id );		

		if ( empty( $this->user_code ) ) {
			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID );
			$polldaddy->reset();
		
			$this->user_code = $polldaddy->get_usercode( $this->id );
			
			if ( !empty( $this->user_code ) ) {
				update_option( 'pd-usercode-'.$this->id, $this->user_code );
			}
		}
	}

	function management_page_load() {
	
		wp_reset_vars( array( 'page', 'action', 'poll', 'style', 'rating', 'id' ) );
		global $plugin_page, $page, $action, $poll, $style, $rating, $id, $wp_locale;

		$this->set_api_user_code();

		if ( empty( $this->user_code ) && $page == 'polls' ) {
			// one last try to get the user code automatically if possible
			$this->user_code = apply_filters_ref_array( 'polldaddy_get_user_code', array( $this->user_code, &$this ) );
			if ( false == $this->user_code )
				$action = 'signup';
		}

		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		wp_enqueue_script( 'polls', "{$this->base_url}js/polldaddy.js", array( 'jquery', 'jquery-ui-sortable', 'jquery-form' ), $this->version );
		wp_enqueue_script( 'polls-common', "{$this->base_url}js/common.js", array(), $this->version );

		if ( $page == 'polls' ) {
			if ( !$this->is_author && in_array( $action, array( 'edit', 'edit-poll', 'create-poll', 'edit-style', 'create-style', 'list-styles', 'options', 'update-options', 'import-account' ) ) ) {//check user privileges has access to action
				$action = '';
			}

			switch ( $action ) {
			case 'edit' :
			case 'edit-poll' :
			case 'create-poll' :
			case 'add-media' :
				wp_enqueue_script( 'media-upload', array(), $this->version );
				wp_enqueue_script( 'polls-style', "{$this->base_url}js/poll-style-picker.js", array( 'polls', 'polls-common' ), $this->version );

				if ( $action == 'create-poll' )
					$plugin_page = 'polls&action=create-poll';

				break;
			case 'edit-style' :
			case 'create-style' :
				wp_enqueue_script( 'polls-style', "{$this->base_url}js/style-editor.js", array( 'polls', 'polls-common' ), $this->version );
				wp_enqueue_script( 'polls-style-color', "{$this->base_url}js/jscolor.js", array(), $this->version );
				wp_enqueue_style( 'polls', "{$this->base_url}css/style-editor.css", array(), $this->version );
				$plugin_page = 'polls&action=list-styles';
				break;
			case 'list-styles' :
				$plugin_page = 'polls&action=list-styles';
				break;
			case 'options' :
			case 'update-options' :
			case 'import-account' :
				$plugin_page = 'polls&action=options';
				break;
			}//end switch
		} elseif ( $page == 'ratings' ) {
			switch ( $action ) {	
			case 'update-rating' :			
			case 'options':
				$plugin_page = 'ratings&action=options';
				wp_enqueue_script( 'rating-text-color', "{$this->base_url}js/jscolor.js", array(), $this->version );
				wp_enqueue_script( 'ratings', "{$this->base_url}js/rating.js", array(), $this->version );
				wp_localize_script( 'polls-common', 'adminRatingsL10n', array(
						'star_colors' => __( 'Star Colors', 'polldaddy' ), 'star_size' =>  __( 'Star Size', 'polldaddy' ),
						'nero_type' => __( 'Nero Type', 'polldaddy' ), 'nero_size' => __( 'Nero Size', 'polldaddy' ), ) );
				break;			
			default :
				if ( empty( $action ) )
					$action = 'reports';
				$plugin_page = 'ratings&action=reports';
			}//end switch
		}
		
		wp_enqueue_style( 'polldaddy', "{$this->base_url}css/polldaddy.css", array(), $this->version );
		wp_enqueue_script( 'admin-forms' );
		add_thickbox();
		
		if ( isset( $_GET['iframe'] ) ) {
			add_action( 'admin_head', array( &$this, 'hide_admin_menu' ) );
		}

		if ( isset( $wp_locale->text_direction ) && 'rtl' == $wp_locale->text_direction )
			wp_enqueue_style( 'polls-rtl', "{$this->base_url}css/polldaddy-rtl.css", array( 'global', 'wp-admin' ), $this->version );
		add_action( 'admin_body_class', array( &$this, 'admin_body_class' ) );

		add_action( 'admin_notices', array( &$this, 'management_page_notices' ) );

		$query_args = array();
		$args = array();

		$allowedtags = array(
			'a' => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array() ),
			'img' => array(
				'alt'      => array(),
				'align'    => array(),
				'border'   => array(),
				'class'    => array(),
				'height'   => array(),
				'hspace'   => array(),
				'longdesc' => array(),
				'vspace'   => array(),
				'src'      => array(),
				'width'    => array() ),
			'abbr'       => array( 'title' => array() ),
			'acronym'    => array( 'title' => array() ),
			'blockquote' => array( 'cite'  => array() ),
			'q'          => array( 'cite'  => array() ),
			'b'      => array(),
			'cite'   => array(),
			'em'     => array(),
			'i'      => array(),
			'strike' => array(),
			'strong' => array()
		);

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $page == 'polls' ) {
			switch ( $action ) {
			case 'signup' : // sign up for first time
			case 'account' : // reauthenticate
			case 'import-account' : // reauthenticate
				if ( !$is_POST )
					return;

				check_admin_referer( 'polldaddy-account' );

				$this->user_code = '';
				update_option( 'pd-usercode-'.$this->id, '' );

				if ( $new_args = $this->management_page_load_signup() )
					$query_args = array_merge( $query_args, $new_args );

				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'imported-account';

				wp_reset_vars( array( 'action' ) );
				if ( !empty( $_GET['reaction'] ) )
					$query_args['action'] = $_GET['reaction'];
				elseif ( !empty( $_GET['action'] ) && 'account' == $_GET['action'] )
					$query_args['action'] = $_GET['action'];
				else
					$query_args['action'] = false;
				if ( $action == 'import-account' )
					$query_args[ 'action' ] = 'options'; // make sure we redirect back to the right page.
				break;

			case 'delete' :
				if ( empty( $poll ) )
					return;

				if ( is_array( $poll ) )
					check_admin_referer( 'action-poll_bulk' );
				else
					check_admin_referer( "delete-poll_$poll" );

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );

				foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
					$polldaddy->reset();
					$poll_object = $polldaddy->get_poll( $poll_id );

					if ( !$this->can_edit( $poll_object ) ) {
						$this->errors->add( 'permission', __( 'You are not allowed to delete this poll.', 'polldaddy' ) );
						return false;
					}

					// Send Poll Author credentials
					if ( !empty( $poll_object->_owner ) && $this->id != $poll_object->_owner ) {
						$polldaddy->reset();
						if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) {
							$this->errors->add( 'no_usercode', __( 'Invalid Poll Author', 'polldaddy' ) );
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

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );

				foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
					$polldaddy->reset();
					$poll_object = $polldaddy->get_poll( $poll_id );

					if ( !$this->can_edit( $poll_object ) ) {
						$this->errors->add( 'permission', __( 'You are not allowed to open this poll.', 'polldaddy' ) );
						return false;
					}

					// Send Poll Author credentials
					if ( !empty( $poll_object->_owner ) && $this->id != $poll_object->_owner ) {
						$polldaddy->reset();
						if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) {
							$this->errors->add( 'no_usercode', __( 'Invalid Poll Author', 'polldaddy' ) );
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

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );

				foreach ( (array) $_REQUEST['poll'] as $poll_id ) {
					$polldaddy->reset();
					$poll_object = $polldaddy->get_poll( $poll_id );

					if ( !$this->can_edit( $poll_object ) ) {
						$this->errors->add( 'permission', __( 'You are not allowed to close this poll.', 'polldaddy' ) );
						return false;
					}

					// Send Poll Author credentials
					if ( !empty( $poll_object->_owner ) && $this->id != $poll_object->_owner ) {
						$polldaddy->reset();
						if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) {
							$this->errors->add( 'no_usercode', __( 'Invalid Poll Author', 'polldaddy' ) );
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

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$polldaddy->reset();

				$poll_object = $polldaddy->get_poll( $poll );
				$this->parse_errors( $polldaddy );

				if ( !$this->can_edit( $poll_object ) ) {
					$this->errors->add( 'permission', __( 'You are not allowed to edit this poll.', 'polldaddy' ) );
					return false;
				}

				// Send Poll Author credentials
				if ( !empty( $poll_object->_owner ) && $this->id != $poll_object->_owner ) {
					$polldaddy->reset();
					if ( !$userCode = $polldaddy->get_usercode( $poll_object->_owner ) ) {
						$this->errors->add( 'no_usercode', __( 'Invalid Poll Author', 'polldaddy' ) );
					}
					$this->parse_errors( $polldaddy );
					$polldaddy->userCode = $userCode;
				}

				if ( !$poll_object )
					$this->errors->add( 'GetPoll', __( 'Poll not found', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$media = $mediaType = array();
				if ( isset( $_POST['media'] ) ) {
					$media = $_POST['media'];
					unset( $_POST['media'] );
				}

				if ( isset( $_POST['mediaType'] ) ) {
					$mediaType = $_POST['mediaType'];
					unset( $_POST['mediaType'] );
				}

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

				$blocks = array( 'off', 'cookie', 'cookieip' );
				if ( isset( $_POST['blockRepeatVotersType'] ) && in_array( $_POST['blockRepeatVotersType'], $blocks ) )
					$poll_data['blockRepeatVotersType'] = $_POST['blockRepeatVotersType'];

				$results = array( 'show', 'percent', 'hide' );
				if ( isset( $_POST['resultsType'] ) && in_array( $_POST['resultsType'], $results ) )
					$poll_data['resultsType'] = $_POST['resultsType'];
				$poll_data['question'] = stripslashes( $_POST['question'] );

				$comments = array( 'off', 'allow', 'moderate' );
				if ( isset( $_POST['comments'] ) && in_array( $_POST['comments'], $comments ) )
					$poll_data['comments'] = $_POST['comments'];

				if ( empty( $_POST['answer'] ) || !is_array( $_POST['answer'] ) )
					$this->errors->add( 'answer', __( 'Invalid answers', 'polldaddy' ) );

				$answers = array();
				foreach ( $_POST['answer'] as $answer_id => $answer ) {
					$answer = stripslashes( trim( $answer ) );

					if ( strlen( $answer ) > 0 ) {
						$answer = wp_kses( $answer, $allowedtags );

						$args['text'] = (string) $answer;

						$answer_id = str_replace('new', '', $answer_id );
						$mc = '';
						$mt = 0;

						if ( isset( $media[$answer_id] ) )
							$mc = esc_html( $media[$answer_id] );

						if ( isset( $mediaType[$answer_id] ) )
							$mt = intval( $mediaType[$answer_id] );

						$args['mediaType'] = $mt;
						$args['mediaCode'] = $mc;

						if ( $answer_id > 1000 )
							$answer = polldaddy_poll_answer( $args, $answer_id );
						else
							$answer = polldaddy_poll_answer( $args );

						if ( isset( $answer ) && is_a( $answer, 'Polldaddy_Poll_Answer' ) )
							$answers[] = $answer;
					}
				}

				if ( 2 > count( $answers ) )
					$this->errors->add( 'answer', __( 'You must include at least 2 answers', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$poll_data['answers'] = $answers;

				$poll_data['question'] = wp_kses( $poll_data['question'], $allowedtags );

				if ( isset ( $_POST['styleID'] ) ) {
					if ( $_POST['styleID'] == 'x' ) {
						$this->errors->add( 'UpdatePoll', __( 'Please choose a poll style', 'polldaddy' ) );
						return false;
					}
				}
				$poll_data['styleID'] = (int) $_POST['styleID'];
				$poll_data['choices'] = (int) $_POST['choices'];

				if ( $poll_data['blockRepeatVotersType'] == 'cookie' ) {
					if ( isset( $_POST['cookieip_expiration'] ) )
						$poll_data['blockExpiration'] = (int) $_POST['cookieip_expiration'];
				} elseif ( $poll_data['blockRepeatVotersType'] == 'cookieip' ) {
					if ( isset( $_POST['cookieip_expiration'] ) )
						$poll_data['blockExpiration'] = (int) $_POST['cookieip_expiration'];
				}

				if ( isset( $media[999999999] ) )
					$poll_data['mediaCode'] = esc_html( $media[999999999] );

				if ( isset( $mediaType[999999999] ) )
					$poll_data['mediaType'] = intval( $mediaType[999999999] );
					
				if( isset( $GLOBALS['blog_id'] ) )
					$poll_data['parentID'] = (int) $GLOBALS['blog_id'];

				$polldaddy->reset();

				$update_response = $polldaddy->update_poll( $poll, $poll_data );
				$this->parse_errors( $polldaddy );

				if ( !$update_response )
					$this->errors->add( 'UpdatePoll', __( 'Poll could not be updated', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'updated';
				if ( isset( $_POST['iframe'] ) )
					$query_args['iframe'] = '';
				break;
			case 'create-poll' :
				if ( !$is_POST )
					return;

				check_admin_referer( 'create-poll' );

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$polldaddy->reset();

				$media = $mediaType = array();
				if ( isset( $_POST['media'] ) ) {
					$media = $_POST['media'];
					unset( $_POST['media'] );
				}

				if ( isset( $_POST['mediaType'] ) ) {
					$mediaType = $_POST['mediaType'];
					unset( $_POST['mediaType'] );
				}

				$answers = array();
				foreach ( $_POST['answer'] as $answer_id => $answer ) {
					$answer = stripslashes( trim( $answer ) );

					if ( strlen( $answer ) > 0 ) {
						$answer = wp_kses( $answer, $allowedtags );

						$args['text'] = (string) $answer;

						$answer_id = (int) str_replace('new', '', $answer_id );
						$mc = '';
						$mt = 0;

						if ( isset( $media[$answer_id] ) )
							$mc = esc_html( $media[$answer_id] );

						if ( isset( $mediaType[$answer_id] ) )
							$mt = intval( $mediaType[$answer_id] );

						$args['mediaType'] = $mt;
						$args['mediaCode'] = $mc;

						$answer = polldaddy_poll_answer( $args );

						if ( isset( $answer ) && is_a( $answer, 'Polldaddy_Poll_Answer' ) )
							$answers[] = $answer;
					}
				}

				if ( !$answers )
					return false;

				$poll_data = _polldaddy_poll_defaults();

				foreach ( array( 'multipleChoice', 'randomiseAnswers', 'otherAnswer', 'sharing' ) as $option ) {
					if ( isset( $_POST[$option] ) && $_POST[$option] )
						$poll_data[$option] = 'yes';
					else
						$poll_data[$option] = 'no';
				}

				$blocks = array( 'off', 'cookie', 'cookieip' );
				if ( isset( $_POST['blockRepeatVotersType'] ) && in_array( $_POST['blockRepeatVotersType'], $blocks ) )
					$poll_data['blockRepeatVotersType'] = $_POST['blockRepeatVotersType'];

				$results = array( 'show', 'percent', 'hide' );
				if ( isset( $_POST['resultsType'] ) && in_array( $_POST['resultsType'], $results ) )
					$poll_data['resultsType'] = $_POST['resultsType'];

				$comments = array( 'off', 'allow', 'moderate' );
				if ( isset( $_POST['comments'] ) && in_array( $_POST['comments'], $comments ) )
					$poll_data['comments'] = $_POST['comments'];

				$poll_data['answers'] = $answers;

				$poll_data['question'] = stripslashes( $_POST['question'] );
				$poll_data['question'] = wp_kses( $poll_data['question'], $allowedtags );

				if ( isset ( $_POST['styleID'] ) ) {
					if ( $_POST['styleID'] == 'x' ) {
						$this->errors->add( 'UpdatePoll', __( 'Please choose a poll style', 'polldaddy' ) );
						return false;
					}
				}
				$poll_data['styleID'] = (int) $_POST['styleID'];
				$poll_data['choices'] = (int) $_POST['choices'];

				if ( $poll_data['blockRepeatVotersType'] == 'cookie' ) {
					if ( isset( $_POST['cookieip_expiration'] ) )
						$poll_data['blockExpiration'] = (int) $_POST['cookieip_expiration'];
				} elseif ( $poll_data['blockRepeatVotersType'] == 'cookieip' ) {
					if ( isset( $_POST['cookieip_expiration'] ) )
						$poll_data['blockExpiration'] = (int) $_POST['cookieip_expiration'];
				}

				if ( isset( $media[999999999] ) )
					$poll_data['mediaCode'] = esc_html( $media[999999999] );

				if ( isset( $mediaType[999999999] ) )
					$poll_data['mediaType'] = intval( $mediaType[999999999] );
				
				$poll = $polldaddy->create_poll( $poll_data );
				$this->parse_errors( $polldaddy );

				if ( !$poll || empty( $poll->_id ) )
					$this->errors->add( 'CreatePoll', __( 'Poll could not be created', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'created';
				$query_args['action'] = 'edit-poll';
				$query_args['poll'] = $poll->_id;
				if ( isset( $_POST['iframe'] ) )
					$query_args['iframe'] = '';
				break;
			case 'delete-style' :
				if ( empty( $style ) )
					return;

				if ( is_array( $style ) )
					check_admin_referer( 'action-style_bulk' );
				else
					check_admin_referer( "delete-style_$style" );

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );

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

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$polldaddy->reset();

				$style_data = _polldaddy_style_defaults();

				if ( isset( $_POST['style-title'] ) )
					$style_data['title'] = stripslashes( trim( (string) $_POST['style-title'] ) );

				if ( isset( $_POST['CSSXML'] ) )
					$style_data['css'] = urlencode( stripslashes( trim( (string) $_POST['CSSXML'] ) ) );

				if ( isset( $_REQUEST['updatePollCheck'] ) && $_REQUEST['updatePollCheck'] == 'on' )
					$style_data['retro'] = 1;

				$update_response = $polldaddy->update_style( $style, $style_data );

				$this->parse_errors( $polldaddy );

				if ( !$update_response )
					$this->errors->add( 'UpdateStyle', __( 'Style could not be updated', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'updated-style';
				if ( isset( $_POST['iframe'] ) )
					$query_args['iframe'] = '';
				break;
			case 'create-style' :
				if ( !$is_POST )
					return;

				check_admin_referer( 'create-style' );

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$polldaddy->reset();

				$style_data = _polldaddy_style_defaults();

				if ( isset( $_POST['style-title'] ) )
					$style_data['title'] = stripslashes( strip_tags( trim( (string) $_POST['style-title'] ) ) );

				if ( isset( $_POST['CSSXML'] ) )
					$style_data['css'] = urlencode( stripslashes( trim( (string) $_POST['CSSXML'] ) ) );

				$style = $polldaddy->create_style( $style_data );
				$this->parse_errors( $polldaddy );

				if ( !$style || empty( $style->_id ) )
					$this->errors->add( 'CreateStyle', __( 'Style could not be created', 'polldaddy' ) );

				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'created-style';
				$query_args['action'] = 'edit-style';
				$query_args['style'] = $style->_id;
				if ( isset( $_POST['iframe'] ) )
					$query_args['iframe'] = '';
				break;
			case 'update-options' :
				if ( !$is_POST )
					return;

				check_admin_referer( 'polldaddy-account' );

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$polldaddy->reset();

				$poll_defaults = _polldaddy_poll_defaults();

				$user_defaults = array();

				foreach ( array( "multipleChoice", "randomiseAnswers", "otherAnswer", "sharing", "resultsType", "styleID", "blockRepeatVotersType", "blockExpiration" ) as $option ) {
					if ( isset( $poll_defaults[$option] ) && $poll_defaults[$option] )
						$user_defaults[$option] = $poll_defaults[$option];
				}

				foreach ( array( 'multipleChoice', 'randomiseAnswers', 'otherAnswer', 'sharing' ) as $option ) {
					if ( isset( $_POST[$option] ) && $_POST[$option] )
						$user_defaults[$option] = 'yes';
					else
						$user_defaults[$option] = 'no';
				}

				$results = array( 'show', 'percent', 'hide' );
				if ( isset( $_POST['resultsType'] ) && in_array( $_POST['resultsType'], $results ) )
					$user_defaults['resultsType'] = $_POST['resultsType'];

				if ( isset ( $_POST['styleID'] ) ) {
					$user_defaults['styleID'] = (int) $_POST['styleID'];
				}

				$blocks = array( 'off', 'cookie', 'cookieip' );
				if ( isset( $_POST['blockRepeatVotersType'] ) && in_array( $_POST['blockRepeatVotersType'], $blocks ) )
					$user_defaults['blockRepeatVotersType'] = $_POST['blockRepeatVotersType'];

				if ( isset( $_POST['blockExpiration'] ) )
					$user_defaults['blockExpiration'] = (int) $_POST['blockExpiration'];

				$polldaddy->update_poll_defaults( 0, $user_defaults );

				$this->parse_errors( $polldaddy );
				if ( $this->errors->get_error_codes() )
					return false;

				$query_args['message'] = 'updated-options';
				break;
			default :
				return;
			}//end switch
		} elseif ( $page == 'ratings' ) {

			switch ( $action ) {
			case 'delete' :
				if ( empty( $id ) )
					return;
				if ( empty( $rating ) )
					return;

				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );

				if ( is_array( $rating ) ) {
					check_admin_referer( 'action-rating_bulk' );

					foreach ( $rating as $key => $value ) {
						$polldaddy->reset();
						$polldaddy->delete_rating_result( $id, $value );
					}
				} else {
					check_admin_referer( "delete-rating_$rating" );

					$polldaddy->delete_rating_result( $id, $rating );
				}

				if ( isset( $_REQUEST['filter'] ) )
					$query_args['filter'] = $_REQUEST['filter'];
				if ( isset( $_REQUEST['change-report-to'] ) )
					$query_args['change-report-to'] = $_REQUEST['change-report-to'];
				$query_args['message'] = 'deleted-rating';
				$query_args['deleted'] = count( (array) $rating );
				break;
			default :
				return;
			}//end switch
		}

		wp_safe_redirect( add_query_arg( $query_args, wp_get_referer() ) );
		exit;
	}
	
	function hide_admin_menu() {
		echo '<style>#adminmenuback,#adminmenuwrap,#screen-meta-links,#footer{display:none;visibility:hidden;}#wpcontent{margin-left:10px;}</style>';
	}

	function management_page_load_signup() {

		switch ( $_POST['account'] ) {
		case 'import' :
			return array( $this->import_account() );
			break;
		default :
			return;
		}//end switch
	}

	function import_account() {
		if ( isset( $_POST[ 'polldaddy_key' ] ) ) {
			$polldaddy_api_key = trim( stripslashes( $_POST[ 'polldaddy_key' ] ) );
			$polldaddy = $this->get_client( $polldaddy_api_key );
			$polldaddy->reset();
			if ( !$polldaddy->get_usercode( $this->id ) ) {
				$this->parse_errors( $polldaddy );
				$this->errors->add( 'GetUserCode', __( 'Account could not be accessed.  Is your API code correct?', 'polldaddy' ) );
				return false;
			}
			update_option( 'polldaddy_api_key', $polldaddy_api_key );
		} else {
			$this->user_code = false;
			$this->errors->add( 'import-account', __( 'Account could not be imported. Did you enter the correct API key?', 'polldaddy' ) );
			return false;
		}
	}

	function admin_body_class( $class ) {
		if ( isset( $_GET['iframe'] ) )
			$class .= 'poll-preview-iframe ';
		if ( isset( $_GET['TB_iframe'] ) )
			$class .= 'poll-preview-iframe-editor ';
		return $class;
	}

	function management_page_notices( $message = false ) {

		switch ( (string) @$_GET['message'] ) {
		case 'deleted' :
			$deleted = (int) $_GET['deleted'];
			if ( 1 == $deleted )
				$message = __( 'Poll deleted.', 'polldaddy' );
			else
				$message = sprintf( _n( '%s Poll Deleted.', '%s Polls Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
			break;
		case 'opened' :
			$opened = (int) $_GET['opened'];
			if ( 1 == $opened )
				$message = __( 'Poll opened.', 'polldaddy' );
			else
				$message = sprintf( _n( '%s Poll Opened.', '%s Polls Opened.', $opened, 'polldaddy' ), number_format_i18n( $opened ) );
			break;
		case 'closed' :
			$closed = (int) $_GET['closed'];
			if ( 1 == $closed )
				$message = __( 'Poll closed.', 'polldaddy' );
			else
				$message = sprintf( _n( '%s Poll Closed.', '%s Polls Closed.', $closed, 'polldaddy' ), number_format_i18n( $closed ) );
			break;
		case 'updated' :
			$message = __( 'Poll updated.', 'polldaddy' );
			break;
		case 'created' :
			$message = __( 'Poll created.', 'polldaddy' );
			if ( isset( $_GET['iframe'] ) )
				$message .= ' <input type="button" class="button polldaddy-send-to-editor" value="' . esc_attr( __( 'Embed in Post', 'polldaddy' ) ) . '" />';
			break;
		case 'updated-style' :
			$message = __( 'Custom Style updated.', 'polldaddy' );
			break;
		case 'created-style' :
			$message = __( 'Custom Style created.', 'polldaddy' );
			break;
		case 'deleted-style' :
			$deleted = (int) $_GET['deleted'];
			if ( 1 == $deleted )
				$message = __( 'Custom Style deleted.', 'polldaddy' );
			else
				$message = sprintf( _n( '%s Style Deleted.', '%s Custom Styles Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
			break;
		case 'imported-account' :
			$message = __( 'Account Linked.', 'polldaddy' );
			break;
		case 'updated-options' :
			$message = __( 'Options Updated.', 'polldaddy' );
			break;
		case 'deleted-rating' :
			$deleted = (int) $_GET['deleted'];
			if ( 1 == $deleted )
				$message = __( 'Rating deleted.', 'polldaddy' );
			else
				$message = sprintf( _n( '%s Rating Deleted.', '%s Ratings Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
			break;
		}//end switch

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $is_POST ) {
			switch ( $GLOBALS['action'] ) {
			case 'create-poll' :
				$message = __( 'Error: An error has occurred;  Poll not created.', 'polldaddy' );
				break;
			case 'edit-poll' :
				$message = __( 'Error: An error has occurred;  Poll not updated.', 'polldaddy' );
				break;
			case 'account' :
				if ( 'import' == $_POST['account'] )
					$message = __( 'Error: An error has occurred;  Account could not be imported.  Perhaps your email address or password is incorrect?', 'polldaddy' );
				else
					$message = __( 'Error: An error has occurred;  Account could not be created.', 'polldaddy' );
				break;
			}//end switch
		}

		if ( !$message )
			return;
?>
		<div class='updated'><p><?php echo $message; ?></p></div>
<?php
		$this->print_errors();
	}

	function management_page() {

		global $page, $action, $poll, $style, $rating;
		$poll = (int) $poll;
		$style = (int) $style;
		$rating = esc_html( $rating );
?>

	<div class="wrap" id="manage-polls">

<?php
		if ( $page == 'polls' ) {
			if ( !$this->is_author && in_array( $action, array( 'edit', 'edit-poll', 'create-poll', 'edit-style', 'create-style', 'list-styles', 'options', 'update-options', 'import-account' ) ) ) {//check user privileges has access to action
				$action = '';
			}
			switch ( $action ) {
			case 'signup' :
			case 'account' :
				$this->signup();
				break;
			case 'preview' :
				if ( isset( $_GET['iframe'] ) ):
					if ( !isset( $_GET['popup'] ) ) { ?>
				<h2 id="poll-list-header"><?php _e( 'Polldaddy Polls', 'polldaddy' ); ?></h2>	
<?php 
					} else { ?>
				<h2 id="poll-list-header"><?php printf( __( 'Preview Poll <a href="%s" class="add-new-h2">All Polls</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ) ); ?></h2>
<?php
					}
				endif;

				echo do_shortcode( "[polldaddy poll=$poll cb=1]" );
				
				wp_print_scripts( 'polldaddy-poll-js' );
				break;
			case 'results' :
?>

				<h2 id="poll-list-header"><?php printf( __( 'Poll Results <a href="%s" class="add-new-h2">All Polls</a> <a href="%s" class="add-new-h2">Edit Poll</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ), esc_url( add_query_arg( array( 'action' => 'edit-poll', 'poll' => $poll, 'message' => false ) ) ) ); ?></h2>

<?php
				$this->poll_results_page( $poll );
				break;
			case 'edit' :
			case 'edit-poll' :
?>

		<h2 id="poll-list-header"><?php printf( __( 'Edit Poll <a href="%s" class="add-new-h2">All Polls</a> <a href="%s" class="add-new-h2">View Results</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ), esc_url( add_query_arg( array( 'action' => 'results', 'poll' => $poll, 'message' => false ) ) ) ); ?></h2>

<?php

				$this->poll_edit_form( $poll );
				break;
			case 'create-poll' :
?>

		<h2 id="poll-list-header"><?php printf( __( 'Add New Poll <a href="%s" class="add-new-h2">All Polls</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ) ); ?></h2>

<?php
				$this->poll_edit_form();
				break;
			case 'list-styles' :
?>

		<h2 id="polldaddy-header"><?php
				if ( $this->is_author )
					printf( __( 'Custom Styles <a href="%s" class="add-new-h2">Add New</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'create-style', 'poll' => false, 'message' => false ) ) ) );
				else
					_e( 'Custom Styles', 'polldaddy' ); ?></h2>

<?php
				$this->styles_table();
				break;
			case 'edit-style' :
?>

		<h2 id="polldaddy-header"><?php printf( __( 'Edit Style <a href="%s" class="add-new-h2">List Styles</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?></h2>

<?php

				$this->style_edit_form( $style );
				break;
			case 'create-style' :
?>

		<h2 id="polldaddy-header"><?php printf( __( 'Create Style <a href="%s" class="add-new-h2">List Styles</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?></h2>

<?php
				$this->style_edit_form();
				break;
			case 'options' :
			case 'import-account' :
			case 'update-options' :
				$this->plugin_options();
				break;
			default :

?>

		<h2 id="poll-list-header"><?php
				if ( $this->is_author )
					printf( __( 'Polldaddy Polls <a href="%s" class="add-new-h2">Add New</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'create-poll', 'poll' => false, 'message' => false ) ) ) );
				else
					_e( 'Polldaddy Polls ', 'polldaddy' );
		?></h2><?php
				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
				$account = $polldaddy->get_account();
				if ( !empty( $account ) )
					$account_email = esc_attr( $account->email );
				if ( isset( $account_email ) && current_user_can( 'manage_options' ) ) {
					echo "<p>" . sprintf( __( 'Linked to WordPress.com Account: <strong>%s</strong> (<a target="_blank" href="options-general.php?page=polls&action=options">Settings</a> / <a target="_blank" href="http://polldaddy.com/dashboard/">Polldaddy.com</a>)', 'polldaddy' ), $account_email ) . "</p>";
				}

				if ( !isset( $_GET['view'] ) )
					$this->polls_table( 'user' );
				else
					$this->polls_table( 'blog' );

			}//end switch
		} elseif ( $page == 'ratings' ) {
			if ( !$this->is_admin && !in_array( $action, array( 'delete', 'reports' ) ) ) {//check user privileges has access to action
				$action = 'reports';
			}

			switch ( $action ) {
			case 'delete' :
			case 'reports' :
				$this->rating_reports();
				break;
			case 'update-rating' :
				$this->update_rating();
				$this->rating_settings();
				break;
			default :
				$this->rating_settings();
			}//end switch
		}
?>

	</div>

<?php

	}

	function polls_table( $view = 'user' ) {
		$page = 1;
		if ( isset( $_GET['paged'] ) )
			$page = absint( $_GET['paged'] );
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
		$polldaddy->reset();

		if ( 'user' == $view )
			$polls_object = $polldaddy->get_polls( ( $page - 1 ) * 10 + 1, $page * 10 );
		else
			$polls_object = $polldaddy->get_polls_by_parent_id( ( $page - 1 ) * 10 + 1, $page * 10 );

		$this->parse_errors( $polldaddy );
		$this->print_errors();
		$polls = & $polls_object->poll;
		if ( isset( $polls_object->_total ) )
			$total_polls = $polls_object->_total;
		else
			$total_polls = count( $polls );
		$class = '';

		$page_links = paginate_links( array(
				'base' => add_query_arg( 'paged', '%#%' ),
				'format' => '',
				'total' => ceil( $total_polls / 10 ),
				'current' => $page
			) );


?>
		<form method="post" action="">
		<input type="hidden" name="iframe" id="iframe1" value="<?php echo isset( $_GET['iframe'] ) ? 1: 0;?>">
		<div class="tablenav">

<?php if ( $this->is_author ) { ?>
			<div class="alignleft actions">
				<select name="action">
					<option selected="selected" value=""><?php _e( 'Actions', 'polldaddy' ); ?></option>
					<option value="delete"><?php _e( 'Delete', 'polldaddy' ); ?></option>
					<option value="close"><?php _e( 'Close', 'polldaddy' ); ?></option>
					<option value="open"><?php _e( 'Open', 'polldaddy' ); ?></option>
				</select>

				<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply', 'polldaddy' ); ?>" />
				<?php wp_nonce_field( 'action-poll_bulk' ); ?>
			</div>
			<div class="alignleft actions">
				<select name="filter" id="filter-options" style="margin-left:15px;">
					<option <?php if (!isset( $_GET['view'] ) ): ?> selected="selected" <?php endif; ?> value=""><?php _e( 'View All Polls', 'polldaddy' ); ?></option>
					<option <?php if ( $_GET['view'] == 'blog' ): ?> selected="selected" <?php endif; ?> value="blog"><?php _e( 'This Blog\'s Polls', 'polldaddy' ); ?></option>
				</select>
				<input class="button-secondary action" type="button" id="filter-polls" name="dofilter" value="<?php _e( 'Filter', 'polldaddy' ); ?>" />


			</div>


			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>

<?php } ?>
		<table class="widefat">
			<thead>
				<tr>
					<th id="cb" class="manage-column column-cb check-column" scope="col"><?php if ( $this->is_author ) { ?><input type="checkbox" /><?php } ?></th>
					<th id="title" class="manage-column column-title" scope="col"><?php _e( 'Poll', 'polldaddy' ); ?></th>
					<th id="votes" class="manage-column column-vote num" scope="col">&nbsp;</th>
				</tr>
			</thead>
			<tbody>

<?php
		if ( $polls ) :
			foreach ( $polls as $poll ) :
				$poll_id = (int) $poll->_id;

			$poll->___content = trim( strip_tags( $poll->___content ) );
		if ( strlen( $poll->___content ) == 0 ) {
			$poll->___content = '-- empty HTML tag --';
		}

		$poll_closed = (int) $poll->_closed;

		if ( $this->is_author and $this->can_edit( $poll ) ) {
			$edit_link = esc_url( add_query_arg( array( 'action' => 'edit', 'poll' => $poll_id, 'message' => false ) ) );
			$delete_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'poll' => $poll_id, 'message' => false ) ), "delete-poll_$poll_id" ) );
			$open_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'open', 'poll' => $poll_id, 'message' => false ) ), "open-poll_$poll_id" ) );
			$close_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'close', 'poll' => $poll_id, 'message' => false ) ), "close-poll_$poll_id" ) );
		}
		else {
			$edit_link = false;
			$delete_link = false;
			$open_link = false;
			$close_link = false;
		}

		$class = $class ? '' : ' class="alternate"';
		$results_link = esc_url( add_query_arg( array( 'action' => 'results', 'poll' => $poll_id, 'message' => false ) ) );
		$preview = array( 'action' => 'preview', 'poll' => $poll_id, 'message' => false );
		
		if ( isset( $_GET['iframe'] ) ) {
			$preview[ 'popup' ] = 1;
		}
		
		$preview_link = esc_url( add_query_arg( $preview ) );
		
		list( $poll_time ) = explode( '.', $poll->_created );
		$poll_time = strtotime( $poll_time );
?>
				<tr<?php echo $class; ?>>
					<th class="check-column" scope="row"><?php if ( $this->is_author and $this->can_edit( $poll ) ) { ?><input type="checkbox" value="<?php echo (int) $poll_id; ?>" name="poll[]" /><?php } ?></th>
					<td class="post-title column-title" style="padding-top:7px;">
<?php if ( $edit_link ) { ?>
						<a class="row-title" style="display:block;" href="<?php echo $edit_link; ?>"><strong><?php echo esc_html( $poll->___content ); ?></strong></a>

						<abbr title="<?php echo date( __( 'Y/m/d g:i:s A', 'polldaddy' ), $poll_time ); ?>"> <?php _e( 'created', 'polldaddy' ); ?> <?php echo date( __( 'M d, Y', 'polldaddy' ), $poll_time ); ?></abbr>

						<div class="row-actions">
						<span class="edit"><a href="<?php echo $edit_link; ?>"><?php _e( 'Edit', 'polldaddy' ); ?></a></span><span> | </span>
<?php } else { ?>
						<strong><?php echo esc_html( $poll->___content ); ?></strong>
						<div class="row-actions">

<?php } ?>
					
<?php if ( !isset( $_GET['iframe'] ) ):?>
						<span class="shortcode"><a href="javascript:void(0);" class="polldaddy-show-shortcode"><?php _e( 'Embed &amp; Link', 'polldaddy' ); ?></a></span>
<?php else: ?>
						<input type="hidden" class="polldaddy-poll-id" value="<?php echo $poll_id; ?>" />
						<span><a href="javascript:void(0);" class="polldaddy-send-to-editor"><?php _e( 'Embed in Post', 'polldaddy' ); ?></a></span>
<?php endif; ?>


<?php
		if ( $poll_closed == 2 ) {
			if ( $open_link ) { ?>
						<span> | </span><span class="open"><a class="open-poll" href="<?php echo $open_link; ?>"><?php _e( 'Open', 'polldaddy' ); ?></a></span>
<?php } } else {
			if ( $close_link ) { ?>
						<span> | </span><span class="close"><a class="close-poll" href="<?php echo $close_link; ?>"><?php _e( 'Close', 'polldaddy' ); ?></a></span>
<?php } }
		if ( !isset( $_GET['iframe'] ) ): ?>
						<span> | </span><span class="view"><a class="thickbox" href="<?php echo $preview_link; ?>"><?php _e( 'Preview', 'polldaddy' ); ?></a></span>
<?php   else: ?>
						<span> | </span><span class="view"><a href="<?php echo $preview_link; ?>"><?php _e( 'Preview', 'polldaddy' ); ?></a></span>
<?php   endif;
		if ( $delete_link ) { ?>
						<span> | </span><span class="delete"><a class="delete-poll delete" href="<?php echo $delete_link; ?>"><?php _e( 'Delete', 'polldaddy' ); ?></a></span>
<?php	}
		if ( $poll->_responses > 0 ):?>
						<span> | </span><span class="results"><a href="<?php echo $results_link; ?>"><?php _e( 'Results', 'polldaddy' ); ?></a></span>		
<?php   endif; ?>

<?php $this->poll_table_add_option( $poll_id ); ?>
          	</div>
          </td>
                                        <td class="poll-votes column-vote num"><?php echo number_format_i18n( $poll->_responses ); ?><span class="votes-label"><?php _e( 'votes', 'polldaddy' ); ?></span></td>
                                </tr>
                                <tr class="polldaddy-shortcode-row <?php if ( $class ): ?> alternate <?php endif; ?>" style="display: none;">
                                    <td colspan="4" style="padding:10px 0px 10px 20px;">

										<a style="display:block;font-size:12px;font-weight:bold;"  href="<?php echo $edit_link; ?>"><?php echo esc_html( $poll->___content ); ?></a>

                                    	<div class="pd-embed-col">
                                        	<h4 style="color:#666;font-weight:normal;"><?php _e( 'WordPress Shortcode', 'polldaddy' ); ?></h4>
                                        	<input type="text" readonly="readonly" style="width: 175px;" onclick="this.select();" value="[polldaddy poll=<?php echo (int) $poll_id; ?>]"/>
                                        </div>

                                        <div class="pd-embed-col">
	                                        <h4 style="color:#666;font-weight:normal;"><?php _e( 'Short URL (Good for Twitter etc.)', 'polldaddy' ); ?></h4>
											<input type="text" readonly="readonly" style="width: 175px;" onclick="this.select();" value="http://poll.fm/<?php echo base_convert( $poll_id, 10, 36 ); ?>"/>

                                        </div>

                                       	<div class="pd-embed-col">
											<h4 style="color:#666;font-weight:normal;"><?php _e( 'Facebook URL', 'polldaddy' ); ?></h4>
											<input type="text" readonly="readonly" style="width: 175px;" onclick="this.select();" value="http://poll.fm/f/<?php echo base_convert( $poll_id, 10, 36 ); ?>"/>
                                        </div>

                                        <br class="clearing" />


                                        <h4 style="padding-top:10px;color:#666;font-weight:normal;"><?php _e( 'JavaScript', 'polldaddy' ); ?></h4>
                                        <pre class="hardbreak" style="max-width:542px;text-wrap:word-wrap;margin-bottom:20px;">&lt;script type="text/javascript" language="javascript"
src="http://static.polldaddy.com/p/<?php echo (int) $poll_id; ?>.js"&gt;&lt;/script&gt;
&lt;noscript&gt;
&lt;a href="http://polldaddy.com/poll/<?php echo (int) $poll_id; ?>/"&gt;<?php echo trim( strip_tags( $poll->___content ) ); ?>&lt;/a&gt;&lt;br/&gt;
&lt;span style="font:9px;"&gt;(&lt;a href="http://www.polldaddy.com"&gt;polls&lt;/a&gt;)&lt;/span&gt;
&lt;/noscript&gt;</pre>
						<p class="submit" style="clear:both;padding:0px;">
							<a href="#" class="button pd-embed-done"><?php _e( 'Done', 'polldaddy' ); ?></a>
						</p>

					</td>
				</tr>

<?php
		endforeach;
		elseif ( $total_polls ) : // $polls
?>

				<tr>
					<td colspan="4"><?php printf( __( 'What are you doing here?  <a href="%s">Go back</a>.', 'polldaddy' ), esc_url( add_query_arg( 'paged', false ) ) ); ?></td>
				</tr>

<?php
		else : // $polls
?>

				<tr>
					<td colspan="4" id="empty-set"><?php
			if ( $this->is_author ) { ?>

				<h3 style="margin-bottom:0px;"><?php _e( 'You haven\'t created any polls for this blog.', 'polldaddy');?> </h3>
				<p style="margin-bottom:20px;"><?php _e( 'Why don\'t you go ahead and get started on that?', 'polldaddy' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'create-poll' ) ) ); ?>" class="button-primary"><?php _e( 'Create a Poll Now', 'polldaddy' ); ?></a>

			<?php
			} else { ?>

				<p id="no-polls"><?php _e( 'No one has created any polls for this blog.', 'polldaddy' ); ?></p>

			<?php }
		?></td>
				</tr>
<?php  endif; // $polls ?>

			</tbody>
		</table>






		<?php $this->poll_table_extra(); ?>
		</form>
		<div class="tablenav" <?php if ( $page_links == '' ) { ?> style="display:none;" <?php }  ?> >
			<div class="tablenav-pages"><?php echo $page_links; ?></div>
		</div>




		<script type="text/javascript">
		jQuery( document ).ready(function(){
			plugin = new Plugin( {
				delete_rating: '<?php echo esc_js( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
				delete_poll: '<?php echo esc_js( __( 'Are you sure you want to delete the poll %s?', 'polldaddy' ) ); ?>',
				delete_answer: '<?php echo esc_js( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
				delete_answer_title: '<?php echo esc_js( __( 'delete this answer', 'polldaddy' ) ); ?>',
				standard_styles: '<?php echo esc_js( __( 'Standard Styles', 'polldaddy' ) ); ?>',
				custom_styles: '<?php echo esc_js( __( 'Custom Styles', 'polldaddy' ) ); ?>'
			} );

			jQuery( '#filter-polls' ).click( function(){


					if( jQuery( '#filter-options' ).val() == 'blog' ){
						window.location = '<?php echo add_query_arg( array( 'page' => 'polls', 'view' => 'blog' ), admin_url( 'admin.php' ) ); ?>';
					} else {
						window.location = '<?php echo add_query_arg( array( 'page' => 'polls' ), admin_url( 'admin.php' ) ); ?>';
					}



				} );


		});
		</script>

<?php
	}

	function poll_table_add_option() {}

	function poll_table_extra() {}

	function poll_edit_form( $poll_id = 1 ) {
		$poll_id = (int) $poll_id;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
		$polldaddy->reset();

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $poll_id ) {
			$poll = $polldaddy->get_poll( $poll_id );
			$this->parse_errors( $polldaddy );

			if ( !$this->can_edit( $poll ) ) {
				$this->errors->add( 'permission', __( 'You are not allowed to edit this poll.', 'polldaddy' ) );
			}

			if ( $poll_id == 1 ) {
				$poll->answers = array();
				$poll_id = 0;
			}

		} else {
			$poll = polldaddy_poll( array(), null, false );
		}

		$question = $is_POST ? esc_attr( stripslashes( $_POST['question'] ) ) : esc_attr( $poll->question );

		$answers = $media = $mediaType = array();
		if ( $is_POST ) {
			if ( isset( $_POST['mediaType'] ) )
				$mediaType = $_POST['mediaType'];

			if ( isset( $_POST['media'] ) ) {
				$mc = $_POST['media'];

				foreach ( $mc as $key => $value ) {
					if ( $mediaType[$key] == 1 ) {
						$media[$key] = $polldaddy->get_media( $value );
					}
				}
			}

			if ( isset( $_POST['answer'] ) )
				foreach ( $_POST['answer'] as $answer_id => $answer )
					$answers[esc_attr($answer_id)] = esc_attr( stripslashes($answer) );
		} elseif ( isset( $poll->answers->answer ) ) {
			foreach ( $poll->answers->answer as $answer ) {
				$answers[(int) $answer->_id] = esc_attr( $answer->text );

				if ( $answer->mediaType == 1 && !empty( $answer->mediaCode ) ) {
					$polldaddy->reset();
					$media[$answer->_id] = $polldaddy->get_media( $answer->mediaCode );
					$mediaType[$answer->_id] = 1;
				}
				elseif ( $answer->mediaType == 2 ) {
					$mediaType[$answer->_id] = 2;
				}
			}

			if ( isset( $poll->mediaCode ) && isset( $poll->mediaType ) ) {
				if ( $poll->mediaType == 1 && !empty( $poll->mediaCode ) ) {
					$polldaddy->reset();
					$media[999999999] = $polldaddy->get_media( $poll->mediaCode );
					$mediaType[999999999] = 1;
				}
				elseif ( $poll->mediaType == 2 ) {
					$mediaType[999999999] = 2;
				}
			}
		}
		$this->print_errors();
		
		$delete_media_link = '<a href="#" class="delete-media delete hidden" title="' . esc_attr( __( 'delete this image' ) ) . '"><img src="' . $this->base_url . 'img/icon-clear-search.png" width="16" height="16" /></a>';
?>

<form enctype="multipart/form-data" name="send-media" action="admin-ajax.php" method="post">
	<?php wp_nonce_field( 'send-media' ); ?>
	<input type="hidden" value="" name="action">
	<input type="hidden" value="<?php echo $this->user_code; ?>" name="uc">
	<input type="hidden" value="" name="attach-id">
	<input type="hidden" value="" name="media-id">
	<input type="hidden" value="" name="url">
</form>

<form name="add-answer" action="admin-ajax.php" method="post">
	<?php wp_nonce_field( 'add-answer' ); ?>
	<input type="hidden" value="" name="action">
	<input type="hidden" value="" name="aa">
	<input type="hidden" value="" name="src">
	<input type="hidden" value="<?php echo isset( $_GET['iframe'] ) ? '1': '0';?>" name="popup">
</form>

<form action="" method="post">
<div id="poststuff"><div id="post-body" class="has-sidebar has-right-sidebar">

<div class="inner-sidebar" id="side-info-column">
	<div id="submitdiv" class="postbox">
		<h3><?php _e( 'Save', 'polldaddy' ); ?></h3>
		<div class="inside">
		<div class="minor-publishing">

						<ul id="answer-options">

<?php
		foreach ( array(  'randomiseAnswers' => __( 'Randomize answer order', 'polldaddy' ), 'otherAnswer' => __( 'Allow other answers', 'polldaddy' ), 'multipleChoice' => __( 'Multiple choice', 'polldaddy' ), 'sharing' => __( 'Sharing', 'polldaddy' ) ) as $option => $label ) :
			if ( $is_POST )
				$checked = 'yes' === $_POST[$option] ? ' checked="checked"' : '';
			else
				$checked = 'yes' === $poll->$option ? ' checked="checked"' : '';
?>

			<li>
				<label for="<?php echo $option; ?>"><input type="checkbox"<?php echo $checked; ?> value="yes" id="<?php echo $option; ?>" name="<?php echo $option; ?>" /> <?php echo esc_html( $label ); ?></label>
			</li>

<?php  endforeach; ?>

		</ul>
		<?php
		if ( $is_POST )
			$style = 'yes' === $_POST['multipleChoice'] ? 'display:block;' : 'display:none;';
		else
			$style = 'yes' === $poll->multipleChoice ? 'display:block;' : 'display:none;';
?>
		<div id="numberChoices" name="numberChoices" style="padding-left:15px;<?php echo $style; ?>">
			<p><?php _e( 'Number of choices', 'polldaddy' ) ?>: <select name="choices" id="choices"><option value="0"><?php _e( 'No Limit', 'polldaddy' ) ?></option>
				<?php
		if ( $is_POST )
			$choices = (int) $_POST['choices'];
		else
			$choices = (int) $poll->choices;

		$a = count( $answers ) - 1;

		if ( $a > 1 ) :
			for ( $i=2; $i<=$a; $i++ ) :
			$selected = $i == $choices ? 'selected="selected"' : '';
		printf( "<option value='%d' %s>%d</option>", $i, $selected, $i );
		endfor;
		endif; ?>
				</select>
			</p>
		</div>
	</div>



			<div id="major-publishing-actions">







				<p id="publishing-action">



					<?php wp_nonce_field( $poll_id ? "edit-poll_$poll_id" : 'create-poll' ); ?>
					<input type="hidden" name="action" value="<?php echo $poll_id ? 'edit-poll' : 'create-poll'; ?>" />
					<input type="hidden" class="polldaddy-poll-id" name="poll" value="<?php echo $poll_id; ?>" />
					<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Poll', 'polldaddy' ) ); ?>" />

<?php if ( isset( $_GET['iframe'] ) && $poll_id ) : ?>
					<div id="delete-action">
					<input type="button" class="button polldaddy-send-to-editor" style="margin-top:8px;" value="<?php echo esc_attr( __( 'Embed in Post', 'polldaddy' ) ); ?>" />
					</div>
<?php endif; ?>

				</p>
				<br class="clear" />
			</div>
		</div>
	</div>

	<div class="postbox">
		<h3><?php _e( 'Results Display', 'polldaddy' ); ?></h3>
		<div class="inside">
			<ul class="poll-options">

<?php
		foreach ( array( 'show' => __( 'Show results to voters', 'polldaddy' ), 'percent' => __( 'Only show percentages', 'polldaddy' ), 'hide' => __( 'Hide all results', 'polldaddy' ) ) as $value => $label ) :
			if ( $is_POST )
				$checked = $value === $_POST['resultsType'] ? ' checked="checked"' : '';
			else
				$checked = $value === $poll->resultsType ? ' checked="checked"' : '';
?>

				<li>
				<label for="resultsType-<?php echo $value; ?>"><input type="radio"<?php echo $checked; ?> value="<?php echo $value; ?>" name="resultsType" id="resultsType-<?php echo $value; ?>" /> <?php echo esc_html( $label ); ?></label>
				</li>

<?php   endforeach; ?>

			</ul>
		</div>
	</div>

	<div class="postbox">
		<h3><?php _e( 'Repeat Voting', 'polldaddy' ); ?></h3>
		<div class="inside">
			<ul class="poll-options">

<?php
		foreach ( array( 'off' => __( "Don't block repeat voters", 'polldaddy' ), 'cookie' => __( 'Block by cookie (recommended)', 'polldaddy' ), 'cookieip' => __( 'Block by cookie and by IP address', 'polldaddy' ) ) as $value => $label ) :
			if ( $is_POST )
				$checked = $value === $_POST['blockRepeatVotersType'] ? ' checked="checked"' : '';
			else
				$checked = $value === $poll->blockRepeatVotersType ? ' checked="checked"' : '';
?>

				<li>
					<label for="blockRepeatVotersType-<?php echo $value; ?>"><input class="block-repeat" type="radio"<?php echo $checked; ?> value="<?php echo $value; ?>" name="blockRepeatVotersType" id="blockRepeatVotersType-<?php echo $value; ?>" /> <?php echo esc_html( $label ); ?></label>
				</li>

<?php   endforeach; ?>

			</ul>

<?php 
		if ( $poll->blockExpiration == 0 || $poll->blockExpiration > 604800 )
			$poll->blockExpiration = 604800;
?>
			<span style="margin:6px 6px 8px;<?php echo $poll->blockRepeatVotersType == 'off' ? 'display:none;' : ''; ?>" id="cookieip_expiration_label"><label><?php _e( 'Expires: ', 'polldaddy' ); ?></label></span>
			<select id="cookieip_expiration" name="cookieip_expiration" style="width: auto;<?php echo $poll->blockRepeatVotersType == 'off' ? 'display:none;' : ''; ?>">
				<option value="3600" <?php echo (int) $poll->blockExpiration == 3600 ? 'selected' : ''; ?>><?php printf( __( '%d hour', 'polldaddy' ), 1 ); ?></option>
				<option value="10800" <?php echo (int) $poll->blockExpiration == 10800 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 3 ); ?></option>
				<option value="21600" <?php echo (int) $poll->blockExpiration == 21600 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 6 ); ?></option>
				<option value="43200" <?php echo (int) $poll->blockExpiration == 43200 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 12 ); ?></option>
				<option value="86400" <?php echo (int) $poll->blockExpiration == 86400 ? 'selected' : ''; ?>><?php printf( __( '%d day', 'polldaddy' ), 1 ); ?></option>
				<option value="604800" <?php echo (int) $poll->blockExpiration == 604800 ? 'selected' : ''; ?>><?php printf( __( '%d week', 'polldaddy' ), 1 ); ?></option>
			</select>
			<p><?php _e( 'Note: Blocking by cookie and IP address can be problematic for some voters.', 'polldaddy' ); ?></p>
		</div>
	</div>

	<div class="postbox">
		<h3><?php _e( 'Comments', 'polldaddy' ); ?></h3>
		<div class="inside">
			<ul class="poll-options">

<?php
		foreach ( array( 'allow' => __( "Allow comments", 'polldaddy' ), 'moderate' => __( 'Moderate first', 'polldaddy' ), 'off' => __( 'No comments', 'polldaddy' ) ) as $value => $label ) :
			if ( $is_POST )
				$checked = $value === $_POST['comments'] ? ' checked="checked"' : '';
			else
				$checked = $value === $poll->comments->___content ? ' checked="checked"' : '';
?>

				<li>
					<label for="comments-<?php echo $value; ?>"><input type="radio"<?php echo $checked; ?> value="<?php echo $value; ?>" name="comments" id="comments-<?php echo $value; ?>" /> <?php echo esc_html( $label ); ?></label>
				</li>

<?php   endforeach; ?>

			</ul>
		</div>
	</div>
</div>


<div id="post-body-content" class="has-sidebar-content">

	<div id="titlediv" style="margin-top:0px;">
		<div id="titlewrap">

			<table class="question">

				<tr>
					<td class="question-input">
						<input type="text" autocomplete="off" id="title" placeholder="<?php _e( 'Enter Question Here', 'polldaddy' ); ?>" value="<?php echo $question; ?>" tabindex="1" size="30" name="question" />
					</td>
					<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width: 55px !important;"' : '';?>>
					<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : '';?>>
<?php  if ( isset( $mediaType[999999999] ) && $mediaType[999999999] == 2 ) { ?>
				<li class="media-preview image-added" style="width: 20px; height: 16px; padding-left: 5px;"><img height="16" width="16" src="<?php echo $this->base_url; ?>img/icon-report-ip-analysis.png" alt="Video Embed"><?php echo $delete_media_link;?></li>
<?php   } else {
			$url = '';
			if ( isset($media[999999999]) ) {
				$url = urldecode( $media[999999999]->img_small );
				
				if ( is_ssl() )
					$url = preg_replace( '/http\:/', 'https:', $url );
			}?>
			<li class="media-preview <?php echo !empty( $url ) ? 'image-added' : ''; ?>" style="width: 20px; height: 16px; padding-left: 5px;"><?php echo $url; ?><?php echo $delete_media_link;?></li>				
<?php   }

		if ( !isset( $_GET['iframe'] ) ) : ?>
				<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></li>
				<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></li>
				<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
<?php   endif; ?>
			</ul>

				<input type="hidden" value="<?php echo isset( $media[999999999] ) ? $media[999999999]->_id : ''; ?>" id="hMC999999999" name="media[999999999]">
				<input type="hidden" value="<?php echo isset( $mediaType[999999999] ) ? $mediaType[999999999] : ''; ?>" id="hMT999999999" name="mediaType[999999999]">

					</td>
				</tr>
			</table>

			<?php if ( isset( $poll->_id ) && !isset( $_GET['iframe']) ): ?>
				<div class="inside">
					<div id="edit-slug-box" style="margin-bottom:30px;">
						<strong><?php _e( 'WordPress Shortcode:', 'polldaddy' ); ?></strong>
						<input type="text" style="color:#999;" value="[polldaddy poll=<?php echo $poll->_id; ?>]" id="shortcode-field" readonly="readonly" />
						<span><a href="post-new.php?content=[polldaddy poll=<?php echo $poll->_id; ?>]" class="button"><?php _e( 'Embed Poll in New Post' ); ?></a></span>
					</div>
				</div>
			<?php endif; ?>

		</div>
	</div>

	<div id="answersdiv" class="postbox">
		<h3><?php _e( 'Answers', 'polldaddy' ); ?></h3>

		<div id="answerswrap" class="inside">
		<ul id="answers">
<?php
		$a = 0;
		foreach ( $answers as $answer_id => $answer ) :
			$a++;
		$delete_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete-answer', 'poll' => $poll_id, 'answer' => $answer_id, 'message' => false ) ), "delete-answer_$answer_id" ) );
?>

			<li>


				<table class="answer">

						<tr>
							<th>
								<span class="handle" title="<?php echo esc_attr( __( 'click and drag to reorder' ) ); ?>"><img src="<?php echo $this->base_url; ?>img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span>
							</th>
							<td class="answer-input">
								<input type="text" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>" id="answer-<?php echo $answer_id; ?>" value="<?php echo $answer; ?>" tabindex="2" size="30" name="answer[<?php echo $answer_id; ?>]" />
							</td>
							<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width: 55px !important;"' : '';?>>
							<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : '';?>>
<?php  if ( isset( $mediaType[$answer_id] ) && $mediaType[$answer_id] == 2 ) { ?>
						<li class="media-preview image-added" style="width: 20px; height: 16px; padding-left: 5px;"><img height="16" width="16" src="<?php echo $this->base_url; ?>img/icon-report-ip-analysis.png" alt="Video Embed"><?php echo $delete_media_link;?></li>
<?php   } else {
			$url = '';
			if ( isset($media[$answer_id]) ) {
				$url = urldecode( $media[$answer_id]->img_small );
					
				if ( is_ssl() )
					$url = preg_replace( '/http\:/', 'https:', $url );
			}?>
						<li class="media-preview <?php echo !empty( $url ) ? 'image-added' : ''; ?>" style="width: 20px; height: 16px; padding-left: 5px;"><?php echo $url; ?><?php echo $delete_media_link;?></li>
<?php   }

		if ( !isset( $_GET['iframe'] ) ) : ?>
						<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image<?php echo $answer_id; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></li>
						<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video<?php echo $answer_id; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></li>
						<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio<?php echo $answer_id; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
<?php   endif; ?>
						<li><a href="<?php echo $delete_link; ?>" class="delete-answer delete" title="<?php echo esc_attr( __( 'delete this answer' ) ); ?>"><img src="<?php echo $this->base_url; ?>img/icon-clear-search.png" width="16" height="16" /></a></li>

					</ul>

						<input type="hidden" value="<?php echo isset( $media[$answer_id] ) ? $media[$answer_id]->_id : ''; ?>" id="hMC<?php echo $answer_id; ?>" name="media[<?php echo $answer_id; ?>]">
						<input type="hidden" value="<?php echo isset( $mediaType[$answer_id] ) ? $mediaType[$answer_id] : ''; ?>" id="hMT<?php echo $answer_id; ?>" name="mediaType[<?php echo $answer_id; ?>]">

							</td>
						</tr>
					</table>


								</li>

<?php
		endforeach;

		while ( 3 - $a > 0 ) :
			$a++;
?>

			<li>
				<table class="answer">

						<tr>
							<th>
								<span class="handle" title="<?php echo esc_attr( __( 'click and drag to reorder' ) ); ?>"><img src="<?php echo $this->base_url; ?>img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span>
							</th>
							<td class="answer-input">
								<input type="text" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>" value="" tabindex="2" size="30" name="answer[new<?php echo $a; ?>]" />
							</td>
							<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width:55px !important;"' : '';?>>
								<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : '';?>>
									<li class="media-preview" style="width: 20px; height: 16px; padding-left: 5px;"></li>
<?php
		if ( !isset( $_GET['iframe'] ) ) : ?>
									<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image<?php echo $a; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></a></li>
									<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video<?php echo $a; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></a></li>
									<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio<?php echo $a; ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
<?php   endif; ?>
									<li><a href="#" class="delete-answer delete" title="<?php echo esc_attr( __( 'delete this answer' ) ); ?>"><img src="<?php echo $this->base_url; ?>img/icon-clear-search.png" width="16" height="16" /></a></li>
								</ul>

									<input type="hidden" value="" id="hMC<?php echo $a; ?>" name="media[<?php echo $a; ?>]">
									<input type="hidden" value="" id="hMT<?php echo $a; ?>" name="mediaType[<?php echo $a; ?>]">

							</td>
						</tr>


				</table>





			</li>

<?php
		endwhile;
?>

		</ul>

		<p id="add-answer-holder" class="<?php echo $this->base_url; ?>">
			<button class="button"><?php echo esc_html( __( 'Add New Answer', 'polldaddy' ) ); ?></button>
		</p>

		</div>
	</div>
	
	<div class="hidden-links"><div class="delete-media-link"><?php echo $delete_media_link;?></div></div>

	<div id="design" class="postbox">

<?php $style_ID = (int) ( $is_POST ? $_POST['styleID'] : $poll->styleID );

		$iframe_view = false;
		if ( isset( $_GET['iframe'] ) )
			$iframe_view = true;

		$options = array(
			101 => __( 'Aluminum Narrow', 'polldaddy' ),
			102 => __( 'Aluminum Medium', 'polldaddy' ),
			103 => __( 'Aluminum Wide', 'polldaddy' ),
			104 => __( 'Plain White Narrow', 'polldaddy' ),
			105 => __( 'Plain White Medium', 'polldaddy' ),
			106 => __( 'Plain White Wide', 'polldaddy' ),
			107 => __( 'Plain Black Narrow', 'polldaddy' ),
			108 => __( 'Plain Black Medium', 'polldaddy' ),
			109 => __( 'Plain Black Wide', 'polldaddy' ),
			110 => __( 'Paper Narrow', 'polldaddy' ),
			111 => __( 'Paper Medium', 'polldaddy' ),
			112 => __( 'Paper Wide', 'polldaddy' ),
			113 => __( 'Skull Dark Narrow', 'polldaddy' ),
			114 => __( 'Skull Dark Medium', 'polldaddy' ),
			115 => __( 'Skull Dark Wide', 'polldaddy' ),
			116 => __( 'Skull Light Narrow', 'polldaddy' ),
			117 => __( 'Skull Light Medium', 'polldaddy' ),
			118 => __( 'Skull Light Wide', 'polldaddy' ),
			157 => __( 'Micro', 'polldaddy' ),
			119 => __( 'Plastic White Narrow', 'polldaddy' ),
			120 => __( 'Plastic White Medium', 'polldaddy' ),
			121 => __( 'Plastic White Wide', 'polldaddy' ),
			122 => __( 'Plastic Grey Narrow', 'polldaddy' ),
			123 => __( 'Plastic Grey Medium', 'polldaddy' ),
			124 => __( 'Plastic Grey Wide', 'polldaddy' ),
			125 => __( 'Plastic Black Narrow', 'polldaddy' ),
			126 => __( 'Plastic Black Medium', 'polldaddy' ),
			127 => __( 'Plastic Black Wide', 'polldaddy' ),
			128 => __( 'Manga Narrow', 'polldaddy' ),
			129 => __( 'Manga Medium', 'polldaddy' ),
			130 => __( 'Manga Wide', 'polldaddy' ),
			131 => __( 'Tech Dark Narrow', 'polldaddy' ),
			132 => __( 'Tech Dark Medium', 'polldaddy' ),
			133 => __( 'Tech Dark Wide', 'polldaddy' ),
			134 => __( 'Tech Grey Narrow', 'polldaddy' ),
			135 => __( 'Tech Grey Medium', 'polldaddy' ),
			136 => __( 'Tech Grey Wide', 'polldaddy' ),
			137 => __( 'Tech Light Narrow', 'polldaddy' ),
			138 => __( 'Tech Light Medium', 'polldaddy' ),
			139 => __( 'Tech Light Wide', 'polldaddy' ),
			140 => __( 'Working Male Narrow', 'polldaddy' ),
			141 => __( 'Working Male Medium', 'polldaddy' ),
			142 => __( 'Working Male Wide', 'polldaddy' ),
			143 => __( 'Working Female Narrow', 'polldaddy' ),
			144 => __( 'Working Female Medium', 'polldaddy' ),
			145 => __( 'Working Female Wide', 'polldaddy' ),
			146 => __( 'Thinking Male Narrow', 'polldaddy' ),
			147 => __( 'Thinking Male Medium', 'polldaddy' ),
			148 => __( 'Thinking Male Wide', 'polldaddy' ),
			149 => __( 'Thinking Female Narrow', 'polldaddy' ),
			150 => __( 'Thinking Female Medium', 'polldaddy' ),
			151 => __( 'Thinking Female Wide', 'polldaddy' ),
			152 => __( 'Sunset Narrow', 'polldaddy' ),
			153 => __( 'Sunset Medium', 'polldaddy' ),
			154 => __( 'Sunset Wide', 'polldaddy' ),
			155 => __( 'Music Medium', 'polldaddy' ),
			156 => __( 'Music Wide', 'polldaddy' )
		);

		$polldaddy->reset();
		$styles = $polldaddy->get_styles();

		$show_custom = false;
		if ( !empty( $styles ) && !empty( $styles->style ) && count( $styles->style ) > 0 ) {
			foreach ( (array) $styles->style as $style ) {
				$options[ (int) $style->_id ] = $style->title;
			}
			$show_custom = true;
		}

		if ( $style_ID > 18 ) {
			$standard_style_ID = 0;
			$custom_style_ID = $style_ID;
		}
		else {
			$standard_style_ID = $style_ID;
			$custom_style_ID = 0;
		}
?>
		<h3><?php _e( 'Poll Style', 'polldaddy' ); ?></h3>
		<input type="hidden" name="styleID" id="styleID" value="<?php echo $style_ID ?>">
		<div class="inside">

			<ul class="pd-tabs">
				<li class="selected" id="pd-styles"><a href="#"><?php _e( 'Polldaddy Styles', 'polldaddy' ); ?></a><input type="checkbox" style="display:none;" id="regular"/></li>
				<?php $hide = $show_custom == true ? ' style="display:block;"' : ' style="display:none;"'; ?>
				<li id="pd-custom-styles" <?php echo $hide; ?>><a href="#"><?php _e( 'Custom Styles', 'polldaddy' ); ?></a><input type="checkbox" style="display:none;" id="custom"/></li>

			</ul>

			<div class="pd-tab-panel show" id="pd-styles-panel">


				<?php if ( $iframe_view ) { ?>
				<div id="design_standard" style="padding:0px;padding-top:10px;">
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
								<td class="selector" style="width:120px;">
									<table class="st_selector">
										<tr>
											<td class="dir_left" style="padding:0px;width:30px;">
												<a href="javascript:pd_move('prev');" style="display: block;font-size: 3.2em;text-decoration: none;">&#171;</a>
											</td>
											<td class="img"><div class="st_image_loader"><div id="st_image" onmouseover="st_results(this, 'show');" onmouseout="st_results(this, 'hide');"></div></div></td>
											<td class="dir_right" style="padding:0px;width:30px;">
												<a href="javascript:pd_move('next');" style="display: block;padding-left:20px;font-size: 3.2em;text-decoration: none;">&#187;</a>
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
												<div style="width:230px;" id="st_description"></div>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</div>

					<p class="empty-if-js" id="no-js-styleID">
						<select id="styleID" name="styleID">

					<?php  foreach ( $options as $styleID => $label ) :
				$selected = $styleID == $style_ID ? ' selected="selected"' : ''; ?>
							<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo esc_html( $label ); ?></option>
					<?php  endforeach; ?>

						</select>
					</p>
				</div>
				<?php } else {?>

					<div class="design_standard">
						<div class="hide-if-no-js">
						<table class="pollStyle">
							<thead>
								<tr style="display:none;">
									<th class="cb">

										<input type="radio" name="styleTypeCB" id="regular" onclick="javascript:pd_build_styles( 0 );"/>
										<label for="skin" onclick="javascript:pd_build_styles( 0 );"><?php _e( 'Polldaddy Style', 'polldaddy' ); ?></label>

										<?php $disabled = $show_custom == false ? ' disabled="true"' : ''; ?>

										<input type="radio" name="styleTypeCB" id="custom" onclick="javascript:pd_change_style(_$('customSelect').value);" <?php echo $disabled; ?> />

										<label onclick="javascript:pd_change_style(_$('customSelect').value);"><?php _e( 'Custom Style', 'polldaddy' ); ?></label>

									<th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td style="text-align:center">
										<table class="st_selector" style="margin:20px auto;">
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
							</tbody>
						</table>
						</div>
						<p class="empty-if-js" id="no-js-styleID">
							<select id="styleID" name="styleID">

						<?php  foreach ( $options as $styleID => $label ) :
				$selected = $styleID == $style_ID ? ' selected="selected"' : ''; ?>
								<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo esc_html( $label ); ?></option>
						<?php  endforeach; ?>

							</select>
						</p>
					</div>
				<?php } ?>




			</div>


			<div class="pd-tab-panel" id="pd-custom-styles-panel">
				<div  style="padding:20px;">
					<?php  if ( $show_custom ) : ?>
					<p><a href="<?php echo esc_url( add_query_arg( array( 'action' => 'list-styles', 'poll' => false, 'style' => false, 'message' => false, 'preload' => false ) ) );?>" class="add-new-h2">All Styles</a></p>
					<select id="customSelect" name="customSelect" onchange="javascript:pd_change_style(this.value);">
						<?php  $selected = $custom_style_ID == 0 ? ' selected="selected"' : ''; ?>
								<option value="x"<?php echo $selected; ?>><?php _e( 'Please choose a custom style…', 'polldaddy' ); ?></option>
						<?php  foreach ( (array)$styles->style as $style ) :
				$selected = $style->_id == $custom_style_ID ? ' selected="selected"' : ''; ?>
								<option value="<?php echo (int) $style->_id; ?>"<?php echo $selected; ?>><?php echo esc_html( $style->title ); ?></option>
						<?php endforeach; ?>
					</select>
					<div id="styleIDErr" class="formErr" style="display:none;"><?php _e( 'Please choose a style.', 'polldaddy' ); ?></div>
					<?php else : ?>
					<p><?php _e( 'You currently have no custom styles created.', 'polldaddy' ); ?> <a href="/wp-admin/edit.php?page=polls&amp;action=create-style" class="add-new-h2"><?php _e( 'New Style', 'polldaddy');?></a></p>
					<p><?php printf( __( 'Did you know we have a new editor for building your own custom poll styles? Find out more <a href="%s" target="_blank">here</a>.', 'polldaddy' ), 'http://support.polldaddy.com/custom-poll-styles/' ); ?></p>
					<?php endif; ?>
				</div>




			</div>




				<script language="javascript">
			jQuery( document ).ready(function(){
				plugin = new Plugin( {
					delete_rating: '<?php echo esc_attr( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
					delete_poll: '<?php echo esc_attr( __( 'Are you sure you want to delete "%s"?', 'polldaddy' ) ); ?>',
					delete_answer: '<?php echo esc_attr( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
		            new_answer_test: '<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>',
		            delete_answer_title: '<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>',
		            reorder_answer_title: '<?php echo esc_attr( __( 'click and drag to reorder', 'polldaddy' ) ); ?>',
		            add_image_title: '<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>',
		            add_audio_title: '<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>',
		            add_video_title: '<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>',
		            standard_styles: '<?php echo esc_attr( __( 'Standard Styles', 'polldaddy' ) ); ?>',
		            custom_styles: '<?php echo esc_attr( __( 'Custom Styles', 'polldaddy' ) ); ?>',
		            base_url: '<?php echo esc_attr( $this->base_url ); ?>'
				} );
			});
			</script>
			<script language="javascript">
			current_pos = 0;

			for( var key in styles_array ) {
				var name = styles_array[key].name;

				switch( name ){
					case 'Aluminum':
						styles_array[key].name = '<?php echo esc_attr( __( 'Aluminum', 'polldaddy' ) ); ?>';
						break;
					case 'Plain White':
						styles_array[key].name = '<?php echo esc_attr( __( 'Plain White', 'polldaddy' ) ); ?>';
						break;
					case 'Plain Black':
						styles_array[key].name = '<?php echo esc_attr( __( 'Plain Black', 'polldaddy' ) ); ?>';
						break;
					case 'Paper':
						styles_array[key].name = '<?php echo esc_attr( __( 'Paper', 'polldaddy' ) ); ?>';
						break;
					case 'Skull Dark':
						styles_array[key].name = '<?php echo esc_attr( __( 'Skull Dark', 'polldaddy' ) ); ?>';
						break;
					case 'Skull Light':
						styles_array[key].name = '<?php echo esc_attr( __( 'Skull Light', 'polldaddy' ) ); ?>';
						break;
					case 'Micro':
						styles_array[key].name = '<?php echo esc_attr( __( 'Micro', 'polldaddy' ) ); ?>';
						styles_array[key].n_desc = '<?php echo esc_attr( __( 'Width 150px, the micro style is useful when space is tight.', 'polldaddy' ) ); ?>';
						break;
					case 'Plastic White':
						styles_array[key].name = '<?php echo esc_attr( __( 'Plastic White', 'polldaddy' ) ); ?>';
						break;
					case 'Plastic Grey':
						styles_array[key].name = '<?php echo esc_attr( __( 'Plastic Grey', 'polldaddy' ) ); ?>';
						break;
					case 'Plastic Black':
						styles_array[key].name = '<?php echo esc_attr( __( 'Plastic Black', 'polldaddy' ) ); ?>';
						break;
					case 'Manga':
						styles_array[key].name = '<?php echo esc_attr( __( 'Manga', 'polldaddy' ) ); ?>';
						break;
					case 'Tech Dark':
						styles_array[key].name = '<?php echo esc_attr( __( 'Tech Dark', 'polldaddy' ) ); ?>';
						break;
					case 'Tech Grey':
						styles_array[key].name = '<?php echo esc_attr( __( 'Tech Grey', 'polldaddy' ) ); ?>';
						break;
					case 'Tech Light':
						styles_array[key].name = '<?php echo esc_attr( __( 'Tech Light', 'polldaddy' ) ); ?>';
						break;
					case 'Working Male':
						styles_array[key].name = '<?php echo esc_attr( __( 'Working Male', 'polldaddy' ) ); ?>';
						break;
					case 'Working Female':
						styles_array[key].name = '<?php echo esc_attr( __( 'Working Female', 'polldaddy' ) ); ?>';
						break;
					case 'Thinking Male':
						styles_array[key].name = '<?php echo esc_attr( __( 'Thinking Male', 'polldaddy' ) ); ?>';
						break;
					case 'Thinking Female':
						styles_array[key].name = '<?php echo esc_attr( __( 'Thinking Female', 'polldaddy' ) ); ?>';
						break;
					case 'Sunset':
						styles_array[key].name = '<?php echo esc_attr( __( 'Sunset', 'polldaddy' ) ); ?>';
						break;
					case 'Music':
						styles_array[key].name = '<?php echo esc_attr( __( 'Music', 'polldaddy' ) ); ?>';
						break;
				}
			}
			pd_map = {
				wide : '<?php echo esc_attr( __( 'Wide', 'polldaddy' ) ); ?>',
				medium : '<?php echo esc_attr( __( 'Medium', 'polldaddy' ) ); ?>',
				narrow : '<?php echo esc_attr( __( 'Narrow', 'polldaddy' ) ); ?>',
				style_desc_wide : '<?php echo esc_attr( __( 'Width: 630px, the wide style is good for blog posts.', 'polldaddy' ) ); ?>',
				style_desc_medium : '<?php echo esc_attr( __( 'Width: 300px, the medium style is good for general use.', 'polldaddy' ) ); ?>',
				style_desc_narrow : '<?php echo esc_attr( __( 'Width 150px, the narrow style is good for sidebars etc.', 'polldaddy' ) ); ?>',
				style_desc_micro : '<?php echo esc_attr( __( 'Width 150px, the micro style is useful when space is tight.', 'polldaddy' ) ); ?>',
				image_path : '<?php echo plugins_url( 'img', __FILE__ );?>'
			}
			pd_build_styles( current_pos );
			<?php if ( $style_ID > 0 && $style_ID <= 1000 ) { ?>
			pd_pick_style( <?php echo $style_ID ?> );
			<?php }else { ?>
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
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
		$polldaddy->reset();

		$results = $polldaddy->get_poll_results( $poll_id );
		$poll = $polldaddy->get_poll( $poll_id );

?>
		<h3 style="line-height:21px;"><?php echo $poll->question;  ?></h3>
		<table class="poll-results widefat">
			<thead>
				<tr>
					<th scope="col" class="column-title" style="width:40%;"><?php _e( 'Answer', 'polldaddy' ); ?></th>
					<th scope="col" class="column-vote" style="width:10%;text-align:center;"><?php _e( 'Votes', 'polldaddy' ); ?></th>
					<th scope="col" class="column-vote" style="width:10%;text-align:center;"><?php _e( 'Percent', 'polldaddy' ); ?></th>
					<th scope="col" class="column-vote" style="width:40%;">&nbsp;</th>
				</tr>
			</thead>
			<tbody>

<?php
		$class = '';
		foreach ( $results->answers as $answer ) :
			$answer->text = trim( strip_tags( $answer->text ) );
		if ( strlen( $answer->text ) == 0 ) {
			$answer->text = '-- empty HTML tag --';
		}

		$class = $class ? '' : ' class="alternate"';
		$content = $results->others && 'Other answer…' === $answer->text ? sprintf( __( 'Other (<a href="%s">see below</a>)', 'polldaddy' ), '#other-answers-results' ) : esc_html( $answer->text );

?>

				<tr<?php echo $class; ?>>
					<th scope="row" style="vertical-align:bottom" class="column-title"><?php echo $content; ?></th>
					<td class="column-vote" style="text-align:center;vertical-align:middle;">
						<?php echo number_format_i18n( $answer->_total ); ?>
					</td>
					<td style="text-align:center;vertical-align:middle;">
						<?php echo number_format_i18n( $answer->_percent ); ?>%
					</td>
					<td style="vertical-align:middle;">
						<span class="result-bar" style="width: <?php echo number_format( $answer->_percent, 2 ); ?>%;">&nbsp;</span>
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
					<th scope="col" class="column-title"><?php _e( 'Other Answer', 'polldaddy' ); ?></th>
					<th scope="col" class="column-vote"><?php _e( 'Votes', 'polldaddy' ); ?></th>
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
					<th scope="row" class="column-title"><?php echo esc_html( $other ); ?></th>
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
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
		$polldaddy->reset();

		$styles_object = $polldaddy->get_styles();

		$this->parse_errors( $polldaddy );
		$this->print_errors();
		$styles = & $styles_object->style;
		$class = '';
		$styles_exist = false;

		foreach ( (array)$styles as $style ) :
			if ( (int) $style->_type == 1 ):
				$styles_exist = true;
			break;
		endif;
		endforeach;
?>

		<form method="post" action="">
		<div class="tablenav">
			<div class="alignleft">
				<select name="action">
					<option selected="selected" value=""><?php _e( 'Actions', 'polldaddy' ); ?></option>
					<option value="delete-style"><?php _e( 'Delete', 'polldaddy' ); ?></option>
				</select>
				<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply', 'polldaddy' ); ?>" />
				<?php wp_nonce_field( 'action-style_bulk' ); ?>
			</div>
			<div class="tablenav-pages"></div>
		</div>

		<table class="widefat">
			<thead>
				<tr>
					<th id="cb" class="manage-column column-cb check-column" scope="col"><input type="checkbox" /></th>
					<th id="title" class="manage-column column-title" scope="col"><?php _e( 'Style', 'polldaddy' ); ?></th>
					<th id="date" class="manage-column column-date" scope="col"><?php _e( 'Last Modified', 'polldaddy' ); ?></th>
				</tr>
			</thead>
			<tbody>

<?php
		if ( $styles_exist ) :
			foreach ( $styles as $style ) :
				if ( (int) $style->_type == 1 ):
					$style_id = (int) $style->_id;

				$class = $class ? '' : ' class="alternate"';
			$edit_link = esc_url( add_query_arg( array( 'action' => 'edit-style', 'style' => $style_id, 'message' => false ) ) );
		$delete_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete-style', 'style' => $style_id, 'message' => false ) ), "delete-style_$style_id" ) );
		list( $style_time ) = explode( '.', $style->date );
		$style_time = strtotime( $style_time );
?>

					<tr<?php echo $class; ?>>
						<th class="check-column" scope="row"><input type="checkbox" value="<?php echo (int) $style_id; ?>" name="style[]" /></th>
						<td class="post-title column-title">
	<?php     if ( $edit_link ) : ?>
							<strong><a class="row-title" href="<?php echo $edit_link; ?>"><?php echo esc_html( $style->title ); ?></a></strong>
							<div class="row-actions">
							<span class="edit"><a href="<?php echo $edit_link; ?>"><?php _e( 'Edit', 'polldaddy' ); ?></a> | </span>
	<?php     else : ?>
							<strong><?php echo esc_html( $style->title ); ?></strong>
	<?php     endif; ?>

							<span class="delete"><a class="delete-poll delete" href="<?php echo $delete_link; ?>"><?php _e( 'Delete', 'polldaddy' ); ?></a></span>
							</div>
						</td>
						<td class="date column-date"><abbr title="<?php echo date( __( 'Y/m/d g:i:s A', 'polldaddy' ), $style_time ); ?>"><?php echo date( __( 'Y/m/d', 'polldaddy' ), $style_time ); ?></abbr></td>
					</tr>

	<?php
		endif;
		endforeach;
		else : // $styles
?>

				<tr>
					<td colspan="4" id="empty-set">

						<h3 style="margin-bottom:0px;"><?php _e( 'You haven\'t used our fancy style editor to create any custom styles!', 'polldaddy');?> </h3>
						<p style="margin-bottom:20px;"><?php _e( 'Why don\'t you go ahead and get started on that?', 'polldaddy' ); ?></p>
						<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'create-style' ) ) ); ?>" class="button-primary"><?php _e( 'Create a Custom Style Now', 'polldaddy' ); ?></a>

					</td>
				</tr>
<?php  endif; // $styles ?>

			</tbody>
		</table>
		</form>
		<div class="tablenav">
			<div class="tablenav-pages"></div>
		</div>
		<br class="clear" />

<?php
	}

	function style_edit_form( $style_id = 105 ) {
		$style_id = (int) $style_id;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
		$polldaddy->reset();

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( $style_id ) {
			$style = $polldaddy->get_style( $style_id );
			$this->parse_errors( $polldaddy );
		} else {
			$style = polldaddy_style( array(), null, false );
		}

		$style->css = trim( urldecode( $style->css ) );
		
		$direction = 'ltr';
		if ( in_array( $style->_direction, array( 'ltr', 'rtl') ) )
			$direction = $style->_direction;
		
		if ( $start = stripos( $style->css, '<data>' ) )
			$style->css = substr( $style->css, $start );

		$style->css = addslashes( $style->css );

		$preload_style_id = 0;
		$preload_style = null;

		if ( isset ( $_REQUEST['preload'] ) ) {
			$preload_style_id = (int) $_REQUEST['preload'];

			if ( $preload_style_id > 1000 || $preload_style_id < 100 )
				$preload_style_id = 0;

			if ( $preload_style_id > 0 ) {
				$polldaddy->reset();
				$preload_style = $polldaddy->get_style( $preload_style_id );
				$this->parse_errors( $polldaddy );
			}

			$preload_style->css = trim( urldecode( $preload_style->css ) );

			if ( $start = stripos( $preload_style->css, '<data>' ) )
				$preload_style->css = substr( $preload_style->css, $start );

			$style->css = addslashes( $preload_style->css );
		}

		$this->print_errors();

		echo '<script language="javascript">var CSSXMLString = "' . $style->css .'";</script>';
?>

	<form action="" method="post">
	<div id="poststuff">
		<div id="post-body">
			<br/>
			<table>
				<tr>
					<td class="pd-editor-label">
						<label class="CSSE_title_label"><?php _e( 'Style Name', 'polldaddy' ); ?></label>
					</td>
					<td>
						<div id="titlediv" style="margin:0px;">
							<div id="titlewrap">
								<input type="text" autocomplete="off" value="<?php echo $style_id > 1000 ? $style->title : ''; ?>" tabindex="1" style="width:25em;" name="style-title" />
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<td class="pd-editor-label">
						<label class="CSSE_title_label"><?php _e( 'Preload Basic Style', 'polldaddy' ); ?></label>
					</td>
					<td>
						<div class="CSSE_preload">
							<select id="preload_value">
								<option value="0"></option>
								<option value="102"><?php _e( 'Aluminum', 'polldaddy' ); ?></option>
								<option value="105"><?php _e( 'Plain White', 'polldaddy' ); ?></option>
								<option value="108"><?php _e( 'Plain Black', 'polldaddy' ); ?></option>
								<option value="111"><?php _e( 'Paper', 'polldaddy' ); ?></option>
								<option value="114"><?php _e( 'Skull Dark', 'polldaddy' ); ?></option>
								<option value="117"><?php _e( 'Skull Light', 'polldaddy' ); ?></option>
								<option value="157"><?php _e( 'Micro', 'polldaddy' ); ?></option>
							</select>
							<a tabindex="4" id="style-preload" href="javascript:preload_style();" class="button"><?php echo esc_attr( __( 'Load Style', 'polldaddy' ) ); ?></a>								
						</div>
					</td>
				</tr>
				<tr>
					<td class="pd-editor-label">
						<label class="CSSE_title_label"><?php _e( 'Text Direction', 'polldaddy' ); ?></label>
					</td>
					<td>
						<div class="CSSE_rtl_ltr">
							<a tabindex="4" id="style-force-rtl" href="#" onclick="javascript:force_rtl();" class="button" style="<?php echo $direction == 'rtl' ? 'display:none;' : '' ;?>"><?php echo esc_attr( __( 'Force RTL', 'polldaddy' ) ); ?></a>
							<a tabindex="4" id="style-force-ltr" href="#" onclick="javascript:force_ltr();" class="button" style="<?php echo $direction == 'ltr' ? 'display:none;' : '' ;?>"><?php echo esc_attr( __( 'Force LTR', 'polldaddy' ) ); ?></a>
						</div>
					</td>
				</tr>
			</table>

			<h3><?php _e( 'Style Editor', 'polldaddy' ); ?></h3>

			<table>
				<tr>
					<td class="pd-editor-label"><label for="styleName"><?php _e( 'Select a template part to edit:' ); ?></label></td>
					<td>
						<select id="styleName" onchange="renderStyleEdit(this.value);">
							<option value="pds-box" selected="selected"><?php _e( 'Poll Box', 'polldaddy' ); ?></option>
							<option value="pds-question-top"><?php _e( 'Question', 'polldaddy' ); ?></option>
							<option value="pds-answer-group"><?php _e( 'Answer Group', 'polldaddy' ); ?></option>
							<option value="pds-answer-input"><?php _e( 'Answer Check', 'polldaddy' ); ?></option>
							<option value="pds-answer"><?php _e( 'Answers', 'polldaddy' ); ?></option>
							<option value="pds-textfield"><?php _e( 'Other Input', 'polldaddy' ); ?></option>
							<option value="pds-vote-button"><?php _e( 'Vote Button', 'polldaddy' ); ?></option>
							<option value="pds-link"><?php _e( 'Links', 'polldaddy' ); ?></option>
							<option value="pds-feedback-group"><?php _e( 'Feedback Group', 'polldaddy' ); ?></option>
							<option value="pds-feedback-result"><?php _e( 'Results Group', 'polldaddy' ); ?></option>
							<option value="pds-feedback-per"><?php _e( 'Results Percent', 'polldaddy' ); ?></option>
							<option value="pds-feedback-votes"><?php _e( 'Results Votes', 'polldaddy' ); ?></option>
							<option value="pds-answer-text"><?php _e( 'Results Text', 'polldaddy' ); ?></option>
							<option value="pds-answer-feedback"><?php _e( 'Results Background', 'polldaddy' ); ?></option>
							<option value="pds-answer-feedback-bar"><?php _e( 'Results Bar', 'polldaddy' ); ?></option>
							<option value="pds-totalvotes-inner"><?php _e( 'Total Votes', 'polldaddy' ); ?></option>
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
										<a href="javascript:CSSE_changeView('Font');" id="A_Font" class="Aoff"><?php _e( 'Font', 'polldaddy' ); ?></a>
									</div>
									<div class="on" id="D_Background">
										<a href="javascript:CSSE_changeView('Background');" id="A_Background" class="Aon"><?php _e( 'Background', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Border">
										<a href="javascript:CSSE_changeView('Border');" id="A_Border" class="Aoff"><?php _e( 'Border', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Margin">
										<a href="javascript:CSSE_changeView('Margin');" id="A_Margin" class="Aoff"><?php _e( 'Margin', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Padding">
										<a href="javascript:CSSE_changeView('Padding');" id="A_Padding" class="Aoff"><?php _e( 'Padding', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Scale">
										<a href="javascript:CSSE_changeView('Scale');" id="A_Scale" class="Aoff"><?php _e( 'Width', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Height">
										<a href="javascript:CSSE_changeView('Height');" id="A_Height" class="Aoff"><?php _e( 'Height', 'polldaddy' ); ?></a>
									</div>
									<div class="off" id="D_Position">
										<a href="javascript:CSSE_changeView('Position');" id="A_Position" class="Aoff"><?php _e( 'Position', 'polldaddy' ); ?></a>
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
														<td width="85"><?php _e( 'Font Size', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Font Size', 'polldaddy' ); ?></td>
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
														<td><?php _e( 'Color', 'polldaddy' ); ?> (#hex):</td>
														<td>
															<input type="text" maxlength="11" id="color" class="elmColor jscolor-picker" onblur="bind(this);" style="float:left;"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Bold', 'polldaddy' ); ?>:</td>
														<td>
															<input type="checkbox" id="font-weight" value="bold" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Italic', 'polldaddy' ); ?>:</td>
														<td>
															<input type="checkbox" id="font-style" value="italic" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Underline', 'polldaddy' ); ?>:</td>
														<td>
															<input type="checkbox" id="text-decoration" value="underline" onclick="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Line Height', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Align', 'polldaddy' ); ?>:</td>
														<td>
															<select id="text-align" onchange="bind(this);">
																<option value="left"><?php _e( 'Left', 'polldaddy' ); ?></option>
																<option value="center"><?php _e( 'Center', 'polldaddy' ); ?></option>
																<option value="right"><?php _e( 'Right', 'polldaddy' ); ?></option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Background Table -->
												<table class="CSSE_edit" id="editBackground" style="display:none;">
													<tr>
														<td width="85"><?php _e( 'Color', 'polldaddy' ); ?> (#hex):</td>
														<td>
															<input type="text" maxlength="11" id="background-color" class="elmColor jscolor-picker" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Image URL', 'polldaddy' ); ?>: <a href="http://support.polldaddy.com/custom-poll-styles/" class="noteLink" title="<?php _e( 'Click here for more information', 'polldaddy' ); ?>">(?)</a></td>
														<td>
															<input type="text" id="background-image" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Image Repeat', 'polldaddy' ); ?>:</td>
														<td>
															<select id="background-repeat" onchange="bind(this);">
																<option value="repeat"><?php _e( 'repeat', 'polldaddy' ); ?></option>
																<option value="no-repeat"><?php _e( 'no-repeat', 'polldaddy' ); ?></option>
																<option value="repeat-x"><?php _e( 'repeat-x', 'polldaddy' ); ?></option>
																<option value="repeat-y"><?php _e( 'repeat-y', 'polldaddy' ); ?></option>
															</select>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Image Position', 'polldaddy' ); ?>:</td>
														<td>
															<select id="background-position" onchange="bind(this);">
																<option value="left top"><?php _e( 'left top', 'polldaddy' ); ?></option>
																<option value="left center"><?php _e( 'left center', 'polldaddy' ); ?></option>
																<option value="left bottom"><?php _e( 'left bottom', 'polldaddy' ); ?></option>
																<option value="center top"><?php _e( 'center top', 'polldaddy' ); ?></option>
																<option value="center center"><?php _e( 'center center', 'polldaddy' ); ?></option>
																<option value="center bottom"><?php _e( 'center bottom', 'polldaddy' ); ?></option>
																<option value="right top"><?php _e( 'right top', 'polldaddy' ); ?></option>
																<option value="right center"><?php _e( 'right center', 'polldaddy' ); ?></option>
																<option value="right bottom"><?php _e( 'right bottom', 'polldaddy' ); ?></option>
															</select>
														</td>
													</tr>
												</table>
	<!-- Border Table -->
												<table class="CSSE_edit" id="editBorder" style="display:none;">
													<tr>
														<td width="85"><?php _e( 'Width', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Style', 'polldaddy' ); ?>:</td>
														<td>
															<select id="border-style" onchange="bind(this);">
																<option value="none"><?php _e( 'none', 'polldaddy' ); ?></option>
																<option value="solid"><?php _e( 'solid', 'polldaddy' ); ?></option>
																<option value="dotted"><?php _e( 'dotted', 'polldaddy' ); ?></option>
																<option value="dashed"><?php _e( 'dashed', 'polldaddy' ); ?></option>
																<option value="double"><?php _e( 'double', 'polldaddy' ); ?></option>
																<option value="groove"><?php _e( 'groove', 'polldaddy' ); ?></option>
																<option value="inset"><?php _e( 'inset', 'polldaddy' ); ?></option>
																<option value="outset"><?php _e( 'outset', 'polldaddy' ); ?></option>
																<option value="ridge"><?php _e( 'ridge', 'polldaddy' ); ?></option>
																<option value="hidden"><?php _e( 'hidden', 'polldaddy' ); ?></option>
															</select>
														</td>
													</tr>
													<tr>
														<td><?php _e( 'Color', 'polldaddy' ); ?> (#hex):</td>
														<td>
															<input type="text" maxlength="11" class="elmColor jscolor-picker" id="border-color" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td width="85"><?php _e( 'Rounded Corners', 'polldaddy' ); ?>:</td>
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
															<?php _e( 'Not supported in Internet Explorer.', 'polldaddy' ); ?>
														</td>
													</tr>
												</table>
	<!-- Margin Table -->
												<table class="CSSE_edit" id="editMargin" style="display:none;">
													<tr>
														<td width="85"><?php _e( 'Top', 'polldaddy' ); ?>: </td>
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
														<td><?php _e( 'Right', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Bottom', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Left', 'polldaddy' ); ?>:</td>
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
														<td width="85"><?php _e( 'Top', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Right', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Bottom', 'polldaddy' ); ?>:</td>
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
														<td><?php _e( 'Left', 'polldaddy' ); ?>:</td>
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
														<td width="85"><?php _e( 'Width', 'polldaddy' ); ?> (px):  <a href="http://support.polldaddy.com/custom-poll-styles/" class="noteLink" title="<?php _e( 'Click here for more information', 'polldaddy' ); ?>">(?)</a></td>
														<td>
															<input type="text" maxlength="4" class="elmColor" id="width" onblur="bind(this);"/>
														</td>
													</tr>
													<tr>
														<td width="85"></td>
														<td>
															<?php _e( 'If you change the width of the<br/> poll you may also need to change<br/> the width of your answers.', 'polldaddy' ); ?>
														</td>
													</tr>
												</table>

	<!-- Height Table -->
												<table class="CSSE_edit" id="editHeight" style="display:none;">
													<tr>
														<td width="85"><?php _e( 'Height', 'polldaddy' ); ?> (px):</td>
														<td>
															<input type="text" maxlength="4" class="elmColor" id="height" onblur="bind(this);"/>
														</td>
													</tr>
												</table>
							
												<table class="CSSE_edit" id="editPosition" style="display:none;">
													<tr>
														<td width="85"><?php _e( 'Position', 'polldaddy' ); ?> (px):</td>
														<td>
															<select class="set-width" id="float" onchange="bind(this);">
																<option value="left">Left</option>
																<option value="right">Right</option>
															</select>
															<input type="hidden" id="position" />
															<input type="hidden" id="direction" />
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
						<div style="overflow-x:auto;">
							<!-- POLL XHTML START -->
									<div class="pds-box" id="pds-box">
										<div class="pds-box-outer">
											<div class="pds-box-inner">
												<div class="pds-box-top">
													<div class="pds-question">
														<div class="pds-question-outer">
															<div class="pds-question-inner">
																<div class="pds-question-top" id="pds-question-top"><?php _e( 'Do you mostly use the internet at work, in school or at home?', 'polldaddy' ); ?></div>
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
																	<label for="p1" class="pds-answer" id="pds-answer"><span class="pds-answer-span"><?php _e( 'I use it in school.', 'polldaddy' ); ?></span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group1">
																	<span class="pds-answer-input" id="pds-answer-input1">
																		<input type="radio" name="PDI_answer" value="2" id="p2" class="pds-checkbox"/>
																	</span>
																	<label for="p2" class="pds-answer" id="pds-answer1"><span class="pds-answer-span"><?php _e( 'I use it at home.', 'polldaddy' ); ?></span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group2">
																	<span class="pds-answer-input" id="pds-answer-input2">
																		<input type="radio" name="PDI_answer" value="3" id="p3" class="pds-checkbox"/>
																	</span>
																	<label for="p3" class="pds-answer" id="pds-answer2"><span class="pds-answer-span"><?php _e( 'I use it every where I go, at work and home and anywhere else that I can!', 'polldaddy' ); ?></span></label>
																	<span class="pds-clear"></span>
																</span>

																<span class="pds-answer-group" id="pds-answer-group3">
																	<span class="pds-answer-input" id="pds-answer-input3">
																		<input type="radio" name="PDI_answer" value="4" id="p4" class="pds-checkbox"/>
																	</span>
																	<label for="p4" class="pds-answer" id="pds-answer3"><span class="pds-answer-span"><?php _e( 'Other', 'polldaddy' ); ?>:</span></label>
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
																	<a href="javascript:renderStyleEdit('pds-answer-feedback');" id="pds-vote-button" style="display:block;float:left;" class="pds-vote-button"><span><?php _e( 'Vote', 'polldaddy' ); ?></span></a>
																	<span class="pds-links">
																		<div style="padding: 0px 0px 0px 15px; float:left;"><a href="javascript:renderStyleEdit('pds-answer-feedback');" class="pds-link" id="pds-link"><?php _e( 'View Results', 'polldaddy' ); ?></a></div>
																		<span class="pds-clear"></span>
																	</span>
																	<span class="pds-clear"></span>
																</div>
															</div>

														</div>
							<!-- End divAnswers -->
							<!-- divResults -->
														<div id="divResults">
														
															<div class="pds-feedback-group" id="pds-feedback-group" > 
																<label class="pds-feedback-label" id="pds-feedback-label"> 
																	<span class="pds-answer-text" id="pds-answer-text"><?php _e( 'I use it in school!', 'polldaddy' ); ?></span>
																	<span class="pds-feedback-result" id="pds-feedback-result">
																		<span class="pds-feedback-per" id="pds-feedback-per">&nbsp;46%</span>&nbsp;<span class="pds-feedback-votes" id="pds-feedback-votes"> <?php printf( __( '(%d votes)', 'polldaddy' ), 620 ); ?></span>
																	</span>
																</label>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
																<div class="pds-answer-feedback" id="pds-answer-feedback">
																	<div style="width:46%" class="pds-answer-feedback-bar" id="pds-answer-feedback-bar"></div>
																</div>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
															</div>
															
															<div class="pds-feedback-group" id="pds-feedback-group1"> 
																<label class="pds-feedback-label" id="pds-feedback-label1"> 
																	<span class="pds-answer-text" id="pds-answer-text1"><?php _e( 'I use it at home.', 'polldaddy' ); ?></span>
																	<span class="pds-feedback-result" id="pds-feedback-result1">
																		<span class="pds-feedback-per" id="pds-feedback-per1">&nbsp;30%</span>&nbsp;<span class="pds-feedback-votes" id="pds-feedback-votes1"> <?php printf( __( '(%d votes)', 'polldaddy' ), 400 ); ?></span>
																	</span>
																</label>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
																<div class="pds-answer-feedback" id="pds-answer-feedback1">
																	<div style="width:30%" class="pds-answer-feedback-bar" id="pds-answer-feedback-bar1"></div>
																</div>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
															</div>
															
															<div class="pds-feedback-group" id="pds-feedback-group2"> 
																<label class="pds-feedback-label" id="pds-feedback-label2"> 
																	<span class="pds-answer-text" id="pds-answer-text2"><?php _e( 'I use it every where I go, at work and home and anywhere else that I can!', 'polldaddy' ); ?></span>
																	<span class="pds-feedback-result" id="pds-feedback-result2">
																		<span class="pds-feedback-per" id="pds-feedback-per2">&nbsp;16%</span>&nbsp;<span class="pds-feedback-votes" id="pds-feedback-votes2"> <?php printf( __( '(%d votes)', 'polldaddy' ), 220 ); ?></span>
																	</span>
																</label>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
																<div class="pds-answer-feedback" id="pds-answer-feedback2">
																	<div style="width:16%" class="pds-answer-feedback-bar" id="pds-answer-feedback-bar2"></div>
																</div>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
															</div>
															
															<div class="pds-feedback-group" id="pds-feedback-group3"> 
																<label class="pds-feedback-label" id="pds-feedback-label3"> 
																	<span class="pds-answer-text" id="pds-answer-text3"><?php _e( 'Other', 'polldaddy' ); ?></span>
																	<span class="pds-feedback-result" id="pds-feedback-result3">
																		<span class="pds-feedback-per" id="pds-feedback-per3">&nbsp;8%</span>&nbsp;<span class="pds-feedback-votes" id="pds-feedback-votes3"> <?php printf( __( '(%d votes)', 'polldaddy' ), 110 ); ?></span>
																	</span>
																</label>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
																<div class="pds-answer-feedback" id="pds-answer-feedback3">
																	<div style="width:8%" class="pds-answer-feedback-bar" id="pds-answer-feedback-bar3"></div>
																</div>
																<span style="display: block;clear: both;height:1px;line-height:1px;" class="pds-clear">&nbsp;</span>
															</div>

														</div>
							<!-- End divResults -->
														<span class="pds-clear"></span>
														<div style="height: 10px;"></div>
														<div id="pds-totalvotes-inner"><?php _e( 'Total Votes', 'polldaddy' ); ?>: <strong>1,350</strong></div>
													</div>
													<div class="pds-vote" id="pds-links-back">
														<div class="pds-totalvotes-outer">
																<span class="pds-links-back">
																	<br/>
																	<a href="javascript:" class="pds-link" id="pds-link1"><?php _e( 'Comments', 'polldaddy' ); ?> <strong>(19)</strong></a>
																	<xsl:text> </xsl:text>
																	<a href="javascript:renderStyleEdit('pds-box');" class="pds-link" id="pds-link2"><?php _e( 'Return To Poll', 'polldaddy' ); ?></a>
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
			<p>
				<?php wp_nonce_field( $style_id > 1000 ? "edit-style$style_id" : 'create-style' ); ?>
				<input type="hidden" name="action" value="<?php echo $style_id > 1000 ? 'edit-style' : 'create-style'; ?>" />
				<input type="hidden" class="polldaddy-style-id" name="style" value="<?php echo $style_id; ?>" />
				<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Style', 'polldaddy' ) ); ?>" />
				<?php if ( $style_id > 1000 ) { ?>
				<input name="updatePollCheck" id="updatePollCheck" type="checkbox"> <label for="updatePollCheck"><?php _e( 'Check this box if you wish to update the polls that use this style.', 'polldaddy' ); ?></label>
				<?php } ?>
			</p>
		</div>
	</div>
	<textarea id="S_www" name="CSSXML" style="display:none;width: 1000px; height: 500px;" rows="10" cols="10"> </textarea>
	</form>
<script language="javascript">
	jQuery( document ).ready(function(){
		plugin = new Plugin( {
			delete_rating: '<?php echo esc_attr( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
			delete_poll: '<?php echo esc_attr( __( 'Are you sure you want to delete "%s"?', 'polldaddy' ) ); ?>',
			delete_answer: '<?php echo esc_attr( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
			delete_answer_title: '<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>',
			standard_styles: '<?php echo esc_attr( __( 'Standard Styles', 'polldaddy' ) ); ?>',
			custom_styles: '<?php echo esc_attr( __( 'Custom Styles', 'polldaddy' ) ); ?>'
		} );
	});
	pd_map = {
		thankyou : '<?php echo esc_attr( __( 'Thank you for voting!', 'polldaddy' ) ); ?>',
		question : '<?php echo esc_attr( __( 'Do you mostly use the internet at work, in school or at home?', 'polldaddy' ) ); ?>'
	}
</script>
<script type="text/javascript" language="javascript">window.onload = function() {
	var CSSXML;
	loadStyle();
	showResults( false );
	renderStyleEdit( _$('styleName').value );
}</script>
	<br class="clear" />

	<?php
	}

	function rating_settings() {
		global $action, $rating;
		$show_posts = $show_posts_index = $show_pages = $show_comments = $pos_posts = $pos_posts_index = $pos_pages = $pos_comments = 0;
		$show_settings = $rating_updated = ( $action == 'update-rating' ? true : false );
		$error = false;

		$settings_style = 'display: none;';
		if ( $show_settings )
			$settings_style = 'display: block;';

		$rating_id = get_option( 'pd-rating-posts-id' );
		$report_type = 'posts';
		$updated = false;

		if ( isset( $rating ) ) {
			switch ( $rating ) {
			case 'pages':
				$report_type = 'pages';
				$rating_id = (int) get_option( 'pd-rating-pages-id' );
				break;
			case 'comments':
				$report_type = 'comments';
				$rating_id = (int) get_option( 'pd-rating-comments-id' );
				break;
			case 'posts':
				$report_type = 'posts';
				$rating_id = (int) get_option( 'pd-rating-posts-id' );
				break;
			}//end switch
		}

		$new_type = 0;
		if ( $report_type == 'comments' )
			$new_type = 1;

		$blog_name = get_option( 'blogname' );

		if ( empty( $blog_name ) )
			$blog_name = 'WordPress Blog';
		$blog_name .= ' - ' . $report_type;

		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
		$polldaddy->reset();

		if ( empty( $rating_id ) ) {
			$pd_rating = $polldaddy->create_rating( $blog_name , $new_type );
			if ( !empty( $pd_rating ) ) {
				$rating_id = (int) $pd_rating->_id;
				update_option ( 'pd-rating-' . $report_type . '-id', $rating_id );
				update_option ( 'pd-rating-' . $report_type, 0 );
			}
		} else
			$pd_rating = $polldaddy->get_rating( $rating_id );

		if ( empty( $pd_rating ) || (int) $pd_rating->_id == 0 ) {
			
			$this->log( 'rating_settings: unable to get rating id - '.$rating_id );

			if ( $polldaddy->errors ) {
				if ( array_key_exists( 4, $polldaddy->errors ) ) { //Obsolete key
					$this->log( 'rating_settings: obsolete key - '.$this->rating_user_code );
					$this->rating_user_code = '';
					update_option( 'pd-rating-usercode', '' );
					$this->set_api_user_code();  // get latest key

					$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
					$polldaddy->reset();
					$pd_rating = $polldaddy->get_rating( $rating_id ); //see it exists

					if ( empty( $pd_rating ) || (int) $pd_rating->_id == 0 ) { //if not then create a rating for blog
						$polldaddy->reset();
						$pd_rating = $polldaddy->create_rating( $blog_name , $new_type );
					}
				}
			}

			if ( empty( $pd_rating ) ) { //something's up!
				echo '<div class="error" id="polldaddy"><p>'.sprintf( __( 'Sorry! There was an error creating your rating widget. Please contact <a href="%1$s" %2$s>Polldaddy support</a> to fix this.', 'polldaddy' ), 'http://polldaddy.com/feedback/', 'target="_blank"' ) . '</p></div>';
				$error = true;
			} else {
				$rating_id = (int) $pd_rating->_id;
				update_option ( 'pd-rating-' . $report_type . '-id', $rating_id );
				update_option ( 'pd-rating-' . $report_type, 0 );

				switch ( $report_type ) {
				case 'posts':
					$show_posts = 0;
					break;
				case 'pages':
					$show_pages = 0;
					break;
				case 'comments':
					$show_comments = 0;
					break;
				}//end switch
			}
		}

		if ( isset( $_POST[ 'pd_rating_action_type' ] ) ) {
			check_admin_referer( 'action-rating_settings_' . $_POST[ 'pd_rating_action_type' ] );

			switch ( $_POST[ 'pd_rating_action_type' ]  ) {
			case 'posts' :
				if ( isset( $_POST[ 'pd_show_posts' ] ) && (int) $_POST[ 'pd_show_posts' ] == 1 )
					$show_posts = get_option( 'pd-rating-posts-id' );

				update_option( 'pd-rating-posts', $show_posts );

				if ( isset( $_POST[ 'pd_show_posts_index' ] ) && (int) $_POST[ 'pd_show_posts_index' ] == 1 )
					$show_posts_index = get_option( 'pd-rating-posts-id' );

				update_option( 'pd-rating-posts-index', $show_posts_index );

				if ( isset( $_POST[ 'posts_pos' ] ) && (int) $_POST[ 'posts_pos' ] == 1 )
					$pos_posts = 1;

				update_option( 'pd-rating-posts-pos', $pos_posts );

				if ( isset( $_POST[ 'posts_index_pos' ] ) && (int) $_POST[ 'posts_index_pos' ] == 1 )
					$pos_posts_index = 1;

				update_option( 'pd-rating-posts-index-pos', $pos_posts_index );
				$rating_updated = true;
				break;

			case 'pages';
				if ( isset( $_POST[ 'pd_show_pages' ] ) && (int) $_POST[ 'pd_show_pages' ] == 1 )
					$show_pages = get_option( 'pd-rating-pages-id' );

				update_option( 'pd-rating-pages', $show_pages );

				if ( isset( $_POST[ 'pages_pos' ] ) && (int) $_POST[ 'pages_pos' ] == 1 )
					$pos_pages = 1;

				update_option( 'pd-rating-pages-pos', $pos_pages );
				$rating_updated = true;
				break;

			case 'comments':
				if ( isset( $_POST[ 'pd_show_comments' ] ) && (int) $_POST[ 'pd_show_comments' ] == 1 )
					$show_comments = get_option( 'pd-rating-comments-id' );

				update_option( 'pd-rating-comments', $show_comments );

				if ( isset( $_POST[ 'comments_pos' ] ) && (int) $_POST[ 'comments_pos' ] == 1 )
					$pos_comments = 1;

				update_option( 'pd-rating-comments-pos', $pos_comments );

				$rating_updated = true;
				break;
			}//end switch
		}

		$show_posts       = (int) get_option( 'pd-rating-posts' );
		$show_pages       = (int) get_option( 'pd-rating-pages' );
		$show_comments    = (int) get_option( 'pd-rating-comments' );
		$show_posts_index = (int) get_option( 'pd-rating-posts-index' );

		$pos_posts        = (int) get_option( 'pd-rating-posts-pos' );
		$pos_pages        = (int) get_option( 'pd-rating-pages-pos' );
		$pos_comments     = (int) get_option( 'pd-rating-comments-pos' );
		$pos_posts_index  = (int) get_option( 'pd-rating-posts-index-pos' );

		if ( !empty( $pd_rating ) ) {
			$settings_text = $pd_rating->settings;
			$settings = json_decode( $settings_text );

			$popup_disabled = ( isset( $settings->popup ) && $settings->popup == 'off' );

			$rating_type = 0;

			if ( $settings->type == 'stars' )
				$rating_type = 0;
			else
				$rating_type = 1;

			if ( empty( $settings->font_color ) )
				$settings->font_color = '#000000';
		}?>
		<div class="wrap">
		  <div class="icon32" id="icon-options-general"><br/></div>
		  <h2>
		    <?php _e( 'Rating Settings', 'polldaddy' ); ?>
		  </h2>
		<?php if ( $rating_updated )
			echo '<div class="updated"><p>'.__( 'Rating updated', 'polldaddy' ).'</p></div>';

		if ( !$error ) { ?>
      <div id="side-sortables">
        <div id="categorydiv" class="categorydiv">
          <ul id="category-tabs" class="category-tabs wp-tab-bar"><?php
			$this_class = '';
			$posts_link = esc_url( add_query_arg( array( 'rating' => 'posts', 'message' => false ) ) );
			$pages_link = esc_url( add_query_arg( array( 'rating' => 'pages', 'message' => false ) ) );
			$comments_link = esc_url( add_query_arg( array( 'rating' => 'comments', 'message' => false ) ) );
			if ( $report_type == 'posts' )
				$this_class = ' class="tabs"';?>
            <li <?php echo $this_class; ?>><a tabindex="3" href="<?php echo $posts_link; ?>"><?php _e( 'Posts', 'polldaddy' );?></a></li><?php
			$this_class = '';
			if ( $report_type == 'pages' )
				$this_class = ' class="tabs"';  ?>
            <li <?php echo $this_class; ?>><a tabindex="3" href="<?php echo $pages_link; ?>"><?php _e( 'Pages', 'polldaddy' );?></a></li><?php
			$this_class = '';
			if ( $report_type == 'comments' )
				$this_class = ' class="tabs"';  ?>
            <li <?php echo $this_class; ?>><a href="<?php echo $comments_link; ?>"><?php _e( 'Comments', 'polldaddy' );?></a></li>
          </ul>
          <div class="tabs-panel" id="categories-all" style="background: #FFFFFF;height: auto; overflow: visible;max-height:400px;">
            <form action="" method="post">
            <input type="hidden" name="pd_rating_action_type" value="<?php echo $report_type; ?>" />
<?php wp_nonce_field( 'action-rating_settings_' . $report_type ); ?>
            <table class="form-table" style="width: normal;">
              <tbody><?php
			if ( $report_type == 'posts' ) { ?>
                <tr valign="top">
					<th scope="row"><label><?php _e( 'Show Ratings on', 'polldaddy' );?></label></th>
					<td>
						<label><input type="checkbox" name="pd_show_posts_index" id="pd_show_posts_index" <?php if ( $show_posts_index > 0 ) echo ' checked="checked" '; ?> value="1" /> <?php _e( 'Front Page, Archive Pages, and Search Results', 'polldaddy' );?></label>
						<br><label><input type="checkbox" name="pd_show_posts" id="pd_show_posts" <?php if ( $show_posts > 0 ) echo ' checked="checked" '; ?> value="1" /> <?php _e( 'Posts', 'polldaddy' );?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Position Front Page, Archive Pages, and Search Results Ratings', 'polldaddy' );?></label></th>
					<td>
						<select name="posts_index_pos"><?php
						$select = array( __( 'Above each blog post', 'polldaddy' ) => '0', __( 'Below each blog post', 'polldaddy' ) => '1' );
						foreach ( $select as $option => $value ) :
							$selected = '';
						if ( $value == $pos_posts_index )
							$selected = ' selected="selected"';
						echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
						endforeach;?>
		                </select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Position Post Ratings', 'polldaddy' );?></label></th>
					<td>
						<select name="posts_pos"><?php
						$select = array( __( 'Above each blog post', 'polldaddy' ) => '0', __( 'Below each blog post', 'polldaddy' ) => '1' );
						foreach ( $select as $option => $value ) :
							$selected = '';
						if ( $value == $pos_posts )
							$selected = ' selected="selected"';
						echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
						endforeach;?>
		                </select>
					</td>
				</tr><?php
			}
			if ( $report_type == 'pages' ) {?>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Show Ratings on', 'polldaddy' );?></label></th>
					<td>
						<label><input type="checkbox" name="pd_show_pages" id="pd_show_pages" <?php if ( $show_pages > 0 ) echo ' checked="checked" '; ?> value="1" /> <?php _e( 'Pages', 'polldaddy' );?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Position Page Ratings', 'polldaddy' );?></label></th>
					<td>
						<select name="pages_pos"><?php
						$select = array( __( 'Above each blog page', 'polldaddy' ) => '0', __( 'Below each blog page', 'polldaddy' ) => '1' );
						foreach ( $select as $option => $value ) :
							$selected = '';
						if ( $value == $pos_pages )
							$selected = ' selected="selected"';
						echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
						endforeach;?>
		                </select>
					</td>
				</tr><?php
			}
			if ( $report_type == 'comments' ) {?>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Show Ratings on', 'polldaddy' );?></label></th>
					<td>
						<label><input type="checkbox" name="pd_show_comments" id="pd_show_comments" <?php if ( $show_comments > 0 ) echo ' checked="checked" '; ?> value="1" /> <?php _e( 'Comments', 'polldaddy' );?></label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label><?php _e( 'Position Comment Ratings', 'polldaddy' );?></label></th>
					<td>
						<select name="comments_pos"><?php
						$select = array( __( 'Above each comment', 'polldaddy' ) => '0', __( 'Below each comment', 'polldaddy' ) => '1' );
						foreach ( $select as $option => $value ) :
							$selected = '';
						if ( $value == $pos_comments )
							$selected = ' selected="selected"';
						echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
						endforeach;?>
		                </select>
					</td>
				</tr><?php
			} ?>
				<tr valign="top">
					<td height="30"><input class="button-primary" type="submit" value="<?php esc_attr_e( 'Save Changes', 'polldaddy' );?>" name="Submit" /></td>
				</tr>
              </tbody>
            </table>
          </form>
        </div>
        
          <div style="padding:20px 0px 0px 0px"><?php
			if ( $report_type == 'posts' ) {
				if ( $show_posts > 0 || $show_posts_index > 0 )
					$show_settings = true;
			}
			if ( $report_type == 'pages' && $show_pages > 0 )
				$show_settings = true;
			if ( $report_type == 'comments' && $show_comments > 0 )
				$show_settings = true;
			if ( $show_settings == true )
				echo '<a href="javascript:" onclick="show_settings();">'.__( 'Advanced Settings', 'polldaddy' ).'</a>';?></div>
      </div>
    </div>

    <?php if ( $show_settings == true ) { ?>
    <br />
    <form method="post" action="">
      <div id="poststuff" style="<?php echo $settings_style; ?>">
        <div  class="has-sidebar has-right-sidebar">
          <div class="inner-sidebar-ratings">
           <div id="submitdiv" class="postbox ">
			    <h3 class="hndle"><span><?php _e( 'Save Advanced Settings', 'polldaddy' );?></span></h3>
			
			    <div class="inside">
			        <div class="submitbox" id="submitpost">
			            <div id="minor-publishing" style="padding:10px;">
			                <input type="submit" name="save_menu" id="save_menu_header" class="button button-primary menu-save" value="<?php echo esc_attr( __( 'Save Changes', 'polldaddy' ) );?>">
			                <input type="hidden" name="type" value="<?php echo $report_type; ?>" />
							<input type="hidden" name="rating_id" value="<?php echo $rating_id; ?>" />
							<input type="hidden" name="action" value="update-rating" />
			            </div>
			        </div>
			    </div>
			</div>
            <div class="postbox">
              <h3><?php _e( 'Preview', 'polldaddy' );?></h3>
              <div class="inside">
                <p><?php _e( 'This is a demo of what your rating widget will look like', 'polldaddy' ); ?>.</p>
                <p>
                  <div id="pd_rating_holder_1"></div>
                </p>
              </div>
            </div>
            <div class="postbox">
              <h3><?php _e( 'Customize Labels', 'polldaddy' );?></h3>
              <div class="inside">
                <table width="99.5%">
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Vote', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_vote" id="text_vote" value="<?php echo empty( $settings->text_vote ) ? 'Vote' : esc_html( $settings->text_vote ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Votes', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_votes" id="text_votes" value="<?php echo empty( $settings->text_votes ) ? 'Votes' : esc_html( $settings->text_votes ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Rate This', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_rate_this" id="text_rate_this" value="<?php echo empty( $settings->text_rate_this ) ? 'Rate This' : esc_html( $settings->text_rate_this ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php printf( __( '%d star', 'polldaddy' ), 1 );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_1_star" id="text_1_star" value="<?php echo empty( $settings->text_1_star ) ? '1 star' : esc_html( $settings->text_1_star ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php printf( __( '%d stars', 'polldaddy' ), 2 );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_2_star" id="text_2_star" value="<?php echo empty( $settings->text_2_star ) ? '2 stars' : esc_html( $settings->text_2_star ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php printf( __( '%d stars', 'polldaddy' ), 3 );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_3_star" id="text_3_star" value="<?php echo empty( $settings->text_3_star ) ? '3 stars' : esc_html( $settings->text_3_star ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php printf( __( '%d stars', 'polldaddy' ), 4 );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_4_star" id="text_4_star" value="<?php echo empty( $settings->text_4_star ) ? '4 stars' : esc_html( $settings->text_4_star ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php printf( __( '%d stars', 'polldaddy' ), 5 );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_5_star" id="text_5_star" value="<?php echo empty( $settings->text_5_star ) ? '5 stars' : esc_html( $settings->text_5_star ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Thank You', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_thank_you" id="text_thank_you" value="<?php echo empty( $settings->text_thank_you ) ? 'Thank You' : esc_html( $settings->text_thank_you ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Rate Up', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_rate_up" id="text_rate_up" value="<?php echo empty( $settings->text_rate_up ) ? 'Rate Up' : esc_html( $settings->text_rate_up ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Rate Down', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_rate_down" id="text_rate_down" value="<?php echo empty( $settings->text_rate_down ) ? 'Rate Down' : esc_html( $settings->text_rate_down ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Most Popular Content', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_popcontent" id="text_popcontent" value="<?php echo empty( $settings->text_popcontent ) ? 'Most Popular Content' : esc_html( $settings->text_popcontent ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Close', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_close" id="text_close" value="<?php echo empty( $settings->text_close ) ? 'Close' : esc_html( $settings->text_close ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'All', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_all" id="text_all" value="<?php echo empty( $settings->text_all ) ? 'All' : esc_html( $settings->text_all ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Today', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_today" id="text_today" value="<?php echo empty( $settings->text_today ) ? 'Today' : esc_html( $settings->text_today ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'This Week', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_thisweek" id="text_thisweek" value="<?php echo empty( $settings->text_thisweek ) ? 'This Week' : esc_html( $settings->text_thisweek ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'This Month', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_thismonth" id="text_thismonth" value="<?php  echo empty( $settings->text_thismonth ) ? 'This Month' : esc_html( $settings->text_thismonth ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'Rated', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_rated" id="text_rated" value="<?php echo empty( $settings->text_rated ) ? 'Rated' : esc_html( $settings->text_rated ); ?>" maxlength="20" />
                  </tr>
                  <tr>
                    <td><p style="margin-bottom: 0px;"><?php _e( 'There are no rated items for this period', 'polldaddy' );?></p></td>
                  </tr>
                  <tr>
                    <td><input onblur="pd_bind(this);" type="text" style="width: 100%;" name="text_noratings" id="text_noratings" value="<?php echo empty( $settings->text_noratings ) ? 'There are no rated items for this period' : esc_html( $settings->text_noratings ); ?>" maxlength="50" />
                  </tr>
                </table>
              </div>
            </div>
          </div>
          <div id="post-body-content" class="has-sidebar-content">
            <div class="postbox">
              <h3><?php _e( 'Rating Type', 'polldaddy' );?></h3>
              <div class="inside">
                <p><?php _e( 'Here you can choose how you want your rating to display. The 5 star rating is the most commonly used. The Nero rating is useful for keeping it simple.', 'polldaddy' ); ?></p>
                  <ul>
                    <li style="display: inline;margin-right: 10px;">
                      <label for="stars"><?php
				$checked = '';
				if ( $settings->type == 'stars' )
					$checked = ' checked="checked"';?>
                        <input type="radio" onchange="pd_change_type( 0 );" <?php echo $checked; ?> value="stars" id="stars" name="rating_type" />
                          <?php printf( __( '%d Star Rating', 'polldaddy' ), 5 );?>
                        </label>
                    </li>
                    <li style="display: inline;">
                      <label><?php
				$checked = '';
				if ( $settings->type == 'nero' )
					$checked = ' checked="checked"';?>
                        <input type="radio" onchange="pd_change_type( 1 );" <?php echo $checked; ?> value="nero" id="nero" name="rating_type" />
                        <?php _e( 'Nero Rating', 'polldaddy' );?>
                      </label>
                    </li>
                  </ul>
                </div>
            </div>
          <div class="postbox">
            <h3><?php _e( 'Rating Style', 'polldaddy' );?></h3>
            <div class="inside">
              <table>
                <tr>
                  <td height="30" width="100" id="editor_star_size_text"><?php _e( 'Star Size', 'polldaddy' );?></td>
                  <td>
                    <select name="size" id="size" onchange="pd_bind(this);"><?php
				$select = array( __( 'Small', 'polldaddy' )." (16px)" => "sml", __( 'Medium', 'polldaddy' )." (20px)" => "med", __( 'Large', 'polldaddy' )." (24px)" => "lrg" );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->size == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>' . "\n";
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30" id="editor_star_color_text"><?php echo 'bubu'; _e( 'Star Color', 'polldaddy' );?></td>
                  <td>
                    <select name="star_color" id="star_color" onchange="pd_bind(this);" style="display: none;"><?php
				$select = array( __( 'Yellow', 'polldaddy' ) => "yellow", __( 'Red', 'polldaddy' ) => "red", __( 'Blue', 'polldaddy' ) => "blue", __( 'Green', 'polldaddy' ) => "green", __( 'Grey', 'polldaddy' ) => "grey" );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->star_color == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>' . "\n";
				endforeach;?>
                    </select>
                    <select name="nero_style" id="nero_style" onchange="pd_bind(this);"  style="display: none;"><?php
				$select = array( __( 'Hand', 'polldaddy' ) => "hand" );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->star_color == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>' . "\n";
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Custom Image', 'polldaddy' );?></td>
                  <td><input type="text" onblur="pd_bind(this);" name="custom_star" id="custom_star" value="<?php echo esc_url( $settings->custom_star ); ?>" maxlength="200" />
                </tr>
              </table>
            </div>
          </div>
          <div class="postbox">
            <h3><?php _e( 'Text Layout & Font', 'polldaddy' );?></h3>
            <div class="inside">
              <table>
                <tr>
                  <td width="100" height="30"><?php _e( 'Align', 'polldaddy' );?></td>
                  <td>
                    <select id="font_align" onchange="pd_bind(this);" name="font_align"><?php
				$select = array( __( 'Left', 'polldaddy' ) => "left", __( 'Center', 'polldaddy' ) => "center", __( 'Right', 'polldaddy' ) => "right" );
				foreach ( $select as $option => $value ):
					$selected = '';
				if ( $settings->font_align == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Position', 'polldaddy' );?></td>
                  <td>
                    <select name="font_position" onchange="pd_bind(this);" id="font_position"><?php
				$select = array( __( 'Top', 'polldaddy' ) => "top", __( 'Right', 'polldaddy' ) => "right", __( 'Bottom', 'polldaddy' ) => "bottom" );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->font_position == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Font', 'polldaddy' );?></td>
                  <td>
                    <select name="font_family" id="font_family" onchange="pd_bind(this);"><?php
				$select = array( __( 'Inherit', 'polldaddy' ) => "", "Arial" => "arial", "Comic Sans MS" => "comic sans ms", "Courier" => "courier",  "Georgia" => "georgia", "Lucida Grande" => "lucida grande", "Tahoma" => "tahoma", "Times" => "times", "Trebuchet MS" => "trebuchet ms", "Verdana" => "verdana" );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->font_family == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>';
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Color', 'polldaddy' );?></td>
                  <td><input type="text" onblur="pd_bind(this);" class="elmColor jscolor-picker" name="font_color" id="font_color" value="<?php echo esc_html( $settings->font_color ); ?>" maxlength="11" autocomplete="off"/>
                  </td>
                </tr>
                <tr>
                  <td><?php _e( 'Size', 'polldaddy' );?></td>
                  <td>
                    <select name="font_size" id="font_size"  onchange="pd_bind(this);"><?php
				$select = array( __( 'Inherit', 'polldaddy' ) => "", "6px" => "6px", "8px" => "8px", "9px" => "9px", "10px" => "10px", "11px" => "11px", "12px" => "12px", "14px" => "14px", "16px" => "16px", "18px" => "18px", "20px" => "20px", "24px" => "24px", "30px" => "30px", "36px" => "36px", );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->font_size == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>' . "\n";
				endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Line Height', 'polldaddy' );?></td>
                  <td>
                    <select name="font_line_height" id="font_line_height" onchange="pd_bind(this);"><?php
				$select = array( __( 'Inherit', 'polldaddy' ) => "", "6px" => "6px", "8px" => "8px", "9px" => "9px", "10px" => "10px", "11px" => "11px", "12px" => "12px", "14px" => "14px", "16px" => "16px", "18px" => "18px", "20px" => "20px", "24px" => "24px", "30px" => "30px", "36px" => "36px", );
				foreach ( $select as $option => $value ) :
					$selected = '';
				if ( $settings->font_line_height == $value )
					$selected = ' selected="selected"';
				echo '<option value="' . $value . '" ' . $selected . '>' . $option . '</option>' . "\n";
				endforeach; ?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Bold', 'polldaddy' );?></td>
                  <td><?php
				$checked = '';
				if ( $settings->font_bold == 'bold' )
					$checked = ' checked="checked"';?>
                    <input type="checkbox" name="font_bold" onclick="pd_bind(this);" id="font_bold" value="bold" <?php echo $checked; ?> />
                  </td>
                </tr>
                <tr>
                  <td height="30"><?php _e( 'Italic', 'polldaddy' );?></td><?php
				$checked = '';
				if ( $settings->font_italic == 'italic' )
					$checked = ' checked="checked"';?>
                  <td><input type="checkbox" name="font_italic"  onclick="pd_bind(this);" id="font_italic" value="italic" <?php echo $checked; ?>/></td>
                </tr>
              </table>
            </div>
          </div>
          <?php
				if ( $this->is_admin ) { ?>
            <div class="postbox">
              <h3><?php _e( 'Extra Settings', 'polldaddy' );?></h3>
              <div class="inside">
                <table>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Results Popup', 'polldaddy' );?></td>
                    <td>
                      <input type="checkbox" onchange="pd_bind(this);" value="on" name="polldaddy-rating-popup" id="polldaddy-rating-popup" <?php echo !$popup_disabled ? 'checked="checked"' : ''; ?> />
                    </td>
                    <td>
                      <span class="description">
                        <label for="polldaddy-rating-popup"><?php _e( 'Uncheck this box to disable the results popup', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr><?php
					if ( $report_type == 'posts' ) {
						$exclude_post_ids = esc_html( get_option( 'pd-rating-exclude-post-ids' ) ); ?>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Rating ID', 'polldaddy' );?></td>
                    <td>
                      <input type="text" name="polldaddy-post-rating-id" id="polldaddy-post-rating-id" value="<?php echo $rating_id; ?>" />
                    </td>
                    <td>
                      <span class="description">
                        <label for="polldaddy-post-rating-id"><?php _e( 'This is the rating ID used in posts', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Exclude Posts', 'polldaddy' );?></td>
                    <td>
                      <input type="text" name="exclude-post-ids" id="exclude-post-ids" value="<?php echo $exclude_post_ids; ?>" />
                    </td>
                    <td>
                      <span class="description">
                        <label for="exclude-post-ids"><?php _e( 'Enter the Post IDs where you want to exclude ratings from. Please use a comma-delimited list, eg. 1,2,3', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr><?php
					} elseif ( $report_type == 'pages' ) {
						$exclude_page_ids = esc_html( get_option( 'pd-rating-exclude-page-ids' ) ); ?>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Rating ID', 'polldaddy' );?></td>
                    <td>
                      <input type="text" name="polldaddy-page-rating-id" id="polldaddy-page-rating-id" value="<?php echo $rating_id; ?>" />
                    </td>
                    <td>
                      <span class="description">
                        <label for="polldaddy-page-rating-id"><?php _e( 'This is the rating ID used in pages', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Exclude Pages', 'polldaddy' );?></td>
                    <td>
                      <input type="text" name="exclude-page-ids" id="exclude-page-ids" value="<?php echo $exclude_page_ids; ?>" />
                    </td>
                    <td>
                      <span class="description">
                        <label for="exclude-page-ids"><?php _e( 'Enter the Page IDs where you want to exclude ratings from. Please use a comma-delimited list, eg. 1,2,3', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr><?php
					} elseif ( $report_type == 'comments' ) { ?>
                  <tr>
                    <td width="100" height="30"><?php _e( 'Rating ID', 'polldaddy' );?></td>
                    <td>
                      <input type="text" name="polldaddy-comment-rating-id" id="polldaddy-comment-rating-id" value="<?php echo $rating_id; ?>" />
                    </td>
                    <td>
                      <span class="description">
                        <label for="polldaddy-comment-rating-id"><?php _e( 'This is the rating ID used in comments', 'polldaddy' ); ?></label>
                      </span>
                    </td>
                  </tr><?php
					} ?>
                </table>
              </div>
            </div>
            <?php } ?>
        </div>
      </div>
    </form>
	<script language="javascript">
	jQuery( document ).ready(function(){
		plugin = new Plugin( {
			delete_rating: '<?php echo esc_attr( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
			delete_poll: '<?php echo esc_attr( __( 'Are you sure you want to delete "%s"?', 'polldaddy' ) ); ?>',
			delete_answer: '<?php echo esc_attr( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
			delete_answer_title: '<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>',
			standard_styles: '<?php echo esc_attr( __( 'Standard Styles', 'polldaddy' ) ); ?>',
			custom_styles: '<?php echo esc_attr( __( 'Custom Styles', 'polldaddy' ) ); ?>'
		} );
	});
	pd_map = { image_path : '<?php echo plugins_url( 'img', __FILE__ );?>' };
	</script>
    <script type="text/javascript">
    PDRTJS_settings = <?php echo $settings_text; ?>;
    PDRTJS_settings.id = "1";
    PDRTJS_settings.unique_id = "xxx";
    PDRTJS_settings.title = "";
    PDRTJS_settings.override = "<?php echo esc_attr( $rating_id ); ?>";
    PDRTJS_settings.permalink = "";
    PDRTJS_1 = new PDRTJS_RATING( PDRTJS_settings );
    pd_change_type( <?php echo $rating_type?> );
    </script><?php
			} ?>
    </div><?php
		} // from if !error ?>
    </div><?php
	}

	function update_rating() {
		$rating_type = 0;
		$rating_id = 0;
		$new_rating_id = 0;
		$type = 'post';
		$set = null;

		if ( isset( $_REQUEST['rating_id'] ) )
			$rating_id = (int) $_REQUEST['rating_id'];

		if ( isset( $_REQUEST['polldaddy-post-rating-id'] ) ) {
			$new_rating_id = (int) $_REQUEST['polldaddy-post-rating-id'];
			$type = 'posts';
		}
		elseif ( isset( $_REQUEST['polldaddy-page-rating-id'] ) ) {
			$new_rating_id = (int) $_REQUEST['polldaddy-page-rating-id'];
			$type = 'pages';
		}
		elseif ( isset( $_REQUEST['polldaddy-comment-rating-id'] ) ) {
			$new_rating_id = (int) $_REQUEST['polldaddy-comment-rating-id'];
			$type = 'comments';
		} else {
			$new_rating_id = $rating_id;
		}

		if ( $rating_id > 0 && $rating_id == $new_rating_id ) {
			if ( isset( $_REQUEST['rating_type'] ) && $_REQUEST['rating_type'] == 'stars' ) {
				$set->type = 'stars';
				$rating_type = 0;
				if ( isset( $_REQUEST['star_color'] ) )
					$set->star_color = esc_attr( $_REQUEST['star_color'] );
			} else {
				$set->type = 'nero';
				$rating_type = 1;
				if ( isset( $_REQUEST['nero_style'] ) )
					$set->star_color = esc_attr( $_REQUEST['nero_style'] );
			}

			$set->size             = esc_html( $_REQUEST['size'], 1 );
			$set->custom_star      = esc_html( esc_url( $_REQUEST['custom_star'] ) , 1 );
			$set->font_align       = esc_html( $_REQUEST['font_align'], 1 );
			$set->font_position    = esc_html( $_REQUEST['font_position'], 1 );
			$set->font_family      = esc_html( $_REQUEST['font_family'], 1 );
			$set->font_size        = esc_html( $_REQUEST['font_size'], 1 );
			$set->font_line_height = esc_html( $_REQUEST['font_line_height'], 1 );

			if ( isset( $_REQUEST['font_bold'] ) && $_REQUEST['font_bold'] == 'bold' )
				$set->font_bold = 'bold';
			else
				$set->font_bold = 'normal';

			if ( isset( $_REQUEST['font_italic'] ) && $_REQUEST['font_italic'] == 'italic' )
				$set->font_italic = 'italic';
			else
				$set->font_italic = 'normal';

			$set->text_vote      = rawurlencode( stripslashes( esc_html( $_REQUEST['text_vote'], 1 ) ) );
			$set->text_votes     = rawurlencode( stripslashes( esc_html( $_REQUEST['text_votes'], 1 ) ) );
			$set->text_rate_this = rawurlencode( stripslashes( esc_html( $_REQUEST['text_rate_this'], 1 ) ) );
			$set->text_1_star    = rawurlencode( stripslashes( esc_html( $_REQUEST['text_1_star'], 1 ) ) );
			$set->text_2_star    = rawurlencode( stripslashes( esc_html( $_REQUEST['text_2_star'], 1 ) ) );
			$set->text_3_star    = rawurlencode( stripslashes( esc_html( $_REQUEST['text_3_star'], 1 ) ) );
			$set->text_4_star    = rawurlencode( stripslashes( esc_html( $_REQUEST['text_4_star'], 1 ) ) );
			$set->text_5_star    = rawurlencode( stripslashes( esc_html( $_REQUEST['text_5_star'], 1 ) ) );
			$set->text_thank_you = rawurlencode( stripslashes( esc_html( $_REQUEST['text_thank_you'], 1 ) ) );
			$set->text_rate_up   = rawurlencode( stripslashes( esc_html( $_REQUEST['text_rate_up'], 1 ) ) );
			$set->text_rate_down = rawurlencode( stripslashes( esc_html( $_REQUEST['text_rate_down'], 1 ) ) );
			$set->font_color     = rawurlencode( stripslashes( esc_html( $_REQUEST['font_color'], 1 ) ) );

			$set->text_popcontent= rawurlencode( stripslashes( esc_html( $_REQUEST['text_popcontent'], 1 ) ) );
			$set->text_close     = rawurlencode( stripslashes( esc_html( $_REQUEST['text_close'], 1 ) ) );
			$set->text_all       = rawurlencode( stripslashes( esc_html( $_REQUEST['text_all'], 1 ) ) );
			$set->text_today     = rawurlencode( stripslashes( esc_html( $_REQUEST['text_today'], 1 ) ) );
			$set->text_thisweek  = rawurlencode( stripslashes( esc_html( $_REQUEST['text_thisweek'], 1 ) ) );
			$set->text_thismonth = rawurlencode( stripslashes( esc_html( $_REQUEST['text_thismonth'], 1 ) ) );
			$set->text_rated     = rawurlencode( stripslashes( esc_html( $_REQUEST['text_rated'], 1 ) ) );
			$set->text_noratings = rawurlencode( stripslashes( esc_html( $_REQUEST['text_noratings'], 1 ) ) );

			$set->popup = 'off';
			if ( isset( $_REQUEST['polldaddy-rating-popup'] ) )
				$set->popup = ( $_REQUEST['polldaddy-rating-popup'] == 'on' ? 'on' : 'off' );

			$settings_text = json_encode( $set );

			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
			$polldaddy->reset();
			$rating = $polldaddy->update_rating( $rating_id, $settings_text, $rating_type );
		}
		elseif ( $this->is_admin && $new_rating_id > 0 ) {
			switch ( $type ) {
			case 'pages':
				update_option( 'pd-rating-pages-id', $new_rating_id );
				if ( (int) get_option( 'pd-rating-pages' ) > 0 )
					update_option( 'pd-rating-pages', $new_rating_id );
				break;
			case 'comments':
				update_option( 'pd-rating-comments-id', $new_rating_id );
				if ( (int) get_option( 'pd-rating-comments' ) > 0 )
					update_option( 'pd-rating-comments', $new_rating_id );
				break;
			case 'posts':
				update_option( 'pd-rating-posts-id', $new_rating_id );
				if ( (int) get_option( 'pd-rating-posts' ) > 0 )
					update_option( 'pd-rating-posts', $new_rating_id );
			}
		}

		if ( $this->is_admin ) {
			if ( $type=='posts' && isset( $_REQUEST['exclude-post-ids'] ) ) {
				$exclude_post_ids = $_REQUEST['exclude-post-ids'];
				if ( empty( $exclude_post_ids ) ) {
					update_option( 'pd-rating-exclude-post-ids', '' );
				} else {
					$post_ids = array();
					$ids = explode( ',', $exclude_post_ids );
					if ( !empty( $ids ) ) {
						foreach ( (array) $ids as $id ) {
							if ( (int) $id > 0 )
								$post_ids[] = (int) $id;
						}
					}
					if ( !empty( $post_ids ) ) {
						$exclude_post_ids = implode( ',', $post_ids );
						update_option( 'pd-rating-exclude-post-ids', $exclude_post_ids );
					}
				}
			}

			if ( $type=='pages' && isset( $_REQUEST['exclude-page-ids'] ) ) {
				$exclude_page_ids = $_REQUEST['exclude-page-ids'];
				if ( empty( $exclude_page_ids ) ) {
					update_option( 'pd-rating-exclude-page-ids', '' );
				} else {
					$page_ids = array();
					$ids = explode( ',', $exclude_page_ids );
					if ( !empty( $ids ) ) {
						foreach ( (array) $ids as $id ) {
							if ( (int) $id > 0 )
								$page_ids[] = (int) $id;
						}
					}
					if ( !empty( $page_ids ) ) {
						$exclude_page_ids = implode( ',', $page_ids );
						update_option( 'pd-rating-exclude-page-ids', $exclude_page_ids );
					}
				}
			}
		}
	}
	function rating_reports() {
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
		$rating_id = get_option( 'pd-rating-posts-id' );

		$report_type = 'posts';
		$period = '7';
		$show_rating = 0;

		if ( isset( $_REQUEST['change-report-to'] ) ) {
			switch ( $_REQUEST['change-report-to'] ) {
			case 'pages':
				$report_type = 'pages';
				$rating_id = (int) get_option( 'pd-rating-pages-id' );
				break;

			case 'comments':
				$report_type = 'comments';
				$rating_id = get_option( 'pd-rating-comments-id' );
				break;

			case 'posts':
				$report_type = 'posts';
				$rating_id = get_option( 'pd-rating-posts-id' );
				break;
			}//end switch
		}

		if ( isset( $_REQUEST['filter'] ) &&  $_REQUEST['filter'] ) {
			switch ( $_REQUEST['filter'] ) {
			case '1':
				$period = '1';
				break;

			case '7':
				$period = '7';
				break;

			case '31':
				$period = '31';
				break;

			case '90':
				$period = '90';
				break;

			case '365':
				$period = '365';
				break;

			case 'all':
				$period = 'all';
				break;
			}//end switch
		}

		$page_size = 15;
		$current_page = 1;

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'change-report' ) {
			$current_page = 1;
		} else {
			if ( isset( $_REQUEST['paged'] ) ) {
				$current_page = (int) $_REQUEST['paged'];
				if ( $current_page == 0 )
					$current_page = 1;
			}
		}

		$start = ( $current_page * $page_size ) - $page_size;
		$end = $page_size;

		$response = $polldaddy->get_rating_results( $rating_id, $period, $start, $end );

		$total = $total_pages = 0;
		$ratings = null;

		if ( !empty( $response ) ) {
			$ratings = $response->rating;
			$total = (int) $response->_total;
			$total_pages = ceil( $total / $page_size );
		}

		$page_links = paginate_links( array(
				'base'       => add_query_arg( array ( 'paged' => '%#%', 'change-report-to' => $report_type, 'filter' => $period ) ),
				'format'     => '',
				'prev_text'  => __( '&laquo;', 'polldaddy' ),
				'next_text'  => __( '&raquo;', 'polldaddy' ),
				'total'      => $total_pages,
				'current'    => $current_page
			) );
?>
		<div class="wrap">
			<?php if ( $this->is_admin ) : ?>
			<h2 id="polldaddy-header"><?php printf( __( 'Rating Results <a href="%s" class="add-new-h2">Settings</a>', 'polldaddy' ), esc_url( 'options-general.php?page=ratings&action=options' ) ); ?></h2>
			<?php else : ?>
			<h2 id="polldaddy-header"><?php _e( 'Rating Results', 'polldaddy' ); ?></h2>
			<?php endif; ?>
			<div class="clear"></div>
			<form method="post" action="">
				<div class="tablenav">
					<div class="alignleft actions">
						<?php if ( $this->is_editor ) { ?>
						<select name="action">
							<option selected="selected" value=""><?php _e( 'Actions', 'polldaddy' ); ?></option>
							<option value="delete"><?php _e( 'Delete', 'polldaddy' ); ?></option>
						</select>
						<input type="hidden" name="id" id="id" value="<?php echo (int) $rating_id; ?>" />
						<input class="button-secondary action" type="submit" name="doaction" value="<?php _e( 'Apply', 'polldaddy' ); ?>" />
						<?php wp_nonce_field( 'action-rating_bulk' ); ?>
						<?php } ?>
						<select name="change-report-to"><?php
		$select = array( __( 'Posts', 'polldaddy' ) => "posts", __( 'Pages', 'polldaddy' ) => "pages", __( 'Comments', 'polldaddy' ) => "comments" );
		foreach ( $select as $option => $value ) :
			$selected = '';
		if ( $value == $report_type )
			$selected = ' selected="selected"';?>
        <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
    <?php endforeach;  ?>
						</select>
            			<select name="filter"><?php
		$select = array( __( 'Last 24 hours', 'polldaddy' ) => "1", __( 'Last 7 days', 'polldaddy' ) => "7", __( 'Last 31 days', 'polldaddy' ) => "31", __( 'Last 3 months', 'polldaddy' ) => "90", __( 'Last 12 months', 'polldaddy' ) => "365", __( 'All time', 'polldaddy' ) => "all" );
		foreach ( $select as $option => $value ) :
			$selected = '';
		if ( $value == $period )
			$selected = ' selected="selected"';?>
        					<option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $option; ?></option>
    <?php endforeach; ?>
          				</select>
          				<input class="button-secondary action" type="submit" value="<?php _e( 'Filter', 'polldaddy' );?>" />
          				<?php if ( in_array( $period, array( 1, 7 ) ) ) : ?>
          				<label><?php _e( '* The results are cached and are updated every hour' ); ?></label>
          				<?php elseif ( $period == 31 ) : ?>
          				<label><?php _e( '* The results are cached and are updated every day' ); ?></label>
          				<?php else : ?>
          				<label><?php _e( '* The results are cached and are updated every 3 days' ); ?></label>
          				<?php endif; ?>
					</div>
					<div class="alignright">
						<div class="tablenav-pages">
							<?php echo $page_links; ?>
						</div>
					</div>
				</div>

			<table class="widefat"><?php
		if ( empty( $ratings ) ) { ?>
				<tbody>
					<tr>
						<td colspan="4"><?php printf( __( 'No ratings have been collected for your %s yet.', 'polldaddy' ), $report_type ); ?></td>
					</tr>
				</tbody><?php
		} else {  
			polldaddy_update_ratings_cache( $ratings );
			?>
				<thead>
					<tr>
						<?php if ( $this->is_editor ) { ?>
			 	 		<th scope="col" class="manage-column column-cb check-column" id="cb"><input type="checkbox"></th>
						<?php } else { ?>
			 	 		<th scope="col" class="manage-column column-cb check-column" id="cb"></th>
						<?php } ?>
						<th scope="col" class="manage-column column-title" id="title"><?php _e( 'Title', 'polldaddy' );?></th>
						<th scope="col" class="manage-column column-id" id="id"><?php _e( 'Unique ID', 'polldaddy' );?></th>
						<th scope="col" class="manage-column column-date" id="date"><?php _e( 'Start Date', 'polldaddy' );?></th>
						<th scope="col" class="manage-column column-vote num" id="votes"><?php _e( 'Votes', 'polldaddy' );?></th>
						<th scope="col" class="manage-column column-rating num" id="rating"><?php _e( 'Average Rating', 'polldaddy' );?></th>
					</tr>
				</thead>
				<tbody><?php
			$alt_counter = 0;
			$alt = '';

			foreach ( $ratings as $rating  ) :
				$delete_link = esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'id' => $rating_id, 'rating' => $rating->uid, 'change-report-to' => $report_type, 'message' => false ) ), "delete-rating_$rating->uid" ) );
			$alt_counter++;?>
					<tr <?php echo ( $alt_counter & 1 ) ? ' class="alternate"' : ''; ?>>
						<th class="check-column" scope="row"><input type="checkbox" value="<?php echo esc_html( $rating->uid ); ?>" name="rating[]" /></th>
						<td class="post-title column-title">
							<strong><a href="<?php echo esc_url( $rating->permalink ); ?>"><?php echo strlen( esc_html( $rating->title ) ) > 75 ? substr( esc_html( $rating->title ), 0, 72 ) . '&hellip' : esc_html( $rating->title ); ?></a></strong>
							<div class="row-actions">
							<?php if ( $this->is_editor && $delete_link ) { ?>
								<span class="delete"><a class="delete-rating delete" href="<?php echo $delete_link; ?>"><?php _e( 'Delete', 'polldaddy' ); ?></a></span>
							<?php } ?>
							</div>
						</td>
						<td class="column-id">
							<?php echo esc_html( $rating->uid ); ?>
						</td>
						<td class="date column-date">
							<abbr title="<?php echo date( __( 'Y/m/d g:i:s A', 'polldaddy' ), $rating->date ); ?>"><?php echo str_replace( '-', '/', substr( esc_html( $rating->date ), 0, 10 ) ); ?></abbr>
						</td>
						<td class="column-vote num"><?php echo number_format( $rating->_votes ); ?></td>
						<td class="column-rating num"><table width="100%"><tr align="center"><td style="border:none;"><?php
			if ( $rating->_type == 0 ) {
				$avg_rating = $this->round( $rating->average_rating, 0.5 );?>
							<div style="width:100px"><?php
				$image_pos = '';

				for ( $c = 1; $c <= 5; $c++ ) :
					if ( $avg_rating > 0 ) {
						if ( $avg_rating < $c )
							$image_pos = 'bottom left';
						if ( $avg_rating == ( $c - 1 + 0.5 ) )
							$image_pos = 'center left';
					} ?>
								<div style="width: 20px; height: 20px; background: url(<?php echo plugins_url( 'img/star-yellow-med.png', __FILE__ ); ?>) <?php echo $image_pos; ?>; float: left;"></div><?php
				endfor; ?>
								<br class="clear" />
							</div><?php
			} else { ?>
							<div>
								<div style="margin: 0px 0px 0px 20px; background: transparent url(<?php echo plugins_url( 'img/rate-graph-up.png', __FILE__ ); ?>); width: 20px; height: 20px; float: left;"></div>
								<div style="float:left; line-height: 20px; padding: 0px 10px 0px 5px;"><?php echo number_format( $rating->total1 );?></div>
								<div style="margin: 0px; background: transparent url(<?php echo plugins_url( 'img/rate-graph-dn.png', __FILE__ ); ?>); width: 20px; height: 20px; float: left;"></div>
								<div style="float:left; line-height: 20px; padding: 0px 10px 0px 5px;"><?php echo number_format( $rating->total2 );?></div>
								<br class="clear" />
							</div><?php
			} ?>
							</td></tr></table>
						</td>
					</tr><?php
			endforeach;
?>
				</tbody><?php
		} ?>
			</table>
	    	<div class="tablenav">
	        	<div class="alignright">
	            	<div class="tablenav-pages">
	                	<?php echo $page_links; ?>
	            	</div>
	        	</div>
	    	</div>
			</form>
		</div>
		<p></p>
	<script language="javascript">
	jQuery( document ).ready(function(){
		plugin = new Plugin( {
			delete_rating: '<?php echo esc_attr( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
			delete_poll: '<?php echo esc_attr( __( 'Are you sure you want to delete "%s"?', 'polldaddy' ) ); ?>',
			delete_answer: '<?php echo esc_attr( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
			delete_answer_title: '<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>',
			standard_styles: '<?php echo esc_attr( __( 'Standard Styles', 'polldaddy' ) ); ?>',
			custom_styles: '<?php echo esc_attr( __( 'Custom Styles', 'polldaddy' ) ); ?>'
		} );
	});
	</script><?php
	}

	function plugin_options() {
		if ( isset( $_POST['polldaddy_email'] ) ) {
			$account_email = false;
		} else {
			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
			$account = $polldaddy->get_account();

			if ( !empty( $account ) )
				$account_email = esc_attr( $account->email );

			$polldaddy->reset();
			$poll = $polldaddy->get_poll( 1 );

			$options = array(
				101 => __( 'Aluminum Narrow', 'polldaddy' ),
				102 => __( 'Aluminum Medium', 'polldaddy' ),
				103 => __( 'Aluminum Wide', 'polldaddy' ),
				104 => __( 'Plain White Narrow', 'polldaddy' ),
				105 => __( 'Plain White Medium', 'polldaddy' ),
				106 => __( 'Plain White Wide', 'polldaddy' ),
				107 => __( 'Plain Black Narrow', 'polldaddy' ),
				108 => __( 'Plain Black Medium', 'polldaddy' ),
				109 => __( 'Plain Black Wide', 'polldaddy' ),
				110 => __( 'Paper Narrow', 'polldaddy' ),
				111 => __( 'Paper Medium', 'polldaddy' ),
				112 => __( 'Paper Wide', 'polldaddy' ),
				113 => __( 'Skull Dark Narrow', 'polldaddy' ),
				114 => __( 'Skull Dark Medium', 'polldaddy' ),
				115 => __( 'Skull Dark Wide', 'polldaddy' ),
				116 => __( 'Skull Light Narrow', 'polldaddy' ),
				117 => __( 'Skull Light Medium', 'polldaddy' ),
				118 => __( 'Skull Light Wide', 'polldaddy' ),
				157 => __( 'Micro', 'polldaddy' ),
				119 => __( 'Plastic White Narrow', 'polldaddy' ),
				120 => __( 'Plastic White Medium', 'polldaddy' ),
				121 => __( 'Plastic White Wide', 'polldaddy' ),
				122 => __( 'Plastic Grey Narrow', 'polldaddy' ),
				123 => __( 'Plastic Grey Medium', 'polldaddy' ),
				124 => __( 'Plastic Grey Wide', 'polldaddy' ),
				125 => __( 'Plastic Black Narrow', 'polldaddy' ),
				126 => __( 'Plastic Black Medium', 'polldaddy' ),
				127 => __( 'Plastic Black Wide', 'polldaddy' ),
				128 => __( 'Manga Narrow', 'polldaddy' ),
				129 => __( 'Manga Medium', 'polldaddy' ),
				130 => __( 'Manga Wide', 'polldaddy' ),
				131 => __( 'Tech Dark Narrow', 'polldaddy' ),
				132 => __( 'Tech Dark Medium', 'polldaddy' ),
				133 => __( 'Tech Dark Wide', 'polldaddy' ),
				134 => __( 'Tech Grey Narrow', 'polldaddy' ),
				135 => __( 'Tech Grey Medium', 'polldaddy' ),
				136 => __( 'Tech Grey Wide', 'polldaddy' ),
				137 => __( 'Tech Light Narrow', 'polldaddy' ),
				138 => __( 'Tech Light Medium', 'polldaddy' ),
				139 => __( 'Tech Light Wide', 'polldaddy' ),
				140 => __( 'Working Male Narrow', 'polldaddy' ),
				141 => __( 'Working Male Medium', 'polldaddy' ),
				142 => __( 'Working Male Wide', 'polldaddy' ),
				143 => __( 'Working Female Narrow', 'polldaddy' ),
				144 => __( 'Working Female Medium', 'polldaddy' ),
				145 => __( 'Working Female Wide', 'polldaddy' ),
				146 => __( 'Thinking Male Narrow', 'polldaddy' ),
				147 => __( 'Thinking Male Medium', 'polldaddy' ),
				148 => __( 'Thinking Male Wide', 'polldaddy' ),
				149 => __( 'Thinking Female Narrow', 'polldaddy' ),
				150 => __( 'Thinking Female Medium', 'polldaddy' ),
				151 => __( 'Thinking Female Wide', 'polldaddy' ),
				152 => __( 'Sunset Narrow', 'polldaddy' ),
				153 => __( 'Sunset Medium', 'polldaddy' ),
				154 => __( 'Sunset Wide', 'polldaddy' ),
				155 => __( 'Music Medium', 'polldaddy' ),
				156 => __( 'Music Wide', 'polldaddy' )
			);

			$polldaddy->reset();
			$styles = $polldaddy->get_styles();

			if ( !empty( $styles ) && !empty( $styles->style ) && count( $styles->style ) > 0 ) {
				foreach ( (array) $styles->style as $style ) {
					$options[ (int) $style->_id ] = $style->title;
				}
			}
		}
		$this->print_errors();
?>
<div id="options-page" class="wrap">
  <div class="icon32" id="icon-options-general"><br/></div>
  <h2>
    <?php _e( 'Poll Settings', 'polldaddy' ); ?>
  </h2>
	<?php 
		if ( $this->is_admin || $this->multiple_accounts ) {
			if ( $account_email != false ) {
	?>
	<h3>
		<?php _e( 'Polldaddy Account Info', 'polldaddy' ); ?>
	</h3>
	<p><?php _e( '<em>Polldaddy</em> and <em>WordPress.com</em> are now connected using <a href="http://en.support.wordpress.com/wpcc-faq/">WordPress.com Connect</a>. If you have a WordPress.com account you can use it to login to <a href="http://polldaddy.com/">Polldaddy.com</a>. Click on the Polldaddy "sign in" button, authorize the connection and create your new Polldaddy account.', 'polldaddy' ); ?></p>
	<p><?php _e( 'Login to the Polldaddy website and scroll to the end of your <a href="http://polldaddy.com/account/#apikey">account page</a> to create or retrieve an API key.', 'polldaddy' ); ?></p>
	<p><?php printf( __( 'Your account is currently linked to this API key: <strong>%s</strong>', 'polldaddy' ), WP_POLLDADDY__PARTNERGUID ); ?></p>
	<br />
	<h3><?php _e( 'Link to a different Polldaddy account', 'polldaddy' ); ?></h3>
		<?php } else { ?>
			<br />
			<h3><?php _e( 'Link to your Polldaddy account', 'polldaddy' ); ?></h3>
		<?php } ?>
  <form action="" method="post">
    <table class="form-table">
      <tbody>
        <tr class="form-field form-required">
          <th valign="top" scope="row">
            <label for="polldaddy-email">
              <?php _e( 'Polldaddy.com API Key', 'polldaddy' ); ?>
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
  <br />
  <?php } ?>
  <h3>
    <?php _e( 'General Settings', 'polldaddy' ); ?>
  </h3>
  <form action="" method="post">
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th valign="top" scope="row">
            <label>
              <?php _e( 'Default poll settings', 'polldaddy' ); ?>
            </label>
          </th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>poll-defaults</span></legend><?php
		foreach ( array(  'randomiseAnswers' => __( 'Randomize answer order', 'polldaddy' ), 'otherAnswer' => __( 'Allow other answers', 'polldaddy' ), 'multipleChoice' => __( 'Multiple choice', 'polldaddy' ), 'sharing' => __( 'Sharing', 'polldaddy' ) ) as $option => $label ) :
			$checked = 'yes' === $poll->$option ? ' checked="checked"' : '';
?>
			<label for="<?php echo $option; ?>"><input type="checkbox"<?php echo $checked; ?> value="1" id="<?php echo $option; ?>" name="<?php echo $option; ?>" /> <?php echo esc_html( $label ); ?></label><br />

<?php  endforeach; ?>
              <br class="clear" />
              <br class="clear" />
              <div class="field">
              <label for="resultsType" class="pd-label">
              	<?php _e( 'Results Display', 'polldaddy' ); ?></label>
                <select id="resultsType" name="resultsType">
                  <option <?php echo $poll->resultsType == 'show' ? 'selected="selected"':''; ?> value="show"><?php _e( 'Show', 'polldaddy' ); ?></option>
                  <option <?php echo $poll->resultsType == 'hide' ? 'selected="selected"':''; ?> value="hide"><?php _e( 'Hide', 'polldaddy' ); ?></option>
                  <option <?php echo $poll->resultsType == 'percent' ? 'selected="selected"':''; ?> value="percent"><?php _e( 'Percentages', 'polldaddy' ); ?></option>
                </select>
              </div>
              <br class="clear" />
              <div class="field">
              <label for="styleID" class="pd-label">
               <?php _e( 'Poll style', 'polldaddy' ); ?></label>
                <select id="styleID" name="styleID"><?php
		foreach ( (array) $options as $styleID => $label ) :
			$selected = $styleID == $poll->styleID ? ' selected="selected"' : ''; ?>
        						<option value="<?php echo (int) $styleID; ?>"<?php echo $selected; ?>><?php echo esc_html( $label ); ?></option><?php
		endforeach;?>
                </select>
                </div>
                </div>
              <br class="clear" />
              <div class="field">
              <label for="blockRepeatVotersType" class="pd-label">
              <?php _e( 'Repeat Voting', 'polldaddy' ); ?></label>
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
             </div>
              <br class="clear" />
            </fieldset>
          </td>
        </tr>
        <?php $this->plugin_options_add(); ?>
      </tbody>
    </table>
    <p class="submit">
      <?php wp_nonce_field( 'polldaddy-account' ); ?>
      <input type="hidden" name="action" value="update-options" />
      <input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Options', 'polldaddy' ) ); ?>" />
    </p>
  </form>
</div>
  <?php
	}

	function plugin_options_add() {}

	function round( $number, $increments ) {
		$increments = 1 / $increments;
		return round( $number * $increments ) / $increments;
	}

	function signup() {
		return $this->api_key_page();
	}

	function can_edit( &$poll ) {
		if ( empty( $poll->_owner ) ) {
			$this->log( 'can_edit: poll owner is empty.' );
			return true;
		}

		if ( $this->id == $poll->_owner ) {
			$this->log( 'can_edit: poll owner equals id.' );
			return true;
		}
		
		if ( $poll->parentID == (int) $GLOBALS['blog_id'] && current_user_can( 'edit_others_posts' ) ) {
			$this->log( 'can_edit: poll was created on this blog and current user can edit_others_posts' );
			return true;
		}

		//check to see if poll owner is a member of this blog
		if ( function_exists( 'get_users' ) ) {      
			$user = get_users( array( 'include' => $poll->_owner ) ); 
			if ( empty( $user ) ) {
				$this->log( 'can_edit: poll owner is not a member of this blog.' );
				return false;
			}
		}

		if ( false == (bool) current_user_can( 'edit_others_posts' ) )
			$this->log( 'can_edit: current user cannot edit_others_posts.' );

		return (bool) current_user_can( 'edit_others_posts' );
	}

	function log( $message ) {}
}

require dirname( __FILE__ ).'/rating.php';
require dirname( __FILE__ ).'/ajax.php';
require dirname( __FILE__ ).'/popups.php';
require dirname( __FILE__ ).'/polldaddy-org.php';

$GLOBALS[ 'wp_log_plugins' ][] = 'polldaddy';

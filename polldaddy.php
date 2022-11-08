<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Crowdsignal Polls & Ratings
 * Plugin URI: http://wordpress.org/extend/plugins/polldaddy/
 * Description: Create and manage Crowdsignal polls and ratings in WordPress
 * Author: Automattic, Inc.
 * Author URL: https://crowdsignal.com/
 * Version: 3.0.10
 */

// To hardcode your Polldaddy PartnerGUID (API Key), add the (uncommented) line below with the PartnerGUID to your `wp-config.php`
// define( 'WP_POLLDADDY__PARTNERGUID', '12345…' );

if ( ! defined( 'POLLDADDY_API_HOST' ) ) {
	define( 'POLLDADDY_API_HOST', 'api.crowdsignal.com' );
}

if ( ! defined( 'POLLDADDY_API_VERSION' ) ) {
	define( 'POLLDADDY_API_VERSION', 'v1' );
}

function polldaddy_api_path( $path, $version = POLLDADDY_API_VERSION ) {
	return sprintf( '/%s%s', $version, $path );
}

function polldaddy_api_url( $path, $version = POLLDADDY_API_VERSION, $protocol = 'https' ) {
	return sprintf(
		'%s://%s%s',
		$protocol,
		POLLDADDY_API_HOST,
		rtrim( polldaddy_api_path( $path, $version ), '/' )
	);
}

function polldaddy_add_oembed_provider() {
	wp_oembed_add_provider( '#https?://(.+\.)?polldaddy\.com/.*#i', 'https://api.crowdsignal.com/oembed', true );
	wp_oembed_add_provider( '#https?://.+\.survey\.fm/.*#i', 'https://api.crowdsignal.com/oembed', true );
	wp_oembed_add_provider( '#https?://poll\.fm/.*#i', 'https://api.crowdsignal.com/oembed', true );
}
add_action( 'init', 'polldaddy_add_oembed_provider' );

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

	public $has_items = array();

	function __construct() {
		global $current_user;
		$this->log( 'Created WP_Polldaddy Object: constructor' );
		$this->errors                 = new WP_Error;
		$this->scheme                 = 'https';
		$this->version                = '3.0.1';
		$this->multiple_accounts      = ! empty( get_option( 'polldaddy_usercode_user' ) );
		$this->polldaddy_client_class = 'api_client';
		$this->polldaddy_clients      = array();
		$this->is_admin               = (bool) current_user_can( 'manage_options' );
		$this->is_author              = (bool) current_user_can( 'edit_posts' );
		$this->is_editor              = (bool) current_user_can( 'delete_others_pages' );
		$this->user_code              = null;
		$this->rating_user_code       = null;
		$this->id                     = ($current_user instanceof WP_User) ? intval( $current_user->ID ): 0;
		$this->has_feedback_menu      = false;
		$this->has_crowdsignal_blocks = ! empty( get_option( 'crowdsignal_user_code' ) );

		if ( class_exists( 'Jetpack' ) ) {
			if ( method_exists( 'Jetpack', 'is_active' ) && Jetpack::is_active() ) {
				$jetpack_active_modules = get_option('jetpack_active_modules');
				if ( $jetpack_active_modules && in_array( 'contact-form', $jetpack_active_modules ) )
					$this->has_feedback_menu = true;
			}

			if ( class_exists( 'Jetpack_Sync' ) && defined( 'JETPACK__VERSION' ) &&  version_compare( JETPACK__VERSION, '4.1', '<' ) ) {
				Jetpack_Sync::sync_options( __FILE__, 'polldaddy_api_key' );
			}

			add_filter( 'jetpack_options_whitelist', array( $this, 'add_to_jetpack_options_whitelist' ) );
		}

		if ( ! post_type_exists( 'feedback' ) ) {
			register_post_type(
				'feedback', array(
					'labels'                => array(
					),
					'menu_icon'             => 'dashicons-feedback',
					'show_ui'               => true,
					'show_in_menu'          => 'edit.php?post_type=feedback',
					'show_in_admin_bar'     => false,
					'public'                => false,
					'rewrite'               => false,
					'query_var'             => false,
					'capability_type'       => 'page',
					'show_in_rest'          => true,
					'rest_controller_class' => 'Grunion_Contact_Form_Endpoint',
					'capabilities'          => array(
						'create_posts'        => 'do_not_allow',
						'publish_posts'       => 'publish_pages',
						'edit_posts'          => 'edit_pages',
						'edit_others_posts'   => 'edit_others_pages',
						'delete_posts'        => 'delete_pages',
						'delete_others_posts' => 'delete_others_pages',
						'read_private_posts'  => 'read_private_pages',
						'edit_post'           => 'edit_page',
						'delete_post'         => 'delete_page',
						'read_post'           => 'read_page',
					),
					'map_meta_cap'          => true,
				)
			);
			add_action( 'admin_menu', array( $this, 'remove_feedback_menu' ) );
		}
	}

	/**
	 * Remove the feedback "All Posts" submenu if not needed.
	 */
	public function remove_feedback_menu() {
		remove_submenu_page( 'edit.php?post_type=feedback', 'edit.php?post_type=feedback' );
	}

	/**
	 * Add the polldaddy option to the Jetpack options management whitelist.
	 *
	 * @param array $options The list of whitelisted option names.
	 * @return array The updated whitelist
	 */
	public static function add_to_jetpack_options_whitelist( $options ) {
		$options[] = 'polldaddy_api_key';
		return $options;
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
		if ( isset( $_GET['page'] ) && 'pollsettings' === $_GET['page'] ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=crowdsignal-settings' ) );
			die();
		}
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_polldaddy_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'menu_alter' ) );

		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) ) {
			$guid = get_option( 'polldaddy_api_key' );
			if ( !$guid || !is_string( $guid ) )
				$guid = false;
			define( 'WP_POLLDADDY__PARTNERGUID', $guid );

		}

		$capability = 'edit_posts';
		$function   = array( &$this, 'management_page' );

		$icon_encoded = 'PHN2ZyBpZD0iY29udGVudCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMjg4IDIyMCI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNGRkZGRkY7fTwvc3R5bGU+PC9kZWZzPjx0aXRsZT5pY29uLWJsdWU8L3RpdGxlPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTI2Mi40MSw4MC4xYy04LjQ3LTIyLjU1LTE5LjA1LTQyLjgzLTI5Ljc5LTU3LjFDMjIwLjc0LDcuMjQsMjEwLC41NywyMDEuNDcsMy43OWExMi4zMiwxMi4zMiwwLDAsMC0zLjcyLDIuM2wtLjA1LS4xNUwxNiwxNzMuOTRsOC4yLDE5LjEyLDMwLjU2LTEuOTJ2MTMuMDVhMTIuNTcsMTIuNTcsMCwwLDAsMTIuNTgsMTIuNTZjLjMzLDAsLjY3LDAsMSwwbDU4Ljg1LTQuNzdhMTIuNjUsMTIuNjUsMCwwLDAsMTEuNTYtMTIuNTNWMTg1Ljg2bDEyMS40NS03LjY0YTEzLjg4LDEzLjg4LDAsMCwwLDIuMDkuMjYsMTIuMywxMi4zLDAsMCwwLDQuNDEtLjhDMjg1LjMzLDE3MC43LDI3OC42MywxMjMuMzEsMjYyLjQxLDgwLjFabS0yLjI2LDg5Ljc3Yy0xMC40OC0zLjI1LTMwLjQ0LTI4LjE1LTQ2LjY4LTcxLjM5LTE1LjcyLTQxLjktMTcuNS03My4yMS0xMi4zNC04My41NGE2LjUyLDYuNTIsMCwwLDEsMy4yMi0zLjQ4LDMuODIsMy44MiwwLDAsMSwxLjQxLS4yNGMzLjg1LDAsMTAuOTQsNC4yNiwyMC4zMSwxNi43MUMyMzYuMzYsNDEuNTksMjQ2LjU0LDYxLjE1LDI1NC43NCw4M2MxOC40NCw0OS4xMiwxNy43NCw4My43OSw5LjEzLDg3QTUuOTMsNS45MywwLDAsMSwyNjAuMTUsMTY5Ljg3Wk0xMzAuNiwxOTkuNDFhNC40LDQuNCwwLDAsMS00LDQuMzdsLTU4Ljg1LDQuNzdBNC4zOSw0LjM5LDAsMCwxLDYzLDIwNC4xOVYxOTAuNjJsNjcuNjEtNC4yNVoiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik02LDE4NS4yNmExMC4yNSwxMC4yNSwwLDAsMCwxMC4yNSwxMC4yNSwxMC4wNSwxMC4wNSwwLDAsMCw0LjM0LTFsLTcuOTQtMTguNzNBMTAuMiwxMC4yLDAsMCwwLDYsMTg1LjI2WiIvPjwvc3ZnPgo=';

		$slug = 'edit.php?post_type=feedback';

		if ( ! $this->has_feedback_menu ) {
			$hook = add_menu_page(
				__( 'Feedback', 'polldaddy' ),
				__( 'Feedback', 'polldaddy' ),
				$capability,
				$slug,
				$function,
				'data:image/svg+xml;base64,' . $icon_encoded
			);
			add_action( "load-$hook", array( &$this, 'management_page_load' ) );
		}

		foreach( array( 'polls' => __( 'Crowdsignal', 'polldaddy' ), 'ratings' => __( 'Ratings', 'polldaddy' ) ) as $menu_slug => $page_title ) {
			$menu_title  = $page_title;

			$hook = add_submenu_page( $this->has_feedback_menu ? 'feedback' : $slug, $menu_title, $menu_title, $capability, $menu_slug, $function, 99 );
			add_action( "load-$hook", array( &$this, 'management_page_load' ) );
		}

		// Add settings pages.
		foreach( array( 'crowdsignal-settings' => __( 'Crowdsignal', 'polldaddy' ), 'ratingsettings' => __( 'Ratings', 'polldaddy' ) ) as $menu_slug => $page_title ) {
			// translators: %s placeholder is the setting page type (Poll or Rating).
			$settings_page_title = sprintf( esc_html__( '%s', 'polldaddy' ), $page_title );
			$hook = add_options_page( $settings_page_title, $settings_page_title, $menu_slug == 'ratingsettings' ? 'manage_options' : 'edit_others_posts', $menu_slug, array( $this, 'settings_page' ) );
			add_action( "load-$hook", array( $this, 'management_page_load' ) );
		}

	}

	function menu_alter() {
		// Make sure we're working off a clean version.
		include( ABSPATH . WPINC . '/version.php' );

		if ( version_compare( $wp_version, '3.8', '<' ) ) {
			$css = "
				#toplevel_page_polldaddy .wp-menu-image {
					background: url( " . plugins_url( 'img/polldaddy.png', __FILE__ ) . " ) 0 90% no-repeat;
				}
				/* Retina Polldaddy Menu Icon */
				@media  only screen and (-moz-min-device-pixel-ratio: 1.5),
						only screen and (-o-min-device-pixel-ratio: 3/2),
						only screen and (-webkit-min-device-pixel-ratio: 1.5),
						only screen and (min-device-pixel-ratio: 1.5) {
					#toplevel_page_polldaddy .wp-menu-image {
						background: url( " . plugins_url( 'polldaddy@2x.png', __FILE__ ) . " ) 0 90% no-repeat;
						background-size:30px 64px;
					}
				}
				#toplevel_page_polldaddy.current .wp-menu-image,
				#toplevel_page_polldaddy.wp-has-current-submenu .wp-menu-image,
				#toplevel_page_polldaddy:hover .wp-menu-image {
					background-position: top left;
				}";
			wp_add_inline_style( 'wp-admin', $css );
		}
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
			$polldaddy_api_key = wp_remote_post( polldaddy_api_url( '/key' ), array(
					'body' => $details
				) );
			if ( is_wp_error( $polldaddy_api_key ) ) {
				$this->errors = $polldaddy_api_key;
				return false;
			}
			$polldaddy_api_key = wp_remote_retrieve_body( $polldaddy_api_key );
		} else {
			$fp = fsockopen(
				polldaddy_api_url( '/', POLLDADDY_API_VERSION, 'tls' ),
				443,
				$err_num,
				$err_str,
				5
			);

			if ( !$fp ) {
				$this->errors->add( 'connect', __( "Can't connect to Polldaddy.com", 'polldaddy' ) );
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, 3 );

			global $wp_version;

			$request_body = http_build_query( $details, null, '&' );

			$request  = 'POST ' . polldaddy_api_path( '/key' ) . " HTTP/1.0\r\n";
			$request .= 'Host: ' . POLLDADDY_API_HOST . "\r\n";
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
				global $wp_version;
				if ( version_compare( $wp_version, '4.2', '<' ) ) {
					delete_option( 'pd-usercode-' . $this->id );
					add_option( 'pd-usercode-' . $this->id, '', '', false );
				} else {
					update_option( 'pd-usercode-' . $this->id, '', false );
				}
				$this->set_api_user_code();
			}
	}

	function print_errors() {
		if ( !$error_codes = $this->errors->get_error_codes() )
			return;

		$this->render_partial( 'errors', array( 'error_codes' => $error_codes, 'errors' => $this->errors ) );
		$this->errors = new WP_Error;
	}

	function api_key_page() {
		$this->print_errors();
		?>

		<div class="wrap">
			<h2 id="polldaddy-header"><?php _e( 'Crowdsignal', 'polldaddy' ); ?></h2>

			<p><?php printf( __( 'Before you can use the Crowdsignal plugin, you need to enter your <a href="%s">Crowdsignal.com</a> account details.', 'polldaddy' ), 'https://app.crowdsignal.com/' ); ?></p>

			<form action="" method="post">
				<table class="form-table">
					<tbody>
						<tr class="form-field form-required">
							<th valign="top" scope="row">
								<label for="polldaddy-email"><?php _e( 'Crowdsignal Email Address', 'polldaddy' ); ?></label>
							</th>
							<td>
								<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" />
							</td>
						</tr>
						<tr class="form-field form-required">
							<th valign="top" scope="row">
								<label for="polldaddy-password"><?php _e( 'Crowdsignal Password', 'polldaddy' ); ?></label>
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

	function get_usercode( $for_current_user = false ) {
		// sitewide access to Crowdsignal account
		if ( ! $for_current_user && $user_id = get_option( 'polldaddy_usercode_user' ) ) {
			return get_option( 'pd-usercode-' . $user_id );
		} else {
			return get_option( 'pd-usercode-' . $this->id );
		}
	}

	function set_api_user_code() {

		$this->user_code = get_option( 'pd-usercode-'.$this->id );

		if ( empty( $this->user_code ) ) {
			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID );
			$polldaddy->reset();

			$this->user_code = $polldaddy->get_usercode( $this->id );

			if ( !empty( $this->user_code ) ) {
				global $wp_version;
				if ( version_compare( $wp_version, '4.2', '<' ) ) {
					delete_option( 'pd-usercode-' . $this->id );
					add_option( 'pd-usercode-' . $this->id, $this->user_code, '', false );
				} else {
					update_option( 'pd-usercode-' . $this->id, $this->user_code, false );
				}
			} elseif ( get_option( 'crowdsignal_api_key' ) === get_option( 'polldaddy_api_key' ) ) {
				// attempt to get credentials from Crowdsignal Forms.
				$this->user_code = get_option( 'crowdsignal_user_code' );
			} elseif ( get_option( 'polldaddy_api_key' ) ) {
				$this->contact_support_message( 'There was a problem linking your account', $polldaddy->errors );
			}
		}
	}

	function management_page_load() {
		wp_reset_vars( array( 'page', 'action', 'poll', 'style', 'rating', 'id' ) );
		global $plugin_page, $page, $action, $poll, $style, $rating, $id, $wp_locale;

		if (
			isset( $_GET['step'] ) && 2 === (int) $_GET['step']
			&& isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD']
			&& isset( $_POST['got_api_key'] ) && get_option( 'crowdsignal_api_key_secret' ) === $_POST['got_api_key']
			&& isset( $_POST['api_key'] )
		) {
			$api_key = sanitize_key( wp_unslash( $_POST['api_key'] ) );
			$crowdsignal = $this->get_client( $api_key );
			$crowdsignal->reset();
			$usercode = $crowdsignal->get_usercode( $this->id );
			if ( $usercode ) {
				update_option( 'polldaddy_api_key', $api_key );
				update_option( 'crowdsignal_api_key', $api_key );
				update_option( 'crowdsignal_user_code', $usercode );
				update_option( 'pd-usercode-' . $this->id, $usercode );
				delete_option( 'crowdsignal_api_key_secret' );
				$connected = true;
			} else {
				$connected = false;
			}
			$this->render_partial(
				'html-admin-setup-step-2',
				array(
					'is_connected'  => $connected,
				)
			);
			die();
		}

		if (
			isset( $_POST['action'] )
			&& $_POST['action'] === 'disconnect'
			&& current_user_can( 'edit_others_posts' )
		) {
			check_admin_referer( 'disconnect-api-key' );
			delete_option( 'polldaddy_api_key' );
			delete_option( 'crowdsignal_api_key' );
			delete_option( 'crowdsignal_user_code' );
			delete_option( 'pd-usercode-' . $this->id );
			wp_safe_redirect( admin_url( 'options-general.php?page=crowdsignal-settings&msg=disconnected' ) );
		}

		$this->set_api_user_code();

		if ( empty( $this->user_code ) && 'crowdsignal-settings' === $page && 'options' !== $action ) {
			// one last try to get the user code automatically if possible
			$this->user_code = apply_filters_ref_array( 'polldaddy_get_user_code', array( $this->user_code, &$this ) );
			if ( false == $this->user_code && $action != 'restore-account' )
				$action = 'signup';
		}

		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		wp_enqueue_style( 'admin-styles', plugin_dir_url( __FILE__ ) . '/admin-styles.css', array(), '1.5.12' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_script( 'polls', "{$this->base_url}js/polldaddy.js", array( 'jquery', 'jquery-ui-sortable', 'jquery-form', 'wp-components' ), $this->version );
		wp_enqueue_script( 'polls-common', "{$this->base_url}js/common.js", array(), $this->version );

		if ( $page == 'polls' ) {
			if ( !$this->is_author && in_array( $action, array( 'edit', 'edit-poll', 'create-poll', 'edit-style', 'create-style', 'list-styles', 'options', 'update-options', 'import-account', 'create-block-poll' ) ) ) {//check user privileges has access to action
				$action = '';
			}

			switch ( $action ) {
				case 'create-block-poll':
					$post_id = wp_insert_post(
						array(
							'post_title'   => esc_html__( 'Crowdsignal blocks in WordPress' ),

							'post_content' => '
								<!-- wp:paragraph -->
								<p>Welcome to this little demo page! We would love to introduce you to our set of Crowdsignal blocks and created this post for you, so that you can test and play with all of them right inside of your editor. </p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p><a href="https://wordpress.org/support/article/how-to-use-the-preview-function/">Preview this post</a> if you would like to test the Crowdsignal blocks from your visitors perspective. <em>Oh and please feel free to delete this draft post anytime</em>, it was only created for demo purposes.</p>
								<!-- /wp:paragraph -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Overview</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>Let\'s start with a quick overview of all our current blocks available in your WordPress editor. You can <a href="https://wordpress.com/support/wordpress-editor/">find all these blocks inside your block library via searching</a> for their name or simply by searching "Crowdsignal".</p>
								<!-- /wp:paragraph -->

								<!-- wp:image {"align":"wide","id":241,"sizeSlug":"full","linkDestination":"none"} -->
								<figure class="wp-block-image alignwide size-full"><img src="https://crowdsignal.files.wordpress.com/2021/11/crowdsignalcards.png" alt="" class="wp-image-241"/></figure>
								<!-- /wp:image -->

								<!-- wp:paragraph -->
								<p>If you want to learn more about Crowdsignal please go to <a href="https://crowdsignal.com" data-type="URL" data-id="https://crowdsignal.com">crowdsignal.com</a> and join our little <a href="https://crowdsignalfeedback.wordpress.com/">community</a> all about feedback here.</p>
								<!-- /wp:paragraph -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Polls</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>We all have opinions! Curious about the opinion of your audience? Start asking with our poll block. It makes creating a poll as fast and simple as listing bullet points.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>&nbsp;You can choose between a button or a list style for your answer options, and you can fully customize the styling of the block. &nbsp;By default the poll block will support your theme styling, but it’s up to you if you want to keep it. You can customize the style how you want, from font-family to border colours.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Just click in the poll below and start editing. </p>
								<!-- /wp:paragraph -->

								<!-- wp:crowdsignal-forms/poll {"pollId":"","title":"Demo Poll block","question":"What do you think about this demo page","answers":[{"text":"Super useful","answerId":""},{"text":"Not sure yet","answerId":""},{"text":"I don\'t like it","answerId":""}],"borderWidth":5,"borderRadius":5,"hasBoxShadow":true,"fontFamily":"Open+Sans","className":"is-style-buttons"} /-->

								<!-- wp:paragraph -->
								<p>And everything else you expect from a Crowdsignal poll is also available — such as setting “single answer” or “multiple answer” choices, a customised confirmation message, poll timeframe, and avoidance of double voting.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Here is a short demo video for how to set up this block, not that you would need it ;) </p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src":"https://crowdsignal.files.wordpress.com/2021/11/add-poll-tutorial-720.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-poll-tutorial-720.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Feedback Button</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>You might have spotted it already, in the bottom left corner of this page: Our Feedback button.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>This is a floating button that lives above your site\'s content. Always visible this button makes giving feedback easy! User can send you a message and provide their email address so you could can get back to them. Needless to say that you can fully customize the design and text, including the label of the button itself. Feel free to make it a "Contact me" or "Say hello" button or anything you like.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>And yes, you can change its placement! You can put the button in any corner of your site. Just try it! Click in the feedback and start editing.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Don\'t miss out on your customers\' feedback. Keep your door open anytime and place a feedback button on all your pages. </p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src":"https://crowdsignal.files.wordpress.com/2021/11/add-feedback-button-tutorial.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-feedback-button-tutorial.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Voting</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>Sometimes we need just quick and fast feedback from our audience. A quick voting button might be all you need. Fully customizable of course.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>There is already a “like” button at the end of a WordPress post that you can click to express satisfaction or agreement. But what if you want to ask readers their opinion on a subject in the middle of a post? Or what if you want to present several ideas and find out which one is the most popular? Wouldn’t it be great to ask readers what they think without having to leave the editor or switch to another service or plugin?</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>That’s what we thought! Say hello to our Voting Block:</p>
								<!-- /wp:paragraph -->

								<!-- wp:crowdsignal-forms/vote {"pollId":"","title":"Demo Vote block"} -->
								<!-- wp:crowdsignal-forms/vote-item {"answerId":"","type":"up","textColor":"#066127","borderColor":"#066127"} /-->

								<!-- wp:crowdsignal-forms/vote-item {"answerId":"","type":"down","textColor":"#c6302e","borderColor":"#c6302e"} /-->
								<!-- /wp:crowdsignal-forms/vote -->

								<!-- wp:paragraph -->
								<p>It’s a simple block that adds two voting buttons—thumbs up, thumbs down—to your post wherever you want to place them. Customize the block in different sizes and colors, with or without a border, and with or without a visible vote counter. Put several in a single post, next to different ideas, to see how they stack up for readers. Make the block your own!</p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src":"https://crowdsignal.files.wordpress.com/2021/11/add-vote-tutorial.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-vote-tutorial.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Applause</h2>
								<!-- /wp:heading -->

								<!-- wp:image {"sizeSlug":"large"} -->
								<figure class="wp-block-image size-large"><img src="https://crowdsignal.files.wordpress.com/2021/11/17claps-small.gif" alt=""/></figure>
								<!-- /wp:image -->

								<!-- wp:paragraph -->
								<p>The Applause block is a simpler and more playful version of our Voting block. The main differences are users only being able to give positive feedback and encouraging users to “make as much noise as they want”. Meaning this block does not only allow repeated voting, but even encourages it.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Let your audience make some noise with a big round of applause.</p>
								<!-- /wp:paragraph -->

								<!-- wp:crowdsignal-forms/applause {"pollId":"","title":"Demo Applause block","answerId":"","size":"large","borderWidth":1,"borderRadius":5} /-->

								<!-- wp:paragraph -->
								<p><a href="https://wordpress.org/support/article/how-to-use-the-preview-function/">Preview this post</a> and try clapping yourself! It\'s fun.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>The block currently comes in three different sizes, and can be customised with a button-like styling, including a border, border radius and some colour customisation options.</p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src": "https://crowdsignal.files.wordpress.com/2021/11/add-applause-block-tutorial.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-applause-block-tutorial.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Embed Surveys &amp; Forms</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>So far we only talked about quick and fast ways to collect feedback or opinions from your audience. But what if you have many questions or want to create simple forms? You can do this with Crowdsignal, too! Create a survey or form on app.crowdsignal.com and embed it into your WordPress post or site. Similar like here:</p>
								<!-- /wp:paragraph -->

								<!-- wp:embed {"url":"https://crowdsignal.survey.fm/product-market-fit-score","type":"rich","providerNameSlug":"crowdsignal","responsive":true,"align":"wide"} -->
								<figure class="wp-block-embed alignwide is-type-rich is-provider-crowdsignal wp-block-embed-crowdsignal"><div class="wp-block-embed__wrapper">
								https://crowdsignal.survey.fm/product-market-fit-score
								</div></figure>
								<!-- /wp:embed -->

								<!-- wp:paragraph -->
								<p>The Crowdsignal survey above was embedded using our  "Single question per page mode."&nbsp;It’s exactly what it sounds like: In this mode, no matter how many questions your survey has, your respondents will always see one question at a time. Single Mode shines when you embed a survey into your website or blog post. Surveys with multiple questions can take up a lot of space, overwhelming your site. If you’re not sure whether your readers will take the survey at all, it disrupts the reading experience. With Single Mode, a survey&nbsp; uses the same amount of space as an image, even a really long survey.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Once they provide an answer (or skip the question),&nbsp; the next question loads. It has a playful&nbsp; feel, like flipping through a slide show. Every answered question feels like progress.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>You can choose between several transition options, and decide whether the questions should move from top to bottom, or from left to right.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p><strong>Ready to create one? Here’s how:</strong></p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- Go to <a href="https://app.crowdsignal.com/dashboard">app.crowdsignal.com</a> (we will log you in with your WordPress.com account - magic ;)) .</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- Create a new survey.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- In the Editor, choose “Single Mode” at the top left.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- Then create as many questions as you like and style your theme.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- When you are ready click on Sharing and copy the URL of your survey.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- Go back to your WordPress editor and paste the URL of your survey into your post</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>- Done! Your survey will appear in your post.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Here is a short demo video for you that shows you how it works in less than a minute:</p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src": "https://crowdsignal.files.wordpress.com/2021/11/add-survey-tutorial-yt.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-survey-tutorial-yt.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:spacer {"height":60} -->
								<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
								<!-- /wp:spacer -->

								<!-- wp:heading -->
								<h2>Measure NPS</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>While we are driving our projects, working hard on our products, we all wonder: How are we doing? Are people satisfied with our service? Are we doing better since last month?&nbsp;</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Sometimes you want to measure your progress over time. <a href="https://crowdsignal.com/2021/03/16/measure-nps/">Measure and monitor the customer satisfaction and growth potential of your product with a Net Promoter Score</a>. </p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>We have built a Gutenberg block for you that makes it easier than ever before to track your Net Promoter Score on WordPress. If you have <a href="https://wordpress.org/support/article/how-to-use-the-preview-function/">previewed this post</a> before, you might have seen the NPS question already in a modal window. </p>
								<!-- /wp:paragraph -->

								<!-- wp:crowdsignal-forms/nps {"surveyId":"","title":"Demo NPS block","viewThreshold":"2"} /-->

								<!-- wp:paragraph -->
								<p>The moment you add the block, you are basically done. The design of the block is based on your site’s theme. You can still customize the styling of the block, or edit the questions, but that might not even be necessary.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>To get the most out of your NPS data, it is important to show the question only to users that are already familiar with your service or product. You can configure the block to only show to repeat visitors. It’s more likely you will get feedback from someone who knows what they are talking about, and you can make sure new users are not interrupted during their first visit to your site.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>After you publish the block go to the results page of the block and monitor your results. We have built a special results page for you to track your NPS score and to analyse any additional feedback. </p>
								<!-- /wp:paragraph -->

								<!-- wp:image {"sizeSlug":"large"} -->
								<figure class="wp-block-image size-large"><img src="https://s0.wp.com/wp-content/themes/a8c/crowd-signal/assets/images/AnalyseResults.png" alt=""/></figure>
								<!-- /wp:image -->

								<!-- wp:paragraph -->
								<p>We provide an analytics dashboard with our block that automatically calculates the Net Promoter Score for you in real-time and allows you to monitor your score over time. Are the differences geographic? Filter your results based on countries.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>By the way, did you know you also can get email notifications or a ping in your Slack channel any time you get an NPS rating? Just click on the little “connect” button on your results page.</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>Here is a quick tutorial video on how it works.</p>
								<!-- /wp:paragraph -->

								<!-- wp:video {"src": "https://crowdsignal.files.wordpress.com/2021/11/add-nps-tutorial-long-3.mp4"} -->
								<figure class="wp-block-video"><video controls src="https://crowdsignal.files.wordpress.com/2021/11/add-nps-tutorial-long-3.mp4"></video></figure>
								<!-- /wp:video -->

								<!-- wp:crowdsignal-forms/feedback {"surveyId":"","title":"Demo Feedback block"} /-->

								<!-- wp:paragraph -->
								<p></p>
								<!-- /wp:paragraph -->
							',
						)
					);
					if ( ! is_wp_error( $post_id ) ) {
						wp_safe_redirect( admin_url( 'post.php?post=' . intval( $post_id ) . '&action=edit' ) );
					} else {
						// admin.php?page=polls
						// wp_safe_redirect( admin_url( 'admin.php?page=polls' ) );
					}
					break;

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
			}//end switch
		} elseif ( $page == 'crowdsignal-settings' ) {
			$plugin_page = 'crowdsignal-settings';
		} elseif ( $page == 'ratings' ) {
			if ( empty( $action ) ) {
				$action = 'reports';
			}
			$plugin_page = 'ratings&action=reports';
		} elseif ( $page == 'ratingsettings' ) {
			$plugin_page = 'ratingsettings';
			wp_enqueue_script( 'rating-text-color', "{$this->base_url}js/jscolor.js", array(), $this->version );
			wp_enqueue_script( 'ratings', "{$this->base_url}js/rating.js", array(), $this->version );
			wp_localize_script( 'polls-common', 'adminRatingsL10n', array(
				'star_colors' => __( 'Star Colors', 'polldaddy' ), 'star_size' =>  __( 'Star Size', 'polldaddy' ),
				'nero_type' => __( 'Nero Type', 'polldaddy' ), 'nero_size' => __( 'Nero Size', 'polldaddy' ), ) );
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

		if ( 'polls' === $page || 'crowdsignal-settings' === $page ) {
			switch ( $action ) {
			case 'multi-account':
				if ( ! isset( $_POST['crowdsignal_multiuser'] ) ) {
					delete_option( 'polldaddy_usercode_user' );
				}
				break;
			case 'reset-account' : // reset everything
				global $current_user;
				check_admin_referer( 'polldaddy-reset' . $this->id );
				$fields = array( 'polldaddy_api_key', 'pd-rating-comments', 'pd-rating-comments-id', 'pd-rating-comments-pos', 'pd-rating-exclude-post-ids', 'pd-rating-pages', 'pd-rating-pages-id', 'pd-rating-posts', 'pd-rating-posts-id', 'pd-rating-posts-index', 'pd-rating-posts-index-id', 'pd-rating-posts-index-pos', 'pd-rating-posts-pos', 'pd-rating-title-filter', 'pd-rating-usercode', 'pd-rich-snippets', 'pd-usercode-' . $current_user->ID );
				$msg = __( "You have just reset your Polldaddy connection settings." ) . "\n\n";
				foreach( $fields as $field ) {
					$value = get_option( $field );
					if ( $value != false ) {
						$settings[ $field ] = $value;
						$msg .= "$field: $value\n";
						delete_option( $field );
					}
				}
				if ( isset( $_POST[ 'email' ] ) )
					wp_mail( $current_user->user_email, "Crowdsignal Settings", $msg );
				update_option( 'polldaddy_settings', $settings );
				break;
			case 'restore-account' : // restore everything
				global $current_user;
				check_admin_referer( 'polldaddy-restore' . $this->id );
				$previous_settings = get_option( 'polldaddy_settings' );
				foreach( $previous_settings as $key => $value )
					update_option( $key, $value );
				delete_option( 'polldaddy_settings' );
				break;
			case 'restore-ratings' : // restore ratings
				global $current_user;
				check_admin_referer( 'polldaddy-restore-ratings' . $this->id );
				$previous_settings = get_option( 'polldaddy_settings' );
				$fields = array( 'pd-rating-comments', 'pd-rating-comments-id', 'pd-rating-comments-pos', 'pd-rating-exclude-post-ids', 'pd-rating-pages', 'pd-rating-pages-id', 'pd-rating-posts', 'pd-rating-posts-id', 'pd-rating-posts-index', 'pd-rating-posts-index-id', 'pd-rating-posts-index-pos', 'pd-rating-posts-pos', 'pd-rating-title-filter' );
				foreach( $fields as $key ) {
					if ( isset( $previous_settings[ $key ] ) )
						update_option( $key, $previous_settings[ $key ] );
				}
				break;
			case 'signup' : // sign up for first time
			case 'account' : // reauthenticate
			case 'import-account' : // reauthenticate
				if ( !$is_POST )
					return;

				check_admin_referer( 'polldaddy-account' );

				$this->user_code = '';
				global $wp_version;
				if ( version_compare( $wp_version, '4.2', '<' ) ) {
					delete_option( 'pd-usercode-' . $this->id );
					add_option( 'pd-usercode-' . $this->id, '', false );
				} else {
					update_option( 'pd-usercode-' . $this->id, '', false );
				}

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
				if ( ! empty( $_POST['answer'] ) ) {
					foreach ( $_POST['answer'] as $answer_id => $answer ) {
						$answer = stripslashes( trim( $answer ) );

						if ( strlen( $answer ) > 0 ) {
							$answer = wp_kses( $answer, $allowedtags );

							$args['text'] = (string) $answer;

							$answer_id = str_replace( 'new', '', $answer_id );
							$mc        = '';
							$mt        = 0;

							if ( isset( $media[ $answer_id ] ) ) {
								$mc = esc_html( $media[ $answer_id ] );
							}

							if ( isset( $mediaType[ $answer_id ] ) ) {
								$mt = intval( $mediaType[ $answer_id ] );
							}

							$args['mediaType'] = $mt;
							$args['mediaCode'] = $mc;

							if ( $answer_id > 1000 ) {
								$answer = polldaddy_poll_answer( $args, $answer_id );
							} else {
								$answer = polldaddy_poll_answer( $args );
							}

							if ( isset( $answer ) && is_a( $answer, 'Polldaddy_Poll_Answer' ) ) {
								$answers[] = $answer;
							}
						}
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

				if ( ! empty( $_POST['answer'] ) ) {
					foreach ( $_POST['answer'] as $answer_id => $answer ) {
						$answer = stripslashes( trim( $answer ) );

						if ( strlen( $answer ) > 0 ) {
							$answer = wp_kses( $answer, $allowedtags );

							$args['text'] = (string) $answer;

							$answer_id = (int) str_replace( 'new', '', $answer_id );
							$mc        = '';
							$mt        = 0;

							if ( isset( $media[ $answer_id ] ) ) {
								$mc = esc_html( $media[ $answer_id ] );
							}

							if ( isset( $mediaType[ $answer_id ] ) ) {
								$mt = intval( $mediaType[ $answer_id ] );
							}

							$args['mediaType'] = $mt;
							$args['mediaCode'] = $mc;

							$answer = polldaddy_poll_answer( $args );

							if ( isset( $answer ) && is_a( $answer, 'Polldaddy_Poll_Answer' ) ) {
								$answers[] = $answer;
							}
						}
					}
				}

				if ( 2 > count( $answers ) ) {
					$this->errors->add( 'answer', __( 'You must include at least 2 answers', 'polldaddy' ) );
				}

				if ( $this->errors->get_error_codes() ) {
					return false;
				}

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

				if ( ! empty( $_POST['multipleChoice'] ) ) {
					$user_defaults['choices'] = 1;
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
		} elseif ( 'ratings' === $page || 'ratingsettings' === $page ) {

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

		if ( isset( $_GET['message'] ) ) {
			switch ( (string) $_GET['message'] ) {
				case 'deleted' :
					$deleted = (int) $_GET['deleted'];
					if ( 1 == $deleted ) {
						$message = __( 'Poll deleted.', 'polldaddy' );
					} else {
						$message = sprintf( _n( '%s Poll Deleted.', '%s Polls Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
					}
					break;
				case 'opened' :
					$opened = (int) $_GET['opened'];
					if ( 1 == $opened ) {
						$message = __( 'Poll opened.', 'polldaddy' );
					} else {
						$message = sprintf( _n( '%s Poll Opened.', '%s Polls Opened.', $opened, 'polldaddy' ), number_format_i18n( $opened ) );
					}
					break;
				case 'closed' :
					$closed = (int) $_GET['closed'];
					if ( 1 == $closed ) {
						$message = __( 'Poll closed.', 'polldaddy' );
					} else {
						$message = sprintf( _n( '%s Poll Closed.', '%s Polls Closed.', $closed, 'polldaddy' ), number_format_i18n( $closed ) );
					}
					break;
				case 'updated' :
					$message = __( 'Poll updated.', 'polldaddy' );
					break;
				case 'created' :
					$message = __( 'Poll created.', 'polldaddy' );
					if ( isset( $_GET['iframe'] ) ) {
						$message .= ' <input type="button" class="button polldaddy-send-to-editor" value="' . esc_attr( __( 'Embed in Post', 'polldaddy' ) ) . '" />';
					}
					break;
				case 'updated-style' :
					$message = __( 'Custom Style updated.', 'polldaddy' );
					break;
				case 'created-style' :
					$message = __( 'Custom Style created.', 'polldaddy' );
					break;
				case 'deleted-style' :
					$deleted = (int) $_GET['deleted'];
					if ( 1 == $deleted ) {
						$message = __( 'Custom Style deleted.', 'polldaddy' );
					} else {
						$message = sprintf( _n( '%s Style Deleted.', '%s Custom Styles Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
					}
					break;
				case 'connected' :
				case 'imported-account' :
					$message = __( 'Account Linked.', 'polldaddy' );
					break;
				case 'api-key-not-added' :
					$message = __( 'There was a problem linking your account.', 'polldaddy' );
					break;
				case 'updated-options' :
					$message = __( 'Options Updated.', 'polldaddy' );
					break;
				case 'deleted-rating' :
					$deleted = (int) $_GET['deleted'];
					if ( 1 == $deleted ) {
						$message = __( 'Rating deleted.', 'polldaddy' );
					} else {
						$message = sprintf( _n( '%s Rating Deleted.', '%s Ratings Deleted.', $deleted, 'polldaddy' ), number_format_i18n( $deleted ) );
					}
					break;
			}//end switch
		}

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

	function settings_page() {
		global $page, $action;
		?>
		<div class="wrap" id="manage-polls">
			<div class="cs-wrapper">
				<?php
				$this->set_api_user_code();

				if ( isset( $_GET['page'] ) ) { // phpcs:ignore
					$page = $_GET['page']; // phpcs:ignore
				}
				if ( 'crowdsignal-settings' === $page ) {
					if ( ! current_user_can( 'edit_others_posts' ) ) { // check user privileges has access to action.
						return;
					}
					$this->plugin_options();
				} elseif ( 'ratingsettings' === $page ) {
					if ( 'update-rating' === $action ) {
						$this->update_rating();
					}

					$this->rating_settings();
				}
				?>
			</div>
		</div>
		<?php
	}

	function management_page() {
		global $page, $action, $poll, $style, $rating;
		$poll   = (int) $poll;
		$style  = (int) $style;
		$rating = esc_html( $rating );
		$wrap_style = $page === 'polls' ? 'polls' : 'ratings';
		?>

		<div class="wrap cs-dashboard__crowdsignal_<?php echo $wrap_style ; ?>_wrap" id="manage-polls">
			<div class="cs-wrapper">
				<?php
				if ( 'polls' === $page ) {
					?><div class="cs-pre-wrap"></div><?php
					if ( ! $this->is_author && in_array( $action, array( 'edit', 'edit-poll', 'create-poll', 'edit-style', 'create-style', 'list-styles' ), true ) ) { // check user privileges has access to action.
						$action = '';
					}
					switch ( $action ) {
						case 'preview':
							if ( isset( $_GET['iframe'] ) ) {
								if ( isset( $_GET['popup'] ) ) {
									?>
									<h2 id="poll-list-header"><?php printf( __( 'Preview Poll <a href="%s" class="add-new-h2">All Polls</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ) ); ?></h2>
									<?php
								}
							}

							echo do_shortcode( "[crowdsignal poll=$poll cb=1]" );

							wp_print_scripts( 'polldaddy-poll-js' );
							break;
						case 'results':
							?>
							<h2 id="poll-list-header">
								<?php printf( __( 'Poll Results <a href="%s" class="add-new-h2">All Polls</a> <a href="%s" class="add-new-h2">Edit Poll</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ), esc_url( add_query_arg( array( 'action' => 'edit-poll', 'poll' => $poll, 'message' => false ) ) ) ); ?>
							</h2>
							<?php
							$this->poll_results_page( $poll );
							break;
						case 'edit':
						case 'edit-poll':
							?>
							<h2 id="poll-list-header">
								<?php
								printf(
									__( 'Edit Poll <a href="%s" class="add-new-h2">All Polls</a> <a href="%s" class="add-new-h2">View Results</a>', 'polldaddy' ),
									esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ),
									esc_url( add_query_arg( array( 'action' => 'results', 'poll' => $poll, 'message' => false ) ) )
								);
								?>
							</h2>
							<?php

							$this->poll_edit_form( $poll );
							break;
						case 'create-poll':
							?>
							<h2 id="poll-list-header"><?php printf( __( 'Add New Poll <a href="%s" class="add-new-h2">All Polls</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'polls', 'poll' => false, 'message' => false ) ) ) ); ?></h2>
							<?php
							$this->poll_edit_form();
							break;
						case 'list-styles':
							?>
							<h2 id="polldaddy-header">
								<?php
								if ( $this->is_author )
									printf( __( 'Custom Styles <a href="%s" class="add-new-h2">Add New</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'create-style', 'poll' => false, 'message' => false ) ) ) );
								else
									_e( 'Custom Styles', 'polldaddy' );
								?>
							</h2>
							<?php
							$this->styles_table();
							break;
						case 'edit-style':
							?>
							<h2 id="polldaddy-header">
								<?php printf( __( 'Edit Style <a href="%s" class="add-new-h2">List Styles</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?>
							</h2>
							<?php

							$this->style_edit_form( $style );
							break;
						case 'create-style':
							?>
							<h2 id="polldaddy-header">
								<?php printf( __( 'Create Style <a href="%s" class="add-new-h2">List Styles</a>', 'polldaddy' ), esc_url( add_query_arg( array( 'action' => 'list-styles', 'style' => false, 'message' => false, 'preload' => false ) ) ) ); ?>
							</h2>
							<?php
							$this->style_edit_form();
							break;
						case 'landing-page':
							$this->render_landing_page();
							break;
						default:
							$view_type = 'me'; // default (and only) config for self-hosted.

							// cascaded attempt to "show something".
							if ( ! $this->has_items_for_view( $view_type ) ) {
								$this->render_landing_page();
								break;
							}

							$this->polls_table( $view_type );
					} //end switch.
				} elseif ( 'ratings' === $page ) {
					if ( ! $this->is_admin && ! in_array( $action, array( 'delete', 'reports' ), true ) ) { // check user privileges has access to action.
						$action = 'reports';
					}

					switch ( $action ) {
						case 'delete':
						case 'reports':
							$this->rating_reports();
							break;
					}//end switch
				}
				?>
				</div>
			</div>
			<?php
	}

	private function render_landing_page() {
		$this->render_partial(
			'crowdsignal-landing-page',
			array(
				'resource_path' => $this->base_url,
			)
		);
	}


	private function has_items_for_view( $view = 'me' ) {
		if ( isset( $this->has_items[ $view ] ) ) {
			return $this->has_items[ $view ];
		}

		$guid = WP_POLLDADDY__PARTNERGUID;
		// re-write the user_code based on the intended view.
		switch ( $view ) {
			case 'csforms':
				$this->user_code = get_option( 'crowdsignal_user_code' );
				$guid            = get_option( 'crowdsignal_api_key' );
				break;
			case 'blog':
				$this->user_code = $this->get_usercode();
				break;
			default:
				$this->user_code = get_option( 'pd-usercode-' . $this->id );
		}

		if ( empty( $this->user_code ) ) {
			// use set_api_user_code last attempt.
			$this->set_api_user_code();
		}

		$polldaddy = $this->get_client( $guid, $this->user_code );
		$polldaddy->reset();

		$polls_object = $polldaddy->get_items( 1, 1, 0, 'csforms' === $view ? get_site_url() : '' );

		if ( ! $polls_object ) {
			return false;
		}
		$polls = & $polls_object->item;

		if ( isset( $polls_object->_total ) ) {
			$total_polls = $polls_object->_total;
		} else {
			$total_polls = count( $polls );
		}

		$this->has_items[ $view ] = $total_polls > 0;

		return $this->has_items[ $view ];
	}


	function polls_table( $view = 'me' ) {
		$page = 1;
		if ( isset( $_GET['paged'] ) ) { // phpcs:ignore
			$page = absint( $_GET['paged'] ); // phpcs:ignore
		}

		$guid = WP_POLLDADDY__PARTNERGUID;
		// re-write the user_code based on the intended view.
		switch ( $view ) {
			case 'csforms':
				$this->user_code = get_option( 'crowdsignal_user_code' );
				$guid            = get_option( 'crowdsignal_api_key' );
				break;
			case 'blog':
				$this->user_code = $this->get_usercode();
				break;
			default:
				$this->user_code = get_option( 'pd-usercode-' . $this->id );
		}

		if ( empty( $this->user_code ) ) {
			// use set_api_user_code last attempt.
			$this->set_api_user_code();
		}

		$polldaddy = $this->get_client( $guid, $this->user_code );
		$polldaddy->reset();

		$items = $polldaddy->get_items( $page, 20, 0, 'csforms' === $view ? get_site_url() : '' );

		$this->parse_errors( $polldaddy );
		if ( in_array( 'API Key Not Found, 890', $polldaddy->errors, true ) ) {
			return false;
		}

		$total             = $items->_total;
		$items             = $items->item;
		$connected_account = $polldaddy->get_account();

		$this->print_errors();

		$page_links = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'total'     => ceil( $total / 10 ),
				'current'   => $page,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			)
		);

		global $current_user;

		$current_user_name = ( $current_user instanceof WP_User ) && $current_user->first_name && $current_user->last_name
			? "{$current_user->first_name} {$current_user->last_name}"
			: $current_user->user_login;

		$global_user_id   = (int) get_option( 'polldaddy_usercode_user' );
		$global_user_name = '';
		if ( $global_user_id ) {
			$global_user_account = new WP_User( $global_user_id );
			$global_user_name    = $global_user_account && $global_user_account->first_name && $global_user_account->last_name
				? "{$global_user_account->first_name} {$global_user_account->last_name}"
				: ( $global_user_account ? $global_user_account->user_login : __( 'Disconnected user', 'polldaddy' ) );
		}

		// reset $this vars at this point so we show a consistent list with less "tabbed" options.
		$this->has_crowdsignal_blocks = false;
		$this->multiple_accounts      = false;

		$cs_forms_account = $this->get_crowdsignal_connected_account();

		switch ( $view ) {
			case 'csforms':
				$current_user_owns_connection = ! empty( $cs_forms_account ) && $cs_forms_account->email === $current_user->user_email;
				break;
			default: // me and blog case.
				$current_user_owns_connection = ! empty( $connected_account ) && $connected_account->email === $current_user->user_email;
		}

		$this->render_partial(
			'polls-table',
			array(
				'page_links'                   => $page_links,
				'items'                        => $items,
				'can_manage_options'           => current_user_can( 'manage_options' ),
				'connected_account_email'      => ! empty( $connected_account ) ? $connected_account->email : '',
				'is_author'                    => $this->is_author,
				'is_admin'                     => $this->is_admin,
				'current_user_name'            => $current_user_name,
				'resource_path'                => $this->base_url,
				'has_multiple_accounts'        => $this->multiple_accounts && $this->has_items_for_view( 'blog' ),
				'global_user_id'               => $global_user_id,
				'global_user_name'             => $global_user_name,
				'current_user_owns_connection' => $current_user_owns_connection,
				'user_id'                      => (int) $this->id,
				'view'                         => $view,
				'has_crowdsignal_blocks'       => $this->has_crowdsignal_blocks && $this->has_items_for_view( 'csforms' ),
				'cs_forms_account_email'       => $this->has_crowdsignal_blocks && $cs_forms_account ? $cs_forms_account->email : '',
			)
		);
	}

	private function get_crowdsignal_connected_account() {
		if ( $this->has_crowdsignal_blocks ) {
			$polldaddy = $this->get_client( get_option( 'crowdsignal_api_key' ), get_option( 'crowdsignal_user_code' ) );
			$polldaddy->reset();
			return $polldaddy->get_account();
		}

		return false;
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

		$view_vars = [
			'delete_media_link' => $delete_media_link,
			'polldaddy' => $polldaddy,
			'controller' => $this,
			'poll' => $poll,
			'poll_id' => $poll_id,
			'is_post' => $is_POST,
			'base_url' => $this->base_url,
			'preview_img_dir' => plugins_url( 'img', __FILE__ ),
			'question' => $question,
			'answers' => $answers,
			'media' => $media,
			'media_type' => $mediaType,
		];

		$this->render_partial( 'poll-edit-form', $view_vars );
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
						<?php echo number_format_i18n( $answer->_percent, 2 ); ?>%
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
								<input type="text" autocomplete="off" value="<?php echo $style_id > 1000 ? esc_html( $style->title ) : ''; ?>" tabindex="1" style="width:25em;" name="style-title" />
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
														<td><?php _e( 'Image URL', 'polldaddy' ); ?>: <a href="https://crowdsignal.com/support/custom-poll-styles/" class="noteLink" title="<?php _e( 'Click here for more information', 'polldaddy' ); ?>">(?)</a></td>
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
														<td width="85"><?php _e( 'Width', 'polldaddy' ); ?> (px):  <a href="https://crowdsignal.com/support/custom-poll-styles/" class="noteLink" title="<?php _e( 'Click here for more information', 'polldaddy' ); ?>">(?)</a></td>
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
		$rich_snippets = $show_posts = $show_posts_index = $show_pages = $show_comments = $pos_posts = $pos_posts_index = $pos_pages = $pos_comments = 0;
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

		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) )
			return false;

		if ( $this->rating_user_code == '' )
			die();
		$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
		$polldaddy->reset();

		$error = false;
		$rating_errors = array();
		if ( empty( $rating_id ) ) {
			$pd_rating = $polldaddy->create_rating( $blog_name , $new_type );
			if ( !empty( $pd_rating ) ) {
				$rating_id = (int) $pd_rating->_id;
				update_option ( 'pd-rating-' . $report_type . '-id', $rating_id );
				update_option ( 'pd-rating-' . $report_type, 0 );
			} else {
				$rating_errors[] = $polldaddy->errors;
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
					$rating_errors[] = $polldaddy->errors;

					if ( empty( $pd_rating ) || (int) $pd_rating->_id == 0 ) { //if not then create a rating for blog
						$polldaddy->reset();
						$pd_rating = $polldaddy->create_rating( $blog_name , $new_type );
						$rating_errors[] = $polldaddy->errors;
					}
				} elseif ( isset( $polldaddy->errors[ -1 ] ) && $polldaddy->errors[ -1 ] == "Can't connect" ) {
					$this->contact_support_message( __( 'Could not connect to the Crowdsignal API' ), $rating_errors );
					$error = true;
				} elseif ( isset( $polldaddy->errors[ -1 ] ) && $polldaddy->errors[ -1 ] == "Invalid API URL" ) {
					$this->contact_support_message( __( 'The API URL is incorrect' ), $rating_errors );
					$error = true;
				} elseif ( isset( $polldaddy->errors[ -2 ] ) && $polldaddy->errors[ -2 ] == "No Data" ) {
					$this->contact_support_message( __( 'Your API request did not return any data' ), $rating_errors );
					$error = true;
				}
			}

			if ( $error == false && empty( $pd_rating ) ) { //something's up!
				$this->contact_support_message( __( 'There was an error creating your rating widget' ), $rating_errors );
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
				delete_option( 'pd-rich-snippets' );
				if ( wp_next_scheduled( 'polldaddy_rating_update_job' ) ) {
					wp_clear_scheduled_hook( 'polldaddy_rating_update_job' );
				}

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
          <div class="tabs-panel" id="categories-all" style="background: #FFFFFF;height: auto; overflow: visible;max-height:500px;">
            <form action="" method="post">
            <input type="hidden" name="pd_rating_action_type" value="<?php echo $report_type; ?>" />
<?php wp_nonce_field( 'action-rating_settings_' . $report_type ); ?>
            <table class="form-table" style="width: normal;">
              <tbody>
			<?php if ( $report_type == 'posts' ) { ?>
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
		<?php // check for previous settings
		$previous_settings = get_option( 'polldaddy_settings' );
		$current_setting   = get_option( 'pd-rating-posts-id' );
		if ( $current_setting && isset( $previous_settings[ 'pd-rating-posts-id' ] ) && $current_setting != $previous_settings[ 'pd-rating-posts-id' ] ) {
			echo "<p>" . sprintf( __( "Previous settings for ratings on this site discovered. You can restore them on the <a href='%s'>poll settings page</a> if your site is missing ratings after resetting your connection settings.", 'polldaddy' ), "options-general.php?page=crowdsignal-settings" ) . "</p>";
		}
		?>
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
			    <h2 class="postbox-title"><span><?php _e( 'Save Advanced Settings', 'polldaddy' );?></span></h2>

			    <div class="inside">
			        <div class="submitbox" id="submitpost">
			            <div id="minor-publishing" style="padding:10px;">
			                <input type="submit" name="save_menu" class="button button-primary menu-save" value="<?php echo esc_attr( __( 'Save Changes', 'polldaddy' ) );?>">
			                <input type="hidden" name="type" value="<?php echo $report_type; ?>" />
							<input type="hidden" name="rating_id" value="<?php echo $rating_id; ?>" />
							<input type="hidden" name="action" value="update-rating" />
			            </div>
			        </div>
			    </div>
			</div>
            <div class="postbox">
              <h2 class="postbox-title"><?php _e( 'Preview', 'polldaddy' );?></h2>
              <div class="inside">
                <p><?php _e( 'This is a demo of what your rating widget will look like', 'polldaddy' ); ?>.</p>
                <p>
                  <div id="pd_rating_holder_1"></div>
                </p>
              </div>
            </div>
            <div class="postbox">
              <h2 class="postbox-title"><?php _e( 'Customize Labels', 'polldaddy' );?></h2>
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
              <h2 class="postbox-title"><?php _e( 'Rating Type', 'polldaddy' );?></h2>
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
            <h2 class="postbox-title"><?php _e( 'Rating Style', 'polldaddy' );?></h2>
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
            <h2 class="postbox-title"><?php _e( 'Text Layout & Font', 'polldaddy' );?></h2>
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
              <h2 class="postbox-title"><?php _e( 'Extra Settings', 'polldaddy' );?></h2>
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
		$set = new stdClass;

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
		} elseif ( $this->is_admin && $new_rating_id > 0 ) {
			$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->rating_user_code );
			$pd_rating = $polldaddy->get_rating( $new_rating_id );
			if ( false !== $pd_rating ) {
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
		if ( !defined( 'WP_POLLDADDY__PARTNERGUID' ) || WP_POLLDADDY__PARTNERGUID == false )
			return false;

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
				'current'    => $current_page,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;'
			) );
?>
		<div class="wrap">
			<?php if ( $this->is_admin ) : ?>
			<h2 id="polldaddy-header"><?php printf( __( 'Rating Results <a href="%s" class="add-new-h2">Settings</a>', 'polldaddy' ), esc_url( 'options-general.php?page=ratingsettings' ) ); ?></h2>
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
							<abbr title="<?php echo date( __( 'Y/m/d g:i:s A', 'polldaddy' ), strtotime( $rating->date ) ); ?>"><?php echo str_replace( '-', '/', substr( esc_html( $rating->date ), 0, 10 ) ); ?></abbr>
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
			$connected     = false;
			$account_email = '';
			$polldaddy     = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
			$account       = $polldaddy->get_account();

			if ( ! empty( $account ) ) {
				$connected     = true;
				$account_email = $account->email;
			}

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
				156 => __( 'Music Wide', 'polldaddy' ),
			);

			$polldaddy->reset();
			$styles = $polldaddy->get_styles();

			if ( ! empty( $styles ) && ! empty( $styles->style ) && count( $styles->style ) > 0 ) {
				foreach ( (array) $styles->style as $style ) {
					$options[ (int) $style->_id ] = $style->title;
				}
			}
		}

		$this->print_errors();

		$this->render_partial( 'html-admin-setup-header' );
		if ( ! $connected ) {
			update_option( 'crowdsignal_api_key_secret', md5( time() . wp_rand() ) );
			$this->render_partial( 'html-admin-setup-step-1' );
		} else {
			$this->render_partial(
				'settings',
				array(
					'is_connected'  => $connected,
					'api_key'       => get_option( 'polldaddy_api_key' ),
				)
			);

			if ( ! is_plugin_active( 'crowdsignal-forms/crowdsignal-forms.php' ) ) {
				$this->render_partial( 'html-admin-teaser' );
			} else {
				$this->render_partial( 'html-admin-setup-step-3' );
			}

			$this->render_partial(
				'settings-2',
				array(
					'is_connected'  => $connected,
					'api_key'       => get_option( 'polldaddy_api_key' ),
					'poll'          => $poll,
					'options'       => $options,
					'controller'    => $this,
					'account_email' => $account_email,
				)
			);
		}
		$this->render_partial( 'html-admin-setup-footer' );
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

		if ( false == (bool) current_user_can( 'edit_others_posts' ) )
			$this->log( 'can_edit: current user cannot edit_others_posts.' );

		return (bool) current_user_can( 'edit_others_posts' );
	}

	function log( $message ) {
		// error_log( print_r( $message, true ) );
	}

	function contact_support_message( $message, $errors ) {
		global $current_user;
		echo '<div class="error" id="polldaddy">';
		echo '<h1>' . $message . '</h1>';
		echo '<p>' . __( "There are a few things you can do:" );
		echo "<ul><ol>" . __( "Press reload on your browser and reload this page. There may have been a temporary problem communicating with Crowdsignal.com", "polldaddy" ) . "</ol>";
		echo "<ol>" . sprintf( __( "Go to the <a href='%s'>poll settings page</a>, scroll to the end of the page and reset your connection settings. Link your account again with the same API key.", "polldaddy" ), 'options-general.php?page=crowdsignal-settings' ) . "</ol>";
		echo "<ol>" . sprintf( __( 'Contact <a href="%1$s" %2$s>Crowdsignal support</a> and tell them your rating usercode is %3$s', 'polldaddy' ), 'https://crowdsignal.com/feedback/', 'target="_blank"', $this->rating_user_code ) . '<br />' . __( 'Also include the following information when contacting support to help us resolve your problem as quickly as possible:', 'polldaddy' ) . '';
		echo "<ul><li> API Key: " . get_option( 'polldaddy_api_key' ) . "</li>";
		echo "<li> ID Usercode: " . get_option( 'pd-usercode-' . $current_user->ID ) . "</li>";
		echo "<li> pd-rating-usercode: " . get_option( 'pd-rating-usercode' ) . "</li>";
		echo "<li> pd-rating-posts-id: " . get_option( 'pd-rating-posts-id' ) . "</li>";
		echo "<li> Errors: " . print_r( $errors, 1 ) . "</li></ul>";
		echo "</ol></ul></div>";
	}

	/**
	 * Renders a partial/template.
	 *
	 * The global $current_user is made available for any rendered template.
	 *
	 * @param string $partial  - Filename under ./partials directory, with or without .php (appended if absent).
	 * @param array  $page_vars - Variables made available for the template.
	 */
	public function render_partial( $partial, array $page_vars = array() ) {
		if ( substr( $partial, -4 ) !== '.php' ) {
			$partial .= '.php';
		}

		if ( strpos( $partial, 'partials/' ) !== 0 ) {
			$partial = 'partials/' . $partial;
		}

		$path = __DIR__ . '/' . $partial;
		if ( ! file_exists( $path ) ) {
			return;
		}

		foreach ( $page_vars as $key => $val ) {
			$$key = $val;
		}
		global $current_user;
		include $path;
	}
}

require dirname( __FILE__ ).'/rating.php';
require dirname( __FILE__ ).'/ajax.php';
require dirname( __FILE__ ).'/popups.php';
require dirname( __FILE__ ).'/polldaddy-org.php';
require dirname( __FILE__ ).'/polldaddy-shortcode.php';

$GLOBALS[ 'wp_log_plugins' ][] = 'polldaddy';

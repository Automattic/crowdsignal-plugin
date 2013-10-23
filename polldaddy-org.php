<?php

require_once dirname( __FILE__ ) . '/polldaddy-client.php';

$GLOBALS[ 'wp_log_plugins' ][] = 'polldaddy';

class WPORG_Polldaddy extends WP_Polldaddy {
	var $use_ssl;
	var $inline;

	function __construct() {
		parent::__construct();
		$this->log( 'Created WPORG_Polldaddy Object: constructor' );
		$this->version                = '2.0.19';
		$this->base_url               = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
		$this->polldaddy_client_class = 'WPORG_Polldaddy_Client';
		$this->use_ssl                = (int) get_option( 'polldaddy_use_ssl' );
		$this->multiple_accounts      = (bool) get_option( 'polldaddy_multiple_accounts' );
		$this->inline			      = (bool) get_option( 'polldaddy_load_poll_inline' );
		$this->is_author              = ( ( (bool) current_user_can('edit_others_posts')) or ( $this->multiple_accounts ) );
		return;
	}

	function log( $message ) {		
		if ( defined( 'WP_DEBUG_LOG' ) )
			$GLOBALS[ 'wp_log' ][ 'polldaddy' ][] = $message;
		parent::log( $message );
	}

	function set_api_user_code() {
		if ( empty( $this->rating_user_code ) ) {
			$this->rating_user_code = get_option( 'pd-rating-usercode' );

			if ( empty( $this->rating_user_code ) ) {
				$this->log( 'set_api_user_code: retrieve usercode from Polldaddy' );
				$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID );
				$polldaddy->reset();

				if ( $this->multiple_accounts ) {
					//need to retrieve initial admin user code to use as ratings user code
					$polldaddy->update_partner_account( array( 'role' => 0 ) );
					update_option( 'polldaddy_multiple_accounts', 0 );
				}

				$this->rating_user_code = $polldaddy->get_usercode( $this->id );
				if ( !empty( $this->rating_user_code ) )
					update_option( 'pd-rating-usercode', $this->rating_user_code );

				if ( $this->multiple_accounts ) {
					$polldaddy->update_partner_account( array( 'role' => 1 ) );
					update_option( 'polldaddy_multiple_accounts', 1 );
				}
			}
		}
		parent::set_api_user_code();
	}
	
	function admin_title( $admin_title ) {
		global $page;
		
		if ( $page == 'ratings' )
			return (stripos( $admin_title, $page ) === false ? __( "Ratings", "polldaddy" ) : '' ).$admin_title;
		elseif ( $page == 'polls' )
			return (stripos( $admin_title, $page ) === false ? __( "Polls", "polldaddy" ) : '' ).$admin_title;
		
		return $admin_title;
	}
	
	function admin_menu() {				
		parent::admin_menu();
	}

	function management_page_load() {
		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );
		wp_reset_vars( array( 'action', 'page' ) );
		global $action, $page;

		$this->set_api_user_code();

		if ( $page == 'polls' ) {
			switch ( $action ) {
			case 'update-options' :
				if ( !$is_POST )
					return;

				if ( $this->is_admin ) {
					check_admin_referer( 'polldaddy-account' );

					$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
					$polldaddy->reset();

					$polldaddy_sync_account      = 0;
					$polldaddy_multiple_accounts = 0;
					$polldaddy_load_poll_inline  = 0;

					if ( isset( $_POST['polldaddy-sync-account'] ) )
						$polldaddy_sync_account = (int) $_POST['polldaddy-sync-account'];

					if ( $polldaddy_sync_account > 0 ) {
						$this->log( 'management_page_load: sync usercode' );
						$this->rating_user_code = '';
						update_option( 'pd-rating-usercode', '' );
						$this->set_api_user_code();
					}

					if ( isset( $_POST['polldaddy-multiple-accounts'] ) )
						$polldaddy_multiple_accounts = (int) $_POST['polldaddy-multiple-accounts'];

					if ( isset( $_POST['polldaddy-load-poll-inline'] ) )
						$polldaddy_load_poll_inline = (int) $_POST['polldaddy-load-poll-inline'];

					$partner = array( 'role' => $polldaddy_multiple_accounts );
					$polldaddy->update_partner_account( $partner );
					update_option( 'polldaddy_multiple_accounts', $polldaddy_multiple_accounts );
					update_option( 'polldaddy_load_poll_inline', $polldaddy_load_poll_inline );
					
					$rating_title_filter = '';
					if ( isset( $_POST['polldaddy-ratings-title-filter'] ) )
						$rating_title_filter = $_POST['polldaddy-ratings-title-filter'];
						
					update_option( 'pd-rating-title-filter', $rating_title_filter );
				}
				break;
			} //end switch
		}
		
		global $parent_file, $submenu_file, $typenow;
		
		//need to set this to make sure that menus behave properly
		if ( in_array( $action, array( 'options', 'update-rating' ) ) ) {
			$parent_file  = 'options-general.php';
			$submenu_file = $page.'&action=options';
		} else {					
			add_filter( 'admin_title', array( &$this, 'admin_title' ) );	
			$submenu_file = $page;
		}

		parent::management_page_load();
	}

	function api_key_page_load() {
		if ( 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || empty( $_POST['action'] ) || 'account' != $_POST['action'] )
			return false;

		check_admin_referer( 'polldaddy-account' );

		$polldaddy_email    = stripslashes( $_POST['polldaddy_email'] );
		$polldaddy_password = stripslashes( $_POST['polldaddy_password'] );
		
		$this->log( 'api_key_page_load: get Polldaddy API key for account - '.$polldaddy_email );

		if ( !$polldaddy_email )
			$this->errors->add( 'polldaddy_email', __( 'Email address required', 'polldaddy' ) );

		if ( !$polldaddy_password )
			$this->errors->add( 'polldaddy_password', __( 'Password required', 'polldaddy' ) );

		if ( $this->errors->get_error_codes() )
			return false;

		if ( !empty( $_POST['polldaddy_use_ssl_checkbox'] ) ) {
			if ( $polldaddy_use_ssl = (int) $_POST['polldaddy_use_ssl'] ) {
				$this->use_ssl = 0; //checked (by default)
			} else {
				$this->use_ssl = 1; //unchecked
				$this->scheme  = 'http';
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
		}

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
			$response_code = wp_remote_retrieve_response_code( $polldaddy_api_key );
			if ( 200 != $response_code ) {
				$this->log( 'management_page_load: could not connect to Polldaddy API key service' );	
				$this->errors->add( 'http_code', __( 'Could not connect to Polldaddy API Key service', 'polldaddy' ) );
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
				$this->log( 'management_page_load: could not connect to Polldaddy API key service' );
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
			$request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
			$request .= 'Content-Length: ' . strlen( $request_body ) . "\r\n";

			fwrite( $fp, "$request\r\n$request_body" );

			$response = '';
			while ( !feof( $fp ) )
				$response .= fread( $fp, 4096 );
			fclose( $fp );
			list($headers, $polldaddy_api_key) = explode( "\r\n\r\n", $response, 2 );
		}

		if ( isset( $polldaddy_api_key ) && strlen( $polldaddy_api_key ) > 0 ) {
			update_option( 'polldaddy_api_key', $polldaddy_api_key );
		} else {
			$this->log( 'management_page_load: login to Polldaddy failed' );
			$this->errors->add( 'polldaddy_api_key', __( 'Login to Polldaddy failed.  Double check your email address and password.', 'polldaddy' ) );
			if ( 1 !== $this->use_ssl ) {
				$this->errors->add( 'polldaddy_api_key', __( 'If your email address and password are correct, your host may not support secure logins.', 'polldaddy' ) );
				$this->errors->add( 'polldaddy_api_key', __( 'In that case, you may be able to log in to Polldaddy by unchecking the "Use SSL to Log in" checkbox.', 'polldaddy' ) );
				$this->use_ssl = 0;
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
			return false;
		}

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->get_usercode( $this->id ) ) {
			$this->parse_errors( $polldaddy );
			$this->log( 'management_page_load: get usercode from Polldaddy failed' );
			$this->errors->add( 'GetUserCode', __( 'Account could not be accessed.  Are your email address and password correct?', 'polldaddy' ) );
			return false;
		}

		wp_redirect( add_query_arg( array( 'page' => 'polls' ), wp_get_referer() ) );
		return true;
	}

	function api_key_page() {
		$this->print_errors();
?>

<div class="wrap">

	<h2><?php _e( 'Polldaddy Account', 'polldaddy' ); ?></h2>

	<p><?php printf( __( 'Before you can use the Polldaddy plugin, you need to enter your <a href="%s">Polldaddy.com</a> account details.', 'polldaddy' ), 'http://polldaddy.com/' ); ?></p>

	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-email"><?php _e( 'Polldaddy Email Address', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" value="<?php if ( isset( $_POST['polldaddy_email'] ) ) echo esc_attr( $_POST['polldaddy_email'] ); ?>" />
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
				<?php
		$checked = '';
		if ( $this->use_ssl == 0 )
			$checked = 'checked="checked"';
?>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-use-ssl"><?php _e( 'Use SSL to Log in', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="polldaddy_use_ssl" id="polldaddy-use-ssl" value="1" <?php echo $checked ?> style="width: auto"/>
						<label for="polldaddy-use-ssl"><?php _e( 'This ensures a secure login to your Polldaddy account.  Only uncheck if you are having problems logging in.', 'polldaddy' ); ?></label>
						<input type="hidden" name="polldaddy_use_ssl_checkbox" value="1" />
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

	function plugin_options_add() {
		if ( $this->is_admin ) {
			$inline = '';
			if ( $this->inline )
				$inline = 'checked="checked"';
				
			$checked = '';
			if ( $this->multiple_accounts )
				$checked = 'checked="checked"';
				
			$rating_title_filter = get_option( 'pd-rating-title-filter' );
			
			if ( $rating_title_filter === false )
				$rating_title_filter = 'wp_title'; 
				
			?><tr class="form-field form-required">
    <th valign="top" scope="row">
      <label for="polldaddy-load-poll-inline">
        <?php _e( 'Load Shortcodes Inline', 'polldaddy' ); ?>
      </label>
    </th>
    <td>
      <input type="checkbox" name="polldaddy-load-poll-inline" id="polldaddy-load-poll-inline" value="1" <?php echo $inline ?> style="width: auto" />
        <span class="description">
          <label for="polldaddy-load-poll-inline"><?php _e( 'This will load the Polldaddy shortcodes inline rather than in the page footer.', 'polldaddy' ); ?></label>
        </span>
    </td>
  </tr><tr class="form-field form-required">
    <th valign="top" scope="row">
      <label for="polldaddy-multiple-accounts">
        <?php _e( 'Multiple Polldaddy Accounts', 'polldaddy' ); ?>
      </label>
    </th>
    <td>
      <input type="checkbox" name="polldaddy-multiple-accounts" id="polldaddy-multiple-accounts" value="1" <?php echo $checked ?> style="width: auto" />
        <span class="description">
          <label for="polldaddy-multiple-accounts"><?php _e( 'This setting will allow each blog user to import a Polldaddy account.', 'polldaddy' ); ?></label>
        </span>
    </td>
  </tr>
  <tr class="form-field form-required">
    <th valign="top" scope="row">
      <label for="polldaddy-sync-account">
        <?php _e( 'Sync Ratings Account', 'polldaddy' ); ?>
      </label>
    </th>
    <td>
      <input type="checkbox" name="polldaddy-sync-account" id="polldaddy-sync-account" value="1" style="width: auto" />
        <span class="description">
          <label for="polldaddy-sync-account"><?php _e( 'This will synchronize your ratings Polldaddy account.', 'polldaddy' ); ?></label>
        </span>
    </td>
  </tr>
  <tr class="form-field form-required">
    <th valign="top" scope="row">
      <label for="polldaddy-ratings-title-filter">
        <?php _e( 'Ratings Title Filter', 'polldaddy' ); ?>
      </label>
    </th>
    <td>
      <input type="text" name="polldaddy-ratings-title-filter" id="polldaddy-ratings-title-filter" value="<?php echo $rating_title_filter; ?>" style="width: auto" />
        <span class="description">
          <label for="polldaddy-ratings-title-filter"><?php _e( 'This setting allows you to specify a filter to use with your ratings title.', 'polldaddy' ); ?></label>
        </span>
    </td>
  </tr><?php }
		return parent::plugin_options_add();
	}
}

class WPORG_Polldaddy_Client extends api_client {
	/**
	 *
	 *
	 * @return string|false Polldaddy partner account or false on failure
	 */
	function get_partner_account() {
		$pos = $this->add_request( 'getpartneraccount' );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->partner ) && !is_null( $r->partner->_role ) )
			return $r->partner;
		return false;
	}

	/**
	 *
	 *
	 * @see polldaddy_partner()
	 * @param array   $args polldaddy_partner() args
	 * @return string|false Polldaddy partner account or false on failure
	 */
	function update_partner_account( $args ) {
		if ( !$partner = polldaddy_partner( $args ) )
			return false;

		$pos = $this->add_request( 'updatepartneraccount', $partner );
		$this->send_request();
		$r = $this->response_part( $pos );
		if ( isset( $r->partner ) && !is_null( $r->partner->_role ) )
			return $r->partner;
		return false;
	}
}

function &polldaddy_partner( $args = null ) {
	$false = false;
	if ( is_a( $args, 'Polldaddy_Partner' ) )
		return $args;

	$defaults = _polldaddy_partner_defaults();

	$args = wp_parse_args( $args, $defaults );

	foreach ( array( 'name' ) as $required )
		if ( !is_string( $args[$required] ) || !$args[$required] )
			return $false;

		$obj = new Polldaddy_Partner( $args, $args );

	return $obj;
}

function _polldaddy_partner_defaults() {
	return array(
		'name' => get_bloginfo( 'name' ),
		'role' => 0
	);
}

if ( !function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags($string, $remove_breaks = false) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags($string);
	
		if ( $remove_breaks )
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);
	
		return trim($string);
	}
}

define( 'WP_POLLDADDY__CLASS', 'WPORG_Polldaddy' );
define( 'WP_POLLDADDY__POLLDADDY_CLIENT_PATH', dirname( __FILE__ ) . '/polldaddy-client.php' );

function polldaddy_loader() {
	global $polldaddy_object;
	$polldaddy_class  = WP_POLLDADDY__CLASS;
	$polldaddy_object = new $polldaddy_class;
	load_plugin_textdomain( 'polldaddy', '', 'polldaddy/locale' );
	add_action( 'admin_menu', array( &$polldaddy_object, 'admin_menu' ) );
}


if ( !function_exists( 'polldaddy_shortcode_handler' ) ) {
function polldaddy_shortcode_handler() {}
}

if ( !class_exists( 'PolldaddyShortcode' ) ) {
	/**
* Class wrapper for polldaddy shortcodes
*/
class PolldaddyShortcode {

	static $add_script = false;
	static $scripts = false;
	
	/**
	 * Add all the actions & resgister the shortcode
	 */
	function __construct() {
		if ( defined( 'GLOBAL_TAGS' ) == false )
			add_shortcode( 'polldaddy', array( $this, 'polldaddy_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'check_infinite' ) );
		add_action( 'infinite_scroll_render', array( $this, 'polldaddy_shortcode_infinite' ), 11 );
	}

	/**
	 * Shortcode for polldadddy
	 * [polldaddy poll|survey|rating="123456"]
	 *
	 * */
	function polldaddy_shortcode( $atts ) {
		global $post;
		global $content_width;
	
		extract( shortcode_atts( array(
			'survey'     => null,
			'link_text'  => 'Take Our Survey',
			'poll'       => 'empty',
			'rating'     => 'empty',
			'unique_id'  => null,
			'item_id'    => null,
			'title'      => null,
			'permalink'  => null,
			'cb'         => 0,
			'type'       => 'button',
			'body'       => '',
			'button'     => '',
			'text_color' => '000000',
			'back_color' => 'FFFFFF',
			'align'      => '',
			'style'      => '',
			'width'      => $content_width,
			'height'     => floor( $content_width * 3 / 4 ),
			'delay'      => 100,
			'visit'      => 'single',
			'domain'     => '',
			'id'         => ''
		), $atts ) );
		
		if ( ! is_array( $atts ) ) {
			return '<!-- Polldaddy shortcode passed invalid attributes -->';
		}
		
		$inline          = !in_the_loop();
		$no_script       = false;
		$infinite_scroll = false;
		
		if ( is_home() && current_theme_supports( 'infinite-scroll' ) )
			$infinite_scroll = true;
	
		if ( defined( 'PADPRESS_LOADED' ) )
			$inline = true;
	
		if ( function_exists( 'get_option' ) && get_option( 'polldaddy_load_poll_inline' ) )
			$inline = true;
	
		if ( is_feed() || ( defined( 'DOING_AJAX' ) && !$infinite_scroll ) )
			$no_script = false;
		
		self::$add_script = $infinite_scroll;
		
		if ( intval( $rating ) > 0 && !$no_script ) { //rating embed		
		
			if ( empty( $unique_id ) )
				$unique_id = is_page() ? 'wp-page-'.$post->ID : 'wp-post-'.$post->ID;
			
			if ( empty( $item_id ) )
				$item_id = is_page() ? '_page_'.$post->ID : '_post_'.$post->ID;
	
			if ( empty( $title ) )
				$title = apply_filters( 'the_title', $post->post_title );
	
			if ( empty( $permalink ) )
				$permalink = get_permalink( $post->ID );
				
			$rating    = intval( $rating );
			$unique_id = wp_strip_all_tags( $unique_id );
			$item_id   = wp_strip_all_tags( $item_id );
			$item_id   = preg_replace( '/[^_a-z0-9]/i', '', $item_id );
			
			$settings = json_encode( array(
				'id'        => $rating,
				'unique_id' => $unique_id,
				'title'     => rawurlencode( trim( $title ) ),
				'permalink' => esc_url( $permalink ),
				'item_id'   => $item_id
			) );
			
			$item_id = esc_js( $item_id );
			
			if ( $inline ) {		
				return <<<SCRIPT
<div class="pd-rating" id="pd_rating_holder_{$rating}{$item_id}"></div>
<script type="text/javascript" charset="UTF-8"><!--//--><![CDATA[//><!--
PDRTJS_settings_{$rating}{$item_id}={$settings};
//--><!]]></script>
<script type="text/javascript" charset="UTF-8" src="http://i0.poll.fm/js/rating/rating.js"></script>
SCRIPT;
			} else {				
				if ( self::$scripts === false )
					self::$scripts = array();
					
				$data = array( 'id' => $rating, 'item_id' => $item_id, 'settings' => $settings );
					
				self::$scripts['rating'][] = $data;
				
				add_action( 'wp_footer', array( $this, 'generate_scripts' ) );
				
				$data = esc_attr( json_encode( $data ) );
				
				if ( $infinite_scroll )
					return <<<CONTAINER
<div class="pd-rating" id="pd_rating_holder_{$rating}{$item_id}" data-settings="{$data}"></div>
CONTAINER;
				else
					return <<<CONTAINER
<div class="pd-rating" id="pd_rating_holder_{$rating}{$item_id}"></div>
CONTAINER;
			}
		} elseif ( intval( $poll ) > 0 ) { //poll embed
		
			$poll      = intval( $poll );
			$poll_url  = sprintf( 'http://polldaddy.com/poll/%d', $poll );
			$poll_js   = sprintf( '%s.polldaddy.com/p/%d.js', ( is_ssl() ? 'https://secure' : 'http://static' ), $poll );
			$poll_link = sprintf( '<a href="%s">Take Our Poll</a>', $poll_url );
	
			if ( $no_script ) {
				return $poll_link;
			} else {
				if ( $type == 'slider' && !$inline ) {
				
					if( !in_array( $visit, array( 'single', 'multiple' ) ) )
						$visit = 'single';
						
					$settings = json_encode( array(
						'type'  => 'slider',
						'embed' => 'poll',
						'delay' => intval( $delay ),
						'visit' => $visit,
						'id'    => intval( $poll )
					) );
					
					return <<<SCRIPT
<script type="text/javascript" charset="UTF-8" src="http://i0.poll.fm/survey.js"></script>
<script type="text/javascript" charset="UTF-8"><!--//--><![CDATA[//><!--
polldaddy.add( {$settings} );
//--><!]]></script>
<noscript>{$poll_link}</noscript>
SCRIPT;
				} else {
					$cb      = ( $cb == 1 ? '?cb='.mktime() : false );
					$margins = '';
					$float   = '';
					
					if ( in_array( $align, array( 'right', 'left' ) ) ) {
						$float = sprintf( 'float: %s;', $align );					
						
						if ( $align == 'left')
							$margins = 'margin: 0px 10px 0px 0px;';
						elseif ( $align == 'right' )
							$margins = 'margin: 0px 0px 0px 10px';
					}									
			
					if ( $cb === false && !$inline ) {
						if ( self::$scripts === false )
							self::$scripts = array();
							
						$data = array( 'url' => $poll_js );
							
						self::$scripts['poll'][] = $data;
						
						add_action( 'wp_footer', array( $this, 'generate_scripts' ) );
						
						$data = esc_attr( json_encode( $data ) );
						
						return <<<CONTAINER
<a name="pd_a_{$poll}"></a>
<div class="PDS_Poll" id="PDI_container{$poll}" data-settings="{$data}" style="display:inline-block;{$float}{$margins}"></div>
<div id="PD_superContainer"></div>
<noscript>{$poll_link}</noscript>
CONTAINER;
					} else {
						if ( $inline )
							$cb = '';
							
						return <<<CONTAINER
<a name="pd_a_{$poll}"></a>
<div class="PDS_Poll" id="PDI_container{$poll}" style="display:inline-block;{$float}{$margins}"></div>
<div id="PD_superContainer"></div>
<script type="text/javascript" charset="UTF-8" src="{$poll_js}{$cb}"></script>
<noscript>{$poll_link}</noscript>
CONTAINER;
					}				
				}		
			}
		} elseif ( !empty( $survey ) ) { //survey embed
	
			if ( in_array( $type, array( 'iframe', 'button', 'banner', 'slider' ) ) ) {
				
				if ( empty( $title ) ) {
					$title = 'Take Our Survey';
					if( !empty( $link_text ) )
						$title = $link_text;
				}
				
				$survey      = preg_replace( '/[^a-f0-9]/i', '', $survey );
				$survey_url  = esc_url( "http://polldaddy.com/s/{$survey}" );			
				$survey_link = sprintf( '<a href="%s">%s</a>', $survey_url, esc_html( $title ) );	
				
				if ( $no_script || $inline || $infinite_scroll )
					return $survey_link;			
							
				if ( $type == 'iframe' ) {	
					if ( $height != 'auto' ) {
						if ( isset( $content_width ) && is_numeric( $width ) && $width > $content_width ) 
							$width = $content_width;
					
						if ( !$width )
							$width = '100%';
						else
							$width = (int) $width;
					
						if ( !$height )
							$height = '600';
						else
							$height = (int) $height;		
										
						return <<<CONTAINER
<iframe src="{$survey_url}?iframe=1" frameborder="0" width="{$width}" height="{$height}" scrolling="auto" allowtransparency="true" marginheight="0" marginwidth="0">{$survey_link}</iframe> 
CONTAINER;
					} elseif ( !empty( $domain ) && !empty( $id ) ) {
					
						$auto_src = esc_url( "http://{$domain}.polldaddy.com/s/{$id}" );					
						$auto_src = parse_url( $auto_src );
						
						if ( !is_array( $auto_src ) || count( $auto_src ) == 0 )
							return '<!-- no polldaddy output -->';
							
						if ( !isset( $auto_src['host'] ) || !isset( $auto_src['path'] ) )
							return '<!-- no polldaddy output -->';
						
						$domain   = $auto_src['host'].'/s/';
						$id       = str_ireplace( '/s/', '', $auto_src['path'] );
						
						$settings = json_encode( array(
							'type'       => $type,
							'auto'       => true,
							'domain'     => $domain,
							'id'         => $id
						) );
					}
				} else {				
					$text_color = preg_replace( '/[^a-f0-9]/i', '', $text_color );
					$back_color = preg_replace( '/[^a-f0-9]/i', '', $back_color );
					
					if ( !in_array( $align, array( 'right', 'left', 'top-left', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-right' ) ) )
						$align = '';
						
					if ( !in_array( $style, array( 'inline', 'side', 'corner', 'rounded', 'square' ) ) )
						$style = '';
				
					$title  = wp_strip_all_tags( $title );
					$body   = wp_strip_all_tags( $body );
					$button = wp_strip_all_tags( $button );
					
					$settings = json_encode( array_filter( array(
						'title'      => $title,
						'type'       => $type,
						'body'       => $body,
						'button'     => $button,
						'text_color' => $text_color,
						'back_color' => $back_color,
						'align'      => $align,
						'style'      => $style,
						'id'         => $survey
					) ) );	
				}
				return <<<CONTAINER
<script type="text/javascript" charset="UTF-8" src="http://i0.poll.fm/survey.js"></script>
<script type="text/javascript" charset="UTF-8"><!--//--><![CDATA[//><!--
polldaddy.add( {$settings} );
//--><!]]></script>
<noscript>{$survey_link}</noscript>
CONTAINER;
			} 
		} else {
			return '<!-- no polldaddy output -->';
		}
	}
	
	function generate_scripts() {
		$script = '';
		
		if ( is_array( self::$scripts ) ) {
			if ( isset( self::$scripts['rating'] ) ) {
				$script = "<script type='text/javascript' charset='UTF-8' id='polldaddyRatings'><!--//--><![CDATA[//><!--\n";
				foreach( self::$scripts['rating'] as $rating ) {
					$script .= "PDRTJS_settings_{$rating['id']}{$rating['item_id']}={$rating['settings']}; if ( typeof PDRTJS_RATING !== 'undefined' ){if ( typeof PDRTJS_{$rating['id']}{$rating['item_id']} == 'undefined' ){PDRTJS_{$rating['id']}{$rating['item_id']} = new PDRTJS_RATING( PDRTJS_settings_{$rating['id']}{$rating['item_id']} );}}";
				}
				$script .= "\n//--><!]]></script><script type='text/javascript' charset='UTF-8' src='http://i0.poll.fm/js/rating/rating.js'></script>";
			
			}
			
			if ( isset( self::$scripts['poll'] ) ) {
				foreach( self::$scripts['poll'] as $poll ) {
					$script .= "<script type='text/javascript' charset='UTF-8' src='{$poll['url']}'></script>";
				}
			}
		}
			
		self::$scripts = false;
		echo $script;
	}

	/**
	 * If the theme uses infinite scroll, include jquery at the start
	 */
	function check_infinite() {
		if ( current_theme_supports( 'infinite-scroll' ) && class_exists( 'The_Neverending_Home_Page' ) && The_Neverending_Home_Page::archive_supports_infinity() )
			wp_enqueue_script( 'jquery' );
	}

	/**
	 * Dynamically load the .js, if needed
	 *
	 * This hooks in late (priority 11) to infinite_scroll_render to determine
	 * a posteriori if a shortcode has been called.
	 */
	function polldaddy_shortcode_infinite() {
		// only try to load if a shortcode has been called and theme supports infinite scroll
		if( self::$add_script ) {
			$script_url = json_encode( esc_url_raw( plugins_url( 'js/polldaddy-shortcode.js', __FILE__ ) ) );

			// if the script hasn't been loaded, load it
			// if the script loads successfully, fire an 'pd-script-load' event
			echo <<<SCRIPT
				<script type='text/javascript'>
				//<![CDATA[
				if ( typeof window.polldaddyshortcode === 'undefined' ) {
					var wp_pd_js = document.createElement( 'script' );
					wp_pd_js.type = 'text/javascript';
					wp_pd_js.src = $script_url;
					wp_pd_js.async = true;
					wp_pd_js.onload = function() { 
						jQuery( document.body ).trigger( 'pd-script-load' ); 
					};
					document.getElementsByTagName( 'head' )[0].appendChild( wp_pd_js );
				} else {
					jQuery( document.body ).trigger( 'pd-script-load' );
				}
				//]]>
				</script>
SCRIPT;
	
		}
	}
}

// kick it all off
new PolldaddyShortcode();

if ( !function_exists( 'polldaddy_link' ) ) {
	// http://polldaddy.com/poll/1562975/?view=results&msg=voted
	function polldaddy_link( $content ) {
		return preg_replace( '!(?:\n|\A)http://polldaddy.com/poll/([0-9]+?)/(.+)?(?:\n|\Z)!i', "\n<script type='text/javascript' language='javascript' charset='utf-8' src='http://static.polldaddy.com/p/$1.js'></script><noscript> <a href='http://polldaddy.com/poll/$1/'>View Poll</a></noscript>\n", $content );
	}
	
	// higher priority because we need it before auto-link and autop get to it
	add_filter( 'the_content', 'polldaddy_link', 1 );
	add_filter( 'the_content_rss', 'polldaddy_link', 1 );
	add_filter( 'comment_text', 'polldaddy_link', 1 );
}

}

add_action( 'init', 'polldaddy_loader' );
add_filter( 'widget_text', 'do_shortcode' );

/**
 * Polldaddy Top Rated Widget
 *
 * **/
if ( class_exists( 'WP_Widget' ) ) {
	class PD_Top_Rated extends WP_Widget {

		function PD_Top_Rated() {

			$widget_ops = array( 'classname' => 'top_rated', 'description' => __( 'A list of your top rated posts, pages or comments.', 'polldaddy' ) );
			$this->WP_Widget( 'PD_Top_Rated', 'Top Rated', $widget_ops );
		}

		function widget($args, $instance) {

			extract($args, EXTR_SKIP);

			$title              = empty( $instance['title'] ) ? __( 'Top Rated', 'polldaddy' ) : apply_filters( 'widget_title', $instance['title'] );
			$posts_rating_id    = (int) get_option( 'pd-rating-posts-id' );
			$pages_rating_id    = (int) get_option( 'pd-rating-pages-id' );
			$comments_rating_id = (int) get_option( 'pd-rating-comments-id' );
			$rating_seq         = $instance['show_posts'] . $instance['show_pages'] . $instance['show_comments'];
			
			$filter = '';
			if ( $instance['show_posts'] == 1 && $instance['filter_by_category'] == 1 ) {
				if ( is_single() ) { //get all posts in current category					
					global $post;
					if( !empty( $post ) )
						$current_category = get_the_category( $post->ID );
				}
				
				if ( is_category() ) { //get all posts in category archive page					
					global $posts;
					if( !empty( $posts ) )
						$current_category = get_the_category( $posts[0]->ID );
				}					
				
				if ( is_array( $current_category ) && (int) $current_category[0]->cat_ID > 0 ) {
					$args = array( 'category' => $current_category[0]->cat_ID );
					$post_ids = '';
					foreach( get_posts( $args ) as $p )
						$post_ids .= $p->ID . ',';
					$post_ids = substr( $post_ids, 0, -1 ); 
				}

				if ( !empty( $post_ids ) ) //set variable
					$filter = 'PDRTJS_TOP.filters = [' . $post_ids . '];';
			}
			
			$show = "PDRTJS_TOP.get_top( 'posts', '0' );";
			if ( $instance['show_pages'] == 1 )
				$show = "PDRTJS_TOP.get_top( 'pages', '0' );";
			elseif ( $instance['show_comments'] == 1 )
				$show = "PDRTJS_TOP.get_top( 'comments', '0' );";

        	echo '</script>';
			
			$widget = <<<EOD
{$before_title}{$title}{$after_title}
<div id="pd_top_rated_holder"></div>
<script language="javascript" charset="UTF-8" src="http://i0.poll.fm/js/rating/rating-top.js"></script>
<script type="text/javascript" charset="UTF-8"><!--//--><![CDATA[//><!--
PDRTJS_TOP = new PDRTJS_RATING_TOP( {$posts_rating_id}, {$pages_rating_id}, {$comments_rating_id}, '{$rating_seq}', {$instance['item_count']} );{$filter}{$show}
//--><!]]></script>
EOD;
			echo $before_widget;
			echo $widget;
			echo $after_widget;
		}

		function update( $new_instance, $old_instance ) {

			$instance                  = $old_instance;
			$instance['title']         = strip_tags($new_instance['title']);
			$instance['show_posts']    = (int) $new_instance['show_posts'];
			$instance['show_pages']    = (int) $new_instance['show_pages'];
			$instance['show_comments'] = (int) $new_instance['show_comments'];
	        $instance['filter_by_category'] = (int) $new_instance['filter_by_category'];
			$instance['item_count']    = (int) $new_instance['item_count'];
			return $instance;
		}

		function form( $instance ) {

			$instance      = wp_parse_args( (array) $instance, array( 'title' => '', 'show_posts' => '1', 'show_pages' => '1', 'show_comments' => '1', 'item_count' => '5', 'filter_by_category' => '', ) );
			$title         = strip_tags( $instance['title'] );
			$show_posts    = (int) $instance['show_posts'];
			$show_pages    = (int) $instance['show_pages'];
			$show_comments = (int) $instance['show_comments'];
	        $filter_by_category = (int) $instance['filter_by_category'];
			$item_count    = (int) $instance['item_count'];
?>
				<p>
					<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title', 'polldaddy' ); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
				<p>
					<label for="<?php echo $this->get_field_id( 'show_posts' ); ?>">
						<input type="checkbox" class="checkbox"  id="<?php echo $this->get_field_id( 'show_posts' ); ?>" name="<?php echo $this->get_field_name( 'show_posts' ); ?>" value="1" <?php echo $show_posts == 1 ? 'checked="checked"' : ''; ?> />
						 <?php _e( 'Show for posts', 'polldaddy' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id( 'show_pages' ); ?>">
						<input type="checkbox" class="checkbox"  id="<?php echo $this->get_field_id( 'show_pages' ); ?>" name="<?php echo $this->get_field_name( 'show_pages' ); ?>" value="1" <?php echo $show_pages == 1 ? 'checked="checked"' : ''; ?> />
						 <?php _e( 'Show for pages', 'polldaddy' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id( 'show_comments' ); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'show_comments' ); ?>" name="<?php echo $this->get_field_name( 'show_comments' ); ?>" value="1" <?php echo $show_comments == 1 ? 'checked="checked"' : ''; ?> />
						 <?php _e( 'Show for comments', 'polldaddy' ); ?>
					</label>
				</p>
        		<p>
            		<label for="<?php echo $this->get_field_id( 'filter_by_category' ); ?>">
                    	<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'filter_by_category' ); ?>" name="<?php echo $this->get_field_name( 'filter_by_category' ); ?>" value="1" <?php echo $filter_by_category == 1 ? 'checked="checked"':''; ?>/>
                 		<?php _e('Filter by category'); ?>
        			</label>
    			</p>
				<p>
					<label for="rss-items-<?php echo $number; ?>"><?php _e( 'How many items would you like to display?', 'polldaddy' ); ?>
							<select id="<?php echo $this->get_field_id( 'item_count' ); ?>" name="<?php echo $this->get_field_name( 'item_count' ); ?>">
						<?php
	for ( $i = 1; $i <= 20; ++$i )
		echo "<option value='$i' " . ( $item_count == $i ? "selected='selected'" : '' ) . ">$i</option>";
?>
					</select>
				</label>
			</p>
	<?php
		}
	}
	add_action('widgets_init', create_function('', 'return register_widget("PD_Top_Rated");'));
}

function polldaddy_login_warning() {
	global $cache_enabled;
	$page = isset( $_GET[ 'page' ] ) ? $_GET[ 'page' ] : '';
	if ( $page != 'polls' && false == get_option( 'polldaddy_api_key' ) && function_exists( "admin_url" ) )
		echo '<div class="updated"><p><strong>' . sprintf( __( 'Warning! The Polldaddy plugin must be linked to your Polldaddy.com account. Please visit the <a href="%s">plugin settings page</a> to login.', 'polldaddy' ), admin_url( 'options-general.php?page=polls&action=options' ) ) . '</strong></p></div>';
}
add_action( 'admin_notices', 'polldaddy_login_warning' );

/**
 * check if the hook is scheduled - if not, schedule it.
 */
function polldaddy_setup_schedule() {
	if ( false == wp_next_scheduled( 'polldaddy_rating_update_job' ) ) {
		wp_schedule_event( time(), 'daily', 'polldaddy_rating_update_job');
	}
}
add_action( 'init', 'polldaddy_setup_schedule' );

/**
 * On deactivation, remove all functions from the scheduled action hook.
 */
function polldaddy_deactivation() {
	wp_clear_scheduled_hook( 'polldaddy_rating_update_job' );
}
register_deactivation_hook( __FILE__, 'polldaddy_deactivation' );

/**
 * On the scheduled action hook, run a function.
 */
function polldaddy_rating_update() {
	global $polldaddy_object;
	$polldaddy = $polldaddy_object->get_client( WP_POLLDADDY__PARTNERGUID, get_option( 'pd-rating-usercode' ) );
	$response = $polldaddy->get_rating_results( $rating[ 'id' ], 2, 0, 15 );
	$ratings = $response->ratings;
	if ( empty( $ratings ) )
		return false;

	polldaddy_update_ratings_cache( $ratings );
}

add_action( 'polldaddy_rating_update_job', 'polldaddy_rating_update' );

function polldaddy_update_ratings_cache( $ratings ) {
	foreach( $ratings as $rating ) {
		$post_id = str_replace( 'wp-post-', '', $rating->uid );
		update_post_meta( $post_id, 'pd_rating', array( 'type' => $rating->_type, 'votes' => $rating->_votes, 
			'total1' => $rating->total1,
			'total2' => $rating->total2,
			'total3' => $rating->total3,
			'total4' => $rating->total4,
			'total5' => $rating->total5, 
			'average' => $rating->average_rating ) );
	}
}

function polldaddy_post_rating( $content ) {
	if ( false == is_singular() )
		return $content;
	if ( false == get_option( 'pd-rating-usercode' ) )
		return $content;
	$rating = get_post_meta( $GLOBALS[ 'post' ]->ID, 'pd_rating' );
	if ( false == $rating )
		return $content;
	// convert to 5 star rating
	if ( $rating[0][ 'type' ] == 1 )
		$average = ceil( ( $rating[0][ 'average' ] / $rating[0][ 'votes' ] ) * 5 );
	else
		$average = $rating[ 'average' ];
	return $content . '
		<div itemtype="http://schema.org/AggregateRating" itemscope itemprop="aggregateRating">
		<meta itemprop="ratingValue" content=' . $average . '>
		<meta itemprop="ratingCount" content=' . $rating[0][ 'votes' ] . '>
		</div>';
}
add_filter( 'the_content', 'polldaddy_post_rating' );
?>

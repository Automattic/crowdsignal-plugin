<?php

if ( function_exists( 'get_option' ) == false )
	die( "Cheatin' eh?" );

require_once dirname( __FILE__ ) . '/polldaddy-client.php';

$GLOBALS[ 'wp_log_plugins' ][] = 'polldaddy';

class WPORG_Polldaddy extends WP_Polldaddy {
	var $use_ssl;
	var $inline;

	function __construct() {
		parent::__construct();
		$this->log( 'Created WPORG_Polldaddy Object: constructor' );
		$this->version                = '2.0.22';
		$this->base_url               = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
		$this->polldaddy_client_class = 'WPORG_Polldaddy_Client';
		$this->use_ssl                = (int) get_option( 'polldaddy_use_ssl' );
		$this->multiple_accounts      = (bool) get_option( 'polldaddy_multiple_accounts' );
		$this->inline                 = (bool) get_option( 'polldaddy_load_poll_inline' );
		$this->is_author              = ( ( (bool) current_user_can( 'edit_others_posts' ) ) || ( $this->multiple_accounts ) );
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
				$this->log( 'set_api_user_code: retrieve usercode from Crowdsignal' );
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
			return (stripos( $admin_title, $page ) === false ? __( "Dashboard", "polldaddy" ) : '' ) . ' | ' . $admin_title;

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

		if ( 'polls' === $page || 'crowdsignal-settings' === $page ) {
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
				}
				break;
			} //end switch
		}

		global $parent_file, $submenu_file, $typenow;

		//need to set this to make sure that menus behave properly
		if ( in_array( $action, array( 'options', 'update-rating' ) ) ) {
			$parent_file  = 'admin.php';
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

		$this->log( 'api_key_page_load: get Crowdsignal API key for account - '.$polldaddy_email );

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
			$polldaddy_api_key = wp_remote_post( polldaddy_api_url( '/key' ), array(
					'body' => $details
				) );
			if ( is_wp_error( $polldaddy_api_key ) ) {
				$this->errors = $polldaddy_api_key;
				return false;
			}
			$response_code = wp_remote_retrieve_response_code( $polldaddy_api_key );
			if ( 200 != $response_code ) {
				$this->log( 'management_page_load: could not connect to Crowdsignal API key service' );
				$this->errors->add( 'http_code', __( 'Could not connect to Crowdsignal API Key service', 'polldaddy' ) );
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
				$this->log( 'management_page_load: could not connect to Crowdsignal API key service' );
				$this->errors->add( 'connect', __( "Can't connect to Crowdsignal.com", 'polldaddy' ) );
				return false;
			}

			if ( function_exists( 'stream_set_timeout' ) )
				stream_set_timeout( $fp, 3 );

			global $wp_version;

			$request_body = http_build_query( $details, null, '&' );

			$request  = 'POST ' . polldaddy_api_path( '/key' ) . " HTTP/1.0\r\n";
			$request .= 'Host: ' . POLLDADDY_API_HOST . "\r\n";
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
			$this->log( 'management_page_load: login to Crowdsignal failed' );
			$this->errors->add( 'polldaddy_api_key', __( 'Login to Crowdsignal failed.  Double check your email address and password.', 'polldaddy' ) );
			if ( 1 !== $this->use_ssl ) {
				$this->errors->add( 'polldaddy_api_key', __( 'If your email address and password are correct, your host may not support secure logins.', 'polldaddy' ) );
				$this->errors->add( 'polldaddy_api_key', __( 'In that case, you may be able to log in to Crowdsignal by unchecking the "Use SSL to Log in" checkbox.', 'polldaddy' ) );
				$this->use_ssl = 0;
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
			return false;
		}

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->get_usercode( $this->id ) ) {
			$this->parse_errors( $polldaddy );
			$this->log( 'management_page_load: get usercode from Crowdsignal failed' );
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

	<h2><?php _e( 'Crowdsignal Account', 'polldaddy' ); ?></h2>

	<p><?php printf( __( 'Before you can use the Crowdsignal plugin, you need to enter your <a href="%s">Crowdsignal.com</a> account details.', 'polldaddy' ), 'https://app.crowdsignal.com/' ); ?></p>

	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-email"><?php _e( 'Crowdsignal Email Address', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" value="<?php if ( isset( $_POST['polldaddy_email'] ) ) echo esc_attr( $_POST['polldaddy_email'] ); ?>" />
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
						<label for="polldaddy-use-ssl"><?php _e( 'This ensures a secure login to your Crowdsignal account.  Only uncheck if you are having problems logging in.', 'polldaddy' ); ?></label>
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
			if ( $this->inline ) {
				$inline = 'checked="checked"';
			}

			$checked = '';
			if ( $this->multiple_accounts ) {
				$checked = 'checked="checked"';
			}

			$rating_title_filter = get_option( 'pd-rating-title-filter' );

			if ( $rating_title_filter === false ) {
				$rating_title_filter = 'wp_title';
			}

			?>
			<tr class="form-field form-required">
				<th valign="top" scope="row">
					<label for="polldaddy-load-poll-inline"><?php esc_html_e( 'Load Shortcodes Inline', 'polldaddy' ); ?></label>
				</th>
				<td>
					<input type="checkbox" name="polldaddy-load-poll-inline" id="polldaddy-load-poll-inline" value="1" <?php echo $inline ?> style="width: auto" />
					<span class="description">
						<label for="polldaddy-load-poll-inline"><?php esc_html_e( 'This will load the Crowdsignal shortcodes inline rather than in the page footer.', 'polldaddy' ); ?></label>
					</span>
				</td>
			</tr>
			<?php
			if ( $this->multiple_accounts ) {
				?>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-multiple-accounts"><?php esc_html_e( 'Multiple Crowdsignal Accounts', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="polldaddy-multiple-accounts" id="polldaddy-multiple-accounts" value="1" <?php echo $checked ?> style="width: auto" />
						<span class="description">
							<label for="polldaddy-multiple-accounts">
								<?php esc_html_e( 'This setting will allow each blog user to import a Crowdsignal account.', 'polldaddy' ); ?>
								<br />
								<strong>
									<?php esc_html_e( 'Warning', 'polldaddy' ); ?>
									<?php esc_html_e( 'This is a deprecated feature and is not supported anymore. If you disable this Multi User Access you won\'t be able to activate it again.', 'polldaddy' ); ?>
								</strong>

							</label>
						</span>
					</td>
				</tr>
				<?php
			}
			?>
			<tr class="form-field form-required">
				<th valign="top" scope="row">
					<label for="polldaddy-sync-account"><?php esc_html_e( 'Sync Ratings Account', 'polldaddy' ); ?></label>
				</th>
				<td>
					<input type="checkbox" name="polldaddy-sync-account" id="polldaddy-sync-account" value="1" style="width: auto" />
					<span class="description">
						<label for="polldaddy-sync-account"><?php esc_html_e( 'This will synchronize your ratings Crowdsignal account.', 'polldaddy' ); ?></label>
					</span>
				</td>
			</tr>
			<tr class="form-field form-required">
				<th valign="top" scope="row">
					<label for="polldaddy-ratings-title-filter"><?php esc_html_e( 'Ratings Title Filter', 'polldaddy' ); ?></label>
				</th>
				<td>
					<input type="text" name="polldaddy-ratings-title-filter" id="polldaddy-ratings-title-filter" value="<?php echo esc_attr( $rating_title_filter ); ?>" style="width: auto" />
					<span class="description">
						<label for="polldaddy-ratings-title-filter"><?php esc_html_e( 'This setting allows you to specify a filter to use with your ratings title.', 'polldaddy' ); ?></label>
					</span>
				</td>
			</tr>
		<?php }
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
			$string = preg_replace('/[\r\n\t\s]+/', ' ', $string);

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

add_action( 'init', 'polldaddy_loader' );
add_filter( 'widget_text', 'do_shortcode' );

/**
 * Polldaddy Top Rated Widget
 *
 * **/
if ( class_exists( 'WP_Widget' ) && ! class_exists( 'PD_Top_Rated' ) ) {
	class PD_Top_Rated extends WP_Widget {

		function __construct() {

			$widget_ops = array( 'classname' => 'top_rated', 'description' => __( 'A list of your top rated posts, pages or comments.', 'polldaddy' ) );
			parent::__construct( 'PD_Top_Rated', 'Top Rated', $widget_ops );
		}

		function PD_Top_Rated() {
			$this->__construct();
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

				if ( ! isset( $current_category ) ) {
					$current_category = null;
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
			$widget_class = 'posts';
			if ( $instance['show_pages'] == 1 ) {
				$show = "PDRTJS_TOP.get_top( 'pages', '0' );";
				$widget_class = 'pages';
			} elseif ( $instance['show_comments'] == 1 ) {
				$show = "PDRTJS_TOP.get_top( 'comments', '0' );";
				$widget_class = 'comments';
			}

        	echo '</script>';

			if ( is_ssl() )
				$rating_js_file = "https://polldaddy.com/js/rating/rating-top.js";
			else
				$rating_js_file = "http://i0.poll.fm/js/rating/rating-top.js";
			$widget = <<<EOD
{$before_title}{$title}{$after_title}
<div id="pd_top_rated_holder" class="pd_top_rated_holder_{$widget_class}"></div>
<script language="javascript" charset="UTF-8" src="{$rating_js_file}"></script>
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
}

if ( class_exists( 'PD_Top_Rated' ) ) {
	function polldaddy_register_top_rated_widget() {
		return register_widget( 'PD_Top_Rated' );
	}
	add_action( 'widgets_init', 'polldaddy_register_top_rated_widget' );
}

function polldaddy_login_warning() {
	global $hook_suffix;

	if (  false != get_option( 'polldaddy_api_key' ) || ! function_exists( "admin_url" ) ) {
		return;
	}

	$page   = isset( $_GET['page'] ) ? $_GET['page'] : '';
	$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

	if ( 'plugins.php' !== $hook_suffix && ! in_array( $page, [ 'polls', 'ratings' ], true ) ) {
		return;
	}

	// We want the main poll options page to never show this message.
	if ( 'polls' === $page && 'options' === $action ) {
		return;
	}

	echo '<div class="updated"><p><strong>' . sprintf( __( 'Crowdsignal features will be unavailable until you link your Crowdsignal.com account. Please visit the <a href="%s">plugin settings page</a> to login.', 'polldaddy' ), admin_url( 'options-general.php?page=crowdsignal-settings' ) ) . '</strong></p></div>';
}
add_action( 'admin_notices', 'polldaddy_login_warning' );

/**
 * On deactivation, remove all functions from the scheduled action hook.
 */
function polldaddy_deactivation() {
	wp_clear_scheduled_hook( 'polldaddy_rating_update_job' );
}
register_deactivation_hook( __FILE__, 'polldaddy_deactivation' );

?>

<?php

require_once dirname( __FILE__ ) . '/polldaddy-client.php';

class WPORG_PollDaddy extends WP_PollDaddy {
	var $use_ssl;

	function WPORG_PollDaddy() {
		$this->__construct();
	}

	function __construct() {
		parent::__construct();
		$this->version = '1.8.9';
		$this->base_url = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) . '/';
		$this->polldaddy_client_class = 'WPORG_PollDaddy_Client';
		$this->use_ssl = (int) get_option( 'polldaddy_use_ssl' );
		$this->multiple_accounts = (bool) get_option( 'polldaddy_multiple_accounts' );
		$this->is_author = ( ( (bool) current_user_can('edit_others_posts')) or ( $this->multiple_accounts ) );
		return;
	}

	function set_api_user_code() {
		if ( empty( $this->rating_user_code ) ) {
			$this->rating_user_code = get_option( 'pd-rating-usercode' );

			if ( empty( $this->rating_user_code ) ) {
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

	function management_page_load() {
		require_once WP_POLLDADDY__POLLDADDY_CLIENT_PATH;

		$is_POST = 'post' == strtolower( $_SERVER['REQUEST_METHOD'] );
		wp_reset_vars( array( 'action', 'page' ) );
		global $action, $page;

		$this->set_api_user_code();

		if ( $page == 'polls' ) {
			switch ( $action ) :
			case 'update-options' :
				if ( !$is_POST )
					return;

				if ( $this->is_admin ) {
					check_admin_referer( 'polldaddy-account' );

					$polldaddy = $this->get_client( WP_POLLDADDY__PARTNERGUID, $this->user_code );
					$polldaddy->reset();

					$polldaddy_sync_account = 0;
					$polldaddy_multiple_accounts = 0;

					if ( isset( $_POST['polldaddy-sync-account'] ) )
						$polldaddy_sync_account = (int) $_POST['polldaddy-sync-account'];

					if ( $polldaddy_sync_account > 0 ) {
						$this->rating_user_code = '';
						update_option( 'pd-rating-usercode', '' );
						$this->set_api_user_code();
					}

					if ( isset( $_POST['polldaddy-multiple-accounts'] ) )
						$polldaddy_multiple_accounts = (int) $_POST['polldaddy-multiple-accounts'];

					$partner = array( 'role' => $polldaddy_multiple_accounts );
					$polldaddy->update_partner_account( $partner );
					update_option( 'polldaddy_multiple_accounts', $polldaddy_multiple_accounts );
				}
			break;
			endswitch;
		}

		parent::management_page_load();
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
				$this->errors->add( 'http_code', __( 'Could not connect to PollDaddy API Key service', 'polldaddy' ) );
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
				$this->errors->add( 'connect', __( "Can't connect to PollDaddy.com", 'polldaddy' ) );
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
		}
		else {
			$this->errors->add( 'polldaddy_api_key', __( 'Login to PollDaddy failed.  Double check your email address and password.', 'polldaddy' ) );
			if ( 1 !== $this->use_ssl ) {
				$this->errors->add( 'polldaddy_api_key', __( 'If your email address and password are correct, your host may not support secure logins.', 'polldaddy' ) );
				$this->errors->add( 'polldaddy_api_key', __( 'In that case, you may be able to log in to PollDaddy by unchecking the "Use SSL to Log in" checkbox.', 'polldaddy' ) );
				$this->use_ssl = 0;
			}
			update_option( 'polldaddy_use_ssl', $this->use_ssl );
			return false;
		}

		$polldaddy = $this->get_client( $polldaddy_api_key );
		$polldaddy->reset();
		if ( !$polldaddy->get_usercode( $this->id ) ) {
			$this->parse_errors( $polldaddy );
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

	<h2><?php _e( 'PollDaddy Account', 'polldaddy' ); ?></h2>

	<p><?php printf( __('Before you can use the PollDaddy plugin, you need to enter your <a href="%s">PollDaddy.com</a> account details.' ), 'http://polldaddy.com/' ); ?></p>

	<form action="" method="post">
		<table class="form-table">
			<tbody>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-email"><?php _e( 'PollDaddy Email Address', 'polldaddy' ); ?></label>
					</th>
					<td>
						<input type="text" name="polldaddy_email" id="polldaddy-email" aria-required="true" size="40" value="<?php if ( isset( $_POST['polldaddy_email'] ) ) echo attribute_escape( $_POST['polldaddy_email'] ); ?>" />
					</td>
				</tr>
				<tr class="form-field form-required">
					<th valign="top" scope="row">
						<label for="polldaddy-password"><?php _e( 'PollDaddy Password', 'polldaddy' ); ?></label>
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
						<label for="polldaddy-use-ssl"><?php _e( 'This ensures a secure login to your PollDaddy account.  Only uncheck if you are having problems logging in.', 'polldaddy' ); ?></label>
						<input type="hidden" name="polldaddy_use_ssl_checkbox" value="1" />
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<?php wp_nonce_field( 'polldaddy-account' ); ?>
			<input type="hidden" name="action" value="account" />
			<input type="hidden" name="account" value="import" />
			<input type="submit" value="<?php echo attribute_escape( __( 'Submit', 'polldaddy' ) ); ?>" />
		</p>
	</form>
</div>

<?php
	}

	function plugin_options_add() {
		if ( $this->is_admin ) {
			$checked = '';
			if ( $this->multiple_accounts )
				$checked = 'checked="checked"';
			?><tr class="form-field form-required">
    <th valign="top" scope="row">
      <label for="polldaddy-multiple-accounts">
        <?php _e( 'Multiple PollDaddy Accounts', 'polldaddy' ); ?>
      </label>
    </th>
    <td>
      <input type="checkbox" name="polldaddy-multiple-accounts" id="polldaddy-multiple-accounts" value="1" <?php echo $checked ?> style="width: auto" />
        <span class="description">
          <label for="polldaddy-multiple-accounts"><?php _e( 'This setting will allow each blog user to import a PollDaddy account.', 'polldaddy' ); ?></label>
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
          <label for="polldaddy-sync-account"><?php _e( 'This will synchronize your ratings PollDaddy account.', 'polldaddy' ); ?></label>
        </span>
    </td>
  </tr><?php }
		return parent::plugin_options_add();
	}
}

class WPORG_PollDaddy_Client extends api_client {
	/**
	 *
	 *
	 * @return string|false PollDaddy partner account or false on failure
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
	 * @return string|false PollDaddy partner account or false on failure
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
	if ( is_a( $args, 'PollDaddy_Partner' ) )
		return $args;

	$defaults = _polldaddy_partner_defaults();

	$args = wp_parse_args( $args, $defaults );

	foreach ( array( 'name' ) as $required )
		if ( !is_string( $args[$required] ) || !$args[$required] )
			return $false;

		$obj = new PollDaddy_Partner( $args, $args );

	return $obj;
}

function _polldaddy_partner_defaults() {
	return array(
		'name' => get_bloginfo( 'name' ),
		'role' => 0
	);
}

define( 'WP_POLLDADDY__CLASS', 'WPORG_PollDaddy' );
define( 'WP_POLLDADDY__POLLDADDY_CLIENT_PATH', dirname( __FILE__ ) . '/polldaddy-client.php' );

function polldaddy_loader() {
	global $polldaddy_object;
	$polldaddy_class = WP_POLLDADDY__CLASS;
	$polldaddy_object = new $polldaddy_class;
	load_plugin_textdomain( 'polldaddy', '', 'polldaddy/locale' );
	add_action( 'admin_menu', array( &$polldaddy_object, 'admin_menu' ) );
}

if ( !function_exists( 'polldaddy_shortcode_handler' ) ) {
	/*
	 polldaddy.com
	 [polldaddy poll="139742"]
	 */
	
	function polldaddy_shortcode_handler_set_data() {
		$resource = wp_remote_get( 'http://polldaddy.com/xml/keywords.xml' );
		$body = wp_remote_retrieve_body( $resource );
		$keywords_xml = simplexml_load_string ( $body );
		$keywords = array();
		$keywords['generated'] = time();
	
		foreach ( $keywords_xml->keyword as $keyword_xml ){
			$keywords[] = array( 'keyword' => (string) $keyword_xml, 'url' => (string) $keyword_xml['url'] );
		}
		wp_cache_set( 'pd-keywords', $keywords, 'site-options', 864000 );
	
		return $keywords;
	}
	
	function polldaddy_add_rating_js() {
		wp_print_scripts( 'polldaddy-rating-js' );
	}
	
	function polldaddy_shortcode_handler( $atts, $content = null ) {
		global $post;
	
		extract( shortcode_atts( array(	
			'survey'     => null,
			'link_text'  => 'View Survey',
			'poll'       => 'empty',
			'rating'     => 'empty',
			'unique_id'  => null,
			'title'      => null,
			'permalink'  => null,
			'cb'         => 0,
			'type'       => null,
			'body'       => '',
			'button'     => '',
			'text_color' => '000000',
			'back_color' => 'FFFFFF',
			'align'      => '',
			'style'      => ''
		), $atts ) );
	
		$survey = esc_attr( str_replace( "'", "", $survey ) );
		$link_text = esc_attr( $link_text );
		
		if ( null != $survey ) {
	
			// This is the new survey embed
			if ( $type != null ) {
				//need to use esc_js and esc_attr as the values will be inserted into javascript while being enclosed in single quotes.
				$title      = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $title ) ) );
				$type       = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $type ) ) );
				$body       = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $body ) ) );
				$button     = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $button ) ) );
				$text_color = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $text_color ) ) );
				$back_color = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $back_color ) ) );
				$align      = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $align ) ) );
				$style      = preg_replace( '/&amp;(\w*);/', '&$1;', esc_js( esc_attr( $style ) ) );
	
				return "
					<script type='text/javascript' src='http://i0.poll.fm/survey.js' charset='UTF-8'></script>
					<noscript><a href='http://polldaddy.com/s/$survey'>$title</a></noscript>
					<script type='text/javascript'>
					  polldaddy.add( {
					    title: '$title',
					    type: '$type',
					    body: '$body',
					    button: '$button',
					    text_color: '$text_color',
					    back_color: '$back_color',
					    align: '$align',
					    style: '$style',
					    id: '$survey'
					  } );
					</script>			
				";
			
			} else {
				return "
					<script language='javascript' type='text/javascript'>
					var PDF_surveyID = '$survey';
					var PDF_openText = '$link_text';
					</script>
					<script type='text/javascript' language='javascript' src='http://www.polldaddy.com/s.js'></script>
					<noscript><a href='http://surveys.polldaddy.com/s/$survey/'>$link_text</a></noscript>	
				";
			}
		}
	
		$poll = (int) $poll;
		$rating = (int) $rating;
		$cb = (int) $cb;
	
		if ( $rating > 0 ) {
			if ( null != $unique_id ) { 
				$unique_id = wp_specialchars( $unique_id );
			} else {
				$unique_id = is_page() ? 'wp-page-' : 'wp-post-';
				$unique_id .= $post->ID;
			}
	
			if ( null != $title )
				$title = wp_specialchars( $title );
			else
				$title = urlencode( $post->post_title );
	
			if ( null != $permalink )
				$permalink = clean_url( $permalink );
			else
				$permalink = urlencode( get_permalink( $post->ID ) );
			
			wp_register_script( 'polldaddy-rating-js', 'http://i.polldaddy.com/ratings/rating.js' );
			add_filter( 'wp_footer', 'polldaddy_add_rating_js' );
			
			return '<div id="pd_rating_holder_' . $rating . '"></div>
<script language="javascript">
	PDRTJS_settings_' . $rating . ' = {
		"id" : "' . $rating . '",
		"unique_id" : "' . $unique_id . '",
		"title" : "' . $title . '",
		"permalink" : "' . $permalink . '"
	};
</script>';
		} elseif ( $poll > 0 ) {
			$cb = ( $cb == 1 ? '?cb=' . mktime() : '' );
			$keywords = wp_cache_get( 'pd-keywords', 'site-options' );
			if ( ! $keywords || $keywords['generated'] <= ( time() - 300 ) ) {
				if ( ! wp_cache_get( 'pd-keywords-fetching', 'site-options' ) ) {
					wp_cache_set( 'pd-keywords-fetching', 1, 'site-options', 30 );
					$keywords = polldaddy_shortcode_handler_set_data();
				}
			}
	
			if ( !$keywords )
				$keywords = array();
		
			$mod = ( $poll % ( count( $keywords ) - 1 ) );
	
			return '<a name="pd_a_' . $poll . '"></a><div class="PDS_Poll" id="PDI_container' . $poll . '" style="display:inline-block;"></div><script type="text/javascript" language="javascript" charset="utf-8" src="http://static.polldaddy.com/p/' . $poll . '.js' . $cb . '"></script>
<noscript>
<a href="http://polldaddy.com/poll/' . $poll . '/">View This Poll</a><br/><span style="font-size:10px;"><a href="' . $keywords[ $mod ][ 'url' ] . '">' . $keywords[ $mod ][ 'keyword' ] . '</a></span>
</noscript>';
		}
	
		return '<!-- no polldaddy output -->';
	}
	
	// http://polldaddy.com/poll/1562975/?view=results&msg=voted
	function polldaddy_link( $content ) {
		return preg_replace( '!(?:\n|\A)http://polldaddy.com/poll/([0-9]+?)/(.+)?(?:\n|\Z)!i', "\n<script type='text/javascript' language='javascript' charset='utf-8' src='http://static.polldaddy.com/p/$1.js'></script><noscript> <a href='http://polldaddy.com/poll/$1/'>View Poll</a></noscript>\n", $content );
	}
	
	// higher priority because we need it before auto-link and autop get to it
	add_filter( 'the_content', 'polldaddy_link', 1 );
	add_filter( 'the_content_rss', 'polldaddy_link', 1 );
	add_filter( 'comment_text', 'polldaddy_link', 1 );
	
	add_shortcode( 'polldaddy', 'polldaddy_shortcode_handler' );
}

add_action( 'init', 'polldaddy_loader' );
add_filter( 'widget_text', 'do_shortcode' );

/**
 * PollDaddy Top Rated Widget
 *
 * **/
if ( class_exists( 'WP_Widget' ) ) {
	class PD_Top_Rated extends WP_Widget {

		function PD_Top_Rated() {

			$widget_ops = array( 'classname' => 'top_rated', 'description' => __( 'A list of your top rated posts, pages or comments.' ) );
			$this->WP_Widget( 'PD_Top_Rated', 'Top Rated', $widget_ops );
		}

		function widget($args, $instance) {

			extract($args, EXTR_SKIP);

			echo $before_widget;
			$title = empty( $instance['title'] ) ? __( 'Top Rated', 'polldaddy' ) : apply_filters( 'widget_title', $instance['title'] );
			$posts_rating_id = (int) get_option( 'pd-rating-posts-id' );
			$pages_rating_id = (int) get_option( 'pd-rating-pages-id' );
			$comments_rating_id = (int) get_option( 'pd-rating-comments-id' );

			echo $before_title . $title . $after_title;
			echo '<div id="pd_top_rated_holder"></div>';
			echo '<script language="javascript" src="http://i.polldaddy.com/ratings/rating-top.js"></script>';
			echo '<script language="javascript" type="text/javascript">';
			$rating_seq = $instance['show_posts'] . $instance['show_pages'] . $instance['show_comments'];

			echo '  PDRTJS_TOP = new PDRTJS_RATING_TOP( ' . $posts_rating_id . ', ' . $pages_rating_id . ', ' . $comments_rating_id . ", '"  . $rating_seq . "', " . $instance['item_count'] . ' );';
			echo '</script>';
			echo $after_widget;
		}

		function update( $new_instance, $old_instance ) {

			$instance = $old_instance;
			$instance['title'] = strip_tags($new_instance['title']);
			$instance['show_posts'] = (int) $new_instance['show_posts'];
			$instance['show_pages'] = (int) $new_instance['show_pages'];
			$instance['show_comments'] = (int) $new_instance['show_comments'];
			$instance['item_count'] = (int) $new_instance['item_count'];
			return $instance;
		}

		function form( $instance ) {

			$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'show_posts' => '1', 'show_pages' => '1', 'show_comments' => '1', 'item_count' => '5' ) );
			$title = strip_tags( $instance['title'] );
			$show_posts = (int) $instance['show_posts'];
			$show_pages = (int) $instance['show_pages'];
			$show_comments = (int) $instance['show_comments'];
			$item_count = (int) $instance['item_count'];
?>
				<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title', 'polldaddy' ); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape( $title ); ?>" /></label></p>
				<p>
					<label for="<?php echo $this->get_field_id( 'show_posts' ); ?>">
						<?php
			$checked = '';
			if ( $show_posts == 1 )
				$checked = 'checked="checked"';
?>
					<input type="checkbox" class="checkbox"  id="<?php echo $this->get_field_id( 'show_posts' ); ?>" name="<?php echo $this->get_field_name( 'show_posts' ); ?>" value="1" <?php echo $checked; ?> />
						 <?php _e( 'Show for posts', 'polldaddy' ); ?>
					</label>
				</p>
						<p>
							<label for="<?php echo $this->get_field_id( 'show_pages' ); ?>">
								<?php
			$checked = '';
			if ( $show_pages == 1 )
				$checked = 'checked="checked"';
?>
							<input type="checkbox" class="checkbox"  id="<?php echo $this->get_field_id( 'show_pages' ); ?>" name="<?php echo $this->get_field_name( 'show_pages' ); ?>" value="1" <?php echo $checked; ?> />
								 <?php _e( 'Show for pages', 'polldaddy' ); ?>
							</label>
						</p>
						<p>
							<label for="<?php echo $this->get_field_id( 'show_comments' ); ?>">
								<?php
			$checked = '';
			if ( $show_comments == 1 )
				$checked = 'checked="checked"';
?>
									<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'show_comments' ); ?>" name="<?php echo $this->get_field_name( 'show_comments' ); ?>" value="1" <?php echo $checked; ?>/>
								 <?php _e( 'Show for comments', 'polldaddy' ); ?>
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
?>

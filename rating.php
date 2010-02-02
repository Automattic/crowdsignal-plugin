<?php
function polldaddy_show_rating_comments( $content ){
	if ( !is_feed() ) {
		global $comment;
		global $post;

		if ( $comment->comment_ID > 0 ) {
			$unique_id = '';
			$title = '';
			$permalink = '';
			$html = '';
			$rating_pos = 0;
			
			if ( (int) get_option( 'pd-rating-comments' ) > 0 ) {
				$rating_id = (int) get_option( 'pd-rating-comments' );
				$unique_id = 'wp-comment-' . $comment->comment_ID;
        $rating_pos = (int) get_option( 'pd-rating-comments-pos' );
				$title = mb_substr( $comment->comment_content, 0, 195 ) . '...';
				$permalink = get_permalink( $post->ID ) . '#comment-' . $comment->comment_ID;	            
				$html = polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, '_comm_' . $comment->comment_ID );

				wp_register_script( 'polldaddy-rating-js', 'http://i.polldaddy.com/ratings/rating.js' );
				add_filter( 'wp_footer', 'polldaddy_add_rating_js' );
			}

      if ( $rating_pos == 0 )
          $content = $html . $content;
      else
          $content .= $html;
		}   
	}
	return $content;
}

function polldaddy_show_rating( $content ) {
	if ( !is_feed() && !is_attachment() ) {
		if ( is_single() || is_page() || is_home() ) {
			global $post;

			if ( $post->ID > 0 ) {
				$unique_id = '';
				$title = '';
				$permalink = '';
				$html = '';
				$rating_id = 0;
				$rating_pos = 0;
				$item_id = '';

				if ( is_page() ) {
					if ( (int) get_option( 'pd-rating-pages' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-pages' );
						$unique_id = 'wp-page-' . $post->ID;
						$rating_pos = (int) get_option( 'pd-rating-pages-pos' );
						$item_id =  '_page_' . $post->ID;
					}
				} else if( is_home() ) {
				  if ( (int) get_option( 'pd-rating-posts-index' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-posts-index' );
						$unique_id = 'wp-post-' . $post->ID;
						$rating_pos = (int) get_option( 'pd-rating-posts-index-pos' );
						$item_id =  '_post_' . $post->ID;
					}
        } else { 
					if ( (int) get_option( 'pd-rating-posts' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-posts' );
	          $unique_id = 'wp-post-' . $post->ID;
		        $rating_pos = (int) get_option( 'pd-rating-posts-pos' );
			      $item_id =  '_post_' . $post->ID;
					}
				}

				if ( $rating_id > 0 ) {
					$title = $post->post_title;
					$permalink = get_permalink( $post->ID );
					$html = polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id );

					wp_register_script( 'polldaddy-rating-js', 'http://i.polldaddy.com/ratings/rating.js' );
					add_filter( 'wp_footer', 'polldaddy_add_rating_js' );
				}

				if ( $rating_pos == 0 )
					$content = $html . $content;
				else
					$content .= $html;
			}	
		}
	}
	return $content;	
}

function polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id = '' ) {
	$html = "\n".'<p><div class="pd-rating" id="pd_rating_holder_' . $rating_id . $item_id . '"></div></p>
<script type="text/javascript">
	PDRTJS_settings_' . (int)$rating_id . $item_id . ' = {
		"id" : "' . (int)$rating_id . '",
		"unique_id" : "' . urlencode( $unique_id ) . '",
		"title" : "' . urlencode( $title ) . '",' . "\n";

		if ( $item_id != '' )
			$html .=  ( '		"item_id" : "' . $item_id . '",' . "\n" );

		$html .= '		"permalink" : "' . urlencode( clean_url( $permalink ) ) . '"';
		$html .= "\n	}\n";
		$html .=  "</script>\n";

	return $html;
}

function polldaddy_show_rating_excerpt( $content ) {
 remove_filter( 'the_content', 'polldaddy_show_rating', 5 );
 return $content;
}

function polldaddy_show_rating_excerpt_for_real( $content ) {
 return polldaddy_show_rating( $content );
}

add_filter( 'the_content', 'polldaddy_show_rating', 5 );
add_filter( 'get_the_excerpt', 'polldaddy_show_rating_excerpt', 5 );
add_filter( 'the_excerpt', 'polldaddy_show_rating_excerpt_for_real' );
add_filter( 'comment_text', 'polldaddy_show_rating_comments', 50 );
?>
<?php
function polldaddy_show_rating_comments( $content ) {
	if ( !is_feed() && !defined( 'DOING_AJAX' ) ) {
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
				$title = mb_substr( preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $comment->comment_content ), 0, 195 ) . '...';
				$permalink = get_permalink( $post->ID ) . '#comment-' . $comment->comment_ID;
				$html = polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, '_comm_' . $comment->comment_ID );
			}

			if ( $rating_pos == 0 )
				$content = $html . '<br/>' . $content;
			else
				$content .= $html;
		}
	}
	return $content;
}

function polldaddy_show_rating( $content ) {
	if ( !is_feed() && !is_attachment() ) {
		if ( is_single() || is_page() || is_home() || is_archive() || is_category() ) {
			$html = polldaddy_get_rating_html( 'check-options' );

			if ( !empty( $html ) ) {
				$rating_pos = 0;

				if ( is_page() ) {
					$rating_pos = (int) get_option( 'pd-rating-pages-pos' );
				} elseif ( is_home() || is_archive() || is_category() ) {
					$rating_pos = (int) get_option( 'pd-rating-posts-index-pos' );
				} else {
					$rating_pos = (int) get_option( 'pd-rating-posts-pos' );
				}

				if ( $rating_pos == 0 )
					$content = $html . '<br/>' . $content;
				else
					$content .= $html;
			}
		}
	}
	return $content;
}

function polldaddy_get_rating_html( $condition = '' ) {
	global $post;
	$html = '';

	if ( $post->ID > 0 ) {
		$unique_id = '';
		$title = '';
		$permalink = '';
		$rating_id = 0;
		$item_id = '';
		$exclude_posts = explode( ',', get_option( 'pd-rating-exclude-post-ids' ) );
		$exclude_pages = explode( ',', get_option( 'pd-rating-exclude-page-ids' ) );

		if ( is_page() ) {
			if ( !in_array( $post->ID, $exclude_pages ) ) {
				$unique_id = 'wp-page-' . $post->ID;
				$item_id =  '_page_' . $post->ID;
				if ( $condition == 'check-options' ) {
					if ( (int) get_option( 'pd-rating-pages' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-pages-id' );
					}
				} else {
					$rating_id = (int) get_option( 'pd-rating-pages-id' );
				}
			}
		} elseif ( !in_array( $post->ID, $exclude_posts ) ) {
			$unique_id = 'wp-post-' . $post->ID;
			$item_id =  '_post_' . $post->ID;
			if ( is_home() || is_archive() || is_category() ) {
				if ( $condition == 'check-options' ) {
					if ( (int) get_option( 'pd-rating-posts-index' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-posts-id' );
					}
				} else {
					$rating_id = (int) get_option( 'pd-rating-posts-id' );
				}
			} else {
				if ( $condition == 'check-options' ) {
					if ( (int) get_option( 'pd-rating-posts' ) > 0 ) {
						$rating_id = (int) get_option( 'pd-rating-posts-id' );
					}
				} else {
					$rating_id = (int) get_option( 'pd-rating-posts-id' );
				}
			}
		}

		if ( $rating_id > 0 ) {
			$rating_title_filter = get_option( 'pd-rating-title-filter' );
			
			if ( $rating_title_filter === false )
				$title = apply_filters( 'wp_title', $post->post_title );
			elseif ( strlen( $rating_title_filter ) > 0 )
				$title = apply_filters( $rating_title_filter, $post->post_title );
			else
				$title = $post->post_title;
				
			$permalink = get_permalink( $post->ID );
			$html = polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id );
		}
	}
	return $html;
}

/**
 * Construct a Polldaddy target div for a given rating_id and optional item_id
 * Define a Polldaddy ratings variable for a given rating_id and optional item_id
 *
 * @param int     $rating_id
 * @param string  $unique_id
 * @param string  $title     Post title
 * @param string  $permalink Post permalink
 * @param string  $item_id
 * @return HTML snippet with a ratings container and a related JS block defining a new variable
 */
function polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id = '' ) {	
	$html = sprintf( '[polldaddy rating="%d" item_id="%s" unique_id="%s" title="%s" permalink="%s"]', absint( $rating_id ), polldaddy_sanitize_shortcode( $item_id ), polldaddy_sanitize_shortcode( $unique_id ), polldaddy_sanitize_shortcode( $title ), polldaddy_sanitize_shortcode( $permalink ) );
	return do_shortcode( $html );
}

function polldaddy_sanitize_shortcode( $text ) {
	$text = preg_replace( array( '/\[/', '/\]/' ), array( '&#91;', '&#93;' ), $text );
	return esc_attr( $text );
}

function polldaddy_show_rating_excerpt( $content ) {
	remove_filter( 'the_content', 'polldaddy_show_rating', 5 );
	return $content;
}

function polldaddy_show_rating_excerpt_for_real( $content ) {
	return polldaddy_show_rating( $content );
}

if ( (int) get_option( 'pd-rating-pages' ) > 0 || (int) get_option( 'pd-rating-posts-index' ) > 0 || (int) get_option( 'pd-rating-posts' ) > 0 ) {
	add_filter( 'the_content', 'polldaddy_show_rating', 5 );
	add_filter( 'get_the_excerpt', 'polldaddy_show_rating_excerpt', 5 );
	add_filter( 'the_excerpt', 'polldaddy_show_rating_excerpt_for_real' );
}

add_filter( 'comment_text', 'polldaddy_show_rating_comments', 50 );
?>
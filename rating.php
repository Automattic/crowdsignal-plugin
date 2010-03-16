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
      $html = polldaddy_get_rating_html( 'check-options' );
      
			if( !empty( $html ) ) {
        $rating_pos = 0;
           		
  		  if ( is_page() ) {
      		$rating_pos = (int) get_option( 'pd-rating-pages-pos' );
        } else if( is_home() ) {
      		$rating_pos = (int) get_option( 'pd-rating-posts-index-pos' );
        } else { 
          $rating_pos = (int) get_option( 'pd-rating-posts-pos' );
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

function polldaddy_get_rating_html( $condition = '' ){
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
      if( !in_array( $post->ID, $exclude_pages ) ){
        $unique_id = 'wp-page-' . $post->ID;
        $item_id =  '_page_' . $post->ID;
        if ( $condition == 'check-options' ){
          if( (int) get_option( 'pd-rating-pages' ) > 0 ){
            $rating_id = (int) get_option( 'pd-rating-pages-id' );
          }
        } else {
          $rating_id = (int) get_option( 'pd-rating-pages-id' );
        }         
      }
    } else if ( !in_array( $post->ID, $exclude_posts ) ) {
      $unique_id = 'wp-post-' . $post->ID;
      $item_id =  '_post_' . $post->ID;       
      if ( is_home() ){
        if ( $condition == 'check-options' ){
          if( (int) get_option( 'pd-rating-posts-index' ) > 0 ){
            $rating_id = (int) get_option( 'pd-rating-posts-id' );
          }
        } else {
          $rating_id = (int) get_option( 'pd-rating-posts-id' );
        }
      } else {
        if ( $condition == 'check-options' ){
          if( (int) get_option( 'pd-rating-posts' ) > 0 ){
            $rating_id = (int) get_option( 'pd-rating-posts-id' );
          }    
        } else {
          $rating_id = (int) get_option( 'pd-rating-posts-id' );
        }       
      }
    }
  
  	if ( $rating_id > 0 ) {
  		$title = $post->post_title;
  		$permalink = get_permalink( $post->ID );
  		$html = polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id );
  		wp_register_script( 'polldaddy-rating-js', 'http://i.polldaddy.com/ratings/rating.js' );
			add_filter( 'wp_footer', 'polldaddy_add_rating_js' );
  	}
  }
  return $html;
}

function polldaddy_get_rating_code( $rating_id, $unique_id, $title, $permalink, $item_id = '' ) {
	$html = "\n".'<div class="pd-rating" id="pd_rating_holder_' . $rating_id . $item_id . '"></div>
<script type="text/javascript" charset="utf-8">
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

if ( (int) get_option( 'pd-rating-pages' ) > 0 || (int) get_option( 'pd-rating-posts-index' ) > 0 || (int) get_option( 'pd-rating-posts' ) > 0 ) {   
  add_filter( 'the_content', 'polldaddy_show_rating', 5 );
  add_filter( 'get_the_excerpt', 'polldaddy_show_rating_excerpt', 5 );
  add_filter( 'the_excerpt', 'polldaddy_show_rating_excerpt_for_real' ); 
}

add_filter( 'comment_text', 'polldaddy_show_rating_comments', 50 );
?>
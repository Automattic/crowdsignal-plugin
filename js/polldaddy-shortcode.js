(function($) {

	window.polldaddyshortcode = {

		render: function() {
			var ratings = $( 'div.pd-rating[data-settings]' );
			var polls = $( 'div.PDS_Poll[data-settings]' );

			if ( polls ){
				$.each( polls, function() {
					var poll_el = this;

					// Only process each poll element once, even if render() runs
					// again (e.g. on a later 'post-load' / 'pd-script-load' event).
					if ( poll_el.getAttribute( 'data-pd-init-done' ) ) {
						return;
					}
					poll_el.setAttribute( 'data-pd-init-done', '1' );

					var poll = $( poll_el ).data( 'settings' );

					if ( poll ) {
						var poll_url = document.createElement("a");
						poll_url.href = poll['url'];
						// Skip this element with return, not return false: return false
						// would break the whole $.each loop and hide later valid polls.
						if ( poll_url.protocol !== 'https:' ) {
							return;
						}
						if ( poll_url.hostname != 'secure.polldaddy.com' && poll_url.hostname != 'static.polldaddy.com' ) {
							return;
						}
						var pathname = poll_url.pathname;
						if ( ! /^\/p\/\d+\.js$/.test( pathname ) ) {
							return;
						}
						var wp_pd_js = document.createElement('script');
						wp_pd_js.type = 'text/javascript';
						wp_pd_js.src = poll_url.href;
						wp_pd_js.charset = 'utf-8';
						wp_pd_js.async = true;
						document.getElementsByTagName('head')[0].appendChild(wp_pd_js);
					}
				});
			};

			if ( ratings ){
				$.each( ratings, function() {
					var rating_el = this;

					// Only process each rating element once.
					if ( rating_el.getAttribute( 'data-pd-init-done' ) ) {
						return;
					}
					rating_el.setAttribute( 'data-pd-init-done', '1' );

					var rating = $( rating_el ).data( 'settings' );

					if ( ! rating ) {
						return;
					}

					// The 'settings' value is a JSON-encoded settings object built
					// server-side (see polldaddy-shortcode.php). Parse it as data and
					// assign it directly rather than concatenating it into a <script>,
					// so the markup can only ever contribute data, never executable code.
					var settings;
					try {
						settings = JSON.parse( rating['settings'] );
					} catch ( e ) {
						return;
					}

					var key = '' + rating['id'] + rating['item_id'];
					window[ 'PDRTJS_settings_' + key ] = settings;

					if ( typeof PDRTJS_RATING !== 'undefined' && typeof window[ 'PDRTJS_' + key ] === 'undefined' ) {
						window[ 'PDRTJS_' + key ] = new PDRTJS_RATING( window[ 'PDRTJS_settings_' + key ] );
					}
				});
			};
		}
	}

	$('body').on( 'post-load pd-script-load', function() { window.polldaddyshortcode.render() } );
	$('body').trigger( 'pd-script-load' );
})(jQuery);

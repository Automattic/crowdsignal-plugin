(function($) {

	window.polldaddyshortcode = {

		render: function() {
			var ratings = $( 'div.pd-rating[data-settings]' );
			var polls = $( 'div.PDS_Poll[data-settings]' );

			if ( polls ){
				$.each( polls, function() {
					var poll = $( this ).data( 'settings' );

					if ( poll ) {
						var poll_url = document.createElement("a");
						poll_url.href = poll['url'];
						if ( poll_url.hostname != 'secure.polldaddy.com' && poll_url.hostname != 'static.polldaddy.com' ) {
							return false;
						}
						var pathname = poll_url.pathname;
						if ( ! /\/?p\/\d+\.js/.test( pathname ) ) {
							return false;
						}
						var wp_pd_js = document.createElement('script');
						wp_pd_js.type = 'text/javascript';
						wp_pd_js.src = poll['url'];
						wp_pd_js.charset = 'utf-8';
						wp_pd_js.async = true;
						document.getElementsByTagName('head')[0].appendChild(wp_pd_js);
					}
				});
			};

			if ( ratings ){
				$.each( ratings, function() {
					var rating = $( this ).data( 'settings' );

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

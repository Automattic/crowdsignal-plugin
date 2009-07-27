jQuery(function($){
	$('.hide-if-js').hide();
	$('.hide-if-no-js').removeClass( 'hide-if-no-js' );

	$('a.delete-poll').click( function() {
		return confirm( 'Are you sure you want to delete "' + $(this).parents( 'td' ).find( 'strong' ).text() + '"?' );
	} );

	$('span.view a.thickbox').attr( 'href', function() {
		return $(this).attr( 'href' ) + '&iframe&TB_iframe=true';
	} );

	var delAnswerPrep = function( context ) {
		$('a.delete-answer', context || null ).click( function() {
			if ( confirm( 'Are you sure you want to delete this answer?' ) ) {
				$(this).parents( 'li' ).remove();
			}
			return false;
		} );
	};
	delAnswerPrep();

	$('#answers').sortable( {
		axis: 'y',
		containment: 'parent',
		handle: '.handle',
		tolerance: 'pointer'
	} );

	$('#add-answer-holder').show().find( 'button').click( function() {
		var aa = ( 1 + $('#answers li').size() ).toString();
		delAnswerPrep( $('#answers').append( '<li><span class="handle">&#x2195;</span><div><input type="text" name="answer[new' + aa + ']" size="30" tabindex="2" value="" autocomplete="off" /></div><a title="delete this answer" class="delete-answer delete" href="#">&times;</a></li>' ).find( 'li:last' ) );
		return false;
	} );

	var win = window.dialogArguments || opener || parent || top;
	$('.polldaddy-send-to-editor').click( function() {
		var pollID = $(this).siblings('.polldaddy-poll-id').val();
		if ( !pollID )
			pollID = $('.polldaddy-poll-id:first').val();
		win.send_to_editor( '[polldaddy poll=' + parseInt( pollID ).toString() + ']' );
	} );

	$('.polldaddy-show-shortcode').toggle( function() {
		$(this).parents('tr:first').next('tr').fadeIn();
		return false;
	}, function() {
		$(this).parents('tr:first').next('tr').fadeOut();
		return false;
	} );

	var img1 = $('#design img:first');
	if ( !img1.size() ) {
		return;
	}
	var img2 = $('#design img:last');
	var imgPath = 'http://polldaddy.com/images/';

	var styleCount = $(':input[name=styleID] option').size();
	var styles = $(':input[name=styleID]').remove();
	var o = parseInt( styles.val() );
	$('#design_standard').append( '<input type="hidden" id="hidden-styleID" name="styleID" value="' + o.toString() + '" /><p><strong id="styleID-name">' + $(styles.find('option').get(o)).text() + '</strong><br /><span id="span-styleID">' + ( o + 1 ).toString() + '</span> of ' + styleCount + '</p>');
	var hiddenStyleID = $('#hidden-styleID');
	var spanStyleID = $('#span-styleID');
	var styleIDName = $('#styleID-name');

	var changePreview = function( i ) {
		var o = parseInt( img1.attr( 'src' ).substr( imgPath.length ) );
		img1.attr( 'src', imgPath + ( ( i + o + styleCount ) % styleCount ).toString() + '.gif' );
		img2.attr( 'src', imgPath + ( ( 2 * i + o + styleCount ) % styleCount ).toString() + '.gif' );
		hiddenStyleID.val( ( ( i + o + styleCount ) % styleCount ).toString() );
		spanStyleID.text( ( ( i + o + styleCount ) % styleCount + 1 ).toString() );
		styleIDName.text( $(styles.find('option').get( ( i + o + styleCount ) % styleCount )).text() );
	};

	$('#design a.alignleft').click( function() { changePreview( -1 ); return false; } );
	$('#design a.alignright').click( function() { changePreview( 1 ); return false; } );
	
	var customStyle = $(':input[name=styleID_custom]');
	var customStyleVal = parseInt( customStyle.val() );
	
	customStyle.change(function() {
		var customStyleVal = parseInt( customStyle.val() );
	   	hiddenStyleID.val( customStyleVal.toString() );
	});
	
	if ( customStyleVal > 0 ) {
		$('#design_standard').hide();
		$('#design_custom').show();
		$('.polldaddy-show-design-options').html('Standard Styles');
		hiddenStyleID.val( customStyleVal.toString() );	
		
		$('.polldaddy-show-design-options').toggle( function() {
			$('#design_custom').hide();
			$('#design_standard').fadeIn();
			$('.polldaddy-show-design-options').html('Custom Styles');
			var styleVal = parseInt( img1.attr( 'src' ).substr( imgPath.length ) );
			hiddenStyleID.val( styleVal.toString() );
			return false;
		}, function() {
			$('#design_standard').hide();
			$('#design_custom').fadeIn();
			$('.polldaddy-show-design-options').html('Standard Styles');
			var customStyle = $(':input[name=styleID_custom]');
			var customStyleVal = parseInt( customStyle.val() );
			hiddenStyleID.val( customStyleVal.toString() );
			return false;				
		} );
	}
	else{
		$('#design_custom').hide();
		$('#design_standard').show();
		
		$('.polldaddy-show-design-options').toggle( function() {
			$('#design_standard').hide();
			$('#design_custom').fadeIn();
			$('.polldaddy-show-design-options').html('Standard Styles');
			var customStyle = $(':input[name=styleID_custom]');
			var customStyleVal = parseInt( customStyle.val() );
			hiddenStyleID.val( customStyleVal.toString() );
			return false;
		}, function() {
			$('#design_custom').hide();
			$('#design_standard').fadeIn();
			$('.polldaddy-show-design-options').html('Custom Styles');
			var styleVal = parseInt( img1.attr( 'src' ).substr( imgPath.length ) );
			hiddenStyleID.val( styleVal.toString() );
			return false;
		} );
	}
});

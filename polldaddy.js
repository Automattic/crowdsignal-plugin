jQuery(function($){
	$('.hide-if-js').hide();
	$('.empty-if-js').empty();
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
				$('#choices option:last-child').remove();
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
		$('#choices').append('<option value="'+aa+'">'+aa+'</option>');
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
		$(this).parents('tr:first').next('tr').show();
		return false;
	}, function() {
		$(this).parents('tr:first').next('tr').fadeOut();
		$(this).parents('tr:first').next('tr').hide();
		return false;
	} );
	
	var hiddenStyleID = $(':input[name=styleID]');	
	var customStyle = $(':input[name=customSelect]');
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
			hiddenStyleID.val( 'x' );
			return false;
		}, function() {
			$('#design_standard').hide();
			$('#design_custom').fadeIn();
			$('.polldaddy-show-design-options').html('Standard Styles');
			var customStyle = $(':input[name=customSelect]');
			var customStyleVal = parseInt( customStyle.val() );
			if ( customStyleVal > 0 ){
				hiddenStyleID.val( customStyleVal.toString() );
			}
			else{
				hiddenStyleID.val( 'x' );
			}			
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
			var customStyle = $(':input[name=customSelect]');
			var customStyleVal = parseInt( customStyle.val() );
			if ( customStyleVal > 0 ){
				hiddenStyleID.val( customStyleVal.toString() );
			}
			else{
				hiddenStyleID.val( 'x' );
			}
			return false;
		}, function() {
			$('#design_custom').hide();
			$('#design_standard').fadeIn();
			$('.polldaddy-show-design-options').html('Custom Styles');
			hiddenStyleID.val( 'x' );
			return false;
		} );
	}
	
	$("#multipleChoice").click(function(){
	        if ($("#multipleChoice").is(":checked"))
	        {
	            $("#numberChoices").show("fast");
	        }
	        else
	        {     
	            $("#numberChoices").hide("fast");
	        }
	      });
});
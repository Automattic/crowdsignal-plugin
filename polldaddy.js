jQuery(function ($) {
    if (typeof(window.$) == 'undefined') { window.$ = jQuery; }
    Plugin = function (args) {
		var opts = $.extend( {
            delete_rating: 'Are you sure you want to delete the rating for "%s"?',
            delete_poll: 'Are you sure you want to delete "%s"?',
            delete_answer: 'Are you sure you want to delete this answer?',
            new_answer: 'Enter an answer here',
            delete_answer_title: 'delete this answer',
            reorder_answer_title: 'click and drag to reorder',
            add_image_title: 'Add an Image',
            add_audio_title: 'Add Audio',
            add_video_title: 'Add Video',
            standard_styles: 'Standard Styles',
            custom_styles: 'Custom Styles'
        }, args);
        $('.hide-if-js').hide();
        $('.empty-if-js').empty();
        $('.hide-if-no-js').removeClass('hide-if-no-js');
        $('.polldaddy-shortcode-row pre').click(function () {
            var refNode = $(this)[0];
            if ($.browser.msie) {
                var range = document.body.createTextRange();
                range.moveToElementText(refNode);
                range.select();
            } else if ($.browser.mozilla || $.browser.opera) {
                var selection = window.getSelection();
                var range = document.createRange();
                range.selectNodeContents(refNode);
                selection.removeAllRanges();
                selection.addRange(range);
            } else if ($.browser.safari) {
                var selection = window.getSelection();
                selection.setBaseAndExtent(refNode, 0, refNode, 1);
            }
        });
        $('input#shortcode-field').click( function(){ 
        
        	$( this ).select();
        		
        } );
        
        $('a.delete-rating').click(function () {
            return confirm( opts.delete_rating.replace( "%s", "'" + $(this).parents('td').find('strong').text() + "'" ) );
        });
        $('a.delete-poll').click(function () {
            return confirm( opts.delete_poll.replace( "%s", "'" + $(this).parents('td').find('strong').text() + "'" ) );
        });
        $('span.view a.thickbox').attr('href', function () {
            return $(this).attr('href') + '&iframe&TB_iframe=true';
        });
        var delAnswerPrep = function (context) {
            $('a.delete-answer', context || null).click(function () {
                if (confirm( opts.delete_answer )) {
                    $(this).parents('li').remove();
                    $('#choices option:last-child').remove();
                }
                return false;
            });
        };
        delAnswerPrep();
        $('#answers').sortable({
            axis: 'y',
            containment: 'parent',
            handle: '.handle',
            tolerance: 'pointer'
        });
        
        function add_answer( aa, src ) {			
			return false;
		}
		
		var busy = false;
        $('#add-answer-holder').show().find('button').click(function () {
        	if ( !busy ) {
        		busy = true;
	            var aa = (1 + get_number_answers()).toString();
	            var src = $( this ).closest( 'p' ).attr( 'class' );            
	            			
				$( 'form[name=add-answer] input[name=aa]' ).val( aa );
				$( 'form[name=add-answer] input[name=src]' ).val( src );
				$( 'form[name=add-answer] input[name=action]' ).val( 'polls_add_answer' );
				
				$( 'form[name=add-answer]' ).ajaxSubmit( function( response ) {
					delAnswerPrep( $( '#answers' ).append( response ).find( 'li:last' ) );
	            	$('#choices').append('<option value="' + (aa-1) + '">' + (aa-1) + '</option>');
	            	busy = false;
	            	init();
				} ); 
        	}            
            return false;
        });   
	    var win = window.dialogArguments || opener || parent || top;  
		$('.polldaddy-send-to-editor').click(function () {
            var pollID = $(this).parents('div.row-actions').find('.polldaddy-poll-id').val();
            if (!pollID) pollID = $('.polldaddy-poll-id:first').val();
            if (pollID){ 
            	pollID = parseInt(pollID);
            	if ( pollID > 0 ) {
            		win.send_to_editor('[polldaddy poll=' + pollID.toString() + ']');
            	}
            }	            	
        });  
        $('.polldaddy-show-shortcode').toggle(function (ev) {
            ev.preventDefault();
            $(this).parents('tr:first').next('tr').fadeIn();
            $(this).parents('tr:first').next('tr').show();
            $(this).closest('tr').css('display','none');
            
            return false;
        }, function () {
            $(this).parents('tr:first').next('tr').fadeOut();
            $(this).parents('tr:first').next('tr').hide();
            return false;
        });
        
        $('.pd-embed-done').click(function(ev){
        	ev.preventDefault();
			$( this ).closest('tr').hide();
			$( this ).closest('tr').prev('tr').show();        	
        
        });
                
        $( '.pd-tabs a' ).click( function(){
			if( !jQuery( this ).closest('li').hasClass( 'selected' ) ){

				jQuery( '.pd-tabs li' ).removeClass( 'selected' );
				jQuery( this ).closest( 'li' ).addClass( 'selected' );

				jQuery( '.pd-tab-panel' ).removeClass( 'show' );
				jQuery( '.pd-tab-panel#' + $( this ).closest( 'li' ).attr( 'id' ) + '-panel' ).addClass( 'show' );
			}
		} );
        var hiddenStyleID = $(':input[name=styleID]');
        var customStyle = $(':input[name=customSelect]');
        var customStyleVal = parseInt(customStyle.val());
        
        if (customStyleVal > 0) {
        	hiddenStyleID.val(customStyleVal.toString());
		    $( '#pd-custom-styles a' ).click();           
        } 

        $("#multipleChoice").click(function () {
            if ($("#multipleChoice").is(":checked")) {
                $("#numberChoices").show("fast");
            } else {
                $("#numberChoices").hide("fast");
            }
        });
        $('.block-repeat').click(function () {
            var repeat = jQuery(this).val();
            if (repeat == 'off') {
                $('#cookieip_expiration_label').hide();
                $('#cookieip_expiration').hide();
            } else {
                $('#cookieip_expiration_label').show();
                $('#cookieip_expiration').show();
            }
        });
        var uploading = false;
        function init() {
        	$('.image').unbind( 'click' ).click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_image', '');
				tb_show('Add an Image', 'media-upload.php?type=image&amp;&amp;polls_media=1TB_iframe=1');			
				win.send_to_editor = function(html) {
					var $h = $('<div/>').html(html);
			 		url = $h.find('img').attr('src');
					tb_remove();
			 		send_media( url, media_id );
				}
				return false;
			});
        	$('.video').unbind( 'click' ).click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_video', '');
				tb_show('Add Video', 'media-upload.php?type=video&amp;tab=type_url&amp;polls_media=1&amp;TB_iframe=1');			
				win.send_to_editor = function(shortcode) {			 		
			 		tb_remove();
			 		add_media( media_id, shortcode, '<img height="16" width="16" src="http://i0.poll.fm/images/icon-report-ip-analysis.png" alt="Video Embed">' );
				}
				return false;
			});
        	$('.audio').unbind( 'click' ).click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_audio', '');
				tb_show('Add Audio', 'media-upload.php?type=audio&amp;polls_media=1&amp;TB_iframe=1');			
				win.send_to_editor = function(html) {
					var $h = $('<div/>').html(html);
			 		url = $h.find('a').attr('href');			 		
			 		tb_remove();
			 		send_media( url, media_id );
				}
				return false;
			});
        }
        function send_media( url, media_id ) {
			if ( uploading == true )
				return false;
				
			uploading = true;
			$('input[name="media\[' + media_id + '\]"]').parents('td').find('.media-preview').addClass('st_image_loader');
			
			$( 'form[name=send-media] input[name=attach-id]' ).val( media_id );
			$( 'form[name=send-media] input[name=url]' ).val( url );
			$( 'form[name=send-media] input[name=action]' ).val( 'polls_upload_image' );
			
			$( 'form[name=send-media]' ).ajaxSubmit( function( response ) {
				uploading = false;
				response = response.replace( /<div.*/, '' );
				
				
				if ( response.substr( 0, 4 ) == 'true' ) {
					var parts = response.split( '||' );
									
					add_media( parts[4], parts[1], parts[2] );
				}
			} );
			
			return false;
		}
		function add_media( media_id, upload_id, img ) {
			if (parseInt(upload_id) > 0) $('input[name="mediaType\[' + media_id + '\]"]').val(1);
			else $('input[name="mediaType\[' + media_id + '\]"]').val(2);
			
			$('input[name="media\[' + media_id + '\]"]').val(upload_id);
			$('input[name="media\[' + media_id + '\]"]').parents('td.answer-media-icons').find('li.media-preview').removeClass('st_image_loader');
			$('input[name="media\[' + media_id + '\]"]').parents('td.answer-media-icons').find('li.media-preview').html(img);
		};
		function get_number_answers() {
            var num_answers = parseInt($('.answer').size());
            $('input.answer-text').each(function () {
                var item = this;
                if ($(item).val() == opts.new_answer || $(item).hasClass('idle')) num_answers--;
            });
            return num_answers;
        }
		
		init();
		
		var api = {
			add_media: add_media
		};
		
		return api;
    }
});
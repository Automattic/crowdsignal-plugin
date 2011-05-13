jQuery(function ($) {
    if (typeof(window.$) == 'undefined') { window.$ = jQuery; }
    Plugin = function (args) {
		var opts = $.extend( {
            delete_rating: 'Are you sure you want to delete the rating for "%s"?',
            delete_poll: 'Are you sure you want to delete "%s"?',
            delete_answer: 'Are you sure you want to delete this answer?',
            delete_answer_title: 'delete this answer',
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
            return confirm( opts.delete_rating.replace("%s", $(this).parents('td').find('strong').text() ) );
        });
        $('a.delete-poll').click(function () {
            return confirm( opts.delete_poll.replace("%s", $(this).parents('td').find('strong').text() ) );
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
        $('#add-answer-holder').show().find('button').click(function () {
            var aa = (1 + $('#answers li').size()).toString();
            var src = $( this ).closest( 'p' ).attr( 'class' );
            delAnswerPrep( $( '#answers' ).append( '<li><span class="handle" title="click and drag to reorder"><img src="' + src + 'img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span><div><input type="text" autocomplete="off" placeholder="Enter an answer here" value="" tabindex="2" size="30" name="answer[new' + aa + ']" /></div><a href="#" class="delete-answer delete" title="' + opts.delete_answer_title + '"><img src="' + src + 'img/icon-clear-search.png" /></a></li>' ).find( 'li:last' ) );
            //delAnswerPrep($('#answers').append('<li><span class="handle">&#x2195;</span><div><input type="text" name="answer[new' + aa + ']" size="30" tabindex="2" value="" autocomplete="off" /></div><a title="' + opts.delete_answer_title + '" class="delete-answer delete" href="#">&times;</a></li>').find('li:last'));
            $('#choices').append('<option value="' + aa + '">' + aa + '</option>');
            return false;
        });
        var win = window.dialogArguments || opener || parent || top;
        $('.polldaddy-send-to-editor').click(function () {
            var pollID = $(this).siblings('.polldaddy-poll-id').val();
            if (!pollID) pollID = $('.polldaddy-poll-id:first').val();
            win.send_to_editor('[polldaddy poll=' + parseInt(pollID).toString() + ']');
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
        var hiddenStyleID = $(':input[name=styleID]');
        var customStyle = $(':input[name=customSelect]');
        var customStyleVal = parseInt(customStyle.val());
        
        if (customStyleVal > 0) {
            $('#design_standard').hide();
            $('#design_custom').show();
            $('.polldaddy-show-design-options').html( opts.standard_styles );
            hiddenStyleID.val(customStyleVal.toString());
            $('.polldaddy-show-design-options').toggle(function () {
                $('#design_custom').hide();
                $('#design_standard').fadeIn();
                $('.polldaddy-show-design-options').html( opts.custom_styles );
                hiddenStyleID.val('x');
                return false;
            }, function () {
                $('#design_standard').hide();
                $('#design_custom').fadeIn();
                $('.polldaddy-show-design-options').html( opts.standard_styles );
                var customStyle = $(':input[name=customSelect]');
                var customStyleVal = parseInt(customStyle.val());
                if (customStyleVal > 0) {
                    hiddenStyleID.val(customStyleVal.toString());
                } else {
                    hiddenStyleID.val('x');
                }
                return false;
            });
        } else {
            $('#design_custom').hide();
            $('#design_standard').show();
            $('.polldaddy-show-design-options').toggle(function () {
                $('#design_standard').hide();
                $('#design_custom').fadeIn();
                $('.polldaddy-show-design-options').html( opts.standard_styles );
                var customStyle = $(':input[name=customSelect]');
                var customStyleVal = parseInt(customStyle.val());
                if (customStyleVal > 0) {
                    hiddenStyleID.val(customStyleVal.toString());
                } else {
                    hiddenStyleID.val('x');
                }
                return false;
            }, function () {
                $('#design_custom').hide();
                $('#design_standard').fadeIn();
                $('.polldaddy-show-design-options').html( opts.custom_styles );
                hiddenStyleID.val('x');
                return false;
            });
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
        	$('.image').click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_image', '');
				tb_show('Add an Image', 'media-upload.php?type=image&amp;&amp;polls_media=1TB_iframe=1');			
				win.send_to_editor = function(html) {
					var $h = $('<div/>').html(html);
			 		url = $h.find('img').attr('src');
					console.log( url );
			 		tb_remove();
			 		send_media( url, media_id );
				}
				return false;
			});
        	$('.video').click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_video', '');
				tb_show('Add Video', 'media-upload.php?type=video&amp;tab=type_url&amp;polls_media=1&amp;TB_iframe=1');			
				win.send_to_editor = function(shortcode) {
			 		console.log( media_id + '::' + shortcode );
			 		tb_remove();
			 		add_media( media_id, shortcode, '<img height="16" width="16" src="http://i0.poll.fm/images/icon-report-ip-analysis.png" alt="Video Embed">' );
				}
				return false;
			});
        	$('.audio').click(function() {
	        	var media_id = $( this ).attr('id').replace('add_poll_audio', '');
				tb_show('Add Audio', 'media-upload.php?type=audio&amp;polls_media=1&amp;TB_iframe=1');			
				win.send_to_editor = function(html) {
					var $h = $('<div/>').html(html);
			 		url = $h.find('a').attr('href');
			 		console.log( url );
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
			$('input[name="media\[' + media_id + '\]"]').parents('ul:first').find('.media-preview').addClass('st_image_loader');
			
			$( 'form[name=send-media] input[name=attach-id]' ).val( media_id );
			$( 'form[name=send-media] input[name=url]' ).val( url );
			$( 'form[name=send-media] input[name=action]' ).val( 'polls_upload_image' );
			
			$( 'form[name=send-media]' ).ajaxSubmit( function( response ) {
				uploading = false;
				response = response.replace( /<div.*/, '' );
				if ( response.substr( 0, 4 ) == 'true' ) {
					var parts = response.split( '||' );
					console.log( parts );				
					add_media( parts[4], parts[1], parts[2] );
				}
			} );
			
			return false;
		}
		function add_media( media_id, upload_id, img ) {
			if (parseInt(upload_id) > 0) $('input[name="mediaType\[' + media_id + '\]"]').val(1);
			else $('input[name="mediaType\[' + media_id + '\]"]').val(2);
			
			$('input[name="media\[' + media_id + '\]"]').val(upload_id);
			$('input[name="media\[' + media_id + '\]"]').parents('ul:first').find('.media-preview').removeClass('st_image_loader');
			$('input[name="media\[' + media_id + '\]"]').parents('ul:first').find('.media-preview').html(img);
		};
		
		init();
		
		var api = {
			add_media: add_media
		};
		
		return api;
    }
});







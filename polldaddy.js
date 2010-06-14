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
            delAnswerPrep($('#answers').append('<li><span class="handle">&#x2195;</span><div><input type="text" name="answer[new' + aa + ']" size="30" tabindex="2" value="" autocomplete="off" /></div><a title="' + opts.delete_answer_title + '" class="delete-answer delete" href="#">&times;</a></li>').find('li:last'));
            $('#choices').append('<option value="' + aa + '">' + aa + '</option>');
            return false;
        });
        var win = window.dialogArguments || opener || parent || top;
        $('.polldaddy-send-to-editor').click(function () {
            var pollID = $(this).siblings('.polldaddy-poll-id').val();
            if (!pollID) pollID = $('.polldaddy-poll-id:first').val();
            win.send_to_editor('[polldaddy poll=' + parseInt(pollID).toString() + ']');
        });
        $('.polldaddy-show-shortcode').toggle(function () {
            $(this).parents('tr:first').next('tr').fadeIn();
            $(this).parents('tr:first').next('tr').show();
            return false;
        }, function () {
            $(this).parents('tr:first').next('tr').fadeOut();
            $(this).parents('tr:first').next('tr').hide();
            return false;
        });
        var hiddenStyleID = $(':input[name=styleID]');
        var customStyle = $(':input[name=customSelect]');
        var customStyleVal = parseInt(customStyle.val());
        customStyle.change(function () {
            var customStyleVal = parseInt(customStyle.val());
            hiddenStyleID.val(customStyleVal.toString());
        });
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
    }
});
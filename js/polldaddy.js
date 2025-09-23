jQuery(function($) {
	if (typeof window.$ == "undefined") {
		window.$ = jQuery;
	}

	Plugin = function(options) {
		var settings = $.extend({
			delete_rating: 'Are you sure you want to delete the rating for "%s"?',
			delete_poll: 'Are you sure you want to delete "%s"?',
			delete_answer: "Are you sure you want to delete this answer?",
			new_answer: "Enter an answer here",
			delete_answer_title: "delete this answer",
			reorder_answer_title: "click and drag to reorder",
			add_image_title: "Add an Image",
			add_audio_title: "Add Audio",
			add_video_title: "Add Video",
			standard_styles: "Standard Styles",
			custom_styles: "Custom Styles",
			base_url: ""
		}, options);

		function isSSL() {
			return "https:" == document.location.protocol ? true : false;
		}

		function getSecureMediaURL(type, mediaType) {
			var baseURL = "media-upload.php?type=" + type + "&polls_media=1";

			// Add nonce for CSRF protection if available
			if (typeof pollsMediaSecurity !== 'undefined' && pollsMediaSecurity.nonce) {
				baseURL += "&_wpnonce=" + encodeURIComponent(pollsMediaSecurity.nonce);
			}

			if (mediaType) {
				baseURL += "&tab=" + mediaType;
			}

			return baseURL + "&TB_iframe=1";
		}

		function resizeThickbox() {
			var bodyWidth = jQuery("body", window.parent.document).width();
			var bodyHeight = jQuery("body", window.parent.document).height();
			var hasMaxHeight = typeof document.body.style.maxHeight === "undefined";

			jQuery("#TB_window, #TB_iframeContent", window.parent.document).css("width", "855px");
			jQuery("#TB_window", window.parent.document).css({
				left: (bodyWidth - 768) / 2 + "px",
				top: 48 + window.parent.scrollY + "px",
				position: "absolute",
				marginLeft: "0"
			});

			if (!hasMaxHeight) {
				jQuery("#TB_window, #TB_iframeContent", window.parent.document).css("height", bodyHeight - 73 + "px");
			}
		}

		function initMediaHandlers() {
			resizeThickbox();
			jQuery(window).resize(function() {
				setTimeout(resizeThickbox, 50);
			});

			// Delete media handlers
			$("a.delete-media").unbind("click").click(function() {
				var mediaContainer = $(this).parents("td.answer-media-icons");
				mediaContainer.find("li.image-added").removeClass("image-added").html("");
				mediaContainer.find(":hidden").val("");
			});

			// Media hover effects
			$("td.answer-media-icons li.image-added").unbind("mouseover").mouseover(function() {
				$(this).find("img").addClass("hidden");
				$(this).find("a.delete-media img").removeClass("hidden");
				$(this).find("a.delete-media").removeClass("hidden");
			}).unbind("mouseout").mouseout(function() {
				$(this).find("a.delete-media").addClass("hidden");
				$(this).find("img").removeClass("hidden");
			});

			// Image upload handler
			$(".image").unbind("click").click(function() {
				var answerId = $(this).attr("id").replace("add_poll_image", "");
				var secureURL = getSecureMediaURL("image");

				tb_show("Add an Image", secureURL);

				var parent = window.dialogArguments || opener || parent || top;
				parent.send_to_editor = function(html) {
					var container = $("<div/>").html(html);
					var img = container.find("img");
					var attachId = 0;
					var url = img.attr("src");

					if (isSSL()) {
						url = url.replace("http://", "https://");
					}

					var match = img.attr("class").match(/wp-image-(\d+)/);
					if ($.isArray(match) && match[1] !== undefined) {
						attachId = match[1];
					}

					tb_remove();
					uploadMedia(url, answerId, attachId);
				};
				return false;
			});

			// Video upload handler
			$(".video").unbind("click").click(function() {
				var answerId = $(this).attr("id").replace("add_poll_video", "");
				var secureURL = getSecureMediaURL("video", "type_url");

				tb_show("Add Video", secureURL);

				var parent = window.dialogArguments || opener || parent || top;
				parent.send_to_editor = function(content) {
					tb_remove();
					addMediaContent(answerId, content, '<img height="16" width="16" src="' + settings.base_url + 'img/icon-report-ip-analysis.png" alt="Video Embed">');
				};
				return false;
			});

			// Audio upload handler
			$(".audio").unbind("click").click(function() {
				var answerId = $(this).attr("id").replace("add_poll_audio", "");
				var secureURL = getSecureMediaURL("audio");

				tb_show("Add Audio", secureURL);

				var parent = window.dialogArguments || opener || parent || top;
				parent.send_to_editor = function(html) {
					var container = $("<div/>").html(html);
					var img = container.find("img");
					var attachId = 0;
					var url = img.attr("src");

					if (isSSL()) {
						url = url.replace("http://", "https://");
					}

					var match = img.attr("class").match(/wp-image-(\d+)/);
					if ($.isArray(match) && match[1] !== undefined) {
						attachId = match[1];
					}

					tb_remove();
					uploadMedia(url, answerId, attachId);
				};
				return false;
			});
		}

		var isUploading = false;

		function uploadMedia(url, answerId, attachId) {
			if (isUploading === true) return false;
			isUploading = true;

			$('input[name="media[' + answerId + ']"]').parents("td").find(".media-preview").addClass("st_image_loader");
			$("form[name=send-media] input[name=media-id]").val(answerId);
			$("form[name=send-media] input[name=attach-id]").val(attachId);
			$("form[name=send-media] input[name=url]").val(url);
			$("form[name=send-media] input[name=action]").val("polls_upload_image");

			$("form[name=send-media]").ajaxSubmit(function(response) {
				isUploading = false;
				response = response.replace(/<div.*/, "");

				if (response.substr(0, 4) == "true") {
					var parts = response.split("||");
					addMediaContent(parts[4], parts[1], parts[2]);
				} else {
					addMediaContent(answerId, "", "");
				}
			});
			return false;
		}

		function addMediaContent(answerId, content, preview) {
			if (parseInt(content) > 0) {
				$('input[name="mediaType[' + answerId + ']"]').val(1);
			} else {
				$('input[name="mediaType[' + answerId + ']"]').val(2);
			}

			if (isSSL()) {
				preview = preview.replace("http://", "https://");
			}

			var deleteLink = $("div.hidden-links").find("div.delete-media-link").html();
			preview += deleteLink;

			var mediaPreview = $('input[name="media[' + answerId + ']"]').parents("td.answer-media-icons").find("li.media-preview");
			mediaPreview.removeClass("st_image_loader");
			mediaPreview.html(preview);
			mediaPreview.addClass("image-added");
			$('input[name="media[' + answerId + ']"]').val(content);

			initMediaHandlers();
		}

		function countAnswers() {
			var total = parseInt($(".answer").size());
			$("input.answer-text").each(function() {
				var input = this;
				if ($(input).val() == settings.new_answer || $(input).hasClass("idle")) {
					total--;
				}
			});
			return total;
		}

		// Initialize UI elements
		$(".hide-if-js").hide();
		$(".empty-if-js").empty();
		$(".hide-if-no-js").removeClass("hide-if-no-js");

		// Shortcode selection
		$(".polldaddy-shortcode-row pre").click(function() {
			var element = $(this)[0];
			if ($.browser.msie) {
				var range = document.body.createTextRange();
				range.moveToElementText(element);
				range.select();
			} else if ($.browser.mozilla || $.browser.opera) {
				var selection = window.getSelection();
				var range = document.createRange();
				range.selectNodeContents(element);
				selection.removeAllRanges();
				selection.addRange(range);
			} else if ($.browser.safari) {
				var selection = window.getSelection();
				selection.setBaseAndExtent(element, 0, element, 1);
			}
		});

		$("input#shortcode-field").click(function() {
			$(this).select();
		});

		// Confirmation dialogs
		$("a.delete-rating").click(function() {
			return confirm(settings.delete_rating.replace("%s", $(this).parents("td").find("strong").text()));
		});

		$("a.delete-poll").click(function() {
			return confirm(settings.delete_poll.replace("%s", $(this).parents("td").find("strong").text()));
		});

		// Thickbox iframe adjustments
		$("span.view a.thickbox").attr("href", function() {
			return $(this).attr("href") + "&iframe&TB_iframe=true";
		});

		// Answer management
		var addAnswerHandler = function(container) {
			$("a.delete-answer", container || null).click(function() {
				if (confirm(settings.delete_answer)) {
					$(this).parents("li").remove();
					$("#choices option:last-child").remove();
				}
				return false;
			});
		};

		addAnswerHandler();

		// Sortable answers
		$("#answers").sortable({
			axis: "y",
			containment: "parent",
			handle: ".handle",
			tolerance: "pointer"
		});

		// Add answer functionality
		var isAddingAnswer = false;
		$("#add-answer-holder").show().find("button").click(function() {
			if (!isAddingAnswer) {
				isAddingAnswer = true;
				var answerNumber = (1 + countAnswers()).toString();
				var sourceClass = $(this).closest("p").attr("class");

				$("form[name=add-answer] input[name=aa]").val(answerNumber);
				$("form[name=add-answer] input[name=src]").val(sourceClass);
				$("form[name=add-answer] input[name=action]").val("polls_add_answer");

				$("form[name=add-answer]").ajaxSubmit(function(response) {
					addAnswerHandler($("#answers").append(response).find("li:last"));
					$("#choices").append('<option value="' + (answerNumber - 1) + '">' + (answerNumber - 1) + "</option>");
					isAddingAnswer = false;
					initMediaHandlers();
				});
			}
			return false;
		});

		// Editor integration
		var parentWindow = window.dialogArguments || opener || parent || top;
		$(".polldaddy-send-to-editor").click(function() {
			var pollId = $(this).parents("div.row-actions").find(".polldaddy-poll-id").val();
			if (!pollId) {
				pollId = $(".polldaddy-poll-id:first").val();
			}
			if (pollId) {
				pollId = parseInt(pollId);
				if (pollId > 0) {
					parentWindow.send_to_editor("[polldaddy poll=" + pollId.toString() + "]");
				}
			}
		});

		// Shortcode display toggle
		$(".polldaddy-show-shortcode").toggle(function(e) {
			e.preventDefault();
			$(this).parents("tr:first").next("tr").fadeIn();
			$(this).parents("tr:first").next("tr").show();
			$(this).closest("tr").css("display", "none");
			return false;
		}, function() {
			$(this).parents("tr:first").next("tr").fadeOut();
			$(this).parents("tr:first").next("tr").hide();
			return false;
		});

		$(".pd-embed-done").click(function(e) {
			e.preventDefault();
			$(this).closest("tr").hide();
			$(this).closest("tr").prev("tr").show();
		});

		// Tab functionality
		$(".pd-tabs a").click(function() {
			if (!jQuery(this).closest("li").hasClass("selected")) {
				jQuery(".pd-tabs li").removeClass("selected");
				jQuery(this).closest("li").addClass("selected");
				jQuery(".pd-tab-panel").removeClass("show");
				jQuery(".pd-tab-panel#" + $(this).closest("li").attr("id") + "-panel").addClass("show");
			}
		});

		// Style handling
		var styleField = $(":input[name=styleID]");
		var customField = $(":input[name=customSelect]");
		var customValue = parseInt(customField.val());

		if (customValue > 0) {
			styleField.val(customValue.toString());
			$("#pd-custom-styles a").click();
		}

		// Multiple choice handling
		$("#multipleChoice").click(function() {
			if ($("#multipleChoice").is(":checked")) {
				$("#numberChoices").show("fast");
			} else {
				$("#numberChoices").hide("fast");
			}
		});

		// Block repeat voting
		$(".block-repeat").click(function() {
			var value = jQuery(this).val();
			if (value == "off") {
				$("#cookieip_expiration_label").hide();
				$("#cookieip_expiration").hide();
			} else {
				$("#cookieip_expiration_label").show();
				$("#cookieip_expiration").show();
			}
		});

		// Initialize media handlers
		initMediaHandlers();

		// Return public interface
		return {
			add_media: addMediaContent
		};
	};
});
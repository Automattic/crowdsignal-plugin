<?php // phpcs:ignore NeutronStandard.StrictTypes.RequireStrictTypes.StrictTypes -- we're not ready yet
/**
 * Crowdsignal legacy plugin
 *
 * @package crowdsignal
 */
?>

<?php
// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- vars coming from view renderer
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- legacy class, won't fix
// phpcs:disable NeutronStandard.Arrays.DisallowLongformArray.LongformArray -- TODO
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment -- TODO

$delete_media_link = '<a href="#" class="delete-media delete hidden" title="' . esc_attr( __( 'delete this image' ) ) . '"><img src="' . esc_url( $base_url ) . 'img/icon-clear-search.png" width="16" height="16" /></a>';
?>
<form enctype="multipart/form-data" name="send-media" action="admin-ajax.php" method="post">
	<?php wp_nonce_field( 'send-media' ); ?>
	<input type="hidden" value="" name="action">
	<input type="hidden" value="<?php echo esc_attr( $controller->user_code ); ?>" name="uc">
	<input type="hidden" value="" name="attach-id">
	<input type="hidden" value="" name="media-id">
	<input type="hidden" value="" name="url">
</form>

<form name="add-answer" action="admin-ajax.php" method="post">
	<?php wp_nonce_field( 'add-answer' ); ?>
	<input type="hidden" value="" name="action">
	<input type="hidden" value="" name="aa">
	<input type="hidden" value="" name="src">
	<input type="hidden" value="<?php echo isset( $_GET['iframe'] ) ? '1' : '0'; ?>" name="popup">
</form>

<form action="" method="post">
	<div id="poststuff">
		<div id="post-body" class="has-sidebar has-right-sidebar">

			<div class="inner-sidebar" id="side-info-column">
				<div id="submitdiv" class="postbox">
					<h2 class="postbox-title"><?php _e( 'Save', 'polldaddy' ); ?></h2>
					<div class="inside">
					<div class="minor-publishing">
						<ul id="answer-options">

							<?php
							$poll_options = [
								'randomiseAnswers' => __( 'Randomize answer order', 'polldaddy' ),
								'otherAnswer' => __( 'Allow other answers', 'polldaddy' ),
								'multipleChoice' => __( 'Multiple choice', 'polldaddy' ),
								'sharing' => __( 'Sharing', 'polldaddy' ),
							];
							foreach ( $poll_options as $option => $label ) :
								if ( $is_post ) {
									$checked = isset( $_POST[ $option ] ) && 'yes' === $_POST[ $option ] ? ' checked="checked"' : '';
								} else {
									$checked = 'yes' === $poll->$option ? ' checked="checked"' : '';
								}
								?>

								<li>
									<label for="<?php echo esc_attr( $option ); ?>">
										<input type="checkbox"<?php echo esc_attr( $checked ); ?> value="yes" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" /> <?php echo esc_html( $label ); ?>
									</label>
								</li>

							<?php endforeach; ?>
						</ul>
						<?php
						if ( $is_post ) {
							$style = isset( $_POST['multipleChoice'] ) && 'yes' === $_POST['multipleChoice'] ? 'display:block;' : 'display:none;';
						} else {
							$style = 'yes' === $poll->multipleChoice ? 'display:block;' : 'display:none;';
						}
						?>
						<div id="numberChoices" name="numberChoices" style="padding-left:15px;<?php echo esc_attr( $style ); ?>">
							<p>
								<?php _e( 'Number of choices', 'polldaddy' ); ?>:
								<select name="choices" id="choices">
									<option value="1"><?php _e( 'No Limit', 'polldaddy' ); ?></option>
									<?php
									if ( $is_post && isset( $_POST['choices'] ) ) {
										$choices = (int) $_POST['choices'];
									} else {
										$choices = (int) $poll->choices;
									}

									$a = count( $answers ) - 1;

									if ( $a > 1 ) :
										for ( $i = 2; $i <= $a; $i++ ) :
											$selected = $i === $choices ? 'selected="selected"' : '';
											printf( "<option value='%d' %s>%d</option>", $i, $selected, $i );
										endfor;
									endif;
									?>
								</select>
							</p>
						</div>
					</div>
					<div id="major-publishing-actions">
						<div id="publishing-action">
							<?php wp_nonce_field( $poll_id ? "edit-poll_$poll_id" : 'create-poll' ); ?>
							<input type="hidden" name="action" value="<?php echo $poll_id ? 'edit-poll' : 'create-poll'; ?>" />
							<input type="hidden" class="polldaddy-poll-id" name="poll" value="<?php echo esc_attr( $poll_id ); ?>" />
							<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save Poll', 'polldaddy' ) ); ?>" />

							<?php if ( isset( $_GET['iframe'] ) && $poll_id ) : ?>
								<div id="delete-action">
									<input type="button" class="button polldaddy-send-to-editor" style="margin-top:8px;" value="<?php echo esc_attr( __( 'Embed in Post', 'polldaddy' ) ); ?>" />
								</div>
							<?php endif; ?>

						</div>
						<br class="clear" />
					</div>
				</div>
			</div>

			<div class="postbox">
				<h2 class="postbox-title"><?php _e( 'Results Display', 'polldaddy' ); ?></h2>
				<div class="inside">
					<ul class="poll-options">

						<?php
						$result_options = [
							'show' => __( 'Show results to voters', 'polldaddy' ),
							'percent' => __( 'Only show percentages', 'polldaddy' ),
							'hide' => __( 'Hide all results', 'polldaddy' ),
						];
						foreach ( $result_options as $value => $label ) :
							if ( $is_post ) {
								$checked = $value === $_POST['resultsType'] ? ' checked="checked"' : '';
							} else {
								$checked = $value === $poll->resultsType ? ' checked="checked"' : '';
							}
							?>

							<li>
								<label for="resultsType-<?php echo esc_attr( $value ); ?>">
									<input type="radio"<?php echo esc_attr( $checked ); ?> value="<?php echo esc_attr( $value ); ?>" name="resultsType" id="resultsType-<?php echo esc_attr( $value ); ?>" /> <?php echo esc_html( $label ); ?>
								</label>
							</li>

						<?php endforeach; ?>

					</ul>
				</div>
			</div>

			<div class="postbox">
				<h2 class="postbox-title"><?php _e( 'Repeat Voting', 'polldaddy' ); ?></h2>
				<div class="inside">
					<ul class="poll-options">

						<?php
						$vote_options = [
							'off' => __( "Don't block repeat voters", 'polldaddy' ),
							'cookie' => __( 'Block by cookie (recommended)', 'polldaddy' ),
							'cookieip' => __( 'Block by cookie and by IP address', 'polldaddy' ),
						];
						foreach ( $vote_options as $value => $label ) :
							if ( $is_post ) {
								$checked = $value === $_POST['blockRepeatVotersType'] ? ' checked="checked"' : '';
							} else {
								$checked = $value === $poll->blockRepeatVotersType ? ' checked="checked"' : '';
							}
							?>

							<li>
								<label for="blockRepeatVotersType-<?php echo esc_attr( $value ); ?>">
									<input class="block-repeat" type="radio"<?php echo esc_attr( $checked ); ?> value="<?php echo esc_attr( $value ); ?>" name="blockRepeatVotersType" id="blockRepeatVotersType-<?php echo esc_attr( $value ); ?>" /> <?php echo esc_html( $label ); ?>
								</label>
							</li>

						<?php endforeach; ?>

					</ul>

					<?php
					if ( (int) $poll->blockExpiration === 0 || $poll->blockExpiration > 604800 ) {
						$poll->blockExpiration = 604800;
					}
					?>
					<span style="margin:6px 6px 8px;<?php echo $poll->blockRepeatVotersType === 'off' ? 'display:none;' : ''; ?>" id="cookieip_expiration_label"><label><?php _e( 'Expires: ', 'polldaddy' ); ?></label></span>
					<select id="cookieip_expiration" name="cookieip_expiration" style="width: auto;<?php echo $poll->blockRepeatVotersType === 'off' ? 'display:none;' : ''; ?>">
						<option value="3600" <?php echo (int) $poll->blockExpiration === 3600 ? 'selected' : ''; ?>><?php printf( __( '%d hour', 'polldaddy' ), 1 ); ?></option>
						<option value="10800" <?php echo (int) $poll->blockExpiration === 10800 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 3 ); ?></option>
						<option value="21600" <?php echo (int) $poll->blockExpiration === 21600 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 6 ); ?></option>
						<option value="43200" <?php echo (int) $poll->blockExpiration === 43200 ? 'selected' : ''; ?>><?php printf( __( '%d hours', 'polldaddy' ), 12 ); ?></option>
						<option value="86400" <?php echo (int) $poll->blockExpiration === 86400 ? 'selected' : ''; ?>><?php printf( __( '%d day', 'polldaddy' ), 1 ); ?></option>
						<option value="604800" <?php echo (int) $poll->blockExpiration === 604800 ? 'selected' : ''; ?>><?php printf( __( '%d week', 'polldaddy' ), 1 ); ?></option>
					</select>
					<p><?php _e( 'Note: Blocking by cookie and IP address can be problematic for some voters.', 'polldaddy' ); ?></p>
				</div>
			</div>

			<div class="postbox">
				<h2 class="postbox-title"><?php _e( 'Comments', 'polldaddy' ); ?></h2>
				<div class="inside">
					<ul class="poll-options">

						<?php
						$comment_options = [
							'allow' => __( 'Allow comments', 'polldaddy' ),
							'moderate' => __( 'Moderate first', 'polldaddy' ),
							'off' => __( 'No comments', 'polldaddy' ),
						];
						foreach ( $comment_options as $value => $label ) :
							if ( $is_post ) {
								$checked = $value === $_POST['comments'] ? ' checked="checked"' : '';
							} else {
								$checked = $value === $poll->comments->___content ? ' checked="checked"' : '';
							}
							?>

							<li>
								<label for="comments-<?php echo esc_attr( $value ); ?>">
									<input type="radio"<?php echo esc_attr( $checked ); ?> value="<?php echo esc_attr( $value ); ?>" name="comments" id="comments-<?php echo esc_attr( $value ); ?>" /> <?php echo esc_html( $label ); ?>
								</label>
							</li>

						<?php endforeach; ?>

					</ul>
				</div>
			</div>
		</div>

		<div id="post-body-content" class="has-sidebar-content">

			<div id="titlediv" style="margin-top:0px;">
				<div id="titlewrap">

					<table class="question">

						<tr>
							<td class="question-input">
								<input type="text" autocomplete="off" id="title" placeholder="<?php _e( 'Enter Question Here', 'polldaddy' ); ?>" value="<?php echo esc_attr( $question ); ?>" tabindex="1" size="30" name="question" />
							</td>
							<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width: 55px !important;"' : ''; ?>>
								<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : ''; ?>>
									<?php if ( isset( $media_type[999999999] ) && (int) $media_type[999999999] === 2 ) { ?>
										<li class="media-preview image-added" style="width: 20px; height: 16px; padding-left: 5px;"><img height="16" width="16" src="<?php echo esc_url( $base_url ); ?>img/icon-report-ip-analysis.png" alt="Video Embed"><?php echo $delete_media_link; ?></li>
										<?php
									} else {
										$url = '';
										if ( isset( $media[999999999] ) ) {
											$url = urldecode( $media[999999999]->img_small );

											if ( is_ssl() ) {
												$url = preg_replace( '/http\:/', 'https:', $url );
											}
										}
										?>
										<li class="media-preview <?php echo ! empty( $url ) ? 'image-added' : ''; ?>" style="width: 20px; height: 16px; padding-left: 5px;">
											<?php echo esc_html( $url ); ?><?php echo $delete_media_link; ?>
										</li>
										<?php
									}

									if ( ! isset( $_GET['iframe'] ) ) :
										?>
										<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></li>
										<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></li>
										<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio999999999" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
									<?php endif; ?>
								</ul>

								<input type="hidden" value="<?php echo isset( $media[999999999] ) ? esc_attr( $media[999999999]->_id ) : ''; ?>" id="hMC999999999" name="media[999999999]">
								<input type="hidden" value="<?php echo isset( $media_type[999999999] ) ? intval( $media_type[999999999] ) : ''; ?>" id="hMT999999999" name="mediaType[999999999]">

							</td>
						</tr>
					</table>

					<?php if ( isset( $poll->_id ) && ! isset( $_GET['iframe'] ) ) : ?>
						<div class="inside">
							<div id="edit-slug-box" style="margin-bottom:30px;">
								<strong><?php _e( 'WordPress Shortcode:', 'polldaddy' ); ?></strong>
								<input type="text" style="color:#999;" value="[crowdsignal poll=<?php echo esc_attr( $poll->_id ); ?>]" id="shortcode-field" readonly="readonly" />
								<span><a href="post-new.php?content=[crowdsignal poll=<?php echo esc_attr( $poll->_id ); ?>]" class="button"><?php _e( 'Embed Poll in New Post' ); ?></a></span>
							</div>
						</div>
					<?php endif; ?>

				</div>
			</div>

			<div id="answersdiv" class="postbox">
				<h2 class="postbox-title"><?php _e( 'Answers', 'polldaddy' ); ?></h2>

				<div id="answerswrap" class="inside">
				<ul id="answers">
					<?php
					$a = 0;
					foreach ( $answers as $answer_id => $answer ) :
						$a++;
						$query_args = [
							'action' => 'delete-answer',
							'poll' => $poll_id,
							'answer' => $answer_id,
							'message' => false,
						];
						$delete_link = esc_url( wp_nonce_url( add_query_arg( $query_args ), "delete-answer_$answer_id" ) );
						?>
						<li>
							<table class="answer">
								<tr>
									<th>
										<span class="handle" title="<?php echo esc_attr( __( 'click and drag to reorder' ) ); ?>"><img src="<?php echo esc_url( $base_url ); ?>img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span>
									</th>
									<td class="answer-input">
										<input type="text" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>" id="answer-<?php echo esc_attr( $answer_id ); ?>" value="<?php echo esc_attr( $answer ); ?>" tabindex="2" size="30" name="answer[<?php echo esc_attr( $answer_id ); ?>]" />
									</td>
									<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width: 55px !important;"' : ''; ?>>
										<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : ''; ?>>
											<?php if ( isset( $media_type[ $answer_id ] ) && intval( $media_type[ $answer_id ] ) === 2 ) { ?>
												<li class="media-preview image-added" style="width: 20px; height: 16px; padding-left: 5px;">
													<img height="16" width="16" src="<?php echo esc_url( $base_url ); ?>img/icon-report-ip-analysis.png" alt="Video Embed" />
													<?php echo $delete_media_link; ?>
												</li>
												<?php
											} else {
												$url = '';
												if ( isset( $media[ $answer_id ] ) ) {
													$url = urldecode( $media[ $answer_id ]->img_small );

													if ( is_ssl() ) {
														$url = preg_replace( '/http\:/', 'https:', $url );
													}
												}
												?>
												<li class="media-preview <?php echo ! empty( $url ) ? 'image-added' : ''; ?>" style="width: 20px; height: 16px; padding-left: 5px;">
													<?php echo esc_html( $url ); ?><?php echo $delete_media_link; ?>
												</li>
												<?php
											}

											if ( ! isset( $_GET['iframe'] ) ) :
												?>
												<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image<?php echo esc_attr( $answer_id ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></li>
												<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video<?php echo esc_attr( $answer_id ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></li>
												<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio<?php echo esc_attr( $answer_id ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
											<?php endif; ?>
											<li>
												<a href="<?php echo esc_url( $delete_link ); ?>" class="delete-answer delete" title="<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>">
													<img src="<?php echo esc_url( $base_url ); ?>img/icon-clear-search.png" width="16" height="16" />
												</a>
											</li>

										</ul>

										<input type="hidden" value="<?php echo isset( $media[ $answer_id ] ) ? esc_attr( $media[ $answer_id ]->_id ) : ''; ?>" id="hMC<?php echo esc_attr( $answer_id ); ?>" name="media[<?php echo esc_attr( $answer_id ); ?>]">
										<input type="hidden" value="<?php echo isset( $media_type[ $answer_id ] ) ? esc_attr( $media_type[ $answer_id ] ) : ''; ?>" id="hMT<?php echo esc_attr( $answer_id ); ?>" name="mediaType[<?php echo esc_attr( $answer_id ); ?>]">

									</td>
								</tr>
							</table>

						</li>

						<?php
					endforeach;

					while ( 3 - $a > 0 ) :
						$a++;
						?>

						<li>
							<table class="answer">
									<tr>
										<th>
											<span class="handle" title="<?php echo esc_attr( __( 'click and drag to reorder' ) ); ?>"><img src="<?php echo esc_url( $base_url ); ?>img/icon-reorder.png" alt="click and drag to reorder" width="6" height="9" /></span>
										</th>
										<td class="answer-input">
											<input type="text" autocomplete="off" placeholder="<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>" value="" tabindex="2" size="30" name="answer[new<?php echo esc_attr( $a ); ?>]" />
										</td>
										<td class="answer-media-icons" <?php echo isset( $_GET['iframe'] ) ? 'style="width:55px !important;"' : ''; ?>>
											<ul class="answer-media" <?php echo isset( $_GET['iframe'] ) ? 'style="min-width: 30px;"' : ''; ?>>
												<li class="media-preview" style="width: 20px; height: 16px; padding-left: 5px;"></li>
												<?php if ( ! isset( $_GET['iframe'] ) ) : ?>
													<li><a title="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" class="thickbox media image" id="add_poll_image<?php echo esc_attr( $a ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>" src="images/media-button-image.gif"></a></a></li>
													<li><a title="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" class="thickbox media video" id="add_poll_video<?php echo esc_attr( $a ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>" src="images/media-button-video.gif"></a></a></li>
													<li><a title="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" class="thickbox media audio" id="add_poll_audio<?php echo esc_attr( $a ); ?>" href="#"><img style="vertical-align:middle;" alt="<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>" src="images/media-button-music.gif"></a></li>
												<?php endif; ?>
												<li><a href="#" class="delete-answer delete" title="<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>"><img src="<?php echo esc_url( $base_url ); ?>img/icon-clear-search.png" width="16" height="16" /></a></li>
											</ul>

											<input type="hidden" value="" id="hMC<?php echo esc_attr( $a ); ?>" name="media[<?php echo esc_attr( $a ); ?>]">
											<input type="hidden" value="" id="hMT<?php echo esc_attr( $a ); ?>" name="mediaType[<?php echo esc_attr( $a ); ?>]">

										</td>
									</tr>
							</table>
						</li>

						<?php
					endwhile;
					?>

				</ul>

				<p id="add-answer-holder" class="<?php echo esc_attr( $base_url ); ?>">
					<button class="button"><?php echo esc_html( __( 'Add New Answer', 'polldaddy' ) ); ?></button>
				</p>

				</div>
			</div>

			<div class="hidden-links">
				<div class="delete-media-link"><?php echo $delete_media_link; ?></div>
			</div>

			<div id="design" class="postbox">

				<?php
				$style_id = (int) ( $is_post ? $_POST['styleID'] : $poll->styleID );

				$iframe_view = false;
				if ( isset( $_GET['iframe'] ) ) {
					$iframe_view = true;
				}

				$preset_styles = [
					101 => __( 'Aluminum Narrow', 'polldaddy' ),
					102 => __( 'Aluminum Medium', 'polldaddy' ),
					103 => __( 'Aluminum Wide', 'polldaddy' ),
					104 => __( 'Plain White Narrow', 'polldaddy' ),
					105 => __( 'Plain White Medium', 'polldaddy' ),
					106 => __( 'Plain White Wide', 'polldaddy' ),
					107 => __( 'Plain Black Narrow', 'polldaddy' ),
					108 => __( 'Plain Black Medium', 'polldaddy' ),
					109 => __( 'Plain Black Wide', 'polldaddy' ),
					110 => __( 'Paper Narrow', 'polldaddy' ),
					111 => __( 'Paper Medium', 'polldaddy' ),
					112 => __( 'Paper Wide', 'polldaddy' ),
					113 => __( 'Skull Dark Narrow', 'polldaddy' ),
					114 => __( 'Skull Dark Medium', 'polldaddy' ),
					115 => __( 'Skull Dark Wide', 'polldaddy' ),
					116 => __( 'Skull Light Narrow', 'polldaddy' ),
					117 => __( 'Skull Light Medium', 'polldaddy' ),
					118 => __( 'Skull Light Wide', 'polldaddy' ),
					157 => __( 'Micro', 'polldaddy' ),
					119 => __( 'Plastic White Narrow', 'polldaddy' ),
					120 => __( 'Plastic White Medium', 'polldaddy' ),
					121 => __( 'Plastic White Wide', 'polldaddy' ),
					122 => __( 'Plastic Grey Narrow', 'polldaddy' ),
					123 => __( 'Plastic Grey Medium', 'polldaddy' ),
					124 => __( 'Plastic Grey Wide', 'polldaddy' ),
					125 => __( 'Plastic Black Narrow', 'polldaddy' ),
					126 => __( 'Plastic Black Medium', 'polldaddy' ),
					127 => __( 'Plastic Black Wide', 'polldaddy' ),
					128 => __( 'Manga Narrow', 'polldaddy' ),
					129 => __( 'Manga Medium', 'polldaddy' ),
					130 => __( 'Manga Wide', 'polldaddy' ),
					131 => __( 'Tech Dark Narrow', 'polldaddy' ),
					132 => __( 'Tech Dark Medium', 'polldaddy' ),
					133 => __( 'Tech Dark Wide', 'polldaddy' ),
					134 => __( 'Tech Grey Narrow', 'polldaddy' ),
					135 => __( 'Tech Grey Medium', 'polldaddy' ),
					136 => __( 'Tech Grey Wide', 'polldaddy' ),
					137 => __( 'Tech Light Narrow', 'polldaddy' ),
					138 => __( 'Tech Light Medium', 'polldaddy' ),
					139 => __( 'Tech Light Wide', 'polldaddy' ),
					140 => __( 'Working Male Narrow', 'polldaddy' ),
					141 => __( 'Working Male Medium', 'polldaddy' ),
					142 => __( 'Working Male Wide', 'polldaddy' ),
					143 => __( 'Working Female Narrow', 'polldaddy' ),
					144 => __( 'Working Female Medium', 'polldaddy' ),
					145 => __( 'Working Female Wide', 'polldaddy' ),
					146 => __( 'Thinking Male Narrow', 'polldaddy' ),
					147 => __( 'Thinking Male Medium', 'polldaddy' ),
					148 => __( 'Thinking Male Wide', 'polldaddy' ),
					149 => __( 'Thinking Female Narrow', 'polldaddy' ),
					150 => __( 'Thinking Female Medium', 'polldaddy' ),
					151 => __( 'Thinking Female Wide', 'polldaddy' ),
					152 => __( 'Sunset Narrow', 'polldaddy' ),
					153 => __( 'Sunset Medium', 'polldaddy' ),
					154 => __( 'Sunset Wide', 'polldaddy' ),
					155 => __( 'Music Medium', 'polldaddy' ),
					156 => __( 'Music Wide', 'polldaddy' ),
				];

				$polldaddy->reset();
				$styles = $polldaddy->get_styles();

				$show_custom = false;
				if ( ! empty( $styles ) && ! empty( $styles->style ) && count( $styles->style ) > 0 ) {
					foreach ( (array) $styles->style as $style ) {
						$preset_styles[ (int) $style->_id ] = $style->title;
					}
					$show_custom = true;
				}

				if ( $style_id > 18 ) {
					$standard_style_id = 0;
					$custom_style_id = $style_id;
				} else {
					$standard_style_id = $style_id;
					$custom_style_id = 0;
				}
				?>
				<h2 class="postbox-title"><?php _e( 'Poll Style', 'polldaddy' ); ?></h2>
				<input type="hidden" name="styleID" id="styleID" value="<?php echo esc_attr( $style_id ); ?>">
				<div class="inside">

					<ul class="pd-tabs">
						<li class="selected" id="pd-styles"><a href="#"><?php _e( 'Crowdsignal Styles', 'polldaddy' ); ?></a><input type="checkbox" style="display:none;" id="regular"/></li>
						<?php $hide = (bool) $show_custom === true ? ' style="display:block;"' : ' style="display:none;"'; ?>
						<li id="pd-custom-styles" <?php echo $hide; ?>><a href="#"><?php _e( 'Custom Styles', 'polldaddy' ); ?></a><input type="checkbox" style="display:none;" id="custom"/></li>

					</ul>

					<div class="pd-tab-panel show" id="pd-styles-panel">
						<?php if ( $iframe_view ) { ?>
							<div id="design_standard" style="padding:0px;padding-top:10px;">
								<div class="hide-if-no-js">
									<table class="pollStyle">
										<thead>
											<tr>
												<th>
													<div style="display:none;">
														<input type="radio" name="styleTypeCB" id="regular" onclick="javascript:pd_build_styles( 0 );"/>
													</div>
												</th>
											</tr>
										</thead>
										<tr>
											<td class="selector" style="width:120px;">
												<table class="st_selector">
													<tr>
														<td class="dir_left" style="padding:0px;width:30px;">
															<a href="javascript:pd_move('prev');" style="display: block;font-size: 3.2em;text-decoration: none;">&#171;</a>
														</td>
														<td class="img"><div class="st_image_loader"><div id="st_image" onmouseover="st_results(this, 'show');" onmouseout="st_results(this, 'hide');"></div></div></td>
														<td class="dir_right" style="padding:0px;width:30px;">
															<a href="javascript:pd_move('next');" style="display: block;padding-left:20px;font-size: 3.2em;text-decoration: none;">&#187;</a>
														</td>
													</tr>
													<tr>
														<td></td>
														<td class="counter">
															<div id="st_number"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td></td>
														<td class="title">
															<div id="st_name"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td></td>
														<td>
															<div id="st_sizes"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td colspan="3">
															<div style="width:230px;" id="st_description"></div>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</div>

								<p class="empty-if-js" id="no-js-styleID">
									<select id="styleID" name="styleID">

								<?php
								foreach ( $preset_styles as $s_id => $label ) :
									$selected = $s_id === $style_id ? ' selected="selected"' : '';
									?>
									<option value="<?php echo (int) $s_id; ?>"<?php echo esc_attr( $selected ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>

									</select>
								</p>
							</div>
						<?php } else { ?>

							<div class="design_standard">
								<div class="hide-if-no-js">
								<table class="pollStyle">
									<thead>
										<tr style="display:none;">
											<th class="cb">

												<input type="radio" name="styleTypeCB" id="regular" onclick="javascript:pd_build_styles( 0 );"/>
												<label for="skin" onclick="javascript:pd_build_styles( 0 );"><?php _e( 'Crowdsignal Style', 'polldaddy' ); ?></label>

												<?php $disabled = (bool) $show_custom === false ? ' disabled="true"' : ''; ?>

												<input type="radio" name="styleTypeCB" id="custom" onclick="javascript:pd_change_style(_$('customSelect').value);" <?php echo $disabled; ?> />

												<label onclick="javascript:pd_change_style(_$('customSelect').value);"><?php _e( 'Custom Style', 'polldaddy' ); ?></label>

											<th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td style="text-align:center">
												<table class="st_selector" style="margin:20px auto;">
													<tr>
														<td class="dir_left">
															<a href="javascript:pd_move('prev');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#171;</a>
														</td>
														<td class="img"><div class="st_image_loader"><div id="st_image" onmouseover="st_results(this, 'show');" onmouseout="st_results(this, 'hide');"></div></div></td>
														<td class="dir_right">
															<a href="javascript:pd_move('next');" style="width: 1em;display: block;font-size: 4em;text-decoration: none;">&#187;</a>
														</td>
													</tr>
													<tr>
														<td></td>
														<td class="counter">
															<div id="st_number"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td></td>
														<td class="title">
															<div id="st_name"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td></td>
														<td>
															<div id="st_sizes"></div>
														</td>
														<td></td>
													</tr>
													<tr>
														<td colspan="3">
															<div id="st_description"></div>
														</td>
													</tr>
												</table>
											</td>

										</tr>
									</tbody>
								</table>
								</div>
								<p class="empty-if-js" id="no-js-styleID">
									<select id="styleID" name="styleID">

									<?php
									foreach ( $preset_styles as $s_id => $label ) :
										$selected = $s_id === $style_id ? ' selected="selected"' : '';
										?>
										<option value="<?php echo (int) $s_id; ?>"<?php echo esc_attr( $selected ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>

									</select>
								</p>
							</div>
						<?php } ?>
					</div>


					<div class="pd-tab-panel" id="pd-custom-styles-panel">
						<div  style="padding:20px;">
							<?php if ( $show_custom ) : ?>
								<p>
									<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'list-styles', 'poll' => false, 'style' => false, 'message' => false, 'preload' => false ) ) ); ?>" class="add-new-h2">
										All Styles
									</a>
								</p>
								<select id="customSelect" name="customSelect" onchange="javascript:pd_change_style(this.value);">
									<?php $selected = (int) $custom_style_id === 0 ? ' selected="selected"' : ''; ?>
									<option value="x"<?php echo esc_attr( $selected ); ?>><?php _e( 'Please choose a custom style…', 'polldaddy' ); ?></option>
									<?php
									foreach ( (array) $styles->style as $style ) :
										$selected = (int) $style->_id === (int) $custom_style_id ? ' selected="selected"' : '';
										?>
											<option value="<?php echo (int) $style->_id; ?>"<?php echo esc_attr( $selected ); ?>><?php echo esc_html( $style->title ); ?></option>
									<?php endforeach; ?>
								</select>
								<div id="styleIDErr" class="formErr" style="display:none;">
									<?php _e( 'Please choose a style.', 'polldaddy' ); ?>
								</div>
							<?php else : ?>
								<p>
									<?php _e( 'You currently have no custom styles created.', 'polldaddy' ); ?>
									<a href="/wp-admin/edit.php?post_type=feedback&page=polls&action=create-style" class="add-new-h2">
										<?php _e( 'New Style', 'polldaddy' ); ?>
									</a>
								</p>
								<p>
									<?php
									/* translators: link to support site for custom poll styles */
									printf( __( 'Did you know we have a new editor for building your own custom poll styles? Find out more <a href="%s" target="_blank">here</a>.', 'polldaddy' ), 'https://crowdsignal.com/support/custom-poll-styles/' );
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<script language="javascript">
						jQuery( document ).ready(function(){
							plugin = new Plugin( {
								delete_rating: '<?php echo esc_attr( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
								delete_poll: '<?php echo esc_attr( __( 'Are you sure you want to delete "%s"?', 'polldaddy' ) ); ?>',
								delete_answer: '<?php echo esc_attr( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
								new_answer_test: '<?php echo esc_attr( __( 'Enter an answer here', 'polldaddy' ) ); ?>',
								delete_answer_title: '<?php echo esc_attr( __( 'delete this answer', 'polldaddy' ) ); ?>',
								reorder_answer_title: '<?php echo esc_attr( __( 'click and drag to reorder', 'polldaddy' ) ); ?>',
								add_image_title: '<?php echo esc_attr( __( 'Add an Image', 'polldaddy' ) ); ?>',
								add_audio_title: '<?php echo esc_attr( __( 'Add Audio', 'polldaddy' ) ); ?>',
								add_video_title: '<?php echo esc_attr( __( 'Add Video', 'polldaddy' ) ); ?>',
								standard_styles: '<?php echo esc_attr( __( 'Standard Styles', 'polldaddy' ) ); ?>',
								custom_styles: '<?php echo esc_attr( __( 'Custom Styles', 'polldaddy' ) ); ?>',
								base_url: '<?php echo esc_attr( $base_url ); ?>'
							} );
						});
					</script>
					<script language="javascript">
						current_pos = 0;

						for( var key in styles_array ) {
							var name = styles_array[key].name;

							switch( name ) {
								case 'Aluminum':
									styles_array[key].name = '<?php echo esc_attr( __( 'Aluminum', 'polldaddy' ) ); ?>';
									break;
								case 'Plain White':
									styles_array[key].name = '<?php echo esc_attr( __( 'Plain White', 'polldaddy' ) ); ?>';
									break;
								case 'Plain Black':
									styles_array[key].name = '<?php echo esc_attr( __( 'Plain Black', 'polldaddy' ) ); ?>';
									break;
								case 'Paper':
									styles_array[key].name = '<?php echo esc_attr( __( 'Paper', 'polldaddy' ) ); ?>';
									break;
								case 'Skull Dark':
									styles_array[key].name = '<?php echo esc_attr( __( 'Skull Dark', 'polldaddy' ) ); ?>';
									break;
								case 'Skull Light':
									styles_array[key].name = '<?php echo esc_attr( __( 'Skull Light', 'polldaddy' ) ); ?>';
									break;
								case 'Micro':
									styles_array[key].name = '<?php echo esc_attr( __( 'Micro', 'polldaddy' ) ); ?>';
									styles_array[key].n_desc = '<?php echo esc_attr( __( 'Width 150px, the micro style is useful when space is tight.', 'polldaddy' ) ); ?>';
									break;
								case 'Plastic White':
									styles_array[key].name = '<?php echo esc_attr( __( 'Plastic White', 'polldaddy' ) ); ?>';
									break;
								case 'Plastic Grey':
									styles_array[key].name = '<?php echo esc_attr( __( 'Plastic Grey', 'polldaddy' ) ); ?>';
									break;
								case 'Plastic Black':
									styles_array[key].name = '<?php echo esc_attr( __( 'Plastic Black', 'polldaddy' ) ); ?>';
									break;
								case 'Manga':
									styles_array[key].name = '<?php echo esc_attr( __( 'Manga', 'polldaddy' ) ); ?>';
									break;
								case 'Tech Dark':
									styles_array[key].name = '<?php echo esc_attr( __( 'Tech Dark', 'polldaddy' ) ); ?>';
									break;
								case 'Tech Grey':
									styles_array[key].name = '<?php echo esc_attr( __( 'Tech Grey', 'polldaddy' ) ); ?>';
									break;
								case 'Tech Light':
									styles_array[key].name = '<?php echo esc_attr( __( 'Tech Light', 'polldaddy' ) ); ?>';
									break;
								case 'Working Male':
									styles_array[key].name = '<?php echo esc_attr( __( 'Working Male', 'polldaddy' ) ); ?>';
									break;
								case 'Working Female':
									styles_array[key].name = '<?php echo esc_attr( __( 'Working Female', 'polldaddy' ) ); ?>';
									break;
								case 'Thinking Male':
									styles_array[key].name = '<?php echo esc_attr( __( 'Thinking Male', 'polldaddy' ) ); ?>';
									break;
								case 'Thinking Female':
									styles_array[key].name = '<?php echo esc_attr( __( 'Thinking Female', 'polldaddy' ) ); ?>';
									break;
								case 'Sunset':
									styles_array[key].name = '<?php echo esc_attr( __( 'Sunset', 'polldaddy' ) ); ?>';
									break;
								case 'Music':
									styles_array[key].name = '<?php echo esc_attr( __( 'Music', 'polldaddy' ) ); ?>';
									break;
							}
						}
						pd_map = {
							wide : '<?php echo esc_attr( __( 'Wide', 'polldaddy' ) ); ?>',
							medium : '<?php echo esc_attr( __( 'Medium', 'polldaddy' ) ); ?>',
							narrow : '<?php echo esc_attr( __( 'Narrow', 'polldaddy' ) ); ?>',
							style_desc_wide : '<?php echo esc_attr( __( 'Width: 630px, the wide style is good for blog posts.', 'polldaddy' ) ); ?>',
							style_desc_medium : '<?php echo esc_attr( __( 'Width: 300px, the medium style is good for general use.', 'polldaddy' ) ); ?>',
							style_desc_narrow : '<?php echo esc_attr( __( 'Width 150px, the narrow style is good for sidebars etc.', 'polldaddy' ) ); ?>',
							style_desc_micro : '<?php echo esc_attr( __( 'Width 150px, the micro style is useful when space is tight.', 'polldaddy' ) ); ?>',
							image_path : '<?php echo plugins_url( 'img', dirname( __FILE__ ) ); ?>'
						}
						pd_build_styles( current_pos );
						<?php if ( $style_id > 0 && $style_id <= 1000 ) { ?>
						pd_pick_style( <?php echo $style_id; ?> );
						<?php } else { ?>
						pd_change_style( <?php echo $style_id; ?> );
						<?php } ?>
					</script>
				</div>

			</div>

		</div>
	</div></div>
</form>
<br class="clear" />

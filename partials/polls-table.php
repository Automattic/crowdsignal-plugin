<?php
/**
 * Polls table view for Crowdsignal dashboard
 *
 * @package crowdsignal
 */

?>

<div class="cs-wrapper-row cs-background-fill">
	<div class="cs-dashboard__crowdsignal-header">
		<a href="https://crowdsignal.com" target="_blank" rel="noopener" class="cs-dashboard__crowdsignal-header-link">
			<h2 class="cs-dashboard__crowdsignal-header-brand">
				<img class="cs-dashboard__crowdsignal-header-logo" src="<?php echo esc_html( $resource_path ); ?>/img/svg/cs-logo2.svg" title="Crowdsignal" loading="lazy" width="40" alt="Crowdsignal Logo">
				<?php esc_html_e( 'Crowdsignal', 'polldaddy' ); ?>
			</h2>
		</a>
		<div class="cs-dashboard__crowdsignal-header-actions" id="dashboard-crowdsignal-header-actions"></div>
	</div>

	<div class='cs-dashboard__main'>
		<div id="cs-dashboard-notice" class="cs-dashboard__header-notice"></div>
		<div class="cs-dashboard__header">
			<div class="cs-dashboard__header-left">
				<div id="cs-dashboard-switch"></div>
			</div>
			<div class="cs-dashboard__header-right">
				<div id="cs-dashboard-create-menu"></div>
			</div>
		</div>

		<div class="item-container" id="dashboard-items">
			<table class="cs-dashboard__grid">
				<thead>
					<tr>
						<th class="cs-dashboard__grid is-name"><?php esc_html_e( 'Name' ); ?></th>
						<th class="cs-dashboard__grid is-type"><span class="cs-dashboard__mq-desktop-only"><?php esc_html_e( 'Type' ); ?></span></th>
						<th class="cs-dashboard__grid is-created"><span class="cs-dashboard__mq-desktop-only"><?php esc_html_e( 'Created' ); ?></span></th>
						<th class="cs-dashboard__grid is-status"><?php esc_html_e( 'Status' ); ?></th>
						<th class="cs-dashboard__grid is-responses-total"><?php esc_html_e( 'Responses' ); ?></th>
						<th class="cs-dashboard__grid is-source"><span class="cs-dashboard__mq-desktop-only"><?php esc_html_e( 'Source' ); ?></span></th>
						<th class="cs-dashboard__grid is-links"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( ! is_array( $items ) ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer
						$items = array();
					}
					foreach ( $items as $item ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable
						$item->_id = intval( $item->_id );

						$delete_link  = false;
						$preview_link = false;
						$close_link   = false;
						$open_link    = false;
						$edit_link    = false;

						$item_post_link = isset( $item->_source_link ) ? $item->_source_link : '';

						$item_post_id = url_to_postid( $item_post_link );

						$display_link = wp_parse_url( $item_post_link );
						if ( isset( $display_link['query'] ) && '' !== $display_link['query'] ) {
							$display_link = $display_link['path'] . '?' . $display_link['query'];
						} elseif ( isset( $display_link['path'] ) && '' !== $display_link['path'] ) {
							$display_link = $display_link['path'];
						} else {
							$display_link = $item_post_link;
						}

						if ( 'poll' === $item->type ) {
							$item->name = trim( wp_strip_all_tags( $item->name ) );
							if ( 0 === strlen( $item->name ) ) {
								$item->name = __( 'Unknown' );
							}

							$results_link = 'https://app.crowdsignal.com/polls/' . $item->_id . '/results';
							if ( $item_post_link ) {
								$edit_link = get_edit_post_link( $item_post_id );
							} else {
								$edit_link   = add_query_arg(
									array(
										'action'  => 'edit',
										'poll'    => $item->_id,
										'message' => false,
									)
								);
								$delete_link = wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'delete',
											'poll'    => $item->_id,
											'message' => false,
										)
									),
									'delete-poll_' . $item->_id
								);
								$preview     = array( // phpcs:ignore -- for preview of polls
									'action'    => 'preview',
									'poll'      => $item->_id,
									'message'   => false,
									'iframe'    => 1,
									'TB_iframe' => 'true',
								);

								if ( isset( $_GET['iframe'] ) ) { // phpcs:ignore -- not actually processing a form
									$preview['popup'] = 1; // phpcs:ignore -- for preview of polls
								}
								$preview_link = add_query_arg( $preview );

								$close_link = wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'close',
											'poll'    => $item->_id,
											'message' => false,
										)
									),
									'close-poll_' . $item->_id
								);

								$open_link = wp_nonce_url(
									add_query_arg(
										array(
											'action'  => 'open',
											'poll'    => $item->_id,
											'message' => false,
										)
									),
									'open-poll_' . $item->_id
								);
							}
							$icon_url = 'img/svg/icon-block-poll-round.svg';
							switch ( $item->subtype ) {
								case 'applause':
									$icon_url = 'img/svg/icon-block-applause-round.svg';
									break;
								case 'vote':
									$icon_url = 'img/svg/icon-block-voting-round.svg';
									break;
							}
						} elseif ( 'survey' === $item->type ) {
							$results_link   = 'https://app.crowdsignal.com/surveys/' . $item->_id . '/report/overview';
							$icon_url       = 'img/svg/icon-block-survey-round.svg';
							$edit_post      = false;
							switch ( $item->subtype ) {
								case 'nps':
									$edit_link = $item_post_id ? get_edit_post_link( $item_post_id ) : false;
									$icon_url  = 'img/svg/icon-block-nps-round.svg';
									break;
								case 'feedback':
									$edit_link = $item_post_id ? get_edit_post_link( $item_post_id ) : false;
									$icon_url  = 'img/svg/icon-block-feedbackButton-round.svg';
									break;
							}
						} elseif ( 'quiz' === $item->type ) {
							$edit_post      = false;
							$item_post_link = false;
							$results_link   = 'https://app.crowdsignal.com/quizzes/' . $item->_id . '/report/overview';
							$edit_link      = 'https://app.crowdsignal.com/quizzes/' . $item->_id . '/question';
							$icon_url       = 'img/svg/icon-block-quiz-round.svg';
						} elseif ( 'rating' === $item->type ) {
							$edit_post      = false;
							$item_post_link = false;
							$results_link   = 'https://app.crowdsignal.com/ratings/' . $item->_id . '/results/';
							$edit_link      = 'https://app.crowdsignal.com/ratings/' . $item->_id . '/edit/';
							$icon_url       = 'img/svg/icon-block-rating-round.svg';
								} elseif ( 'project' === $item->type ) {
							$edit_post      = false;
							$item_post_link = false;
							$results_link   = 'https://app.crowdsignal.com/project/' . $item->_id . '/results/';
							$edit_link      = 'https://app.crowdsignal.com/project/' . $item->_id;
							$icon_url       = 'img/svg/icon-block-project-round.svg';
						} else { // show a generic icon and generic links to app.crowdsignal.com for unhandled item types
							$edit_post      = false;
							$item_post_link = false;
							$results_link   = false;
							$edit_link      = 'https://app.crowdsignal.com/dashboard/';
							$icon_url       = 'img/svg/cs-logo2.svg';
						}

						$icon_url = $resource_path . $icon_url; // phpcs:ignore -- variable comes from controller
						$type_descriptor = $item->subtype
							? $item->subtype
							: $item->type;

						?>
						<tr>
							<td class="cs-dashboard__grid is-name">
								<a target="_blank" rel="noopener" title="<?php echo esc_attr( $item->name ); ?>" href="<?php echo esc_url( $results_link ); ?>"><?php echo esc_html( $item->name ); ?></a>
							</td>
							<td class="cs-dashboard__grid is-type">
								<img class="cs-dashboard__mq-desktop-only" src="<?php echo esc_url( $icon_url ); ?>" title="<?php echo esc_attr( $type_descriptor ); ?>" alt="<?php echo esc_attr( $type_descriptor ); ?> icon" />
							</td>
							<td class="cs-dashboard__grid is-created">
								<span class="cs-dashboard__mq-desktop-only"><?php echo esc_html( gmdate( 'M j', $item->_created ) ); ?></span>
							</td>
							<td class="cs-dashboard__grid is-status" data-open="<?php echo $item->_closed ? 0 : 1; ?>">
							<?php echo ! $item->_closed ? esc_html__( 'Open' ) : esc_html__( 'Closed' ); ?>
							</td>
							<td class="cs-dashboard__grid is-responses-total">
							<strong><?php echo esc_html( number_format_i18n( $item->_responses ) ); ?></strong>
							</td>
							<td class="cs-dashboard__grid is-source">
								<?php if ( $item_post_link ) : ?>
									<span class="cs-dashboard__mq-desktop-only">
										<a rel="noopener" href="<?php echo esc_url( $item_post_link ); ?>"><?php echo esc_url( $display_link ); ?></a>
									</span>
								<?php endif; ?>
							</td>
							<td class="cs-dashboard__grid is-links">
								<span class="cs-dashboard__mq-desktop-only">
									<a target="_blank" rel="noopener" href="<?php echo esc_url( $results_link ); ?>"><?php esc_html_e( 'Results' ); ?></a>
									<?php if ( $edit_link ) { ?>
										<a target="<?php echo $item_post_id ? '' : '_blank'; ?>" rel="noopener" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit' ); ?></a>
									<?php } ?>
									<?php if ( $open_link || $close_link ) { ?>
										<a target="_blank" rel="noopener" href="<?php echo $item->_closed ? esc_url( $open_link ) : esc_url( $close_link ); ?>"><?php $item->_closed ? esc_html_e( 'Open' ) : esc_html_e( 'Close' ); ?></a>
									<?php } ?>

									<?php if ( $delete_link ) { ?>
										<a target="_blank" rel="noopener" class="delete-poll delete" href="<?php esc_url( $delete_link ); ?>"><?php esc_html_e( 'Delete' ); ?></a>
									<?php } ?>

									<?php if ( $preview_link ) { ?>
										<a class='thickbox' href="<?php echo esc_url( $preview_link ); ?>"><?php esc_html_e( 'Preview' ); ?></a>
									<?php } ?>
								</span>
								<span
									class="cs-dashboard__mq-mobile-only cs-dashboard__links-dropdown-toggle"
									data-link-id="<?php echo esc_attr( $item->_id ); ?>"
									data-status="<?php echo $item->_closed ? 'closed' : 'open'; ?>"
									data-results-url="<?php echo esc_attr( $results_link ); ?>"
									data-edit-url="<?php echo $edit_link ? esc_attr( $edit_link ) : ''; ?>"
									data-open-url="<?php echo $open_link ? esc_attr( $open_link ) : ''; ?>"
									data-close-url="<?php echo $close_link ? esc_attr( $close_link ) : ''; ?>"
									data-delete-url="<?php echo $delete_link ? esc_attr( $delete_link ) : ''; ?>"
									data-preview-url="<?php echo $preview_link ? esc_attr( $preview_link ) : ''; ?>"
									data-post-url="<?php echo $item_post_link ? esc_attr( $item_post_link ) : ''; ?>"
									>
								</span>
								<div id="cs-dashboard__links-dropdown-menu-<?php echo esc_attr( $item->_id ); ?>"></div>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<div class="tablenav" <?php echo ( '' === $page_links ) ? 'style="display:none;"' : ''; // phpcs:ignore -- output from paginate_links ?>>
			<div class="tablenav-pages"><?php echo $page_links; ?></div> <?php // phpcs:ignore -- output from paginate_links ?>
		</div>
	</div>

	<div class="cs-dashboard__footer">
		<div class="cs-dashboard__footer-left">
			<a href="https://crowdsignal.com" target="_blank" rel="noopener">
				<img class="cs-dashboard__crowdsignal-header-logo" src="<?php echo esc_html( $resource_path ); ?>/img/svg/cs-logo2.svg" title="Crowdsignal" loading="lazy" width="40" alt="Crowdsignal Logo" />
			</a>
			<br />
			What is Crowdsignal? <a href="https://crowdsignal.com" target="_blank" rel="noopener">Learn more here.</a>
		</div>
		<div class="cs-dashboard__footer-right">
			<a class="cs-dashboard__a8c-link" href="https://automattic.com" rel="noreferrer noopener" target="_blank">An <span>Automattic</span> Company</a>
		</div>
	</div>
</div>

<div id="cs-dashboard__modal-request"></div>

<script type="text/javascript">

jQuery( document ).ready(function(){
	const currentUserName = '<?php echo esc_js( $current_user_name ); // phpcs:ignore -- output from main handler file ?>';
	const currentView = '<?php echo esc_js( $view ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>';
	const el = wp.element.createElement;
	const render = wp.element.render;
	const useState = wp.element.useState;
	const Fragment = wp.element.Fragment;
	const connectedAccountEmail = '<?php echo esc_js( $connected_account_email ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>';
	const csFormsAccountEmail = '<?php echo esc_js( $cs_forms_account_email ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>';
	const currentUserOwnsConnection = <?php echo ( $current_user_owns_connection ) ? 'true' : 'false'; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>;
	const imgPath = '<?php echo esc_url( $resource_path . 'img' ); // phpcs:ignore -- variable comes from controller ?>';
	const hasCrowdsignalBlocks = <?php echo $has_crowdsignal_blocks ? 'true' : 'false'; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>;
	const hasMultipleAccounts = <?php echo $has_multiple_accounts ? 'true' : 'false'; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>;
	const globalAccountName = '<?php echo esc_js( $global_user_name ); // phpcs:ignore -- variable comes from controller ?>';
	const globalAccountId = '<?php echo esc_js( $global_user_id ); // phpcs:ignore -- variable comes from controller ?>';

	plugin = new Plugin( {
		<?php /* translators: name of the rating being deleted */ ?>
		delete_rating: '<?php echo esc_js( __( 'Are you sure you want to delete the rating for "%s"?', 'polldaddy' ) ); ?>',
		<?php /* translators: name of the poll being deleted */ ?>
		delete_poll: '<?php echo esc_js( __( 'Are you sure you want to delete the poll %s?', 'polldaddy' ) ); ?>',
		delete_answer: '<?php echo esc_js( __( 'Are you sure you want to delete this answer?', 'polldaddy' ) ); ?>',
		delete_answer_title: '<?php echo esc_js( __( 'delete this answer', 'polldaddy' ) ); ?>',
		standard_styles: '<?php echo esc_js( __( 'Standard Styles', 'polldaddy' ) ); ?>',
		custom_styles: '<?php echo esc_js( __( 'Custom Styles', 'polldaddy' ) ); ?>'
	} );

	if ( 'me' !== currentView && ! currentUserOwnsConnection ) {
		const RestrictionModal = () => {
			const [ isModalOpen, setModalOpen ] = useState( false );
			const openModal = () => setModalOpen( true );
			const closeModal = () => setModalOpen( false );
			const linkClickHandler = ( e ) => {
				e.preventDefault();
				openModal();
				return false;
			}
			jQuery( '.is-name a' ).on( 'click', linkClickHandler );
			jQuery( '.is-results-action a' ).on( 'click', linkClickHandler );
			const shallNotPass = el( 'img', { src: `${imgPath}/svg/lock.svg` } );
			const modalContentHeadline = el( 'div', { className: 'cs-dashboard__modal-request-headline' }, 'You need access to this Crowdsignal page.' );
			const modalContentCta = el( 'div', { className: 'cs-dashboard__modal-request-text' }, 'Please ask the project owner' );
			const modalContentCtaEmail = el( 'div', { className: 'cs-dashboard__modal-request-text is-email' }, connectedAccountEmail );
			const modalContentCtaEnd = el( 'div', { className: 'cs-dashboard__modal-request-text' }, 'for access to a Team account.' );
			const modalContent = el(
				'div',
				{ className: 'cs-dashboard__modal-request-body' },
				shallNotPass,
				modalContentHeadline,
				modalContentCta,
				modalContentCtaEmail,
				modalContentCtaEnd
			);
			return el(
				Fragment,
				{},
				'',
				isModalOpen && el( wp.components.Modal, { className: 'cs-dashboard__modal-request', onRequestClose: closeModal, title: 'Request access', contentLabel: 'Request access' }, modalContent )
			);
		}

		render(
			el( RestrictionModal ),
			document.getElementById( 'cs-dashboard__modal-request' )
		);
	}

	const CreateAccountDropdown = () => {
		const toggle = ( { isOpen, onToggle } ) => el(
			wp.components.Button,
			{
				className: 'cs-account__dropdown-menu-toggle',
				onClick: onToggle,
				'aria-expanded': isOpen,
			},
			connectedAccountEmail || '<?php echo esc_js( __( 'Account', 'polldaddy' ) ); ?>',
			el( wp.components.Icon, { icon: 'arrow-down' }, null )
		);

		const openCsAccount = () => window.open( 'https://app.crowdsignal.com/account', '_blank' );
		const openCsSettings = () => window.open( '?page=polls&action=options', '_self' );
		const openCsBlog = () => window.open( 'https://crowdsignal.com/blog', '_blank' );
		const openCsSupport = () => window.open( 'https://crowdsignal.com/support', '_blank' );
		const openCsSite = () => window.open( 'https://crowdsignal.com/', '_blank' );
		const renderList = () => el(
			'div',
			{
				className: 'cs-account-menu__dropdown-list'
			},
			el( wp.components.Button, { isSecondary: true, onClick: openCsAccount }, '<?php echo esc_js( __( 'My Account', 'polldaddy' ) ); ?>' ),
			el( wp.components.Button, { isSecondary: true, onClick: openCsSettings }, '<?php echo esc_js( __( 'Settings', 'polldaddy' ) ); ?>' ),
			el( wp.components.Button, { isSecondary: true, onClick: openCsBlog }, '<?php echo esc_js( __( 'Crowdsignal Blog', 'polldaddy' ) ); ?>' ),
			el( wp.components.Button, { isSecondary: true, onClick: openCsSupport }, '<?php echo esc_js( __( 'Help', 'polldaddy' ) ); ?>' ),
			el( wp.components.Button, { isSecondary: true, onClick: openCsSite }, 'crowdsignal.com' ),
		);
		return el(
			wp.components.Dropdown,
			{
				className: 'cs-account-menu__dropdown',
				contentClassName: 'cs-account-menu__drowpdown-list-container',
				placement: 'bottom center',
				renderToggle: toggle,
				renderContent: renderList ,
				popoverProps: { noArrow: false },
			}
		);
	}

	render( el( CreateAccountDropdown, {} ), document.getElementById( 'dashboard-crowdsignal-header-actions' ) );

	// create new dropdown with modals:
	const ModalButton = ( { label, iconUrl, videoSrc, headline, footer } ) => {
		const videoPath = `${imgPath}/video/${videoSrc}`;
		const csImageUrlPath = 'https://app.crowdsignal.com/images/item-icons';
		const iconPath = `${csImageUrlPath}/${iconUrl}`;

		const [ isModalOpen, setModalOpen ] = useState( false );
		const openModal = () => setModalOpen( true );
		const closeModal = () => setModalOpen( false );

		const buttonIcon = el( 'img', { className: 'cs-create-menu__item-image-icon', src: iconPath, alt: label }, null );
		// const head = el( 'h2', {}, headline );
		// const head2 = el( 'h2', {}, 'This is how it works:' );
		const video = el( 'video', { className: 'cs-create-menu__video', src: `${videoPath}`, autoPlay: true, muted: true, loop: true }, null );
		return el( Fragment, {},
			el( wp.components.Button, { isSecondary: true, className: 'cs-create-menu__item', onClick: openModal }, buttonIcon, label ),
			isModalOpen && el( wp.components.Modal, { className: 'cs-create-menu__modal', contentLabel: `${label} video tutorial`, onRequestClose: closeModal, title: headline }, video, footer )
		);
	}

	const CreateMenuDropdown = () => {
		const toggle = ( { isOpen, onToggle } ) => el(
			wp.components.Button,
			{
				isPrimary: true,
				className: isOpen ? 'cs-create-menu__dropdown-toggle is-active' : 'cs-create-menu__dropdown-toggle',
				onClick: onToggle,
				'aria-expanded': isOpen
			},
			'Create new', el( wp.components.Icon, { icon: 'arrow-down-alt2' }, null )
		);

		const embedFooter = el( 'div', {}, 'Create a survey on: ', el( 'a', { href: 'https://app.crowdsignal.com' }, 'app.crowdsignal.com' ) );
		const sublistItems = [
			{ headline: 'Please find the Poll Block in your editor:', videoSrc: 'poll-block-tutorial.mp4', iconUrl: 'icon-block-poll-round.svg', label: 'Poll' },
			{ headline: 'How to embed a Crowdsignal Survey into WordPress:', videoSrc: 'survey-embed-tutorial.mp4', iconUrl: 'icon-block-survey-round.svg', label: 'Survey', footer: embedFooter },
			{ headline: 'Please find the Feedback Button Block in your editor:', videoSrc: 'feedback-button-block-tutorial.mp4', iconUrl: 'icon-block-feedbackButton-round.svg', label: 'Feedback' },
			{ headline: 'Please find the Measure NPS Block in your editor:', videoSrc: 'nps-block-tutorial.mp4', iconUrl: 'icon-block-nps-round.svg', label: 'NPS' },
			{ headline: 'Please find the Vote Block in your editor:', videoSrc: 'vote-block-tutorial.mp4', iconUrl: 'icon-block-voting-round.svg', label: 'Voting' },
			{ headline: 'Please find the Applause Block in your editor:', videoSrc: 'applause-block-tutorial.mp4', iconUrl: 'icon-block-applause-round.svg', label: 'Applause' },
		];
		const sublist = () => el(
			'div',
			{ className: 'cs-create-menu__dropdown-list' },
			sublistItems.map( item => el( ModalButton, item ) )
		);

		var dropdownProps = {
			className: 'cs-create-menu__dropdown',
			contentClassName: 'cs-create-menu__drowpdown-list-container',
			position: 'bottom center',
			renderToggle: toggle,
			renderContent: sublist,
			popoverProps: { noArrow: false },
		};

		return el( wp.components.Dropdown, dropdownProps, null );
	}

	render(
		el( CreateMenuDropdown, {} ),
		document.getElementById( 'cs-dashboard-create-menu' )
	);

	// notification
	const Notification = () => {
		const [ visible, setVisible ] = useState( true );
		const editorLink = el( 'a', { href: '<?php echo esc_url( add_query_arg( array( 'action' => 'create-poll' ) ) ); ?>' }, 'create poll page' );
		const csLink = el( 'a', { href: 'https://app.crowdsignal.com', target: '_blank' }, 'Crowdsignal dashboard' );
		const settingsLink = el( 'a', { href: 'options-general.php?page=polls&action=options' }, 'here' );
		const learnMoreLink = el( 'a', { href: 'https://crowdsignal.com/support/welcome-to-the-new-crowdsignal-area-in-wp-admin/', target: '_blank' }, 'Learn more.' );
		const noticeOptions = { onRemove: () => {
			window.localStorage.setItem( 'makingChangesNoticeClosed', true );
			setVisible( false );
		} };
		return visible && el(
			wp.components.Notice,
			noticeOptions,
			'We made some changes here. ', learnMoreLink
		);
	}

	const hasClosedNotice = window.localStorage.getItem( 'makingChangesNoticeClosed' );

	if ( ! hasClosedNotice ) {
		render(
			el( Notification ),
			document.getElementById( 'cs-dashboard-notice' )
		);
	} else {
		document.getElementById( 'cs-dashboard-notice' ) && document.getElementById( 'cs-dashboard-notice' ).remove();
	}


	// switch buttons
	const ButtonGroup = () => {
		const meAvatarUrl = '<?php echo esc_js( get_avatar_url( $user_id, array( 'size' => 16 ) ) ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>';
		const meGravatar = el( 'img', { width: 16, src: meAvatarUrl, alt: 'User avatar', title: 'User avatar', className: 'cs-dashboard-switch__avatar' } );
		const [ isVisible, setVisible ] = useState( false );
		const hidePopover = () => setVisible( false );
		const showPopover = () => setVisible( true );
		let otherAvatarUrl = null;
		let otherGravatar = null;
		if ( hasMultipleAccounts && globalAccountId ) {
			otherAvatarUrl = '<?php echo esc_js( get_avatar_url( $global_user_id, array( 'size' => 16 ) ) ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- output from page renderer ?>';
			otherGravatar = el( 'img', { width: 16, src: otherAvatarUrl, alt: globalAccountName, title: globalAccountName, className: 'cs-dashboard-switch__avatar' } );
		}
		const meButton = el(
			wp.components.Button,
			{
				href: '?post_type=feedback&page=polls&view=me',
				isSecondary: true,
				className: 'me' === currentView && 'is-current',
			},
			el( 'span', { className: 'cs-dashboard-switch__text' }, 'Me', meGravatar )
		);
		const otherButton = hasMultipleAccounts
			? el(
				wp.components.Button,
				{
					href: '?post_type=feedback&page=polls&view=blog',
					isSecondary: true,
					className: 'blog' === currentView && 'is-current',
				},
				el( 'span', { className: 'cs-dashboard-switch__text' }, globalAccountName, otherGravatar )
			)
			: null;
		const csformsButton = hasCrowdsignalBlocks
			? el(
					Fragment,
					{},
					el(
						wp.components.Button,
						{
							href: '?post_type=feedback&page=polls&view=csforms',
							isSecondary: true,
							className: 'csforms' === currentView && 'is-current',
							onMouseOver: showPopover,
							onMouseOut: hidePopover,
						},
						'On This Site'
					),
					isVisible && el(
						wp.components.Popover,
						{ noArrow: false, position: 'middle right', className: 'cs-dashboard-switch__popover' },
						'This WordPress site is connected',
						el( 'br' ),
						'to Crowdsignal account:',
						el( 'br' ),
						csFormsAccountEmail
					)
				)
			: null;
		return ( hasMultipleAccounts || hasCrowdsignalBlocks ) && el( wp.components.ButtonGroup, { className: 'cs-dashboard-switch' }, meButton, otherButton, csformsButton );
	}
	const buttonGroupContainer = document.getElementById( 'cs-dashboard-switch' );
	if ( buttonGroupContainer ) {
		render(
			el( ButtonGroup ),
			buttonGroupContainer
		);
	}

	// Links dropdown (mobile view)
	const linksDropdownToggle = ( { isOpen, onToggle } ) => el(
		wp.components.Button,
		{
			isPrimary: false,
			className: isOpen ? 'cs-links-menu__dropdown-toggle is-active' : 'cs-links-menu__dropdown-toggle',
			onClick: onToggle,
			'aria-expanded': isOpen
		},
		el( wp.components.Icon, { icon: 'ellipsis' }, null )
	);

	const linkToggles = document.body.querySelectorAll( 'span.cs-dashboard__links-dropdown-toggle' );
	linkToggles.forEach( linkToggle => {
		const {
			postUrl,
			linkId,
			resultsUrl,
			openUrl,
			closeUrl,
			status
		} = linkToggle.dataset;
		const links = [];
		postUrl && links.push( el( 'a', { rel: 'noopener noreferer', key: Math.random(), href: postUrl }, 'View Post' ) );
		resultsUrl && links.push( el( 'a', { rel: 'noopener noreferer', key: Math.random(), href: resultsUrl }, 'Results' ) );
		status === 'closed' && openUrl && links.push( el( 'a', { rel: 'noopener noreferer', key: Math.random(), href: openUrl }, 'Open' ) );
		status === 'open' && closeUrl && links.push( el( 'a', { rel: 'noopener noreferer', key: Math.random(), href: closeUrl }, 'Close' ) );

		const sublist = () => el(
			'div',
			{ className: 'cs-links-menu__dropdown-list' },
			links.map( link => link )
		);
		const linkDropdown = document.getElementById( 'cs-dashboard__links-dropdown-menu-' + linkId );
		if ( linkDropdown ) {
			render(
				el( wp.components.Dropdown, {
					className: 'cs-dashboard__links-dropdown',
					contentClassName: 'cs-dashboard__links-drowpdown-container',
					position: 'bottom left',
					renderToggle: linksDropdownToggle,
					renderContent: sublist,
					popoverProps: { noArrow: false },
				} ),
				linkDropdown
			);
		}
	} );
});
</script>

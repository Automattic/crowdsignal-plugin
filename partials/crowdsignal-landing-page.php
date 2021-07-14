<?php
/**
 * Crowdsignal legacy plugin
 *
 * @package crowdsignal
 */

?>

<div class="cs-wrapper-row cs-background-fill cs-centered">
	<div class="cs-dashboard__crowdsignal-header">
		<a href="https://crowdsignal.com" target="_blank" rel="noopener" class="cs-dashboard__crowdsignal-header-link">
			<h2 class="cs-dashboard__crowdsignal-header-brand">
				<img class="cs-dashboard__crowdsignal-header-logo" src="<?php echo esc_html( $resource_path ); ?>/img/svg/cs-logo2.svg" title="Crowdsignal" loading="lazy" width="40" alt="Crowdsignal Logo">
				<?php esc_html_e( 'Crowdsignal', 'polldaddy' ); ?>
			</h2>
		</a>
		<div class="cs-dashboard__crowdsignal-header-actions" id="dashboard-crowdsignal-header-actions"></div>
	</div>
</div>

<div class="cs-wrapper-row cs-background-fill cs-centered">
	<div class="crowdsignal-landing__container w-container">
		<div class="crowdsignal-landing__hero">
			<div class="crowdsignal-landing__hero-left">
				<div class="crowdsignal-landing__hero-headline"><?php esc_html_e( 'Looking for insights?' ); ?><br><?php esc_html_e( 'Start asking!' ); ?></div>
				<div class="crowdsignal-landing__hero-subline">
					<strong><?php esc_html_e( 'Crowdsignal', 'polldaddy' ); ?></strong>
					<?php esc_html_e( 'is a collection of powerful blocks that help you to collect feedback, analyze incoming responses and learn from your audience.' ); ?>
				</div>
			</div>
			<div class="crowdsignal-landing__hero-right">
				<div class="crowdsignal-landing__herogif">
					<img src="<?php echo esc_html( $resource_path ); ?>/img/gif/poll-block-v1.1.gif" loading="lazy" alt="Crowdsignal Poll Block" class="crowdsignal-landing__image">
				</div>
			</div>
		</div>
	</div>
</div>

<div class="cs-wrapper-row cs-centered">
	<div class="crowdsignal-landing__blocks-cta">
		<h3 class="crowdsignal-landing__blocks-cta-title">
			<?php esc_html_e( 'Have you discovered our blocks yet?', 'polldaddy' ); ?>
			<br />
			<?php esc_html_e( 'They are available in your editor:', 'polldaddy' ); ?>
		</h3>
	</div>
</div>

<div class="cs-wrapper-row cs-centered">
	<div class="crowdsignal-landing__card-container">
		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-poll.png"
					title="<?php esc_html_e( 'Poll block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Poll block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Poll', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'Create polls and get your audience’s opinion.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="poll-tutorial"></div>
		</div>

		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-survey-embed.png"
					title="<?php esc_html_e( 'Survey embed block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Survey embed block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Survey Embed', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'Create surveys in minutes with 14 question and form types and embed them into your page.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="survey-embed-tutorial"></div>
		</div>

		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-feedback.png"
					title="<?php esc_html_e( 'Feedback Button block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Feedback Button block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Feedback Button', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'A floating always visible button that allows your audience to share feedback anytime.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="feedback-button-tutorial"></div>
		</div>
		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-nps.png"
					title="<?php esc_html_e( 'Measure NPS block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Measure NPS block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Measure NPS', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'Calculate your Net Promoter Score! Collect feedback and track customer satisfaction over time.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="nps-tutorial"></div>
		</div>

		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-voting.png"
					title="<?php esc_html_e( 'Voting block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Voting block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Voting', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'Allow your audience to rate your work or express their opinion.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="vote-tutorial"></div>
		</div>

		<div class="crowdsignal-landing__card cs-background-fill">
			<div class="crowdsignal-landing__card-icon">
				<img src="<?php echo esc_html( $resource_path ); ?>/img/item-icons/icon-block-applause.png"
					title="<?php esc_html_e( 'Applause block', 'polldaddy' ); ?>"
					alt="<?php esc_html_e( 'Applause block icon', 'polldaddy' ); ?>" />
			</div>
			<div class="crowdsignal-landing__card-title"><?php esc_html_e( 'Applause', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-body"><?php esc_html_e( 'Let your audience cheer with a big round of applause.', 'polldaddy' ); ?></div>
			<div class="crowdsignal-landing__card-footer" id="applause-tutorial"></div>
		</div>
	</div>
</div>

<div class="cs-wrapper-row cs-centered">
	<div class="crowdsignal-landing__blocks-cta">
		<h3 class="crowdsignal-landing__blocks-cta-title crowdsignal-landing__blocks-export-showcase">
			<?php esc_html_e( 'Analyze responses in real time, export your results everywhere!', 'polldaddy' ); ?>
		</h3>
	</div>
</div>

<div class="cs-wrapper-row cs-centered">
	<div class="crowdsignal-landing__blocks-cta crowdsignal-landing__blocks-export-showcase">
		<img src="<?php echo esc_html( $resource_path ); ?>/img/export-everywhere.png"
			alt="<?php esc_html_e( 'Export showcase image', 'polldady' ); ?>"
			title="<?php esc_html_e( 'Export showcase', 'polldady' ); ?>" width="583px" />
	</div>
</div>

<div class="cs-wrapper-row cs-centered">
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

<script>
jQuery( document ).ready( function() {
	const el = wp.element.createElement;
	const render = wp.element.render;
	const useState = wp.element.useState;
	const Fragment = wp.element.Fragment;

	const ModalButton = ( { videoSrc, headline, footer } ) => {
		const imgPath = '<?php echo esc_url( $resource_path . 'img' ); // phpcs:ignore -- variable comes from controller ?>';
		const videoPath = `${imgPath}/video/${videoSrc}`;

		const [ isModalOpen, setModalOpen ] = useState( false );
		const openModal = () => setModalOpen( true );
		const closeModal = () => setModalOpen( false );

		// const head = el( 'h2', {}, headline );
		// const head2 = el( 'h2', {}, 'This is how it works:' );
		const video = el( 'video', { className: 'cs-create-menu__video', src: `${videoPath}`, autoPlay: true, muted: true, loop: true }, null );
		return el( Fragment, {},
			el( wp.components.Button, { isSecondary: true, isSmall: true, onClick: openModal }, '<?php echo esc_js( 'Learn more', 'polldaddy' ); ?>' ),
			isModalOpen && el( wp.components.Modal, { onRequestClose: closeModal, title: headline }, video, footer && footer )
		);
	};

	const embedFooter = el( 'div', {}, 'Create a survey on: ', el( 'a', { href: 'https://app.crowdsignal.com' }, 'app.crowdsignal.com' ) );

	render( el( ModalButton, { headline: 'Please find the Poll Block in your editor:', videoSrc: 'poll-block-tutorial.mp4' } ), document.getElementById( 'poll-tutorial' ) );
	render( el( ModalButton, { headline: 'How to embed a Crowdsignal Survey into WordPress:', videoSrc: 'survey-embed-tutorial.mp4', footer: embedFooter } ), document.getElementById( 'survey-embed-tutorial' ) );
	render( el( ModalButton, { headline: 'Please find the Feedback Button Block in your editor:', videoSrc: 'feedback-button-block-tutorial.mp4' } ), document.getElementById( 'feedback-button-tutorial' ) );
	render( el( ModalButton, { headline: 'Please find the Measure NPS Block in your editor:', videoSrc: 'nps-block-tutorial.mp4' } ), document.getElementById( 'nps-tutorial' ) );
	render( el( ModalButton, { headline: 'Please find the Vote Block in your editor:', videoSrc: 'vote-block-tutorial.mp4' } ), document.getElementById( 'vote-tutorial' ) );
	render( el( ModalButton, { headline: 'Please find the Applause Block in your editor:', videoSrc: 'applause-block-tutorial.mp4' } ), document.getElementById( 'applause-tutorial' ) );
} );
</script>

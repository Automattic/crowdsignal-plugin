<?php
/**
 * Base test case for Crowdsignal Plugin tests
 *
 * @package Crowdsignal_Forms
 */

/**
 * Base test case class.
 *
 * All Crowdsignal Plugin test cases should extend this class.
 * It extends WP_UnitTestCase to follow WordPress testing conventions.
 */
class Crowdsignal_Test_Case extends WP_UnitTestCase {

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset any plugin-specific globals or state.
		$this->reset_plugin_state();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Clean up any test data.
		$this->cleanup_test_data();

		parent::tearDown();
	}

	/**
	 * Reset plugin state to ensure test isolation.
	 */
	protected function reset_plugin_state() {
		// Reset any global variables used by the plugin.
		global $polldaddy_object;
		if ( isset( $polldaddy_object ) ) {
			$polldaddy_object = null;
		}

		// Clear any cached data.
		wp_cache_flush();
	}

	/**
	 * Clean up test data created during tests.
	 */
	protected function cleanup_test_data() {
		// Remove any test options.
		delete_option( 'pd-rating-test' );
		delete_option( 'crowdsignal-test' );

		// Clear transients.
		delete_transient( 'polldaddy_test_cache' );
	}

	/**
	 * Create a test user with specific capabilities.
	 *
	 * @param array $capabilities Array of capabilities to grant.
	 * @return int User ID.
	 */
	protected function create_test_user( $capabilities = array( 'edit_posts' ) ) {
		$user_id = $this->factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		foreach ( $capabilities as $cap ) {
			$user->add_cap( $cap );
		}

		return $user_id;
	}

	/**
	 * Create a test poll.
	 *
	 * @param array $args Poll arguments.
	 * @return array Test poll data.
	 */
	protected function create_test_poll( $args = array() ) {
		$defaults = array(
			'title'    => 'Test Poll',
			'question' => 'Test Question?',
			'answers'  => array(
				'Answer 1',
				'Answer 2',
				'Answer 3',
			),
		);

		$poll_data = wp_parse_args( $args, $defaults );

		// Store in option for testing.
		$poll_id = wp_rand( 10000, 99999 );
		$poll_data['id'] = $poll_id;

		update_option( 'test_poll_' . $poll_id, $poll_data );

		return $poll_data;
	}

	/**
	 * Assert that a nonce is valid.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 */
	protected function assertNonceValid( $nonce, $action ) {
		$this->assertNotFalse(
			wp_verify_nonce( $nonce, $action ),
			"Nonce verification failed for action: {$action}"
		);
	}

	/**
	 * Assert that output is properly escaped.
	 *
	 * @param string $output HTML output to check.
	 * @param string $message Optional message.
	 */
	protected function assertProperlyEscaped( $output, $message = '' ) {
		// Check for unescaped script tags.
		$this->assertStringNotContainsString(
			'<script>',
			$output,
			$message ?: 'Output contains unescaped script tags'
		);

		// Check for unescaped event handlers.
		$this->assertDoesNotMatchRegularExpression(
			'/on\w+\s*=\s*["\']/',
			$output,
			$message ?: 'Output contains unescaped event handlers'
		);
	}

	/**
	 * Mock an AJAX request.
	 *
	 * @param string $action AJAX action.
	 * @param array  $data Request data.
	 * @param bool   $with_nonce Whether to include a valid nonce.
	 */
	protected function mock_ajax_request( $action, $data = array(), $with_nonce = true ) {
		// Set up the AJAX action.
		$_REQUEST['action'] = $action;
		$_POST['action']    = $action;

		// Add nonce if requested.
		if ( $with_nonce ) {
			$nonce_action         = 'polldaddy-' . $action;
			$_REQUEST['_wpnonce'] = wp_create_nonce( $nonce_action );
		}

		// Merge additional data.
		$_REQUEST = array_merge( $_REQUEST, $data );
		$_POST    = array_merge( $_POST, $data );

		// Set the AJAX constant.
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
	}

	/**
	 * Assert that a WordPress database query uses prepared statements.
	 *
	 * @param string $query The prepared query to check.
	 */
	protected function assertUsesPreparation( $query ) {
		// For a prepared query, we expect it to be properly formatted without placeholders.
		// This is more of a semantic check to ensure the test is using $wpdb->prepare().
		$this->assertIsString( $query, 'Query should be a string' );
		$this->assertNotEmpty( $query, 'Query should not be empty' );

		// Verify it doesn't contain unescaped placeholders (which would indicate improper use).
		$this->assertDoesNotMatchRegularExpression(
			'/(?<!%)%[sdF](?!%)/',
			$query,
			'Query contains unescaped placeholders - use $wpdb->prepare()'
		);
	}
}
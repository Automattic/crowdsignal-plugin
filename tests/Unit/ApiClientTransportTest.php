<?php
/**
 * Regression guard for the Crowdsignal API client transport scheme.
 *
 * The api_client base URL was set to https in Feb 2018 (issue #5) but was
 * reinstated as http:// when the dormant client came back in the Aug 2021
 * "Release v3" commit. This test pins the scheme to https so the API transport
 * cannot silently regress again.
 *
 * @package Automattic\Crowdsignal\Tests\Unit
 */

declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Transport regression tests for api_client.
 */
class ApiClientTransportTest extends TestCase {

	/**
	 * Load the real client class (no WordPress required to read the base URL).
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/polldaddy-client.php';
	}

	/**
	 * The API base URL must use the https scheme.
	 */
	public function test_api_base_url_uses_https() {
		$client = new \api_client( 'test-partner-guid', 'test-usercode' );

		$this->assertStringStartsWith(
			'https://',
			$client->polldaddy_url,
			'api_client::$polldaddy_url must use https.'
		);
	}
}

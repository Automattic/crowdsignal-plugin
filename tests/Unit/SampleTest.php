<?php
/**
 * Sample test to verify PHPUnit framework works
 *
 * @package Automattic\Crowdsignal\Tests\Unit
 */
declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sample test class
 */
class SampleTest extends TestCase {

	/**
	 * Test that PHPUnit is working
	 */
	public function test_phpunit_works() {
		$this->assertTrue( true );
	}

	/**
	 * Test basic PHP functionality
	 */
	public function test_basic_php_functionality() {
		$result = 2 + 2;
		$this->assertEquals( 4, $result );
	}
}

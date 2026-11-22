<?php
/**
 * Tests for plugin bootstrap compatibility.
 *
 * @package GatherPress_Schema
 */

namespace GatherPress_Schema\Tests;

use GatherPress_Schema\Output;
use WP_UnitTestCase;

/**
 * Class Test_Plugin.
 *
 * @coversNothing
 */
final class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Enforces the documented minimum GatherPress version.
	 *
	 * @return void
	 */
	public function test_gatherpress_version_compatibility(): void {
		$this->assertFalse( \gatherpress_schema_is_gatherpress_compatible( '0.36.0' ) );
		$this->assertTrue( \gatherpress_schema_is_gatherpress_compatible( '0.36.1' ) );
		$this->assertTrue( \gatherpress_schema_is_gatherpress_compatible( '0.37.0-alpha' ) );
		$this->assertTrue( \gatherpress_schema_is_gatherpress_compatible() );
	}

	/**
	 * The plugin boots with GatherPress and registers its namespace.
	 *
	 * @return void
	 */
	public function test_boots(): void {
		$this->assertArrayHasKey( 'GatherPress_Schema', \gatherpress_schema_autoloader( array() ) );
		$this->assertSame( 10, has_action( 'wp_head', array( Output::get_instance(), 'print_json_ld' ) ) );
	}
}

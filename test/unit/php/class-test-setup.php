<?php
/**
 * Tests for the Setup class.
 *
 * @package GatherPress_Schema
 */

namespace GatherPress_Schema\Tests;

use GatherPress_Schema\Output;
use GatherPress_Schema\Setup;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress_Schema\Setup
 */
final class Test_Setup extends WP_UnitTestCase {

	/**
	 * Setup boots the output, which registers the hooks.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		// The plugin built its instance before the tests started, so build another one here.
		$reflection = new ReflectionClass( Setup::class );
		$reflection->getConstructor()->invoke( $reflection->newInstanceWithoutConstructor() );

		$this->assertSame(
			Output::PRINT_PRIORITY,
			has_action( 'wp_head', array( Output::get_instance(), 'print_json_ld' ) )
		);
	}
}

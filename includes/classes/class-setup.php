<?php
/**
 * Plugin setup for GatherPress Schema.
 *
 * @package GatherPress_Schema
 * @since 0.1.0
 */

namespace GatherPress_Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * Boots the plugin's subsystems.
 *
 * @since 0.1.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {
		Output::get_instance();
	}
}

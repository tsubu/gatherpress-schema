<?php
/**
 * Plugin Name:       GatherPress Schema
 * Plugin URI:        https://github.com/GatherPress/gatherpress/issues/953
 * Description:       Schema.org Event structured data for GatherPress events, with Yoast SEO and Rank Math.
 * Author:            tsubu
 * Version:           0.1.0
 * Requires PHP:      8.1
 * Requires at least: 7.0
 * Requires Plugins:  gatherpress
 * Text Domain:       gatherpress-schema
 * Domain Path:       /languages
 * License:           GNU General Public License v2.0 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package GatherPress_Schema
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Bail when a sibling copy is already loaded (e.g., when WordPress includes a
// duplicate folder during activation).
if ( defined( 'GATHERPRESS_SCHEMA_VERSION' ) ) {
	return;
}

define(
	'GATHERPRESS_SCHEMA_VERSION',
	get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' )['Version']
);
define( 'GATHERPRESS_SCHEMA_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_SCHEMA_MIN_GATHERPRESS_VERSION', '0.36.1' );

/**
 * Adds the GatherPress_Schema namespace to the autoloader.
 *
 * @since 0.1.0
 *
 * @param array $namespaces An associative array of namespaces and their paths.
 *
 * @return array Modified array of namespaces and their paths.
 */
function gatherpress_schema_autoloader( array $namespaces ): array {
	$namespaces['GatherPress_Schema'] = GATHERPRESS_SCHEMA_CORE_PATH;

	return $namespaces;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_schema_autoloader' );

/**
 * Boots the GatherPress Schema runtime.
 *
 * Hooked on `gatherpress_loaded`, which GatherPress fires only after its own
 * requirements check passes, so the GatherPress classes this plugin uses are
 * guaranteed to be loadable.
 *
 * @since 0.1.0
 *
 * @return void
 */
function gatherpress_schema_setup(): void {
	if ( ! gatherpress_schema_is_gatherpress_compatible() ) {
		return;
	}

	GatherPress_Schema\Setup::get_instance();
}
add_action( 'gatherpress_loaded', 'gatherpress_schema_setup' );

/**
 * Checks whether the active GatherPress version meets the plugin requirement.
 *
 * @since 0.1.0
 *
 * @param string|null $version The version to check, or null for the active version.
 *
 * @return bool Whether the GatherPress version is supported.
 */
function gatherpress_schema_is_gatherpress_compatible( ?string $version = null ): bool {
	if ( null === $version ) {
		$version = defined( 'GATHERPRESS_VERSION' ) ? GATHERPRESS_VERSION : '';
	}

	return version_compare( $version, GATHERPRESS_SCHEMA_MIN_GATHERPRESS_VERSION, '>=' );
}

/**
 * Surfaces why GatherPress Schema is inert when GatherPress is absent or too old.
 *
 * Stays on `plugins_loaded` because it must run in exactly the case where
 * `gatherpress_loaded` never fires, including on GatherPress versions before 0.34.1.
 *
 * @since 0.1.0
 *
 * @return void
 */
function gatherpress_schema_maybe_notify_missing_gatherpress(): void {
	if ( ! defined( 'GATHERPRESS_VERSION' ) ) {
		$notice_type = 'missing';
	} elseif ( ! gatherpress_schema_is_gatherpress_compatible() ) {
		$notice_type = 'outdated';
	} else {
		return;
	}

	$show_notice = static function () use ( $notice_type ): void {
		if ( 'missing' === $notice_type ) {
			$message = esc_html__( 'GatherPress Schema requires GatherPress.', 'gatherpress-schema' );
		} else {
			$message = sprintf(
				/* translators: %s: minimum required GatherPress version. */
				esc_html__( 'GatherPress Schema requires GatherPress %s or later.', 'gatherpress-schema' ),
				esc_html( GATHERPRESS_SCHEMA_MIN_GATHERPRESS_VERSION )
			);
		}

		wp_admin_notice( $message, array( 'type' => 'error' ) );
	};

	add_action( 'admin_notices', $show_notice );
	add_action( 'network_admin_notices', $show_notice );
}
add_action( 'plugins_loaded', 'gatherpress_schema_maybe_notify_missing_gatherpress' );

/**
 * Announces this plugin to GatherPress's coexistence guard.
 *
 * @since 0.1.0
 *
 * @return void
 */
function gatherpress_schema_register_coexistence_guard(): void {
	do_action(
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- GatherPress's own action.
		'gatherpress_register_coexistence_guard',
		'gatherpress-schema',
		'GatherPress Schema',
		__FILE__
	);
}
add_action( 'gatherpress_loaded', 'gatherpress_schema_register_coexistence_guard' );

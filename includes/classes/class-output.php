<?php
/**
 * Adds the schema.org Event to the page.
 *
 * @package GatherPress_Schema
 * @since 0.1.0
 */

namespace GatherPress_Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Output.
 *
 * Puts the Event on the page of a single event, in one place only, so a page
 * never has two competing Event entities:
 *
 * - With Yoast SEO, the Event joins Yoast's schema graph.
 * - With Rank Math, the Event joins Rank Math's JSON-LD.
 * - With neither, the plugin prints its own JSON-LD in the head.
 *
 * @since 0.1.0
 */
final class Output {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The key of the Event in Rank Math's JSON-LD data.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const RANK_MATH_KEY = 'GatherPressEvent';

	/**
	 * Constructor for the Output class.
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Sets up the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'wpseo_schema_graph', array( $this, 'add_to_yoast_graph' ), 10, 2 );
		add_filter( 'rank_math/json_ld', array( $this, 'add_to_rank_math' ), 99 );
		add_action( 'wp_head', array( $this, 'print_json_ld' ) );
	}

	/**
	 * Returns the Event of the current request, or an empty array.
	 *
	 * Only the page of a single event has one.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> The Event, or an empty array.
	 */
	public function get_current_event(): array {
		if ( ! is_singular() ) {
			return array();
		}

		return Event_Schema::get_instance()->get_event( (int) get_queried_object_id() );
	}

	/**
	 * Adds the Event to Yoast SEO's schema graph.
	 *
	 * The Event is the main entity of Yoast's WebPage piece.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $graph   The schema pieces.
	 * @param mixed $context Yoast's Meta_Tags_Context.
	 *
	 * @return mixed The schema pieces.
	 */
	public function add_to_yoast_graph( $graph, $context = null ) {
		$event = $this->get_current_event();

		if ( ! is_array( $graph ) || empty( $event ) ) {
			return $graph;
		}

		if ( is_object( $context ) && ! empty( $context->main_schema_id ) && is_string( $context->main_schema_id ) ) {
			$event['mainEntityOfPage'] = array( '@id' => $context->main_schema_id );
		}

		$graph[] = $event;

		return $graph;
	}

	/**
	 * Adds the Event to Rank Math's JSON-LD.
	 *
	 * The Event is the main entity of Rank Math's WebPage, when there is one.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $data Rank Math's JSON-LD data, keyed by entity.
	 *
	 * @return mixed Rank Math's JSON-LD data.
	 */
	public function add_to_rank_math( $data ) {
		$event = $this->get_current_event();

		if ( ! is_array( $data ) || empty( $event ) ) {
			return $data;
		}

		if ( ! empty( $data['WebPage']['@id'] ) && is_string( $data['WebPage']['@id'] ) ) {
			$event['mainEntityOfPage'] = array( '@id' => $data['WebPage']['@id'] );
		}

		$data[ self::RANK_MATH_KEY ] = $event;

		return $data;
	}

	/**
	 * Whether the plugin prints its own JSON-LD.
	 *
	 * It does when no SEO plugin that it joins is active.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether to print the JSON-LD.
	 */
	public function should_print_json_ld(): bool {
		$has_seo_plugin = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );

		/**
		 * Filters whether GatherPress Schema prints its own JSON-LD.
		 *
		 * By default it does when neither Yoast SEO nor Rank Math is active.
		 * Return false when another plugin already outputs the Event, or
		 * true to print it next to an SEO plugin that this plugin does not
		 * join.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $print Whether to print the JSON-LD.
		 */
		return (bool) apply_filters( 'gatherpress_schema_print_json_ld', ! $has_seo_plugin );
	}

	/**
	 * Prints the Event as JSON-LD in the head.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function print_json_ld(): void {
		if ( ! $this->should_print_json_ld() ) {
			return;
		}

		$event = $this->get_current_event();

		if ( empty( $event ) ) {
			return;
		}

		$json = wp_json_encode(
			array_merge( array( '@context' => 'https://schema.org' ), $event ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);

		if ( false === $json ) {
			return; // @codeCoverageIgnore
		}

		// The flags hex-escape <, > and &, so a value cannot close the script tag.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script type="application/ld+json" class="gatherpress-schema">' . $json . "</script>\n";
	}
}

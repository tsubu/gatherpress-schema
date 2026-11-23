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
 * - When Yoast SEO builds its schema graph, the Event joins it.
 * - When Rank Math builds its JSON-LD, the Event joins it.
 * - Otherwise the plugin prints its own JSON-LD in the head.
 *
 * What counts is whether an SEO plugin built its schema on this page, not
 * whether it is active. Rank Math before its setup wizard, or Yoast SEO with
 * its schema turned off, builds none, and then the plugin prints its own.
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
	 * The priority of the standalone JSON-LD on `wp_head`.
	 *
	 * Yoast SEO and Rank Math build their schema on `wp_head` at priority 1,
	 * so by this priority the plugin knows whether one of them took the Event.
	 *
	 * @since 0.1.0
	 *
	 * @var int
	 */
	const PRINT_PRIORITY = 99;

	/**
	 * Whether an SEO plugin's schema on this request has the Event.
	 *
	 * @since 0.1.0
	 *
	 * @var bool
	 */
	private bool $handled = false;

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
		add_action( 'wp', array( $this, 'reset' ) );
		add_filter( 'wpseo_schema_graph', array( $this, 'add_to_yoast_graph' ), 10, 2 );
		add_filter( 'rank_math/json_ld', array( $this, 'add_to_rank_math' ), 99 );
		add_action( 'wp_head', array( $this, 'print_json_ld' ), self::PRINT_PRIORITY );
	}

	/**
	 * Forgets whether an SEO plugin took the Event, at the start of a request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->handled = false;
	}

	/**
	 * Whether an SEO plugin's schema on this request has the Event.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether the Event is handled.
	 */
	public function is_handled(): bool {
		return $this->handled;
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
	 * Whether a list of schema nodes already has an event.
	 *
	 * An event is a node of the type `Event` or of one of its subtypes, such
	 * as `MusicEvent` or `Festival`. A page that has one, for example from an
	 * Event schema set up in the SEO plugin, gets no second one.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int|string, mixed> $nodes The schema nodes.
	 *
	 * @return bool Whether one of the nodes is an event.
	 */
	public function has_event( array $nodes ): bool {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
				continue;
			}

			foreach ( (array) $node['@type'] as $type ) {
				if (
					is_string( $type )
					&& ( str_ends_with( $type, 'Event' ) || in_array( $type, array( 'Festival', 'Hackathon' ), true ) )
				) {
					return true;
				}
			}
		}

		return false;
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
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$event = $this->get_current_event();

		if ( empty( $event ) ) {
			return $graph;
		}

		// The graph has the page's Event either way, so the plugin prints none of its own.
		$this->handled = true;

		if ( $this->has_event( $graph ) ) {
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
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$event = $this->get_current_event();

		if ( empty( $event ) ) {
			return $data;
		}

		// The data has the page's Event either way, so the plugin prints none of its own.
		$this->handled = true;

		if ( $this->has_event( $data ) ) {
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
	 * It does when no SEO plugin's schema on this page took the Event.
	 *
	 * @since 0.1.0
	 *
	 * @return bool Whether to print the JSON-LD.
	 */
	public function should_print_json_ld(): bool {
		/**
		 * Filters whether GatherPress Schema prints its own JSON-LD.
		 *
		 * By default it does when neither the Yoast SEO graph nor the Rank Math
		 * JSON-LD on the page took the Event. Return false when another plugin,
		 * such as another SEO plugin, already outputs the Event.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $print Whether to print the JSON-LD.
		 */
		return (bool) apply_filters( 'gatherpress_schema_print_json_ld', ! $this->handled );
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

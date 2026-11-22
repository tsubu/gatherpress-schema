<?php
/**
 * Tests for the Output class.
 *
 * @package GatherPress_Schema
 */

namespace GatherPress_Schema\Tests;

use GatherPress\Core\Event\Event;
use GatherPress_Schema\Output;
use WP_UnitTestCase;

/**
 * Class Test_Output.
 *
 * @coversDefaultClass \GatherPress_Schema\Output
 */
final class Test_Output extends WP_UnitTestCase {

	/**
	 * Removes the header callbacks before go_to() runs them.
	 *
	 * The test bootstrap has printed output, so a callback that sends a
	 * header, such as GatherPress's own, would fail. The test case restores
	 * the hooks after each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		remove_all_actions( 'send_headers' );
	}

	/**
	 * Creates a published event and opens its page.
	 *
	 * @param string $title The event title.
	 *
	 * @return int The event ID.
	 */
	private function go_to_event( string $title = 'Block Themes Meetup' ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-05-12 18:30:00',
				'datetime_end'   => '2030-05-12 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		return $post_id;
	}

	/**
	 * The hooks are registered.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Output::get_instance();

		$this->assertSame( 10, has_filter( 'wpseo_schema_graph', array( $instance, 'add_to_yoast_graph' ) ) );
		$this->assertSame( 99, has_filter( 'rank_math/json_ld', array( $instance, 'add_to_rank_math' ) ) );
		$this->assertSame( 10, has_action( 'wp_head', array( $instance, 'print_json_ld' ) ) );
	}

	/**
	 * Only the page of a single event has an Event.
	 *
	 * @covers ::get_current_event
	 *
	 * @return void
	 */
	public function test_get_current_event(): void {
		$instance = Output::get_instance();

		$this->go_to( home_url( '/' ) );

		$this->assertSame( array(), $instance->get_current_event(), 'Failed to assert the home page has no Event.' );

		$this->go_to( get_permalink( self::factory()->post->create() ) );

		$this->assertSame( array(), $instance->get_current_event(), 'Failed to assert a post has no Event.' );

		$post_id = $this->go_to_event();

		$this->assertSame( get_permalink( $post_id ), $instance->get_current_event()['url'] );
	}

	/**
	 * The Event joins Yoast's graph as the main entity of its WebPage.
	 *
	 * @covers ::add_to_yoast_graph
	 *
	 * @return void
	 */
	public function test_add_to_yoast_graph(): void {
		$instance = Output::get_instance();
		$webpage  = array(
			'@type' => 'WebPage',
			'@id'   => 'https://example.com/event/meetup/',
		);

		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			array( $webpage ),
			$instance->add_to_yoast_graph( array( $webpage ) ),
			'Failed to assert the home page graph is unchanged.'
		);

		$this->go_to_event();

		$this->assertSame( 'not-a-graph', $instance->add_to_yoast_graph( 'not-a-graph' ) );

		$context                 = new \stdClass();
		$context->main_schema_id = 'https://example.com/event/meetup/';
		$graph                   = $instance->add_to_yoast_graph( array( $webpage ), $context );

		$this->assertCount( 2, $graph );
		$this->assertSame( 'Event', $graph[1]['@type'] );
		$this->assertSame( array( '@id' => 'https://example.com/event/meetup/' ), $graph[1]['mainEntityOfPage'] );

		$graph = $instance->add_to_yoast_graph( array( $webpage ) );

		$this->assertArrayNotHasKey(
			'mainEntityOfPage',
			$graph[1],
			'Failed to assert no context gives no main entity link.'
		);
	}

	/**
	 * The Event joins Rank Math's JSON-LD as the main entity of its WebPage.
	 *
	 * @covers ::add_to_rank_math
	 *
	 * @return void
	 */
	public function test_add_to_rank_math(): void {
		$instance = Output::get_instance();
		$data     = array(
			'WebPage' => array(
				'@type' => 'WebPage',
				'@id'   => 'https://example.com/event/meetup/#webpage',
			),
		);

		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			$data,
			$instance->add_to_rank_math( $data ),
			'Failed to assert the home page data is unchanged.'
		);

		$this->go_to_event();

		$this->assertSame( 'not-data', $instance->add_to_rank_math( 'not-data' ) );

		$result = $instance->add_to_rank_math( $data );

		$this->assertSame( 'Event', $result[ Output::RANK_MATH_KEY ]['@type'] );
		$this->assertSame(
			array( '@id' => 'https://example.com/event/meetup/#webpage' ),
			$result[ Output::RANK_MATH_KEY ]['mainEntityOfPage']
		);

		$result = $instance->add_to_rank_math( array() );

		$this->assertArrayNotHasKey( 'mainEntityOfPage', $result[ Output::RANK_MATH_KEY ] );
	}

	/**
	 * Without an SEO plugin the JSON-LD prints, and a filter can stop it.
	 *
	 * @covers ::should_print_json_ld
	 *
	 * @return void
	 */
	public function test_should_print_json_ld(): void {
		$instance = Output::get_instance();

		$this->assertTrue( $instance->should_print_json_ld() );

		add_filter( 'gatherpress_schema_print_json_ld', '__return_false' );
		$print = $instance->should_print_json_ld();
		remove_filter( 'gatherpress_schema_print_json_ld', '__return_false' );

		$this->assertFalse( $print );
	}

	/**
	 * The page of an event prints its JSON-LD, escaped so it cannot close the
	 * script tag. Other pages print nothing.
	 *
	 * @covers ::print_json_ld
	 *
	 * @return void
	 */
	public function test_print_json_ld(): void {
		$instance = Output::get_instance();

		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			'',
			get_echo( array( $instance, 'print_json_ld' ) ),
			'Failed to assert the home page prints nothing.'
		);

		$post_id = $this->go_to_event( 'Q&A night' );
		$add     = static function ( array $data ): array {
			$data['disambiguatingDescription'] = '</script><script>alert(1)</script>';

			return $data;
		};

		add_filter( 'gatherpress_schema_event', $add );
		$output = get_echo( array( $instance, 'print_json_ld' ) );
		remove_filter( 'gatherpress_schema_event', $add );

		$this->assertStringStartsWith( '<script type="application/ld+json" class="gatherpress-schema">', $output );
		$this->assertSame(
			1,
			substr_count( $output, '</script>' ),
			'Failed to assert a value cannot close the script tag.'
		);

		$json = json_decode( substr( $output, strpos( $output, '>' ) + 1, -strlen( "</script>\n" ) ), true );

		$this->assertSame( 'https://schema.org', $json['@context'] );
		$this->assertSame( 'Event', $json['@type'] );
		$this->assertSame( get_permalink( $post_id ), $json['url'] );
		$this->assertSame( '</script><script>alert(1)</script>', $json['disambiguatingDescription'] );

		add_filter( 'gatherpress_schema_print_json_ld', '__return_false' );
		$output = get_echo( array( $instance, 'print_json_ld' ) );
		remove_filter( 'gatherpress_schema_print_json_ld', '__return_false' );

		$this->assertSame( '', $output, 'Failed to assert the filter stops the JSON-LD.' );
	}
}

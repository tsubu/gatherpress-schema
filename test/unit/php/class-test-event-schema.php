<?php
/**
 * Tests for the Event_Schema class.
 *
 * @package GatherPress_Schema
 */

namespace GatherPress_Schema\Tests;

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Venue\Venue;
use GatherPress_Schema\Event_Schema;
use WP_UnitTestCase;

/**
 * Class Test_Event_Schema.
 *
 * @coversDefaultClass \GatherPress_Schema\Event_Schema
 */
final class Test_Event_Schema extends WP_UnitTestCase {

	/**
	 * Creates a published event with dates.
	 *
	 * @param array<string, mixed> $args     Post arguments.
	 * @param string               $start    Start, in the site time zone.
	 * @param string               $end      End, in the site time zone.
	 *
	 * @return int The event ID.
	 */
	private function create_event(
		array $args = array(),
		string $start = '2030-05-12 18:30:00',
		string $end = '2030-05-12 20:00:00'
	): int {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => Event::POST_TYPE,
					'post_title'   => 'Block Themes Meetup',
					'post_status'  => 'publish',
					// The test factory writes an excerpt unless one is given.
					'post_excerpt' => '',
				),
				$args
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return $post_id;
	}

	/**
	 * Creates a venue and puts an event at it.
	 *
	 * @param int                   $event_id The event ID.
	 * @param array<string, string> $meta     Venue fields, without the prefix.
	 * @param array<string, mixed>  $args     Post arguments.
	 *
	 * @return int The venue ID.
	 */
	private function create_venue( int $event_id, array $meta, array $args = array() ): int {
		$venue_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_title'  => 'Community Hall',
					'post_name'   => 'community-hall',
					'post_status' => 'publish',
				),
				$args
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $venue_id, 'gatherpress_' . $key, $value );
		}

		// Append, so an online event keeps its online-event term in the same taxonomy.
		wp_set_object_terms( $event_id, '_community-hall', '_' . Venue::POST_TYPE, true );

		return $venue_id;
	}

	/**
	 * Posts that are not public events give no Event.
	 *
	 * @covers ::get_event
	 * @covers ::is_public_event
	 *
	 * @return void
	 */
	public function test_get_event_without_public_event(): void {
		$instance = Event_Schema::get_instance();

		$this->assertSame( array(), $instance->get_event( 0 ), 'Failed to assert a missing post gives no Event.' );
		$this->assertSame(
			array(),
			$instance->get_event( self::factory()->post->create() ),
			'Failed to assert a post gives no Event.'
		);
		$this->assertSame(
			array(),
			$instance->get_event( $this->create_event( array( 'post_status' => 'draft' ) ) ),
			'Failed to assert a draft event gives no Event.'
		);
		$this->assertSame(
			array(),
			$instance->get_event( $this->create_event( array( 'post_password' => 'secret' ) ) ),
			'Failed to assert a password-protected event gives no Event.'
		);

		$without_dates = self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertSame(
			array(),
			$instance->get_event( $without_dates ),
			'Failed to assert an event without dates gives no Event.'
		);
	}

	/**
	 * An event gives its name, URL, dates and status.
	 *
	 * @covers ::get_event
	 * @covers ::get_status
	 *
	 * @return void
	 */
	public function test_get_event(): void {
		$event_id = $this->create_event( array( 'post_title' => 'Tom &amp; Jerry <em>Night</em>' ) );
		$url      = get_permalink( $event_id );
		$event    = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertSame( 'Event', $event['@type'] );
		$this->assertSame( $url . '#/schema/gatherpress-event', $event['@id'] );
		$this->assertSame(
			'Tom & Jerry Night',
			$event['name'],
			'Failed to assert the name is plain text, without tags or entities.'
		);
		$this->assertSame( $url, $event['url'] );
		$this->assertSame( '2030-05-12T18:30:00+00:00', $event['startDate'] );
		$this->assertSame( '2030-05-12T20:00:00+00:00', $event['endDate'] );
		$this->assertSame( 'https://schema.org/EventScheduled', $event['eventStatus'] );
		$this->assertArrayNotHasKey( 'location', $event, 'Failed to assert an event without a venue has no location.' );
		$this->assertArrayNotHasKey( 'eventAttendanceMode', $event );
		$this->assertArrayNotHasKey(
			'description',
			$event,
			'Failed to assert an event without an excerpt has no description.'
		);
		$this->assertArrayNotHasKey( 'image', $event );
	}

	/**
	 * A canceled event gives the EventCancelled status.
	 *
	 * @covers ::get_status
	 *
	 * @return void
	 */
	public function test_get_status_canceled(): void {
		if ( ! class_exists( Status::class ) ) {
			$this->markTestSkipped( 'This GatherPress has no event statuses.' );
		}

		$event_id = $this->create_event();

		( new Status( $event_id ) )->set( Status::STATUS_CANCELED );

		$this->assertSame( 'EventCancelled', Event_Schema::get_instance()->get_status( $event_id ) );
	}

	/**
	 * An excerpt gives the description, and a featured image the image.
	 *
	 * @covers ::get_event
	 * @covers ::get_description
	 *
	 * @return void
	 */
	public function test_get_event_description_and_image(): void {
		$event_id      = $this->create_event(
			array( 'post_excerpt' => "Talks about <strong>block themes</strong> &amp; Q&#038;A. It's free." )
		);
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg',
			$event_id
		);

		set_post_thumbnail( $event_id, $attachment_id );

		$event = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertSame(
			"Talks about block themes & Q&A. It's free.",
			$event['description'],
			'Failed to assert the description is plain text, without tags or entities.'
		);
		$this->assertSame( wp_get_attachment_image_url( $attachment_id, 'full' ), $event['image'] );
	}

	/**
	 * A venue gives a Place and the offline attendance mode.
	 *
	 * @covers ::get_event
	 * @covers ::get_location
	 * @covers ::get_place
	 * @covers ::get_postal_address
	 * @covers ::get_street_address
	 *
	 * @return void
	 */
	public function test_get_event_at_venue(): void {
		$event_id = $this->create_event();
		$venue_id = $this->create_venue(
			$event_id,
			array(
				'address'      => '221B Baker Street, London NW1 6XE, UK',
				'house_number' => '221B',
				'street'       => 'Baker Street',
				'city'         => 'London',
				'postcode'     => 'NW1 6XE',
				'country'      => 'United Kingdom',
				'country_code' => 'gb',
				'latitude'     => '51.5237',
				'longitude'    => '-0.1585',
				'phone'        => '+44 20 7224 3688',
				'website'      => 'https://example.com/hall',
			)
		);

		$event = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', $event['eventAttendanceMode'] );
		$this->assertSame(
			array(
				'@type'     => 'Place',
				'name'      => 'Community Hall',
				'address'   => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => '221B Baker Street',
					'addressLocality' => 'London',
					'postalCode'      => 'NW1 6XE',
					'addressCountry'  => 'GB',
				),
				'url'       => get_permalink( $venue_id ),
				'geo'       => array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => 51.5237,
					'longitude' => -0.1585,
				),
				'telephone' => '+44 20 7224 3688',
				'sameAs'    => 'https://example.com/hall',
			),
			$event['location']
		);
	}

	/**
	 * A password-protected venue gives no Place.
	 *
	 * @covers ::get_location
	 * @covers ::get_place
	 *
	 * @return void
	 */
	public function test_get_event_at_protected_venue(): void {
		$event_id = $this->create_event();

		$this->create_venue( $event_id, array( 'address' => 'Secret Street 1' ), array( 'post_password' => 'secret' ) );

		$event = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertArrayNotHasKey( 'location', $event );
	}

	/**
	 * An online event gives a VirtualLocation at the event page, and an
	 * online event at a venue is a mixed event.
	 *
	 * @covers ::get_location
	 *
	 * @return void
	 */
	public function test_get_event_online(): void {
		$event_id = $this->create_event();
		$url      = get_permalink( $event_id );

		( new Event( $event_id ) )->set_online( true, 'https://meet.example.com/secret-room' );

		$event = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertSame( 'https://schema.org/OnlineEventAttendanceMode', $event['eventAttendanceMode'] );
		$this->assertSame(
			array(
				'@type' => 'VirtualLocation',
				'url'   => $url,
			),
			$event['location'],
			'Failed to assert the virtual location is the event page, not the link to join.'
		);

		$this->create_venue( $event_id, array( 'address' => 'Main Street 1' ) );

		$event = Event_Schema::get_instance()->get_event( $event_id );

		$this->assertSame( 'https://schema.org/MixedEventAttendanceMode', $event['eventAttendanceMode'] );
		$this->assertCount( 2, $event['location'] );
		$this->assertSame( 'Place', $event['location'][0]['@type'] );
		$this->assertSame( 'VirtualLocation', $event['location'][1]['@type'] );
	}

	/**
	 * A filter can change the Event, and a value that is not an Event, such
	 * as an empty array or false, gives no Event.
	 *
	 * @covers ::get_event
	 *
	 * @return void
	 */
	public function test_get_event_filter(): void {
		$event_id = $this->create_event();
		$add      = static function ( array $data ): array {
			$data['isAccessibleForFree'] = true;

			return $data;
		};

		add_filter( 'gatherpress_schema_event', $add );
		$event = Event_Schema::get_instance()->get_event( $event_id );
		remove_filter( 'gatherpress_schema_event', $add );

		$this->assertTrue( $event['isAccessibleForFree'] );

		foreach ( array( '__return_empty_array', '__return_false' ) as $callback ) {
			add_filter( 'gatherpress_schema_event', $callback );
			$event = Event_Schema::get_instance()->get_event( $event_id );
			remove_filter( 'gatherpress_schema_event', $callback );

			$this->assertSame( array(), $event, "Failed to assert {$callback} gives no Event." );
		}
	}

	/**
	 * A venue needs a name and an address to be a Place.
	 *
	 * @covers ::get_place
	 *
	 * @return void
	 */
	public function test_get_place_needs_name_and_address(): void {
		$instance = Event_Schema::get_instance();

		$this->assertSame( array(), $instance->get_place( array( 'address' => 'Main Street 1' ) ) );
		$this->assertSame( array(), $instance->get_place( array( 'name' => 'Online event' ) ) );
		$this->assertArrayNotHasKey(
			'geo',
			$instance->get_place(
				array(
					'name'      => 'Hall',
					'address'   => 'Main Street 1',
					'latitude'  => '',
					'longitude' => '12.5',
				)
			),
			'Failed to assert a venue without both coordinates has no geo.'
		);
	}

	/**
	 * The PostalAddress uses the country name without a country code, and is
	 * empty without any field.
	 *
	 * @covers ::get_postal_address
	 *
	 * @return void
	 */
	public function test_get_postal_address(): void {
		$instance = Event_Schema::get_instance();

		$this->assertSame( array(), $instance->get_postal_address( array( 'address' => '  ' ) ) );
		$this->assertSame(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Hauptstraße 5',
				'addressLocality' => 'Berlin',
				'addressRegion'   => 'Berlin',
				'addressCountry'  => 'Deutschland',
			),
			$instance->get_postal_address(
				array(
					'street'       => 'Hauptstraße',
					'house_number' => '5',
					'city'         => 'Berlin',
					'state'        => 'Berlin',
					'country'      => 'Deutschland',
				)
			)
		);
	}

	/**
	 * The street address puts the house number where the country does, and
	 * falls back to the typed address.
	 *
	 * @covers ::get_street_address
	 *
	 * @return void
	 */
	public function test_get_street_address(): void {
		$instance = Event_Schema::get_instance();

		$this->assertSame(
			'1600 Pennsylvania Avenue NW',
			$instance->get_street_address(
				array(
					'street'       => 'Pennsylvania Avenue NW',
					'house_number' => '1600',
					'country_code' => 'US',
				)
			)
		);
		$this->assertSame(
			'Hauptstraße 5',
			$instance->get_street_address(
				array(
					'street'       => 'Hauptstraße',
					'house_number' => '5',
					'country_code' => 'de',
				)
			)
		);
		$this->assertSame( 'Hauptstraße', $instance->get_street_address( array( 'street' => 'Hauptstraße' ) ) );
		$this->assertSame(
			'1-1 Chiyoda, Tokyo',
			$instance->get_street_address( array( 'address' => ' 1-1 Chiyoda, Tokyo ' ) )
		);

		$filter = static fn(): string => 'Filtered Street 1';

		add_filter( 'gatherpress_schema_street_address', $filter );
		$filtered = $instance->get_street_address( array( 'street' => 'Hauptstraße' ) );
		remove_filter( 'gatherpress_schema_street_address', $filter );

		$this->assertSame( 'Filtered Street 1', $filtered );

		add_filter( 'gatherpress_schema_street_address', '__return_null' );
		$filtered = $instance->get_street_address( array( 'street' => 'Hauptstraße' ) );
		remove_filter( 'gatherpress_schema_street_address', '__return_null' );

		$this->assertSame(
			'',
			$filtered,
			'Failed to assert a filtered value that is not a string gives no street address.'
		);
	}
}

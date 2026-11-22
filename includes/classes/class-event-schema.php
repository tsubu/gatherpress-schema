<?php
/**
 * Builds the schema.org Event of a GatherPress event.
 *
 * @package GatherPress_Schema
 * @since 0.1.0
 */

namespace GatherPress_Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Event_Schema.
 *
 * The one place that turns an event into a schema.org `Event`, from the
 * GatherPress data model rather than from rendered blocks. The output
 * integrations (Yoast SEO, Rank Math, the standalone JSON-LD) all read it.
 *
 * @since 0.1.0
 */
final class Event_Schema {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The schema.org vocabulary URL, which enumeration values are prefixed with.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const SCHEMA_ORG = 'https://schema.org/';

	/**
	 * Country codes where the house number comes before the street.
	 *
	 * In most other countries it comes after the street. A site can change the
	 * result with the `gatherpress_schema_street_address` filter.
	 *
	 * @since 0.1.0
	 *
	 * @var string[]
	 */
	const HOUSE_NUMBER_FIRST = array( 'au', 'ca', 'gb', 'ie', 'in', 'my', 'nz', 'ph', 'sg', 'us', 'za' );

	/**
	 * Constructor for the Event_Schema class.
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {}

	/**
	 * Returns the schema.org Event of a post, or an empty array.
	 *
	 * The array is empty when the post is not an event that the public can
	 * see: a post type without `gatherpress-event-date` support, a post that is
	 * not published, a password-protected post, an event that GatherPress does
	 * not consider viewable, or an event without a start date.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, mixed> The Event, or an empty array.
	 */
	public function get_event( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $this->is_public_event( $post ) ) {
			return array();
		}

		$event = new Event( $post->ID );
		$start = $event->get_datetime_start_iso();

		if ( '' === $start ) {
			return array();
		}

		$url  = (string) get_permalink( $post );
		$data = array(
			'@type'     => 'Event',
			'@id'       => $url . '#/schema/gatherpress-event',
			'name'      => $this->plain_text( get_the_title( $post ) ),
			'url'       => $url,
			'startDate' => $start,
		);

		$end = $event->get_datetime_end_iso();

		if ( '' !== $end ) {
			$data['endDate'] = $end;
		}

		$data['eventStatus'] = self::SCHEMA_ORG . $this->get_status( $post->ID );

		$data = array_merge( $data, $this->get_location( $event, $url ) );

		$description = $this->get_description( $post );

		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );

		if ( is_string( $image ) && '' !== $image ) {
			$data['image'] = $image;
		}

		/**
		 * Filters the schema.org Event of a GatherPress event.
		 *
		 * Return an empty array to leave the event out of every output.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $data The Event.
		 * @param WP_Post              $post The event post.
		 */
		$data = (array) apply_filters( 'gatherpress_schema_event', $data, $post );

		// A filter that returns something other than an Event leaves it out.
		return empty( $data['@type'] ) ? array() : $data;
	}

	/**
	 * Whether a post is an event that the public can see.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post|null $post The post.
	 *
	 * @return bool Whether the post is a public event.
	 *
	 * @phpstan-assert-if-true WP_Post $post
	 */
	public function is_public_event( ?WP_Post $post ): bool {
		return $post instanceof WP_Post
			&& post_type_supports( $post->post_type, Event::SUPPORT )
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& Event::is_viewable( $post->ID );
	}

	/**
	 * Returns the schema.org EventStatusType of an event, without the vocabulary URL.
	 *
	 * GatherPress 0.37.0 adds event statuses that map to schema.org values. An
	 * older GatherPress has no statuses, so every event is scheduled.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string The status, such as `EventScheduled` or `EventCancelled`.
	 */
	public function get_status( int $post_id ): string {
		if ( ! class_exists( Status::class ) ) {
			return 'EventScheduled'; // @codeCoverageIgnore
		}

		$status = ( new Status( $post_id ) )->get_schema();

		return '' !== $status ? $status : Status::DEFAULT_SCHEMA;
	}

	/**
	 * Returns the location and the attendance mode of an event.
	 *
	 * A venue gives a `Place`. An online event gives a `VirtualLocation` with
	 * the event page as its URL, because the link to join is only for
	 * attendees. An event with both is a mixed event.
	 *
	 * @since 0.1.0
	 *
	 * @param Event  $event The event.
	 * @param string $url   The URL of the event page.
	 *
	 * @return array<string, mixed> The `location` and `eventAttendanceMode` properties, or an empty array.
	 */
	public function get_location( Event $event, string $url ): array {
		$locations = array();
		$place     = $this->get_place( $event->get_public_venue_information() );

		if ( ! empty( $place ) ) {
			$locations[] = $place;
		}

		if ( $event->is_online() ) {
			$locations[] = array(
				'@type' => 'VirtualLocation',
				'url'   => $url,
			);
		}

		if ( empty( $locations ) ) {
			return array();
		}

		if ( 2 === count( $locations ) ) {
			$mode = 'MixedEventAttendanceMode';
		} elseif ( 'VirtualLocation' === $locations[0]['@type'] ) {
			$mode = 'OnlineEventAttendanceMode';
		} else {
			$mode = 'OfflineEventAttendanceMode';
		}

		return array(
			'eventAttendanceMode' => self::SCHEMA_ORG . $mode,
			'location'            => 1 === count( $locations ) ? $locations[0] : $locations,
		);
	}

	/**
	 * Returns the schema.org Place of a venue, or an empty array.
	 *
	 * A venue needs a name and an address to be a Place. The online-event
	 * term gives a name but no address, so it is not a Place.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, string> $venue The venue information from GatherPress.
	 *
	 * @return array<string, mixed> The Place, or an empty array.
	 */
	public function get_place( array $venue ): array {
		$name    = $this->plain_text( $venue['name'] ?? '' );
		$address = $this->get_postal_address( $venue );

		if ( '' === $name || empty( $address ) ) {
			return array();
		}

		$place = array(
			'@type'   => 'Place',
			'name'    => $name,
			'address' => $address,
		);

		if ( ! empty( $venue['permalink'] ) ) {
			$place['url'] = $venue['permalink'];
		}

		$latitude  = $venue['latitude'] ?? '';
		$longitude = $venue['longitude'] ?? '';

		if ( is_numeric( $latitude ) && is_numeric( $longitude ) ) {
			$place['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $latitude,
				'longitude' => (float) $longitude,
			);
		}

		if ( ! empty( $venue['phone'] ) ) {
			$place['telephone'] = $venue['phone'];
		}

		if ( ! empty( $venue['website'] ) ) {
			$place['sameAs'] = $venue['website'];
		}

		return $place;
	}

	/**
	 * Returns the schema.org PostalAddress of a venue, or an empty array.
	 *
	 * The structured address fields come from the address autocomplete. A
	 * typed address leaves them blank, and then the whole address is the
	 * street address.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, string> $venue The venue information from GatherPress.
	 *
	 * @return array<string, string> The PostalAddress, or an empty array.
	 */
	public function get_postal_address( array $venue ): array {
		$fields = array(
			'streetAddress'   => $this->get_street_address( $venue ),
			'addressLocality' => $venue['city'] ?? '',
			'addressRegion'   => $venue['state'] ?? '',
			'postalCode'      => $venue['postcode'] ?? '',
			'addressCountry'  => strtoupper( $venue['country_code'] ?? '' ),
		);

		if ( '' === $fields['addressCountry'] ) {
			$fields['addressCountry'] = $venue['country'] ?? '';
		}

		$fields = array_filter(
			array_map( 'trim', $fields ),
			static fn( string $value ): bool => '' !== $value
		);

		if ( empty( $fields ) ) {
			return array();
		}

		return array_merge( array( '@type' => 'PostalAddress' ), $fields );
	}

	/**
	 * Returns the street address of a venue.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, string> $venue The venue information from GatherPress.
	 *
	 * @return string The street address, or an empty string.
	 */
	public function get_street_address( array $venue ): string {
		$street       = trim( $venue['street'] ?? '' );
		$house_number = trim( $venue['house_number'] ?? '' );

		if ( '' === $street ) {
			$street_address = trim( $venue['address'] ?? '' );
		} elseif ( '' === $house_number ) {
			$street_address = $street;
		} elseif ( in_array( strtolower( $venue['country_code'] ?? '' ), self::HOUSE_NUMBER_FIRST, true ) ) {
			$street_address = $house_number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $house_number;
		}

		/**
		 * Filters the street address of a venue in the schema.org Event.
		 *
		 * @since 0.1.0
		 *
		 * @param string                $street_address The street address.
		 * @param array<string, string> $venue          The venue information from GatherPress.
		 */
		return (string) apply_filters( 'gatherpress_schema_street_address', $street_address, $venue );
	}

	/**
	 * Returns the description of an event: its excerpt as plain text.
	 *
	 * Only a hand-written excerpt is used. The content of an event is mostly
	 * blocks, such as the date, the venue and the RSVP form, so an excerpt
	 * made from it says little.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The event post.
	 *
	 * @return string The description, or an empty string.
	 */
	public function get_description( WP_Post $post ): string {
		if ( '' === trim( $post->post_excerpt ) ) {
			return '';
		}

		return $this->plain_text( get_the_excerpt( $post ) );
	}

	/**
	 * Turns text for display into plain text.
	 *
	 * Titles and excerpts come with tags and HTML entities, such as `&#038;`
	 * for `&` and `&#8217;` for a curly quote. JSON-LD holds plain text, and
	 * the JSON encoding escapes what it needs.
	 *
	 * @since 0.1.0
	 *
	 * @param string $text Text for display.
	 *
	 * @return string The plain text.
	 */
	public function plain_text( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}

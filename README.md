# GatherPress Schema

Schema.org `Event` structured data for [GatherPress](https://github.com/GatherPress/gatherpress) events, so search engines can show the date, the place and the status of an event.

This is a companion plugin for [GatherPress#953](https://github.com/GatherPress/gatherpress/issues/953). It follows the shape proposed there:

1. **One provider.** `GatherPress_Schema\Event_Schema` builds the `Event` from the GatherPress data model, not from the rendered blocks.
2. **Join the SEO plugin's graph.** When Yoast SEO builds its schema graph, the `Event` is added to it (`wpseo_schema_graph`), as the main entity of Yoast's WebPage. When Rank Math builds its JSON-LD, the `Event` is added to it (`rank_math/json_ld`). The plugin then prints nothing of its own.
3. **Print it alone otherwise.** When no SEO plugin built its schema on the page, the plugin prints the `Event` as JSON-LD in the head.

What counts in step 3 is whether an SEO plugin built its schema on the page, not whether it is active. Rank Math before its setup wizard, or Yoast SEO with its schema turned off, builds none, and the plugin prints its own. When the graph already has an event, for example one set up in Rank Math, the plugin adds none. A page has one `Event`, not two.

## What the Event holds

| Property | From |
|---|---|
| `name`, `url`, `description`, `image` | The title, the permalink, the hand-written excerpt and the featured image, as plain text. |
| `startDate`, `endDate` | The event dates in ISO 8601, with the event's time zone. An all-day event has dates only. |
| `eventStatus` | The event status in GatherPress 0.37.0 or later, such as `EventCancelled` or `EventRescheduled`. Older versions give `EventScheduled`. |
| `location` | A `Place` for the venue, with a `PostalAddress` from the structured address fields, `GeoCoordinates`, the phone and the website. An event that is only online, or that moved online, gives a `VirtualLocation` with the event page as its URL. The link to join is only for attendees, so it is never in the output. |
| `eventAttendanceMode` | Offline for a venue, online for an online event, mixed for both. |

An event at a venue that is also online keeps the `Place` as its location, with the mixed attendance mode. Google requires an address on the location, and a `VirtualLocation` has none. Google shows no rich result for an event that is only online, but the data stays correct for other consumers.

The `Event` is on the page of a single event only, for every post type that supports `gatherpress-event-date`. A draft, a password-protected event, or an event that GatherPress does not show to the visitor has none. A password-protected venue gives no `Place`.

## Filters

- `gatherpress_schema_event` changes the `Event` array. Return an empty array to leave an event out.
- `gatherpress_schema_street_address` changes the street address of a venue. By default the house number comes before the street in some countries, such as the US, the UK, France and Japan, and after it in others.
- `gatherpress_schema_print_json_ld` turns the standalone JSON-LD on or off. Return false when another plugin already outputs the event.

## Requirements

- WordPress 7.0 or later
- PHP 8.1 or later
- GatherPress 0.36.1 or later

## Development

```bash
composer install
npm install
npm run wp-env start
npm run test:unit:php
npm run lint:php
npm run lint:phpstan
```

The PHPStan configuration reads the GatherPress classes from `../gatherpress`.

## License

GPL-2.0-or-later

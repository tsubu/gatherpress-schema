=== GatherPress Schema ===
Contributors: tsubu
Tags: gatherpress, events, schema, structured data, json-ld
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds schema.org Event structured data to GatherPress events, inside the Yoast SEO or Rank Math graph when one of them is active.

== Description ==

GatherPress Schema is a companion plugin for GatherPress. It describes each event as a schema.org Event, so search engines can show its date, place and status.

* Built from the GatherPress data model: the event dates and time zone, the venue address and coordinates, online events, and the event status.
* When Yoast SEO or Rank Math outputs structured data, the Event joins it, so a page never has two competing Events.
* Otherwise, for example before the Rank Math setup wizard, the plugin prints the Event as JSON-LD.
* Works for every post type that supports GatherPress event dates.
* Leaves out drafts, password-protected events and venues, and the link to join an online event.
* Requires GatherPress 0.36.1 or later. Event statuses need GatherPress 0.37.0 or later.

== Installation ==

1. Install and activate GatherPress 0.36.1 or later.
2. Upload the plugin folder to `/wp-content/plugins/` and activate it.
3. Open an event page and check its structured data, for example with the Rich Results Test.

== Frequently Asked Questions ==

= Can I change the output? =

Use the `gatherpress_schema_event` filter to change the Event, `gatherpress_schema_street_address` to change how a street address is written, and `gatherpress_schema_print_json_ld` to turn the standalone JSON-LD on or off.

== Changelog ==

= 0.1.0 =

* First version.

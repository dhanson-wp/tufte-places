=== Tufte Places ===
Tags: places, photos, location, block theme
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Places for photos and posts, shown as a line under images and galleries.

== Description ==

The full description, tests, and deploy notes live in README.md.

== Changelog ==

= 0.2.0 =
* A place can be made from a Google Maps link. Paste one on the place screen, or send it over REST, and saving fills in the coordinates; a link that's only a search fills in an empty address instead.
* Share links from the Maps app work too. They're followed to the full link when the place is saved, and the share link is what's kept.
* The Places list has a Map column that links to each place's map, or says "Needs coordinates" for an exact place without them.
* The Map link under a photo goes to the place's Google listing when it has one, and to OpenStreetMap otherwise. General places never keep a map link.
* The `wp tufte-places sync` command is gone. Saving a post already keeps its places in step with its photos.

= 0.1.0 =
* First release: the place taxonomy with exact and general precision, places on photos in the media modal, the place line under images and galleries, places synced from photos to posts, and place archives.

# Tufte Places

Places for photos and posts, for sites running a block theme like [Tufte Blocks](https://github.com/dhanson-wp/tufte-blocks).

I'm backfilling photo posts from team meetups and trips, and I want each photo to say where it was taken. My photos lose their GPS on the way into WordPress, and that's fine; I'd rather choose what a place is than have the camera decide, because the one place I never want pinned is my home. This plugin gives me a reusable place record, so every visit to the same spot adds up over time.

## What places are

A place is a term in a hierarchical `place` taxonomy on posts and attachments, so a city can hold the cafés and coworking spaces inside it. Places are public, have archives at `/place/{slug}/`, and show up in the REST API at `/wp/v2/places`.

Every place has a precision:

- **Exact** places have an address and `lat` and `lng` coordinates, and they can link to a map.
- **General** places show only a name. This is my privacy rule: a general place never stores or outputs an address or coordinates. They're deleted when the place is saved, and blanked in REST responses as a second guard.

`tufte_places_get_place( $term_id )` returns a place as a plain array.

## Tagging photos

Tag a photo with a place in the Places field of the media modal. Tag a post the same way, or let the plugin do it for you: when a post uses tagged photos, the plugin adds those places to the post, and it removes them again when the photos go away. A place you added by hand that also comes from a photo is removed when that photo goes away, so add a place by hand only when no photo carries it.

To sync every post (or one) after a bulk change:

```
wp tufte-places sync
wp tufte-places sync --post=123
```

## Keep off the map

Each photo has a "keep off the map" flag, a checkbox in the media modal and a toggle in the block sidebar for image and gallery blocks. The flag is stored for a future map. It doesn't change the page yet; the place line still shows.

## Captions

The library caption shows under a photo, and it stays live, so editing it in the media library updates every post. A caption typed into the block overrides it and is left exactly as saved. A block caption that's identical to the library caption counts as none, so the live one keeps showing.

## The place line

Under an image or gallery whose photos have places, the plugin adds a line wrapped in a `tufte-places-group` div. Images inside a gallery get a caption but no line of their own. The markup and class names are public API, so themes can style them:

- `tufte-places-group`: wrapper around the block and its line (it carries the block's alignment class).
- `tufte-places-line`: the line itself.
- `tufte-places-place`: one place.
- `tufte-places-pin`: the pin icon.
- `tufte-places-name`: the place name.
- `tufte-places-locality`: the city or region.
- `tufte-places-map`: the map link, for exact places with coordinates.
- `tufte-places-more`: the "More from here" link to the archive.
- `tufte-places-sep`: the separator between parts.

## Filters

- `tufte_places_map_url( $url, $place )` changes the map link. It defaults to OpenStreetMap.
- `tufte_places_object_types( $types )` changes which post types can carry a place. It defaults to `post` and `attachment`.

## The archive

A place archive shows the place header, the posts there with pagination, and a grid of photos taken there. The plugin registers a block template named `taxonomy-place`. To override it, add `templates/taxonomy-place.html` to your theme (or edit the template in the Site Editor). The `tufte-places/place-header` and `tufte-places/photos` blocks hold the header and the grid.

## Requirements

I build and test it on WordPress 7.1 with PHP 8.3. There's no build step and no dependency on Tufte Blocks or Tufte Formats.

## Install

Copy this folder into `wp-content/plugins/tufte-places` and activate it. Activating flushes the rewrite rules, so `/place/{slug}/` works right away.

## Tests

The tests are plain PHP and run inside WordPress:

```
wp eval-file wp-content/plugins/tufte-places/tests/run.php
```

The runner prints one PASS or FAIL line per case and exits non-zero on a failure. The tests create real terms on the site and delete them when they finish.

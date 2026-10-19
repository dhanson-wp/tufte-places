# Tufte Places

Places for photos and posts, for sites running a block theme like [Tufte Blocks](https://github.com/dhanson-wp/tufte-blocks).

I'm backfilling photo posts from team meetups and trips, and I want each photo to say where it was taken. My photos lose their GPS on the way into WordPress, and that's fine; I'd rather choose what a place is than have the camera decide, because the one place I never want pinned is my home. This plugin gives me a reusable place record, so every visit to the same spot adds up over time.

## What it does

- **Place taxonomy.** A hierarchical `place` taxonomy on posts and attachments, so a city can hold the cafés and coworking spaces inside it. It's public, has archives at `/place/`, and is available in the REST API at `/wp/v2/places`.
- **Term meta.** Each place has a precision (`exact` or `general`), an address, and `lat` and `lng` coordinates.
- **General places stay vague.** A general place never stores or outputs an address or coordinates. They're deleted when the place is saved, and blanked in REST responses as a second guard.
- **Helpers.** `tufte_places_get_place( $term_id )` returns a place as a plain array, and the `tufte_places_object_types` filter changes which post types can carry one.

More is coming: tagging places in the media library, a place line under images and galleries, and eventually a map. This first piece is the data model.

## Requirements

I build and test it on WordPress 7.1 with PHP 8.3. There's no build step and no dependency on Tufte Blocks or Tufte Formats.

## Install

Copy this folder into `wp-content/plugins/tufte-places` and activate it.

## Tests

The tests are plain PHP and run inside WordPress:

```
wp eval-file wp-content/plugins/tufte-places/tests/run.php
```

The runner prints one PASS or FAIL line per case and exits non-zero on a failure. The tests create real terms on the site and delete them when they finish.

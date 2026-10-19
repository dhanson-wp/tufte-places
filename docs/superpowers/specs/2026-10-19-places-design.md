# Tufte Places: design

Status: draft for review, 2026-10-19

## Why this exists

I'm backfilling photo posts from team meetups and trips, starting with five gallery notes from our Montreal meetup (September 13 to 17, 2026). Every stop has a story: where we coworked, where we ate, what we walked past. I want each photo to say where it was taken, I want every visit to the same place to add up over time (I'm at our NoHo space regularly), and eventually I want a map that connects all of it.

The photos lost their GPS on the way into WordPress, and that's fine. I'd rather choose what a place is than have the camera decide, because the one place I never want pinned is my home.

## What it is

A WordPress plugin that adds **places** to photos and posts:

- A place is a reusable record with a name, an address, coordinates, and a precision.
- Photos are tagged with a place in the media library. Posts pick up places from the photos in them.
- Galleries and images show a place line (pin, name, locality, "Map", "More from here").
- Each place has an archive page with every post and photo from it.
- Captions live on the photo in the media library and follow it everywhere, with a per-post override.

It pairs with Tufte Formats and Tufte Blocks but requires neither. It works with any block theme, any post format, and any post type that uses the core Image or Gallery blocks. It's built for a future public release on WordPress.org.

## Goals

1. One record per place, reused across posts and years.
2. A place archive that shows galleries and photos from that place together.
3. A place line on every gallery and standalone image that has a place.
4. Privacy by choice of place: a "general" place (a city like Ames) carries no street and no coordinates, so home photos can be grouped without exposing an address.
5. A per-photo and per-gallery way to keep things off a future map, separate from precision.
6. Captions written once on the photo, shown everywhere, overridable in a post.
7. Data shaped so a map can be built later without a backfill.

## Non-goals (for now)

- The map itself. Store what it needs; don't build it.
- Reading GPS from EXIF. Uploads to WordPress.com lose it anyway, and auto-tagging is how a home address leaks.
- Geocoding addresses into coordinates. Coordinates are entered by hand or by a script.
- Places on blocks other than Image and Gallery (Cover, Media & Text) in the first release.

## Anti-goals

- Anything that can put my home's coordinates on the public site. A general place must never output or store a street address or coordinates.
- Requiring Tufte Formats, Tufte Blocks, or post formats.
- A build step. Tufte Formats ships plain PHP and plain JavaScript modules; this should too.
- Copying captions into post content at insert time and letting them drift from the library.

## Data model

### The `place` taxonomy

- Hierarchical, so places can nest: Montréal → Le Plateau → Hof Kelsten; Iowa → Ames.
- Registered for `post` and `attachment`, with a filter so other post types can opt in.
- Public, with archives at `/place/{slug}/`. `show_in_rest` on.

### Term meta (registered with `register_term_meta`, exposed in REST)

| Key | Type | Notes |
|---|---|---|
| `precision` | `exact` or `general` | Default `exact`. |
| `address` | string | Exact places only. Not displayed on the post; kept for maps and the archive. |
| `lat`, `lng` | number | Exact places only. |

Saving a general place clears `address`, `lat`, and `lng`, on the edit screen and through REST. A general place never outputs them anywhere.

### On the photo (attachment)

- **Place:** the attachment's `place` terms. Normally one; more than one is allowed.
- **Caption:** WordPress's own attachment caption (`post_excerpt`). No new field.
- **Keep off the map:** registered post meta `tufte_places_hide_from_map` (boolean, REST-exposed). Shown as a checkbox in the media modal and on the attachment edit screen.

### On the post

A post's `place` terms are the union of the places of the Image and Gallery blocks in its content. The featured image doesn't count (notes don't show one). This is what puts a post on a place archive.

- **When it syncs:** on post save, and whenever a photo's places change. The second case finds posts that use that attachment (by `wp-image-{id}` and block `"id":{id}` in content) and resyncs them.
- **Removal:** the plugin remembers which places it derived (post meta `tufte_places_derived`). On resync it removes derived places that no longer apply. Places a person added to the post by hand are never removed.
- **Backfill:** a WP-CLI command, `wp tufte-places sync [--post=<id>]`, resyncs one post or all of them. The Montreal rollout uses it after tagging photos.

### On the gallery block

A block attribute, `tufteHideFromMap` (boolean), added to `core/gallery` and `core/image` through a small editor script that adds one toggle to the block sidebar. It means "nothing in this block goes on the map", on top of each photo's own flag.

## Editing

- **Places** are managed on the standard term screens (Posts → Places), with the extra fields above. Precision is a radio; address, lat, and lng hide when General is chosen.
- **Tagging photos** happens in the media modal and attachment edit screen, using the taxonomy field WordPress shows for attachments plus the "Keep off the map" checkbox. The editor script adds no place picker in the first release.
- **Captions** are written in the media library. Typing a caption on an image block in a post overrides it for that post; clearing it brings the library caption back.

## Display

### Captions

On render, when an image block (inside a gallery or alone) has no caption of its own, fill in the attachment's caption. A caption on the block wins. Styling stays core's: gallery captions overlay the bottom of each photo; the theme decides the rest.

Core copies the library caption into the block when an image is inserted, which would freeze it as an override. To prevent that drift:

- The editor script clears a just-inserted image's block caption when it matches the library caption, and shows the library caption as the empty caption's placeholder so the author can see what will display.
- At render, a block caption that exactly matches the current library caption is treated the same as no caption.
- The five Montreal drafts have no block captions, so nothing needs migrating.

### The place line

Rendered after a gallery or a standalone image that has at least one place among its images.

- **Contents, per place:** a pin icon, the place name in small caps, the locality, then links: "Map" (exact places only) and "More from here" (the place archive).
- **Locality** is the parent chain from the nearest parent up, joined with commas, for example "Le Plateau, Montréal". A top-level place shows no locality.
- **Order:** places appear in the order their first photo appears in the block. Duplicates collapse.
- **Photos with no place** add nothing. A block with no places gets no line.
- **Map link** opens OpenStreetMap at the place's coordinates by default; a filter lets a site switch providers.
- **Markup:** the plugin wraps the gallery (or image) and its place line together in one container, so a theme can position the line relative to its gallery, for example bottom-aligned in the margin. The container passes through the block's alignment classes (`alignwide`, `alignfull`) so layouts don't shift. Both the container and the line get stable class names, and the line gets an accessible label naming it as the location. Themes will target this markup, so treat it as public API once released.

### Default styling vs. the Tufte theme

The plugin's own stylesheet shows the line directly under the gallery: one row, wrapping on small screens. That's the any-theme default.

On derekhanson.blog the child theme moves it into the right margin as a Tufte sidenote at widths where the margin exists, bottom-aligned with its gallery, stacked as name / locality / links. Below that width it falls back to the plugin's under-the-gallery line. Mockups from the brainstorm: the combined version is the one to build.

## Place archive

A place archive lists posts with that place (newest first, showing their galleries as usual) and a grid of that place's photos. Child places roll up into parents: Montréal's archive includes Le Plateau and Hof Kelsten.

The photo grid only shows tagged photos that appear in at least one published, public post. A tagged photo that's unattached or only used in a draft or private post stays off the archive.

The plugin supplies a default block template for `taxonomy-place` that any block theme can override. A theme template wins.

The archive heading shows the place name and locality. For exact places it also shows the address and a map link. For general places it shows only the name and locality.

## Privacy

- General places carry and output no address or coordinates.
- No EXIF location is read or stored.
- The "keep off the map" flags are stored and exposed in REST now so the future map can honor them; until the map exists they don't change anything on the page.
- REST output for term meta follows the same rule: a general place returns no address or coordinates.

## Compatibility

- WordPress 7.1 and PHP 8.3, matching Tufte Formats. Minimum versions to be set before a public release.
- Block themes. Classic themes get the place line and captions (they render blocks) but no archive template.
- Text domain `tufte-places`; all strings translatable.
- Prefix `tufte_places_` for functions, hooks, and meta.

## Testing

Plain PHP tests run inside WordPress, like Tufte Formats:

```
wp eval-file wp-content/plugins/tufte-places/tests/run.php
```

At minimum: taxonomy and meta registration, the general-place clearing rule (edit screen and REST), post term sync, caption fill-in and override, place line contents and order, the map link for exact vs. general, the archive including child places, and REST never returning coordinates for a general place.

## Rollout on derekhanson.blog

1. Build and test the plugin locally on the Studio site.
2. Child theme: add the sidenote CSS for the place line in the Derek Hanson Blog child theme (`themes/derekhanson-blog` in the `dhanson-wp/derekhanson-blog` repo, a child of Tufte Blocks). Not in Tufte Blocks itself.
3. Create the Montreal places (list in the brainstorm notes: Boxotel, Hof Kelsten, Montréal Cowork, Le Port de tête, Le Valet d'Coeur, Mix'Heure, La Fabrique de Bagel, Schwartz's, Kitsch'n Swell, Le Toledo, Nouilles de Lan Zhou, Belvédère Kondiaronk, Chalet du Mont-Royal, Lac aux Castors, McGill, Le Balcon, Arepera, The Bagel Class) under Montréal and its neighborhoods, with coordinates from the photo GPS.
4. Tag the 78 Montreal photos and write their captions in the media library, then run `wp tufte-places sync` so the five posts pick up their places.
5. Deploy through a WordPress.com GitHub deployment, like Tufte Formats.

## Decided in the brainstorm (Derek)

- Places are a taxonomy (direction A), not a post type and not tags.
- A place is reused across posts; each place has an archive with galleries and photos together.
- Privacy comes from choosing a general place (Ames) instead of an exact one (home). Hotels are fine as exact places.
- Keeping something off the map is per photo or per gallery.
- No map yet.
- Display: Tufte sidenote placement, option B's styling (pin, name, links), option C's caption overlay.
- Captions live on the photo and follow it everywhere, with a per-post override.
- A new plugin, separate from Tufte Formats, aimed at a future public release.

## Claude's calls (check these)

- Working name "Tufte Places", repo `tufte-places`.
- OpenStreetMap as the default map link.
- The caption fill-in happens at render time, so the library stays the source of truth.
- Posts get places by syncing from their images on save, add-only.
- Locality comes from the parent chain, not from parsing the address.
- The per-gallery map toggle is a block attribute with a tiny no-build editor script.
- Child places roll up into parent archives.
- Post places resync when a photo's places change, derived places are removed when they no longer apply, and a WP-CLI command handles backfill.
- The featured image doesn't count toward a post's places.
- The editor clears a just-inserted caption that matches the library, and identical captions count as no override.
- The plugin wraps a gallery and its place line in one container so themes can position the line.
- The archive photo grid only shows photos used in published, public posts.

## Left to the builder

- Archive template layout and the photo grid's design.
- The pin icon, exact class names, and default CSS.
- Function and file organization, following Tufte Formats' `includes/` pattern.
- How the term screen hides fields for general places.

# Tufte Places Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Tufte Places plugin: a reusable `place` taxonomy for photos and posts, a place line and caption fill-in on core Image and Gallery blocks, place archives, and map opt-out data, plus the sidenote styling in the Derek Hanson Blog child theme.

**Architecture:** Plain PHP plugin in `includes/` (one file per responsibility, like Tufte Formats), hooked into core block rendering with `render_block_data` and `render_block_*` filters. One small no-build editor script uses the `wp.*` globals. The archive uses a plugin-registered block template and two server-rendered blocks.

**Tech Stack:** WordPress 7.1, PHP 8.3, block themes, WP-CLI, plain JavaScript with `wp.*` globals. Tests are plain PHP run through `wp eval-file`.

**Spec:** `docs/superpowers/specs/2026-10-19-places-design.md`

## Global Constraints

- WordPress 7.1 and PHP 8.3. `declare(strict_types=1);` in every `includes/` file and the main plugin file, not in tests (WP-CLI evals test files inside a method).
- Prefix `tufte_places_` for functions, hooks, options, and meta. Text domain `tufte-places`; every user-facing string translatable.
- No build step: no `package.json`, no bundler. JavaScript is plain and uses `wp.*` globals.
- Never require Tufte Formats, Tufte Blocks, or post formats.
- A general place never stores or outputs `address`, `lat`, or `lng`, on screen or in REST.
- No EXIF location is read or stored.
- Prose (README, strings) follows Derek's voice rules: no em dashes, sentence case, contractions.
- Commit messages are plain sentences like Tufte Formats' ("Add the place taxonomy and term meta") and end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Local site sync (run before every test run):
  `rsync -a --delete --exclude .git --exclude docs ~/Projects/tufte-places/ /Users/derekhanson/Studio/derekhansonblog/wp-content/plugins/tufte-places/`
- Test command:
  `studio wp --path /Users/derekhanson/Studio/derekhansonblog eval-file wp-content/plugins/tufte-places/tests/run.php`
  Passing output ends with `N passed, 0 failed` and exits 0.
- Tests create real terms, posts, and attachments on the Studio site and delete everything they create at the end of their file.

## Review Focus

1. **A place term is deleted while photos still reference it.** The line skips it; no warnings, no empty line. Test in Task 4.
2. **An image block with no attachment ID** (an external URL) or an ID whose attachment was deleted. No caption fill, no line, no warnings. Tests in Tasks 4 and 5.
3. **A library caption with markup or quotes.** It renders through `wp_kses_post`, and quotes don't break attributes. Test in Task 5.
4. **An exact place saved without coordinates.** It gets no "Map" link but still gets "More from here". Test in Task 4.
5. **A photo flagged "keep off the map" (or a gallery with `tufteHideFromMap`).** It still shows its place line on the page; the flag only affects data for the future map. Test in Task 5.

---

### Task 1: Plugin scaffold, place taxonomy, and term meta

**Files:**
- Create: `tufte-places.php`, `includes/taxonomy.php`, `tests/run.php`, `tests/test-taxonomy.php`, `README.md`, `.gitignore` (`.DS_Store`)

**Interfaces:**
- Produces:
  - `tufte_places_object_types(): array`, default `['post', 'attachment']`, filtered by `tufte_places_object_types`.
  - Taxonomy `place`: hierarchical, public, `show_in_rest` true, `rest_base` `places`, rewrite slug `place`, `show_admin_column` true.
  - Term meta `precision` (`exact`|`general`, default `exact`), `address` (string), `lat` and `lng` (number), all `show_in_rest`, `single`.
  - `tufte_places_get_place( int $term_id ): ?array` returns `['id','name','slug','precision','address','lat','lng','link','parent']`, with `address` `''` and `lat`/`lng` `null` for general places, or `null` when the term doesn't exist.
  - `tufte_places_enforce_general( int $term_id ): void` deletes `address`, `lat`, `lng` when precision is `general`. Hooked on `created_place`, `edited_place`, and `rest_after_insert_place`.
  - `tufte_places_assert_same( $expected, $actual, string $name ): void` in `tests/run.php`, with the same shape as Tufte Formats' runner.

- [ ] **Step 1: Write the runner and the failing tests** in `tests/run.php` (copy Tufte Formats' runner, renamed to `tufte_places_`) and `tests/test-taxonomy.php`:

```php
tufte_places_assert_same( true, taxonomy_exists( 'place' ), 'taxonomy: place exists' );
tufte_places_assert_same( true, is_taxonomy_hierarchical( 'place' ), 'taxonomy: place is hierarchical' );
tufte_places_assert_same( array( 'post', 'attachment' ), get_taxonomy( 'place' )->object_type, 'taxonomy: on posts and attachments' );
tufte_places_assert_same( 'places', get_taxonomy( 'place' )->rest_base, 'taxonomy: rest base' );

$tp_exact = wp_insert_term( 'Test Hof Kelsten', 'place' )['term_id'];
update_term_meta( $tp_exact, 'address', '4524 boul. Saint-Laurent' );
update_term_meta( $tp_exact, 'lat', 45.52039 );
update_term_meta( $tp_exact, 'lng', -73.58704 );
$tp = tufte_places_get_place( $tp_exact );
tufte_places_assert_same( 'exact', $tp['precision'], 'place: precision defaults to exact' );
tufte_places_assert_same( 45.52039, $tp['lat'], 'place: exact keeps lat' );

$tp_general = wp_insert_term( 'Test Ames', 'place' )['term_id'];
update_term_meta( $tp_general, 'precision', 'general' );
update_term_meta( $tp_general, 'address', '123 Secret St' );
update_term_meta( $tp_general, 'lat', 42.0 );
tufte_places_enforce_general( $tp_general );
tufte_places_assert_same( '', get_term_meta( $tp_general, 'address', true ), 'general: address cleared' );
tufte_places_assert_same( '', get_term_meta( $tp_general, 'lat', true ), 'general: lat cleared' );
tufte_places_assert_same( null, tufte_places_get_place( $tp_general )['lat'], 'general: get_place returns no lat' );
tufte_places_assert_same( null, tufte_places_get_place( 999999999 ), 'place: missing term is null' );

// REST: a general place never returns coordinates.
$tp_req = new WP_REST_Request( 'GET', '/wp/v2/places/' . $tp_general );
$tp_res = rest_do_request( $tp_req )->get_data();
tufte_places_assert_same( true, empty( $tp_res['meta']['lat'] ) && empty( $tp_res['meta']['address'] ), 'rest: general place has no coordinates or address' );

wp_delete_term( $tp_exact, 'place' );
wp_delete_term( $tp_general, 'place' );
```

- [ ] **Step 2: Sync and run; expect FAIL** (`taxonomy: place exists` fails, then fatal on undefined `tufte_places_get_place`).

- [ ] **Step 3: Implement** `tufte-places.php` (header: Plugin Name "Tufte Places", Version `0.1.0`, `TUFTE_PLACES_VERSION`, `TUFTE_PLACES_PLUGIN_FILE`, `require_once` each `includes/` file as tasks add them) and `includes/taxonomy.php` with the functions above. Register on `init`. Sanitize `precision` to `exact` unless the value is exactly `general`. Also add a `rest_prepare_place` filter that blanks `address`, `lat`, `lng` for general places, as a second guard.

- [ ] **Step 4: Sync and run; expect all PASS.**

- [ ] **Step 5: Write `README.md`** (what it does, requirements, install, tests, in Derek's voice), then commit: "Add the place taxonomy and term meta".

---

### Task 2: Place fields on the term screens

**Files:**
- Create: `includes/admin-terms.php`, `tests/test-admin-terms.php`

**Interfaces:**
- Consumes: term meta keys from Task 1, `tufte_places_enforce_general()`.
- Produces: `tufte_places_save_term_fields( int $term_id, array $input ): void`. It reads `tufte_places_precision`, `tufte_places_address`, `tufte_places_lat`, `tufte_places_lng` from `$input`, sanitizes them, saves them, then calls `tufte_places_enforce_general()`. The `created_place` and `edited_place` hooks call it with `wp_unslash( $_POST )` after a nonce (`tufte_places_term_nonce`) and `manage_categories` capability check.

- [ ] **Step 1: Write the failing tests:**

```php
$tp_id = wp_insert_term( 'Test Cowork', 'place' )['term_id'];
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_address' => ' 4388 rue Saint-Denis ', 'tufte_places_lat' => '45.52322', 'tufte_places_lng' => 'not a number' ) );
tufte_places_assert_same( '4388 rue Saint-Denis', get_term_meta( $tp_id, 'address', true ), 'term fields: address trimmed' );
tufte_places_assert_same( 45.52322, (float) get_term_meta( $tp_id, 'lat', true ), 'term fields: lat saved as a number' );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lng', true ), 'term fields: an invalid lng is not saved' );
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'general', 'tufte_places_address' => 'x', 'tufte_places_lat' => '1', 'tufte_places_lng' => '1' ) );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'address', true ), 'term fields: switching to general clears the address' );
tufte_places_assert_same( 'general', get_term_meta( $tp_id, 'precision', true ), 'term fields: precision saved' );
wp_delete_term( $tp_id, 'place' );
```

- [ ] **Step 2: Sync and run; expect FAIL** (undefined function).

- [ ] **Step 3: Implement** the save function, the add and edit form fields (`place_add_form_fields`, `place_edit_form_fields`: a Precision radio of Exact/General, then Address, Latitude, Longitude), and a few lines of inline script that hide the three inputs while General is checked. Latitude must be within -90 to 90 and longitude within -180 to 180, or it isn't saved.

- [ ] **Step 4: Sync and run; expect PASS.** Then open Posts → Places on the Studio site, add a general place, and confirm the coordinate fields hide.

- [ ] **Step 5: Commit:** "Add place fields to the term screens".

---

### Task 3: Keep-off-the-map flag on photos

**Files:**
- Create: `includes/attachments.php`, `tests/test-attachments.php`

**Interfaces:**
- Produces:
  - Post meta `tufte_places_hide_from_map` on `attachment` (boolean, `single`, `show_in_rest`, default `false`).
  - `tufte_places_photo_hidden_from_map( int $attachment_id ): bool`.
  - `tufte_places_attachment_fields( array $fields, WP_Post $post ): array` (filter `attachment_fields_to_edit`) adds a checkbox labeled "Keep off the map".
  - `tufte_places_attachment_fields_save( array $post, array $attachment ): array` (filter `attachment_fields_to_save`).

- [ ] **Step 1: Write the failing tests.** Create a test attachment with `wp_insert_attachment`, then:

```php
tufte_places_assert_same( false, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: visible on the map by default' );
tufte_places_attachment_fields_save( array( 'ID' => $tp_att ), array( 'tufte_places_hide_from_map' => '1' ) );
tufte_places_assert_same( true, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: flag saved from the media modal' );
tufte_places_attachment_fields_save( array( 'ID' => $tp_att ), array() );
tufte_places_assert_same( false, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: unchecked clears the flag' );
tufte_places_assert_same( true, isset( tufte_places_attachment_fields( array(), get_post( $tp_att ) )['tufte_places_hide_from_map'] ), 'photo: checkbox field present' );
tufte_places_assert_same( true, in_array( 'place', get_object_taxonomies( 'attachment' ), true ), 'photo: place taxonomy available in the media modal' );
```

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement** the functions above.
- [ ] **Step 4: Sync and run; expect PASS.** Open a photo in the media modal on the Studio site and confirm both the Places field and the checkbox appear and save.
- [ ] **Step 5: Commit:** "Add the keep-off-the-map flag to photos".

---

### Task 4: Place line markup

**Files:**
- Create: `includes/place-line.php`, `assets/css/place-line.css`, `tests/test-place-line.php`

**Interfaces:**
- Consumes: `tufte_places_get_place()`.
- Produces:
  - `tufte_places_locality( int $term_id ): string`: ancestor names from the nearest parent up, joined with `', '`. Empty for a top-level place.
  - `tufte_places_map_url( array $place ): string`: `''` unless precision is `exact` and both coordinates are set. Default `sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=18/%1$s/%2$s', $lat, $lng )`, filtered by `tufte_places_map_url` with `( $url, $place )`.
  - `tufte_places_block_attachment_ids( array $block ): array<int>`: `core/image` gives `[ attrs.id ]` when set; `core/gallery` gives its inner image IDs in order. Anything else gives `[]`.
  - `tufte_places_places_for_attachments( array $attachment_ids ): array<int>`: place term IDs in order of first appearance, deduplicated, skipping terms that no longer exist.
  - `tufte_places_line_html( array $term_ids ): string`: `''` for an empty list. Otherwise `<div class="tufte-places-line" role="group" aria-label="Location">` holding one `<p class="tufte-places-place">` per place, each with an inline pin SVG (`aria-hidden="true"`), `<span class="tufte-places-name">`, `<span class="tufte-places-locality">` (omitted when empty), a "Map" link (omitted when the map URL is empty), and a "More from here" link to the term archive. Separator spans `<span class="tufte-places-sep" aria-hidden="true">·</span>`.
  - Style handle `tufte-places-line` registered on `init`, pointing at `assets/css/place-line.css`: one wrapping row under the block, name in `font-variant: small-caps`, inherits theme colors.

- [ ] **Step 1: Write the failing tests.** Create Montréal → Le Plateau → Hof Kelsten (exact, with coordinates), a general Ames, an exact place with no coordinates, and two attachments tagged Hof Kelsten and Ames, then:

```php
tufte_places_assert_same( 'Le Plateau, Montréal', tufte_places_locality( $tp_hof ), 'line: locality is the parent chain' );
tufte_places_assert_same( '', tufte_places_locality( $tp_mtl ), 'line: a top-level place has no locality' );
tufte_places_assert_same( 'https://www.openstreetmap.org/?mlat=45.52039&mlon=-73.58704#map=18/45.52039/-73.58704', tufte_places_map_url( tufte_places_get_place( $tp_hof ) ), 'line: OpenStreetMap link for an exact place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_ames ) ), 'line: no map link for a general place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_nocoords ) ), 'line: no map link for an exact place without coordinates' );
tufte_places_assert_same( array( $tp_hof, $tp_ames ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2, $tp_a1, 0, 999999999 ) ), 'line: order of first appearance, deduplicated, missing IDs skipped' );
$tp_html = tufte_places_line_html( array( $tp_hof, $tp_ames ) );
tufte_places_assert_same( 2, substr_count( $tp_html, 'class="tufte-places-place"' ), 'line: one entry per place' );
tufte_places_assert_same( 1, substr_count( $tp_html, 'openstreetmap.org' ), 'line: only the exact place gets a map link' );
tufte_places_assert_same( 2, substr_count( $tp_html, '>More from here<' ), 'line: every place links to its archive' );
tufte_places_assert_same( '', tufte_places_line_html( array() ), 'line: no places, no markup' );
wp_delete_term( $tp_ames, 'place' );
tufte_places_assert_same( array( $tp_hof ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2 ) ), 'line: a deleted place is skipped' );
tufte_places_assert_same( array( 6162, 6191 ), tufte_places_block_attachment_ids( parse_blocks( tufte_places_fixture( 'gallery.html' ) )[0] ), 'line: gallery image IDs in order' );
tufte_places_assert_same( array(), tufte_places_block_attachment_ids( array( 'blockName' => 'core/image', 'attrs' => array() ) ), 'line: an image with no ID has no attachments' );
```

Add `tests/fixtures/gallery.html` (a two-image `core/gallery` with IDs 6162 and 6191, the same markup as the Montreal drafts) and a `tufte_places_fixture()` helper in `run.php`.

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement** the functions and the stylesheet. Escape every output with `esc_html` and `esc_url`.
- [ ] **Step 4: Sync and run; expect PASS.**
- [ ] **Step 5: Commit:** "Build the place line markup".

---

### Task 5: Block rendering: captions, wrapper, and the line

**Files:**
- Create: `includes/render.php`, `tests/test-render.php`

**Interfaces:**
- Consumes: Task 4's functions and style handle.
- Produces:
  - `tufte_places_mark_gallery_images( array $parsed_block, array $source_block, ?WP_Block $parent_block ): array` (filter `render_block_data`) sets `attrs.tuftePlacesInGallery = true` on `core/image` blocks whose parent is `core/gallery`.
  - `tufte_places_effective_caption( string $block_caption, int $attachment_id ): string`: the block caption when it's non-empty and differs from the library caption, otherwise the library caption (`wp_get_attachment_caption`). Output through `wp_kses_post`.
  - `tufte_places_render_image( string $html, array $block ): string` (filter `render_block_core/image`): when the block has an ID, puts the effective caption into `<figcaption class="wp-element-caption">` (replacing an existing one, or inserted before `</figure>`). When the image is not in a gallery and has places, it also wraps and adds the line.
  - `tufte_places_render_gallery( string $html, array $block ): string` (filter `render_block_core/gallery`): wraps and adds the line when its images have places.
  - `tufte_places_wrap( string $block_html, string $line_html, array $attrs ): string` returns `<div class="tufte-places-group{ align classes }">` + block + line + `</div>`. It copies `alignwide` or `alignfull` from `$attrs['align']`, and it enqueues `tufte-places-line`.
  - Server-side attribute `tufteHideFromMap` (boolean, default `false`) added to `core/image` and `core/gallery` via `register_block_type_args`.

- [ ] **Step 1: Write the failing tests.** Two tagged attachments (library captions `Blue trim on a "Plateau" row house.` and `The bread wall`), one flagged hidden-from-map, then render with `do_blocks()`:

```php
$tp_out = do_blocks( $tp_gallery_markup ); // gallery of both, no block captions
tufte_places_assert_same( 1, substr_count( $tp_out, 'class="tufte-places-group' ), 'render: gallery wrapped once' );
tufte_places_assert_same( 1, substr_count( $tp_out, 'class="tufte-places-line"' ), 'render: one line per gallery, none per inner image' );
tufte_places_assert_same( true, str_contains( $tp_out, 'Blue trim on a &quot;Plateau&quot; row house.' ) || str_contains( $tp_out, 'Blue trim on a "Plateau" row house.' ), 'render: library caption filled in safely' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_gallery_with_override ), '>My own caption<' ), 'render: a block caption overrides the library' );
tufte_places_assert_same( false, str_contains( do_blocks( $tp_gallery_with_same_caption ), '>The bread wall</figcaption><figcaption' ), 'render: a block caption equal to the library is not duplicated' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_gallery_markup_hidden ), 'tufte-places-line' ), 'render: hidden-from-map photos still show their place' );
tufte_places_assert_same( $tp_external_image_markup_rendered, do_blocks( $tp_external_image_markup ), 'render: an image without an ID is untouched' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_wide_gallery ), 'tufte-places-group alignwide' ), 'render: wrapper carries alignwide' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_single_image ), 'tufte-places-line' ), 'render: a standalone image gets its own line' );
tufte_places_assert_same( false, str_contains( do_blocks( $tp_untagged_gallery ), 'tufte-places' ), 'render: no places, no wrapper' );
```

Build `$tp_external_image_markup_rendered` by rendering the same markup with the plugin's filters removed.

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement** with `WP_HTML_Tag_Processor` for finding and replacing the figcaption where it can, and string insertion before the final `</figure>` otherwise.
- [ ] **Step 4: Sync and run; expect PASS.** Then preview the Monday Montreal draft on the Studio site (pull it down or paste its content) with two photos tagged, and confirm the line and the overlay captions render.
- [ ] **Step 5: Commit:** "Render captions and the place line on images and galleries".

---

### Task 6: Post place sync and the CLI command

**Files:**
- Create: `includes/sync.php`, `includes/cli.php`, `tests/test-sync.php`

**Interfaces:**
- Consumes: `tufte_places_block_attachment_ids()`.
- Produces:
  - `tufte_places_content_attachment_ids( string $content ): array<int>`: walks `parse_blocks` recursively and collects IDs from `core/image` and `core/gallery`, deduplicated, in order.
  - `tufte_places_sync_post( int $post_id ): void`. Derived places are the union of place terms on the content's attachments. It sets the post's terms to (current terms minus the previous `tufte_places_derived`) plus the new derived set, then stores the new set in post meta `tufte_places_derived` (array of ints). It skips revisions, autosaves, and attachments, and only touches post types in `tufte_places_object_types()` other than `attachment`.
  - `tufte_places_posts_using_attachment( int $attachment_id ): array<int>`: post IDs (any status except `trash` and `auto-draft`) whose content contains `wp-image-{$id}"` or `"id":{$id}` followed by `,` or `}`, via `$wpdb->prepare` with `LIKE`.
  - Hooks: `wp_after_insert_post` calls `tufte_places_sync_post`. `set_object_terms` for `place` on an attachment resyncs every post from `tufte_places_posts_using_attachment`.
  - WP-CLI: `wp tufte-places sync [--post=<id>]` syncs one post or every post of the synced types, printing `Synced N posts.`

- [ ] **Step 1: Write the failing tests:**

```php
// Post with a gallery of A1 (Hof) and A2 (untagged), plus a hand-added place "Test Trip".
tufte_places_sync_post( $tp_post );
tufte_places_assert_same( array( $tp_hof, $tp_trip ), tufte_places_sorted_terms( $tp_post ), 'sync: derived place added, hand-added place kept' );
wp_set_object_terms( $tp_a2, array( $tp_cowork ), 'place' ); // fires the attachment hook
tufte_places_assert_same( array( $tp_hof, $tp_cowork, $tp_trip ), tufte_places_sorted_terms( $tp_post ), 'sync: tagging a photo resyncs posts that use it' );
wp_set_object_terms( $tp_a1, array(), 'place' );
tufte_places_assert_same( array( $tp_cowork, $tp_trip ), tufte_places_sorted_terms( $tp_post ), 'sync: a place no photo has anymore is removed' );
tufte_places_assert_same( array( $tp_post ), tufte_places_posts_using_attachment( $tp_a2 ), 'sync: finds posts by block ID' );
tufte_places_assert_same( array(), tufte_places_posts_using_attachment( $tp_a2 * 10 ), 'sync: ID prefixes do not match (12 is not 120)' );
tufte_places_assert_same( array( 6162, 6191 ), tufte_places_content_attachment_ids( tufte_places_fixture( 'gallery.html' ) ), 'sync: content IDs' );
```

`tufte_places_sorted_terms( int $post_id ): array` is a test helper in `run.php` that returns sorted term IDs. Assertions compare sorted arrays.

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement** the functions, hooks, and CLI command (registered only when `WP_CLI` is defined).
- [ ] **Step 4: Sync and run; expect PASS.** Then run `studio wp --path /Users/derekhanson/Studio/derekhansonblog tufte-places sync` and expect `Synced N posts.`
- [ ] **Step 5: Commit:** "Sync post places from their photos".

---

### Task 7: Editor script: gallery map toggle and caption handling

**Files:**
- Create: `includes/editor.php`, `assets/js/editor.js`, `tests/test-editor.php`

**Interfaces:**
- Consumes: the `tufteHideFromMap` attribute from Task 5.
- Produces:
  - Script handle `tufte-places-editor`, enqueued on `enqueue_block_editor_assets`, with dependencies `wp-hooks`, `wp-compose`, `wp-element`, `wp-block-editor`, `wp-components`, `wp-data`, `wp-core-data`, `wp-i18n`, plus `wp_set_script_translations`.
  - In `editor.js`:
    - A `blocks.registerBlockType` filter (namespace `tufte-places/attributes`) that adds `tufteHideFromMap` to `core/image` and `core/gallery`.
    - An `editor.BlockEdit` filter that adds a "Keep off the map" `ToggleControl` in an `InspectorControls` panel titled "Place".
    - For `core/image` with an `id`: when the block's caption equals the media item's `caption.raw`, set the caption to `''` once, then show the library caption as the caption placeholder through the block's `placeholder` prop, where core supports it, or a muted note under the image in the editor.

- [ ] **Step 1: Write the failing PHP test:**

```php
do_action( 'enqueue_block_editor_assets' );
tufte_places_assert_same( true, wp_script_is( 'tufte-places-editor', 'enqueued' ), 'editor: script enqueued in the block editor' );
tufte_places_assert_same( false, str_contains( file_get_contents( WP_PLUGIN_DIR . '/tufte-places/assets/js/editor.js' ), 'import ' ), 'editor: no module imports (no build step)' );
```

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement** `includes/editor.php` and `assets/js/editor.js`.
- [ ] **Step 4: Sync and run; expect PASS.** Then check by hand in the Studio editor (use the browser pane; `/studio-auto-login?redirect_to=...` signs in):
  1. A gallery shows a "Place" panel with "Keep off the map". Toggling it and saving stores `"tufteHideFromMap":true` in the block comment.
  2. Inserting a photo that has a library caption leaves the block caption empty, and the library caption shows in the editor.
  3. Typing a caption keeps it.
  4. The browser console shows no errors.
- [ ] **Step 5: Commit:** "Add the gallery map toggle and keep library captions live in the editor".

---

### Task 8: Place archive

**Files:**
- Create: `includes/archive.php`, `templates/taxonomy-place.html`, `tests/test-archive.php`

**Interfaces:**
- Consumes: `tufte_places_get_place()`, `tufte_places_locality()`, `tufte_places_map_url()`, `tufte_places_content_attachment_ids()`.
- Produces:
  - `tufte_places_archive_photo_ids( int $term_id, int $limit = 60 ): array<int>`. These are attachments tagged with the term or any descendant, limited to those that appear in the content of at least one `publish` post of a public synced type with that term or a descendant. Newest post first, then content order, deduplicated.
  - Block `tufte-places/photos`, registered in PHP with `render_callback` and no editor UI (`supports.inserter` false): a grid of `wp_get_attachment_image( $id, 'medium_large' )` with the effective caption, inside `<div class="tufte-places-photos">`. On a non-place request it renders nothing.
  - Block `tufte-places/place-header`, in the same style: the term name as `<h1>`, then the locality. For exact places it adds the address and the map link. For general places it shows only the name and locality.
  - Block template `tufte-places//taxonomy-place` registered with `register_block_template()`: header part, `tufte-places/place-header`, a core Query Loop inheriting the main query (title, date, content), `tufte-places/photos`, footer part. A theme's own `taxonomy-place.html` wins through the normal hierarchy.

- [ ] **Step 1: Write the failing tests.** Montréal → Hof Kelsten. P1 is published and uses A1 (Hof). P2 is a draft and uses A2 (Hof). A3 (Hof) is unattached. P3 is private and uses A4 (Hof).

```php
tufte_places_assert_same( array( $tp_a1 ), tufte_places_archive_photo_ids( $tp_hof ), 'archive: only photos used in published, public posts' );
tufte_places_assert_same( array( $tp_a1 ), tufte_places_archive_photo_ids( $tp_mtl ), 'archive: a parent includes its child places' );
$tp_q = new WP_Query( array( 'tax_query' => array( array( 'taxonomy' => 'place', 'terms' => $tp_mtl ) ), 'fields' => 'ids' ) );
tufte_places_assert_same( array( $tp_p1 ), $tp_q->posts, 'archive: the parent archive lists posts tagged with a child place' );
tufte_places_assert_same( true, null !== get_block_template( 'tufte-places//taxonomy-place' ), 'archive: block template registered' );
$tp_head_general = tufte_places_render_place_header( $tp_ames );
tufte_places_assert_same( false, str_contains( $tp_head_general, 'openstreetmap' ), 'archive: general place header has no map link' );
```

`tufte_places_render_place_header( int $term_id ): string` is the `place-header` block's render function, exposed for testing.

- [ ] **Step 2: Sync and run; expect FAIL.**
- [ ] **Step 3: Implement.** Collect candidate posts with a `WP_Query` (`post_status` `publish`, public synced types, tax query with `include_children` true), gather their content IDs, then keep only IDs that have the term or a descendant.
- [ ] **Step 4: Sync and run; expect PASS.** Visit `/place/{slug}/` on the Studio site for a tagged place and confirm the header, posts, and photo grid render with the Derek Hanson Blog theme.
- [ ] **Step 5: Commit:** "Add place archives with a photo grid".

---

### Task 9: Sidenote styling in the Derek Hanson Blog child theme

**Repo:** `~/Projects/derekhanson-blog` (branch `place-sidenotes` off `main`). This task commits there, not in `tufte-places`.

**Files:**
- Modify: `themes/derekhanson-blog/assets/css/note.css` (append a "Places" section)
- Modify: `themes/derekhanson-blog/assets/css/archive.css` only if the stream shows galleries with lines
- Test: add assertions to the theme's existing test runner (`themes/derekhanson-blog/tests/`, same regex-on-CSS style as `test-note-template.php`)

**Interfaces:**
- Consumes: Task 5's classes: `.tufte-places-group`, `.tufte-places-line`, `.tufte-places-place`, `.tufte-places-name`, `.tufte-places-locality`, `.tufte-places-sep`.

**Target design** (from the brainstorm's combined mockup, `place-line-v2.html`):
- At widths where the note's right margin exists (the same breakpoint `note.css` already uses for its two columns, `min-width: 1240px`): `.tufte-places-group` is `position: relative`. `.tufte-places-line` is absolutely positioned in the right margin, bottom-aligned with the block, about 230px wide with a 28px gap, and has a 1px left border in the theme's border color. Each place stacks as the name (pin plus small caps, foreground color) on the first line, the locality on the second, and "Map · More from here" in the primary (gold) color on the third, at about 15px secondary color.
- Below that width: the plugin's default line under the gallery, restyled to the theme's secondary color and serif.
- Captions: keep core's gallery overlay. Give standalone image captions inside `.tufte-places-group` the same overlay treatment.

- [ ] **Step 1: Write the failing theme tests:**

```php
dhb_theme_assert_same( true, (bool) preg_match( '/@media \(min-width: 1240px\)\s*\{[^@]*\.tufte-places-line\s*\{[^}]*position:\s*absolute/s', $dhb_theme_css ), 'places css: the place line moves into the margin on wide screens' );
dhb_theme_assert_same( true, (bool) preg_match( '/\.tufte-places-group\s*\{[^}]*position:\s*relative/s', $dhb_theme_css ), 'places css: the group anchors the sidenote' );
```

- [ ] **Step 2: Run the theme tests (`studio wp --path /Users/derekhanson/Studio/derekhansonblog eval-file wp-content/themes/derekhanson-blog/tests/run.php` after syncing the theme into Studio the way that repo's README describes); expect FAIL.**
- [ ] **Step 3: Implement** the CSS.
- [ ] **Step 4: Run; expect PASS.** In the browser pane, check a tagged gallery on the Studio site at 1280px and 375px wide against the mockup.
- [ ] **Step 5: Commit in `derekhanson-blog`:** "Show places as sidenotes on notes". Don't push.

---

## Out of scope for this plan

- Creating the GitHub repo `dhanson-wp/tufte-places` and a WordPress.com deployment (Derek decides when).
- The Montreal rollout on the live site: creating the places, tagging 78 photos, writing captions, and running the sync. That needs the plugin deployed first, so it gets its own short plan, using the place list and coordinates already gathered.
- The map.

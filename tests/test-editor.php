<?php
/**
 * Tests for the block editor script.
 *
 * @package Tufte_Places
 */

do_action( 'enqueue_block_editor_assets' );
tufte_places_assert_same( true, wp_script_is( 'tufte-places-editor', 'enqueued' ), 'editor: script enqueued in the block editor' );
tufte_places_assert_same( false, str_contains( (string) file_get_contents( WP_PLUGIN_DIR . '/tufte-places/assets/js/editor.js' ), 'import ' ), 'editor: no module imports (no build step)' );

$tp_editor_js = (string) file_get_contents( WP_PLUGIN_DIR . '/tufte-places/assets/js/editor.js' );
tufte_places_assert_same( false, str_contains( $tp_editor_js, "key: 'library-caption'" ), 'editor: no library caption sibling on the canvas' );
tufte_places_assert_same( true, str_contains( $tp_editor_js, 'DOMParser' ) && ! str_contains( $tp_editor_js, 'innerHTML' ), 'editor: captions parse without innerHTML' );

<?php
/**
 * Tests for the block editor script.
 *
 * @package Tufte_Places
 */

do_action( 'enqueue_block_editor_assets' );
tufte_places_assert_same( true, wp_script_is( 'tufte-places-editor', 'enqueued' ), 'editor: script enqueued in the block editor' );
tufte_places_assert_same( false, str_contains( (string) file_get_contents( WP_PLUGIN_DIR . '/tufte-places/assets/js/editor.js' ), 'import ' ), 'editor: no module imports (no build step)' );

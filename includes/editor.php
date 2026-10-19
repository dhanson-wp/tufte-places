<?php
/**
 * Block editor script: the gallery map toggle and caption handling.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the editor script.
 */
function tufte_places_enqueue_editor_script(): void {
	wp_enqueue_script(
		'tufte-places-editor',
		plugins_url( 'assets/js/editor.js', TUFTE_PLACES_PLUGIN_FILE ),
		array(
			'wp-hooks',
			'wp-compose',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-data',
			'wp-core-data',
			'wp-i18n',
		),
		TUFTE_PLACES_VERSION,
		true
	);
	wp_set_script_translations( 'tufte-places-editor', 'tufte-places' );
}
add_action( 'enqueue_block_editor_assets', 'tufte_places_enqueue_editor_script' );

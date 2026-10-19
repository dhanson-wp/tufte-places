<?php
/**
 * Plugin Name: Tufte Places
 * Description: Places for photos and posts: a reusable place record with a name, address, coordinates, and precision, shown as a line under images and galleries.
 * Version:     0.1.0
 * Author:      Derek Hanson
 * License:     GPL-2.0-or-later
 * Text Domain: tufte-places
 *
 * @package Tufte_Places
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TUFTE_PLACES_VERSION', '0.1.0' );
define( 'TUFTE_PLACES_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/admin-terms.php';
require_once __DIR__ . '/includes/attachments.php';
require_once __DIR__ . '/includes/place-line.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/editor.php';
require_once __DIR__ . '/includes/sync.php';
require_once __DIR__ . '/includes/archive.php';
require_once __DIR__ . '/includes/cli.php';

/**
 * Flush rewrite rules on activation, after the taxonomy is registered.
 */
function tufte_places_activate(): void {
	tufte_places_register_taxonomy();
	flush_rewrite_rules();
	update_option( 'tufte_places_rewrite_version', TUFTE_PLACES_VERSION );
}
register_activation_hook( __FILE__, 'tufte_places_activate' );

/**
 * Flush rewrite rules on deactivation.
 */
function tufte_places_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tufte_places_deactivate' );

/**
 * Flush rewrite rules once when the plugin version changes, which covers
 * deploys where the plugin is already active.
 */
function tufte_places_maybe_flush_rewrites(): void {
	if ( TUFTE_PLACES_VERSION === get_option( 'tufte_places_rewrite_version' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'tufte_places_rewrite_version', TUFTE_PLACES_VERSION );
}
add_action( 'init', 'tufte_places_maybe_flush_rewrites', 20 );

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

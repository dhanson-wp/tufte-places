<?php
/**
 * The place line shown under images and galleries.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the place line stylesheet.
 */
function tufte_places_register_line_style(): void {
	wp_register_style(
		'tufte-places-line',
		plugins_url( 'assets/css/place-line.css', TUFTE_PLACES_PLUGIN_FILE ),
		array(),
		TUFTE_PLACES_VERSION
	);
}
add_action( 'init', 'tufte_places_register_line_style' );

/**
 * Ancestor names from the nearest parent up, joined with commas.
 *
 * @param int $term_id Place term ID.
 * @return string Empty for a top-level place.
 */
function tufte_places_locality( int $term_id ): string {
	$names = array();
	foreach ( get_ancestors( $term_id, 'place', 'taxonomy' ) as $ancestor_id ) {
		$ancestor = get_term( (int) $ancestor_id, 'place' );
		if ( $ancestor instanceof WP_Term ) {
			$names[] = $ancestor->name;
		}
	}
	return implode( ', ', $names );
}

/**
 * Format a coordinate without trailing zeros or a locale decimal separator.
 *
 * @param float $value Coordinate.
 * @return string
 */
function tufte_places_format_coordinate( float $value ): string {
	return rtrim( rtrim( number_format( $value, 8, '.', '' ), '0' ), '.' );
}

/**
 * OpenStreetMap URL for an exact place with coordinates.
 *
 * @param array $place Place array from tufte_places_get_place().
 * @return string Empty when there is nothing to link to.
 */
function tufte_places_map_url( array $place ): string {
	if ( 'exact' !== ( $place['precision'] ?? '' ) || ! is_float( $place['lat'] ?? null ) || ! is_float( $place['lng'] ?? null ) ) {
		return '';
	}

	$url = sprintf(
		'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=18/%1$s/%2$s',
		tufte_places_format_coordinate( $place['lat'] ),
		tufte_places_format_coordinate( $place['lng'] )
	);

	/**
	 * Filters the map URL for a place.
	 *
	 * @param string $url   Map URL.
	 * @param array  $place Place array.
	 */
	return (string) apply_filters( 'tufte_places_map_url', $url, $place );
}

/**
 * Attachment IDs used by an image or gallery block.
 *
 * @param array $block Parsed block.
 * @return int[]
 */
function tufte_places_block_attachment_ids( array $block ): array {
	$name = $block['blockName'] ?? '';

	if ( 'core/image' === $name ) {
		$id = (int) ( $block['attrs']['id'] ?? 0 );
		return $id > 0 ? array( $id ) : array();
	}

	if ( 'core/gallery' === $name ) {
		$ids = array();
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
			$ids = array_merge( $ids, tufte_places_block_attachment_ids( (array) $inner ) );
		}
		return $ids;
	}

	return array();
}

/**
 * Place term IDs for a list of attachments, in order of first appearance.
 *
 * @param int[] $attachment_ids Attachment IDs.
 * @return int[]
 */
function tufte_places_places_for_attachments( array $attachment_ids ): array {
	$found = array();
	foreach ( $attachment_ids as $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			continue;
		}
		$terms = wp_get_object_terms( $attachment_id, 'place', array( 'orderby' => 'term_id', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->term_id, $found, true ) ) {
				$found[] = (int) $term->term_id;
			}
		}
	}
	return $found;
}

/**
 * Pin icon markup.
 *
 * @return string
 */
function tufte_places_pin_html(): string {
	return '<span class="tufte-places-pin"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg></span>';
}

/**
 * The place line for a list of place term IDs.
 *
 * @param int[] $term_ids Place term IDs.
 * @return string Empty when there is nothing to show.
 */
function tufte_places_line_html( array $term_ids ): string {
	$sep     = '<span class="tufte-places-sep" aria-hidden="true">·</span>';
	$entries = '';

	foreach ( $term_ids as $term_id ) {
		$place = tufte_places_get_place( (int) $term_id );
		if ( null === $place ) {
			continue;
		}

		$entry = tufte_places_pin_html() . '<span class="tufte-places-name">' . esc_html( $place['name'] ) . '</span>';

		$locality = tufte_places_locality( $place['id'] );
		if ( '' !== $locality ) {
			$entry .= $sep . '<span class="tufte-places-locality">' . esc_html( $locality ) . '</span>';
		}

		$map_url = tufte_places_map_url( $place );
		if ( '' !== $map_url ) {
			$entry .= $sep . '<a class="tufte-places-map" href="' . esc_url( $map_url ) . '">' . esc_html__( 'Map', 'tufte-places' ) . '</a>';
		}

		if ( '' !== $place['link'] ) {
			$entry .= $sep . '<a class="tufte-places-more" href="' . esc_url( $place['link'] ) . '">' . esc_html__( 'More from here', 'tufte-places' ) . '</a>';
		}

		$entries .= '<p class="tufte-places-place">' . $entry . '</p>';
	}

	if ( '' === $entries ) {
		return '';
	}

	return '<div class="tufte-places-line" role="group" aria-label="' . esc_attr__( 'Location', 'tufte-places' ) . '">' . $entries . '</div>';
}

<?php
/**
 * The Google Maps link field.
 *
 * A place can be made from a Google Maps link alone: on save, the link's
 * coordinates fill the place's lat and lng, and a link with only a text query
 * fills an empty address. Short links from the Maps app are followed to the
 * full link first. Nobody types coordinates.
 *
 * @package Tufte_Places
 * @since 0.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The links the field accepts: Google Maps on any country domain, and its
 * short links. Shared with the REST schema so both refuse the same links.
 */
const TUFTE_PLACES_MAP_URL_PATTERN = '^https?://(maps\.app\.goo\.gl/|goo\.gl/maps/|(www\.)?google\.[a-z]{2,3}(\.[a-z]{2})?/maps|maps\.google\.[a-z]{2,3}(\.[a-z]{2})?(/|\?|$))';

/**
 * Whether a URL is a Google Maps link.
 *
 * @param string $url URL.
 * @return bool
 */
function tufte_places_is_map_url( string $url ): bool {
	return 1 === preg_match( '#' . TUFTE_PLACES_MAP_URL_PATTERN . '#i', $url );
}

/**
 * Sanitize a map link: a Google Maps URL, or an empty string.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tufte_places_sanitize_map_url( $value ): string {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$url = esc_url_raw( trim( $value ) );
	return tufte_places_is_map_url( $url ) ? $url : '';
}

/**
 * A latitude and longitude pair, when both are in range.
 *
 * @param string $lat Latitude.
 * @param string $lng Longitude.
 * @return float[]|null
 */
function tufte_places_coordinate_pair( string $lat, string $lng ): ?array {
	$lat = tufte_places_bounded_coordinate( $lat, 90.0 );
	$lng = tufte_places_bounded_coordinate( $lng, 180.0 );
	return ( null === $lat || null === $lng ) ? null : array( $lat, $lng );
}

/**
 * Pull the coordinates, or failing that a text query, out of a Google Maps link.
 *
 * Checks, in order: the pin (`!3d…!4d…`), coordinates in a place or search
 * path, the view center (`@lat,lng`), and the `q`, `ll`, and `query`
 * parameters. Short links have nothing to parse; resolve them first.
 *
 * @param string $url Google Maps URL.
 * @return array{lat: float|null, lng: float|null, query: string}
 */
function tufte_places_parse_map_url( string $url ): array {
	$result = array(
		'lat'   => null,
		'lng'   => null,
		'query' => '',
	);
	if ( ! tufte_places_is_map_url( $url ) ) {
		return $result;
	}

	$number = '(-?\d{1,3}(?:\.\d+)?)';
	$path   = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$pair   = null;

	if ( preg_match( '/!3d' . $number . '!4d' . $number . '/', $url, $m ) ) {
		$pair = tufte_places_coordinate_pair( $m[1], $m[2] );
	} elseif ( preg_match( '#/maps/(?:place|search)/' . $number . ',\s*\+?' . $number . '(?:/|$)#', $path, $m ) ) {
		$pair = tufte_places_coordinate_pair( $m[1], $m[2] );
	} elseif ( preg_match( '/@' . $number . ',' . $number . '/', $path, $m ) ) {
		$pair = tufte_places_coordinate_pair( $m[1], $m[2] );
	} else {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
		foreach ( array( 'q', 'll', 'query' ) as $key ) {
			$value = isset( $params[ $key ] ) && is_string( $params[ $key ] ) ? trim( $params[ $key ] ) : '';
			if ( '' === $value ) {
				continue;
			}
			if ( preg_match( '/^' . $number . '\s*,\s*' . $number . '$/', $value, $m ) ) {
				$pair = tufte_places_coordinate_pair( $m[1], $m[2] );
			} else {
				$result['query'] = sanitize_text_field( $value );
			}
			break;
		}
	}

	if ( null !== $pair ) {
		list( $result['lat'], $result['lng'] ) = $pair;
	}
	return $result;
}

/**
 * Follow a short link from the Maps app to the full Google Maps link.
 *
 * Makes no request for a full link. Follows up to three redirects, one at a
 * time, and only while they stay on the short-link hosts.
 *
 * @param string $url Google Maps URL, short or full.
 * @return string The full link, or an empty string when it can't be resolved.
 */
function tufte_places_resolve_map_url( string $url ): string {
	for ( $hops = 0; $hops <= 3; $hops++ ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! in_array( $host, array( 'maps.app.goo.gl', 'goo.gl' ), true ) ) {
			return tufte_places_is_map_url( $url ) ? $url : '';
		}
		$response = wp_safe_remote_head(
			$url,
			array(
				'redirection' => 0,
				'timeout'     => 5,
			)
		);
		$url      = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'location' );
		if ( '' === $url ) {
			return '';
		}
	}
	return '';
}

/**
 * Fill a place's coordinates, and an empty address, from its map link.
 *
 * A link without coordinates clears the old ones, since they belonged to the
 * old link, and the place then needs coordinates.
 *
 * @param int    $term_id Place term ID.
 * @param string $url     Google Maps URL.
 */
function tufte_places_fill_from_map_url( int $term_id, string $url ): void {
	if ( 'general' === get_term_meta( $term_id, 'precision', true ) ) {
		return;
	}

	$parsed = tufte_places_parse_map_url( tufte_places_resolve_map_url( $url ) );
	if ( null === $parsed['lat'] ) {
		delete_term_meta( $term_id, 'lat' );
		delete_term_meta( $term_id, 'lng' );
	} else {
		update_term_meta( $term_id, 'lat', $parsed['lat'] );
		update_term_meta( $term_id, 'lng', $parsed['lng'] );
	}

	if ( '' !== $parsed['query'] && '' === (string) get_term_meta( $term_id, 'address', true ) ) {
		update_term_meta( $term_id, 'address', $parsed['query'] );
	}
}

/**
 * Whether an exact place is missing its coordinates.
 *
 * @param array $place Place array from tufte_places_get_place().
 * @return bool
 */
function tufte_places_needs_coordinates( array $place ): bool {
	return 'exact' === ( $place['precision'] ?? '' ) && ( null === ( $place['lat'] ?? null ) || null === ( $place['lng'] ?? null ) );
}

/**
 * The map link before a REST save, by term ID.
 *
 * @param int         $term_id Place term ID.
 * @param string|null $url     The link to remember, or null to read it back.
 * @return string
 */
function tufte_places_map_url_before_rest( int $term_id, ?string $url = null ): string {
	static $before = array();
	if ( null !== $url ) {
		$before[ $term_id ] = $url;
	}
	return $before[ $term_id ] ?? '';
}

/**
 * Remember the map link before the REST API saves a place's meta.
 *
 * @param WP_Term $term Inserted or updated term.
 */
function tufte_places_rest_before_map_url( WP_Term $term ): void {
	tufte_places_map_url_before_rest( (int) $term->term_id, (string) get_term_meta( $term->term_id, 'map_url', true ) );
}
add_action( 'rest_insert_place', 'tufte_places_rest_before_map_url' );

/**
 * Fill the coordinates after a REST save that changed the map link, unless the
 * same request sent coordinates, which win.
 *
 * Runs before the general-place guard.
 *
 * @param WP_Term         $term    Inserted or updated term.
 * @param WP_REST_Request $request Request.
 */
function tufte_places_rest_fill_from_map_url( WP_Term $term, WP_REST_Request $request ): void {
	$meta = $request['meta'];
	if ( ! is_array( $meta ) || ! array_key_exists( 'map_url', $meta ) || isset( $meta['lat'] ) || isset( $meta['lng'] ) ) {
		return;
	}
	$term_id = (int) $term->term_id;
	$url     = (string) get_term_meta( $term_id, 'map_url', true );
	if ( '' !== $url && tufte_places_map_url_before_rest( $term_id ) !== $url ) {
		tufte_places_fill_from_map_url( $term_id, $url );
	}
}
add_action( 'rest_after_insert_place', 'tufte_places_rest_fill_from_map_url', 5, 2 );

/**
 * Add the Map column to the Places list.
 *
 * @param string[] $columns Columns.
 * @return string[]
 */
function tufte_places_map_column( array $columns ): array {
	$added = array();
	foreach ( $columns as $key => $label ) {
		if ( 'posts' === $key ) {
			$added['tufte_places_map'] = __( 'Map', 'tufte-places' );
		}
		$added[ $key ] = $label;
	}
	if ( ! isset( $added['tufte_places_map'] ) ) {
		$added['tufte_places_map'] = __( 'Map', 'tufte-places' );
	}
	return $added;
}
add_filter( 'manage_edit-place_columns', 'tufte_places_map_column' );

/**
 * The Map column: a map link, "Needs coordinates", or "General".
 *
 * @param string $content Column content.
 * @param string $column  Column name.
 * @param int    $term_id Place term ID.
 * @return string
 */
function tufte_places_map_column_content( $content, $column, $term_id ) {
	if ( 'tufte_places_map' !== $column ) {
		return $content;
	}
	$place = tufte_places_get_place( (int) $term_id );
	if ( null === $place ) {
		return $content;
	}
	if ( 'general' === $place['precision'] ) {
		return esc_html__( 'General', 'tufte-places' );
	}
	if ( tufte_places_needs_coordinates( $place ) ) {
		return '<strong>' . esc_html__( 'Needs coordinates', 'tufte-places' ) . '</strong>';
	}
	return '<a href="' . esc_url( tufte_places_map_url( $place ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'On the map', 'tufte-places' ) . '</a>';
}
add_filter( 'manage_place_custom_column', 'tufte_places_map_column_content', 10, 3 );

<?php
/**
 * The place taxonomy and its term meta.
 *
 * A place is a hierarchical term on posts and attachments, with a precision
 * (exact or general), an address, and coordinates. A general place never
 * stores or outputs an address or coordinates, in the database or in REST.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Object types that can carry a place.
 *
 * @return string[]
 */
function tufte_places_object_types(): array {
	/**
	 * Filters the object types the place taxonomy attaches to.
	 *
	 * @param string[] $types Post type names.
	 */
	return (array) apply_filters( 'tufte_places_object_types', array( 'post', 'attachment' ) );
}

/**
 * Sanitize a precision value: exact unless it is exactly "general".
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tufte_places_sanitize_precision( $value ): string {
	return 'general' === $value ? 'general' : 'exact';
}

/**
 * Sanitize a coordinate: a float, or an empty string when not numeric.
 *
 * @param mixed $value Raw value.
 * @return float|string
 */
function tufte_places_sanitize_coordinate( $value ) {
	return is_numeric( $value ) ? (float) $value : '';
}

/**
 * Register the place taxonomy and its term meta.
 */
function tufte_places_register_taxonomy(): void {
	register_taxonomy(
		'place',
		tufte_places_object_types(),
		array(
			'labels'            => array(
				'name'          => __( 'Places', 'tufte-places' ),
				'singular_name' => __( 'Place', 'tufte-places' ),
				'add_new_item'  => __( 'Add new place', 'tufte-places' ),
				'edit_item'     => __( 'Edit place', 'tufte-places' ),
				'search_items'  => __( 'Search places', 'tufte-places' ),
				'all_items'     => __( 'All places', 'tufte-places' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'rest_base'         => 'places',
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'place' ),
		)
	);

	register_term_meta(
		'place',
		'precision',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'exact',
			'show_in_rest'      => true,
			'sanitize_callback' => 'tufte_places_sanitize_precision',
		)
	);
	register_term_meta(
		'place',
		'address',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	foreach ( array( 'lat', 'lng' ) as $key ) {
		register_term_meta(
			'place',
			$key,
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'tufte_places_sanitize_coordinate',
			)
		);
	}
}
add_action( 'init', 'tufte_places_register_taxonomy' );

/**
 * Get a place as a plain array.
 *
 * A general place returns an empty address and null coordinates.
 *
 * @param int $term_id Place term ID.
 * @return array|null Null when the term doesn't exist.
 */
function tufte_places_get_place( int $term_id ): ?array {
	$term = get_term( $term_id, 'place' );
	if ( ! ( $term instanceof WP_Term ) ) {
		return null;
	}

	$precision = tufte_places_sanitize_precision( get_term_meta( $term_id, 'precision', true ) );
	$address   = '';
	$lat       = null;
	$lng       = null;

	if ( 'exact' === $precision ) {
		$address = (string) get_term_meta( $term_id, 'address', true );
		$raw_lat = get_term_meta( $term_id, 'lat', true );
		$raw_lng = get_term_meta( $term_id, 'lng', true );
		$lat     = is_numeric( $raw_lat ) ? (float) $raw_lat : null;
		$lng     = is_numeric( $raw_lng ) ? (float) $raw_lng : null;
	}

	$link = get_term_link( $term );

	return array(
		'id'        => $term->term_id,
		'name'      => $term->name,
		'slug'      => $term->slug,
		'precision' => $precision,
		'address'   => $address,
		'lat'       => $lat,
		'lng'       => $lng,
		'link'      => is_wp_error( $link ) ? '' : $link,
		'parent'    => (int) $term->parent,
	);
}

/**
 * Delete address and coordinates from a general place.
 *
 * @param int $term_id Place term ID.
 */
function tufte_places_enforce_general( int $term_id ): void {
	if ( 'general' !== get_term_meta( $term_id, 'precision', true ) ) {
		return;
	}
	delete_term_meta( $term_id, 'address' );
	delete_term_meta( $term_id, 'lat' );
	delete_term_meta( $term_id, 'lng' );
}
add_action( 'created_place', 'tufte_places_enforce_general' );
add_action( 'edited_place', 'tufte_places_enforce_general' );

/**
 * Enforce general precision after the REST API has saved a place and its meta.
 *
 * @param WP_Term $term Inserted or updated term.
 */
function tufte_places_enforce_general_after_rest( WP_Term $term ): void {
	tufte_places_enforce_general( (int) $term->term_id );
}
add_action( 'rest_after_insert_place', 'tufte_places_enforce_general_after_rest' );

/**
 * Second guard: blank address and coordinates in REST responses for general places.
 *
 * @param WP_REST_Response $response Response.
 * @param WP_Term          $item     Term.
 * @return WP_REST_Response
 */
function tufte_places_rest_prepare_place( $response, $item ) {
	if ( ! ( $item instanceof WP_Term ) || 'general' !== get_term_meta( $item->term_id, 'precision', true ) ) {
		return $response;
	}

	$data = $response->get_data();
	if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
		$data['meta']['address'] = '';
		$data['meta']['lat']     = null;
		$data['meta']['lng']     = null;
		$response->set_data( $data );
	}

	return $response;
}
add_filter( 'rest_prepare_place', 'tufte_places_rest_prepare_place', 10, 2 );

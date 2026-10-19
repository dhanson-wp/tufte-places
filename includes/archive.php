<?php
/**
 * The place archive: header, posts, and a grid of photos taken there.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the archive stylesheet.
 */
function tufte_places_register_archive_style(): void {
	wp_register_style(
		'tufte-places-archive',
		plugins_url( 'assets/css/archive.css', TUFTE_PLACES_PLUGIN_FILE ),
		array(),
		TUFTE_PLACES_VERSION
	);
}
add_action( 'init', 'tufte_places_register_archive_style' );

/**
 * Photos for a place archive: tagged with the place or a descendant, and used
 * in a published, public post that also has the place or a descendant.
 *
 * @param int $term_id Place term ID.
 * @param int $limit   Maximum photos.
 * @return int[] Newest post first, then content order, deduplicated.
 */
function tufte_places_archive_photo_ids( int $term_id, int $limit = 60 ): array {
	$types = array_values( array_filter( tufte_places_synced_types(), static fn( string $type ): bool => is_post_type_viewable( $type ) ) );
	if ( $term_id <= 0 || $limit <= 0 || array() === $types ) {
		return array();
	}

	$term_ids   = array_map( 'intval', get_term_children( $term_id, 'place' ) );
	$term_ids[] = $term_id;

	$args = array(
		'post_type'              => $types,
		'post_status'            => 'publish',
		'has_password'           => false,
		'posts_per_page'         => 50,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'tax_query'              => array(
			array(
				'taxonomy'         => 'place',
				'field'            => 'term_id',
				'terms'            => $term_id,
				'include_children' => true,
			),
		),
	);

	$result = array();
	$paged  = 1;
	do {
		$posts = get_posts( $args + array( 'paged' => $paged ) );
		++$paged;

		foreach ( $posts as $post ) {
			$candidates = array_values( array_filter( tufte_places_content_attachment_ids( $post->post_content ), static fn( int $id ): bool => ! isset( $result[ $id ] ) ) );
			if ( array() === $candidates ) {
				continue;
			}
			_prime_post_caches( $candidates, false, false );
			update_object_term_cache( $candidates, 'attachment' );

			foreach ( $candidates as $attachment_id ) {
				if ( 'attachment' !== get_post_type( $attachment_id ) ) {
					continue;
				}
				$tagged = wp_get_object_terms( $attachment_id, 'place', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $tagged ) || array() === array_intersect( array_map( 'intval', $tagged ), $term_ids ) ) {
					continue;
				}
				$result[ $attachment_id ] = $attachment_id;
				if ( count( $result ) >= $limit ) {
					break 3;
				}
			}
		}
	} while ( count( $posts ) === 50 );

	return array_values( $result );
}

/**
 * Render the photo grid for a place.
 *
 * @param int $term_id Place term ID.
 * @return string Empty when there are no photos.
 */
function tufte_places_render_photos( int $term_id ): string {
	$figures = '';
	foreach ( tufte_places_archive_photo_ids( $term_id ) as $attachment_id ) {
		$image = wp_get_attachment_image( $attachment_id, 'medium_large' );
		if ( '' === $image ) {
			continue;
		}
		$caption  = tufte_places_effective_caption( '', $attachment_id );
		$figures .= '<figure class="tufte-places-photo">' . $image;
		if ( '' !== $caption ) {
			$figures .= '<figcaption class="wp-element-caption">' . $caption . '</figcaption>';
		}
		$figures .= '</figure>';
	}

	if ( '' === $figures ) {
		return '';
	}

	wp_enqueue_style( 'tufte-places-archive' );

	return '<div class="tufte-places-photos">' . $figures . '</div>';
}

/**
 * Render the header for a place.
 *
 * @param int $term_id Place term ID.
 * @return string Empty when the place doesn't exist.
 */
function tufte_places_render_place_header( int $term_id ): string {
	$place = tufte_places_get_place( $term_id );
	if ( null === $place ) {
		return '';
	}

	$html = '<header class="tufte-places-header"><h1 class="tufte-places-header-name">' . esc_html( $place['name'] ) . '</h1>';

	$locality = tufte_places_locality( $place['id'] );
	if ( '' !== $locality ) {
		$html .= '<p class="tufte-places-header-locality">' . esc_html( $locality ) . '</p>';
	}

	if ( 'exact' === $place['precision'] && '' !== $place['address'] ) {
		$html   .= '<p class="tufte-places-header-address">' . esc_html( $place['address'] );
		$map_url = tufte_places_map_url( $place );
		if ( '' !== $map_url ) {
			$html .= ' <a class="tufte-places-map" href="' . esc_url( $map_url ) . '">' . esc_html__( 'Map', 'tufte-places' ) . '</a>';
		}
		$html .= '</p>';
	}

	return $html . '</header>';
}

/**
 * Render callback for the place header block.
 *
 * @return string
 */
function tufte_places_render_place_header_block(): string {
	if ( ! is_tax( 'place' ) ) {
		return '';
	}
	return tufte_places_render_place_header( (int) get_queried_object_id() );
}

/**
 * Render callback for the photos block.
 *
 * @return string
 */
function tufte_places_render_photos_block(): string {
	if ( ! is_tax( 'place' ) ) {
		return '';
	}
	return tufte_places_render_photos( (int) get_queried_object_id() );
}

/**
 * Register the archive blocks and template.
 */
function tufte_places_register_archive(): void {
	register_block_type(
		'tufte-places/photos',
		array(
			'title'           => __( 'Place photos', 'tufte-places' ),
			'category'        => 'theme',
			'supports'        => array( 'inserter' => false ),
			'render_callback' => 'tufte_places_render_photos_block',
		)
	);
	register_block_type(
		'tufte-places/place-header',
		array(
			'title'           => __( 'Place header', 'tufte-places' ),
			'category'        => 'theme',
			'supports'        => array( 'inserter' => false ),
			'render_callback' => 'tufte_places_render_place_header_block',
		)
	);

	if ( function_exists( 'register_block_template' ) ) {
		register_block_template(
			'tufte-places//taxonomy-place',
			array(
				'title'       => __( 'Place archive', 'tufte-places' ),
				'description' => __( 'Shows a place with its posts and photos.', 'tufte-places' ),
				'content'     => (string) file_get_contents( dirname( TUFTE_PLACES_PLUGIN_FILE ) . '/templates/taxonomy-place.html' ),
			)
		);
	}
}
add_action( 'init', 'tufte_places_register_archive' );

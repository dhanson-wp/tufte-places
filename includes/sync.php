<?php
/**
 * Keep a post's places in step with the places of the photos it uses.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_post_meta(
	'',
	'tufte_places_derived',
	array(
		'type'         => 'array',
		'single'       => true,
		'show_in_rest' => false,
	)
);

/**
 * Post types whose places are derived from their content.
 *
 * @return string[]
 */
function tufte_places_synced_types(): array {
	return array_values( array_diff( tufte_places_object_types(), array( 'attachment' ) ) );
}

/**
 * Attachment IDs used by image and gallery blocks, including nested ones.
 *
 * @param string $content Post content.
 * @return int[] Deduplicated, in order of first appearance.
 */
function tufte_places_content_attachment_ids( string $content ): array {
	$walk = static function ( array $blocks ) use ( &$walk ): array {
		$ids = array();
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'] ?? '', array( 'core/image', 'core/gallery' ), true ) ) {
				$ids = array_merge( $ids, tufte_places_block_attachment_ids( $block ) );
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, $walk( $block['innerBlocks'] ) );
			}
		}
		return $ids;
	};

	return array_values( array_unique( array_map( 'intval', $walk( parse_blocks( $content ) ) ) ) );
}

/**
 * Set a post's places to its hand-added places plus those of its photos.
 *
 * @param int $post_id Post ID.
 */
function tufte_places_sync_post( int $post_id ): void {
	$post = get_post( $post_id );
	if (
		! ( $post instanceof WP_Post )
		|| 'revision' === $post->post_type
		|| wp_is_post_autosave( $post )
		|| ! in_array( $post->post_type, tufte_places_synced_types(), true )
	) {
		return;
	}

	$derived  = tufte_places_places_for_attachments( tufte_places_content_attachment_ids( $post->post_content ) );
	$previous = array_map( 'intval', (array) get_post_meta( $post_id, 'tufte_places_derived', true ) );
	$current  = wp_get_object_terms( $post_id, 'place', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $current ) ) {
		return;
	}

	$terms = array_values( array_unique( array_merge( array_diff( array_map( 'intval', $current ), $previous ), $derived ) ) );

	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;
	wp_set_object_terms( $post_id, $terms, 'place' );
	$running = false;

	update_post_meta( $post_id, 'tufte_places_derived', $derived );
}

/**
 * Sync a post after it is saved.
 *
 * @param int $post_id Post ID.
 */
function tufte_places_sync_after_insert( int $post_id ): void {
	tufte_places_sync_post( $post_id );
}
add_action( 'wp_after_insert_post', 'tufte_places_sync_after_insert' );

/**
 * Post IDs whose content uses an attachment in an image or gallery block.
 *
 * @param int $attachment_id Attachment ID.
 * @return int[]
 */
function tufte_places_posts_using_attachment( int $attachment_id ): array {
	global $wpdb;

	$types = tufte_places_synced_types();
	if ( $attachment_id <= 0 || array() === $types ) {
		return array();
	}

	$type_sql = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$like_img = '%' . $wpdb->esc_like( 'wp-image-' . $attachment_id . '"' ) . '%';
	$like_a   = '%' . $wpdb->esc_like( '"id":' . $attachment_id . ',' ) . '%';
	$like_b   = '%' . $wpdb->esc_like( '"id":' . $attachment_id . '}' ) . '%';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $type_sql is only placeholders.
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ('trash', 'auto-draft') AND post_type IN ({$type_sql}) AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC",
		array_merge( $types, array( $like_img, $like_a, $like_b ) )
	);

	return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Resync every post that uses a photo when the photo's places change.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms     Term names or IDs passed in.
 * @param array  $tt_ids    Term taxonomy IDs.
 * @param string $taxonomy  Taxonomy.
 */
function tufte_places_resync_for_attachment( $object_id, $terms, $tt_ids, $taxonomy ): void {
	if ( 'place' !== $taxonomy || 'attachment' !== get_post_type( (int) $object_id ) ) {
		return;
	}
	foreach ( tufte_places_posts_using_attachment( (int) $object_id ) as $post_id ) {
		tufte_places_sync_post( $post_id );
	}
}
add_action( 'set_object_terms', 'tufte_places_resync_for_attachment', 10, 4 );

<?php
/**
 * The keep-off-the-map flag on photos.
 *
 * The flag only affects data for a future map. A flagged photo still shows
 * its place line on the page.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the hide-from-map meta on attachments.
 */
function tufte_places_register_attachment_meta(): void {
	register_post_meta(
		'attachment',
		'tufte_places_hide_from_map',
		array(
			'type'         => 'boolean',
			'single'       => true,
			'default'      => false,
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'tufte_places_register_attachment_meta' );

/**
 * Whether a photo is kept off the map.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function tufte_places_photo_hidden_from_map( int $attachment_id ): bool {
	return (bool) get_post_meta( $attachment_id, 'tufte_places_hide_from_map', true );
}

/**
 * Add the "Keep off the map" checkbox to the media modal and attachment screen.
 *
 * @param array   $fields Attachment fields.
 * @param WP_Post $post   Attachment.
 * @return array
 */
function tufte_places_attachment_fields( array $fields, WP_Post $post ): array {
	$id      = (int) $post->ID;
	$checked = tufte_places_photo_hidden_from_map( $id ) ? ' checked="checked"' : '';

	$fields['tufte_places_hide_from_map'] = array(
		'label' => __( 'Keep off the map', 'tufte-places' ),
		'input' => 'html',
		'html'  => sprintf(
			'<input type="checkbox" id="attachments-%1$d-tufte_places_hide_from_map" name="attachments[%1$d][tufte_places_hide_from_map]" value="1"%2$s />',
			$id,
			$checked
		),
		'helps' => __( 'Still shows its place in posts.', 'tufte-places' ),
	);

	return $fields;
}
add_filter( 'attachment_fields_to_edit', 'tufte_places_attachment_fields', 10, 2 );

/**
 * Save the "Keep off the map" checkbox. An absent field means unchecked.
 *
 * @param array $post       Attachment data being saved.
 * @param array $attachment Submitted attachment fields.
 * @return array
 */
function tufte_places_attachment_fields_save( array $post, array $attachment ): array {
	$id = (int) ( $post['ID'] ?? 0 );
	if ( $id > 0 ) {
		if ( ! empty( $attachment['tufte_places_hide_from_map'] ) ) {
			update_post_meta( $id, 'tufte_places_hide_from_map', true );
		} else {
			delete_post_meta( $id, 'tufte_places_hide_from_map' );
		}
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'tufte_places_attachment_fields_save', 10, 2 );

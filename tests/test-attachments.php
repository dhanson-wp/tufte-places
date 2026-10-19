<?php
/**
 * Tests for the keep-off-the-map flag on photos.
 *
 * @package Tufte_Places
 */

$tp_att = wp_insert_attachment(
	array(
		'post_title'     => 'Test photo',
		'post_mime_type' => 'image/jpeg',
		'post_status'    => 'inherit',
	)
);

tufte_places_assert_same( false, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: visible on the map by default' );
tufte_places_attachment_fields_save( array( 'ID' => $tp_att ), array( 'tufte_places_hide_from_map' => '1' ) );
tufte_places_assert_same( true, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: flag saved from the media modal' );
tufte_places_attachment_fields_save( array( 'ID' => $tp_att ), array() );
tufte_places_assert_same( false, tufte_places_photo_hidden_from_map( $tp_att ), 'photo: unchecked clears the flag' );
tufte_places_assert_same( true, isset( tufte_places_attachment_fields( array(), get_post( $tp_att ) )['tufte_places_hide_from_map'] ), 'photo: checkbox field present' );
tufte_places_assert_same( true, in_array( 'place', get_object_taxonomies( 'attachment' ), true ), 'photo: place taxonomy available in the media modal' );

wp_delete_attachment( $tp_att, true );

<?php
/**
 * Tests for the place line markup.
 *
 * @package Tufte_Places
 */

$tp_paris      = (int) wp_insert_term( 'Paris', 'place' )['term_id'];
$tp_marais  = (int) wp_insert_term( 'Le Marais', 'place', array( 'parent' => $tp_paris ) )['term_id'];
$tp_carnavalet      = (int) wp_insert_term( 'MusÃ©e Carnavalet', 'place', array( 'parent' => $tp_marais ) )['term_id'];
$tp_lyon     = (int) wp_insert_term( 'Lyon', 'place' )['term_id'];
$tp_nocoords = (int) wp_insert_term( 'Somewhere exact', 'place' )['term_id'];

update_term_meta( $tp_carnavalet, 'precision', 'exact' );
update_term_meta( $tp_carnavalet, 'address', '23 Rue de SÃ©vignÃ©' );
update_term_meta( $tp_carnavalet, 'lat', 48.85754 );
update_term_meta( $tp_carnavalet, 'lng', 2.36255 );
update_term_meta( $tp_lyon, 'precision', 'general' );
update_term_meta( $tp_nocoords, 'precision', 'exact' );

$tp_a1 = wp_insert_attachment( array( 'post_title' => 'Line photo one', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$tp_a2 = wp_insert_attachment( array( 'post_title' => 'Line photo two', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
wp_set_object_terms( $tp_a1, array( $tp_carnavalet ), 'place' );
wp_set_object_terms( $tp_a2, array( $tp_lyon ), 'place' );

tufte_places_assert_same( 'Le Marais, Paris', tufte_places_locality( $tp_carnavalet ), 'line: locality is the parent chain' );
tufte_places_assert_same( '', tufte_places_locality( $tp_paris ), 'line: a top-level place has no locality' );
tufte_places_assert_same( 'https://www.openstreetmap.org/?mlat=48.85754&mlon=2.36255#map=18/48.85754/2.36255', tufte_places_map_url( tufte_places_get_place( $tp_carnavalet ) ), 'line: OpenStreetMap link for an exact place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_lyon ) ), 'line: no map link for a general place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_nocoords ) ), 'line: no map link for an exact place without coordinates' );
tufte_places_assert_same( array( $tp_carnavalet, $tp_lyon ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2, $tp_a1, 0, 999999999 ) ), 'line: order of first appearance, deduplicated, missing IDs skipped' );
$tp_html = tufte_places_line_html( array( $tp_carnavalet, $tp_lyon ) );
tufte_places_assert_same( 2, substr_count( $tp_html, 'class="tufte-places-place"' ), 'line: one entry per place' );
tufte_places_assert_same( 1, substr_count( $tp_html, 'openstreetmap.org' ), 'line: only the exact place gets a map link' );
tufte_places_assert_same( 2, substr_count( $tp_html, '>More from here<' ), 'line: every place links to its archive' );
tufte_places_assert_same( '', tufte_places_line_html( array() ), 'line: no places, no markup' );
tufte_places_assert_same( 1, substr_count( tufte_places_line_html( array( $tp_nocoords ) ), '>More from here<' ), 'line: an exact place without coordinates still links to its archive' );
tufte_places_assert_same( 0, substr_count( tufte_places_line_html( array( $tp_nocoords ) ), '>Map<' ), 'line: an exact place without coordinates has no Map link' );
wp_delete_term( $tp_lyon, 'place' );
tufte_places_assert_same( array( $tp_carnavalet ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2 ) ), 'line: a deleted place is skipped' );
tufte_places_assert_same( array( 6162, 6191 ), tufte_places_block_attachment_ids( parse_blocks( tufte_places_fixture( 'gallery.html' ) )[0] ), 'line: gallery image IDs in order' );
tufte_places_assert_same( array(), tufte_places_block_attachment_ids( array( 'blockName' => 'core/image', 'attrs' => array() ) ), 'line: an image with no ID has no attachments' );
tufte_places_assert_same( array( 42 ), tufte_places_block_attachment_ids( array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 42 ) ) ), 'line: an image block gives its ID' );
tufte_places_assert_same( array(), tufte_places_block_attachment_ids( array( 'blockName' => 'core/paragraph', 'attrs' => array() ) ), 'line: other blocks have no attachments' );
tufte_places_assert_same( true, wp_style_is( 'tufte-places-line', 'registered' ), 'line: stylesheet handle registered' );

wp_delete_attachment( $tp_a1, true );
wp_delete_attachment( $tp_a2, true );
foreach ( array( $tp_carnavalet, $tp_marais, $tp_paris, $tp_nocoords ) as $tp_term ) {
	wp_delete_term( $tp_term, 'place' );
}

<?php
/**
 * Tests for the place line markup.
 *
 * @package Tufte_Places
 */

$tp_mtl      = (int) wp_insert_term( 'Montréal', 'place' )['term_id'];
$tp_plateau  = (int) wp_insert_term( 'Le Plateau', 'place', array( 'parent' => $tp_mtl ) )['term_id'];
$tp_hof      = (int) wp_insert_term( 'Hof Kelsten', 'place', array( 'parent' => $tp_plateau ) )['term_id'];
$tp_ames     = (int) wp_insert_term( 'Ames', 'place' )['term_id'];
$tp_nocoords = (int) wp_insert_term( 'Somewhere exact', 'place' )['term_id'];

update_term_meta( $tp_hof, 'precision', 'exact' );
update_term_meta( $tp_hof, 'address', '4524 Rue Saint-Laurent' );
update_term_meta( $tp_hof, 'lat', 45.52039 );
update_term_meta( $tp_hof, 'lng', -73.58704 );
update_term_meta( $tp_ames, 'precision', 'general' );
update_term_meta( $tp_nocoords, 'precision', 'exact' );

$tp_a1 = wp_insert_attachment( array( 'post_title' => 'Line photo one', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$tp_a2 = wp_insert_attachment( array( 'post_title' => 'Line photo two', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
wp_set_object_terms( $tp_a1, array( $tp_hof ), 'place' );
wp_set_object_terms( $tp_a2, array( $tp_ames ), 'place' );

tufte_places_assert_same( 'Le Plateau, Montréal', tufte_places_locality( $tp_hof ), 'line: locality is the parent chain' );
tufte_places_assert_same( '', tufte_places_locality( $tp_mtl ), 'line: a top-level place has no locality' );
tufte_places_assert_same( 'https://www.openstreetmap.org/?mlat=45.52039&mlon=-73.58704#map=18/45.52039/-73.58704', tufte_places_map_url( tufte_places_get_place( $tp_hof ) ), 'line: OpenStreetMap link for an exact place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_ames ) ), 'line: no map link for a general place' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_nocoords ) ), 'line: no map link for an exact place without coordinates' );
tufte_places_assert_same( array( $tp_hof, $tp_ames ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2, $tp_a1, 0, 999999999 ) ), 'line: order of first appearance, deduplicated, missing IDs skipped' );
$tp_html = tufte_places_line_html( array( $tp_hof, $tp_ames ) );
tufte_places_assert_same( 2, substr_count( $tp_html, 'class="tufte-places-place"' ), 'line: one entry per place' );
tufte_places_assert_same( 1, substr_count( $tp_html, 'openstreetmap.org' ), 'line: only the exact place gets a map link' );
tufte_places_assert_same( 2, substr_count( $tp_html, '>More from here<' ), 'line: every place links to its archive' );
tufte_places_assert_same( '', tufte_places_line_html( array() ), 'line: no places, no markup' );
tufte_places_assert_same( 1, substr_count( tufte_places_line_html( array( $tp_nocoords ) ), '>More from here<' ), 'line: an exact place without coordinates still links to its archive' );
tufte_places_assert_same( 0, substr_count( tufte_places_line_html( array( $tp_nocoords ) ), '>Map<' ), 'line: an exact place without coordinates has no Map link' );
wp_delete_term( $tp_ames, 'place' );
tufte_places_assert_same( array( $tp_hof ), tufte_places_places_for_attachments( array( $tp_a1, $tp_a2 ) ), 'line: a deleted place is skipped' );
tufte_places_assert_same( array( 6162, 6191 ), tufte_places_block_attachment_ids( parse_blocks( tufte_places_fixture( 'gallery.html' ) )[0] ), 'line: gallery image IDs in order' );
tufte_places_assert_same( array(), tufte_places_block_attachment_ids( array( 'blockName' => 'core/image', 'attrs' => array() ) ), 'line: an image with no ID has no attachments' );
tufte_places_assert_same( array( 42 ), tufte_places_block_attachment_ids( array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 42 ) ) ), 'line: an image block gives its ID' );
tufte_places_assert_same( array(), tufte_places_block_attachment_ids( array( 'blockName' => 'core/paragraph', 'attrs' => array() ) ), 'line: other blocks have no attachments' );
tufte_places_assert_same( true, wp_style_is( 'tufte-places-line', 'registered' ), 'line: stylesheet handle registered' );

wp_delete_attachment( $tp_a1, true );
wp_delete_attachment( $tp_a2, true );
foreach ( array( $tp_hof, $tp_plateau, $tp_mtl, $tp_nocoords ) as $tp_term ) {
	wp_delete_term( $tp_term, 'place' );
}

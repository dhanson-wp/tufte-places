<?php
/**
 * Tests for the place taxonomy and term meta.
 *
 * @package Tufte_Places
 */

tufte_places_assert_same( true, taxonomy_exists( 'place' ), 'taxonomy: place exists' );
tufte_places_assert_same( true, is_taxonomy_hierarchical( 'place' ), 'taxonomy: place is hierarchical' );
tufte_places_assert_same( array( 'post', 'attachment' ), get_taxonomy( 'place' )->object_type, 'taxonomy: on posts and attachments' );
tufte_places_assert_same( 'places', get_taxonomy( 'place' )->rest_base, 'taxonomy: rest base' );

$tp_exact = wp_insert_term( 'Test Hof Kelsten', 'place' )['term_id'];
update_term_meta( $tp_exact, 'address', '4524 boul. Saint-Laurent' );
update_term_meta( $tp_exact, 'lat', 45.52039 );
update_term_meta( $tp_exact, 'lng', -73.58704 );
$tp = tufte_places_get_place( $tp_exact );
tufte_places_assert_same( 'exact', $tp['precision'], 'place: precision defaults to exact' );
tufte_places_assert_same( 45.52039, $tp['lat'], 'place: exact keeps lat' );

$tp_general = wp_insert_term( 'Test Ames', 'place' )['term_id'];
update_term_meta( $tp_general, 'precision', 'general' );
update_term_meta( $tp_general, 'address', '123 Secret St' );
update_term_meta( $tp_general, 'lat', 42.0 );
tufte_places_enforce_general( $tp_general );
tufte_places_assert_same( '', get_term_meta( $tp_general, 'address', true ), 'general: address cleared' );
tufte_places_assert_same( '', get_term_meta( $tp_general, 'lat', true ), 'general: lat cleared' );
tufte_places_assert_same( null, tufte_places_get_place( $tp_general )['lat'], 'general: get_place returns no lat' );
tufte_places_assert_same( null, tufte_places_get_place( 999999999 ), 'place: missing term is null' );

// REST: a general place never returns coordinates.
$tp_req = new WP_REST_Request( 'GET', '/wp/v2/places/' . $tp_general );
$tp_res = rest_do_request( $tp_req )->get_data();
tufte_places_assert_same( true, empty( $tp_res['meta']['lat'] ) && empty( $tp_res['meta']['address'] ), 'rest: general place has no coordinates or address' );

wp_delete_term( $tp_exact, 'place' );
wp_delete_term( $tp_general, 'place' );

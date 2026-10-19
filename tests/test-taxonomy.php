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

// Hooks: switching to general over REST clears the private meta.
$tp_prev_user = get_current_user_id();
$tp_admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $tp_admins[0] );

$tp_rest = wp_insert_term( 'Test Rest Home', 'place' )['term_id'];
update_term_meta( $tp_rest, 'address', '1 Private Ln' );
update_term_meta( $tp_rest, 'lat', 41.5 );
update_term_meta( $tp_rest, 'lng', -93.6 );
$tp_post = new WP_REST_Request( 'POST', '/wp/v2/places/' . $tp_rest );
$tp_post->set_body_params( array( 'meta' => array( 'precision' => 'general' ) ) );
$tp_post_res = rest_do_request( $tp_post );
tufte_places_assert_same( 200, $tp_post_res->get_status(), 'hooks: REST update succeeds' );
tufte_places_assert_same( 'general', get_term_meta( $tp_rest, 'precision', true ), 'hooks: REST set precision general' );
tufte_places_assert_same( '', get_term_meta( $tp_rest, 'address', true ), 'hooks: rest_after_insert_place clears address' );
tufte_places_assert_same( '', get_term_meta( $tp_rest, 'lat', true ), 'hooks: rest_after_insert_place clears lat' );
tufte_places_assert_same( '', get_term_meta( $tp_rest, 'lng', true ), 'hooks: rest_after_insert_place clears lng' );

// Hooks: edited_place clears the private meta on wp_update_term.
$tp_edit = wp_insert_term( 'Test Edit Home', 'place' )['term_id'];
update_term_meta( $tp_edit, 'address', '2 Private Ln' );
update_term_meta( $tp_edit, 'lat', 40.1 );
update_term_meta( $tp_edit, 'lng', -90.2 );
update_term_meta( $tp_edit, 'precision', 'general' );
wp_update_term( $tp_edit, 'place', array( 'name' => 'Test Edit Home 2' ) );
tufte_places_assert_same( '', get_term_meta( $tp_edit, 'address', true ), 'hooks: edited_place clears address' );
tufte_places_assert_same( '', get_term_meta( $tp_edit, 'lat', true ), 'hooks: edited_place clears lat' );
tufte_places_assert_same( '', get_term_meta( $tp_edit, 'lng', true ), 'hooks: edited_place clears lng' );

// Guard: even if private meta is written behind the hooks' back, REST blanks it.
update_term_meta( $tp_rest, 'address', '1 Private Ln' );
update_term_meta( $tp_rest, 'lat', 41.5 );
update_term_meta( $tp_rest, 'lng', -93.6 );
$tp_guard = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/places/' . $tp_rest ) )->get_data();
tufte_places_assert_same( '', $tp_guard['meta']['address'], 'guard: rest blanks address' );
tufte_places_assert_same( null, $tp_guard['meta']['lat'], 'guard: rest blanks lat' );
tufte_places_assert_same( null, $tp_guard['meta']['lng'], 'guard: rest blanks lng' );

wp_set_current_user( $tp_prev_user );
wp_delete_term( $tp_rest, 'place' );
wp_delete_term( $tp_edit, 'place' );

wp_delete_term( $tp_exact, 'place' );
wp_delete_term( $tp_general, 'place' );

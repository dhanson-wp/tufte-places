<?php
/**
 * Tests for the place fields on the term screens.
 *
 * @package Tufte_Places
 */

$tp_id = wp_insert_term( 'Test Cowork', 'place' )['term_id'];
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_address' => ' 4388 rue Saint-Denis ', 'tufte_places_lat' => '45.52322', 'tufte_places_lng' => 'not a number' ) );
tufte_places_assert_same( '4388 rue Saint-Denis', get_term_meta( $tp_id, 'address', true ), 'term fields: address trimmed' );
tufte_places_assert_same( 45.52322, (float) get_term_meta( $tp_id, 'lat', true ), 'term fields: lat saved as a number' );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lng', true ), 'term fields: an invalid lng is not saved' );

// Out-of-range coordinates are not saved, and delete the previous value.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_address' => 'a', 'tufte_places_lat' => '91', 'tufte_places_lng' => '-180.5' ) );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lat', true ), 'term fields: lat above 90 is not saved' );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lng', true ), 'term fields: lng below -180 is not saved' );
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_address' => 'a', 'tufte_places_lat' => '-90', 'tufte_places_lng' => '180' ) );
tufte_places_assert_same( -90.0, (float) get_term_meta( $tp_id, 'lat', true ), 'term fields: lat at -90 is saved' );
tufte_places_assert_same( 180.0, (float) get_term_meta( $tp_id, 'lng', true ), 'term fields: lng at 180 is saved' );

tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'general', 'tufte_places_address' => 'x', 'tufte_places_lat' => '1', 'tufte_places_lng' => '1' ) );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'address', true ), 'term fields: switching to general clears the address' );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lat', true ), 'term fields: switching to general clears lat' );
tufte_places_assert_same( 'general', get_term_meta( $tp_id, 'precision', true ), 'term fields: precision saved' );

// The form: four inputs and a nonce, on both screens.
ob_start();
do_action( 'place_edit_form_fields', get_term( $tp_id, 'place' ) );
$tp_edit_html = ob_get_clean();
ob_start();
do_action( 'place_add_form_fields', 'place' );
$tp_add_html = ob_get_clean();
foreach ( array( 'edit' => $tp_edit_html, 'add' => $tp_add_html ) as $tp_screen => $tp_html ) {
	foreach ( array( 'tufte_places_precision', 'tufte_places_address', 'tufte_places_lat', 'tufte_places_lng', 'tufte_places_term_nonce' ) as $tp_field ) {
		tufte_places_assert_same( true, false !== strpos( $tp_html, 'name="' . $tp_field . '"' ), "form ({$tp_screen}): has {$tp_field}" );
	}
}
tufte_places_assert_same( true, str_contains( $tp_edit_html, '<legend class="screen-reader-text">Precision</legend>' ) && str_contains( $tp_add_html, '<legend class="screen-reader-text">Precision</legend>' ), 'form: the precision fieldset has a screen reader legend' );
tufte_places_assert_same( true, (bool) preg_match( '/value="general"\s+checked/', $tp_edit_html ), 'form (edit): general is checked for a general place' );

// The hooks check the nonce and the capability before saving.
$tp_prev_user = get_current_user_id();
$tp_post_bak  = $_POST;
$tp_admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$tp_hook_id   = wp_insert_term( 'Test Hook Cowork', 'place' )['term_id'];
$tp_payload   = array( 'tufte_places_precision' => 'exact', 'tufte_places_address' => '1 Main St', 'tufte_places_lat' => '10', 'tufte_places_lng' => '20' );

wp_set_current_user( (int) $tp_admins[0] );
$_POST = $tp_payload;
do_action( 'edited_place', $tp_hook_id );
tufte_places_assert_same( '', get_term_meta( $tp_hook_id, 'address', true ), 'hooks: no nonce, nothing saved' );

$_POST = $tp_payload + array( 'tufte_places_term_nonce' => 'bogus' );
do_action( 'edited_place', $tp_hook_id );
tufte_places_assert_same( '', get_term_meta( $tp_hook_id, 'address', true ), 'hooks: bad nonce, nothing saved' );

wp_set_current_user( 0 );
$_POST = $tp_payload + array( 'tufte_places_term_nonce' => wp_create_nonce( 'tufte_places_term' ) );
do_action( 'edited_place', $tp_hook_id );
tufte_places_assert_same( '', get_term_meta( $tp_hook_id, 'address', true ), 'hooks: no capability, nothing saved' );

wp_set_current_user( (int) $tp_admins[0] );
$_POST = $tp_payload + array( 'tufte_places_term_nonce' => wp_create_nonce( 'tufte_places_term' ) );
do_action( 'created_place', $tp_hook_id );
tufte_places_assert_same( '1 Main St', get_term_meta( $tp_hook_id, 'address', true ), 'hooks: valid nonce and capability saves' );

$_POST = $tp_post_bak;
wp_set_current_user( $tp_prev_user );
wp_delete_term( $tp_hook_id, 'place' );
wp_delete_term( $tp_id, 'place' );

<?php
/**
 * Tests for the Google Maps link field.
 *
 * @package Tufte_Places
 */

// Parsing: one case per URL shape.
$tp_cases = array(
	'pin wins over the view center'  => array( 'https://www.google.com/maps/place/Mus%C3%A9e+Carnavalet/@48.8570,2.3600,17z/data=!3m1!4b1!4m6!3m5!1s0x47e66e0:0x1!8m2!3d48.85754!4d2.36255!16s%2Fm%2F0abc', 48.85754, 2.36255, '' ),
	'view link'                      => array( 'https://www.google.com/maps/@48.8566,2.3522,15z', 48.8566, 2.3522, '' ),
	'q with coordinates'             => array( 'https://maps.google.com/?q=48.85754,2.36255', 48.85754, 2.36255, '' ),
	'll with coordinates'            => array( 'https://maps.google.com/maps?ll=48.85754,2.36255&z=17', 48.85754, 2.36255, '' ),
	'api=1 query'                    => array( 'https://www.google.com/maps/search/?api=1&query=48.85754%2C2.36255', 48.85754, 2.36255, '' ),
	'search path with coordinates'   => array( 'https://www.google.com/maps/search/48.85754,+2.36255', 48.85754, 2.36255, '' ),
	'country domain'                 => array( 'https://www.google.fr/maps/@48.8566,2.3522,15z', 48.8566, 2.3522, '' ),
	'negative coordinates'           => array( 'https://www.google.com/maps/@-33.8568,151.2153,15z', -33.8568, 151.2153, '' ),
	'text query'                     => array( 'https://maps.google.com/?q=Mus%C3%A9e+Carnavalet,+23+Rue+de+S%C3%A9vign%C3%A9,+Paris&ftid=0x47e66e0:0x1', null, null, 'Musée Carnavalet, 23 Rue de Sévigné, Paris' ),
	'not Google'                     => array( 'https://www.openstreetmap.org/?mlat=48.85754&mlon=2.36255', null, null, '' ),
	'Google lookalike host'          => array( 'https://google.com.example.org/maps/@48.8566,2.3522,15z', null, null, '' ),
	'Google but not Maps'            => array( 'https://www.google.com/search?q=48.85754,2.36255', null, null, '' ),
	'latitude out of range'          => array( 'https://www.google.com/maps/@95.5,2.3522,15z', null, null, '' ),
	'longitude out of range'         => array( 'https://maps.google.com/?q=48.85,190', null, null, '' ),
	'junk'                           => array( 'not a link', null, null, '' ),
);
foreach ( $tp_cases as $tp_case => list( $tp_url, $tp_lat, $tp_lng, $tp_query ) ) {
	tufte_places_assert_same(
		array( 'lat' => $tp_lat, 'lng' => $tp_lng, 'query' => $tp_query ),
		tufte_places_parse_map_url( $tp_url ),
		"parse: {$tp_case}"
	);
}

// Short links: follow the redirect, but only to Google Maps, and only for short hosts.
$tp_requests = array();
$tp_mock     = static function ( $pre, $args, $url ) use ( &$tp_requests ) {
	$tp_requests[] = $url;
	$locations     = array(
		'https://maps.app.goo.gl/Carnavalet' => 'https://www.google.com/maps/place/Mus%C3%A9e+Carnavalet/@48.857,2.36,17z/data=!4m2!3m1!1s0x1!8m2!3d48.85754!4d2.36255',
		'https://maps.app.goo.gl/TextOnly'   => 'https://maps.google.com/?q=Mus%C3%A9e+Carnavalet,+Paris&ftid=0x1',
		'https://maps.app.goo.gl/Elsewhere'  => 'https://example.org/phish',
	);
	return array(
		'headers'  => isset( $locations[ $url ] ) ? array( 'location' => $locations[ $url ] ) : array(),
		'body'     => '',
		'response' => array( 'code' => isset( $locations[ $url ] ) ? 302 : 404, 'message' => '' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $tp_mock, 10, 3 );

tufte_places_assert_same( 48.85754, tufte_places_parse_map_url( tufte_places_resolve_map_url( 'https://maps.app.goo.gl/Carnavalet' ) )['lat'], 'short link: follows the redirect to the pin' );
tufte_places_assert_same( '', tufte_places_resolve_map_url( 'https://maps.app.goo.gl/Elsewhere' ), 'short link: a redirect away from Google Maps is dropped' );
tufte_places_assert_same( '', tufte_places_resolve_map_url( 'https://maps.app.goo.gl/Missing' ), 'short link: no redirect gives nothing' );
$tp_requests = array();
tufte_places_assert_same( 'https://www.google.com/maps/@48.8566,2.3522,15z', tufte_places_resolve_map_url( 'https://www.google.com/maps/@48.8566,2.3522,15z' ), 'short link: a full link is returned as is' );
tufte_places_assert_same( array(), $tp_requests, 'short link: a full link makes no request' );

// The term form: a new link fills the coordinates.
$tp_id = (int) wp_insert_term( 'Test Map Carnavalet', 'place' )['term_id'];
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://maps.app.goo.gl/Carnavalet', 'tufte_places_address' => '', 'tufte_places_lat' => '', 'tufte_places_lng' => '' ) );
tufte_places_assert_same( 'https://maps.app.goo.gl/Carnavalet', get_term_meta( $tp_id, 'map_url', true ), 'form: keeps the link as pasted' );
tufte_places_assert_same( 48.85754, (float) get_term_meta( $tp_id, 'lat', true ), 'form: a short link fills lat' );
tufte_places_assert_same( 2.36255, (float) get_term_meta( $tp_id, 'lng', true ), 'form: a short link fills lng' );

// Resaving the same link with edited coordinates keeps the edit.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://maps.app.goo.gl/Carnavalet', 'tufte_places_address' => '', 'tufte_places_lat' => '48.8576', 'tufte_places_lng' => '2.3626' ) );
tufte_places_assert_same( 48.8576, (float) get_term_meta( $tp_id, 'lat', true ), 'form: an unchanged link leaves edited coordinates alone' );

// A new link and new coordinates in one save: the coordinates win.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://www.google.com/maps/@48.8566,2.3522,15z', 'tufte_places_address' => '', 'tufte_places_lat' => '48.1', 'tufte_places_lng' => '2.1' ) );
tufte_places_assert_same( 48.1, (float) get_term_meta( $tp_id, 'lat', true ), 'form: typed coordinates win over a new link' );

// A link with only a text query fills an empty address and clears the old coordinates.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://maps.app.goo.gl/TextOnly', 'tufte_places_address' => '', 'tufte_places_lat' => '48.1', 'tufte_places_lng' => '2.1' ) );
tufte_places_assert_same( 'Musée Carnavalet, Paris', get_term_meta( $tp_id, 'address', true ), 'form: a text link fills an empty address' );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'lat', true ), 'form: a link without coordinates clears the old ones' );
tufte_places_assert_same( true, tufte_places_needs_coordinates( tufte_places_get_place( $tp_id ) ), 'form: the place needs coordinates' );

// An address that's already set stays.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://maps.google.com/?q=Somewhere+else', 'tufte_places_address' => '23 Rue de Sévigné', 'tufte_places_lat' => '', 'tufte_places_lng' => '' ) );
tufte_places_assert_same( '23 Rue de Sévigné', get_term_meta( $tp_id, 'address', true ), 'form: a link never overwrites an address' );

// A link that isn't Google Maps is not saved.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://example.org/maps/@48.8566,2.3522,15z', 'tufte_places_address' => '23 Rue de Sévigné', 'tufte_places_lat' => '', 'tufte_places_lng' => '' ) );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'map_url', true ), 'form: a link that is not Google Maps is not saved' );

// Output: the map link goes to the Google listing when there is one.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'exact', 'tufte_places_map_url' => 'https://maps.app.goo.gl/Carnavalet', 'tufte_places_address' => '23 Rue de Sévigné', 'tufte_places_lat' => '', 'tufte_places_lng' => '' ) );
tufte_places_assert_same( 'https://maps.app.goo.gl/Carnavalet', tufte_places_map_url( tufte_places_get_place( $tp_id ) ), 'output: the map link uses the Google link' );

// The Map column.
tufte_places_assert_same( true, array_key_exists( 'tufte_places_map', apply_filters( 'manage_edit-place_columns', array( 'name' => 'Name' ) ) ), 'column: the Map column is added' );
tufte_places_assert_same( true, str_contains( apply_filters( 'manage_place_custom_column', '', 'tufte_places_map', $tp_id ), 'href="https://maps.app.goo.gl/Carnavalet"' ), 'column: a place with coordinates links to its map' );
$tp_bare = (int) wp_insert_term( 'Test Map Bare', 'place' )['term_id'];
tufte_places_assert_same( true, str_contains( apply_filters( 'manage_place_custom_column', '', 'tufte_places_map', $tp_bare ), 'Needs coordinates' ), 'column: an exact place without coordinates needs them' );

// General places drop the link along with the rest.
tufte_places_save_term_fields( $tp_id, array( 'tufte_places_precision' => 'general', 'tufte_places_map_url' => 'https://maps.app.goo.gl/Carnavalet', 'tufte_places_address' => '', 'tufte_places_lat' => '', 'tufte_places_lng' => '' ) );
tufte_places_assert_same( '', get_term_meta( $tp_id, 'map_url', true ), 'general: the link is deleted' );
tufte_places_assert_same( '', tufte_places_map_url( tufte_places_get_place( $tp_id ) ), 'general: no map link' );
tufte_places_assert_same( false, str_contains( apply_filters( 'manage_place_custom_column', '', 'tufte_places_map', $tp_id ), 'Needs coordinates' ), 'column: a general place never needs coordinates' );

// REST: an agent makes a place from a link alone.
$tp_prev_user = get_current_user_id();
$tp_admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $tp_admins[0] );

$tp_req = new WP_REST_Request( 'POST', '/wp/v2/places' );
$tp_req->set_body_params( array( 'name' => 'Test Map REST', 'meta' => array( 'map_url' => 'https://maps.app.goo.gl/Carnavalet' ) ) );
$tp_res    = rest_do_request( $tp_req );
$tp_rest   = (int) ( $tp_res->get_data()['id'] ?? 0 );
$tp_rest_m = $tp_res->get_data()['meta'] ?? array();
tufte_places_assert_same( 48.85754, (float) get_term_meta( $tp_rest, 'lat', true ), 'rest: a link alone fills lat' );
tufte_places_assert_same( 48.85754, (float) ( $tp_rest_m['lat'] ?? 0 ), 'rest: the response has the filled lat' );

$tp_req = new WP_REST_Request( 'POST', '/wp/v2/places/' . $tp_rest );
$tp_req->set_body_params( array( 'meta' => array( 'map_url' => 'https://www.google.com/maps/@48.8566,2.3522,15z', 'lat' => 48.2, 'lng' => 2.2 ) ) );
rest_do_request( $tp_req );
tufte_places_assert_same( 48.2, (float) get_term_meta( $tp_rest, 'lat', true ), 'rest: coordinates sent with a link win' );

$tp_req = new WP_REST_Request( 'POST', '/wp/v2/places/' . $tp_rest );
$tp_req->set_body_params( array( 'meta' => array( 'map_url' => 'https://www.google.com/maps/@48.8566,2.3522,15z' ) ) );
rest_do_request( $tp_req );
tufte_places_assert_same( 48.2, (float) get_term_meta( $tp_rest, 'lat', true ), 'rest: resending the same link keeps the coordinates' );

$tp_req = new WP_REST_Request( 'POST', '/wp/v2/places/' . $tp_rest );
$tp_req->set_body_params( array( 'meta' => array( 'map_url' => 'https://example.org/maps/@1,1,15z' ) ) );
rest_do_request( $tp_req );
tufte_places_assert_same( 'https://www.google.com/maps/@48.8566,2.3522,15z', get_term_meta( $tp_rest, 'map_url', true ), 'rest: a link that is not Google Maps is refused' );

$tp_req = new WP_REST_Request( 'POST', '/wp/v2/places/' . $tp_rest );
$tp_req->set_body_params( array( 'meta' => array( 'precision' => 'general' ) ) );
$tp_res_m = rest_do_request( $tp_req )->get_data()['meta'] ?? array();
tufte_places_assert_same( '', $tp_res_m['map_url'] ?? 'missing', 'rest: a general place returns no link' );
tufte_places_assert_same( '', get_term_meta( $tp_rest, 'map_url', true ), 'rest: switching to general deletes the link' );

wp_set_current_user( $tp_prev_user );
remove_filter( 'pre_http_request', $tp_mock, 10 );
wp_delete_term( $tp_rest, 'place' );
wp_delete_term( $tp_bare, 'place' );
wp_delete_term( $tp_id, 'place' );

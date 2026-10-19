<?php
/**
 * Tests for the place archive.
 *
 * @package Tufte_Places
 */

$tp_mtl  = (int) wp_insert_term( 'Archive Montréal', 'place' )['term_id'];
$tp_hof  = (int) wp_insert_term( 'Archive Hof Kelsten', 'place', array( 'parent' => $tp_mtl ) )['term_id'];
$tp_ames = (int) wp_insert_term( 'Archive Ames', 'place' )['term_id'];
update_term_meta( $tp_hof, 'precision', 'exact' );
update_term_meta( $tp_hof, 'address', '4524 Boulevard Saint-Laurent' );
update_term_meta( $tp_hof, 'lat', '45.5195' );
update_term_meta( $tp_hof, 'lng', '-73.5833' );
update_term_meta( $tp_ames, 'precision', 'general' );

$tp_att = static function ( string $title ) use ( $tp_hof ): int {
	$id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_excerpt' => 'Caption for ' . $title ) );
	update_post_meta( $id, '_wp_attached_file', 'tufte-places-test-' . $id . '.jpg' );
	wp_set_object_terms( $id, array( $tp_hof ), 'place' );
	return (int) $id;
};
$tp_img = static function ( int $id ): string {
	return '<!-- wp:image {"id":' . $id . '} --><figure class="wp-block-image"><img src="x.jpg" alt="" class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
};

$tp_a1 = $tp_att( 'Archive A1' );
$tp_a2 = $tp_att( 'Archive A2' );
$tp_a3 = $tp_att( 'Archive A3' );
$tp_a4 = $tp_att( 'Archive A4' );
$tp_a5 = $tp_att( 'Archive A5' );

$tp_p1 = wp_insert_post( array( 'post_title' => 'Archive P1', 'post_status' => 'publish', 'post_content' => $tp_img( $tp_a1 ) ) );
$tp_p2 = wp_insert_post( array( 'post_title' => 'Archive P2', 'post_status' => 'draft', 'post_content' => $tp_img( $tp_a2 ) ) );
$tp_p3 = wp_insert_post( array( 'post_title' => 'Archive P3', 'post_status' => 'private', 'post_content' => $tp_img( $tp_a4 ) ) );

tufte_places_assert_same( array( $tp_a1 ), tufte_places_archive_photo_ids( $tp_hof ), 'archive: only photos used in published, public posts' );
tufte_places_assert_same( array( $tp_a1 ), tufte_places_archive_photo_ids( $tp_mtl ), 'archive: a parent includes its child places' );
$tp_q = new WP_Query( array( 'tax_query' => array( array( 'taxonomy' => 'place', 'terms' => $tp_mtl ) ), 'fields' => 'ids' ) );
tufte_places_assert_same( array( $tp_p1 ), $tp_q->posts, 'archive: the parent archive lists posts tagged with a child place' );
$tp_p4 = wp_insert_post( array( 'post_title' => 'Archive P4', 'post_status' => 'publish', 'post_password' => 'secret', 'post_content' => $tp_img( $tp_a5 ) ) );
tufte_places_assert_same( array( $tp_a1 ), tufte_places_archive_photo_ids( $tp_hof ), 'archive: a password-protected post does not contribute photos' );
tufte_places_assert_same( true, null !== get_block_template( 'tufte-places//taxonomy-place' ), 'archive: block template registered' );

$tp_head_general = tufte_places_render_place_header( $tp_ames );
tufte_places_assert_same( false, str_contains( $tp_head_general, 'openstreetmap' ), 'archive: general place header has no map link' );
tufte_places_assert_same( true, str_contains( $tp_head_general, '<h1 class="tufte-places-header-name">Archive Ames</h1>' ), 'archive: header has the name as h1' );
$tp_head_exact = tufte_places_render_place_header( $tp_hof );
tufte_places_assert_same( true, str_contains( $tp_head_exact, 'openstreetmap' ) && str_contains( $tp_head_exact, 'Boulevard Saint-Laurent' ), 'archive: exact place header has address and map link' );
tufte_places_assert_same( true, str_contains( $tp_head_exact, 'Archive Montréal' ), 'archive: header shows the locality' );

$tp_grid = tufte_places_render_photos( $tp_hof );
tufte_places_assert_same( true, str_contains( $tp_grid, 'tufte-places-photo' ) && str_contains( $tp_grid, 'Caption for Archive A1' ), 'archive: photo grid shows the library caption' );
tufte_places_assert_same( '', tufte_places_render_photos( $tp_ames ), 'archive: no photos renders nothing' );

$tp_lim  = (int) wp_insert_term( 'Archive Limit', 'place' )['term_id'];
$tp_ls   = $tp_att( 'Archive LS' );
$tp_lx1  = $tp_att( 'Archive LX1' );
$tp_lx2  = $tp_att( 'Archive LX2' );
foreach ( array( $tp_ls, $tp_lx1, $tp_lx2 ) as $tp_id ) {
	wp_set_object_terms( $tp_id, array( $tp_lim ), 'place' );
}
$tp_old = wp_insert_post( array( 'post_title' => 'Archive Old', 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ), 'post_content' => $tp_img( $tp_ls ) . $tp_img( $tp_lx1 ) ) );
$tp_new = wp_insert_post( array( 'post_title' => 'Archive New', 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'post_content' => $tp_img( $tp_ls ) . $tp_img( $tp_lx2 ) ) );
tufte_places_assert_same( array( $tp_ls, $tp_lx2 ), tufte_places_archive_photo_ids( $tp_lim, 2 ), 'archive: limit stops collection, newest post first, content order' );
tufte_places_assert_same( array( $tp_ls, $tp_lx2, $tp_lx1 ), tufte_places_archive_photo_ids( $tp_lim ), 'archive: shared photo deduplicated, older post last' );
wp_delete_post( $tp_old, true );
wp_delete_post( $tp_new, true );
foreach ( array( $tp_ls, $tp_lx1, $tp_lx2 ) as $tp_id ) {
	wp_delete_attachment( $tp_id, true );
}
wp_delete_term( $tp_lim, 'place' );

wp_delete_post( $tp_p1, true );
wp_delete_post( $tp_p2, true );
wp_delete_post( $tp_p3, true );
wp_delete_post( $tp_p4, true );
foreach ( array( $tp_a1, $tp_a2, $tp_a3, $tp_a4, $tp_a5 ) as $tp_id ) {
	wp_delete_attachment( $tp_id, true );
}
foreach ( array( $tp_hof, $tp_mtl, $tp_ames ) as $tp_term ) {
	wp_delete_term( $tp_term, 'place' );
}

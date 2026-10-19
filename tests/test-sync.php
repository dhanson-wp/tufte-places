<?php
/**
 * Tests for syncing a post's places from its photos.
 *
 * @package Tufte_Places
 */

$tp_hof    = (int) wp_insert_term( 'Sync Hof Kelsten', 'place' )['term_id'];
$tp_cowork = (int) wp_insert_term( 'Sync Cowork', 'place' )['term_id'];
$tp_trip   = (int) wp_insert_term( 'Sync Test Trip', 'place' )['term_id'];
$tp_a1     = wp_insert_attachment( array( 'post_title' => 'Sync A1', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$tp_a2     = wp_insert_attachment( array( 'post_title' => 'Sync A2', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
wp_set_object_terms( $tp_a1, array( $tp_hof ), 'place' );

$tp_content = str_replace( array( '6162', '6191' ), array( (string) $tp_a1, (string) $tp_a2 ), tufte_places_fixture( 'gallery.html' ) );
$tp_post    = wp_insert_post( array( 'post_title' => 'Sync test', 'post_status' => 'publish', 'post_content' => $tp_content ) );
wp_set_object_terms( $tp_post, array( $tp_trip ), 'place' );

$tp_sorted = static function ( array $ids ): array {
	sort( $ids );
	return $ids;
};

tufte_places_sync_post( $tp_post );
tufte_places_assert_same( $tp_sorted( array( $tp_hof, $tp_trip ) ), tufte_places_sorted_terms( $tp_post ), 'sync: derived place added, hand-added place kept' );
wp_set_object_terms( $tp_a2, array( $tp_cowork ), 'place' ); // Fires the attachment hook.
tufte_places_assert_same( $tp_sorted( array( $tp_hof, $tp_cowork, $tp_trip ) ), tufte_places_sorted_terms( $tp_post ), 'sync: tagging a photo resyncs posts that use it' );
wp_set_object_terms( $tp_a1, array(), 'place' );
tufte_places_assert_same( $tp_sorted( array( $tp_cowork, $tp_trip ) ), tufte_places_sorted_terms( $tp_post ), 'sync: a place no photo has anymore is removed' );
tufte_places_assert_same( array( $tp_post ), tufte_places_posts_using_attachment( $tp_a2 ), 'sync: finds posts by block ID' );
tufte_places_assert_same( array(), tufte_places_posts_using_attachment( $tp_a2 * 10 ), 'sync: ID prefixes do not match (12 is not 120)' );
tufte_places_assert_same( array( 6162, 6191 ), tufte_places_content_attachment_ids( tufte_places_fixture( 'gallery.html' ) ), 'sync: content IDs' );

$tp_nested = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:image {"id":' . $tp_a1 . '} --><figure class="wp-block-image"><img src="x.jpg" alt="" class="wp-image-' . $tp_a1 . '"/></figure><!-- /wp:image --></div><!-- /wp:column --></div><!-- /wp:columns -->';
tufte_places_assert_same( array( $tp_a1 ), tufte_places_content_attachment_ids( $tp_nested . $tp_nested ), 'sync: nested image IDs found and deduplicated' );

wp_delete_post( $tp_post, true );
wp_delete_attachment( $tp_a1, true );
wp_delete_attachment( $tp_a2, true );
foreach ( array( $tp_hof, $tp_cowork, $tp_trip ) as $tp_term ) {
	wp_delete_term( $tp_term, 'place' );
}

<?php
/**
 * Tests for captions, the wrapper, and the place line on image and gallery blocks.
 *
 * @package Tufte_Places
 */

$tp_r_place = (int) wp_insert_term( 'Render test place', 'place' )['term_id'];

$tp_r_blue  = wp_insert_attachment( array( 'post_title' => 'Render blue trim', 'post_excerpt' => 'Blue trim on a "Plateau" row house.', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$tp_r_bread = wp_insert_attachment( array( 'post_title' => 'Render bread wall', 'post_excerpt' => 'The bread wall', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$tp_r_plain = wp_insert_attachment( array( 'post_title' => 'Render untagged', 'post_excerpt' => '<em>Quiet</em> street<script>alert(1)</script>', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
wp_set_object_terms( $tp_r_blue, array( $tp_r_place ), 'place' );
wp_set_object_terms( $tp_r_bread, array( $tp_r_place ), 'place' );
update_post_meta( $tp_r_bread, 'tufte_places_hide_from_map', true );

$tp_fixture        = tufte_places_fixture( 'gallery.html' );
$tp_gallery_markup = str_replace( array( '6162', '6191' ), array( (string) $tp_r_blue, (string) $tp_r_bread ), $tp_fixture );
$tp_bread_figure   = 'class="wp-image-' . $tp_r_bread . '"/></figure>';

$tp_gallery_with_override     = str_replace( $tp_bread_figure, 'class="wp-image-' . $tp_r_bread . '"/><figcaption class="wp-element-caption">My own caption</figcaption></figure>', $tp_gallery_markup );
$tp_gallery_with_same_caption = str_replace( $tp_bread_figure, 'class="wp-image-' . $tp_r_bread . '"/><figcaption class="wp-element-caption">The bread wall</figcaption></figure>', $tp_gallery_markup );
$tp_gallery_markup_hidden     = str_replace( '{"linkTo":"none"}', '{"linkTo":"none","tufteHideFromMap":true}', str_replace( '6191', (string) $tp_r_bread, str_replace( '6162', (string) $tp_r_bread, $tp_fixture ) ) );
$tp_wide_gallery              = str_replace(
	array( '{"linkTo":"none"}', 'is-cropped"' ),
	array( '{"linkTo":"none","align":"wide"}', 'is-cropped alignwide"' ),
	$tp_gallery_markup
);
$tp_untagged_gallery          = str_replace( array( '6162', '6191' ), array( (string) $tp_r_plain, (string) $tp_r_plain ), $tp_fixture );
$tp_single_image              = '<!-- wp:image {"id":' . $tp_r_blue . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://example.org/blue.jpg" alt="" class="wp-image-' . $tp_r_blue . '"/></figure><!-- /wp:image -->';
$tp_external_image_markup     = '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://example.org/x.jpg" alt=""/></figure><!-- /wp:image -->';
$tp_missing_image_markup      = '<!-- wp:image {"id":999999999,"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://example.org/gone.jpg" alt="" class="wp-image-999999999"/></figure><!-- /wp:image -->';

// Render the no-ID and missing-ID images without the plugin's filters to get core's own output.
remove_filter( 'render_block_data', 'tufte_places_mark_gallery_images', 10 );
remove_filter( 'render_block_core/image', 'tufte_places_render_image', 10 );
remove_filter( 'render_block_core/gallery', 'tufte_places_render_gallery', 10 );
$tp_external_image_markup_rendered = do_blocks( $tp_external_image_markup );
$tp_missing_image_markup_rendered  = do_blocks( $tp_missing_image_markup );
add_filter( 'render_block_data', 'tufte_places_mark_gallery_images', 10, 3 );
add_filter( 'render_block_core/image', 'tufte_places_render_image', 10, 2 );
add_filter( 'render_block_core/gallery', 'tufte_places_render_gallery', 10, 2 );

$tp_out = do_blocks( $tp_gallery_markup ); // Gallery of both, no block captions.
tufte_places_assert_same( 1, substr_count( $tp_out, 'class="tufte-places-group' ), 'render: gallery wrapped once' );
tufte_places_assert_same( 1, substr_count( $tp_out, 'class="tufte-places-line"' ), 'render: one line per gallery, none per inner image' );
tufte_places_assert_same( true, str_contains( $tp_out, 'Blue trim on a &quot;Plateau&quot; row house.' ) || str_contains( $tp_out, 'Blue trim on a "Plateau" row house.' ), 'render: library caption filled in safely' );
tufte_places_assert_same( 2, substr_count( $tp_out, '<figcaption class="wp-element-caption">' ), 'render: every gallery image gets its library caption' );
tufte_places_assert_same( true, str_contains( $tp_out, '>The bread wall</figcaption>' ), 'render: the second library caption is filled in' );
tufte_places_assert_same( true, strpos( $tp_out, 'tufte-places-line' ) > strrpos( $tp_out, '</figure>' ), 'render: the line follows the gallery' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_gallery_with_override ), '>My own caption<' ), 'render: a block caption overrides the library' );
tufte_places_assert_same( false, str_contains( do_blocks( $tp_gallery_with_override ), '>The bread wall<' ), 'render: an overridden library caption is not shown' );
tufte_places_assert_same( false, str_contains( do_blocks( $tp_gallery_with_same_caption ), '>The bread wall</figcaption><figcaption' ), 'render: a block caption equal to the library is not duplicated' );
tufte_places_assert_same( 1, substr_count( do_blocks( $tp_gallery_with_same_caption ), '>The bread wall<' ), 'render: a block caption equal to the library shows once' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_gallery_markup_hidden ), 'tufte-places-line' ), 'render: hidden-from-map photos still show their place' );
tufte_places_assert_same( $tp_external_image_markup_rendered, do_blocks( $tp_external_image_markup ), 'render: an image without an ID is untouched' );
tufte_places_assert_same( $tp_missing_image_markup_rendered, do_blocks( $tp_missing_image_markup ), 'render: an image whose attachment is gone is untouched' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_wide_gallery ), 'tufte-places-group alignwide' ), 'render: wrapper carries alignwide' );
tufte_places_assert_same( true, str_contains( do_blocks( $tp_wide_gallery ), 'is-cropped alignwide' ), 'render: the gallery keeps its own classes' );
$tp_single_out = do_blocks( $tp_single_image );
tufte_places_assert_same( true, str_contains( $tp_single_out, 'tufte-places-line' ), 'render: a standalone image gets its own line' );
tufte_places_assert_same( 1, substr_count( $tp_single_out, 'class="tufte-places-group"' ), 'render: a standalone image gets its own wrapper' );
tufte_places_assert_same( 1, substr_count( $tp_single_out, '<figcaption class="wp-element-caption">' ), 'render: a standalone image gets its library caption' );
$tp_untagged_out = do_blocks( $tp_untagged_gallery );
tufte_places_assert_same( false, str_contains( $tp_untagged_out, 'tufte-places' ), 'render: no places, no wrapper' );
tufte_places_assert_same( true, str_contains( $tp_untagged_out, '<em>Quiet</em> street' ), 'render: library caption markup is kept' );
tufte_places_assert_same( false, str_contains( $tp_untagged_out, '<script' ), 'render: library caption scripts are stripped' );
tufte_places_assert_same( true, wp_style_is( 'tufte-places-line', 'enqueued' ), 'render: the line stylesheet is enqueued once a line renders' );

$tp_image_type   = WP_Block_Type_Registry::get_instance()->get_registered( 'core/image' );
$tp_gallery_type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/gallery' );
tufte_places_assert_same( array( 'type' => 'boolean', 'default' => false ), $tp_image_type->attributes['tufteHideFromMap'] ?? null, 'render: image blocks accept tufteHideFromMap' );
tufte_places_assert_same( array( 'type' => 'boolean', 'default' => false ), $tp_gallery_type->attributes['tufteHideFromMap'] ?? null, 'render: gallery blocks accept tufteHideFromMap' );

foreach ( array( $tp_r_blue, $tp_r_bread, $tp_r_plain ) as $tp_r_att ) {
	wp_delete_attachment( $tp_r_att, true );
}
wp_delete_term( $tp_r_place, 'place' );

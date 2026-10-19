<?php
/**
 * Captions, the wrapper, and the place line on image and gallery blocks.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the tufteHideFromMap attribute to image and gallery blocks, so the
 * attribute the editor saves is known on the server.
 *
 * @param array  $args       Block type arguments.
 * @param string $block_type Block name.
 * @return array
 */
function tufte_places_block_type_args( array $args, string $block_type ): array {
	if ( 'core/image' === $block_type || 'core/gallery' === $block_type ) {
		$args['attributes']                     = $args['attributes'] ?? array();
		$args['attributes']['tufteHideFromMap'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
	}
	return $args;
}
add_filter( 'register_block_type_args', 'tufte_places_block_type_args', 10, 2 );

/**
 * Mark image blocks inside a gallery, so they get a caption but no line of their own.
 *
 * @param array         $parsed_block The block being rendered.
 * @param array         $source_block The original block.
 * @param WP_Block|null $parent_block The parent block, if any.
 * @return array
 */
function tufte_places_mark_gallery_images( array $parsed_block, array $source_block, ?WP_Block $parent_block ): array {
	if ( 'core/image' === ( $parsed_block['blockName'] ?? '' ) && null !== $parent_block && 'core/gallery' === $parent_block->name ) {
		$parsed_block['attrs']                         = (array) ( $parsed_block['attrs'] ?? array() );
		$parsed_block['attrs']['tuftePlacesInGallery'] = true;
	}
	return $parsed_block;
}
add_filter( 'render_block_data', 'tufte_places_mark_gallery_images', 10, 3 );

/**
 * Caption text for comparing a block caption with the library caption: tags
 * stripped, entities decoded, non-breaking spaces made plain, and trimmed.
 *
 * @param string $caption Caption markup.
 * @return string
 */
function tufte_places_normalize_caption( string $caption ): string {
	return trim( str_replace( "\u{00A0}", ' ', html_entity_decode( wp_strip_all_tags( $caption ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/**
 * The caption to show for a photo.
 *
 * The block's own caption wins, unless it's empty or the same as the library
 * caption; then the current library caption shows, so it stays live.
 *
 * @param string $block_caption Caption markup saved on the block.
 * @param int    $attachment_id Attachment ID.
 * @return string Caption markup, or empty for none.
 */
function tufte_places_effective_caption( string $block_caption, int $attachment_id ): string {
	$library_caption = (string) wp_get_attachment_caption( $attachment_id );
	$block_text      = tufte_places_normalize_caption( $block_caption );

	if ( '' === $block_text || tufte_places_normalize_caption( $library_caption ) === $block_text ) {
		return wp_kses_post( $library_caption );
	}

	return wp_kses_post( $block_caption );
}

/**
 * Put a caption into a figure, replacing its figcaption or adding one before
 * the closing </figure>.
 *
 * @param string $html    Figure markup.
 * @param string $caption Caption markup, already safe.
 * @return string
 */
function tufte_places_set_figcaption( string $html, string $caption ): string {
	$processor = new WP_HTML_Tag_Processor( $html );
	if ( $processor->next_tag( 'figcaption' ) && preg_match( '#(<figcaption\b[^>]*>)(.*?)</figcaption>#is', $html, $match, PREG_OFFSET_CAPTURE ) ) {
		$inner_start = $match[2][1];
		return substr_replace( $html, $caption, $inner_start, strlen( $match[2][0] ) );
	}

	$close = strripos( $html, '</figure>' );
	if ( false === $close ) {
		return $html;
	}

	return substr_replace( $html, '<figcaption class="wp-element-caption">' . $caption . '</figcaption>', $close, 0 );
}

/**
 * The caption markup saved inside a figure's figcaption.
 *
 * @param string $html Figure markup.
 * @return string Empty when there is no figcaption.
 */
function tufte_places_block_caption( string $html ): string {
	$processor = new WP_HTML_Tag_Processor( $html );
	if ( ! $processor->next_tag( 'figcaption' ) ) {
		return '';
	}
	return preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', $html, $match ) ? $match[1] : '';
}

/**
 * Wrap a block with its place line.
 *
 * @param string $block_html Rendered block.
 * @param string $line_html  Place line markup.
 * @param array  $attrs      Block attributes. Its alignment is copied to the
 *                           wrapper, since core aligns direct children only.
 * @return string
 */
function tufte_places_wrap( string $block_html, string $line_html, array $attrs ): string {
	$class = 'tufte-places-group';
	$align = $attrs['align'] ?? '';
	if ( in_array( $align, array( 'wide', 'full', 'left', 'right', 'center' ), true ) ) {
		$class .= ' align' . $align;
	}

	wp_enqueue_style( 'tufte-places-line' );

	return '<div class="' . esc_attr( $class ) . '">' . $block_html . $line_html . '</div>';
}

/**
 * Wrap a block with its place line when its photos have places.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 * @return string
 */
function tufte_places_maybe_add_line( string $html, array $block ): string {
	$line = tufte_places_line_html( tufte_places_places_for_attachments( tufte_places_block_attachment_ids( $block ) ) );
	if ( '' === $line ) {
		return $html;
	}
	return tufte_places_wrap( $html, $line, (array) ( $block['attrs'] ?? array() ) );
}

/**
 * Fill in the caption on an image block, and add the place line when it
 * stands alone.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 * @return string
 */
function tufte_places_render_image( string $html, array $block ): string {
	$attachment_id = (int) ( $block['attrs']['id'] ?? 0 );
	if ( $attachment_id <= 0 || '' === $html ) {
		return $html;
	}

	$block_caption = tufte_places_block_caption( $html );
	$block_text    = tufte_places_normalize_caption( $block_caption );
	$library_text  = tufte_places_normalize_caption( (string) wp_get_attachment_caption( $attachment_id ) );

	// A block caption that differs from the library caption is the author's own; leave it exactly as saved.
	if ( '' === $block_text || $block_text === $library_text ) {
		$caption = tufte_places_effective_caption( $block_caption, $attachment_id );
		if ( '' !== $caption ) {
			$html = tufte_places_set_figcaption( $html, $caption );
		}
	}

	if ( ! empty( $block['attrs']['tuftePlacesInGallery'] ) ) {
		return $html;
	}

	return tufte_places_maybe_add_line( $html, $block );
}
add_filter( 'render_block_core/image', 'tufte_places_render_image', 10, 2 );

/**
 * Add the place line under a gallery whose photos have places.
 *
 * @param string $html  Rendered block.
 * @param array  $block Parsed block.
 * @return string
 */
function tufte_places_render_gallery( string $html, array $block ): string {
	if ( '' === $html ) {
		return $html;
	}
	return tufte_places_maybe_add_line( $html, $block );
}
add_filter( 'render_block_core/gallery', 'tufte_places_render_gallery', 10, 2 );

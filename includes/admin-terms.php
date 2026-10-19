<?php
/**
 * Place fields on the term screens.
 *
 * Adds precision, address, and coordinate fields to the add and edit forms for
 * the place taxonomy, and saves them.
 *
 * @package Tufte_Places
 * @since 0.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize a coordinate within a range.
 *
 * @param mixed $value Raw value.
 * @param float $limit Largest allowed absolute value.
 * @return float|null Null when not numeric or out of range.
 */
function tufte_places_bounded_coordinate( $value, float $limit ): ?float {
	$value = is_string( $value ) ? trim( $value ) : $value;
	if ( ! is_numeric( $value ) ) {
		return null;
	}
	$number = (float) $value;
	return ( $number >= -$limit && $number <= $limit ) ? $number : null;
}

/**
 * Save the place fields for a term.
 *
 * Does no nonce or capability check; the hook callbacks do that.
 *
 * @param int   $term_id Place term ID.
 * @param array $input   Unslashed request data.
 */
function tufte_places_save_term_fields( int $term_id, array $input ): void {
	update_term_meta( $term_id, 'precision', tufte_places_sanitize_precision( $input['tufte_places_precision'] ?? 'exact' ) );

	$address = isset( $input['tufte_places_address'] ) && is_string( $input['tufte_places_address'] )
		? sanitize_text_field( $input['tufte_places_address'] )
		: '';
	if ( '' === $address ) {
		delete_term_meta( $term_id, 'address' );
	} else {
		update_term_meta( $term_id, 'address', $address );
	}

	$limits = array(
		'lat' => 90.0,
		'lng' => 180.0,
	);
	foreach ( $limits as $key => $limit ) {
		$number = tufte_places_bounded_coordinate( $input[ 'tufte_places_' . $key ] ?? '', $limit );
		if ( null === $number ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $number );
		}
	}

	tufte_places_enforce_general( $term_id );
}

/**
 * Save the place fields from the term form after checking nonce and capability.
 *
 * @param int $term_id Place term ID.
 */
function tufte_places_handle_term_form( int $term_id ): void {
	$nonce = isset( $_POST['tufte_places_term_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tufte_places_term_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'tufte_places_term' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	tufte_places_save_term_fields( $term_id, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above; each field is sanitized in the save function.
}
add_action( 'created_place', 'tufte_places_handle_term_form' );
add_action( 'edited_place', 'tufte_places_handle_term_form' );

/**
 * Print the field inputs shared by the add and edit forms.
 *
 * @param string $precision Current precision.
 * @param string $address   Current address.
 * @param string $lat       Current latitude.
 * @param string $lng       Current longitude.
 * @param bool   $as_rows   True for table rows (edit screen), false for divs (add screen).
 */
function tufte_places_print_term_fields( string $precision, string $address, string $lat, string $lng, bool $as_rows ): void {
	$open = static function ( string $label, string $for, string $class ) use ( $as_rows ): void {
		if ( $as_rows ) {
			printf( '<tr class="form-field %1$s"><th scope="row"><label for="%2$s">%3$s</label></th><td>', esc_attr( $class ), esc_attr( $for ), esc_html( $label ) );
		} else {
			printf( '<div class="form-field %1$s"><label for="%2$s">%3$s</label>', esc_attr( $class ), esc_attr( $for ), esc_html( $label ) );
		}
	};
	$close = static function ( string $description ) use ( $as_rows ): void {
		if ( '' !== $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
		echo $as_rows ? '</td></tr>' : '</div>';
	};

	wp_nonce_field( 'tufte_places_term', 'tufte_places_term_nonce' );

	if ( $as_rows ) {
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Precision', 'tufte-places' ) . '</th><td>';
	} else {
		echo '<div class="form-field"><span class="tufte-places-label">' . esc_html__( 'Precision', 'tufte-places' ) . '</span>';
	}
	?>
	<fieldset>
		<legend class="screen-reader-text"><?php esc_html_e( 'Precision', 'tufte-places' ); ?></legend>
		<label><input type="radio" name="tufte_places_precision" value="exact" <?php checked( 'exact', $precision ); ?> /> <?php esc_html_e( 'Exact', 'tufte-places' ); ?></label><br />
		<label><input type="radio" name="tufte_places_precision" value="general" <?php checked( 'general', $precision ); ?> /> <?php esc_html_e( 'General', 'tufte-places' ); ?></label>
	</fieldset>
	<?php
	$close( __( 'A general place shows only its name. It never stores or shows an address or coordinates.', 'tufte-places' ) );

	$open( __( 'Address', 'tufte-places' ), 'tufte_places_address', 'tufte-places-exact-field' );
	printf( '<input type="text" name="tufte_places_address" id="tufte_places_address" value="%s" />', esc_attr( $address ) );
	$close( '' );

	$open( __( 'Latitude', 'tufte-places' ), 'tufte_places_lat', 'tufte-places-exact-field' );
	printf( '<input type="text" inputmode="decimal" name="tufte_places_lat" id="tufte_places_lat" value="%s" />', esc_attr( $lat ) );
	$close( __( 'A number from -90 to 90.', 'tufte-places' ) );

	$open( __( 'Longitude', 'tufte-places' ), 'tufte_places_lng', 'tufte-places-exact-field' );
	printf( '<input type="text" inputmode="decimal" name="tufte_places_lng" id="tufte_places_lng" value="%s" />', esc_attr( $lng ) );
	$close( __( 'A number from -180 to 180.', 'tufte-places' ) );
}

/**
 * Fields on the add-term form.
 */
function tufte_places_add_form_fields(): void {
	tufte_places_print_term_fields( 'exact', '', '', '', false );
}
add_action( 'place_add_form_fields', 'tufte_places_add_form_fields' );

/**
 * Fields on the edit-term form.
 *
 * @param WP_Term $term Place being edited.
 */
function tufte_places_edit_form_fields( $term ): void {
	$term_id = (int) $term->term_id;
	tufte_places_print_term_fields(
		tufte_places_sanitize_precision( get_term_meta( $term_id, 'precision', true ) ),
		(string) get_term_meta( $term_id, 'address', true ),
		(string) get_term_meta( $term_id, 'lat', true ),
		(string) get_term_meta( $term_id, 'lng', true ),
		true
	);
}
add_action( 'place_edit_form_fields', 'tufte_places_edit_form_fields' );

/**
 * Hide the address and coordinate fields while General is selected.
 */
function tufte_places_term_screen_script(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'place' !== $screen->taxonomy ) {
		return;
	}
	?>
	<script>
	( function () {
		function sync() {
			var checked = document.querySelector( 'input[name="tufte_places_precision"]:checked' );
			var hide = checked && 'general' === checked.value;
			document.querySelectorAll( '.tufte-places-exact-field' ).forEach( function ( el ) {
				el.hidden = hide;
			} );
		}
		document.addEventListener( 'change', function ( event ) {
			if ( event.target.name === 'tufte_places_precision' ) {
				sync();
			}
		} );
		document.addEventListener( 'reset', function () {
			setTimeout( sync, 0 );
		} );
		sync();
	}() );
	</script>
	<?php
}
add_action( 'admin_print_footer_scripts', 'tufte_places_term_screen_script' );

<?php
/**
 * Tests for the rewrite rules flush.
 *
 * @package Tufte_Places
 */

delete_option( 'tufte_places_rewrite_version' );
tufte_places_maybe_flush_rewrites();
tufte_places_assert_same( TUFTE_PLACES_VERSION, get_option( 'tufte_places_rewrite_version' ), 'rewrite: the check records the version' );

$tp_rules = array_keys( (array) get_option( 'rewrite_rules' ) );
tufte_places_assert_same( true, (bool) array_filter( $tp_rules, static fn( $rule ): bool => str_starts_with( (string) $rule, 'place/' ) ), 'rewrite: the place rule exists after the flush' );

tufte_places_assert_same( 20, has_action( 'init', 'tufte_places_maybe_flush_rewrites' ), 'rewrite: the check runs on init after the taxonomy registers' );

<?php
/**
 * Minimal test runner. Run with:
 *   wp --path=<site> eval-file wp-content/plugins/tufte-places/tests/run.php
 * Prints one PASS/FAIL line per case and exits non-zero on any failure.
 *
 * Note: WP-CLI eval()s this file inside a method, so it cannot declare
 * strict_types and its counters must live in $GLOBALS.
 *
 * @package Tufte_Places
 */

$GLOBALS['tufte_places_tests_failed'] = 0;
$GLOBALS['tufte_places_tests_passed'] = 0;

/**
 * Assert two values are identical.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $name     Case name.
 */
function tufte_places_assert_same( $expected, $actual, string $name ): void {
	global $tufte_places_tests_failed, $tufte_places_tests_passed;
	if ( $expected === $actual ) {
		++$tufte_places_tests_passed;
		echo "PASS  {$name}\n";
		return;
	}
	++$tufte_places_tests_failed;
	echo "FAIL  {$name}\n";
	echo '      expected: ' . var_export( $expected, true ) . "\n";
	echo '      actual:   ' . var_export( $actual, true ) . "\n";
}

/**
 * Read a fixture file from tests/fixtures.
 *
 * @param string $name File name.
 * @return string
 */
function tufte_places_fixture( string $name ): string {
	return (string) file_get_contents( __DIR__ . '/fixtures/' . $name );
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $tufte_places_test_file ) {
	echo "\n== " . basename( $tufte_places_test_file ) . "\n";
	require $tufte_places_test_file;
}

echo "\n{$GLOBALS['tufte_places_tests_passed']} passed, {$GLOBALS['tufte_places_tests_failed']} failed\n";
if ( $GLOBALS['tufte_places_tests_failed'] > 0 ) {
	exit( 1 );
}

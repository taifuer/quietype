<?php
/**
 * Assert server-side integration behavior in the disposable WordPress site.
 *
 * @package Quietype
 */

define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']       = 'localhost:' . ( getenv( 'QUIETYPE_TEST_PORT' ) ?: '8888' );
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require '/var/www/html/wp-load.php';

/** Stop the compatibility job with a readable reason. */
function quietype_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Quietype integration check failed: {$message}\n" );
		exit( 1 );
	}
}

$archive_url = quietype_archive_url( false );
quietype_test_assert( (bool) preg_match( '~/archive/?$~', $archive_url ), 'the template-assigned article archive should resolve to /archive/' );
quietype_test_assert( '' === quietype_page_url( 'missing-template.php', array( 'missing-page' ) ), 'missing optional pages should not produce invented links' );

ob_start();
quietype_menu_fallback();
$fallback_menu = (string) ob_get_clean();
quietype_test_assert( false === strpos( $fallback_menu, 'articlearchive' ), 'fallback navigation must not hard-code the legacy archive slug' );
quietype_test_assert( false === strpos( $fallback_menu, '/missing-' ), 'fallback navigation must omit missing optional pages' );

printf( "Quietype WordPress integration checks passed.\n" );

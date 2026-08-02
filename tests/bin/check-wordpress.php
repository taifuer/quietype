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

$writing_links = get_bookmarks(
	array(
		'category_name' => '独立写作',
		'orderby'       => 'rating',
		'order'         => 'DESC',
	)
);
quietype_test_assert( 3 === count( $writing_links ), 'the deterministic writing category should contain three links' );
$saved_link_states = quietype_link_states();
quietype_update_link_state( $writing_links[0]->link_id, array( 'status' => 'offline' ) );
$writing_group = array_values(
	array_filter(
		quietype_link_groups(),
		function ( $group ) {
			return '独立写作' === $group['term']->name;
		}
	)
);
quietype_test_assert( 1 === count( $writing_group ), 'the writing link category should remain available after sorting' );
quietype_test_assert(
	array( '留白之间', '开放笔记', '山茶书房' ) === wp_list_pluck( $writing_group[0]['links'], 'link_name' ),
	'confirmed offline links should follow reachable links even when their rating is higher'
);
update_option( 'quietype_link_states', $saved_link_states, false );

printf( "Quietype WordPress integration checks passed.\n" );

<?php
/**
 * Optional WordPress behavior refinements.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hide the front-end toolbar without changing the dashboard experience. */
function quietype_maybe_hide_admin_bar( $show ) {
	return quietype_get_setting( 'quietype_hide_admin_bar', false ) ? false : $show;
}
add_filter( 'show_admin_bar', 'quietype_maybe_hide_admin_bar' );

/** Stop keeping new post/page revisions while preserving autosaves. */
function quietype_revisions_to_keep( $number, $post ) {
	if ( quietype_get_setting( 'quietype_disable_revisions', false ) && $post instanceof WP_Post && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return 0;
	}
	return $number;
}
add_filter( 'wp_revisions_to_keep', 'quietype_revisions_to_keep', 10, 2 );

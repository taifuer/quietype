<?php
/**
 * Cache-compatible post view counting.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Build a stable request token that remains valid in full-page caches. */
function quietype_view_token( $post_id ) {
	return hash_hmac( 'sha256', (string) absint( $post_id ), wp_salt( 'auth' ) );
}

/** Treat automated clients as non-human traffic. */
function quietype_is_view_bot() {
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $user_agent ) {
		return true;
	}
	return (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|headless|lighthouse|pagespeed|monitor|uptime|curl|wget|python|scrapy|facebookexternalhit|telegrambot|whatsapp/i', $user_agent );
}

/** Build a privacy-preserving, short-lived visitor fingerprint. */
function quietype_view_fingerprint( $post_id ) {
	$address    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return 'quietype_view_' . hash_hmac( 'sha256', $post_id . '|' . $address . '|' . $user_agent, wp_salt( 'nonce' ) );
}

/** Increment a post-meta counter atomically enough for concurrent public traffic. */
function quietype_increment_post_views( $post_id ) {
	global $wpdb;

	$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta}
			SET meta_value = CAST(meta_value AS UNSIGNED) + 1
			WHERE post_id = %d AND meta_key = 'views'
			LIMIT 1",
			$post_id
		)
	);
	if ( 0 === $updated ) {
		add_post_meta( $post_id, 'views', 1, true );
	}
	wp_cache_delete( $post_id, 'post_meta' );
	return max( 0, (int) get_post_meta( $post_id, 'views', true ) );
}

/** Return the latest count and optionally record one cache-independent view. */
function quietype_record_post_view() {
	nocache_headers();
	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	if ( ! hash_equals( quietype_view_token( $post_id ), $token ) ) {
		wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
	}

	$post    = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		wp_send_json_error( array( 'message' => 'Post not found.' ), 404 );
	}

	$counted   = false;
	$increment = ! empty( $_POST['increment'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['increment'] ) );
	if ( $increment && ! quietype_is_view_bot() ) {
		$fingerprint = quietype_view_fingerprint( $post_id );
		if ( false === get_transient( $fingerprint ) ) {
			$counted = true;
			$count   = quietype_increment_post_views( $post_id );
			set_transient( $fingerprint, 1, 6 * HOUR_IN_SECONDS );
		}
	}
	if ( ! isset( $count ) ) {
		$count = max( 0, (int) get_post_meta( $post_id, 'views', true ) );
	}

	wp_send_json_success(
		array(
			'views'     => $count,
			'formatted' => number_format_i18n( $count ),
			'counted'   => $counted,
		)
	);
}
add_action( 'wp_ajax_quietype_record_view', 'quietype_record_post_view' );
add_action( 'wp_ajax_nopriv_quietype_record_view', 'quietype_record_post_view' );

/** Prevent accidental double counting if the legacy plugin is reactivated. */
function quietype_disable_legacy_postviews_counter() {
	return false;
}
add_filter( 'postviews_should_count', 'quietype_disable_legacy_postviews_counter', PHP_INT_MAX );

<?php
/**
 * Shared administration behavior for archive-only records.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Return archive-only post types and their user-facing destinations. */
function quietype_archive_record_types() {
	return array(
		'book'  => array(
			'fragment' => 'book',
			'label'    => '在书架中查看',
		),
		'photo' => array(
			'fragment' => 'photo',
			'label'    => '在图库中查看',
		),
	);
}

/** Link an administration record to its position in the public archive. */
function quietype_archive_record_url( $post ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$types = quietype_archive_record_types();
	if ( ! isset( $types[ $post->post_type ] ) ) {
		return '';
	}
	$url = get_post_type_archive_link( $post->post_type );
	if ( ! $url ) {
		return '';
	}
	return $url . '#' . $types[ $post->post_type ]['fragment'] . '-' . $post->ID;
}

/** Never expose a synthetic standalone permalink for archive records. */
function quietype_archive_record_permalink( $url, $post ) {
	$archive_url = quietype_archive_record_url( $post );
	return $archive_url ?: $url;
}
add_filter( 'post_type_link', 'quietype_archive_record_permalink', 10, 2 );

/** Classic editor title areas should not offer a slug or local permalink. */
function quietype_hide_archive_record_sample_permalink( $html, $post_id ) {
	return isset( quietype_archive_record_types()[ get_post_type( $post_id ) ] ) ? '' : $html;
}
add_filter( 'get_sample_permalink_html', 'quietype_hide_archive_record_sample_permalink', 10, 2 );

/** Draft preview buttons use the same archive destination as published records. */
function quietype_archive_record_preview_link( $url, $post ) {
	$archive_url = quietype_archive_record_url( $post );
	return $archive_url ?: $url;
}
add_filter( 'preview_post_link', 'quietype_archive_record_preview_link', 10, 2 );

/** Make list-table actions explicit and keep them on this WordPress site. */
function quietype_archive_record_row_actions( $actions, $post ) {
	$types = quietype_archive_record_types();
	if ( ! $post instanceof WP_Post || ! isset( $types[ $post->post_type ] ) ) {
		return $actions;
	}
	$url = quietype_archive_record_url( $post );
	if ( ! $url ) {
		unset( $actions['view'], $actions['preview'] );
		return $actions;
	}
	$link = '<a href="' . esc_url( $url ) . '">' . esc_html( $types[ $post->post_type ]['label'] ) . '</a>';
	if ( isset( $actions['view'] ) ) {
		$actions['view'] = $link;
	}
	if ( isset( $actions['preview'] ) ) {
		$actions['preview'] = $link;
	}
	return $actions;
}
add_filter( 'post_row_actions', 'quietype_archive_record_row_actions', 10, 2 );

/** Remove the classic slug box; record titles remain editable and portable. */
function quietype_remove_archive_record_slug_box( $post_type ) {
	if ( isset( quietype_archive_record_types()[ $post_type ] ) ) {
		remove_meta_box( 'slugdiv', $post_type, 'normal' );
	}
}
add_action( 'add_meta_boxes', 'quietype_remove_archive_record_slug_box', 20 );

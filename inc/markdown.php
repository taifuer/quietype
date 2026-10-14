<?php
/** Optional Markdown authoring. Saved HTML remains usable without this module. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/markdown-math.php';

/** Never run two Markdown save pipelines on the same request. */
function quietype_markdown_enabled() {
	return quietype_get_setting( 'quietype_markdown_enabled', false ) && ! class_exists( 'EditormdApp\\WPComMarkdown' );
}

/** Only new/known Markdown entries qualify; never infer Markdown from HTML. */
function quietype_markdown_post( $post ) {
	$post = get_post( $post instanceof WP_Post ? $post->ID : $post );
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return false;
	}
	$hash = get_post_meta( $post->ID, '_quietype_markdown_html_hash', true );
	if ( $hash ) {
		return hash_equals( $hash, hash( 'sha256', $post->post_content ) );
	}
	return ( 'auto-draft' === $post->post_status && '' === $post->post_content )
		|| ( '' !== $post->post_content_filtered && get_post_meta( $post->ID, '_wpcom_is_markdown', true ) );
}

/** One server parser for preview and persistence, including for administrators. */
function quietype_markdown_render( $source ) {
	require_once __DIR__ . '/markdown-parser.php';
	$parser = new Quietype_Markdown_Parser();
	$html = $parser->transform( $source );
	// WordPress treats the colon in Extra's #fn:note as a URL protocol.
	$html = preg_replace( '/(\b(?:href|id)=["\']#?fn(?:ref[0-9]*)?):/', '$1-', $html );
	// Rendering permission must not implicitly become unfiltered_html permission.
	return wp_kses_post( $html );
}

/** Get unmodified source only from an authenticated editor/heartbeat request. */
function quietype_markdown_request( $post_id ) {
	$data = isset( $_POST['data']['wp_autosave'] ) && is_array( $_POST['data']['wp_autosave'] ) ? $_POST['data']['wp_autosave'] : $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- Nonce/capabilities and typed fields are checked below; raw Markdown is unslashed below and its HTML is sanitized after parsing.
	if ( ! isset( $data['quietype_markdown_nonce'] ) ) {
		return null;
	}
	$nonce = is_string( $data['quietype_markdown_nonce'] ) ? sanitize_text_field( wp_unslash( $data['quietype_markdown_nonce'] ) ) : '';
	$requested_id = absint( $data['post_ID'] ?? $data['post_id'] ?? 0 );
	if ( ! quietype_markdown_enabled() || $post_id !== $requested_id || ! wp_verify_nonce( $nonce, 'quietype_markdown_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! quietype_markdown_post( $post_id ) || ! isset( $data['content'] ) || ! is_string( $data['content'] ) ) {
		return new WP_Error( 'quietype_markdown_invalid', 'Markdown 编辑请求已过期或无权保存，请刷新编辑页面后重试。' );
	}
	return wp_unslash( $data['content'] );
}

/** Parse only explicit editor saves and autosaves; leave REST/quick edit alone. */
function quietype_markdown_save( $data, $postarr ) {
	$is_autosave = 'revision' === $data['post_type'] && str_contains( $data['post_name'], '-autosave' );
	if ( ! $is_autosave && ! in_array( $data['post_type'], array( 'post', 'page' ), true ) ) {
		return $data;
	}
	$post_id = (int) ( $is_autosave ? $data['post_parent'] : ( $postarr['ID'] ?? 0 ) );
	$source = quietype_markdown_request( $post_id );
	if ( is_wp_error( $source ) ) {
		wp_die( esc_html( $source->get_error_message() ), '', array( 'response' => 403 ) );
	}
	if ( null === $source ) {
		return $data;
	}
	$html = quietype_markdown_render( $source );
	$data['post_content'] = wp_slash( $html );
	$data['post_content_filtered'] = wp_slash( $source );
	if ( ! $is_autosave ) {
		$GLOBALS['quietype_markdown_pending'][ $post_id ] = hash( 'sha256', $html );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'quietype_markdown_save', 50, 2 );

/** Record ownership only after a successful write; revisions contain both fields. */
function quietype_markdown_saved( $post_id, $post ) {
	$hash = $GLOBALS['quietype_markdown_pending'][ $post_id ] ?? '';
	if ( $hash && hash_equals( $hash, hash( 'sha256', $post->post_content ) ) ) {
		update_post_meta( $post_id, '_quietype_markdown_html_hash', $hash );
		// Keep a standard compatibility flag, so the original editor can reopen it.
		update_post_meta( $post_id, '_wpcom_is_markdown', true );
		unset( $GLOBALS['quietype_markdown_pending'][ $post_id ] );
	}
}
add_action( 'save_post', 'quietype_markdown_saved', 9, 2 );

function quietype_markdown_revision_fields( $fields ) {
	$fields['post_content_filtered'] = 'Markdown 原文';
	return $fields;
}
add_filter( '_wp_post_revision_fields', 'quietype_markdown_revision_fields' );

/** Restore matching HTML/source together, never reparse an old rendered revision. */
function quietype_markdown_restored( $post_id, $revision_id ) {
	if ( ! get_post_meta( $post_id, '_quietype_markdown_html_hash', true ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( quietype_markdown_render( $post->post_content_filtered ) === $post->post_content ) {
		update_post_meta( $post_id, '_quietype_markdown_html_hash', hash( 'sha256', $post->post_content ) );
	}
}
add_action( 'wp_restore_post_revision', 'quietype_markdown_restored', 20, 2 );

function quietype_markdown_block_editor( $use, $post ) {
	return quietype_markdown_enabled() && quietype_markdown_post( $post ) ? false : $use;
}
add_filter( 'use_block_editor_for_post', 'quietype_markdown_block_editor', 100, 2 );

function quietype_markdown_edit_source( $content, $post_id ) {
	return quietype_markdown_enabled() && quietype_markdown_post( $post_id ) ? get_post( $post_id )->post_content_filtered : $content;
}
add_filter( 'edit_post_content', 'quietype_markdown_edit_source', 20, 2 );

function quietype_markdown_editor_settings( $settings, $editor_id ) {
	global $post;
	if ( 'content' === $editor_id && quietype_markdown_enabled() && quietype_markdown_post( $post ) ) {
		$settings['tinymce'] = false;
		$settings['quicktags'] = false;
		$settings['media_buttons'] = false;
		$settings['textarea_rows'] = 24;
	}
	return $settings;
}
add_filter( 'wp_editor_settings', 'quietype_markdown_editor_settings', 20, 2 );

require_once __DIR__ . '/markdown-admin.php';

/** Local math/code libraries also serve migrated legacy HTML after plugin removal. */
function quietype_markdown_assets() {
	if ( ! is_singular( array( 'post', 'page' ) ) || ( ! quietype_markdown_enabled() && ! get_post_meta( get_queried_object_id(), '_quietype_markdown_html_hash', true ) ) ) {
		return;
	}
	// Already-saved module articles keep their renderer when the option is off
	// or Editor.md is reactivated, without loading two versions of each library.
	foreach ( array( 'Katex', 'Front_Style', 'prism-core-js', 'prism-plugin-autoloader', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'prism-plugin-show-language', 'Prism' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
	foreach ( array( 'Katex', 'prism-theme-default', 'prism-theme-style' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
	$features = quietype_content_features();
	$base = get_template_directory_uri();
	$deps = array();
	if ( $features['code'] ) {
		wp_enqueue_script( 'quietype-prism', $base . '/assets/vendor/prism/prism.js', array(), '1.30.0', true );
		wp_add_inline_script( 'quietype-prism', 'window.Prism=window.Prism||{};Prism.manual=true;', 'before' );
		wp_enqueue_script( 'quietype-prism-autoloader', $base . '/assets/vendor/prism/plugins/autoloader/prism-autoloader.min.js', array( 'quietype-prism' ), '1.30.0', true );
		wp_add_inline_script( 'quietype-prism-autoloader', 'Prism.plugins.autoloader.languages_path=' . wp_json_encode( $base . '/assets/vendor/prism/components/' ) . ';', 'after' );
		wp_enqueue_script( 'quietype-prism-lines', $base . '/assets/vendor/prism/plugins/line-numbers/prism-line-numbers.min.js', array( 'quietype-prism' ), '1.30.0', true );
		$deps[] = 'quietype-prism-autoloader';
		$deps[] = 'quietype-prism-lines';
	}
	if ( $features['math'] ) {
		wp_enqueue_style( 'quietype-katex', $base . '/assets/vendor/katex/katex.min.css', array(), '0.19.0' );
		wp_enqueue_script( 'quietype-katex', $base . '/assets/vendor/katex/katex.min.js', array(), '0.19.0', true );
		$deps[] = 'quietype-katex';
	}
	if ( $deps ) {
		wp_enqueue_script( 'quietype-markdown-render', $base . '/assets/js/markdown-render.js', $deps, quietype_asset_version( 'assets/js/markdown-render.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'quietype_markdown_assets', 120 );

function quietype_markdown_saved_initializers() {
	if ( is_singular( array( 'post', 'page' ) ) && get_post_meta( get_queried_object_id(), '_quietype_markdown_html_hash', true ) ) {
		quietype_remove_object_action( 'wp_print_footer_scripts', 'EditormdApp\\KaTeX', 'katex_wp_footer_scripts' );
	}
}
add_action( 'wp', 'quietype_markdown_saved_initializers', 22 );

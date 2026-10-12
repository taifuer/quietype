<?php
/**
 * Progressive enhancements for technical articles, without changing saved content.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turn an explicit caption paragraph into semantic image/table markup.
 *
 * Runs after Markdown/wpautop/shortcodes, including excerpts and feeds. Only
 * adjacent, standalone media qualify; saved content and existing captions stay
 * untouched. Avoid reserializing the rest of an article (SVG, math and code).
 */
function quietype_render_captions( $content ) {
	if ( false === stripos( $content, '[!CAPTION]' ) ) {
		return $content;
	}

	// Quoted attributes may contain >; do not mistake it for the end of a tag.
	$attrs = '(?:[^<>"\']|"[^"]*"|\'[^\']*\')*';
	$image = '<img\b' . $attrs . '>';
	$picture = '<picture\b' . $attrs . '>\s*(?:<source\b' . $attrs . '>\s*)*' . $image . '\s*</picture>';
	$media = '(?:' . $image . '|' . $picture . ')';
	$media = '(?:<a\b' . $attrs . '>\s*' . $media . '\s*</a>|' . $media . ')';
	$pattern = '~<!--.*?-->(*SKIP)(*F)'
		. '|<(?P<protected>pre|code|script|style|textarea|figure|svg|math|noscript)\b' . $attrs . '>.*?</(?P=protected)\s*>(*SKIP)(*F)'
		. '|<div\b(?=[^>]*\bclass=["\'][^"\']*\bwp-caption\b)' . $attrs . '>.*?</div\s*>(*SKIP)(*F)'
		. '|(?:(?P<image><p\b' . $attrs . '>\s*' . $media . '\s*</p>)'
		. '|(?P<table>(?P<table_open><table\b' . $attrs . '>)(?:(?!</table\b).)*</table>))'
		. '\s*<p\b' . $attrs . '>\s*\[!CAPTION\][\t ]+(?P<caption>.*?)</p>~isu';

	$rendered = preg_replace_callback(
		$pattern,
		function ( $match ) {
			$caption = trim( wp_kses( $match['caption'], array(
				'a'      => array( 'href' => true, 'title' => true ),
				'strong' => array(),
				'em'     => array(),
				'b'      => array(),
				'i'      => array(),
				'code'   => array(),
				'del'    => array(),
				'sup'    => array(),
				'sub'    => array(),
				'br'     => array(),
			) ) );
			if ( '' === trim( wp_strip_all_tags( $caption ) ) ) {
				return $match[0];
			}
			if ( ! empty( $match['image'] ) ) {
				return '<figure class="article-figure">' . $match['image'] . '<figcaption>' . $caption . '</figcaption></figure>';
			}
			// Do not overwrite a native caption or interpret nested tables.
			if ( preg_match( '/<caption\b/i', $match['table'] ) || 1 !== preg_match_all( '/<table\b/i', $match['table'] ) ) {
				return $match[0];
			}
			return $match['table_open'] . '<caption>' . $caption . '</caption>' . substr( $match['table'], strlen( $match['table_open'] ) );
		},
		$content
	);
	return null === $rendered ? $content : $rendered;
}
add_filter( 'the_content', 'quietype_render_captions', 11 );

/** Normalize technical reading markup without changing the stored article. */
function quietype_prepare_reading_content( $content ) {
	// Editor.md stores diagrams as document.write payloads. Decode that exact
	// legacy string format without executing JavaScript or changing stored Markdown.
	$content = preg_replace_callback(
		'~<div\b[^>]*class=["\'][^"\']*\bmermaid\b[^"\']*["\'][^>]*>\s*<script\b[^>]*>\s*document\.write\("((?:\\\\.|[^"\\\\])*)"\);?\s*</script>\s*</div>~isu',
		function ( $match ) {
			$source = preg_replace_callback(
				'/\\\\([\\\\\'"nrt0])/',
				function ( $escape ) {
					$map = array( 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => '' );
					return $map[ $escape[1] ] ?? $escape[1];
				},
				$match[1]
			);
			return '<pre class="mermaid">' . esc_html( html_entity_decode( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . '</pre>';
		},
		$content
	);
	// Standard fenced-code HTML must not be sent to Prism's language autoloader.
	$content = preg_replace_callback(
		'~(<pre\b[^>]*>)(.*?)</pre>~isu',
		function ( $match ) {
			$pre = new WP_HTML_Tag_Processor( $match[1] );
			$pre->next_tag();
			$code = new WP_HTML_Tag_Processor( $match[2] );
			$is_mermaid = $pre->has_class( 'mermaid' ) || $pre->has_class( 'language-mermaid' );
			if ( ! $is_mermaid && ( ! $code->next_tag( 'CODE' ) || ! $code->has_class( 'language-mermaid' ) ) ) {
				return $match[0];
			}
			$pre->remove_class( 'language-mermaid' );
			$pre->add_class( 'mermaid' );
			$pre->set_attribute( 'tabindex', '0' );
			return $pre->get_updated_html() . esc_html( html_entity_decode( wp_strip_all_tags( $match[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . '</pre>';
		},
		$content
	);
	// GitHub-style alerts work with any editor producing ordinary blockquotes.
	$content = preg_replace_callback(
		'~<blockquote(\s[^>]*)?>\s*<p>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\](?:<br\s*/?>|\s)*(.*?)</p>~isu',
		function ( $match ) {
			$labels = array( 'NOTE' => '说明', 'TIP' => '提示', 'IMPORTANT' => '重要', 'WARNING' => '注意', 'CAUTION' => '警告' );
			$type = strtoupper( $match[2] );
			$title = '<p class="article-alert__title">' . esc_html( $labels[ $type ] ) . '</p>';
			$body = '' !== trim( $match[3] ) ? '<p>' . $match[3] . '</p>' : '';
			$tag = new WP_HTML_Tag_Processor( '<blockquote' . ( $match[1] ?? '' ) . '>' );
			$tag->next_tag();
			$tag->add_class( 'article-alert' );
			$tag->add_class( 'article-alert--' . strtolower( $type ) );
			return $tag->get_updated_html() . $title . $body;
		},
		$content
	);
	return preg_replace_callback(
		'~<table\b[^>]*>.*?</table>~isu',
		function ( $match ) {
			if ( preg_match( '/<table\b.*<table\b/is', $match[0] ) ) {
				return $match[0];
			}
			return '<div class="article-table" tabindex="0" role="region" aria-label="数据表格">' . $match[0] . '</div>';
		},
		$content
	);
}

/** Own the public diagram renderer; plugin editor previews remain untouched. */
function quietype_reading_assets() {
	wp_dequeue_script( 'Mermaid' );
	$scripts = wp_scripts();
	if ( wp_script_is( 'prism-core-js', 'enqueued' ) ) {
		foreach ( array( 'autoloader', 'toolbar', 'line-numbers', 'show-language' ) as $plugin ) {
			$handle = 'prism-plugin-' . $plugin;
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$scripts->registered[ $handle ]->deps[] = 'prism-core-js';
			}
		}
		if ( isset( $scripts->registered['prism-plugin-show-language'], $scripts->registered['prism-plugin-toolbar'] ) ) {
			$scripts->registered['prism-plugin-show-language']->deps[] = 'prism-plugin-toolbar';
		}
		$path = preg_replace( '~/components/[^/]+$~', '/components/', $scripts->registered['prism-core-js']->src );
		wp_add_inline_script( 'prism-plugin-autoloader', 'if(window.Prism && Prism.plugins.autoloader){Prism.plugins.autoloader.languages_path=' . wp_json_encode( esc_url_raw( $path ) ) . ';}', 'after' );
	}
	$features = quietype_content_features();
	if ( ! is_singular( array( 'post', 'page' ) ) || ! $features['mermaid'] || ! quietype_get_setting( 'quietype_mermaid_enabled', true ) ) {
		return;
	}
	wp_enqueue_script( 'quietype-mermaid', get_template_directory_uri() . '/assets/js/mermaid.js', array( 'quietype-reading' ), quietype_asset_version( 'assets/js/mermaid.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'quietype_reading_assets', 110 );

/** Replace legacy footer initializers whose order or renderer is incompatible. */
function quietype_remove_legacy_reading_initializers() {
	quietype_remove_object_action( 'wp_print_footer_scripts', 'EditormdApp\\Mermaid', 'mermaid_wp_footer_script' );
	quietype_remove_object_action( 'wp_print_footer_scripts', 'EditormdApp\\PrismJSAuto', 'prism_wp_footer_scripts' );
}
add_action( 'wp', 'quietype_remove_legacy_reading_initializers', 21 );

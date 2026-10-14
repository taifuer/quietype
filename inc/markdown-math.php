<?php
/** Shared formula boundaries for Markdown source and existing article HTML. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Leave escaped delimiters, prices, code spans and link targets literal. */
function quietype_markdown_math_pattern( $markdown = false ) {
	$skip = $markdown ? '(?<!`)(`+)(?!`)(?:(?!\1).)*?\1(?!`)(*SKIP)(*F)|<code\b[^>]*>.*?</code>(*SKIP)(*F)|<[^>]+>(*SKIP)(*F)|\]\([^\n]*?\)(*SKIP)(*F)|' : '';
	$math = <<<'REGEX'
(?:https?://|www\.)[^\s<]+(*SKIP)(*F)
|(?<![\\$])\$\$(?<display_dollar>(?:\\.|[^\\$]|\$(?!\$))+?)\$\$(?!\$)
|(?<!\\)\\\[(?<display_bracket>[\s\S]+?)\\\]
|(?<!\\)\\\((?<paren>[^\r\n]*?)\\\)
|(?<![\\$])\$(?![\s$])(?<dollar>(?:\\.|[^$\r\n\\])+?)(?<!\s)\$(?![\d$])
REGEX;
	return '~' . $skip . $math . '~sx';
}

/** Encode punctuation before Markdown, wptexturize and wpautop can alter TeX. */
function quietype_markdown_math_markup( $source, $display = false, $block = false ) {
	$escaped = preg_replace_callback( '/[[:punct:]\r\n\t]/', static function ( $match ) {
		return '&#' . ord( $match[0] ) . ';';
	}, trim( $source ) );
	$tag = $block ? 'div' : 'span';
	return '<' . $tag . ' class="quietype-math" data-display="' . ( $display ? 'true' : 'false' ) . '">' . $escaped . '</' . $tag . '>';
}

function quietype_markdown_math_match( $match, $html = false ) {
	foreach ( array( 'display_dollar', 'display_bracket', 'paren', 'dollar' ) as $key ) {
		if ( isset( $match[ $key ] ) && '' !== $match[ $key ] ) {
			$source = $html ? html_entity_decode( $match[ $key ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $match[ $key ];
			return quietype_markdown_math_markup( $source, str_starts_with( $key, 'display_' ) );
		}
	}
	return $match[0];
}

/** Read old HTML in place; never reparse Markdown or write existing articles. */
function quietype_markdown_legacy_math( $content ) {
	if ( ! quietype_markdown_enabled() || ( get_post_meta( get_the_ID(), '_quietype_markdown_html_hash', true ) && quietype_markdown_post( get_the_ID() ) ) ) {
		return $content;
	}
	// Old fenced KaTeX blocks also need protection from smart punctuation.
	$attrs = '(?:[^<>"\']|"[^"]*"|\'[^\']*\')*';
	$pattern = '~<!--.*?-->(*SKIP)(*F)|<(pre|code|script|style|textarea|kbd|samp|svg|math)\b' . $attrs . '>.*?</\1>(*SKIP)(*F)|<(span|div)\b(' . $attrs . ')>([^<]*)</\2>~is';
	$content = preg_replace_callback( $pattern, static function ( $match ) {
		$tag = new WP_HTML_Tag_Processor( '<' . $match[2] . $match[3] . '>' );
		$tag->next_tag();
		if ( ! $tag->has_class( 'katex' ) || ! $tag->has_class( 'math' ) ) {
			return $match[0];
		}
		return quietype_markdown_math_markup( html_entity_decode( $match[4], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $tag->has_class( 'multi-line' ), 'div' === strtolower( $match[2] ) );
	}, $content );
	return quietype_transform_reading_prose( $content, static function ( $text ) {
		return preg_replace_callback( quietype_markdown_math_pattern(), static function ( $match ) {
			return quietype_markdown_math_match( $match, true );
		}, $text ) ?? $text;
	}, true );
}
add_filter( 'the_content', 'quietype_markdown_legacy_math', 8 );

/** Code samples alone must not cause the formula libraries to download. */
function quietype_content_has_math( $content ) {
	if ( preg_match( '/class=["\'][^"\']*\b(?:katex|quietype-math)\b|<math\b/i', $content ) ) {
		return true;
	}
	$found = false;
	quietype_transform_reading_prose( $content, static function ( $text ) use ( &$found ) {
		$found = $found || (bool) preg_match( quietype_markdown_math_pattern(), $text );
		return $text;
	}, true );
	return $found;
}

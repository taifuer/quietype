<?php
/** Native file-video playback using WordPress's portable [video] shortcode. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Accept remote/local HTTP media URLs, never executable or inline schemes. */
function quietype_video_url( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( ! preg_match( '~^https?://~i', $value ) || ! wp_parse_url( $value, PHP_URL_HOST ) ) {
		return '';
	}
	return esc_url_raw( $value, array( 'http', 'https' ) );
}

/** No remote probing, autoplay, MediaElement or other player assets. */
function quietype_native_video( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$sources = array();
	$types = array( 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg' );
	foreach ( array_merge( array( 'src' ), array_keys( $types ) ) as $key ) {
		$url = quietype_video_url( $attributes[ $key ] ?? '' );
		$extension = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( $url && isset( $types[ $extension ] ) ) {
			$sources[ $url ] = $types[ $extension ];
		}
	}
	if ( ! $sources ) {
		return '<span class="article-video-error">视频地址无效，请填写 MP4、WebM 或 OGV 文件链接。</span>';
	}
	$title = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? sanitize_text_field( $attributes['title'] ) : '';
	$html = '<video class="quietype-video" controls playsinline preload="none" aria-label="' . esc_attr( $title ?: '视频播放' ) . '"';
	$poster = quietype_video_url( $attributes['poster'] ?? '' );
	if ( $poster ) {
		$html .= ' poster="' . esc_url( $poster ) . '"';
	}
	// Only a complete, explicit pair describes the original aspect ratio.
	if ( isset( $attributes['width'], $attributes['height'] ) && ctype_digit( (string) $attributes['width'] ) && ctype_digit( (string) $attributes['height'] ) && (int) $attributes['width'] > 0 && (int) $attributes['height'] > 0 ) {
		$html .= ' width="' . esc_attr( $attributes['width'] ) . '" height="' . esc_attr( $attributes['height'] ) . '"';
	}
	$html .= '>';
	foreach ( $sources as $url => $type ) {
		$html .= '<source src="' . esc_url( $url ) . '" type="' . esc_attr( $type ) . '">';
	}
	return $html . '<a href="' . esc_url( array_key_first( $sources ) ) . '">打开视频文件</a></video>';
}

/** Standard shortcode calls outside the article use the same native markup. */
function quietype_video_shortcode_override( $html, $attributes ) {
	return '' !== $html ? $html : quietype_native_video( $attributes );
}
add_filter( 'wp_video_shortcode_override', 'quietype_video_shortcode_override', 10, 2 );

/**
 * Render video markers only in prose, not inside code, attributes or comments.
 *
 * Mask candidates for the duration of Core's shortcode pass, then restore the
 * literal examples byte-for-byte. Other shortcodes keep their normal behavior;
 * the isolated editor preview never executes them. No database writes occur.
 */
function quietype_render_video_shortcodes( $content, $other_shortcodes = false ) {
	if ( false === strpos( $content, '[video' ) ) {
		return $other_shortcodes ? do_shortcode( $content ) : $content;
	}
	$originals = array();
	$matches = array();
	$prefix = 'QUIETYPEVIDEO' . wp_generate_password( 24, false, false ) . 'TOKEN';
	$content = preg_replace_callback( '/' . get_shortcode_regex( array( 'video' ) ) . '/s', static function ( $match ) use ( &$originals, &$matches, $prefix ) {
		$token = $prefix . count( $originals ) . 'END';
		$originals[ $token ] = $match[0];
		$matches[ $token ] = $match;
		return $token;
	}, $content );
	// Run Core while all video candidates (including [[escaped]] ones) are inert.
	if ( $other_shortcodes ) {
		$content = do_shortcode( $content );
	}
	$content = quietype_transform_reading_prose( $content, static function ( $text ) use ( $matches, $prefix ) {
		return preg_replace_callback( '/' . $prefix . '\d+END/', static function ( $token ) use ( $matches ) {
			$match = $matches[ $token[0] ];
			if ( '[' === $match[1] && ']' === $match[6] ) {
				return esc_html( substr( $match[0], 1, -1 ) );
			}
			return quietype_native_video( shortcode_parse_atts( html_entity_decode( $match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		}, $text );
	}, true );
	return strtr( $content, $originals );
}

function quietype_video_content( $content ) {
	return quietype_render_video_shortcodes( $content, true );
}

/** Keep Core's shortcode timing; only add the literal-video boundary guard. */
function quietype_video_content_filter() {
	if ( false !== has_filter( 'the_content', 'do_shortcode' ) ) {
		$priority = has_filter( 'the_content', 'do_shortcode' );
		remove_filter( 'the_content', 'do_shortcode', $priority );
		add_filter( 'the_content', 'quietype_video_content', $priority );
	}
}
add_action( 'init', 'quietype_video_content_filter', 25 );

<?php
/**
 * Content-aware front-end loading and remote image metadata.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Inspect the queried entry without rendering the_content a second time. */
function quietype_content_features() {
	static $features = null;
	if ( null !== $features ) {
		return $features;
	}
	$post    = is_singular() ? get_queried_object() : null;
	$content = $post instanceof WP_Post ? (string) $post->post_content : '';
	$features = array(
		'images'   => (bool) preg_match( '/<img\b|!\[[^\]]*\]\([^\)]+\)/iu', $content ),
		'code'     => (bool) preg_match( '/<pre\b|<code\b[^>]*\blanguage-|(?:^|\n)[ \t]*(?:```|~~~)/iu', $content ),
		'math'     => (bool) preg_match( '/class=["\'][^"\']*\bkatex\b|<math\b|\\\\\[|\\\\\]|\$\$|(?:^|[\s(])\$[^$\n]{1,500}\$(?=[\s).,，。]|$)/iu', $content ),
		'mermaid'  => (bool) preg_match( '/(?:```|~~~)[ \t]*mermaid\b|class=["\'][^"\']*\bmermaid\b/iu', $content ),
		'mindmap'  => (bool) preg_match( '/(?:```|~~~)[ \t]*mindmap\b|class=["\'][^"\']*\bmindmap\b/iu', $content ),
	);
	return $features;
}

/** Extract remote images from Markdown and rendered HTML. */
function quietype_remote_image_urls( $content ) {
	$urls = array();
	if ( preg_match_all( '/<img\b[^>]*\bsrc=["\'](https?:\/\/[^"\']+)["\']/iu', $content, $html_matches ) ) {
		$urls = array_merge( $urls, $html_matches[1] );
	}
	if ( preg_match_all( '/!\[[^\]]*\]\(\s*<?(https?:\/\/[^\s)>]+)>?(?:\s+["\'][^"\']*["\'])?\s*\)/u', $content, $markdown_matches ) ) {
		$urls = array_merge( $urls, $markdown_matches[1] );
	}
	return array_values( array_unique( array_map( 'esc_url_raw', $urls ) ) );
}

/** Queue remote image dimension discovery outside the publishing request. */
function quietype_schedule_image_metadata( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	if ( ! quietype_remote_image_urls( $post->post_content ) ) {
		delete_post_meta( $post_id, '_quietype_image_dimensions' );
		return;
	}
	if ( ! wp_next_scheduled( 'quietype_refresh_image_metadata', array( $post_id ) ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'quietype_refresh_image_metadata', array( $post_id ) );
	}
}
add_action( 'save_post', 'quietype_schedule_image_metadata', 20, 2 );

/** Read only enough of each remote image to discover intrinsic dimensions. */
function quietype_refresh_image_metadata( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$urls       = array_slice( quietype_remote_image_urls( $post->post_content ), 0, 30 );
	$dimensions = array_intersect_key( (array) get_post_meta( $post_id, '_quietype_image_dimensions', true ), array_flip( $urls ) );
	foreach ( $urls as $url ) {
		if ( isset( $dimensions[ $url ]['width'], $dimensions[ $url ]['height'] ) ) {
			continue;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 6,
				'redirection'         => 3,
				'limit_response_size' => 262144,
				'headers'             => array( 'Range' => 'bytes=0-262143' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			continue;
		}
		$size = @getimagesizefromstring( wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid or partial remote images are expected.
		if ( $size && $size[0] > 0 && $size[1] > 0 ) {
			$dimensions[ $url ] = array( 'width' => (int) $size[0], 'height' => (int) $size[1] );
		}
	}
	update_post_meta( $post_id, '_quietype_image_dimensions', $dimensions );
}
add_action( 'quietype_refresh_image_metadata', 'quietype_refresh_image_metadata' );

/** Add non-blocking decoding and known intrinsic dimensions after Markdown renders. */
function quietype_optimize_content_images( $content ) {
	if ( ! is_singular() || false === stripos( $content, '<img' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}
	$dimensions = (array) get_post_meta( get_queried_object_id(), '_quietype_image_dimensions', true );
	$processor  = new WP_HTML_Tag_Processor( $content );
	while ( $processor->next_tag( 'img' ) ) {
		if ( ! $processor->get_attribute( 'decoding' ) ) {
			$processor->set_attribute( 'decoding', 'async' );
		}
		$src = html_entity_decode( (string) $processor->get_attribute( 'src' ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		if ( ! $processor->get_attribute( 'width' ) && ! $processor->get_attribute( 'height' ) && isset( $dimensions[ $src ] ) ) {
			$processor->set_attribute( 'width', (string) absint( $dimensions[ $src ]['width'] ) );
			$processor->set_attribute( 'height', (string) absint( $dimensions[ $src ]['height'] ) );
		}
	}
	return $processor->get_updated_html();
}
add_filter( 'the_content', 'quietype_optimize_content_images', 100 );

/** Warn authors about generic or missing image descriptions before publishing. */
function quietype_image_accessibility_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor notice.
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$generic = preg_match_all( '/!\[(?:\s*|image|图片)\]\(/iu', $post->post_content );
	$generic += preg_match_all( '/<img\b(?:(?!\balt=)[^>])*\>|<img\b[^>]*\balt=["\']\s*(?:image|图片)?\s*["\']/iu', $post->post_content );
	if ( $generic > 0 ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '阅读检查：正文中有 %d 张图片缺少有效替代文字，请在发布前补充具体描述。', $generic ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'quietype_image_accessibility_notice' );

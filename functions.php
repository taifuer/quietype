<?php
/**
 * Quietype theme functions.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QUIETYPE_VERSION', '0.2.3' );

function quietype_setup() {
	load_theme_textdomain( 'quietype', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	register_nav_menus(
		array(
			'primary' => '顶部导航',
			'footer'  => '页脚导航',
		)
	);
}
add_action( 'after_setup_theme', 'quietype_setup' );

function quietype_assets() {
	wp_enqueue_style( 'quietype', get_stylesheet_uri(), array(), QUIETYPE_VERSION );
	wp_enqueue_script( 'quietype', get_template_directory_uri() . '/assets/js/theme.js', array(), QUIETYPE_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'quietype_assets', 20 );

/**
 * WP Editor.md registers its reading assets globally. Keep them on singular
 * content pages, but do not make archive and search pages pay that cost.
 */
function quietype_trim_editor_assets() {
	if ( is_singular() ) {
		return;
	}
	$styles = array( 'Katex', 'prism-theme-style', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'Prism', 'Emojify.js' );
	$scripts = array( 'Katex', 'copy-clipboard', 'prism-core-js', 'prism-plugin-autoloader', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'prism-plugin-show-language', 'prism-plugin-copy-to-clipboard', 'Front_Style', 'Prism', 'Emojify.js', 'Mermaid', 'MindMap' );
	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'quietype_trim_editor_assets', 100 );

/** Remove the legacy Plyr plugin's global footer payload when no media exists. */
function quietype_conditionally_disable_plyr() {
	if ( ! function_exists( 'themtuts_plyr_css_and_js_files' ) ) {
		return;
	}
	$post       = is_singular() ? get_queried_object() : null;
	$has_media  = $post instanceof WP_Post && preg_match( '/<(audio|video)\b|\[(audio|video)\b/i', $post->post_content );
	if ( ! $has_media ) {
		remove_action( 'wp_footer', 'themtuts_plyr_css_and_js_files' );
	}
}
add_action( 'wp', 'quietype_conditionally_disable_plyr' );

function quietype_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		return array();
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'quietype_resource_hints', 10, 2 );

function quietype_cleanup_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'quietype_cleanup_head' );

function quietype_body_classes( $classes ) {
	if ( is_singular() ) {
		$classes[] = 'is-singular';
	}
	return $classes;
}
add_filter( 'body_class', 'quietype_body_classes' );

function quietype_menu_fallback() {
	echo '<ul class="menu">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">首页</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/articlearchive/' ) ) . '">归档</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/links/' ) ) . '">友链</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">关于</a></li>';
	echo '</ul>';
}

function quietype_reading_stats( $post_id = null ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array( 'minutes' => 1, 'characters' => 0 );
	}
	$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	preg_match_all( '/[\x{3400}-\x{9fff}\x{f900}-\x{faff}]/u', $text, $han );
	preg_match_all( '/[A-Za-z0-9_]+(?:[-\'][A-Za-z0-9_]+)*/u', $text, $latin );
	$han_count   = count( $han[0] );
	$latin_count = count( $latin[0] );
	$minutes     = max( 1, (int) ceil( $han_count / 400 + $latin_count / 220 ) );
	return array(
		'minutes'   => $minutes,
		'characters' => $han_count + $latin_count,
	);
}

function quietype_primary_category( $post_id = null ) {
	$categories = get_the_category( $post_id );
	if ( empty( $categories ) ) {
		return '';
	}
	$category = end( $categories );
	return '<a class="post-category" href="' . esc_url( get_category_link( $category ) ) . '">#' . esc_html( $category->name ) . '</a>';
}

function quietype_post_terms( $post_id = null, $limit = 3 ) {
	$links = array();
	$categories = get_the_category( $post_id );
	if ( $categories ) {
		$category = end( $categories );
		$links[] = '<a class="post-category" href="' . esc_url( get_category_link( $category ) ) . '">#' . esc_html( $category->name ) . '</a>';
	}
	$tags = get_the_tags( $post_id );
	if ( $tags ) {
		foreach ( $tags as $tag ) {
			if ( count( $links ) >= $limit ) {
				break;
			}
			$links[] = '<a class="post-tag" href="' . esc_url( get_tag_link( $tag ) ) . '">#' . esc_html( $tag->name ) . '</a>';
		}
	}
	return implode( ' ', $links );
}

function quietype_excerpt_length() {
	return 72;
}
add_filter( 'excerpt_length', 'quietype_excerpt_length', 99 );

function quietype_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'quietype_excerpt_more' );

/**
 * Add predictable anchors and return a TOC for rendered post HTML.
 *
 * @param string $content Filtered post content.
 * @return array{content:string,items:array<int,array<string,string>>}
 */
function quietype_prepare_article( $content ) {
	$items = array();
	$used  = array();
	$index = 0;
	$content = preg_replace_callback(
		'/<h([23])([^>]*)>(.*?)<\/h\1>/isu',
		function ( $matches ) use ( &$items, &$used, &$index ) {
			++$index;
			$level = (int) $matches[1];
			$label = trim( wp_strip_all_tags( $matches[3] ) );
			$attrs = $matches[2];
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $attrs, $id_match ) ) {
				$id = $id_match[1];
			} else {
				$id = 'section-' . $index;
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			if ( isset( $used[ $id ] ) ) {
				$id     .= '-' . $index;
				$attrs   = preg_replace( '/\sid=["\'][^"\']+["\']/i', ' id="' . esc_attr( $id ) . '"', $attrs );
			}
			$used[ $id ] = true;
			$items[] = array( 'level' => $level, 'id' => $id, 'label' => $label );
			return '<h' . $level . $attrs . '>' . $matches[3] . '</h' . $level . '>';
		},
		$content
	);
	return array( 'content' => $content, 'items' => $items );
}

function quietype_pagination() {
	$links = paginate_links(
		array(
			'prev_text' => '← 较新文章',
			'next_text' => '更早文章 →',
			'type'      => 'array',
		)
	);
	if ( $links ) {
		echo '<nav class="pagination" aria-label="文章分页">' . wp_kses_post( implode( '', $links ) ) . '</nav>';
	}
}

function quietype_archive_url() {
	$page = get_page_by_path( 'articlearchive' );
	if ( ! $page ) {
		$page = get_page_by_path( 'archive' );
	}
	return $page ? get_permalink( $page ) : home_url( '/?post_type=post' );
}

function quietype_comment_form_defaults( $defaults ) {
	$defaults['title_reply']          = '写下评论';
	$defaults['label_submit']         = '提交评论';
	$defaults['comment_notes_before'] = '<p class="comment-notes">邮箱不会公开，必填项已标注。</p>';
	return $defaults;
}
add_filter( 'comment_form_defaults', 'quietype_comment_form_defaults' );

function quietype_allowed_html( $tags ) {
	if ( isset( $tags['a'] ) ) {
		$tags['a']['data-footnote-backref'] = true;
	}
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'quietype_allowed_html' );

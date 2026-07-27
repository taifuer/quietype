<?php
/**
 * Quietype theme functions.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QUIETYPE_VERSION', '0.10.7' );

require_once get_template_directory() . '/inc/admin-settings.php';
require_once get_template_directory() . '/inc/login-security.php';
require_once get_template_directory() . '/inc/books.php';
require_once get_template_directory() . '/inc/photos.php';
require_once get_template_directory() . '/inc/archive-records.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/mail.php';
require_once get_template_directory() . '/inc/content-performance.php';
require_once get_template_directory() . '/inc/wordpress-tweaks.php';
require_once get_template_directory() . '/inc/link-status.php';
require_once get_template_directory() . '/inc/view-count.php';

function quietype_setup() {
	load_theme_textdomain( 'quietype', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_image_size( 'quietype-book-cover', 252, 372, false );
	add_image_size( 'quietype-photo-grid', 1280, 1280, false );
	add_image_size( 'quietype-photo-lightbox', 2560, 2560, false );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	register_nav_menus(
		array(
			'primary'   => '顶部导航',
			'prefooter' => '页尾上方导航',
		)
	);
}
add_action( 'after_setup_theme', 'quietype_setup' );

/** Bust long-lived browser caches whenever a packaged asset changes. */
function quietype_asset_version( $relative_path ) {
	$path = get_theme_file_path( $relative_path );
	return is_file( $path ) ? (string) filemtime( $path ) : QUIETYPE_VERSION;
}

function quietype_assets() {
	$style_dependencies = array();
	$features = quietype_content_features();
	$has_lightbox = ( is_singular() && $features['images'] ) || is_post_type_archive( 'photo' );
	if ( $has_lightbox ) {
		wp_enqueue_style( 'quietype-photoswipe', get_template_directory_uri() . '/assets/vendor/photoswipe/photoswipe.css', array(), '5.4.4' );
		$style_dependencies[] = 'quietype-photoswipe';
	}
	wp_enqueue_style( 'quietype', get_stylesheet_uri(), $style_dependencies, quietype_asset_version( 'style.css' ) );
	wp_enqueue_script( 'quietype', get_template_directory_uri() . '/assets/js/theme.js', array(), quietype_asset_version( 'assets/js/theme.js' ), true );
	if ( is_singular( 'post' ) ) {
		wp_localize_script(
			'quietype',
			'quietypeViewConfig',
			array(
				'endpoint'        => admin_url( 'admin-ajax.php' ),
				'postId'          => get_queried_object_id(),
				'token'           => quietype_view_token( get_queried_object_id() ),
				'cooldownSeconds' => 6 * HOUR_IN_SECONDS,
			)
		);
	}
	$lightbox_dependencies = array();
	if ( is_post_type_archive( 'photo' ) ) {
		wp_enqueue_script( 'quietype-photos', get_template_directory_uri() . '/assets/js/photos.js', array(), quietype_asset_version( 'assets/js/photos.js' ), true );
		$lightbox_dependencies[] = 'quietype-photos';
	}
	if ( $has_lightbox ) {
		wp_enqueue_script( 'quietype-lightbox', get_template_directory_uri() . '/assets/js/lightbox.js', $lightbox_dependencies, quietype_asset_version( 'assets/js/lightbox.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'quietype_assets', 20 );

/** Give WordPress authentication screens the same quiet reading surface. */
function quietype_login_assets() {
	wp_enqueue_style(
		'quietype-login',
		get_template_directory_uri() . '/assets/css/login.css',
		array(),
		quietype_asset_version( 'assets/css/login.css' )
	);
}
add_action( 'login_enqueue_scripts', 'quietype_login_assets' );

/** Point the login wordmark back to the site instead of wordpress.org. */
function quietype_login_header_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'quietype_login_header_url' );

/** Use the site name as the accessible label for the login wordmark. */
function quietype_login_header_text() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'quietype_login_header_text' );

/** Load the dependency-free lightbox entry point as an ES module. */
function quietype_module_script( $tag, $handle, $src ) {
	if ( 'quietype-lightbox' !== $handle ) {
		return $tag;
	}
	return '<script type="module" src="' . esc_url( $src ) . '"></script>';
}
add_filter( 'script_loader_tag', 'quietype_module_script', 10, 3 );

/**
 * WP Editor.md registers its reading assets globally. Keep them on singular
 * content pages, but do not make archive and search pages pay that cost.
 */
function quietype_trim_editor_assets() {
	$features = quietype_content_features();
	$prism_styles = array( 'prism-theme-style', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'Prism' );
	foreach ( $prism_styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	// Quietype supplies a local Chinese copy action without ClipboardJS/CDN fallback.
	wp_dequeue_script( 'prism-plugin-copy-to-clipboard' );
	wp_dequeue_script( 'copy-clipboard' );
	$styles  = array( 'Emojify.js' );
	$scripts = array( 'copy-clipboard', 'prism-plugin-copy-to-clipboard', 'Emojify.js' );
	if ( ! is_singular() || ! $features['math'] ) {
		$styles[]  = 'Katex';
		$scripts[] = 'Katex';
	}
	if ( ! is_singular() || ! $features['code'] ) {
		$scripts = array_merge( $scripts, array( 'prism-core-js', 'prism-plugin-autoloader', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'prism-plugin-show-language', 'Prism' ) );
	}
	if ( ! is_singular() || ( ! $features['math'] && ! $features['code'] ) ) {
		$scripts[] = 'Front_Style';
	}
	if ( ! is_singular() || ! $features['mermaid'] ) {
		$scripts[] = 'Mermaid';
	}
	if ( ! is_singular() || ! $features['mindmap'] ) {
		$scripts[] = 'MindMap';
	}
	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}
	if ( ! is_singular() || ( ! $features['math'] && ! $features['code'] && ! $features['mermaid'] && ! $features['mindmap'] ) ) {
		wp_dequeue_script( 'jquery' );
		wp_dequeue_script( 'jquery-core' );
		wp_dequeue_script( 'jquery-migrate' );
	}
}
add_action( 'wp_enqueue_scripts', 'quietype_trim_editor_assets', 100 );

/**
 * Remove a plugin footer callback by class and method without depending on the
 * plugin's private object instance.
 */
function quietype_remove_object_action( $hook_name, $class_name, $method_name ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook_name ] ) || empty( $wp_filter[ $hook_name ]->callbacks ) ) {
		return;
	}
	foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'] ?? null;
			if ( is_array( $function ) && is_object( $function[0] ) && is_a( $function[0], $class_name ) && $method_name === $function[1] ) {
				remove_action( $hook_name, $function, $priority );
			}
		}
	}
}

/** Stop WP Editor.md from printing initializers whose libraries were removed. */
function quietype_trim_editor_footer_scripts() {
	$features = quietype_content_features();
	$footer_scripts = array(
		'math'     => array( 'EditormdApp\\KaTeX', 'katex_wp_footer_scripts' ),
		'mermaid'  => array( 'EditormdApp\\Mermaid', 'mermaid_wp_footer_script' ),
		'mindmap'  => array( 'EditormdApp\\MindMap', 'mindmap_wp_footer_script' ),
	);
	foreach ( $footer_scripts as $feature => $callback ) {
		if ( ! is_singular() || empty( $features[ $feature ] ) ) {
			quietype_remove_object_action( 'wp_print_footer_scripts', $callback[0], $callback[1] );
		}
	}
}
add_action( 'wp', 'quietype_trim_editor_footer_scripts', 20 );

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

/** WP Editor.md sets a session language cookie on every first front-end view. */
function quietype_remove_public_editor_language_cookie() {
	if ( is_admin() || is_user_logged_in() || headers_sent() ) {
		return;
	}
	$set_cookies = array_values( array_filter( headers_list(), function ( $header ) {
		return 0 === stripos( $header, 'Set-Cookie:' );
	} ) );
	if ( ! $set_cookies ) {
		return;
	}
	$filtered = array_values( array_filter( $set_cookies, function ( $header ) {
		return false === stripos( $header, 'Set-Cookie: wp-editormd-lang=' );
	} ) );
	if ( count( $filtered ) === count( $set_cookies ) ) {
		return;
	}
	header_remove( 'Set-Cookie' );
	foreach ( $filtered as $header ) {
		header( $header, false );
	}
}
add_action( 'send_headers', 'quietype_remove_public_editor_language_cookie', 100 );

function quietype_cleanup_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
}
add_action( 'init', 'quietype_cleanup_head' );

/** Route Gravatar images through a mainland-friendly endpoint. */
function quietype_avatar_url( $url ) {
	if ( ! is_string( $url ) || ! preg_match( '#^https?://[^/]*gravatar\.com/avatar/#i', $url ) ) {
		return $url;
	}
	$base_url = get_option( 'quietype_gravatar_base_url', 'https://gravatar.loli.net/avatar/' );
	$base_url = apply_filters( 'quietype_avatar_base_url', $base_url );
	if ( ! $base_url ) {
		return $url;
	}
	return preg_replace( '#^https?://[^/]*gravatar\.com/avatar/#i', trailingslashit( $base_url ), $url );
}
add_filter( 'get_avatar_url', 'quietype_avatar_url' );

function quietype_body_classes( $classes ) {
	if ( is_singular() ) {
		$classes[] = 'is-singular';
	}
	return $classes;
}
add_filter( 'body_class', 'quietype_body_classes' );

/** Return one of Quietype's small, consistent interface icons. */
function quietype_icon( $name ) {
	$icons = array(
		'search'  => '<circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4.2 4.2"></path>',
		'palette' => '<path d="M12 3.5a8.5 8.5 0 1 0 0 17h1.2a1.8 1.8 0 0 0 1.2-3.15 1.8 1.8 0 0 1 1.2-3.15H18a2.5 2.5 0 0 0 2.5-2.5A8.2 8.2 0 0 0 12 3.5Z"></path><circle cx="8" cy="9" r=".8"></circle><circle cx="12" cy="7" r=".8"></circle><circle cx="16" cy="9" r=".8"></circle>',
		'up'      => '<path d="m6 14 6-6 6 6"></path><path d="M12 8v11"></path>',
		'down'    => '<path d="m6 10 6 6 6-6"></path><path d="M12 5v11"></path>',
		'menu'    => '<path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path>',
		'link'    => '<path d="M9 17H7A5 5 0 0 1 7 7h3"></path><path d="M15 7h2a5 5 0 0 1 0 10h-3"></path><path d="M8 12h8"></path>',
		'eye'     => '<path d="M3.5 12s3-5 8.5-5 8.5 5 8.5 5-3 5-8.5 5-8.5-5-8.5-5Z"></path><circle cx="12" cy="12" r="2"></circle>',
		'github'  => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3.28-.36 6.72-1.61 6.72-7A5.4 5.4 0 0 0 20.22 4 5 5 0 0 0 20.08.5S18.9.14 16 1.84a13.38 13.38 0 0 0-7 0C6.1.14 4.92.5 4.92.5A5 5 0 0 0 4.78 4a5.4 5.4 0 0 0-1.5 3.75c0 5.42 3.44 6.67 6.72 7A4.8 4.8 0 0 0 9 18v4"></path><path d="M9 18c-3 .9-3-1.5-4-2"></path>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path>',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}
	return '<svg class="quietype-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
}

/** Format the persisted article view count. */
function quietype_post_views( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	return number_format_i18n( max( 0, (int) get_post_meta( $post_id, 'views', true ) ) );
}

/** Return published article, view, and approved-comment totals for archives. */
function quietype_archive_stats() {
	global $wpdb;

	$post_count    = (int) wp_count_posts( 'post' )->publish;
	$view_count    = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)), 0)
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'views'
		WHERE p.post_type = 'post' AND p.post_status = 'publish'"
	);
	$comment_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COUNT(*)
		FROM {$wpdb->comments} c
		INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
		WHERE c.comment_approved = '1' AND p.post_type = 'post' AND p.post_status = 'publish'"
	);

	return array(
		'posts'    => $post_count,
		'views'    => $view_count,
		'comments' => $comment_count,
	);
}

/** Custom home menu items in the imported database should follow each environment. */
function quietype_normalize_home_menu_link( $items ) {
	foreach ( $items as $item ) {
		if ( '首页' === trim( wp_strip_all_tags( $item->title ) ) ) {
			$item->url = home_url( '/' );
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'quietype_normalize_home_menu_link' );

function quietype_menu_fallback() {
	echo '<ul class="menu">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">首页</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/articlearchive/' ) ) . '">归档</a></li>';
	echo '<li><a href="' . esc_url( get_post_type_archive_link( 'book' ) ) . '">阅读</a></li>';
	echo '<li><a href="' . esc_url( get_post_type_archive_link( 'photo' ) ) . '">照片</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/links/' ) ) . '">友链</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">关于</a></li>';
	echo '</ul>';
}

/** Add an explicit mobile submenu control to top-level primary menu items. */
function quietype_primary_submenu_toggle( $item_output, $item, $depth, $args ) {
	if ( 0 !== (int) $depth || empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $item_output;
	}
	if ( ! in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		return $item_output;
	}
	$label = wp_strip_all_tags( $item->title );
	$item_output .= '<button class="submenu-toggle" type="button" aria-expanded="false" aria-label="' . esc_attr( '展开' . $label . '子菜单' ) . '"><span aria-hidden="true">＋</span></button>';
	return $item_output;
}
add_filter( 'walker_nav_menu_start_el', 'quietype_primary_submenu_toggle', 10, 4 );

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

function quietype_specific_category( $post_id = null ) {
	$categories = get_the_category( $post_id );
	if ( empty( $categories ) ) {
		return null;
	}
	usort(
		$categories,
		function ( $first, $second ) {
			$first_depth  = count( get_ancestors( $first->term_id, 'category', 'taxonomy' ) );
			$second_depth = count( get_ancestors( $second->term_id, 'category', 'taxonomy' ) );
			$depth_compare = $second_depth <=> $first_depth;
			return 0 !== $depth_compare ? $depth_compare : $second->term_id <=> $first->term_id;
		}
	);
	return $categories[0];
}

function quietype_primary_category( $post_id = null ) {
	$category = quietype_specific_category( $post_id );
	if ( ! $category ) {
		return '';
	}
	return '<a class="post-category" href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
}

function quietype_post_terms( $post_id = null, $limit = 3 ) {
	$links = array();
	$category = quietype_specific_category( $post_id );
	if ( $category ) {
		$links[] = '<a class="post-category" href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
	}
	$tags = get_the_tags( $post_id );
	if ( $tags ) {
		foreach ( $tags as $tag ) {
			if ( count( $links ) >= $limit ) {
				break;
			}
			$links[] = '<a class="post-tag" href="' . esc_url( get_tag_link( $tag ) ) . '"><span class="post-tag__mark" aria-hidden="true">#</span><span>' . esc_html( $tag->name ) . '</span></a>';
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
			$permalink_label = $label ? sprintf( '复制“%s”章节链接', $label ) : '复制章节链接';
			$permalink       = '<a class="heading-permalink" href="#' . esc_attr( $id ) . '" aria-label="' . esc_attr( $permalink_label ) . '" data-heading-permalink>' . quietype_icon( 'link' ) . '</a>';
			return '<h' . $level . $attrs . '>' . $matches[3] . $permalink . '</h' . $level . '>';
		},
		$content
	);
	$content = preg_replace(
		'/<pre(?![^>]*\bdata-prismjs-copy=)([^>]*)>/i',
		'<pre$1 data-prismjs-copy="复制" data-prismjs-copy-success="已复制" data-prismjs-copy-error="复制失败">',
		$content
	);
	$content = preg_replace_callback(
		'~<pre\b[^>]*>.*?</pre>(*SKIP)(*F)|<code\b([^>]*)>(.*?)</code>~isu',
		function ( $matches ) {
			$text = html_entity_decode( wp_strip_all_tags( $matches[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = esc_html( $text );
			$text = preg_replace_callback(
				'/[A-Za-z0-9_]{16,}/',
				function ( $token_match ) {
					$token = preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=_)/', '<wbr>', $token_match[0] );
					return $token === $token_match[0] ? preg_replace( '/(.{10})(?=.)/', '$1<wbr>', $token ) : $token;
				},
				$text
			);
			return '<code' . $matches[1] . '>' . $text . '</code>';
		},
		$content
	);
	$content = preg_replace_callback(
		'~<(pre|code|script|style|kbd|math|svg)\b[^>]*>.*?</\1>(*SKIP)(*F)|(?<=>)([^<]+)(?=<)~isu',
		function ( $matches ) {
			$text            = $matches[2];
			$has_visible_url = preg_match( '~(?:https?://|www\.)~i', $text );
			if ( $has_visible_url ) {
				$text = preg_replace_callback(
					'~(?:https?://|www\.)[^\s<]+~iu',
					function ( $url_match ) {
						return preg_replace_callback(
							'/[A-Za-z0-9]{16,}/',
							function ( $token_match ) {
								return preg_replace( '/(.{10})(?=.)/', '$1<wbr>', $token_match[0] );
							},
							$url_match[0]
						);
					},
					$text
				);
			}
			$text = preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=_)(?=[A-Za-z0-9])/', '<wbr>', $text );
			$hyphenated_text = preg_replace( '/(?<=[A-Za-z0-9])([\x{2010}-\x{2015}-])(?=[A-Za-z0-9])/u', '$1<wbr>', $text );
			if ( null !== $hyphenated_text ) {
				$text = $hyphenated_text;
			}
			$text = preg_replace( '/(?<=[A-Za-z0-9])\((?=[A-Za-z0-9])/', '(<wbr>', $text );
			if ( $has_visible_url ) {
				// Visible URLs should use the current line before wrapping at a path boundary.
				$text = preg_replace( '~(?<=/)(?=[^/\s<])|(?<=[?#=])(?=[^\s<])~u', '<wbr>', $text );
				$text = preg_replace( '~(?<=[A-Za-z0-9])\.(?=[A-Za-z0-9])~u', '.<wbr>', $text );
			}
			return $text;
		},
		$content
	);
	return array( 'content' => $content, 'items' => $items );
}

function quietype_pagination() {
	$links = paginate_links(
		array(
			'prev_next' => false,
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

/** Add a short-lived, one-time four-digit challenge and an invisible honeypot. */
function quietype_comment_captcha_field( $fields ) {
	if ( is_user_logged_in() ) {
		return $fields;
	}
	if ( isset( $fields['url'] ) ) {
		$fields['url'] = preg_replace( '/\btype=(["\'])url\1/i', 'type="text" inputmode="url"', $fields['url'] );
		$fields['url'] = str_replace( 'autocomplete="url"', 'autocomplete="url" placeholder="example.com 或 https://example.com"', $fields['url'] );
	}
	$challenge = (string) wp_rand( 1000, 9999 );
	$token     = wp_generate_uuid4();
	set_transient( 'quietype_comment_captcha_' . md5( $token ), $challenge, 10 * MINUTE_IN_SECONDS );

	$captcha  = '<p class="comment-form-captcha">';
	$captcha .= '<label for="quietype_comment_captcha">验证 <span class="comment-captcha-code">' . esc_html( $challenge ) . '</span> <span class="required" aria-hidden="true">*</span></label>';
	$captcha .= '<input id="quietype_comment_captcha" name="quietype_comment_captcha" type="text" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" placeholder="填写上方四位数字" required>';
	$captcha .= '<input name="quietype_comment_captcha_token" type="hidden" value="' . esc_attr( $token ) . '">';
	$captcha .= '<span class="quietype-comment-honeypot" aria-hidden="true"><label for="quietype_comment_company">公司</label><input id="quietype_comment_company" name="quietype_comment_company" type="text" value="" tabindex="-1" autocomplete="off"></span>';
	$captcha .= '</p>';

	$ordered = array();
	foreach ( $fields as $key => $field ) {
		$ordered[ $key ] = $field;
		if ( 'url' === $key ) {
			$ordered['quietype_captcha'] = $captcha;
		}
	}
	if ( ! isset( $ordered['quietype_captcha'] ) ) {
		$ordered['quietype_captcha'] = $captcha;
	}
	return $ordered;
}
add_filter( 'comment_form_default_fields', 'quietype_comment_captcha_field' );

/** Accept a bare public domain in the optional comment website field. */
function quietype_normalize_comment_author_url( $commentdata ) {
	$url = trim( (string) ( $commentdata['comment_author_url'] ?? '' ) );
	if ( '' === $url ) {
		return $commentdata;
	}
	if ( str_starts_with( $url, '//' ) ) {
		$url = 'https:' . $url;
	} elseif ( ! preg_match( '~^https?://~i', $url ) ) {
		$url = 'https://' . $url;
	}
	$url   = esc_url_raw( $url, array( 'http', 'https' ) );
	$parts = $url ? wp_parse_url( $url ) : false;
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
		wp_die( '网址格式不正确，请填写 example.com 或完整的 http(s) 地址。', '评论网址无效', array( 'response' => 400, 'back_link' => true ) );
	}
	$commentdata['comment_author_url'] = $url;
	return $commentdata;
}
add_filter( 'preprocess_comment', 'quietype_normalize_comment_author_url', 5 );

/** Validate and consume the public comment challenge before insertion. */
function quietype_validate_comment_captcha( $commentdata ) {
	if ( is_user_logged_in() || in_array( $commentdata['comment_type'] ?? '', array( 'pingback', 'trackback' ), true ) ) {
		return $commentdata;
	}
	$token   = isset( $_POST['quietype_comment_captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['quietype_comment_captcha_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$answer  = isset( $_POST['quietype_comment_captcha'] ) ? sanitize_text_field( wp_unslash( $_POST['quietype_comment_captcha'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$honeypot = isset( $_POST['quietype_comment_company'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['quietype_comment_company'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$key       = $token ? 'quietype_comment_captcha_' . md5( $token ) : '';
	$expected  = $key ? get_transient( $key ) : false;
	if ( $key ) {
		delete_transient( $key );
	}
	$valid = '' === $honeypot && false !== $expected && hash_equals( (string) $expected, trim( $answer ) );
	if ( ! $valid ) {
		wp_die( '验证数字不正确或已过期，请返回页面重新填写。', '评论验证失败', array( 'response' => 403, 'back_link' => true ) );
	}
	$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rate_key = 'quietype_comment_rate_' . md5( absint( $commentdata['comment_post_ID'] ?? 0 ) . '|' . $address . '|' . wp_salt( 'nonce' ) );
	if ( get_transient( $rate_key ) ) {
		wp_die( '评论提交得太快了，请稍候再试。', '评论提交过于频繁', array( 'response' => 429, 'back_link' => true ) );
	}
	set_transient( $rate_key, 1, 20 );
	return $commentdata;
}
add_filter( 'preprocess_comment', 'quietype_validate_comment_captcha' );

/** Keep public search focused on articles rather than utility pages. */
function quietype_search_posts_only( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		$query->set( 'post_type', 'post' );
	}
}
add_action( 'pre_get_posts', 'quietype_search_posts_only' );

/** Keep public REST responses from becoming an account-enumeration endpoint. */
function quietype_limit_public_user_rest_routes( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'quietype_limit_public_user_rest_routes' );

/** A single-author site does not need public account-shaped author archives. */
function quietype_disable_author_archives() {
	if ( is_author() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'quietype_disable_author_archives' );

function quietype_allowed_html( $tags ) {
	if ( isset( $tags['a'] ) ) {
		$tags['a']['data-footnote-backref'] = true;
	}
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'quietype_allowed_html' );

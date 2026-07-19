<?php
/**
 * Quietype theme functions.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QUIETYPE_VERSION', '0.5.1' );

require_once get_template_directory() . '/inc/login-security.php';

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
	$style_dependencies = array();
	if ( is_singular() ) {
		wp_enqueue_style( 'quietype-photoswipe', get_template_directory_uri() . '/assets/vendor/photoswipe/photoswipe.css', array(), '5.4.4' );
		$style_dependencies[] = 'quietype-photoswipe';
	}
	wp_enqueue_style( 'quietype', get_stylesheet_uri(), $style_dependencies, QUIETYPE_VERSION );
	wp_enqueue_script( 'quietype', get_template_directory_uri() . '/assets/js/theme.js', array(), QUIETYPE_VERSION, true );
	if ( is_singular() ) {
		wp_enqueue_script( 'quietype-lightbox', get_template_directory_uri() . '/assets/js/lightbox.js', array(), QUIETYPE_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'quietype_assets', 20 );

/** Give WordPress authentication screens the same quiet reading surface. */
function quietype_login_assets() {
	wp_enqueue_style(
		'quietype-login',
		get_template_directory_uri() . '/assets/css/login.css',
		array(),
		QUIETYPE_VERSION
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
	$prism_styles = array( 'prism-theme-style', 'prism-plugin-toolbar', 'prism-plugin-line-numbers', 'Prism' );
	foreach ( $prism_styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	// Quietype supplies a local Chinese copy action without ClipboardJS/CDN fallback.
	wp_dequeue_script( 'prism-plugin-copy-to-clipboard' );
	wp_dequeue_script( 'copy-clipboard' );
	if ( is_singular() ) {
		return;
	}
	$styles = array( 'Katex', 'Emojify.js' );
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

/** Route Gravatar images through a mainland-friendly endpoint. */
function quietype_avatar_url( $url ) {
	if ( ! is_string( $url ) || ! preg_match( '#^https?://[^/]*gravatar\.com/avatar/#i', $url ) ) {
		return $url;
	}
	$base_url = apply_filters( 'quietype_avatar_base_url', 'https://gravatar.loli.net/avatar/' );
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
		'eye'     => '<path d="M3.5 12s3-5 8.5-5 8.5 5 8.5 5-3 5-8.5 5-8.5-5-8.5-5Z"></path><circle cx="12" cy="12" r="2"></circle>',
		'github'  => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3.28-.36 6.72-1.61 6.72-7A5.4 5.4 0 0 0 20.22 4 5 5 0 0 0 20.08.5S18.9.14 16 1.84a13.38 13.38 0 0 0-7 0C6.1.14 4.92.5 4.92.5A5 5 0 0 0 4.78 4a5.4 5.4 0 0 0-1.5 3.75c0 5.42 3.44 6.67 6.72 7A4.8 4.8 0 0 0 9 18v4"></path><path d="M9 18c-3 .9-3-1.5-4-2"></path>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path>',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}
	return '<svg class="quietype-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
}

/** Footer contact links belong to the active theme and live in the Customizer. */
function quietype_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'quietype_footer',
		array(
			'title'       => '页脚联系方式',
			'description' => '留空即可隐藏对应图标。',
			'priority'    => 165,
		)
	);
	$wp_customize->add_setting(
		'quietype_github_url',
		array(
			'default'           => 'https://github.com/taifuer',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'quietype_github_url',
		array(
			'label'   => 'GitHub 地址',
			'section' => 'quietype_footer',
			'type'    => 'url',
		)
	);
	$wp_customize->add_setting(
		'quietype_contact_email',
		array(
			'default'           => 'taifu@taifua.com',
			'sanitize_callback' => 'sanitize_email',
		)
	);
	$wp_customize->add_control(
		'quietype_contact_email',
		array(
			'label'   => '联系邮箱',
			'section' => 'quietype_footer',
			'type'    => 'email',
		)
	);

	$wp_customize->add_section(
		'quietype_login_security',
		array(
			'title'       => '登录与安全',
			'description' => '启用前请先保存完整的自定义登录地址。参数名和值都匹配时才显示登录页。',
			'priority'    => 166,
		)
	);
	$wp_customize->add_setting(
		'quietype_login_gate_enabled',
		array(
			'default'           => false,
			'sanitize_callback' => 'quietype_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'quietype_login_gate_enabled',
		array(
			'label'   => '启用自定义登录入口',
			'section' => 'quietype_login_security',
			'type'    => 'checkbox',
		)
	);
	$wp_customize->add_setting(
		'quietype_login_gate_key',
		array(
			'default'           => 'user',
			'sanitize_callback' => 'quietype_sanitize_login_gate_key',
		)
	);
	$wp_customize->add_control(
		'quietype_login_gate_key',
		array(
			'label'       => '入口参数名',
			'description' => '仅支持字母、数字、下划线和短横线，例如 user。',
			'section'     => 'quietype_login_security',
			'type'        => 'text',
		)
	);
	$wp_customize->add_setting(
		'quietype_login_gate_value',
		array(
			'default'           => '',
			'sanitize_callback' => 'quietype_sanitize_login_gate_value',
		)
	);
	$wp_customize->add_control(
		'quietype_login_gate_value',
		array(
			'label'       => '入口参数值',
			'description' => '建议使用不易猜测的字符串。留空时不会隐藏默认入口。',
			'section'     => 'quietype_login_security',
			'type'        => 'text',
		)
	);
	$wp_customize->add_setting(
		'quietype_login_captcha_enabled',
		array(
			'default'           => false,
			'sanitize_callback' => 'quietype_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'quietype_login_captcha_enabled',
		array(
			'label'   => '启用一次性算术验证码',
			'section' => 'quietype_login_security',
			'type'    => 'checkbox',
		)
	);
	$wp_customize->add_setting(
		'quietype_xmlrpc_auth_disabled',
		array(
			'default'           => false,
			'sanitize_callback' => 'quietype_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'quietype_xmlrpc_auth_disabled',
		array(
			'label'       => '禁用 XML-RPC 认证与 Pingback',
			'description' => '可阻止绕过网页验证码的旧式登录请求；启用后 WordPress App 等 XML-RPC 客户端将不可用。',
			'section'     => 'quietype_login_security',
			'type'        => 'checkbox',
		)
	);
}
add_action( 'customize_register', 'quietype_customize_register' );

/** Format the WP-PostViews value without depending on the plugin's display template. */
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
			return '<h' . $level . $attrs . '>' . $matches[3] . '</h' . $level . '>';
		},
		$content
	);
	$content = preg_replace(
		'/<pre(?![^>]*\bdata-prismjs-copy=)([^>]*)>/i',
		'<pre$1 data-prismjs-copy="复制" data-prismjs-copy-success="已复制" data-prismjs-copy-error="复制失败">',
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

function quietype_allowed_html( $tags ) {
	if ( isset( $tags['a'] ) ) {
		$tags['a']['data-footnote-backref'] = true;
	}
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'quietype_allowed_html' );

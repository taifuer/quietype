<?php
/** Opt in only the disposable browser-test site; never operate on a live site. */
define( 'WP_USE_THEMES', false );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'QUIETYPE_TEST_PORT' ) ?: '8888' );
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) || ! getenv( 'QUIETYPE_TEST_PORT' ) ) {
	exit( "Refusing to seed outside the disposable test site.\n" );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( 'deactivate' === ( $argv[1] ?? '' ) ) {
	deactivate_plugins( 'wp-editormd/wp-editormd.php', true );
	exit( "Editor.md deactivated in the disposable test site.\n" );
}
if ( class_exists( 'EditormdApp\\WPComMarkdown' ) ) {
	exit( "Start a fresh request after deactivating Editor.md; use npm run env:markdown.\n" );
}
update_option( 'quietype_markdown_enabled', true );
foreach ( array( 'markdown-desktop', 'markdown-mobile', 'video-desktop', 'video-mobile' ) as $login ) {
	$user = get_user_by( 'login', $login );
	$id = $user ? $user->ID : wp_create_user( $login, 'password', $login . '@example.test' );
	( new WP_User( $id ) )->set_role( 'administrator' );
}
$source = file_get_contents( __DIR__ . '/../fixtures/markdown.md' );
$html = quietype_markdown_render( $source );
$existing = get_page_by_path( 'quietype-markdown-test', OBJECT, 'post' );
$id = wp_insert_post( wp_slash( array(
	'ID' => $existing ? $existing->ID : 0,
	'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Markdown：从原文到阅读',
	'post_name' => 'quietype-markdown-test', 'post_content' => $html, 'post_content_filtered' => $source,
	'post_date' => '2015-01-01 10:00:00',
	'meta_input' => array( '_quietype_markdown_html_hash' => hash( 'sha256', $html ), '_wpcom_is_markdown' => true ),
) ) );
echo "Optional Markdown fixture ready: /quietype-markdown-test/ ({$id})\n";

foreach ( array( 'quietype-legacy-math-test' => file_get_contents( __DIR__ . '/../fixtures/legacy-math.html' ), 'quietype-shell-only-test' => '<p>代码示例 <code>$x$</code></p><pre><code class="language-bash">echo "$HOME" "$USER" "$$"</code></pre>' ) as $slug => $content ) {
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	wp_insert_post( wp_slash( array(
		'ID' => $existing ? $existing->ID : 0,
		'post_type' => 'post', 'post_status' => 'publish',
		'post_title' => '旧文章兼容：公式与 Shell', 'post_name' => $slug,
		'post_content' => $content, 'post_date' => '2015-01-01 10:00:00',
		'meta_input' => array( '_wpcom_is_markdown' => true ),
	) ) );
}

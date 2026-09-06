<?php
/** Seed real Editor.md output in the disposable integration environment only. */
define( 'WP_USE_THEMES', false );
define( 'DISABLE_WP_CRON', true );
define( 'FS_METHOD', 'direct' );
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'QUIETYPE_TEST_PORT' ) ?: '8888' );
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
	exit( "Refusing to seed a non-local site.\n" );
}
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
if ( ! is_file( WP_PLUGIN_DIR . '/wp-editormd/wp-editormd.php' ) ) {
	WP_Filesystem();
	$result = unzip_file( '/tmp/quietype-editormd.zip', WP_PLUGIN_DIR );
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, $result->get_error_message() );
		exit( 1 );
	}
}
$result = activate_plugin( 'wp-editormd/wp-editormd.php', '', false, true );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() );
	exit( 1 );
}
// Deliberately enable the old renderer: the theme must prevent duplicate runs.
update_option( 'editor_mermaid', array( 'support_mermaid' => 'on', 'mermaid_config' => '{"startOnLoad":true,"theme":"dark"}' ) );
update_option( 'editor_latex', array( 'support_latex' => 'katex' ) );
update_option( 'editor_style', array( 'editor_addres' => plugins_url( 'wp-editormd' ) ) );
update_option( 'editor_advanced', array( 'jquery_compatible' => 'on' ) );
update_option( 'syntax_highlighting', array( 'highlight_mode_auto' => 'on', 'line_numbers' => 'on', 'show_language' => 'on' ) );
update_option( 'quietype_mermaid_enabled', true );

require_once WP_PLUGIN_DIR . '/wp-editormd/vendor/autoload.php';
$parser = new EditormdApp\WPMarkdownParser();
$source = file_get_contents( __DIR__ . '/../fixtures/reading.md' );
for ( $index = 1; $index <= 30; ++$index ) {
	$source .= "\n\n## {$index}、事件回调与服务端状态同步：一段需要完整保留语义的长章节标题\n\n";
	$source .= str_repeat( "技术文章中的目录应当帮助读者定位章节，长标题可以省略显示，但原始文字始终可以查看。\n\n", 3 );
}
// The pinned legacy parser passes null to preg_split's limit on PHP 8.1+.
// Keep its known deprecations out of the fixture output; runtime errors stay on.
$reporting = error_reporting( E_ALL & ~E_DEPRECATED );
$content = $parser->transform( $source );
$existing = get_page_by_path( 'quietype-plugin-reading-test', OBJECT, 'post' );
$post_id = wp_insert_post( wp_slash( array(
	'ID' => $existing ? $existing->ID : 0,
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => '技术长文：图表、目录与阅读细节',
	'post_name' => 'quietype-plugin-reading-test',
	'post_content' => $content,
	'post_content_filtered' => $source,
	'post_date' => '2016-01-01 10:00:00',
) ) );
error_reporting( $reporting );
if ( is_wp_error( $post_id ) || ! $post_id ) {
	exit( "Could not create plugin reading fixture.\n" );
}
echo "Editor.md integration article created: /quietype-plugin-reading-test/\n";

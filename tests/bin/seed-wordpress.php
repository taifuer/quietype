<?php
/**
 * Create deterministic content in the disposable regression environment.
 */

define( 'WP_USE_THEMES', false );
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']       = 'localhost:' . ( getenv( 'QUIETYPE_TEST_PORT' ) ?: '8888' );
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require '/var/www/html/wp-load.php';

if ( ! is_blog_installed() ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	wp_install( '太傅博客', 'admin', 'admin@example.test', true, '', 'password' );
}

wp_set_current_user( 1 );
switch_theme( 'quietype' );

$existing_posts = get_posts(
	array(
		'post_type'      => 'any',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $existing_posts as $existing_post_id ) {
	wp_delete_post( $existing_post_id, true );
}

foreach ( get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) ) as $existing_term ) {
	if ( 'uncategorized' !== $existing_term->slug ) {
		wp_delete_term( $existing_term->term_id, $existing_term->taxonomy );
	}
}

$practice = wp_insert_term( '实践', 'category', array( 'slug' => 'practice' ) );
$notes    = wp_insert_term( '笔迹', 'category', array( 'slug' => 'notes' ) );
wp_insert_term( 'Agent', 'post_tag', array( 'slug' => 'agent' ) );
wp_insert_term( 'WordPress', 'post_tag', array( 'slug' => 'wordpress' ) );

$article_content = file_get_contents( get_template_directory() . '/tests/fixtures/article.html' );
$article_id      = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Quietype 阅读体验回归测试',
		'post_name'    => 'quietype-reading-test',
		'post_content' => $article_content,
		'post_date'    => '2026-06-16 09:30:00',
	)
);
wp_set_post_terms( $article_id, array( $practice['term_id'] ), 'category' );
wp_set_post_terms( $article_id, array( 'agent', 'wordpress' ), 'post_tag', false );
update_post_meta( $article_id, 'views', 42 );

for ( $post_number = 1; $post_number <= 5; $post_number++ ) {
	$test_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => '安静写作示例 ' . $post_number,
			'post_name'    => 'quiet-writing-' . $post_number,
			'post_excerpt' => '用于检查首页文章列表在不同屏幕宽度下的标题、摘要和元信息排版。',
			'post_content' => '<p>用于首页、归档和分页回归的示例文章。</p>',
			'post_date'    => sprintf( '2026-05-%02d 10:00:00', $post_number ),
		)
	);
	wp_set_post_terms( $test_post_id, array( $notes['term_id'] ), 'category' );
	wp_set_post_terms( $test_post_id, array( 'wordpress' ), 'post_tag', false );
	update_post_meta( $test_post_id, 'views', $post_number * 7 );
}

$about_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => '关于',
		'post_name'    => 'about',
		'post_content' => '<h2>关于本站</h2><p>这是用于自动化回归的关于页面。</p><hr><p>保持写作，也保持阅读。</p>',
	)
);
$archive_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => '文章归档',
		'post_name'   => 'archive',
	)
);
$links_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => '友情链接',
		'post_name'   => 'links',
	)
);
update_post_meta( $archive_id, '_wp_page_template', 'template-archives.php' );
update_post_meta( $links_id, '_wp_page_template', 'template-links.php' );

foreach ( wp_get_nav_menus() as $existing_menu ) {
	wp_delete_nav_menu( $existing_menu->term_id );
}
$menu_id = wp_create_nav_menu( '主导航' );
wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => '首页', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => '实践', 'menu-item-object' => 'category', 'menu-item-object-id' => $practice['term_id'], 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) );
foreach ( array( $archive_id, $links_id, $about_id ) as $page_id ) {
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => get_the_title( $page_id ), 'menu-item-object' => 'page', 'menu-item-object-id' => $page_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
}
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu_id ) );

wp_insert_comment(
	array(
		'comment_post_ID'      => $article_id,
		'comment_author'       => '测试读者',
		'comment_author_email' => 'reader@example.com',
		'comment_content'      => '正文排版清晰，评论区域也应保持一致。',
		'comment_approved'     => 1,
	)
);

update_option( 'blogname', '太傅博客' );
update_option( 'blogdescription', '记录学习与生活' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'Asia/Shanghai' );
flush_rewrite_rules();

printf( "Quietype deterministic content created.\n" );

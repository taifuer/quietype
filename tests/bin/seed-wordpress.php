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
wp_installing( false );

wp_set_current_user( 1 );
switch_theme( 'quietype' );
update_option( 'quietype_photo_thumbnail_base_url', 'https://images.example.test/photos', false );
if ( ! function_exists( 'quietype_register_books' ) ) {
	require_once WP_CONTENT_DIR . '/themes/quietype/functions.php';
}
if ( ! post_type_exists( 'book' ) ) {
	quietype_register_books();
}
if ( ! post_type_exists( 'photo' ) ) {
	quietype_register_photos();
}

$existing_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'page', 'book', 'photo', 'attachment' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $existing_posts as $existing_post_id ) {
	wp_delete_post( $existing_post_id, true );
}


foreach ( array( 'category', 'post_tag', 'book_category', 'book_tag' ) as $taxonomy ) {
	foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $existing_term ) {
		$term_slug = is_object( $existing_term ) ? $existing_term->slug : ( $existing_term['slug'] ?? '' );
		$term_id   = is_object( $existing_term ) ? $existing_term->term_id : ( $existing_term['term_id'] ?? 0 );
		if ( $term_id && 'uncategorized' !== $term_slug ) {
			wp_delete_term( $term_id, $taxonomy );
		}
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

$book_categories = array(
	'technology' => wp_insert_term( '技术与工程', 'book_category', array( 'slug' => 'technology' ) ),
	'humanities' => wp_insert_term( '人文与社会', 'book_category', array( 'slug' => 'humanities' ) ),
	'design'     => wp_insert_term( '设计', 'book_category', array( 'slug' => 'design' ) ),
	'fiction'    => wp_insert_term( '文学', 'book_category', array( 'slug' => 'fiction' ) ),
);
$book_tags = array();
foreach ( array( '算法', '社会', '重读', '经典', '排版', '科幻', '成长', '制度' ) as $tag_name ) {
	$term = wp_insert_term( $tag_name, 'book_tag' );
	$book_tags[ $tag_name ] = $term['term_id'];
}
$books = array(
	array(
		'title' => '编程珠玑', 'slug' => 'programming-pearls', 'authors' => 'Jon Bentley', 'publisher' => '人民邮电出版社', 'year' => '2015', 'read_date' => '2026-06-02', 'status' => 'read', 'rating' => '5', 'douban' => '9.1', 'douban_id' => '3227098', 'book_url' => 'https://example.com/books/programming-pearls', 'category' => 'technology', 'tags' => array( '算法', '经典' ),
		'excerpt' => '真正耐看的不是技巧本身，而是如何把含混的问题压缩成可以验证的结构。',
	),
	array(
		'title' => '置身事内', 'slug' => 'in-the-system', 'authors' => '兰小欢', 'publisher' => '上海人民出版社', 'year' => '2021', 'read_date' => '2026-05-18', 'status' => 'reading', 'rating' => '4', 'douban' => '9.0', 'douban_id' => '35546622', 'category' => 'humanities', 'tags' => array( '社会', '制度' ),
		'excerpt' => '从地方政府与经济运行的关系进入现实，材料清楚，解释也足够克制。',
	),
	array(
		'title' => '红楼梦', 'slug' => 'dream-of-the-red-chamber', 'authors' => '曹雪芹', 'publisher' => '人民文学出版社', 'year' => '1996', 'read_date' => '2026-03-12', 'status' => 'planned', 'rating' => '5', 'douban' => '9.7', 'douban_id' => '1007305', 'category' => 'fiction', 'tags' => array( '经典', '重读' ),
		'excerpt' => '人物从来不只是人物，一座园子也不只是园子。隔几年重读，注意到的总是不同。',
	),
	array(
		'title' => '设计中的设计', 'slug' => 'designing-design', 'authors' => '原研哉', 'publisher' => '广西师范大学出版社', 'year' => '2010', 'read_date' => '2025-12-01', 'status' => 'partial', 'rating' => '4', 'douban' => '8.6', 'douban_id' => '1941558', 'category' => 'design', 'tags' => array( '排版', '经典' ),
		'excerpt' => '设计不是增加装饰，而是重新发现事物原本可以被感知和使用的方式。',
	),
	array(
		'title' => '活着', 'slug' => 'to-live', 'authors' => '余华', 'publisher' => '作家出版社', 'year' => '2012', 'read_date' => '2025-09-18', 'status' => 'read', 'rating' => '4', 'douban' => '9.4', 'douban_id' => '4913064', 'category' => 'fiction', 'tags' => array( '经典', '社会' ),
		'excerpt' => '语言越平静，命运越显得沉重。读完不会立刻想说什么，只想缓一会儿。',
	),
	array(
		'title' => '1984', 'slug' => 'nineteen-eighty-four', 'authors' => '乔治·奥威尔', 'publisher' => '北京十月文艺出版社', 'year' => '2010', 'read_date' => '2025-05-22', 'status' => 'read', 'rating' => '4', 'douban' => '9.4', 'douban_id' => '4820710', 'category' => 'fiction', 'tags' => array( '社会', '经典' ),
		'excerpt' => '真正令人不安的不是监视，而是语言、记忆与常识被一点点改写。',
	),
	array(
		'title' => '三体全集', 'slug' => 'three-body-problem', 'authors' => '刘慈欣', 'publisher' => '重庆出版社', 'year' => '2012', 'read_date' => '2024-11-08', 'status' => 'read', 'rating' => '5', 'douban' => '9.5', 'douban_id' => '6518605', 'category' => 'fiction', 'tags' => array( '科幻', '社会' ),
		'excerpt' => '宏大设定之外，最耐看的还是人在极端尺度与漫长时间面前的选择。',
	),
	array(
		'title' => '小王子', 'slug' => 'the-little-prince', 'authors' => '圣-埃克苏佩里', 'publisher' => '人民文学出版社', 'year' => '2003', 'read_date' => '2023-04-19', 'status' => 'read', 'rating' => '4', 'douban' => '9.1', 'douban_id' => '1084336', 'book_url' => '', 'category' => 'fiction', 'tags' => array( '成长', '重读' ),
		'excerpt' => '小时候读故事，后来读关系。简单的句子里藏着成年人容易忘记的事。',
	),
);
$book_ids = array();
foreach ( $books as $book ) {
	$book_id = wp_insert_post(
		array(
			'post_type'    => 'book',
			'post_status'  => 'publish',
			'post_title'   => $book['title'],
			'post_name'    => $book['slug'],
			'post_excerpt' => $book['excerpt'],
			'post_date'    => '2026-07-01 10:00:00',
		)
	);
	wp_set_post_terms( $book_id, array( $book_categories[ $book['category'] ]['term_id'] ), 'book_category' );
	wp_set_post_terms( $book_id, array_map( function ( $tag ) use ( $book_tags ) { return $book_tags[ $tag ]; }, $book['tags'] ), 'book_tag' );
	foreach ( array(
		'_quietype_book_authors' => $book['authors'], '_quietype_book_publisher' => $book['publisher'], '_quietype_book_publication_year' => $book['year'], '_quietype_book_read_date' => $book['read_date'], '_quietype_book_status' => $book['status'], '_quietype_book_rating' => $book['rating'], '_quietype_book_douban_rating' => $book['douban'], '_quietype_book_douban_id' => $book['douban_id'], '_quietype_book_douban_url' => array_key_exists( 'book_url', $book ) ? $book['book_url'] : quietype_douban_url( $book['douban_id'] ),
	) as $meta_key => $meta_value ) {
		if ( '' !== $meta_value ) {
			update_post_meta( $book_id, $meta_key, $meta_value );
		}
	}
	$book_ids[] = $book_id;
}

// Give the final fixture a real featured image so the dedicated cover derivative is exercised.
if ( $book_ids && function_exists( 'imagecreatetruecolor' ) ) {
	$cover = imagecreatetruecolor( 504, 744 );
	imagefilledrectangle( $cover, 0, 0, 503, 743, imagecolorallocate( $cover, 239, 232, 216 ) );
	imagefilledrectangle( $cover, 42, 42, 461, 701, imagecolorallocate( $cover, 121, 88, 68 ) );
	imagefilledrectangle( $cover, 48, 48, 455, 695, imagecolorallocate( $cover, 250, 249, 246 ) );
	ob_start();
	imagejpeg( $cover, null, 88 );
	$cover_bytes = ob_get_clean();
	imagedestroy( $cover );
	$cover_upload = wp_upload_bits( 'quietype-test-book-cover.jpg', null, $cover_bytes );
	if ( empty( $cover_upload['error'] ) ) {
		add_image_size( 'quietype-book-cover', 252, 372, false );
		$cover_attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
				'post_title'     => 'Quietype test book cover',
			),
			$cover_upload['file'],
			end( $book_ids )
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $cover_attachment_id, wp_generate_attachment_metadata( $cover_attachment_id, $cover_upload['file'] ) );
		set_post_thumbnail( end( $book_ids ), $cover_attachment_id );
	}
}

$photos = array(
	array( '雨后屋檐', '2026-07', '安徽 · 宏村', 1800, 1200, '35mm', 'f/4', '1/320s', '160' ),
	array( '窗外', '2026-05', '京广线', 1200, 1500, '50mm', 'f/2.8', '1/500s', '200' ),
	array( '水田', '2026-04', '云南 · 元阳', 1600, 1067, '28mm', 'f/5.6', '1/250s', '100' ),
	array( '暮色归舟', '2025-11', '湖南 · 洞庭湖', 1800, 1125, '85mm', 'f/4', '1/400s', '320' ),
	array( '雾林', '2025-09', '江西 · 武功山', 1200, 1500, '35mm', 'f/2.8', '1/160s', '400' ),
	array( '林间公路', '2024-10', '川西', 1800, 1200, '24mm', 'f/8', '1/125s', '100' ),
);
foreach ( $photos as $index => $photo ) {
	$photo_id = wp_insert_post(
		array(
			'post_type'    => 'photo',
			'post_status'  => 'publish',
			'post_title'   => $photo[0],
			'post_name'    => 'quietype-photo-' . ( $index + 1 ),
			'post_excerpt' => '在路上偶然停下的一刻。',
			'post_date'    => '2026-07-01 10:00:00',
		)
	);
	foreach ( array(
		'_quietype_photo_image_url'     => 'https://images.example.test/photos/' . substr( $photo[1], 0, 4 ) . '/photo-' . ( $index + 1 ) . '.jpg',
		'_quietype_photo_original_url'  => 0 === $index ? 'https://images.example.test/photo-1-original.jpg' : '',
		'_quietype_photo_captured_date' => $photo[1],
		'_quietype_photo_location'      => $photo[2],
		'_quietype_photo_width'         => $photo[3],
		'_quietype_photo_height'        => $photo[4],
		'_quietype_photo_focal_length'  => $photo[5],
		'_quietype_photo_aperture'      => $photo[6],
		'_quietype_photo_shutter_speed' => $photo[7],
		'_quietype_photo_iso'           => $photo[8],
		'_quietype_photo_camera'        => 0 === $index ? 'Xiaomi 14 Pro' : '',
	) as $meta_key => $meta_value ) {
		if ( '' !== $meta_value ) {
			update_post_meta( $photo_id, $meta_key, $meta_value );
		}
	}
}

$quietype_test_menus = array();
foreach ( wp_get_nav_menus() as $existing_menu ) {
	if ( in_array( $existing_menu->name, array( '主导航', '页尾导航' ), true ) ) {
		$quietype_test_menus[ $existing_menu->name ] = (int) $existing_menu->term_id;
		foreach ( wp_get_nav_menu_items( $existing_menu->term_id ) ?: array() as $existing_menu_item ) {
			wp_delete_post( $existing_menu_item->ID, true );
		}
	} else {
		wp_delete_nav_menu( $existing_menu->term_id );
	}
}
$quietype_primary_menu_id = $quietype_test_menus['主导航'] ?? wp_create_nav_menu( '主导航' );
wp_update_nav_menu_item( $quietype_primary_menu_id, 0, array( 'menu-item-title' => '首页', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
wp_update_nav_menu_item( $quietype_primary_menu_id, 0, array( 'menu-item-title' => '实践', 'menu-item-object' => 'category', 'menu-item-object-id' => $practice['term_id'], 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) );
foreach ( array( $archive_id, $links_id, $about_id ) as $page_id ) {
	wp_update_nav_menu_item( $quietype_primary_menu_id, 0, array( 'menu-item-title' => get_the_title( $page_id ), 'menu-item-object' => 'page', 'menu-item-object-id' => $page_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
}
wp_update_nav_menu_item( $quietype_primary_menu_id, 0, array( 'menu-item-title' => '阅读', 'menu-item-url' => get_post_type_archive_link( 'book' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
$quietype_prefooter_menu_id = $quietype_test_menus['页尾导航'] ?? wp_create_nav_menu( '页尾导航' );
wp_update_nav_menu_item( $quietype_prefooter_menu_id, 0, array( 'menu-item-title' => '书籍', 'menu-item-url' => get_post_type_archive_link( 'book' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
foreach ( array( '归档' => $archive_id, '友链' => $links_id, '关于' => $about_id ) as $menu_title => $page_id ) {
	wp_update_nav_menu_item( $quietype_prefooter_menu_id, 0, array( 'menu-item-title' => $menu_title, 'menu-item-url' => get_permalink( $page_id ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
}
$quietype_theme_mods                       = get_option( 'theme_mods_quietype', array() );
$quietype_theme_mods['nav_menu_locations'] = array(
	'primary'   => (int) $quietype_primary_menu_id,
	'prefooter' => (int) $quietype_prefooter_menu_id,
);
update_option( 'theme_mods_quietype', $quietype_theme_mods );

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

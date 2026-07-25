<?php
/**
 * Lightweight annual reading archive and Douban-assisted book entry.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register books plus administrator-defined categories and tags. */
function quietype_register_books() {
	register_post_type(
		'book',
		array(
			'labels'              => array(
				'name'               => '书籍',
				'singular_name'      => '书籍',
				'add_new'            => '添加书籍',
				'add_new_item'       => '添加书籍',
				'edit_item'          => '编辑书籍',
				'new_item'           => '新书籍',
				'view_item'          => '查看豆瓣条目',
				'search_items'       => '搜索书籍',
				'not_found'          => '没有找到书籍',
				'not_found_in_trash' => '回收站中没有书籍',
				'all_items'          => '全部书籍',
				'menu_name'          => '阅读',
			),
			'public'              => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'has_archive'         => 'reading',
			'rewrite'             => array(
				'slug'       => 'reading',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-book-alt',
			'menu_position'       => 6,
			'supports'            => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_nav_menus'   => true,
		)
	);

	$taxonomies = array(
		'book_category' => array(
			'hierarchical' => true,
			'labels'       => array(
				'name'          => '阅读分类',
				'singular_name' => '阅读分类',
				'search_items'  => '搜索阅读分类',
				'all_items'     => '全部阅读分类',
				'edit_item'     => '编辑阅读分类',
				'update_item'   => '更新阅读分类',
				'add_new_item'  => '添加阅读分类',
				'new_item_name' => '新阅读分类名称',
				'menu_name'     => '阅读分类',
			),
		),
		'book_tag'      => array(
			'hierarchical' => false,
			'labels'       => array(
				'name'                       => '阅读标签',
				'singular_name'              => '阅读标签',
				'search_items'               => '搜索阅读标签',
				'popular_items'              => '常用阅读标签',
				'all_items'                  => '全部阅读标签',
				'edit_item'                  => '编辑阅读标签',
				'update_item'                => '更新阅读标签',
				'add_new_item'               => '添加阅读标签',
				'new_item_name'              => '新阅读标签名称',
				'separate_items_with_commas' => '使用逗号分隔多个标签',
				'add_or_remove_items'        => '添加或删除阅读标签',
				'choose_from_most_used'      => '从常用阅读标签中选择',
				'menu_name'                  => '阅读标签',
			),
		),
	);

	foreach ( $taxonomies as $taxonomy => $args ) {
		register_taxonomy(
			$taxonomy,
			'book',
			array_merge(
				$args,
				array(
					'public'            => false,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'query_var'         => false,
					'rewrite'           => false,
				)
			)
		);
	}

	$meta_fields = array(
		'_quietype_book_authors'          => 'sanitize_text_field',
		'_quietype_book_publisher'        => 'sanitize_text_field',
		'_quietype_book_publication_year' => 'quietype_sanitize_book_year',
		'_quietype_book_isbn'             => 'quietype_sanitize_book_isbn',
		'_quietype_book_read_date'        => 'quietype_sanitize_book_date',
		'_quietype_book_rating'           => 'quietype_sanitize_book_rating',
		'_quietype_book_douban_rating'    => 'quietype_sanitize_douban_rating',
		'_quietype_book_douban_url'       => 'quietype_sanitize_douban_url',
		'_quietype_book_douban_id'        => 'quietype_sanitize_douban_id',
	);
	foreach ( $meta_fields as $key => $sanitize_callback ) {
		register_post_meta(
			'book',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize_callback,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'quietype_register_books' );

/** Migrate the early reading prototype without losing entered records. */
function quietype_upgrade_books() {
	if ( '2' === get_option( 'quietype_book_data_version' ) ) {
		return;
	}
	$book_ids = get_posts(
		array(
			'post_type'      => 'book',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $book_ids as $book_id ) {
		if ( ! get_post_meta( $book_id, '_quietype_book_read_date', true ) ) {
			$read_date = get_post_meta( $book_id, '_quietype_book_finished_date', true );
			$read_date = $read_date ?: get_post_meta( $book_id, '_quietype_book_started_date', true );
			$read_date = $read_date ?: get_post_meta( $book_id, '_quietype_book_activity_date', true );
			$read_date = quietype_sanitize_book_date( $read_date );
			if ( ! $read_date ) {
				$read_date = get_the_date( 'Y-m-d', $book_id );
			}
			update_post_meta( $book_id, '_quietype_book_read_date', $read_date );
		}
		$rating = quietype_sanitize_book_rating( get_post_meta( $book_id, '_quietype_book_rating', true ) );
		if ( $rating ) {
			update_post_meta( $book_id, '_quietype_book_rating', $rating );
		}
		$douban_id = quietype_sanitize_douban_id( get_post_meta( $book_id, '_quietype_book_douban_url', true ) );
		if ( $douban_id ) {
			update_post_meta( $book_id, '_quietype_book_douban_id', $douban_id );
			update_post_meta( $book_id, '_quietype_book_douban_url', quietype_douban_url( $douban_id ) );
		}
	}
	flush_rewrite_rules( false );
	update_option( 'quietype_book_data_version', '2', false );
}
add_action( 'init', 'quietype_upgrade_books', 30 );

function quietype_sanitize_book_year( $value ) {
	$year = absint( $value );
	return $year >= 1000 && $year <= (int) gmdate( 'Y' ) + 2 ? (string) $year : '';
}

function quietype_sanitize_book_date( $value ) {
	$value = sanitize_text_field( $value );
	$date  = DateTime::createFromFormat( '!Y-m-d', $value );
	return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
}

function quietype_sanitize_book_rating( $value ) {
	$rating = (int) round( (float) $value );
	return $rating >= 1 && $rating <= 5 ? (string) $rating : '';
}

function quietype_sanitize_douban_rating( $value ) {
	$rating = round( (float) $value, 1 );
	return $rating > 0 && $rating <= 10 ? number_format( $rating, 1, '.', '' ) : '';
}

/** Accept either a Douban subject ID or its canonical book URL. */
function quietype_sanitize_douban_id( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( preg_match( '/^[1-9][0-9]{4,11}$/', $value ) ) {
		return $value;
	}
	$url  = esc_url_raw( $value, array( 'https' ) );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( 'book.douban.com' === $host && preg_match( '#^/subject/([1-9][0-9]{4,11})/?$#', $path, $matches ) ) {
		return $matches[1];
	}
	return '';
}

function quietype_douban_url( $subject_id ) {
	$subject_id = quietype_sanitize_douban_id( $subject_id );
	return $subject_id ? 'https://book.douban.com/subject/' . $subject_id . '/' : '';
}

function quietype_sanitize_douban_url( $value ) {
	return quietype_douban_url( $value );
}

/** Normalize ISBN-10 or ISBN-13 while retaining a manual fallback. */
function quietype_sanitize_book_isbn( $value ) {
	$isbn = strtoupper( preg_replace( '/[^0-9X]/i', '', (string) $value ) );
	return preg_match( '/^(?:[0-9]{9}[0-9X]|[0-9]{13})$/', $isbn ) ? $isbn : '';
}

/** Return the compact data used by the annual archive. */
function quietype_book_data( $post_id = null ) {
	$post_id  = $post_id ?: get_the_ID();
	$read_date = (string) get_post_meta( $post_id, '_quietype_book_read_date', true );
	$read_date = quietype_sanitize_book_date( $read_date );
	if ( ! $read_date ) {
		$read_date = quietype_sanitize_book_date( get_post_meta( $post_id, '_quietype_book_finished_date', true ) );
	}
	if ( ! $read_date ) {
		$read_date = get_the_date( 'Y-m-d', $post_id );
	}
	$douban_url = quietype_sanitize_douban_url( get_post_meta( $post_id, '_quietype_book_douban_url', true ) );
	$douban_id  = quietype_sanitize_douban_id( get_post_meta( $post_id, '_quietype_book_douban_id', true ) );
	if ( ! $douban_id ) {
		$douban_id = quietype_sanitize_douban_id( $douban_url );
	}
	return array(
		'authors'          => (string) get_post_meta( $post_id, '_quietype_book_authors', true ),
		'publisher'        => (string) get_post_meta( $post_id, '_quietype_book_publisher', true ),
		'publication_year' => (string) get_post_meta( $post_id, '_quietype_book_publication_year', true ),
		'isbn'             => (string) get_post_meta( $post_id, '_quietype_book_isbn', true ),
		'read_date'        => $read_date,
		'rating'           => (int) quietype_sanitize_book_rating( get_post_meta( $post_id, '_quietype_book_rating', true ) ),
		'douban_rating'    => (float) get_post_meta( $post_id, '_quietype_book_douban_rating', true ),
		'douban_url'       => $douban_url ?: quietype_douban_url( $douban_id ),
		'douban_id'        => $douban_id,
	);
}

/** Accessible whole-star rendering; the archive intentionally avoids half-stars. */
function quietype_book_rating_html( $rating ) {
	$rating = (int) quietype_sanitize_book_rating( $rating );
	if ( ! $rating ) {
		return '';
	}
	$html  = '<span class="personal-rating" role="img" aria-label="我的评分 ' . esc_attr( $rating ) . ' 星，满分 5 星">';
	$html .= '<span>我的</span><span class="personal-rating__stars" aria-hidden="true">';
	for ( $star = 1; $star <= 5; $star++ ) {
		$html .= '<i class="personal-rating__star' . ( $star <= $rating ? ' is-full' : '' ) . '">★</i>';
	}
	$html .= '</span></span>';
	return $html;
}

/** Render one category and up to two tags as quiet metadata, not filters. */
function quietype_book_terms_html( $post_id = null ) {
	$post_id    = $post_id ?: get_the_ID();
	$categories = get_the_terms( $post_id, 'book_category' );
	$tags       = get_the_terms( $post_id, 'book_tag' );
	$html       = '';
	if ( $categories && ! is_wp_error( $categories ) ) {
		$html .= '<span class="post-category">' . esc_html( $categories[0]->name ) . '</span>';
	}
	if ( $tags && ! is_wp_error( $tags ) ) {
		foreach ( array_slice( $tags, 0, 2 ) as $tag ) {
			$html .= '<span class="post-tag"><i aria-hidden="true">#</i>' . esc_html( $tag->name ) . '</span>';
		}
	}
	return $html;
}

/** Add the compact fields below the normal title and excerpt. */
function quietype_add_book_meta_box() {
	add_meta_box( 'quietype-book-details', '书籍资料与阅读记录', 'quietype_render_book_meta_box', 'book', 'normal', 'high' );
}
add_action( 'add_meta_boxes_book', 'quietype_add_book_meta_box' );

function quietype_render_book_meta_box( $post ) {
	$data = quietype_book_data( $post->ID );
	wp_nonce_field( 'quietype_save_book', 'quietype_book_nonce' );
	?>
	<div class="quietype-book-editor">
		<p class="description">填写豆瓣链接或条目 ID 后先读取预览；只有点击“确认填入”才会改动表单。抓取失败时可直接手工填写。书籍短评请填写编辑页面中的“摘要”。</p>
		<div class="quietype-book-lookup">
			<label class="screen-reader-text" for="quietype_book_douban_input">豆瓣链接或条目 ID</label>
			<input class="regular-text code" id="quietype_book_douban_input" type="text" value="<?php echo esc_attr( $data['douban_url'] ?: $data['douban_id'] ); ?>" placeholder="https://book.douban.com/subject/38380879/ 或 38380879">
			<button class="button" id="quietype-book-lookup" type="button">读取豆瓣资料</button>
			<span id="quietype-book-lookup-status" aria-live="polite"></span>
		</div>
		<div class="quietype-book-preview" id="quietype-book-preview" hidden>
			<img id="quietype-book-preview-cover" src="" alt="" hidden>
			<div>
				<strong id="quietype-book-preview-title"></strong>
				<p id="quietype-book-preview-meta"></p>
				<p id="quietype-book-preview-extra"></p>
				<button class="button button-primary" id="quietype-book-confirm" type="button">确认填入</button>
			</div>
		</div>
		<table class="form-table" role="presentation">
			<tr><th><label for="quietype_book_authors">作者</label></th><td><input class="regular-text" id="quietype_book_authors" name="quietype_book_authors" type="text" value="<?php echo esc_attr( $data['authors'] ); ?>" placeholder="多位作者使用顿号分隔"></td></tr>
			<tr><th><label for="quietype_book_publisher">出版社</label></th><td><input class="regular-text" id="quietype_book_publisher" name="quietype_book_publisher" type="text" value="<?php echo esc_attr( $data['publisher'] ); ?>"></td></tr>
			<tr><th><label for="quietype_book_publication_year">出版年份</label></th><td><input class="small-text" id="quietype_book_publication_year" name="quietype_book_publication_year" type="number" min="1000" max="<?php echo esc_attr( (int) gmdate( 'Y' ) + 2 ); ?>" value="<?php echo esc_attr( $data['publication_year'] ); ?>"></td></tr>
			<tr><th><label for="quietype_book_isbn">ISBN</label></th><td><input class="regular-text code" id="quietype_book_isbn" name="quietype_book_isbn" type="text" value="<?php echo esc_attr( $data['isbn'] ); ?>"></td></tr>
			<tr><th><label for="quietype_book_read_date">阅读日期</label></th><td><input id="quietype_book_read_date" name="quietype_book_read_date" type="date" value="<?php echo esc_attr( $data['read_date'] ); ?>"><p class="description">前台按年份分组，并显示到月份。</p></td></tr>
			<tr><th><label for="quietype_book_rating">我的评价</label></th><td><select id="quietype_book_rating" name="quietype_book_rating"><option value="">暂不评分</option><?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?><option value="<?php echo esc_attr( $rating ); ?>" <?php selected( $data['rating'], $rating ); ?>><?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?></option><?php endfor; ?></select></td></tr>
			<tr><th><label for="quietype_book_douban_rating">豆瓣评分</label></th><td><input class="small-text" id="quietype_book_douban_rating" name="quietype_book_douban_rating" type="number" min="0.1" max="10" step="0.1" value="<?php echo esc_attr( $data['douban_rating'] ?: '' ); ?>"></td></tr>
			<tr><th><label for="quietype_book_douban_url">豆瓣链接</label></th><td><input class="regular-text code" id="quietype_book_douban_url" name="quietype_book_douban_url" type="url" value="<?php echo esc_attr( $data['douban_url'] ); ?>" placeholder="https://book.douban.com/subject/…/"><input id="quietype_book_douban_id" name="quietype_book_douban_id" type="hidden" value="<?php echo esc_attr( $data['douban_id'] ); ?>"></td></tr>
		</table>
		<div class="quietype-book-import" id="quietype-book-import" hidden>
			<img id="quietype-book-cover-preview" src="" alt="" hidden>
			<p><label><input id="quietype_book_import_cover" name="quietype_book_import_cover" type="checkbox" value="1" checked> 保存时将预览封面导入媒体库并设为特色图</label></p>
		</div>
		<input id="quietype_book_cover_url" name="quietype_book_cover_url" type="hidden" value="">
	</div>
	<?php
}

/** Load the dependency-free preview helper only in the book editor. */
function quietype_book_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'book' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_script( 'quietype-book-admin', get_template_directory_uri() . '/assets/js/admin-books.js', array(), quietype_asset_version( 'assets/js/admin-books.js' ), true );
	wp_localize_script(
		'quietype-book-admin',
		'quietypeBookLookup',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'quietype_book_lookup' ),
		)
	);
	wp_add_inline_style( 'common', '.quietype-book-editor{max-width:860px}.quietype-book-lookup{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.quietype-book-lookup>input,.quietype-book-editor .form-table input[type="text"],.quietype-book-editor .form-table input[type="url"],.quietype-book-editor .form-table input[type="number"],.quietype-book-editor .form-table input[type="date"],.quietype-book-editor .form-table select{width:100%;max-width:420px;min-height:32px}.quietype-book-preview{display:grid;grid-template-columns:72px minmax(0,1fr);gap:14px;align-items:start;padding:12px;margin:14px 0;border:1px solid #dcdcde;background:#f6f7f7}.quietype-book-preview[hidden],.quietype-book-import[hidden]{display:none}.quietype-book-preview img,.quietype-book-import img{width:72px;height:104px;object-fit:contain;background:#fff}.quietype-book-preview p{margin:5px 0}.quietype-book-import{display:flex;align-items:center;gap:12px;padding:10px 12px;margin:4px 0 12px;border-left:3px solid #72aee6;background:#f6f7f7}@media(max-width:782px){.quietype-book-lookup>input,.quietype-book-editor .form-table input[type="text"],.quietype-book-editor .form-table input[type="url"],.quietype-book-editor .form-table input[type="number"],.quietype-book-editor .form-table input[type="date"],.quietype-book-editor .form-table select{max-width:none}}' );
}
add_action( 'admin_enqueue_scripts', 'quietype_book_admin_assets' );

/** Save only values that the administrator has reviewed in the form. */
function quietype_save_book_meta( $post_id, $post ) {
	if ( ! isset( $_POST['quietype_book_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_book_nonce'] ) ), 'quietype_save_book' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$fields = array(
		'_quietype_book_authors'          => array( 'quietype_book_authors', 'sanitize_text_field' ),
		'_quietype_book_publisher'        => array( 'quietype_book_publisher', 'sanitize_text_field' ),
		'_quietype_book_publication_year' => array( 'quietype_book_publication_year', 'quietype_sanitize_book_year' ),
		'_quietype_book_isbn'             => array( 'quietype_book_isbn', 'quietype_sanitize_book_isbn' ),
		'_quietype_book_read_date'        => array( 'quietype_book_read_date', 'quietype_sanitize_book_date' ),
		'_quietype_book_rating'           => array( 'quietype_book_rating', 'quietype_sanitize_book_rating' ),
		'_quietype_book_douban_rating'    => array( 'quietype_book_douban_rating', 'quietype_sanitize_douban_rating' ),
	);
	foreach ( $fields as $meta_key => $field ) {
		$raw   = isset( $_POST[ $field[0] ] ) ? wp_unslash( $_POST[ $field[0] ] ) : '';
		$value = call_user_func( $field[1], $raw );
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
	$douban_source = isset( $_POST['quietype_book_douban_url'] ) ? wp_unslash( $_POST['quietype_book_douban_url'] ) : '';
	$douban_id     = quietype_sanitize_douban_id( $douban_source );
	if ( ! $douban_id && isset( $_POST['quietype_book_douban_id'] ) ) {
		$douban_id = quietype_sanitize_douban_id( wp_unslash( $_POST['quietype_book_douban_id'] ) );
	}
	if ( $douban_id ) {
		update_post_meta( $post_id, '_quietype_book_douban_id', $douban_id );
		update_post_meta( $post_id, '_quietype_book_douban_url', quietype_douban_url( $douban_id ) );
	} else {
		delete_post_meta( $post_id, '_quietype_book_douban_id' );
		delete_post_meta( $post_id, '_quietype_book_douban_url' );
	}
	if ( isset( $_POST['quietype_book_import_cover'], $_POST['quietype_book_cover_url'] ) && current_user_can( 'upload_files' ) ) {
		quietype_import_book_cover( $post_id, esc_url_raw( wp_unslash( $_POST['quietype_book_cover_url'] ) ) );
	}
}
add_action( 'save_post_book', 'quietype_save_book_meta', 10, 2 );

/** Give programmatically inserted books a stable reading date. */
function quietype_ensure_book_defaults( $post_id, $post ) {
	if ( ! $post instanceof WP_Post || 'book' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! get_post_meta( $post_id, '_quietype_book_read_date', true ) ) {
		update_post_meta( $post_id, '_quietype_book_read_date', mysql2date( 'Y-m-d', $post->post_date ) );
	}
}
add_action( 'wp_after_insert_post', 'quietype_ensure_book_defaults', 10, 2 );

/** Extract a named meta value from Douban's server-rendered HTML. */
function quietype_douban_meta_value( $html, $property ) {
	if ( ! preg_match_all( '/<meta\b[^>]*>/iu', $html, $tags ) ) {
		return '';
	}
	foreach ( $tags[0] as $tag ) {
		if ( ! preg_match( '/\bproperty=["\']' . preg_quote( $property, '/' ) . '["\']/iu', $tag ) ) {
			continue;
		}
		if ( preg_match( '/\bcontent=["\']([^"\']*)["\']/iu', $tag, $content ) ) {
			return sanitize_text_field( html_entity_decode( $content[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}
	}
	return '';
}

/** Parse only bibliographic fields visible on a Douban book page. */
function quietype_parse_douban_book_html( $html, $subject_id ) {
	$title     = quietype_douban_meta_value( $html, 'og:title' );
	$cover_url = esc_url_raw( quietype_douban_meta_value( $html, 'og:image' ), array( 'https' ) );
	$authors   = array();
	if ( preg_match_all( '/<meta\b[^>]*\bproperty=["\']book:author["\'][^>]*\bcontent=["\']([^"\']+)["\'][^>]*>/iu', $html, $author_matches ) ) {
		$authors = array_values( array_unique( array_map( 'sanitize_text_field', $author_matches[1] ) ) );
		$authors = array_values(
			array_filter(
				$authors,
				function ( $author ) use ( $authors ) {
					foreach ( $authors as $other ) {
						if ( $author !== $other && false !== mb_strpos( $other, $author ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}
	$fields = array();
	if ( preg_match( '/<div\b[^>]*\bid=["\']info["\'][^>]*>(.*?)<\/div>/isu', $html, $info ) ) {
		$text = preg_replace( '/\s+/u', ' ', $info[1] );
		$text = preg_replace( '/<br\s*\/?\s*>/iu', "\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( preg_split( '/\R+/u', $text ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( preg_match( '/^(作者|出版社|出版年|ISBN)\s*[:：]\s*(.+)$/u', $line, $match ) ) {
				$fields[ $match[1] ] = sanitize_text_field( $match[2] );
			}
		}
	}
	if ( ! $authors && ! empty( $fields['作者'] ) ) {
		$authors = preg_split( '/\s*\/\s*|\s*、\s*/u', $fields['作者'] );
	}
	preg_match( '/<strong\b[^>]*\bproperty=["\']v:average["\'][^>]*>\s*([0-9.]+)/iu', $html, $rating );
	preg_match( '/\b(1[0-9]{3}|20[0-9]{2})\b/u', $fields['出版年'] ?? '', $year );
	return array(
		'title'            => $title,
		'authors'          => implode( '、', array_values( array_filter( array_map( 'trim', $authors ) ) ) ),
		'publisher'        => sanitize_text_field( $fields['出版社'] ?? '' ),
		'publication_year' => quietype_sanitize_book_year( $year[1] ?? '' ),
		'isbn'             => quietype_sanitize_book_isbn( $fields['ISBN'] ?? '' ),
		'douban_rating'    => quietype_sanitize_douban_rating( $rating[1] ?? '' ),
		'douban_id'        => quietype_sanitize_douban_id( $subject_id ),
		'douban_url'       => quietype_douban_url( $subject_id ),
		'cover_url'        => $cover_url,
	);
}

/** Administrator-only lookup returns a preview and never saves content. */
function quietype_ajax_lookup_book() {
	check_ajax_referer( 'quietype_book_lookup', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => '没有查询书籍资料的权限。' ), 403 );
	}
	$subject_id = quietype_sanitize_douban_id( isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '' );
	if ( ! $subject_id ) {
		wp_send_json_error( array( 'message' => '请输入有效的豆瓣读书链接或条目 ID。' ), 422 );
	}
	$cache_key = 'quietype_douban_book_' . $subject_id;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		wp_send_json_success( $cached );
	}
	$response = wp_safe_remote_get(
		quietype_douban_url( $subject_id ),
		array(
			'timeout'             => 12,
			'redirection'         => 2,
			'limit_response_size' => 1572864,
			'headers'             => array(
				'Accept-Language' => 'zh-CN,zh;q=0.9',
				'Referer'         => 'https://book.douban.com/',
				'User-Agent'      => 'Mozilla/5.0 (compatible; Quietype/' . QUIETYPE_VERSION . '; ' . home_url( '/' ) . ')',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => '暂时无法连接豆瓣，请稍后重试或手工填写。' ), 502 );
	}
	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		wp_send_json_error( array( 'message' => '豆瓣暂时拒绝了资料请求，请稍后重试或手工填写。' ), $status >= 400 && $status < 600 ? $status : 502 );
	}
	$result = quietype_parse_douban_book_html( wp_remote_retrieve_body( $response ), $subject_id );
	if ( empty( $result['title'] ) ) {
		wp_send_json_error( array( 'message' => '没有识别到书籍资料，请检查链接或手工填写。' ), 404 );
	}
	set_transient( $cache_key, $result, 30 * DAY_IN_SECONDS );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_quietype_lookup_book', 'quietype_ajax_lookup_book' );

/** Import only images hosted by Douban's image CDN. */
function quietype_import_book_cover( $post_id, $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! str_ends_with( $host, '.doubanio.com' ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $url );
	if ( ! $tmp ) {
		return;
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'     => 12,
			'redirection' => 2,
			'stream'      => true,
			'filename'    => $tmp,
			'headers'     => array(
				'Referer'    => 'https://book.douban.com/',
				'User-Agent' => 'Mozilla/5.0 (compatible; Quietype/' . QUIETYPE_VERSION . ')',
			),
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_delete_file( $tmp );
		return;
	}
	$mime       = wp_get_image_mime( $tmp );
	$extensions = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
	if ( ! isset( $extensions[ $mime ] ) || filesize( $tmp ) > 5 * MB_IN_BYTES ) {
		wp_delete_file( $tmp );
		return;
	}
	$file = array(
		'name'     => sanitize_title( get_the_title( $post_id ) ) . '-cover.' . $extensions[ $mime ],
		'tmp_name' => $tmp,
	);
	$attachment_id = media_handle_sideload( $file, $post_id, get_the_title( $post_id ) . '封面' );
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return;
	}
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', '《' . get_the_title( $post_id ) . '》封面' );
	set_post_thumbnail( $post_id, $attachment_id );
}

/** Keep the annual archive complete and order it by the reading date. */
function quietype_order_book_archive( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'book' ) ) {
		return;
	}
	$query->set( 'posts_per_page', -1 );
	$query->set( 'meta_key', '_quietype_book_read_date' );
	$query->set( 'orderby', array( 'meta_value' => 'DESC', 'date' => 'DESC' ) );
	$query->set( 'order', 'DESC' );
}
add_action( 'pre_get_posts', 'quietype_order_book_archive' );

/** Standalone book URLs deliberately resolve to the source rather than duplicate notes. */
function quietype_redirect_single_book() {
	if ( ! is_singular( 'book' ) ) {
		return;
	}
	$data = quietype_book_data( get_queried_object_id() );
	if ( $data['douban_url'] ) {
		wp_redirect( $data['douban_url'], 302, 'Quietype' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- URL is rebuilt from a validated numeric Douban ID.
	} else {
		wp_safe_redirect( get_post_type_archive_link( 'book' ), 302 );
	}
	exit;
}
add_action( 'template_redirect', 'quietype_redirect_single_book', 1 );

/** Book entries have no canonical detail page, so omit them from core sitemaps. */
function quietype_remove_books_from_sitemap( $post_types ) {
	unset( $post_types['book'] );
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'quietype_remove_books_from_sitemap' );

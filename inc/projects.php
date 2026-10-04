<?php
/**
 * Archive-only project records with editable types and optional external links.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register portable records, without creating public type or detail pages. */
function quietype_register_projects() {
	register_post_type(
		'project',
		array(
			'labels'              => array(
				'name' => '项目', 'singular_name' => '项目', 'add_new' => '添加项目',
				'add_new_item' => '添加项目', 'edit_item' => '编辑项目', 'new_item' => '新项目',
				'view_item' => '在项目页查看', 'view_items' => '查看项目页',
				'search_items' => '搜索项目', 'not_found' => '没有找到项目',
				'not_found_in_trash' => '回收站中没有项目', 'all_items' => '全部项目',
			),
			'public'              => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'has_archive'         => 'projects',
			'rewrite'             => array( 'slug' => 'projects', 'with_front' => false ),
			'menu_icon'           => 'dashicons-portfolio',
			'menu_position'       => 8,
			'supports'            => array( 'title', 'excerpt', 'thumbnail' ),
			'show_in_nav_menus'   => false,
		)
	);
	register_taxonomy(
		'project_type',
		'project',
		array(
			'labels'             => array( 'name' => '项目类型', 'singular_name' => '项目类型', 'add_new_item' => '添加项目类型' ),
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'meta_box_cb'        => false,
			'rewrite'            => false,
			'query_var'          => false,
		)
	);
	foreach ( quietype_project_meta_fields() as $key => $callback ) {
		register_post_meta(
			'project',
			'_quietype_project_' . $key,
			array(
				'type' => 'string', 'single' => true, 'show_in_rest' => true,
				'sanitize_callback' => $callback,
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'quietype_register_projects' );

function quietype_project_meta_fields() {
	return array(
		'url' => 'quietype_sanitize_project_url',
		'source_url' => 'quietype_sanitize_project_url',
		'writeup_url' => 'quietype_sanitize_project_url',
		'image_url' => 'quietype_sanitize_project_url',
		'source_state' => 'quietype_sanitize_project_source_state',
		'languages' => 'quietype_sanitize_project_languages',
	);
}

/** Browser-only links: never fetch an arbitrary project or image on the server. */
function quietype_sanitize_project_url( $value ) {
	$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	return wp_parse_url( $url, PHP_URL_HOST ) ? $url : '';
}

function quietype_sanitize_project_source_state( $value ) {
	return in_array( $value, array( 'open', 'closed' ), true ) ? $value : '';
}

function quietype_sanitize_project_languages( $value ) {
	$parts = preg_split( '/[,，、;；\r\n]+/u', sanitize_text_field( (string) $value ) );
	return implode( ', ', array_unique( array_filter( array_map( 'trim', $parts ) ) ) );
}

/** Seed suggestions once; later renames/deletions belong to the administrator. */
function quietype_upgrade_projects() {
	if ( '1' === get_option( 'quietype_project_data_version' ) ) {
		return;
	}
	foreach ( array( 'personal' => '个人项目', 'dashboard' => '数据看板', 'index' => '资讯索引', 'theme' => '博客主题', 'firmware' => '硬件固件' ) as $slug => $name ) {
		if ( ! term_exists( $slug, 'project_type' ) ) {
			wp_insert_term( $name, 'project_type', array( 'slug' => $slug ) );
		}
	}
	flush_rewrite_rules( false );
	update_option( 'quietype_project_data_version', '1', false );
}
add_action( 'init', 'quietype_upgrade_projects', 32 );

function quietype_sanitize_project_default_type( $value ) {
	$id = absint( $value );
	return $id && term_exists( $id, 'project_type' ) ? $id : 0;
}

/** A deleted default safely falls back without re-creating deleted taxonomy terms. */
function quietype_project_default_type() {
	$id   = quietype_sanitize_project_default_type( quietype_get_setting( 'quietype_projects_default_type', 0 ) );
	$term = $id ? get_term( $id, 'project_type' ) : get_term_by( 'slug', 'personal', 'project_type' );
	return $term instanceof WP_Term ? $term : null;
}

function quietype_project_type_name( $post_id ) {
	$terms = get_the_terms( $post_id, 'project_type' );
	$term  = $terms && ! is_wp_error( $terms ) ? reset( $terms ) : quietype_project_default_type();
	return $term instanceof WP_Term ? $term->name : '个人项目';
}

function quietype_project_data( $post_id ) {
	$data = array();
	foreach ( quietype_project_meta_fields() as $key => $callback ) {
		$data[ $key ] = $callback( get_post_meta( $post_id, '_quietype_project_' . $key, true ) );
	}
	$data['type']          = quietype_project_type_name( $post_id );
	$data['attachment_id'] = get_post_thumbnail_id( $post_id );
	$data['is_external']   = '' !== $data['image_url'];
	$data['image_width']   = 0;
	$data['image_height']  = 0;
	$data['thumbnail_url'] = $data['image_url'];
	if ( ! $data['image_url'] && $data['attachment_id'] ) {
		$image = wp_get_attachment_image_src( $data['attachment_id'], 'full' );
		if ( $image ) {
			$data['image_url']     = $image[0];
			$data['image_width']   = $image[1];
			$data['image_height']  = $image[2];
			$data['thumbnail_url'] = wp_get_attachment_image_url( $data['attachment_id'], 'large' ) ?: $image[0];
		}
	}
	return $data;
}

/** Manual order first, with stable date/ID ordering for records sharing a value. */
function quietype_project_archive_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'project' ) ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'DESC' ) );
	}
}
add_action( 'pre_get_posts', 'quietype_project_archive_query' );

function quietype_redirect_single_project() {
	if ( is_singular( 'project' ) ) {
		wp_safe_redirect( quietype_archive_record_url( get_queried_object() ), 301, 'Quietype' );
		exit;
	}
}
add_action( 'template_redirect', 'quietype_redirect_single_project', 1 );

function quietype_remove_projects_from_sitemap( $post_types ) {
	unset( $post_types['project'] );
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'quietype_remove_projects_from_sitemap' );

/** Keep this short record editor in the same classic form as books and photos. */
function quietype_project_block_editor( $use_block_editor, $post_type ) {
	return 'project' === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'quietype_project_block_editor', 10, 2 );

function quietype_project_meta_boxes() {
	remove_meta_box( 'postexcerpt', 'project', 'normal' );
	add_meta_box( 'quietype-project-details', '项目资料', 'quietype_render_project_meta_box', 'project', 'normal', 'high' );
	add_meta_box( 'postexcerpt', '项目简介', 'quietype_render_project_excerpt_box', 'project', 'normal', 'high' );
}
add_action( 'add_meta_boxes_project', 'quietype_project_meta_boxes' );

function quietype_render_project_excerpt_box( $post ) {
	?>
	<label class="screen-reader-text" for="excerpt">项目简介</label>
	<textarea class="large-text" id="excerpt" name="excerpt" rows="3"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
	<p class="description">简要说明项目做什么；详细的开发记录可另写文章，并填写介绍链接。</p>
	<?php
}

function quietype_render_project_meta_box( $post ) {
	wp_nonce_field( 'quietype_save_project', 'quietype_project_nonce' );
	$data = quietype_project_data( $post->ID );
	$terms = get_terms( array( 'taxonomy' => 'project_type', 'hide_empty' => false ) );
	$assigned = wp_get_post_terms( $post->ID, 'project_type', array( 'fields' => 'ids' ) );
	$current_type = ! is_wp_error( $assigned ) && $assigned ? (int) $assigned[0] : 0;
	$default_type = quietype_project_default_type();
	?>
	<table class="form-table quietype-project-editor" role="presentation">
		<tr><th><label for="quietype_project_type">项目类型</label></th><td><select id="quietype_project_type" name="quietype_project_type">
			<option value="0" <?php selected( $current_type, 0 ); ?>><?php echo esc_html( '使用默认类型（' . ( $default_type ? $default_type->name : '个人项目' ) . '）' ); ?></option>
			<?php foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $current_type, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select><p class="description">在“项目 → 项目类型”中增删或改名；默认类型在“外观 → Quietype 设置 → 内容页面”中配置。</p></td></tr>
		<tr><th><label for="quietype_project_source_state">开源状态</label></th><td><select id="quietype_project_source_state" name="quietype_project_source_state"><option value="">不显示</option><option value="open" <?php selected( $data['source_state'], 'open' ); ?>>开源</option><option value="closed" <?php selected( $data['source_state'], 'closed' ); ?>>未开源</option></select><p class="description">可选。请根据项目的实际许可填写，不会仅凭有无 GitHub 链接自动判断。</p></td></tr>
		<tr><th><label for="quietype_project_languages">主要语言</label></th><td><input id="quietype_project_languages" name="quietype_project_languages" type="text" value="<?php echo esc_attr( $data['languages'] ); ?>" placeholder="PHP, JavaScript"><p class="description">可选，用逗号分隔；留空不显示。</p></td></tr>
		<?php foreach ( array( 'url' => '在线访问', 'source_url' => '源码仓库', 'writeup_url' => '介绍链接', 'image_url' => '截图地址' ) as $key => $label ) : ?>
			<tr><th><label for="quietype_project_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="quietype_project_<?php echo esc_attr( $key ); ?>" name="quietype_project_<?php echo esc_attr( $key ); ?>" type="url" value="<?php echo esc_attr( quietype_sanitize_project_url( get_post_meta( $post->ID, '_quietype_project_' . $key, true ) ) ); ?>" placeholder="<?php echo esc_attr( 'image_url' === $key ? 'https://example.com/images/project.jpg' : 'https://example.com/' ); ?>">
			<?php if ( 'image_url' === $key ) : ?><p class="description">可选外链，优先于右侧的特色图片。留空可从媒体库设置截图；不会自动抓取网页。</p><?php else : ?><p class="description">可选，留空不显示对应入口。</p><?php endif; ?></td></tr>
		<?php endforeach; ?>
		<tr><th><label for="menu_order">显示顺序</label></th><td><input id="menu_order" name="menu_order" type="number" value="<?php echo esc_attr( $post->menu_order ); ?>"><p class="description">数值越小越靠前；相同数值按发布时间从新到旧。</p></td></tr>
	</table>
	<?php
}

function quietype_save_project( $post_id ) {
	if ( ! isset( $_POST['quietype_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_project_nonce'] ) ), 'quietype_save_project' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	foreach ( quietype_project_meta_fields() as $key => $callback ) {
		$field = 'quietype_project_' . $key;
		if ( isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ) {
			$value = $callback( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Field-specific sanitizer above.
			update_post_meta( $post_id, '_quietype_project_' . $key, $value );
		}
	}
	if ( isset( $_POST['quietype_project_type'] ) && is_scalar( $_POST['quietype_project_type'] ) ) {
		$type_id = quietype_sanitize_project_default_type( absint( $_POST['quietype_project_type'] ) );
		wp_set_post_terms( $post_id, $type_id ? array( $type_id ) : array(), 'project_type' );
	}
}
add_action( 'save_post_project', 'quietype_save_project' );

function quietype_project_admin_assets() {
	$screen = get_current_screen();
	if ( $screen && 'project' === $screen->post_type ) {
		wp_add_inline_style( 'common', '.quietype-project-editor input,.quietype-project-editor select{width:100%;max-width:520px;min-height:34px}.quietype-project-editor th{width:140px}.column-quietype_project_type{width:130px}.column-quietype_project_order{width:80px}' );
	}
}
add_action( 'admin_enqueue_scripts', 'quietype_project_admin_assets' );

function quietype_project_admin_columns( $columns ) {
	$columns['quietype_project_type'] = '项目类型';
	$columns['quietype_project_order'] = '顺序';
	return $columns;
}
add_filter( 'manage_project_posts_columns', 'quietype_project_admin_columns' );

function quietype_project_admin_column( $column, $post_id ) {
	if ( 'quietype_project_type' === $column ) {
		echo esc_html( quietype_project_type_name( $post_id ) );
	} elseif ( 'quietype_project_order' === $column ) {
		echo esc_html( get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_project_posts_custom_column', 'quietype_project_admin_column', 10, 2 );

/** Match the optional, operator-configured book/photo archive cache integration. */
function quietype_schedule_project_cache_purge() {
	static $scheduled = false;
	if ( ! $scheduled ) {
		$scheduled = true;
		add_action( 'shutdown', 'quietype_purge_project_cache', PHP_INT_MAX );
	}
}
add_action( 'save_post_project', 'quietype_schedule_project_cache_purge', 100 );
add_action( 'edited_project_type', 'quietype_schedule_project_cache_purge' );
add_action( 'delete_project_type', 'quietype_schedule_project_cache_purge' );

/** REST, imports and featured-image updates may write metadata after save_post. */
function quietype_project_cache_on_meta( $meta_id, $post_id, $key ) {
	if ( 'project' === get_post_type( $post_id ) && ( '_thumbnail_id' === $key || 0 === strpos( $key, '_quietype_project_' ) ) ) {
		quietype_schedule_project_cache_purge();
	}
}
add_action( 'added_post_meta', 'quietype_project_cache_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'quietype_project_cache_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'quietype_project_cache_on_meta', 10, 3 );

function quietype_project_cache_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'project_type' === $taxonomy ) {
		quietype_schedule_project_cache_purge();
	}
}
add_action( 'set_object_terms', 'quietype_project_cache_on_terms', 10, 4 );

function quietype_project_cache_on_deletion( $post_id, $post ) {
	if ( 'project' === $post->post_type ) {
		quietype_schedule_project_cache_purge();
	}
}
add_action( 'deleted_post', 'quietype_project_cache_on_deletion', 10, 2 );

function quietype_project_cache_on_setting( $option ) {
	if ( 0 === strpos( $option, 'quietype_projects_' ) ) {
		quietype_schedule_project_cache_purge();
	}
}
add_action( 'updated_option', 'quietype_project_cache_on_setting' );
add_action( 'added_option', 'quietype_project_cache_on_setting' );

function quietype_purge_project_cache() {
	$endpoint = defined( 'QUIETYPE_PROJECT_CACHE_PURGE_ENDPOINT' ) ? (string) QUIETYPE_PROJECT_CACHE_PURGE_ENDPOINT : '';
	$endpoint = esc_url_raw( (string) apply_filters( 'quietype_project_archive_cache_purge_endpoint', $endpoint ) );
	if ( ! $endpoint ) {
		return;
	}
	$response = wp_remote_request( $endpoint, array( 'method' => 'PURGE', 'timeout' => 2, 'redirection' => 0, 'headers' => array( 'Host' => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) );
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_wp_error( $response ) ) {
		error_log( 'Quietype project cache purge failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

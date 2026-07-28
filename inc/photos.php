<?php
/**
 * Lightweight annual photo archive with optional remote-image metadata.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register photos as archive-only records managed without an extra plugin. */
function quietype_register_photos() {
	register_post_type(
		'photo',
		array(
			'labels'              => array(
				'name'               => '照片',
				'singular_name'      => '照片',
				'add_new'            => '添加照片',
				'add_new_item'       => '添加照片',
				'edit_item'          => '编辑照片',
				'new_item'           => '新照片',
				'view_item'          => '在图库中查看',
				'view_items'         => '查看图库',
				'search_items'       => '搜索照片',
				'not_found'          => '没有找到照片',
				'not_found_in_trash' => '回收站中没有照片',
				'all_items'          => '全部照片',
				'menu_name'          => '照片',
			),
			'public'              => true,
			'exclude_from_search' => true,
			'show_in_rest'        => true,
			'has_archive'         => 'photos',
			'rewrite'             => array(
				'slug'       => 'photos',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-format-gallery',
			'menu_position'       => 7,
			'supports'            => array( 'title', 'excerpt', 'thumbnail' ),
			'show_in_nav_menus'   => false,
		)
	);

	$meta_fields = array(
		'_quietype_photo_image_url'     => 'quietype_sanitize_photo_url',
		'_quietype_photo_original_url'  => 'quietype_sanitize_photo_url',
		'_quietype_photo_captured_date' => 'quietype_sanitize_photo_date',
		'_quietype_photo_location'      => 'sanitize_text_field',
		'_quietype_photo_width'         => 'quietype_sanitize_photo_dimension',
		'_quietype_photo_height'        => 'quietype_sanitize_photo_dimension',
		'_quietype_photo_focal_length'  => 'quietype_sanitize_photo_parameter',
		'_quietype_photo_aperture'      => 'quietype_sanitize_photo_parameter',
		'_quietype_photo_shutter_speed' => 'quietype_sanitize_photo_parameter',
		'_quietype_photo_iso'           => 'quietype_sanitize_photo_iso',
		'_quietype_photo_camera'        => 'quietype_sanitize_photo_camera',
		'_quietype_photo_lens'          => 'sanitize_text_field',
	);
	foreach ( $meta_fields as $key => $sanitize_callback ) {
		register_post_meta(
			'photo',
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
add_action( 'init', 'quietype_register_photos' );

/** Flush the new archive route once per schema version. */
function quietype_upgrade_photos() {
	if ( '1' === get_option( 'quietype_photo_data_version' ) ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'quietype_photo_data_version', '1', false );
}
add_action( 'init', 'quietype_upgrade_photos', 31 );

function quietype_sanitize_photo_url( $value ) {
	$url = esc_url_raw( trim( (string) $value ), array( 'https' ) );
	return wp_parse_url( $url, PHP_URL_HOST ) ? $url : '';
}

/** Normalize the optional root shared by display photos and generated thumbnails. */
function quietype_sanitize_photo_thumbnail_base_url( $value ) {
	$url = quietype_sanitize_photo_url( $value );
	return $url ? untrailingslashit( $url ) : '';
}

function quietype_sanitize_photo_date( $value ) {
	$value = sanitize_text_field( (string) $value );
	if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value ) ) {
		$date = DateTime::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date->format( 'Y-m' ) : '';
	}
	$date = DateTime::createFromFormat( '!Y-m', $value );
	return $date && $date->format( 'Y-m' ) === $value ? $value : '';
}

function quietype_sanitize_photo_dimension( $value ) {
	$value = absint( $value );
	return $value >= 1 && $value <= 60000 ? (string) $value : '';
}

/** Keep camera values compact and printable without accepting arbitrary markup. */
function quietype_sanitize_photo_parameter( $value ) {
	$value = sanitize_text_field( trim( (string) $value ) );
	return mb_substr( $value, 0, 32 );
}

function quietype_sanitize_photo_iso( $value ) {
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}
	$value = absint( $value );
	return $value >= 1 && $value <= 409600 ? (string) $value : '';
}

/** Keep phone-camera metadata at brand level while preserving dedicated camera names. */
function quietype_sanitize_photo_camera( $value ) {
	$value = sanitize_text_field( trim( (string) $value ) );
	if ( preg_match( '/^(?:Xiaomi|Redmi|POCO)(?:\s|$)/iu', $value ) ) {
		return 'Xiaomi';
	}
	return $value;
}

/** Return normalized photo data for the archive and administration screen. */
function quietype_photo_data( $post_id = null ) {
	$post_id       = $post_id ?: get_the_ID();
	$attachment_id = get_post_thumbnail_id( $post_id );
	$external_url  = quietype_sanitize_photo_url( get_post_meta( $post_id, '_quietype_photo_image_url', true ) );
	$image_url     = $external_url;
	$original_url  = quietype_sanitize_photo_url( get_post_meta( $post_id, '_quietype_photo_original_url', true ) );
	$width         = quietype_sanitize_photo_dimension( get_post_meta( $post_id, '_quietype_photo_width', true ) );
	$height        = quietype_sanitize_photo_dimension( get_post_meta( $post_id, '_quietype_photo_height', true ) );
	if ( ! $image_url && $attachment_id ) {
		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( $image ) {
			$image_url = $image[0];
			$width     = $width ?: (string) $image[1];
			$height    = $height ?: (string) $image[2];
		}
	}
	$captured_date = quietype_sanitize_photo_date( get_post_meta( $post_id, '_quietype_photo_captured_date', true ) );
	if ( ! $captured_date ) {
		$captured_date = get_the_date( 'Y-m', $post_id );
	}
	return array(
		'image_url'     => $image_url,
		'original_url'  => $original_url,
		'attachment_id' => $attachment_id,
		'is_external'   => (bool) $external_url,
		'captured_date' => $captured_date,
		'location'      => (string) get_post_meta( $post_id, '_quietype_photo_location', true ),
		'width'         => (int) $width,
		'height'        => (int) $height,
		'focal_length'  => quietype_sanitize_photo_parameter( get_post_meta( $post_id, '_quietype_photo_focal_length', true ) ),
		'aperture'      => quietype_sanitize_photo_parameter( get_post_meta( $post_id, '_quietype_photo_aperture', true ) ),
		'shutter_speed' => quietype_sanitize_photo_parameter( get_post_meta( $post_id, '_quietype_photo_shutter_speed', true ) ),
		'iso'           => quietype_sanitize_photo_iso( get_post_meta( $post_id, '_quietype_photo_iso', true ) ),
		'camera'        => quietype_sanitize_photo_camera( get_post_meta( $post_id, '_quietype_photo_camera', true ) ),
		'lens'          => (string) get_post_meta( $post_id, '_quietype_photo_lens', true ),
	);
}

/** Derive a deterministic WebP grid URL without adding one field to every record. */
function quietype_photo_thumbnail_url( $image_url ) {
	$image_url = quietype_sanitize_photo_url( $image_url );
	$base_url  = defined( 'QUIETYPE_PHOTO_THUMBNAIL_BASE_URL' ) ? QUIETYPE_PHOTO_THUMBNAIL_BASE_URL : quietype_get_setting( 'quietype_photo_thumbnail_base_url', '' );
	$base_url  = quietype_sanitize_photo_thumbnail_base_url( $base_url );
	if ( ! $image_url || ! $base_url ) {
		return $image_url;
	}

	$image_parts = wp_parse_url( $image_url );
	$base_parts  = wp_parse_url( $base_url );
	if ( strtolower( (string) ( $image_parts['host'] ?? '' ) ) !== strtolower( (string) ( $base_parts['host'] ?? '' ) )
		|| (int) ( $image_parts['port'] ?? 443 ) !== (int) ( $base_parts['port'] ?? 443 ) ) {
		return $image_url;
	}

	$base_path  = untrailingslashit( (string) ( $base_parts['path'] ?? '' ) );
	$image_path = (string) ( $image_parts['path'] ?? '' );
	$relative   = ltrim( substr( $image_path, strlen( $base_path ) ), '/' );
	if ( 0 !== strpos( $image_path, $base_path . '/' ) || ! preg_match( '#^([0-9]{4})/([a-z0-9_-]+)\.(?:jpe?g|png|webp)$#i', $relative, $matches ) ) {
		return $image_url;
	}

	$thumbnail_url = $base_url . '/thumbs/' . $matches[1] . '/' . strtolower( $matches[2] ) . '.webp';
	return quietype_sanitize_photo_url( apply_filters( 'quietype_photo_thumbnail_url', $thumbnail_url, $image_url ) ) ?: $image_url;
}

/**
 * Resolve a lightweight grid image, a restrained lightbox image and an optional original.
 *
 * External images can use a deterministic WebP thumbnail when a matching CDN base is
 * configured. The optional original is never loaded automatically.
 */
function quietype_photo_image_sources( $data ) {
	$width  = $data['width'] ?: 1600;
	$height = $data['height'] ?: 1067;
	$result = array(
		'grid_url'        => $data['image_url'],
		'lightbox_url'    => $data['image_url'],
		'lightbox_width'  => $width,
		'lightbox_height' => $height,
		'original_url'    => $data['original_url'],
	);

	if ( ! $data['attachment_id'] || $data['is_external'] ) {
		if ( $data['is_external'] ) {
			$result['grid_url'] = quietype_photo_thumbnail_url( $data['image_url'] );
		}
		if ( $result['original_url'] === $result['lightbox_url'] ) {
			$result['original_url'] = '';
		}
		return $result;
	}

	$grid     = wp_get_attachment_image_src( $data['attachment_id'], 'quietype-photo-grid' );
	$lightbox = wp_get_attachment_image_src( $data['attachment_id'], 'quietype-photo-lightbox' );
	$original = wp_get_original_image_url( $data['attachment_id'] );
	if ( $grid ) {
		$result['grid_url'] = $grid[0];
	}
	if ( $lightbox ) {
		$result['lightbox_url']    = $lightbox[0];
		$result['lightbox_width']  = $lightbox[1];
		$result['lightbox_height'] = $lightbox[2];
	}
	if ( ! $result['original_url'] && $original && $original !== $result['lightbox_url'] ) {
		$result['original_url'] = $original;
	}
	if ( $result['original_url'] === $result['lightbox_url'] ) {
		$result['original_url'] = '';
	}
	return $result;
}

/** Build one restrained line for PhotoSwipe; omit it completely when empty. */
function quietype_photo_exif_text( $data ) {
	$parts = array();
	if ( $data['focal_length'] ) {
		$parts[] = $data['focal_length'];
	}
	if ( $data['aperture'] ) {
		$parts[] = $data['aperture'];
	}
	if ( $data['shutter_speed'] ) {
		$parts[] = $data['shutter_speed'];
	}
	if ( $data['iso'] ) {
		$parts[] = 'ISO ' . $data['iso'];
	}
	return implode( ' · ', $parts );
}

/** Keep the archive complete and sort by the recorded shooting month. */
function quietype_photo_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'photo' ) ) {
		return;
	}
	$query->set( 'posts_per_page', -1 );
	$query->set( 'meta_key', '_quietype_photo_captured_date' );
	$query->set( 'orderby', array( 'meta_value' => 'DESC', 'date' => 'DESC' ) );
}
add_action( 'pre_get_posts', 'quietype_photo_archive_query' );

/** Individual records are administration details, not public destination pages. */
function quietype_redirect_photo_singular() {
	if ( is_singular( 'photo' ) ) {
		$url = function_exists( 'quietype_archive_record_url' ) ? quietype_archive_record_url( get_queried_object_id() ) : get_post_type_archive_link( 'photo' );
		wp_safe_redirect( $url, 301, 'Quietype' );
		exit;
	}
}
add_action( 'template_redirect', 'quietype_redirect_photo_singular', 0 );

/** Do not advertise redirect-only photo detail URLs in the core sitemap. */
function quietype_remove_photos_from_sitemap( $post_types ) {
	unset( $post_types['photo'] );
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'quietype_remove_photos_from_sitemap' );

function quietype_add_photo_meta_boxes() {
	add_meta_box( 'quietype-photo-details', '照片资料', 'quietype_render_photo_meta_box', 'photo', 'normal', 'high' );
	remove_meta_box( 'postexcerpt', 'photo', 'normal' );
	add_meta_box( 'postexcerpt', '照片说明', 'quietype_render_photo_note_meta_box', 'photo', 'normal', 'high' );
}
add_action( 'add_meta_boxes_photo', 'quietype_add_photo_meta_boxes' );

function quietype_render_photo_note_meta_box( $post ) {
	?>
	<label class="screen-reader-text" for="excerpt">照片说明</label>
	<textarea class="widefat" id="excerpt" name="excerpt" rows="3"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
	<p class="description">可选。网格保持简洁，说明仅在放大查看时显示。</p>
	<?php
}

function quietype_render_photo_meta_box( $post ) {
	$data = quietype_photo_data( $post->ID );
	wp_nonce_field( 'quietype_save_photo', 'quietype_photo_nonce' );
	?>
	<div class="quietype-photo-editor">
		<p class="description">填写 HTTPS 展示图后先读取预览；只有点击“确认填入”才会改动尺寸和拍摄参数。展示图建议控制在 1600–2560px、2MB 以内，原图另行填写且只在访客主动打开时加载。</p>
		<div class="quietype-photo-lookup">
			<label class="screen-reader-text" for="quietype_photo_lookup_url">外链图片地址</label>
			<input class="large-text code" id="quietype_photo_lookup_url" type="url" value="<?php echo esc_attr( $data['image_url'] ); ?>" placeholder="https://pic.taifua.com/path/to/photo.jpg">
			<button class="button" id="quietype-photo-lookup" type="button">读取图片信息</button>
			<span id="quietype-photo-lookup-status" aria-live="polite"></span>
		</div>
		<div class="quietype-photo-preview" id="quietype-photo-preview" hidden>
			<img id="quietype-photo-preview-image" src="" alt="" hidden>
			<div>
				<strong id="quietype-photo-preview-size"></strong>
				<p id="quietype-photo-preview-exif"></p>
				<p id="quietype-photo-preview-device"></p>
				<button class="button button-primary" id="quietype-photo-confirm" type="button">确认填入</button>
			</div>
		</div>
		<table class="form-table" role="presentation">
			<tr><th><label for="quietype_photo_image_url">展示图片地址</label></th><td><input class="large-text code" id="quietype_photo_image_url" name="quietype_photo_image_url" type="url" value="<?php echo esc_attr( $data['image_url'] ); ?>" placeholder="https://pic.taifua.com/path/to/photo.jpg"><p class="description">用于网格与灯箱；留空时使用特色图片及其响应式尺寸。</p></td></tr>
			<tr><th><label for="quietype_photo_original_url">原图地址</label></th><td><input class="large-text code" id="quietype_photo_original_url" name="quietype_photo_original_url" type="url" value="<?php echo esc_attr( $data['original_url'] ); ?>" placeholder="https://pic.taifua.com/path/to/photo-original.jpg"><p class="description">可选。只作为灯箱中的“查看原图”入口，不会随页面或灯箱自动下载。</p></td></tr>
			<tr><th><label for="quietype_photo_captured_date">拍摄月份</label></th><td><input id="quietype_photo_captured_date" name="quietype_photo_captured_date" type="month" value="<?php echo esc_attr( $data['captured_date'] ); ?>" required><p class="description">用于前台按年份分组，精确到月份即可。</p></td></tr>
			<tr><th><label for="quietype_photo_location">地点</label></th><td><input class="regular-text" id="quietype_photo_location" name="quietype_photo_location" type="text" value="<?php echo esc_attr( $data['location'] ); ?>" placeholder="湖南 · 长沙"></td></tr>
			<tr><th>图片尺寸</th><td class="quietype-photo-pair"><label><span>宽度</span><input id="quietype_photo_width" name="quietype_photo_width" type="number" min="1" max="60000" value="<?php echo esc_attr( $data['width'] ?: '' ); ?>"></label><label><span>高度</span><input id="quietype_photo_height" name="quietype_photo_height" type="number" min="1" max="60000" value="<?php echo esc_attr( $data['height'] ?: '' ); ?>"></label></td></tr>
			<tr><th>曝光参数</th><td class="quietype-photo-parameters"><label><span>焦距</span><input id="quietype_photo_focal_length" name="quietype_photo_focal_length" type="text" value="<?php echo esc_attr( $data['focal_length'] ); ?>" placeholder="35mm"></label><label><span>光圈</span><input id="quietype_photo_aperture" name="quietype_photo_aperture" type="text" value="<?php echo esc_attr( $data['aperture'] ); ?>" placeholder="f/2.8"></label><label><span>快门</span><input id="quietype_photo_shutter_speed" name="quietype_photo_shutter_speed" type="text" value="<?php echo esc_attr( $data['shutter_speed'] ); ?>" placeholder="1/250s"></label><label><span>ISO</span><input id="quietype_photo_iso" name="quietype_photo_iso" type="number" min="1" max="409600" value="<?php echo esc_attr( $data['iso'] ); ?>" placeholder="100"></label></td></tr>
			<tr><th><label for="quietype_photo_camera">相机</label></th><td><input class="regular-text" id="quietype_photo_camera" name="quietype_photo_camera" type="text" value="<?php echo esc_attr( $data['camera'] ); ?>" placeholder="FUJIFILM X-T5"></td></tr>
			<tr><th><label for="quietype_photo_lens">镜头</label></th><td><input class="regular-text" id="quietype_photo_lens" name="quietype_photo_lens" type="text" value="<?php echo esc_attr( $data['lens'] ); ?>" placeholder="XF 23mm F2 R WR"></td></tr>
		</table>
	</div>
	<?php
}

function quietype_photo_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'photo' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
		return;
	}
	wp_add_inline_style( 'common', '.quietype-photo-editor{max-width:880px}.quietype-photo-lookup{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.quietype-photo-lookup>input{width:100%;max-width:560px}.quietype-photo-editor .form-table input[type="text"],.quietype-photo-editor .form-table input[type="url"],.quietype-photo-editor .form-table input[type="number"],.quietype-photo-editor .form-table input[type="month"]{min-height:32px}.quietype-photo-preview{display:grid;grid-template-columns:128px minmax(0,1fr);gap:16px;align-items:start;padding:12px;margin:14px 0;border:1px solid #dcdcde;background:#f6f7f7}.quietype-photo-preview[hidden]{display:none}.quietype-photo-preview img{display:block;width:128px;height:88px;background:#fff;object-fit:contain}.quietype-photo-preview p{margin:5px 0}.quietype-photo-pair,.quietype-photo-parameters{display:grid!important;gap:10px}.quietype-photo-pair{grid-template-columns:repeat(2,minmax(0,160px))}.quietype-photo-parameters{grid-template-columns:repeat(4,minmax(0,130px))}.quietype-photo-pair label,.quietype-photo-parameters label{display:grid;gap:4px}.quietype-photo-pair span,.quietype-photo-parameters span{color:#646970;font-size:12px}.quietype-photo-pair input,.quietype-photo-parameters input{width:100%}#postexcerpt #excerpt{min-height:82px}.column-quietype_photo_record{width:190px}.quietype-photo-record{white-space:nowrap}.quietype-photo-record small{color:#646970}@media(max-width:782px){.quietype-photo-lookup>input{max-width:none}.quietype-photo-parameters{grid-template-columns:repeat(2,minmax(0,1fr))}}' );
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_script( 'quietype-photo-admin', get_template_directory_uri() . '/assets/js/admin-photos.js', array(), quietype_asset_version( 'assets/js/admin-photos.js' ), true );
		wp_localize_script(
			'quietype-photo-admin',
			'quietypePhotoAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'quietype_photo_lookup' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'quietype_photo_admin_assets' );

function quietype_save_photo_meta( $post_id, $post ) {
	if ( ! isset( $_POST['quietype_photo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_photo_nonce'] ) ), 'quietype_save_photo' ) ) {
		return;
	}
	if ( ! $post instanceof WP_Post || 'photo' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$fields = array(
		'_quietype_photo_image_url'     => array( 'quietype_photo_image_url', 'quietype_sanitize_photo_url' ),
		'_quietype_photo_original_url'  => array( 'quietype_photo_original_url', 'quietype_sanitize_photo_url' ),
		'_quietype_photo_captured_date' => array( 'quietype_photo_captured_date', 'quietype_sanitize_photo_date' ),
		'_quietype_photo_location'      => array( 'quietype_photo_location', 'sanitize_text_field' ),
		'_quietype_photo_width'         => array( 'quietype_photo_width', 'quietype_sanitize_photo_dimension' ),
		'_quietype_photo_height'        => array( 'quietype_photo_height', 'quietype_sanitize_photo_dimension' ),
		'_quietype_photo_focal_length'  => array( 'quietype_photo_focal_length', 'quietype_sanitize_photo_parameter' ),
		'_quietype_photo_aperture'      => array( 'quietype_photo_aperture', 'quietype_sanitize_photo_parameter' ),
		'_quietype_photo_shutter_speed' => array( 'quietype_photo_shutter_speed', 'quietype_sanitize_photo_parameter' ),
		'_quietype_photo_iso'           => array( 'quietype_photo_iso', 'quietype_sanitize_photo_iso' ),
		'_quietype_photo_camera'        => array( 'quietype_photo_camera', 'quietype_sanitize_photo_camera' ),
		'_quietype_photo_lens'          => array( 'quietype_photo_lens', 'sanitize_text_field' ),
	);
	foreach ( $fields as $meta_key => $field ) {
		$value = isset( $_POST[ $field[0] ] ) ? call_user_func( $field[1], wp_unslash( $_POST[ $field[0] ] ) ) : '';
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}
}
add_action( 'save_post_photo', 'quietype_save_photo_meta', 10, 2 );

/** Supply a stable month for records inserted outside the classic editor. */
function quietype_ensure_photo_defaults( $post_id, $post ) {
	if ( ! $post instanceof WP_Post || 'photo' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! get_post_meta( $post_id, '_quietype_photo_captured_date', true ) ) {
		update_post_meta( $post_id, '_quietype_photo_captured_date', mysql2date( 'Y-m', $post->post_date ) );
	}
}
add_action( 'wp_after_insert_post', 'quietype_ensure_photo_defaults', 10, 2 );

function quietype_photo_admin_columns( $columns ) {
	$updated = array();
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$updated['quietype_photo_record'] = '拍摄记录';
			continue;
		}
		$updated[ $key ] = $label;
	}
	return $updated;
}
add_filter( 'manage_photo_posts_columns', 'quietype_photo_admin_columns' );

function quietype_render_photo_admin_column( $column, $post_id ) {
	if ( 'quietype_photo_record' !== $column ) {
		return;
	}
	$data = quietype_photo_data( $post_id );
	$size = $data['width'] && $data['height'] ? $data['width'] . '×' . $data['height'] : '尺寸未记录';
	echo '<span class="quietype-photo-record">' . esc_html( str_replace( '-', '.', $data['captured_date'] ) ) . ( $data['location'] ? ' · ' . esc_html( $data['location'] ) : '' ) . '<br><small>' . esc_html( $size ) . '</small></span>';
}
add_action( 'manage_photo_posts_custom_column', 'quietype_render_photo_admin_column', 10, 2 );

/** Convert an EXIF rational such as 35/1 to a safe decimal number. */
function quietype_exif_number( $value ) {
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}
	$value = trim( (string) $value );
	if ( preg_match( '#^(-?[0-9.]+)/([0-9.]+)$#', $value, $parts ) && (float) $parts[2] > 0 ) {
		return (float) $parts[1] / (float) $parts[2];
	}
	return is_numeric( $value ) ? (float) $value : 0.0;
}

function quietype_format_exif_focal_length( $value ) {
	$number = quietype_exif_number( $value );
	return $number > 0 ? rtrim( rtrim( number_format( $number, 1, '.', '' ), '0' ), '.' ) . 'mm' : '';
}

function quietype_format_exif_aperture( $value ) {
	$number = quietype_exif_number( $value );
	return $number > 0 ? 'f/' . rtrim( rtrim( number_format( $number, 1, '.', '' ), '0' ), '.' ) : '';
}

function quietype_format_exif_shutter( $value ) {
	$value  = trim( (string) $value );
	$number = quietype_exif_number( $value );
	if ( $number <= 0 ) {
		return '';
	}
	if ( false !== strpos( $value, '/' ) && $number < 1 ) {
		list( $numerator, $denominator ) = array_map( 'floatval', explode( '/', $value, 2 ) );
		if ( $numerator > 0 && $denominator > 0 ) {
			return rtrim( rtrim( number_format( $numerator, 2, '.', '' ), '0' ), '.' ) . '/' . rtrim( rtrim( number_format( $denominator, 2, '.', '' ), '0' ), '.' ) . 's';
		}
	}
	return ( $number < 1 ? rtrim( rtrim( number_format( $number, 3, '.', '' ), '0' ), '.' ) : rtrim( rtrim( number_format( $number, 1, '.', '' ), '0' ), '.' ) ) . 's';
}

/** Read dimensions and the small EXIF subset that the front end can display. */
function quietype_photo_metadata_from_binary( $binary ) {
	$size = @getimagesizefromstring( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid remote images are an expected lookup result.
	if ( ! $size || empty( $size[0] ) || empty( $size[1] ) ) {
		return new WP_Error( 'invalid_image', '远程地址没有返回可识别的图片。' );
	}
	$result = array(
		'width'         => quietype_sanitize_photo_dimension( $size[0] ),
		'height'        => quietype_sanitize_photo_dimension( $size[1] ),
		'captured_date' => '',
		'focal_length'  => '',
		'aperture'      => '',
		'shutter_speed' => '',
		'iso'           => '',
		'camera'        => '',
		'lens'          => '',
	);
	if ( ! function_exists( 'exif_read_data' ) ) {
		return $result;
	}
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$temp_file = wp_tempnam( 'quietype-photo' );
	if ( ! $temp_file || false === file_put_contents( $temp_file, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- EXIF requires a temporary local path.
		return $result;
	}
	$exif = @exif_read_data( $temp_file, null, true, false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- EXIF is optional and malformed metadata is common.
	wp_delete_file( $temp_file );
	if ( ! is_array( $exif ) ) {
		return $result;
	}
	$ifd0 = isset( $exif['IFD0'] ) && is_array( $exif['IFD0'] ) ? $exif['IFD0'] : array();
	$sub  = isset( $exif['EXIF'] ) && is_array( $exif['EXIF'] ) ? $exif['EXIF'] : array();
	$date = $sub['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? '';
	if ( preg_match( '/^(\d{4}):(\d{2}):/', (string) $date, $date_parts ) ) {
		$result['captured_date'] = quietype_sanitize_photo_date( $date_parts[1] . '-' . $date_parts[2] );
	}
	$result['focal_length']  = quietype_format_exif_focal_length( $sub['FocalLength'] ?? '' );
	$result['aperture']      = quietype_format_exif_aperture( $sub['FNumber'] ?? '' );
	$result['shutter_speed'] = quietype_format_exif_shutter( $sub['ExposureTime'] ?? '' );
	$result['iso']           = quietype_sanitize_photo_iso( $sub['ISOSpeedRatings'] ?? $sub['PhotographicSensitivity'] ?? '' );
	$make                    = trim( (string) ( $ifd0['Make'] ?? '' ) );
	$model                   = trim( (string) ( $ifd0['Model'] ?? '' ) );
	$result['camera']        = quietype_sanitize_photo_camera( $make && 0 !== stripos( $model, $make ) ? trim( $make . ' ' . $model ) : $model );
	$result['lens']          = sanitize_text_field( $sub['LensModel'] ?? '' );
	return $result;
}

/** Administrator-only remote lookup returns a preview and never saves content. */
function quietype_ajax_lookup_photo() {
	check_ajax_referer( 'quietype_photo_lookup', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => '没有读取图片信息的权限。' ), 403 );
	}
	$url = quietype_sanitize_photo_url( isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '' );
	if ( ! $url ) {
		wp_send_json_error( array( 'message' => '请输入有效的 HTTPS 图片地址。' ), 422 );
	}
	$cache_key = 'quietype_photo_info_' . md5( $url );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		$cached['url'] = $url;
		wp_send_json_success( $cached );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 12,
			'redirection'         => 3,
			'limit_response_size' => 8 * MB_IN_BYTES,
			'headers'             => array(
				'Accept'     => 'image/avif,image/webp,image/jpeg,image/png,image/*;q=0.8',
				'User-Agent' => 'Quietype/' . QUIETYPE_VERSION . '; ' . home_url( '/' ),
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => '暂时无法读取远程图片，请检查地址或手工填写。' ), 502 );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		wp_send_json_error( array( 'message' => '远程图片返回了异常状态，请稍后重试。' ), $status >= 400 && $status < 600 ? $status : 502 );
	}
	$body   = wp_remote_retrieve_body( $response );
	$result = quietype_photo_metadata_from_binary( $body );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
	}
	$content_length        = absint( wp_remote_retrieve_header( $response, 'content-length' ) );
	$result['url']          = $url;
	$result['file_size']    = max( strlen( $body ), $content_length );
	$result['is_oversized'] = $result['file_size'] > 10 * MB_IN_BYTES;
	set_transient( $cache_key, $result, 30 * DAY_IN_SECONDS );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_quietype_lookup_photo', 'quietype_ajax_lookup_photo' );

/** Schedule one optional archive-cache purge after a photo changes. */
function quietype_schedule_photo_archive_cache_purge( $post_id, $post = null ) {
	if ( ! $post instanceof WP_Post ) {
		$post = get_post( $post_id );
	}
	if ( ! $post instanceof WP_Post || 'photo' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	static $scheduled = false;
	if ( $scheduled ) {
		return;
	}
	$scheduled = true;
	add_action( 'shutdown', 'quietype_purge_photo_archive_cache', PHP_INT_MAX );
}
add_action( 'save_post_photo', 'quietype_schedule_photo_archive_cache_purge', 100, 2 );
add_action( 'deleted_post', 'quietype_schedule_photo_archive_cache_purge', 10, 2 );

/** Purge /photos through an explicitly configured loopback-only endpoint. */
function quietype_purge_photo_archive_cache() {
	$endpoint = defined( 'QUIETYPE_PHOTO_CACHE_PURGE_ENDPOINT' ) ? (string) QUIETYPE_PHOTO_CACHE_PURGE_ENDPOINT : '';
	$endpoint = (string) apply_filters( 'quietype_photo_archive_cache_purge_endpoint', $endpoint );
	$endpoint = esc_url_raw( $endpoint );
	if ( ! $endpoint ) {
		return;
	}
	$host    = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$headers = $host ? array( 'Host' => $host ) : array();
	$response = wp_remote_request(
		$endpoint,
		array(
			'method'      => 'PURGE',
			'timeout'     => 2,
			'redirection' => 0,
			'blocking'    => true,
			'headers'     => $headers,
			'user-agent'  => 'Quietype/' . QUIETYPE_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_wp_error( $response ) ) {
		error_log( 'Quietype photo cache purge failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

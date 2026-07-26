<?php
/**
 * Quietype's consolidated theme settings screen.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read a setting while preserving values created by older Customizer builds. */
function quietype_get_setting( $option_name, $default = '' ) {
	return get_option( $option_name, get_theme_mod( $option_name, $default ) );
}

/** Register the settings page under Appearance. */
function quietype_add_settings_page() {
	add_theme_page( 'Quietype 设置', 'Quietype 设置', 'manage_options', 'quietype-settings', 'quietype_render_settings_page' );
}
add_action( 'admin_menu', 'quietype_add_settings_page' );

/** Register every Quietype-owned preference in one settings group. */
function quietype_register_admin_settings() {
	$settings = array(
		'quietype_github_url'                 => array( 'string', 'https://github.com/taifuer', 'esc_url_raw' ),
		'quietype_contact_email'              => array( 'string', 'taifu@taifua.com', 'sanitize_email' ),
		'quietype_icp_number'                 => array( 'string', '湘ICP备17002466号', 'sanitize_text_field' ),
		'quietype_start_year'                 => array( 'integer', 2017, 'quietype_sanitize_start_year' ),
		'quietype_books_page_title'           => array( 'string', '万卷古今', 'quietype_sanitize_archive_title' ),
		'quietype_books_page_eyebrow'         => array( 'string', 'BOOKS', 'quietype_sanitize_archive_eyebrow' ),
		'quietype_books_page_intro'           => array( 'string', '', 'quietype_sanitize_archive_intro' ),
		'quietype_photos_page_title'          => array( 'string', '万物静观', 'quietype_sanitize_archive_title' ),
		'quietype_photos_page_eyebrow'        => array( 'string', 'PHOTOS', 'quietype_sanitize_archive_eyebrow' ),
		'quietype_photos_page_intro'          => array( 'string', '', 'quietype_sanitize_archive_intro' ),
		'quietype_link_check_enabled'         => array( 'boolean', true, 'quietype_sanitize_checkbox' ),
		'quietype_article_copyright_enabled'  => array( 'boolean', true, 'quietype_sanitize_checkbox' ),
		'quietype_article_author_name'        => array( 'string', '小傅', 'sanitize_text_field' ),
		'quietype_hide_admin_bar'             => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_disable_revisions'          => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_seo_enabled'                => array( 'boolean', true, 'quietype_sanitize_checkbox' ),
		'quietype_seo_description'            => array( 'string', '', 'quietype_sanitize_seo_description' ),
		'quietype_seo_keywords'               => array( 'string', '', 'quietype_sanitize_seo_keywords' ),
		'quietype_social_image_url'           => array( 'string', '', 'esc_url_raw' ),
		'quietype_gravatar_base_url'          => array( 'string', 'https://gravatar.loli.net/avatar/', 'quietype_sanitize_gravatar_base_url' ),
		'quietype_login_gate_enabled'         => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_login_gate_key'             => array( 'string', 'user', 'quietype_sanitize_login_gate_key' ),
		'quietype_login_gate_value'           => array( 'string', '', 'quietype_sanitize_login_gate_value' ),
		'quietype_login_captcha_enabled'      => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_xmlrpc_auth_disabled'       => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_smtp_enabled'               => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_smtp_host'                  => array( 'string', '', 'quietype_sanitize_smtp_host' ),
		'quietype_smtp_port'                  => array( 'integer', 587, 'quietype_sanitize_smtp_port' ),
		'quietype_smtp_encryption'            => array( 'string', 'tls', 'quietype_sanitize_smtp_encryption' ),
		'quietype_smtp_auth'                  => array( 'boolean', true, 'quietype_sanitize_checkbox' ),
		'quietype_smtp_username'              => array( 'string', '', 'sanitize_text_field' ),
		'quietype_smtp_password'              => array( 'string', '', 'quietype_sanitize_smtp_password' ),
		'quietype_smtp_from_email'            => array( 'string', '', 'sanitize_email' ),
		'quietype_smtp_from_name'             => array( 'string', '', 'sanitize_text_field' ),
		'quietype_login_notification_enabled' => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_comment_notification_enabled' => array( 'boolean', false, 'quietype_sanitize_checkbox' ),
		'quietype_head_code'                  => array( 'string', '', 'quietype_sanitize_head_code' ),
		'quietype_footer_code'                => array( 'string', '', 'quietype_sanitize_footer_code' ),
	);

	foreach ( $settings as $option_name => $definition ) {
		register_setting(
			'quietype_settings',
			$option_name,
			array(
				'type'              => $definition[0],
				'default'           => $definition[1],
				'sanitize_callback' => $definition[2],
			)
		);
	}
}
add_action( 'admin_init', 'quietype_register_admin_settings' );

/** Carry Customizer values forward before registered defaults are applied. */
function quietype_migrate_legacy_theme_settings() {
	$legacy_options = array(
		'quietype_github_url',
		'quietype_contact_email',
		'quietype_login_gate_enabled',
		'quietype_login_gate_key',
		'quietype_login_gate_value',
		'quietype_login_captcha_enabled',
		'quietype_xmlrpc_auth_disabled',
		'quietype_head_code',
		'quietype_footer_code',
	);
	foreach ( $legacy_options as $option_name ) {
		if ( false !== get_option( $option_name, false ) ) {
			continue;
		}
		$legacy_value = get_theme_mod( $option_name, null );
		if ( null !== $legacy_value ) {
			add_option( $option_name, $legacy_value, '', false );
		}
	}
}
add_action( 'after_setup_theme', 'quietype_migrate_legacy_theme_settings', 20 );

/** Preserve executable code unless a trusted administrator is saving it. */
function quietype_sanitize_code_option( $value, $option_name ) {
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		add_settings_error( 'quietype_settings', 'quietype_code_permission', '当前账户无权保存未过滤的 HTML 或 JavaScript。', 'error' );
		return get_option( $option_name, '' );
	}
	return trim( (string) $value );
}

function quietype_sanitize_head_code( $value ) {
	return quietype_sanitize_code_option( $value, 'quietype_head_code' );
}

function quietype_sanitize_footer_code( $value ) {
	return quietype_sanitize_code_option( $value, 'quietype_footer_code' );
}

function quietype_sanitize_seo_description( $value ) {
	$value = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) );
	return mb_substr( trim( $value ), 0, 300 );
}

function quietype_sanitize_seo_keywords( $value ) {
	$value = preg_replace( '/[\r\n，、]+/u', ',', wp_strip_all_tags( (string) $value ) );
	$parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );
	return mb_substr( implode( ',', array_unique( $parts ) ), 0, 500 );
}

/** Keep archive display copy concise and free of markup. */
function quietype_sanitize_archive_title( $value ) {
	$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
	return mb_substr( $value, 0, 80 );
}

function quietype_sanitize_archive_eyebrow( $value ) {
	$value = strtoupper( sanitize_text_field( (string) $value ) );
	$value = preg_replace( '/[^A-Z0-9 .&_-]/', '', $value );
	return substr( trim( $value ), 0, 32 );
}

function quietype_sanitize_archive_intro( $value ) {
	$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
	return mb_substr( $value, 0, 180 );
}

/** Return one configured archive label with a stable fallback for required fields. */
function quietype_archive_page_text( $post_type, $field ) {
	$defaults = array(
		'book'  => array(
			'title'   => '万卷古今',
			'eyebrow' => 'BOOKS',
			'intro'   => '',
		),
		'photo' => array(
			'title'   => '万物静观',
			'eyebrow' => 'PHOTOS',
			'intro'   => '',
		),
	);
	if ( ! isset( $defaults[ $post_type ][ $field ] ) ) {
		return '';
	}
	$value = (string) quietype_get_setting( 'quietype_' . $post_type . 's_page_' . $field, $defaults[ $post_type ][ $field ] );
	return '' !== $value || 'intro' === $field ? $value : $defaults[ $post_type ][ $field ];
}

/** Keep the browser and social title aligned with the visible archive heading. */
function quietype_archive_document_title( $parts ) {
	if ( is_post_type_archive( 'book' ) ) {
		$parts['title'] = quietype_archive_page_text( 'book', 'title' );
	} elseif ( is_post_type_archive( 'photo' ) ) {
		$parts['title'] = quietype_archive_page_text( 'photo', 'title' );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'quietype_archive_document_title' );

/** Keep the copyright range plausible and never later than the current year. */
function quietype_sanitize_start_year( $value ) {
	$year = absint( $value );
	return max( 1990, min( (int) gmdate( 'Y' ), $year ?: 2017 ) );
}

/** Accept an HTTP(S) avatar base URL, or an empty value to disable rewriting. */
function quietype_sanitize_gravatar_base_url( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$url = esc_url_raw( $value, array( 'http', 'https' ) );
	if ( ! $url ) {
		add_settings_error( 'quietype_settings', 'quietype_gravatar_url', 'Gravatar 地址必须是有效的 HTTP 或 HTTPS 地址。', 'error' );
		return get_option( 'quietype_gravatar_base_url', 'https://gravatar.loli.net/avatar/' );
	}
	return trailingslashit( $url );
}

function quietype_sanitize_smtp_host( $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value || filter_var( $value, FILTER_VALIDATE_IP ) || preg_match( '/^(?=.{1,253}$)(?!-)[a-z0-9.-]+(?<!-)$/i', $value ) ) {
		return $value;
	}
	add_settings_error( 'quietype_settings', 'quietype_smtp_host', 'SMTP 主机应为域名或 IP 地址，不要包含协议、端口或路径。', 'error' );
	return get_option( 'quietype_smtp_host', '' );
}

function quietype_sanitize_smtp_port( $value ) {
	$port = absint( $value );
	return $port >= 1 && $port <= 65535 ? $port : 587;
}

function quietype_sanitize_smtp_encryption( $value ) {
	return in_array( $value, array( 'none', 'tls', 'ssl' ), true ) ? $value : 'tls';
}

/** An empty password field intentionally keeps the previously saved secret. */
function quietype_sanitize_smtp_password( $value ) {
	if ( ! empty( $_POST['quietype_smtp_password_clear'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the registered settings nonce.
		return '';
	}
	$value = (string) $value;
	return '' === $value ? get_option( 'quietype_smtp_password', '' ) : $value;
}

/** Load the native code editor and compact settings-page presentation. */
function quietype_settings_assets( $hook_suffix ) {
	if ( 'appearance_page_quietype-settings' !== $hook_suffix ) {
		return;
	}
	$editor = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
	if ( false !== $editor ) {
		wp_enqueue_script( 'code-editor' );
		wp_enqueue_style( 'code-editor' );
		$settings = wp_json_encode( $editor );
		$script   = "jQuery(function(){['quietype_head_code','quietype_footer_code'].forEach(function(id){if(document.getElementById(id)){wp.codeEditor.initialize(id,{$settings});}});});";
		wp_add_inline_script( 'code-editor', $script );
	}
	wp_add_inline_style( 'common', '.quietype-settings{max-width:1040px}.quietype-settings__nav{position:sticky;top:32px;z-index:2;display:flex;gap:18px;padding:12px 0;background:#f0f0f1;border-bottom:1px solid #c3c4c7}.quietype-settings__nav a{text-decoration:none;font-weight:600}.quietype-settings__section{scroll-margin-top:92px;padding:22px 24px;margin:18px 0;background:#fff;border:1px solid #dcdcde;border-radius:4px}.quietype-settings__section>h2{margin-top:0}.quietype-settings .form-table{margin-top:0}.quietype-settings .form-table th{width:210px}.quietype-settings__note{color:#646970}.quietype-settings__inline{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.quietype-settings__inline input[type=text],.quietype-settings__inline input[type=number],.quietype-settings__inline select{max-width:180px}' );
}
add_action( 'admin_enqueue_scripts', 'quietype_settings_assets' );

/** Print a settings checkbox with an explicit unchecked value. */
function quietype_settings_checkbox( $option_name, $label, $default = false ) {
	?>
	<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>" value="0">
	<label><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" value="1" <?php checked( quietype_get_setting( $option_name, $default ) ); ?>> <?php echo esc_html( $label ); ?></label>
	<?php
}

/** Render all Quietype-owned settings in a single, navigable screen. */
function quietype_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$can_edit_code = current_user_can( 'unfiltered_html' );
	$smtp_password_saved = '' !== quietype_get_setting( 'quietype_smtp_password', '' ) || defined( 'QUIETYPE_SMTP_PASSWORD' );
	$mail_error = get_transient( 'quietype_last_mail_error' );
	?>
	<div class="wrap quietype-settings">
		<h1>Quietype 设置</h1>
		<p class="quietype-settings__note">主题专属功能集中在这里；站点标题、菜单和额外 CSS 继续使用 WordPress 原生界面。</p>
		<?php settings_errors( 'quietype_settings' ); ?>
		<?php if ( isset( $_GET['quietype_mail'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice <?php echo 'sent' === $_GET['quietype_mail'] ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo 'sent' === $_GET['quietype_mail'] ? '测试邮件已交给 SMTP 服务器。' : '测试邮件发送失败，请检查 SMTP 参数和服务器日志。'; ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['quietype_revisions_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>已永久删除 <?php echo esc_html( absint( $_GET['quietype_revisions_deleted'] ) ); ?> 条内容历史版本。</p></div>
		<?php endif; ?>
		<nav class="quietype-settings__nav" aria-label="设置分区">
			<a href="#quietype-section-site">站点</a><a href="#quietype-section-archives">内容页面</a><a href="#quietype-section-seo">SEO</a><a href="#quietype-section-access">访问</a><a href="#quietype-section-wordpress">WordPress</a><a href="#quietype-section-security">安全</a><a href="#quietype-section-mail">邮件</a><a href="#quietype-section-code">代码</a>
		</nav>
		<form action="options.php" method="post">
			<?php settings_fields( 'quietype_settings' ); ?>

			<section class="quietype-settings__section" id="quietype-section-site">
				<h2>站点与页脚</h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="quietype_contact_email">联系邮箱</label></th><td><input class="regular-text" id="quietype_contact_email" name="quietype_contact_email" type="email" value="<?php echo esc_attr( quietype_get_setting( 'quietype_contact_email', 'taifu@taifua.com' ) ); ?>"><p class="description">留空可隐藏页脚邮件图标。</p></td></tr>
					<tr><th><label for="quietype_github_url">GitHub 地址</label></th><td><input class="regular-text" id="quietype_github_url" name="quietype_github_url" type="url" value="<?php echo esc_attr( quietype_get_setting( 'quietype_github_url', 'https://github.com/taifuer' ) ); ?>"><p class="description">留空可隐藏页脚 GitHub 图标。</p></td></tr>
					<tr><th><label for="quietype_icp_number">备案号</label></th><td><input class="regular-text" id="quietype_icp_number" name="quietype_icp_number" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_icp_number', '湘ICP备17002466号' ) ); ?>"><p class="description">留空可隐藏页脚备案信息，填写后自动链接至工信部备案系统。</p></td></tr>
					<tr><th><label for="quietype_start_year">建站年份</label></th><td><input class="small-text" id="quietype_start_year" name="quietype_start_year" type="number" min="1990" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" value="<?php echo esc_attr( quietype_get_setting( 'quietype_start_year', 2017 ) ); ?>"><p class="description">用于页脚版权年份范围。</p></td></tr>
					<tr><th>友链检测</th><td><?php quietype_settings_checkbox( 'quietype_link_check_enabled', '每天分批检测友链可达性', true ); ?><p class="description">每天最多检测五条；连续失败三次只会进入“待确认”，不会自动在前台标记失联。</p></td></tr>
					<tr><th>文章版权声明</th><td><?php quietype_settings_checkbox( 'quietype_article_copyright_enabled', '在文章正文末尾显示 CC BY-NC-SA 4.0 声明', true ); ?></td></tr>
					<tr><th><label for="quietype_article_author_name">版权署名</label></th><td><input class="regular-text" id="quietype_article_author_name" name="quietype_article_author_name" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_article_author_name', '小傅' ) ); ?>"><p class="description">留空时使用文章作者的 WordPress 显示名称，署名链接回本站首页。</p></td></tr>
				</table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-archives">
				<h2>书籍与照片页面</h2>
				<p>控制年度书架与图库页首的展示文字。简介留空时不输出对应区域。</p>
				<table class="form-table" role="presentation">
					<tr><th><label for="quietype_books_page_title">书籍页标题</label></th><td><input class="regular-text" id="quietype_books_page_title" name="quietype_books_page_title" type="text" maxlength="80" value="<?php echo esc_attr( quietype_get_setting( 'quietype_books_page_title', '万卷古今' ) ); ?>" placeholder="万卷古今"></td></tr>
					<tr><th><label for="quietype_books_page_eyebrow">书籍页英文标识</label></th><td><input class="regular-text code" id="quietype_books_page_eyebrow" name="quietype_books_page_eyebrow" type="text" maxlength="32" value="<?php echo esc_attr( quietype_get_setting( 'quietype_books_page_eyebrow', 'BOOKS' ) ); ?>" placeholder="BOOKS"></td></tr>
					<tr><th><label for="quietype_books_page_intro">书籍页简介</label></th><td><textarea class="large-text" id="quietype_books_page_intro" name="quietype_books_page_intro" rows="2" maxlength="180" placeholder="留空不显示"><?php echo esc_textarea( quietype_get_setting( 'quietype_books_page_intro', '' ) ); ?></textarea></td></tr>
					<tr><th><label for="quietype_photos_page_title">照片页标题</label></th><td><input class="regular-text" id="quietype_photos_page_title" name="quietype_photos_page_title" type="text" maxlength="80" value="<?php echo esc_attr( quietype_get_setting( 'quietype_photos_page_title', '万物静观' ) ); ?>" placeholder="万物静观"></td></tr>
					<tr><th><label for="quietype_photos_page_eyebrow">照片页英文标识</label></th><td><input class="regular-text code" id="quietype_photos_page_eyebrow" name="quietype_photos_page_eyebrow" type="text" maxlength="32" value="<?php echo esc_attr( quietype_get_setting( 'quietype_photos_page_eyebrow', 'PHOTOS' ) ); ?>" placeholder="PHOTOS"></td></tr>
					<tr><th><label for="quietype_photos_page_intro">照片页简介</label></th><td><textarea class="large-text" id="quietype_photos_page_intro" name="quietype_photos_page_intro" rows="2" maxlength="180" placeholder="留空不显示"><?php echo esc_textarea( quietype_get_setting( 'quietype_photos_page_intro', '' ) ); ?></textarea></td></tr>
				</table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-seo">
				<h2>搜索引擎优化</h2>
				<p>未检测到常见 SEO 插件时，Quietype 输出描述、关键词和基础 Open Graph。文章优先使用自定义摘要，否则自动提取正文；关键词优先使用文章标签。</p>
				<table class="form-table" role="presentation">
					<tr><th>主题 SEO</th><td><?php quietype_settings_checkbox( 'quietype_seo_enabled', '启用轻量 SEO 元数据', true ); ?><p class="description">检测到 Yoast SEO、Rank Math、All in One SEO、SEOPress 或 The SEO Framework 时自动停用输出，避免重复。</p></td></tr>
					<tr><th><label for="quietype_seo_description">站点描述</label></th><td><textarea class="large-text" id="quietype_seo_description" name="quietype_seo_description" rows="3" maxlength="300"><?php echo esc_textarea( quietype_get_setting( 'quietype_seo_description', '' ) ); ?></textarea><p class="description">首页和无法提取独立摘要的页面使用；留空则使用 WordPress 站点副标题。</p></td></tr>
					<tr><th><label for="quietype_seo_keywords">站点关键词</label></th><td><input class="large-text" id="quietype_seo_keywords" name="quietype_seo_keywords" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_seo_keywords', '' ) ); ?>"><p class="description">使用英文逗号分隔。搜索引擎通常不再依赖 keywords，但可为部分中文检索服务保留。</p></td></tr>
					<tr><th><label for="quietype_social_image_url">默认分享图</label></th><td><input class="large-text code" id="quietype_social_image_url" name="quietype_social_image_url" type="url" value="<?php echo esc_attr( quietype_get_setting( 'quietype_social_image_url', '' ) ); ?>" placeholder="https://example.com/social-card.jpg"><p class="description">建议使用 1200×630 的 JPG 或 PNG。留空使用主题预览图；文章仍优先使用特色图和第一张正文图片。</p></td></tr>
				</table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-access">
				<h2>中国大陆访问</h2>
				<table class="form-table" role="presentation"><tr><th><label for="quietype_gravatar_base_url">Gravatar 地址</label></th><td><input class="regular-text code" id="quietype_gravatar_base_url" name="quietype_gravatar_base_url" type="url" value="<?php echo esc_attr( quietype_get_setting( 'quietype_gravatar_base_url', 'https://gravatar.loli.net/avatar/' ) ); ?>" placeholder="https://gravatar.loli.net/avatar/"><p class="description">留空则保留 WordPress 原始 Gravatar 地址。</p></td></tr></table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-wordpress">
				<h2>WordPress 优化</h2>
				<table class="form-table" role="presentation">
					<tr><th>前台工具栏</th><td><?php quietype_settings_checkbox( 'quietype_hide_admin_bar', '登录后隐藏网站前台顶部管理工具栏' ); ?><p class="description">仅影响网站前台，WordPress 后台工具栏保持不变。</p></td></tr>
					<tr><th>内容历史版本</th><td><?php quietype_settings_checkbox( 'quietype_disable_revisions', '停止为文章、页面和书籍保存新的历史版本' ); ?><p class="description">自动保存仍然保留；此开关不会自动删除数据库中已有的历史版本。</p></td></tr>
				</table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-security">
				<h2>登录与安全</h2>
				<p>入口参数仅在首次访问时换取 12 小时安全 Cookie，随后自动跳转到不含入口值的地址。启用前请保存完整入口。</p>
				<table class="form-table" role="presentation">
					<tr><th>自定义登录入口</th><td><?php quietype_settings_checkbox( 'quietype_login_gate_enabled', '启用入口保护' ); ?></td></tr>
					<tr><th><label for="quietype_login_gate_key">入口参数名</label></th><td><input class="regular-text code" id="quietype_login_gate_key" name="quietype_login_gate_key" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_login_gate_key', 'user' ) ); ?>"><p class="description">仅支持字母、数字、下划线和短横线。</p></td></tr>
					<tr><th><label for="quietype_login_gate_value">入口参数值</label></th><td><input class="regular-text code" id="quietype_login_gate_value" name="quietype_login_gate_value" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_login_gate_value', '' ) ); ?>" minlength="24" autocomplete="off"><p class="description">至少 24 位随机字符串；请勿在截图、分析平台或公开文档中暴露。</p></td></tr>
					<tr><th>登录验证码</th><td><?php quietype_settings_checkbox( 'quietype_login_captcha_enabled', '启用一次性算术验证码' ); ?></td></tr>
					<tr><th>XML-RPC</th><td><?php quietype_settings_checkbox( 'quietype_xmlrpc_auth_disabled', '禁用 XML-RPC 认证与 Pingback' ); ?><p class="description">启用后 WordPress App 等旧式客户端将不可用。</p></td></tr>
				</table>
			</section>

			<section class="quietype-settings__section" id="quietype-section-mail">
				<h2>SMTP 与安全通知</h2>
				<p>SMTP 会接管站点所有通过 <code>wp_mail()</code> 发送的邮件。密码将以原值保存在 WordPress 数据库中，请确保数据库和备份访问受控。</p>
				<table class="form-table" role="presentation">
					<tr><th>SMTP</th><td><?php quietype_settings_checkbox( 'quietype_smtp_enabled', '启用 SMTP 发信' ); ?></td></tr>
					<tr><th><label for="quietype_smtp_host">服务器</label></th><td><input class="regular-text code" id="quietype_smtp_host" name="quietype_smtp_host" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_smtp_host', '' ) ); ?>" placeholder="smtp.example.com"></td></tr>
					<tr><th>连接</th><td><div class="quietype-settings__inline"><label>端口 <input id="quietype_smtp_port" name="quietype_smtp_port" type="number" min="1" max="65535" value="<?php echo esc_attr( quietype_get_setting( 'quietype_smtp_port', 587 ) ); ?>"></label><label>加密 <select name="quietype_smtp_encryption"><option value="tls" <?php selected( quietype_get_setting( 'quietype_smtp_encryption', 'tls' ), 'tls' ); ?>>TLS / STARTTLS</option><option value="ssl" <?php selected( quietype_get_setting( 'quietype_smtp_encryption', 'tls' ), 'ssl' ); ?>>SSL / SMTPS</option><option value="none" <?php selected( quietype_get_setting( 'quietype_smtp_encryption', 'tls' ), 'none' ); ?>>无</option></select></label><?php quietype_settings_checkbox( 'quietype_smtp_auth', '需要身份验证', true ); ?></div></td></tr>
					<tr><th><label for="quietype_smtp_username">用户名</label></th><td><input class="regular-text code" id="quietype_smtp_username" name="quietype_smtp_username" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_smtp_username', '' ) ); ?>" autocomplete="off"></td></tr>
					<tr><th><label for="quietype_smtp_password">密码/授权码</label></th><td><input class="regular-text code" id="quietype_smtp_password" name="quietype_smtp_password" type="password" value="" autocomplete="new-password" placeholder="留空保持现有密码"> <label><input type="checkbox" name="quietype_smtp_password_clear" value="1"> 清除已保存密码</label><p class="description">当前：<?php echo $smtp_password_saved ? '已配置' : '未配置'; ?>。推荐使用独立授权码；也可在 <code>wp-config.php</code> 定义 <code>QUIETYPE_SMTP_PASSWORD</code>，常量优先且不会显示在数据库中。</p></td></tr>
					<tr><th><label for="quietype_smtp_from_email">发件地址</label></th><td><input class="regular-text" id="quietype_smtp_from_email" name="quietype_smtp_from_email" type="email" value="<?php echo esc_attr( quietype_get_setting( 'quietype_smtp_from_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></td></tr>
					<tr><th><label for="quietype_smtp_from_name">发件名称</label></th><td><input class="regular-text" id="quietype_smtp_from_name" name="quietype_smtp_from_name" type="text" value="<?php echo esc_attr( quietype_get_setting( 'quietype_smtp_from_name', '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
					<tr><th>通知事件</th><td><?php quietype_settings_checkbox( 'quietype_login_notification_enabled', '管理员成功登录时通知' ); ?><br><?php quietype_settings_checkbox( 'quietype_comment_notification_enabled', '收到新评论时通知管理员' ); ?><p class="description">登录通知仅针对拥有站点管理权限的账户，并为同一账户设置 5 分钟防重复；垃圾评论、Pingback 和 Trackback 不通知。</p></td></tr>
				</table>
				<?php if ( is_array( $mail_error ) && ! empty( $mail_error['message'] ) ) : ?><div class="notice notice-error inline"><p><strong>最近一次发信错误：</strong><?php echo esc_html( $mail_error['message'] ); ?> <span class="description"><?php echo esc_html( $mail_error['time'] ?? '' ); ?></span></p></div><?php endif; ?>
			</section>

			<section class="quietype-settings__section" id="quietype-section-code">
				<h2>自定义代码</h2><p>仅粘贴可信代码。样式仍建议优先使用 WordPress“额外 CSS”。</p>
				<?php if ( ! $can_edit_code ) : ?><div class="notice notice-warning inline"><p>当前账户无权保存未过滤的 HTML 或 JavaScript，代码框为只读。</p></div><?php endif; ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="quietype_head_code">Head 自定义代码</label></th><td><textarea class="large-text code" id="quietype_head_code" name="quietype_head_code" rows="9" <?php disabled( ! $can_edit_code ); ?>><?php echo esc_textarea( quietype_get_setting( 'quietype_head_code', '' ) ); ?></textarea><p class="description">输出在 <code>&lt;/head&gt;</code> 前。</p></td></tr>
					<tr><th><label for="quietype_footer_code">Footer 自定义代码</label></th><td><textarea class="large-text code" id="quietype_footer_code" name="quietype_footer_code" rows="9" <?php disabled( ! $can_edit_code ); ?>><?php echo esc_textarea( quietype_get_setting( 'quietype_footer_code', '' ) ); ?></textarea><p class="description">输出在 <code>&lt;/body&gt;</code> 前。</p></td></tr>
				</table>
			</section>
			<?php submit_button( '保存全部设置' ); ?>
		</form>

		<section class="quietype-settings__section">
			<h2>发送测试邮件</h2><p>先保存 SMTP 参数，再向 WordPress 管理员邮箱 <code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code> 发送测试邮件。</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="quietype_send_test_email"><?php wp_nonce_field( 'quietype_send_test_email' ); ?><?php $test_button_attributes = quietype_get_setting( 'quietype_smtp_enabled', false ) ? array() : array( 'disabled' => 'disabled' ); ?><?php submit_button( '发送测试邮件', 'secondary', 'submit', false, $test_button_attributes ); ?></form>
		</section>

		<section class="quietype-settings__section" id="quietype-section-revision-cleanup">
			<h2>历史版本清理</h2>
			<p>当前数据库中有 <strong><?php echo esc_html( quietype_revision_count() ); ?></strong> 条文章、页面或书籍历史版本。删除不可撤销，但不会删除已发布内容和自动草稿。</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('确定永久删除全部文章、页面和书籍历史版本吗？此操作不可撤销。');"><input type="hidden" name="action" value="quietype_delete_revisions"><?php wp_nonce_field( 'quietype_delete_revisions' ); ?><?php submit_button( '删除现有历史版本', 'delete', 'submit', false ); ?></form>
		</section>
	</div>
	<?php
}

/** Print administrator-supplied code at the selected document boundary. */
function quietype_print_custom_head_code() {
	$code = quietype_get_setting( 'quietype_head_code', '' );
	if ( $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit unfiltered_html administrator setting.
	}
}
add_action( 'wp_head', 'quietype_print_custom_head_code', 99 );

function quietype_print_custom_footer_code() {
	$code = quietype_get_setting( 'quietype_footer_code', '' );
	if ( $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit unfiltered_html administrator setting.
	}
}
add_action( 'wp_footer', 'quietype_print_custom_footer_code', 99 );

/** Count revisions belonging to theme-managed editorial content. */
function quietype_revision_count() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} revisions INNER JOIN {$wpdb->posts} parents ON parents.ID = revisions.post_parent WHERE revisions.post_type = 'revision' AND parents.post_type IN ('post','page','book')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query without user input.
}

/** Permanently remove existing content revisions after explicit confirmation. */
function quietype_handle_delete_revisions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权执行此操作。' );
	}
	check_admin_referer( 'quietype_delete_revisions' );
	global $wpdb;
	$revision_ids = $wpdb->get_col( "SELECT revisions.ID FROM {$wpdb->posts} revisions INNER JOIN {$wpdb->posts} parents ON parents.ID = revisions.post_parent WHERE revisions.post_type = 'revision' AND parents.post_type IN ('post','page','book')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query without user input.
	$deleted = 0;
	foreach ( $revision_ids as $revision_id ) {
		if ( wp_delete_post_revision( (int) $revision_id ) ) {
			++$deleted;
		}
	}
	$url = add_query_arg( 'quietype_revisions_deleted', $deleted, admin_url( 'themes.php?page=quietype-settings#quietype-section-revision-cleanup' ) );
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_quietype_delete_revisions', 'quietype_handle_delete_revisions' );

<?php
/**
 * Quietype's small site-level settings screen.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the settings page under Appearance. */
function quietype_add_settings_page() {
	add_theme_page(
		'Quietype 设置',
		'Quietype 设置',
		'manage_options',
		'quietype-settings',
		'quietype_render_settings_page'
	);
}
add_action( 'admin_menu', 'quietype_add_settings_page' );

/** Register settings that do not belong in the live-preview Customizer. */
function quietype_register_admin_settings() {
	register_setting(
		'quietype_settings',
		'quietype_head_code',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'quietype_sanitize_head_code',
		)
	);
	register_setting(
		'quietype_settings',
		'quietype_footer_code',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'quietype_sanitize_footer_code',
		)
	);
	register_setting(
		'quietype_settings',
		'quietype_gravatar_base_url',
		array(
			'type'              => 'string',
			'default'           => 'https://gravatar.loli.net/avatar/',
			'sanitize_callback' => 'quietype_sanitize_gravatar_base_url',
		)
	);
}
add_action( 'admin_init', 'quietype_register_admin_settings' );

/** Carry forward values saved by the short-lived Customizer implementation. */
function quietype_migrate_legacy_admin_settings() {
	foreach ( array( 'quietype_head_code', 'quietype_footer_code' ) as $option_name ) {
		if ( false !== get_option( $option_name, false ) ) {
			continue;
		}
		$legacy_value = get_theme_mod( $option_name, '' );
		if ( '' !== $legacy_value ) {
			add_option( $option_name, $legacy_value, '', false );
		}
	}
}
add_action( 'admin_init', 'quietype_migrate_legacy_admin_settings', 11 );

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

/** Load WordPress's built-in code editor only on this theme screen. */
function quietype_settings_assets( $hook_suffix ) {
	if ( 'appearance_page_quietype-settings' !== $hook_suffix ) {
		return;
	}
	$editor = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
	if ( false === $editor ) {
		return;
	}
	wp_enqueue_script( 'code-editor' );
	wp_enqueue_style( 'code-editor' );
	$settings = wp_json_encode( $editor );
	$script   = "jQuery(function(){['quietype_head_code','quietype_footer_code'].forEach(function(id){if(document.getElementById(id)){wp.codeEditor.initialize(id,{$settings});}});});";
	wp_add_inline_script( 'code-editor', $script );
}
add_action( 'admin_enqueue_scripts', 'quietype_settings_assets' );

/** Render the dedicated settings screen. */
function quietype_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$can_edit_code = current_user_can( 'unfiltered_html' );
	?>
	<div class="wrap">
		<h1>Quietype 设置</h1>
		<p>配置不适合实时预览的站点代码与中国大陆访问选项。外观样式仍请使用“外观 → 自定义”。</p>
		<?php settings_errors( 'quietype_settings' ); ?>
		<form action="options.php" method="post">
			<?php settings_fields( 'quietype_settings' ); ?>
			<h2>自定义代码</h2>
			<p>仅粘贴可信代码。百度统计等异步统计脚本通常放在 Head；不要求提前加载的脚本放在 Footer。</p>
			<?php if ( ! $can_edit_code ) : ?>
				<div class="notice notice-warning inline"><p>当前账户无权保存未过滤的 HTML 或 JavaScript，以下代码框为只读。</p></div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="quietype_head_code">Head 自定义代码</label></th>
					<td>
						<textarea class="large-text code" id="quietype_head_code" name="quietype_head_code" rows="9" <?php disabled( ! $can_edit_code ); ?>><?php echo esc_textarea( get_option( 'quietype_head_code', get_theme_mod( 'quietype_head_code', '' ) ) ); ?></textarea>
						<p class="description">输出在 <code>&lt;/head&gt;</code> 前，可填写 <code>&lt;script&gt;</code>、<code>&lt;style&gt;</code> 或统计平台验证代码。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="quietype_footer_code">Footer 自定义代码</label></th>
					<td>
						<textarea class="large-text code" id="quietype_footer_code" name="quietype_footer_code" rows="9" <?php disabled( ! $can_edit_code ); ?>><?php echo esc_textarea( get_option( 'quietype_footer_code', get_theme_mod( 'quietype_footer_code', '' ) ) ); ?></textarea>
						<p class="description">输出在 <code>&lt;/body&gt;</code> 前，适合不要求提前加载的 JavaScript。</p>
					</td>
				</tr>
			</table>

			<h2>中国大陆访问</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="quietype_gravatar_base_url">Gravatar 地址</label></th>
					<td>
						<input class="regular-text code" id="quietype_gravatar_base_url" name="quietype_gravatar_base_url" type="url" value="<?php echo esc_attr( get_option( 'quietype_gravatar_base_url', 'https://gravatar.loli.net/avatar/' ) ); ?>" placeholder="https://gravatar.loli.net/avatar/">
						<p class="description">默认使用国内较易访问的镜像。填写完整的头像基础地址；留空则保留 WordPress 原始 Gravatar 地址。</p>
					</td>
				</tr>
			</table>
			<?php submit_button( '保存设置' ); ?>
		</form>
	</div>
	<?php
}

/** Print administrator-supplied code at the selected document boundary. */
function quietype_print_custom_head_code() {
	$code = get_option( 'quietype_head_code', get_theme_mod( 'quietype_head_code', '' ) );
	if ( $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit unfiltered_html administrator setting.
	}
}
add_action( 'wp_head', 'quietype_print_custom_head_code', 99 );

function quietype_print_custom_footer_code() {
	$code = get_option( 'quietype_footer_code', get_theme_mod( 'quietype_footer_code', '' ) );
	if ( $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit unfiltered_html administrator setting.
	}
}
add_action( 'wp_footer', 'quietype_print_custom_footer_code', 99 );

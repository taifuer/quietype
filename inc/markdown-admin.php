<?php
/** WordPress-native editor chrome and an isolated, server-rendered preview. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function quietype_markdown_admin_notice() {
	global $post;
	if ( quietype_get_setting( 'quietype_markdown_enabled', false ) && class_exists( 'EditormdApp\\WPComMarkdown' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-warning"><p>Quietype 内置 Markdown 已配置，但因 WP Editor.md 仍启用而暂停接管。请先备份并停用 WP Editor.md，再试用内置模块；现有文章不会被批量转换。</p></div>';
	}
	if ( quietype_markdown_enabled() && 'post' === get_current_screen()->base && $post instanceof WP_Post && get_post_meta( $post->ID, '_quietype_markdown_html_hash', true ) && ! quietype_markdown_post( $post ) ) {
		echo '<div class="notice notice-info"><p>这篇内容曾在其他编辑器中修改过，当前继续使用 HTML / 区块编辑器。旧 Markdown 原文仍保留在数据库中，不会自动覆盖新的正文。</p></div>';
	}
}
add_action( 'admin_notices', 'quietype_markdown_admin_notice' );

function quietype_markdown_editor_assets( $hook ) {
	global $post;
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! quietype_markdown_enabled() || ! quietype_markdown_post( $post ) ) {
		return;
	}
	$editor = wp_enqueue_code_editor( array(
		'type' => 'text/x-markdown',
		'codemirror' => array( 'lineWrapping' => true, 'lineNumbers' => true, 'indentUnit' => 4, 'tabSize' => 4 ),
	) );
	$deps = array( 'jquery', 'autosave' );
	if ( $editor ) {
		$deps[] = 'code-editor';
	}
	wp_enqueue_media( array( 'post' => $post->ID ) );
	wp_enqueue_style( 'quietype-markdown-editor', get_template_directory_uri() . '/assets/css/markdown-editor.css', array(), quietype_asset_version( 'assets/css/markdown-editor.css' ) );
	wp_enqueue_script( 'quietype-markdown-editor', get_template_directory_uri() . '/assets/js/markdown-editor.js', $deps, quietype_asset_version( 'assets/js/markdown-editor.js' ), true );
	wp_add_inline_script( 'quietype-markdown-editor', 'window.quietypeMarkdownEditor=' . wp_json_encode( array(
		'editor' => $editor,
		'url' => admin_url( 'admin-ajax.php' ),
		'postId' => $post->ID,
		'nonce' => wp_create_nonce( 'quietype_markdown_' . $post->ID ),
	) ) . ';', 'before' );
}
add_action( 'admin_enqueue_scripts', 'quietype_markdown_editor_assets' );

function quietype_markdown_editor_chrome( $post ) {
	if ( ! quietype_markdown_enabled() || ! quietype_markdown_post( $post ) ) {
		return;
	}
	wp_nonce_field( 'quietype_markdown_' . $post->ID, 'quietype_markdown_nonce', false );
	?>
	<section class="quietype-markdown-editor" aria-label="Markdown 编辑器">
		<div class="quietype-markdown-editor__bar">
			<strong>Markdown</strong>
			<div class="quietype-markdown-editor__modes" role="group" aria-label="编辑视图">
				<button type="button" class="button" data-md-view="write" aria-pressed="true">编辑</button>
				<button type="button" class="button" data-md-view="split" aria-pressed="false">分栏</button>
				<button type="button" class="button" data-md-view="preview" aria-pressed="false">预览</button>
			</div>
		</div>
		<div class="quietype-markdown-editor__toolbar" role="group" aria-label="插入 Markdown">
			<?php foreach ( array( 'heading' => '标题', 'bold' => '加粗', 'italic' => '斜体', 'link' => '链接', 'image' => '图片', 'video' => '视频', 'quote' => '引用', 'list' => '列表', 'code' => '代码', 'table' => '表格', 'math' => '公式' ) as $command => $label ) : ?>
				<button type="button" class="button-link" data-md-command="<?php echo esc_attr( $command ); ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</div>
		<div class="quietype-markdown-editor__panes">
			<iframe class="quietype-markdown-editor__preview" title="Markdown 正文预览" sandbox="allow-scripts allow-same-origin" hidden></iframe>
		</div>
		<p class="quietype-markdown-editor__status" role="status" aria-live="polite">原文与 HTML 同时保存；预览不会发布文章。</p>
		<details class="quietype-markdown-editor__help"><summary>语法与兼容说明</summary><p>支持表格、脚注、删除线、代码围栏、Mermaid、<code>$行内公式$</code> 与 <code>$$独立公式$$</code>。图片/表格下一行直接写 <code>[!CAPTION] 题注</code>，也兼容空一行的写法。视频使用 <code>[video src="https://example.com/demo.mp4"]</code>，可加 <code>poster="封面图片地址"</code>。其他第三方短代码请使用 WordPress 的整页预览检查。已有 HTML / 区块文章不会自动转换。</p><p>试用前请备份。关闭模块后保留已保存 HTML；如果随后用 HTML 编辑器修改正文，原 Markdown 不会自动覆盖新内容。</p></details>
	</section>
	<?php
}
add_action( 'edit_form_after_editor', 'quietype_markdown_editor_chrome' );

/** Build preview without analytics/cookies; only safe native videos are expanded. */
function quietype_markdown_preview_document( $source ) {
	$html = quietype_markdown_render( $source );
	$html = quietype_render_video_shortcodes( $html );
	$html = quietype_render_captions( $html );
	$article = quietype_prepare_article( $html );
	$base = get_template_directory_uri();
	$nonce = wp_generate_password( 24, false );
	$csp = "default-src 'none'; script-src 'nonce-{$nonce}' 'strict-dynamic'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' https: http: data:; media-src 'self' https: http:; connect-src 'self'; base-uri 'none'; form-action 'none'";
	$styles = array( '/style.css', '/assets/css/markdown-preview.css' );
	$scripts = array();
	if ( str_contains( $html, '<pre' ) ) {
		$scripts = array( '/assets/vendor/prism/prism.js', '/assets/vendor/prism/plugins/autoloader/prism-autoloader.min.js', '/assets/vendor/prism/plugins/line-numbers/prism-line-numbers.min.js' );
	}
	if ( str_contains( $html, 'quietype-math' ) ) {
		array_unshift( $styles, '/assets/vendor/katex/katex.min.css' );
		$scripts[] = '/assets/vendor/katex/katex.min.js';
	}
	$scripts[] = '/assets/js/markdown-render.js';
	$scripts[] = '/assets/js/theme.js';
	ob_start();
	?>
	<!doctype html><html lang="zh-CN" data-reading-bg="white"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="<?php echo esc_attr( $csp ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title>正文预览</title>
	<?php foreach ( $styles as $style ) : ?><link rel="stylesheet" href="<?php echo esc_url( $base . $style . '?ver=' . quietype_asset_version( ltrim( $style, '/' ) ) ); ?>"><?php endforeach; ?>
	<script nonce="<?php echo esc_attr( $nonce ); ?>">window.Prism={manual:true};</script></head><body class="quietype-markdown-preview"><main class="article-content"><?php echo $article['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Server Markdown parser applies wp_kses_post before trusted theme transformations. ?></main>
	<?php foreach ( $scripts as $script ) : ?><script nonce="<?php echo esc_attr( $nonce ); ?>" src="<?php echo esc_url( $base . $script . '?ver=' . quietype_asset_version( ltrim( $script, '/' ) ) ); ?>"></script><?php endforeach; ?>
	<?php if ( quietype_get_setting( 'quietype_mermaid_enabled', true ) && str_contains( $html, 'class="mermaid"' ) ) : ?><script nonce="<?php echo esc_attr( $nonce ); ?>" type="module" src="<?php echo esc_url( $base . '/assets/js/mermaid.js' ); ?>"></script><?php endif; ?>
	</body></html>
	<?php
	return (string) ob_get_clean();
}

function quietype_markdown_preview() {
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	check_ajax_referer( 'quietype_markdown_' . $post_id, 'quietype_markdown_nonce' );
	$source = quietype_markdown_request( $post_id );
	if ( null === $source || is_wp_error( $source ) ) {
		wp_send_json_error( array( 'message' => '无权预览或编辑请求已过期，请刷新后重试。' ), 403 );
	}
	nocache_headers();
	wp_send_json_success( array( 'document' => quietype_markdown_preview_document( $source ) ) );
}
add_action( 'wp_ajax_quietype_markdown_preview', 'quietype_markdown_preview' );

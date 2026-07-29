<?php
/**
 * WordPress privacy-policy integration for Quietype-owned storage and requests.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Add accurate suggested text to WordPress's native Privacy Policy Guide. */
function quietype_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content = '<h2>Quietype 主题</h2>'
		. '<p>Quietype 不包含遥测或广告跟踪代码。主题会在浏览器本地存储阅读背景偏好，以及文章浏览量的最近计数时间；这些值不会随普通页面请求发送给本站。</p>'
		. '<p>文章浏览量功能会在服务器端暂存访客 IP 地址与浏览器标识经站点密钥散列后的结果，用于六小时内避免重复计数。该临时记录在到期后删除，文章只保留汇总浏览次数。</p>'
		. '<p>如果管理员启用了自定义登录入口，验证成功后会写入一个最长保留十二小时的 HttpOnly、SameSite 安全 Cookie。它只用于确认该浏览器已经通过入口校验，不包含入口参数值。</p>'
		. '<p>站点可以选择配置 Gravatar 镜像、友链可达性检测、外链书籍封面、照片或自定义 Head/Footer 代码。这些功能可能使浏览器或服务器连接第三方服务；站点运营者应根据实际启用的服务补充接收方、用途和保留期限。评论表单与评论 Cookie 由 WordPress 核心处理。</p>';
	wp_add_privacy_policy_content( 'Quietype', wp_kses_post( $content ) );
}
add_action( 'admin_init', 'quietype_add_privacy_policy_content' );

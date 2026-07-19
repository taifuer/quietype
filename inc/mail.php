<?php
/**
 * SMTP transport and restrained administrative notifications.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Configure WordPress's bundled PHPMailer when SMTP is enabled. */
function quietype_configure_phpmailer( $phpmailer ) {
	if ( ! quietype_get_setting( 'quietype_smtp_enabled', false ) ) {
		return;
	}
	$host = quietype_get_setting( 'quietype_smtp_host', '' );
	if ( ! $host ) {
		return;
	}
	$encryption = quietype_get_setting( 'quietype_smtp_encryption', 'tls' );
	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->Port       = (int) quietype_get_setting( 'quietype_smtp_port', 587 );
	$phpmailer->SMTPAuth   = (bool) quietype_get_setting( 'quietype_smtp_auth', true );
	$phpmailer->Username   = quietype_get_setting( 'quietype_smtp_username', '' );
	$phpmailer->Password   = defined( 'QUIETYPE_SMTP_PASSWORD' ) ? QUIETYPE_SMTP_PASSWORD : quietype_get_setting( 'quietype_smtp_password', '' );
	$phpmailer->SMTPSecure = 'none' === $encryption ? '' : $encryption;
	$phpmailer->SMTPAutoTLS = 'none' !== $encryption;
	$phpmailer->CharSet    = get_bloginfo( 'charset' );
	$from_email = quietype_get_setting( 'quietype_smtp_from_email', '' );
	$from_name  = quietype_get_setting( 'quietype_smtp_from_name', '' );
	if ( $from_email ) {
		$phpmailer->setFrom( $from_email, $from_name ?: get_bloginfo( 'name' ), false );
	}
}
add_action( 'phpmailer_init', 'quietype_configure_phpmailer' );

/** Send notification mail only to the validated WordPress administrator address. */
function quietype_send_admin_notification( $subject, $message ) {
	$recipient = get_option( 'admin_email' );
	if ( ! is_email( $recipient ) ) {
		return false;
	}
	return wp_mail( $recipient, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject, $message );
}

/** Notify only when an account with site-management capability logs in. */
function quietype_notify_admin_login( $user_login, $user ) {
	if ( ! quietype_get_setting( 'quietype_login_notification_enabled', false ) || ! $user instanceof WP_User || ! user_can( $user, 'manage_options' ) ) {
		return;
	}
	$lock = 'quietype_login_notice_' . $user->ID;
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '未知';
	$message = "账户：{$user_login}\n时间：" . wp_date( 'Y-m-d H:i:s' ) . "\nIP：{$ip}\n站点：" . home_url( '/' );
	quietype_send_admin_notification( '管理员登录提醒', $message );
}
add_action( 'wp_login', 'quietype_notify_admin_login', 10, 2 );

/** Notify the administrator once for a genuine newly inserted comment. */
function quietype_notify_new_comment( $comment_id, $comment ) {
	if ( ! quietype_get_setting( 'quietype_comment_notification_enabled', false ) || ! $comment instanceof WP_Comment ) {
		return;
	}
	if ( in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true ) || in_array( (string) $comment->comment_approved, array( 'spam', 'trash' ), true ) ) {
		return;
	}
	// WordPress may already notify the same administrator; avoid a duplicate.
	if ( '1' !== (string) $comment->comment_approved && get_option( 'moderation_notify' ) ) {
		return;
	}
	if ( '1' === (string) $comment->comment_approved && get_option( 'comments_notify' ) ) {
		$post_author = get_userdata( (int) get_post_field( 'post_author', $comment->comment_post_ID ) );
		if ( $post_author instanceof WP_User && strtolower( $post_author->user_email ) === strtolower( get_option( 'admin_email' ) ) ) {
			return;
		}
	}
	$lock = 'quietype_comment_notice_' . $comment_id;
	if ( get_transient( $lock ) ) {
		return;
	}
	set_transient( $lock, 1, DAY_IN_SECONDS );
	$post_title = get_the_title( $comment->comment_post_ID );
	$status     = '1' === (string) $comment->comment_approved ? '已通过' : '待审核';
	$content    = quietype_normalize_meta_text( $comment->comment_content, 500 );
	$message    = "文章：{$post_title}\n作者：{$comment->comment_author}\n状态：{$status}\n内容：{$content}\n管理：" . admin_url( 'comment.php?action=editcomment&c=' . $comment_id );
	quietype_send_admin_notification( '收到新评论', $message );
}
add_action( 'wp_insert_comment', 'quietype_notify_new_comment', 10, 2 );

/** Send a nonce-protected SMTP test message and return to the settings page. */
function quietype_handle_test_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权执行此操作。' );
	}
	check_admin_referer( 'quietype_send_test_email' );
	$sent = quietype_send_admin_notification( 'SMTP 测试邮件', '如果你收到这封邮件，说明 Quietype SMTP 已能通过 WordPress wp_mail() 正常发信。\n\n发送时间：' . wp_date( 'Y-m-d H:i:s' ) );
	$url  = add_query_arg( 'quietype_mail', $sent ? 'sent' : 'failed', admin_url( 'themes.php?page=quietype-settings#quietype-section-mail' ) );
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_quietype_send_test_email', 'quietype_handle_test_email' );

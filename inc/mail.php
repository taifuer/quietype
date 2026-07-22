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

/** Keep the latest transport error visible without exposing SMTP credentials. */
function quietype_record_mail_error( $error ) {
	if ( ! is_wp_error( $error ) ) {
		return;
	}
	set_transient(
		'quietype_last_mail_error',
		array(
			'message' => quietype_normalize_meta_text( $error->get_error_message(), 500 ),
			'time'    => wp_date( 'Y-m-d H:i:s' ),
		),
		DAY_IN_SECONDS
	);
}
add_action( 'wp_mail_failed', 'quietype_record_mail_error' );

/** Clear a stale error once WordPress successfully hands a message to SMTP. */
function quietype_clear_mail_error() {
	delete_transient( 'quietype_last_mail_error' );
}
add_action( 'wp_mail_succeeded', 'quietype_clear_mail_error' );

/** Format a value for the restrained HTML notification template. */
function quietype_mail_detail_value( $value ) {
	$value = (string) $value;
	if ( wp_http_validate_url( $value ) ) {
		return '<a href="' . esc_url( $value ) . '" style="color:#795844;text-decoration:underline;text-underline-offset:2px">' . esc_html( $value ) . '</a>';
	}
	return nl2br( esc_html( $value ) );
}

/** Build a compact email that remains readable in conservative mail clients. */
function quietype_notification_email( $title, $intro, $details = array(), $action_url = '', $action_label = '' ) {
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$rows      = '';
	foreach ( $details as $label => $value ) {
		$rows .= '<tr><th scope="row" style="width:88px;padding:11px 14px 11px 0;border-bottom:1px solid #e3e0d8;color:#666a63;font-size:13px;font-weight:500;line-height:1.6;text-align:left;vertical-align:top">' . esc_html( $label ) . '</th><td style="padding:11px 0;border-bottom:1px solid #e3e0d8;color:#40423e;font-size:14px;line-height:1.7;word-break:break-word">' . quietype_mail_detail_value( $value ) . '</td></tr>';
	}
	$action = '';
	if ( $action_url && $action_label ) {
		$action = '<p style="margin:24px 0 4px"><a href="' . esc_url( $action_url ) . '" style="display:inline-block;padding:10px 18px;border-radius:4px;background:#795844;color:#ffffff;font-size:14px;font-weight:600;line-height:1.4;text-decoration:none">' . esc_html( $action_label ) . '</a></p>';
	}

	return '<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f5f4f0;color:#262724;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Microsoft YaHei,Arial,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#f5f4f0"><tr><td align="center" style="padding:32px 16px"><table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;border-collapse:separate;border-spacing:0;border:1px solid #e3e0d8;border-top:3px solid #795844;border-radius:6px;background:#fdfdfb"><tr><td style="padding:30px 32px 28px"><p style="margin:0 0 14px;color:#795844;font-size:12px;font-weight:700;letter-spacing:2px;line-height:1.5">' . esc_html( $site_name ) . '</p><h1 style="margin:0 0 14px;color:#262724;font-size:22px;font-weight:650;line-height:1.4">' . esc_html( $title ) . '</h1><p style="margin:0 0 20px;color:#666a63;font-size:14px;line-height:1.8">' . esc_html( $intro ) . '</p>' . ( $rows ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>' : '' ) . $action . '</td></tr><tr><td style="padding:16px 32px;border-top:1px solid #e3e0d8;background:#faf9f6;color:#858980;font-size:12px;line-height:1.7">此邮件由' . esc_html( $site_name ) . '系统自动发出，请勿直接回复。</td></tr></table></td></tr></table></body></html>';
}

/** Send notification mail only to the validated WordPress administrator address. */
function quietype_send_admin_notification( $subject, $intro, $details = array(), $action_url = '', $action_label = '' ) {
	$recipient = get_option( 'admin_email' );
	if ( ! is_email( $recipient ) ) {
		return false;
	}
	$message = quietype_notification_email( $subject, $intro, $details, $action_url, $action_label );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	return wp_mail( $recipient, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $subject, $message, $headers );
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
	quietype_send_admin_notification(
		'管理员登录提醒',
		'检测到站点管理员成功登录。若非本人操作，请尽快检查账户安全。',
		array(
			'账户' => $user_login,
			'时间' => wp_date( 'Y-m-d H:i:s' ),
			'IP'   => $ip,
			'站点' => home_url( '/' ),
		),
		admin_url(),
		'进入站点后台'
	);
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
	quietype_send_admin_notification(
		'收到新评论',
		'站点收到一条新的公开评论，请按需查看或处理。',
		array(
			'文章' => $post_title,
			'作者' => $comment->comment_author,
			'状态' => $status,
			'内容' => $content,
		),
		admin_url( 'comment.php?action=editcomment&c=' . $comment_id ),
		'查看并管理评论'
	);
}
add_action( 'wp_insert_comment', 'quietype_notify_new_comment', 10, 2 );

/** Send a nonce-protected SMTP test message and return to the settings page. */
function quietype_handle_test_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权执行此操作。' );
	}
	check_admin_referer( 'quietype_send_test_email' );
	$sent = quietype_send_admin_notification(
		'SMTP 测试邮件',
		'如果你收到这封邮件，说明 Quietype SMTP 已能通过 WordPress wp_mail() 正常发信。',
		array(
			'发送时间' => wp_date( 'Y-m-d H:i:s' ),
			'站点'     => home_url( '/' ),
		)
	);
	$url  = add_query_arg( 'quietype_mail', $sent ? 'sent' : 'failed', admin_url( 'themes.php?page=quietype-settings#quietype-section-mail' ) );
	wp_safe_redirect( $url );
	exit;
}
add_action( 'admin_post_quietype_send_test_email', 'quietype_handle_test_email' );

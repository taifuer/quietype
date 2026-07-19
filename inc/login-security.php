<?php
/**
 * Login entrance gate and dependency-free arithmetic challenge.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Normalize a Customizer checkbox value. */
function quietype_sanitize_checkbox( $value ) {
	return (bool) $value;
}

/** Limit the entrance parameter name to URL-safe identifier characters. */
function quietype_sanitize_login_gate_key( $value ) {
	$value = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
	return $value ?: 'user';
}

/** Keep the entrance value on one line and within a practical URL length. */
function quietype_sanitize_login_gate_value( $value ) {
	return substr( sanitize_text_field( (string) $value ), 0, 128 );
}

/** Return the configured entrance parameter name. */
function quietype_login_gate_key() {
	if ( defined( 'QUIETYPE_LOGIN_GATE_KEY' ) ) {
		return quietype_sanitize_login_gate_key( QUIETYPE_LOGIN_GATE_KEY );
	}
	return quietype_sanitize_login_gate_key( get_theme_mod( 'quietype_login_gate_key', 'user' ) );
}

/** Return the configured entrance parameter value. */
function quietype_login_gate_value() {
	if ( defined( 'QUIETYPE_LOGIN_GATE_VALUE' ) ) {
		return quietype_sanitize_login_gate_value( QUIETYPE_LOGIN_GATE_VALUE );
	}
	return quietype_sanitize_login_gate_value( get_theme_mod( 'quietype_login_gate_value', '' ) );
}

/** The entrance gate is fail-open until both enabled and fully configured. */
function quietype_login_gate_enabled() {
	if ( defined( 'QUIETYPE_LOGIN_GATE_VALUE' ) ) {
		return '' !== quietype_login_gate_value();
	}
	return (bool) get_theme_mod( 'quietype_login_gate_enabled', false ) && '' !== quietype_login_gate_value();
}

/** Return whether this request carries the configured entrance token. */
function quietype_login_gate_matches() {
	$key = quietype_login_gate_key();
	if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}
	$received = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return hash_equals( quietype_login_gate_value(), $received );
}

/**
 * Password reset and signed recovery actions remain usable without exposing
 * the normal credential form. Their own keys are still verified by core.
 */
function quietype_login_is_safe_core_action() {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'postpass' === $action ) {
		return true;
	}
	if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
		$has_reset_key    = isset( $_REQUEST['key'], $_REQUEST['login'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_reset_cookie = false;
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( 0 === strpos( $cookie_name, 'wp-resetpass-' ) ) {
				$has_reset_cookie = true;
				break;
			}
		}
		return $has_reset_key || $has_reset_cookie;
	}
	if ( 'entered_recovery_mode' === $action ) {
		return isset( $_REQUEST['rm_token'], $_REQUEST['rm_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( 'confirmaction' === $action ) {
		return isset( $_REQUEST['request_id'], $_REQUEST['confirm_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return false;
}

/** Emit an intentionally plain 404 without revealing the protected route. */
function quietype_login_not_found() {
	status_header( 404 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	$title = esc_html__( '页面不存在', 'quietype' );
	echo '<!doctype html><html lang="zh-CN"><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1"><title>404</title>';
	echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f1eb;color:#292822;font:16px/1.8 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC",sans-serif}main{text-align:center}b{font:600 64px/1 Georgia,serif}p{margin:14px 0;color:#716d64}a{color:#80583f}</style>';
	echo '<main><b>404</b><p>' . $title . '</p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( '返回首页', 'quietype' ) . '</a></main></html>';
	exit;
}

/** Hide both wp-login.php and wp-admin from unauthenticated direct requests. */
function quietype_protect_login_entry() {
	if ( ! quietype_login_gate_enabled() || quietype_login_gate_matches() || quietype_login_is_safe_core_action() ) {
		return;
	}
	quietype_login_not_found();
}
add_action( 'login_init', 'quietype_protect_login_entry', 0 );

/** Prevent wp-admin from redirecting unauthenticated scans to a revealing URL. */
function quietype_protect_admin_entry() {
	if ( ! quietype_login_gate_enabled() || ! is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	global $pagenow;
	if ( in_array( $pagenow, array( 'admin-post.php', 'async-upload.php' ), true ) ) {
		return;
	}
	quietype_login_not_found();
}
add_action( 'init', 'quietype_protect_admin_entry', 0 );

/** Keep the entrance token present when a protected form submits. */
function quietype_login_gate_hidden_field() {
	if ( ! quietype_login_gate_enabled() ) {
		return;
	}
	echo '<input type="hidden" name="' . esc_attr( quietype_login_gate_key() ) . '" value="' . esc_attr( quietype_login_gate_value() ) . '">';
}
add_action( 'login_form', 'quietype_login_gate_hidden_field', 1 );
add_action( 'lostpassword_form', 'quietype_login_gate_hidden_field', 1 );

/** Add the entrance token to links rendered within the protected login flow. */
function quietype_login_gate_url( $url ) {
	if ( ! quietype_login_gate_enabled() ) {
		return $url;
	}
	return add_query_arg( quietype_login_gate_key(), quietype_login_gate_value(), $url );
}
add_filter( 'lostpassword_url', 'quietype_login_gate_url' );
add_filter( 'logout_url', 'quietype_login_gate_url' );

/** Add the token to login links only after a permitted login request begins. */
function quietype_protected_login_url( $url ) {
	if ( quietype_login_gate_enabled() && did_action( 'login_init' ) ) {
		return quietype_login_gate_url( $url );
	}
	return $url;
}
add_filter( 'login_url', 'quietype_protected_login_url' );

/** Keep the token through the lost-password confirmation redirect. */
function quietype_lostpassword_redirect( $url ) {
	if ( ! quietype_login_gate_enabled() ) {
		return $url;
	}
	if ( ! $url ) {
		$url = site_url( 'wp-login.php?checkemail=confirm', 'login' );
	}
	return quietype_login_gate_url( $url );
}
add_filter( 'lostpassword_redirect', 'quietype_lostpassword_redirect' );

/** Preserve the protected entrance in the password-reset email. */
function quietype_gate_password_reset_message( $message, $key, $user_login ) {
	if ( ! quietype_login_gate_enabled() ) {
		return $message;
	}
	$original = network_site_url(
		'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ),
		'login'
	);
	return str_replace( $original, quietype_login_gate_url( $original ), $message );
}
add_filter( 'retrieve_password_message', 'quietype_gate_password_reset_message', 10, 3 );

/** Create a short-lived, one-time arithmetic challenge for the login form. */
function quietype_login_captcha_field() {
	if ( ! get_theme_mod( 'quietype_login_captcha_enabled', false ) ) {
		return;
	}
	$left         = wp_rand( 1, 9 );
	$right        = wp_rand( 1, 9 );
	$challenge_id = wp_generate_uuid4();
	set_transient( 'quietype_login_captcha_' . md5( $challenge_id ), $left + $right, 10 * MINUTE_IN_SECONDS );
	?>
	<p class="quietype-login-captcha">
		<label for="quietype_captcha"><?php echo esc_html( sprintf( '验证：%d + %d =', $left, $right ) ); ?></label>
		<input type="text" inputmode="numeric" pattern="[0-9]*" name="quietype_captcha" id="quietype_captcha" class="input" autocomplete="off" required>
		<input type="hidden" name="quietype_captcha_id" value="<?php echo esc_attr( $challenge_id ); ?>">
	</p>
	<?php
}
add_action( 'login_form', 'quietype_login_captcha_field', 5 );

/** Reject a missing, expired, reused, or incorrect arithmetic answer. */
function quietype_validate_login_captcha( $user ) {
	if ( ! get_theme_mod( 'quietype_login_captcha_enabled', false ) || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return $user;
	}
	if ( ! isset( $_POST['log'], $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $user;
	}
	$challenge_id = isset( $_POST['quietype_captcha_id'] ) ? sanitize_text_field( wp_unslash( $_POST['quietype_captcha_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$answer       = isset( $_POST['quietype_captcha'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['quietype_captcha'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$transient    = 'quietype_login_captcha_' . md5( $challenge_id );
	$expected     = $challenge_id ? get_transient( $transient ) : false;
	if ( $challenge_id ) {
		delete_transient( $transient );
	}
	if ( false === $expected || ! hash_equals( (string) $expected, $answer ) ) {
		return new WP_Error( 'quietype_captcha', '<strong>错误：</strong>验证码不正确或已过期，请重新计算。' );
	}
	return $user;
}
add_filter( 'authenticate', 'quietype_validate_login_captcha', 100 );

/** Disable legacy remote authentication when the site does not use it. */
function quietype_xmlrpc_enabled( $enabled ) {
	return get_theme_mod( 'quietype_xmlrpc_auth_disabled', false ) ? false : $enabled;
}
add_filter( 'xmlrpc_enabled', 'quietype_xmlrpc_enabled' );

/** Remove unauthenticated pingback methods alongside XML-RPC authentication. */
function quietype_xmlrpc_methods( $methods ) {
	if ( get_theme_mod( 'quietype_xmlrpc_auth_disabled', false ) ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	}
	return $methods;
}
add_filter( 'xmlrpc_methods', 'quietype_xmlrpc_methods' );

/** Stop advertising a disabled pingback endpoint in front-end headers. */
function quietype_remove_pingback_header( $headers ) {
	if ( get_theme_mod( 'quietype_xmlrpc_auth_disabled', false ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}
add_filter( 'wp_headers', 'quietype_remove_pingback_header' );

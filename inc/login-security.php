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
	return $value ?: 'entry';
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
	return quietype_sanitize_login_gate_key( quietype_get_setting( 'quietype_login_gate_key', 'entry' ) );
}

/** Return the configured entrance parameter value. */
function quietype_login_gate_value() {
	if ( defined( 'QUIETYPE_LOGIN_GATE_VALUE' ) ) {
		return quietype_sanitize_login_gate_value( QUIETYPE_LOGIN_GATE_VALUE );
	}
	return quietype_sanitize_login_gate_value( quietype_get_setting( 'quietype_login_gate_value', '' ) );
}

/** Return the short-lived cookie used after the entrance parameter is accepted. */
function quietype_login_gate_cookie_name() {
	return 'quietype_login_gate';
}

/** Sign a time-limited browser token without storing the entrance value in it. */
function quietype_login_gate_cookie_value() {
	$expires = time() + 12 * HOUR_IN_SECONDS;
	$nonce   = wp_generate_password( 24, false, false );
	$payload = $expires . '|' . $nonce;
	return base64_encode( $payload . '|' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Signed transport token.
}

/** Validate an unexpired entrance cookie. */
function quietype_login_gate_cookie_matches() {
	$name    = quietype_login_gate_cookie_name();
	$encoded = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
	$decoded = $encoded ? base64_decode( $encoded, true ) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Signed transport token.
	$parts   = is_string( $decoded ) ? explode( '|', $decoded, 3 ) : array();
	if ( 3 !== count( $parts ) ) {
		return false;
	}
	list( $expires, $nonce, $signature ) = $parts;
	$payload = $expires . '|' . $nonce;
	return ctype_digit( $expires ) && (int) $expires >= time() && (int) $expires <= time() + 13 * HOUR_IN_SECONDS
		&& hash_equals( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), $signature );
}

/** The entrance gate is fail-open until both enabled and fully configured. */
function quietype_login_gate_enabled() {
	if ( defined( 'QUIETYPE_LOGIN_GATE_VALUE' ) ) {
		return '' !== quietype_login_gate_value();
	}
	return (bool) quietype_get_setting( 'quietype_login_gate_enabled', false ) && '' !== quietype_login_gate_value();
}

/** Return whether this request carries the configured entrance token. */
function quietype_login_gate_matches() {
	if ( quietype_login_gate_cookie_matches() ) {
		return true;
	}
	$key = quietype_login_gate_key();
	if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}
	$received = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return hash_equals( quietype_login_gate_value(), $received );
}

/** Exchange a valid query parameter for a secure cookie and a clean URL. */
function quietype_login_gate_exchange_query() {
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( ! quietype_login_gate_enabled() || 'GET' !== $request_method ) {
		return;
	}
	$key = quietype_login_gate_key();
	if ( ! isset( $_GET[ $key ] ) || ! quietype_login_gate_matches() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the entrance credential itself.
		return;
	}
	$value = quietype_login_gate_cookie_value();
	setcookie(
		quietype_login_gate_cookie_name(),
		$value,
		array(
			'expires'  => time() + 12 * HOUR_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		)
	);
	$_COOKIE[ quietype_login_gate_cookie_name() ] = $value;
	$query = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Query is preserved after removing the entrance credential.
	unset( $query[ $key ] );
	$query = array_filter( $query, 'is_scalar' );
	$url   = add_query_arg( array_map( 'sanitize_text_field', $query ), site_url( 'wp-login.php', 'login' ) );
	wp_safe_redirect( $url );
	exit;
}
add_action( 'login_init', 'quietype_login_gate_exchange_query', -1 );

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
	$title = esc_html__( '没有找到这一页。', 'quietype' );
	echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns a pre-escaped language attribute string.
	echo '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>404</title>';
	echo '<style>:root{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-width:320px;min-height:100vh;display:grid;place-items:center;padding:32px 20px;background:#fdfdfb;color:#262724;font:16px/1.75 -apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans SC","PingFang SC","Microsoft YaHei",sans-serif}main{width:min(100%,420px);text-align:center}.eyebrow{margin:0 0 14px;color:#858980;font:600 11px/1.4 ui-monospace,"Cascadia Code",Consolas,monospace;letter-spacing:.16em}h1{margin:0;font:650 28px/1.4 "Source Han Serif SC","Noto Serif CJK SC","Songti SC",SimSun,serif;letter-spacing:.02em}a{display:inline-block;margin-top:24px;color:#795844;text-underline-offset:.2em}a:focus-visible{outline:2px solid #795844;outline-offset:4px;border-radius:2px}</style></head>';
	echo '<body><main><p class="eyebrow">404 · NOT FOUND</p><h1>' . $title . '</h1><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( '返回首页', 'quietype' ) . '</a></main></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $title is escaped above and all other dynamic values are escaped inline.
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

/** Legacy no-op retained for child themes that removed this callback by name. */
function quietype_login_gate_hidden_field() {
	return;
}
add_action( 'login_form', 'quietype_login_gate_hidden_field', 1 );
add_action( 'lostpassword_form', 'quietype_login_gate_hidden_field', 1 );

/** Add the entrance token to links rendered within the protected login flow. */
function quietype_login_gate_url( $url ) {
	if ( ! quietype_login_gate_enabled() || quietype_login_gate_cookie_matches() ) {
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
	return $message;
}
add_filter( 'retrieve_password_message', 'quietype_gate_password_reset_message', 10, 3 );

/** Create a short-lived, one-time arithmetic challenge for the login form. */
function quietype_login_captcha_field() {
	if ( ! quietype_get_setting( 'quietype_login_captcha_enabled', false ) ) {
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
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( ! quietype_get_setting( 'quietype_login_captcha_enabled', false ) || 'POST' !== $request_method ) {
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

/** Keep the focused login surface free of WordPress's language selector. */
add_filter( 'login_display_language_dropdown', '__return_false' );

/** Disable legacy remote authentication when the site does not use it. */
function quietype_xmlrpc_enabled( $enabled ) {
	return quietype_get_setting( 'quietype_xmlrpc_auth_disabled', false ) ? false : $enabled;
}
add_filter( 'xmlrpc_enabled', 'quietype_xmlrpc_enabled' );

/** Remove unauthenticated pingback methods alongside XML-RPC authentication. */
function quietype_xmlrpc_methods( $methods ) {
	if ( quietype_get_setting( 'quietype_xmlrpc_auth_disabled', false ) ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
	}
	return $methods;
}
add_filter( 'xmlrpc_methods', 'quietype_xmlrpc_methods' );

/** Stop advertising a disabled pingback endpoint in front-end headers. */
function quietype_remove_pingback_header( $headers ) {
	if ( quietype_get_setting( 'quietype_xmlrpc_auth_disabled', false ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}
add_filter( 'wp_headers', 'quietype_remove_pingback_header' );

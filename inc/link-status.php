<?php
/**
 * Manual and advisory availability states for WordPress bookmarks.
 *
 * @package Quietype
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Expose WordPress's dormant native Links screen while Quietype uses it. */
add_filter( 'pre_option_link_manager_enabled', '__return_true' );

/** Give existing site administrators the core bookmark capability in-theme. */
function quietype_allow_administrators_to_manage_links( $allcaps, $caps, $args ) {
	if ( 'manage_links' === ( $args[0] ?? '' ) && ! empty( $allcaps['manage_options'] ) ) {
		$allcaps['manage_links'] = true;
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'quietype_allow_administrators_to_manage_links', 10, 3 );

/** Return every stored bookmark state. */
function quietype_link_states() {
	$states = get_option( 'quietype_link_states', array() );
	return is_array( $states ) ? $states : array();
}

/** Return one normalized bookmark state. */
function quietype_link_state( $link_id ) {
	$states = quietype_link_states();
	$state  = isset( $states[ $link_id ] ) && is_array( $states[ $link_id ] ) ? $states[ $link_id ] : array();
	$status = $state['status'] ?? 'normal';
	if ( ! in_array( $status, array( 'normal', 'pending', 'offline' ), true ) ) {
		$status = 'normal';
	}
	return array(
		'status'       => $status,
		'failures'     => absint( $state['failures'] ?? 0 ),
		'last_checked' => sanitize_text_field( $state['last_checked'] ?? '' ),
		'last_code'    => absint( $state['last_code'] ?? 0 ),
		'last_error'   => sanitize_text_field( $state['last_error'] ?? '' ),
	);
}

/** Return the optional administrator-defined order for a link category. */
function quietype_link_category_order( $term_id ) {
	$value = get_term_meta( $term_id, 'quietype_link_category_order', true );
	return '' === $value ? PHP_INT_MAX : absint( $value );
}

/** Return visible bookmarks grouped by every configured non-empty category. */
function quietype_link_groups() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'link_category',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}
	usort(
		$terms,
		function ( $left, $right ) {
			$order = quietype_link_category_order( $left->term_id ) <=> quietype_link_category_order( $right->term_id );
			return 0 !== $order ? $order : strnatcasecmp( $left->name, $right->name );
		}
	);
	$groups = array();
	foreach ( $terms as $term ) {
		$links = get_bookmarks(
			array(
				'category'       => (string) $term->term_id,
				'hide_invisible' => true,
				'orderby'        => 'rating',
				'order'          => 'DESC',
			)
		);
		if ( ! $links ) {
			continue;
		}
		usort(
			$links,
			function ( $left, $right ) {
				$rating = (int) $right->link_rating <=> (int) $left->link_rating;
				return 0 !== $rating ? $rating : strnatcasecmp( $left->link_name, $right->link_name );
			}
		);
		$groups[] = array( 'term' => $term, 'links' => $links );
	}
	return $groups;
}

/** Add an optional display order to WordPress's native link-category forms. */
function quietype_link_category_add_order_field() {
	wp_nonce_field( 'quietype_save_link_category_order', 'quietype_link_category_order_nonce' );
	?>
	<div class="form-field term-order-wrap">
		<label for="quietype-link-category-order">显示顺序</label>
		<input id="quietype-link-category-order" name="quietype_link_category_order" type="number" min="0" max="9999" step="1" value="">
		<p>数字越小越靠前；留空时按分类名称排列。</p>
	</div>
	<?php
}
add_action( 'link_category_add_form_fields', 'quietype_link_category_add_order_field' );

function quietype_link_category_edit_order_field( $term ) {
	$value = get_term_meta( $term->term_id, 'quietype_link_category_order', true );
	wp_nonce_field( 'quietype_save_link_category_order', 'quietype_link_category_order_nonce' );
	?>
	<tr class="form-field term-order-wrap">
		<th scope="row"><label for="quietype-link-category-order">显示顺序</label></th>
		<td><input id="quietype-link-category-order" name="quietype_link_category_order" type="number" min="0" max="9999" step="1" value="<?php echo esc_attr( $value ); ?>"><p class="description">数字越小越靠前；留空时按分类名称排列。</p></td>
	</tr>
	<?php
}
add_action( 'link_category_edit_form_fields', 'quietype_link_category_edit_order_field' );

/** Save or clear a link category's display order. */
function quietype_save_link_category_order( $term_id ) {
	$taxonomy = get_taxonomy( 'link_category' );
	if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->manage_terms ) || empty( $_POST['quietype_link_category_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_link_category_order_nonce'] ) ), 'quietype_save_link_category_order' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$value = isset( $_POST['quietype_link_category_order'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['quietype_link_category_order'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $value ) {
		delete_term_meta( $term_id, 'quietype_link_category_order' );
		return;
	}
	update_term_meta( $term_id, 'quietype_link_category_order', min( 9999, absint( $value ) ) );
}
add_action( 'created_link_category', 'quietype_save_link_category_order' );
add_action( 'edited_link_category', 'quietype_save_link_category_order' );

/** Show category order in the native taxonomy overview. */
function quietype_link_category_order_column( $columns ) {
	$columns['quietype_order'] = '显示顺序';
	return $columns;
}
add_filter( 'manage_edit-link_category_columns', 'quietype_link_category_order_column' );

function quietype_link_category_order_column_value( $content, $column_name, $term_id ) {
	if ( 'quietype_order' !== $column_name ) {
		return $content;
	}
	$value = get_term_meta( $term_id, 'quietype_link_category_order', true );
	return '' === $value ? '—' : esc_html( $value );
}
add_filter( 'manage_link_category_custom_column', 'quietype_link_category_order_column_value', 10, 3 );

/** Persist one bookmark state without autoloading the collection. */
function quietype_update_link_state( $link_id, $state ) {
	$link_id = absint( $link_id );
	if ( ! $link_id ) {
		return;
	}
	$states             = quietype_link_states();
	$states[ $link_id ] = array_merge( quietype_link_state( $link_id ), $state );
	update_option( 'quietype_link_states', $states, false );
}

/** Add a Quietype status box to WordPress's native link editor. */
function quietype_add_link_status_meta_box() {
	add_meta_box( 'quietype-link-status', 'Quietype 友链状态', 'quietype_link_status_meta_box', 'link', 'side', 'default' );
}
add_action( 'add_meta_boxes_link', 'quietype_add_link_status_meta_box' );

/** Render the bookmark status controls and latest advisory result. */
function quietype_link_status_meta_box( $link ) {
	$link_id = absint( $link->link_id ?? 0 );
	$state   = quietype_link_state( $link_id );
	wp_nonce_field( 'quietype_save_link_status', 'quietype_link_status_nonce' );
	$labels = array(
		'normal'  => '正常',
		'pending' => '待确认',
		'offline' => '失联',
	);
	?>
	<fieldset>
		<legend class="screen-reader-text">友链状态</legend>
		<?php foreach ( $labels as $value => $label ) : ?>
			<p><label><input type="radio" name="quietype_link_status" value="<?php echo esc_attr( $value ); ?>" <?php checked( $state['status'], $value ); ?>> <?php echo esc_html( $label ); ?></label></p>
		<?php endforeach; ?>
	</fieldset>
	<p class="description">自动检测只会设为“待确认”；前台仅展示手动确认的“失联”。</p>
	<?php if ( $state['last_checked'] ) : ?>
		<hr>
		<p><strong>最近检测</strong><br><?php echo esc_html( get_date_from_gmt( $state['last_checked'], 'Y-m-d H:i' ) ); ?></p>
		<p><strong>结果</strong><br><?php echo $state['last_code'] ? 'HTTP ' . esc_html( $state['last_code'] ) : esc_html( $state['last_error'] ?: '连接失败' ); ?></p>
		<?php if ( $state['failures'] ) : ?><p><strong>连续失败</strong><br><?php echo esc_html( $state['failures'] ); ?> 次</p><?php endif; ?>
	<?php endif; ?>
	<?php if ( $link_id ) : ?>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=quietype_check_link&link_id=' . $link_id ), 'quietype_check_link_' . $link_id ) ); ?>">立即检测</a></p>
	<?php endif; ?>
	<?php if ( isset( $_GET['quietype_checked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<p style="color:#008a20">检测已完成。</p>
	<?php endif; ?>
	<?php
}

/** Save a manually selected state after WordPress has saved the bookmark. */
function quietype_save_link_status( $link_id ) {
	if ( ! current_user_can( 'manage_links' ) || empty( $_POST['quietype_link_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quietype_link_status_nonce'] ) ), 'quietype_save_link_status' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$status = isset( $_POST['quietype_link_status'] ) ? sanitize_key( wp_unslash( $_POST['quietype_link_status'] ) ) : 'normal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! in_array( $status, array( 'normal', 'pending', 'offline' ), true ) ) {
		$status = 'normal';
	}
	$update = array( 'status' => $status );
	if ( 'normal' === $status ) {
		$update['failures'] = 0;
	}
	quietype_update_link_state( $link_id, $update );
}
add_action( 'add_link', 'quietype_save_link_status' );
add_action( 'edit_link', 'quietype_save_link_status' );

/** Remove state belonging to a deleted bookmark. */
function quietype_delete_link_status( $link_id ) {
	$states = quietype_link_states();
	if ( isset( $states[ $link_id ] ) ) {
		unset( $states[ $link_id ] );
		update_option( 'quietype_link_states', $states, false );
	}
}
add_action( 'deleted_link', 'quietype_delete_link_status' );

/** Make a small, SSRF-safe availability request for a bookmark. */
function quietype_check_link_url( $url ) {
	$url = wp_http_validate_url( $url );
	if ( ! $url ) {
		return array( 'success' => false, 'code' => 0, 'error' => '地址不是安全的公网 HTTP(S) URL' );
	}
	$args = array(
		'timeout'             => 5,
		'redirection'         => 3,
		'reject_unsafe_urls'  => true,
		'user-agent'          => 'Quietype Link Monitor/' . QUIETYPE_VERSION . '; ' . home_url( '/' ),
	);
	$response = wp_safe_remote_head( $url, $args );
	$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	if ( 405 === $code ) {
		$args['method']              = 'GET';
		$args['limit_response_size'] = 2048;
		$response                    = wp_safe_remote_request( $url, $args );
		$code                        = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}
	$success = ( $code >= 200 && $code < 400 ) || in_array( $code, array( 401, 403, 429 ), true );
	return array(
		'success' => $success,
		'code'    => $code,
		'error'   => is_wp_error( $response ) ? $response->get_error_message() : ( $success ? '' : 'HTTP ' . $code ),
	);
}

/** Check a bookmark and update advisory counters without declaring it offline. */
function quietype_check_link( $link_id ) {
	$bookmark = get_bookmark( $link_id );
	if ( ! $bookmark instanceof stdClass ) {
		return false;
	}
	$result = quietype_check_link_url( $bookmark->link_url );
	$state  = quietype_link_state( $link_id );
	$update = array(
		'last_checked' => current_time( 'mysql', true ),
		'last_code'    => $result['code'],
		'last_error'   => $result['error'],
	);
	if ( $result['success'] ) {
		$update['failures'] = 0;
		if ( 'pending' === $state['status'] ) {
			$update['status'] = 'normal';
		}
	} else {
		$update['failures'] = $state['failures'] + 1;
		if ( 'normal' === $state['status'] && $update['failures'] >= 3 ) {
			$update['status'] = 'pending';
		}
	}
	quietype_update_link_state( $link_id, $update );
	return $result;
}

/** Run a nonce-protected check from the native link editor. */
function quietype_handle_manual_link_check() {
	if ( ! current_user_can( 'manage_links' ) ) {
		wp_die( '无权检测友链。', 403 );
	}
	$link_id = isset( $_GET['link_id'] ) ? absint( $_GET['link_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	check_admin_referer( 'quietype_check_link_' . $link_id );
	quietype_check_link( $link_id );
	wp_safe_redirect( add_query_arg( array( 'action' => 'edit', 'link_id' => $link_id, 'quietype_checked' => 1 ), admin_url( 'link.php' ) ) );
	exit;
}
add_action( 'admin_post_quietype_check_link', 'quietype_handle_manual_link_check' );

/** Check five bookmarks per daily cron run to keep request time bounded. */
function quietype_run_link_checks() {
	if ( ! quietype_get_setting( 'quietype_link_check_enabled', false ) ) {
		return;
	}
	$bookmarks = get_bookmarks( array( 'hide_invisible' => true, 'orderby' => 'link_id', 'order' => 'ASC' ) );
	if ( ! $bookmarks ) {
		return;
	}
	$offset = absint( get_option( 'quietype_link_check_offset', 0 ) ) % count( $bookmarks );
	$count  = min( 5, count( $bookmarks ) );
	for ( $index = 0; $index < $count; $index++ ) {
		$bookmark = $bookmarks[ ( $offset + $index ) % count( $bookmarks ) ];
		quietype_check_link( $bookmark->link_id );
	}
	update_option( 'quietype_link_check_offset', ( $offset + $count ) % count( $bookmarks ), false );
}
add_action( 'quietype_daily_link_check', 'quietype_run_link_checks' );

/** Keep the advisory daily task in sync with the theme setting. */
function quietype_ensure_link_check_schedule() {
	$scheduled = wp_next_scheduled( 'quietype_daily_link_check' );
	if ( quietype_get_setting( 'quietype_link_check_enabled', false ) && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'quietype_daily_link_check' );
	} elseif ( ! quietype_get_setting( 'quietype_link_check_enabled', false ) && $scheduled ) {
		wp_clear_scheduled_hook( 'quietype_daily_link_check' );
	}
}
add_action( 'init', 'quietype_ensure_link_check_schedule' );

function quietype_clear_link_check_schedule() {
	wp_clear_scheduled_hook( 'quietype_daily_link_check' );
}
add_action( 'switch_theme', 'quietype_clear_link_check_schedule' );

/** Add the advisory state to the native Links overview table. */
function quietype_link_status_column( $columns ) {
	$columns['quietype_status'] = '状态';
	return $columns;
}
add_filter( 'manage_link-manager_columns', 'quietype_link_status_column' );

function quietype_link_status_column_value( $column_name, $link_id ) {
	if ( 'quietype_status' !== $column_name ) {
		return;
	}
	$labels = array( 'normal' => '正常', 'pending' => '待确认', 'offline' => '失联' );
	$state  = quietype_link_state( $link_id );
	echo esc_html( $labels[ $state['status'] ] );
}
add_action( 'manage_link_custom_column', 'quietype_link_status_column_value', 10, 2 );

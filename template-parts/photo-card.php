<?php
/**
 * One photo in the annual archive.
 *
 * @package Quietype
 */

$photo_data    = quietype_photo_data();
$photo_sources = quietype_photo_image_sources( $photo_data );
$photo_index   = absint( get_query_var( 'quietype_photo_index', 0 ) );
$year_count    = absint( get_query_var( 'quietype_photo_year_count', 0 ) );
$ratio         = $photo_data['width'] && $photo_data['height'] ? $photo_data['width'] / $photo_data['height'] : 1.5;
$layout        = 'photo-card--standard';
$desktop_width = 600;
if ( $ratio >= 1.45 ) {
	$layout        = 'photo-card--wide';
	$desktop_width = 800;
} elseif ( $ratio <= .88 ) {
	$layout        = 'photo-card--narrow';
	$desktop_width = 400;
} elseif ( $ratio <= 1.12 ) {
	$layout        = 'photo-card--square';
	$desktop_width = 400;
}
if ( 0 === $photo_index ) {
	$layout .= ' photo-card--lead';
}
$mobile_width = 0 === $photo_index && 1 === $year_count % 2 ? 100 : 50;
$image_sizes  = sprintf( '(max-width: 720px) %dvw, %dpx', $mobile_width, $desktop_width );
$captured_label = '';
if ( preg_match( '/^(\d{4})-(\d{2})$/', $photo_data['captured_date'], $captured_parts ) ) {
	$captured_label = sprintf( '%d年%d月', (int) $captured_parts[1], (int) $captured_parts[2] );
}
$caption_meta = array_filter( array( $photo_data['location'], $captured_label ) );
$caption      = trim( wp_strip_all_tags( get_the_excerpt() ) );
$exif_text    = quietype_photo_exif_text( $photo_data );
$device_text  = implode( ' · ', array_filter( array( $photo_data['camera'], $photo_data['lens'] ) ) );
$alt          = get_the_title();
if ( $photo_data['location'] ) {
	$alt .= '，拍摄于' . $photo_data['location'];
}
?>
<figure class="photo-card <?php echo esc_attr( $layout ); ?>" id="photo-<?php the_ID(); ?>">
	<a class="photo-frame" href="<?php echo esc_url( $photo_sources['lightbox_url'] ); ?>" data-pswp-src="<?php echo esc_url( $photo_sources['lightbox_url'] ); ?>" data-pswp-width="<?php echo esc_attr( $photo_sources['lightbox_width'] ); ?>" data-pswp-height="<?php echo esc_attr( $photo_sources['lightbox_height'] ); ?>" data-photo-original="<?php echo esc_url( $photo_sources['original_url'] ); ?>" data-photo-title="<?php echo esc_attr( get_the_title() ); ?>" data-photo-meta="<?php echo esc_attr( implode( ' · ', $caption_meta ) ); ?>" data-photo-exif="<?php echo esc_attr( $exif_text ); ?>" data-photo-device="<?php echo esc_attr( $device_text ); ?>" data-photo-caption="<?php echo esc_attr( $caption ); ?>">
		<?php
		if ( $photo_data['attachment_id'] && ! $photo_data['is_external'] ) {
			echo wp_get_attachment_image(
				$photo_data['attachment_id'],
				'quietype-photo-grid',
				false,
				array(
					'alt'      => $alt,
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => $image_sizes,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core generates escaped image markup.
		} else {
			?>
			<img src="<?php echo esc_url( $photo_sources['grid_url'] ); ?>" alt="<?php echo esc_attr( $alt ); ?>" width="<?php echo esc_attr( $photo_data['width'] ?: 1600 ); ?>" height="<?php echo esc_attr( $photo_data['height'] ?: 1067 ); ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
			<?php
		}
		?>
	</a>
	<figcaption class="photo-caption">
		<strong><?php the_title(); ?></strong>
		<?php if ( $caption_meta ) : ?><small><?php echo esc_html( implode( ' · ', $caption_meta ) ); ?></small><?php endif; ?>
	</figcaption>
</figure>

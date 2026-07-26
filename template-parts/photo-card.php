<?php
/**
 * One photo in the annual archive.
 *
 * @package Quietype
 */

$photo_data  = quietype_photo_data();
$photo_index = absint( get_query_var( 'quietype_photo_index', 0 ) );
$ratio       = $photo_data['width'] && $photo_data['height'] ? $photo_data['width'] / $photo_data['height'] : 1.5;
$layout      = 'photo-card--standard';
if ( $ratio >= 1.45 ) {
	$layout = 'photo-card--wide';
} elseif ( $ratio <= .88 ) {
	$layout = 'photo-card--narrow';
} elseif ( $ratio <= 1.12 ) {
	$layout = 'photo-card--square';
}
if ( 0 === $photo_index ) {
	$layout .= ' photo-card--lead';
}
$month       = substr( $photo_data['captured_date'], 5, 2 );
$caption_meta = array_filter( array( $photo_data['location'], $month ? $month . ' 月' : '' ) );
$caption      = trim( wp_strip_all_tags( get_the_excerpt() ) );
$exif_text    = quietype_photo_exif_text( $photo_data );
$device_text  = implode( ' · ', array_filter( array( $photo_data['camera'], $photo_data['lens'] ) ) );
$alt          = get_the_title();
if ( $photo_data['location'] ) {
	$alt .= '，拍摄于' . $photo_data['location'];
}
?>
<figure class="photo-card <?php echo esc_attr( $layout ); ?>" id="photo-<?php the_ID(); ?>">
	<a class="photo-frame" href="<?php echo esc_url( $photo_data['image_url'] ); ?>" data-pswp-src="<?php echo esc_url( $photo_data['image_url'] ); ?>" data-pswp-width="<?php echo esc_attr( $photo_data['width'] ?: 1600 ); ?>" data-pswp-height="<?php echo esc_attr( $photo_data['height'] ?: 1067 ); ?>" data-photo-title="<?php echo esc_attr( get_the_title() ); ?>" data-photo-meta="<?php echo esc_attr( implode( ' · ', $caption_meta ) ); ?>" data-photo-exif="<?php echo esc_attr( $exif_text ); ?>" data-photo-device="<?php echo esc_attr( $device_text ); ?>" data-photo-caption="<?php echo esc_attr( $caption ); ?>">
		<img src="<?php echo esc_url( $photo_data['image_url'] ); ?>" alt="<?php echo esc_attr( $alt ); ?>" width="<?php echo esc_attr( $photo_data['width'] ?: 1600 ); ?>" height="<?php echo esc_attr( $photo_data['height'] ?: 1067 ); ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
	</a>
	<figcaption class="photo-caption">
		<strong><?php the_title(); ?></strong>
		<?php if ( $caption_meta ) : ?><small><?php echo esc_html( implode( ' · ', $caption_meta ) ); ?></small><?php endif; ?>
	</figcaption>
</figure>

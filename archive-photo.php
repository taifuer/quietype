<?php
/**
 * Annual lightweight photo archive.
 *
 * @package Quietype
 */

get_header();
$photos_by_year = array();
while ( have_posts() ) {
	the_post();
	$data = quietype_photo_data();
	if ( ! $data['image_url'] ) {
		continue;
	}
	$year = substr( $data['captured_date'], 0, 4 );
	if ( ! preg_match( '/^[0-9]{4}$/', $year ) ) {
		$year = get_the_date( 'Y' );
	}
	$photos_by_year[ $year ][] = get_post();
}
krsort( $photos_by_year, SORT_NUMERIC );
$years       = array_keys( $photos_by_year );
$photo_count = array_sum( array_map( 'count', $photos_by_year ) );
$year_count  = count( $years );
$year_index_classes = array( 'photo-year-index', 'photo-year-index--count-' . $year_count );
if ( $year_count > 4 && 0 !== $year_count % 3 ) {
	$year_index_classes[] = 'photo-year-index--remainder-' . ( $year_count % 3 );
}
?>
<section class="photos-page section-wrap">
	<header class="photos-hero">
		<div>
			<p class="eyebrow">PHOTOS</p>
			<h1>照片</h1>
		</div>
		<?php if ( $photo_count ) : ?>
			<div class="photos-hero__meta">
				<?php if ( count( $years ) > 1 ) : ?>
					<p>从 <?php echo esc_html( min( $years ) ); ?> 到 <?php echo esc_html( max( $years ) ); ?>，留下 <?php echo esc_html( number_format_i18n( $photo_count ) ); ?> 张照片。</p>
				<?php else : ?>
					<p><?php echo esc_html( $years[0] ); ?> 年，留下 <?php echo esc_html( number_format_i18n( $photo_count ) ); ?> 张照片。</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $photos_by_year ) : ?>
		<nav class="<?php echo esc_attr( implode( ' ', $year_index_classes ) ); ?>" aria-label="按年份查看">
			<?php foreach ( $photos_by_year as $year => $photos ) : ?>
				<a href="#photo-year-<?php echo esc_attr( $year ); ?>"><strong><?php echo esc_html( $year ); ?></strong><span><?php echo esc_html( count( $photos ) ); ?> 张</span></a>
			<?php endforeach; ?>
		</nav>

		<?php foreach ( $photos_by_year as $year => $photos ) : ?>
			<section class="photo-year" id="photo-year-<?php echo esc_attr( $year ); ?>" aria-labelledby="photo-year-title-<?php echo esc_attr( $year ); ?>">
				<header class="photo-year__heading">
					<h2 id="photo-year-title-<?php echo esc_attr( $year ); ?>"><?php echo esc_html( $year ); ?></h2>
					<p><?php echo esc_html( count( $photos ) ); ?> 张</p>
				</header>
				<div class="photo-grid photo-grid--<?php echo 0 === count( $photos ) % 2 ? 'even' : 'odd'; ?>">
					<?php foreach ( $photos as $photo_index => $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
						<?php setup_postdata( $post ); ?>
						<?php set_query_var( 'quietype_photo_index', $photo_index ); ?>
						<?php get_template_part( 'template-parts/photo', 'card' ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p class="empty-state">这里暂时还没有照片。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>

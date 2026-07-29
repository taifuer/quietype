<?php
/**
 * Annual reading archive.
 *
 * @package Quietype
 */

get_header();
$books_by_year = array();
while ( have_posts() ) {
	the_post();
	$data = quietype_book_data();
	$year = substr( $data['read_date'], 0, 4 );
	if ( ! preg_match( '/^[0-9]{4}$/', $year ) ) {
		$year = get_the_date( 'Y' );
	}
	$books_by_year[ $year ][] = get_post();
}
krsort( $books_by_year, SORT_NUMERIC );
$years          = array_keys( $books_by_year );
$year_count     = count( $years );
$book_total     = array_sum( array_map( 'count', $books_by_year ) );
$year_span      = $year_count ? ( 1 === $year_count ? (string) $years[0] : $years[ $year_count - 1 ] . '—' . $years[0] ) : '';
$expanded_limit = quietype_archive_expanded_years( 'book' );
$expanded_years = 0 === $expanded_limit ? $years : array_slice( $years, 0, $expanded_limit );
$page_title = quietype_archive_page_text( 'book', 'title' );
$eyebrow    = quietype_archive_page_text( 'book', 'eyebrow' );
$intro      = quietype_archive_page_text( 'book', 'intro' );
$year_index_classes = array( 'book-year-index', 'book-year-index--count-' . $year_count );
if ( $year_count > 4 && 0 !== $year_count % 3 ) {
	$year_index_classes[] = 'book-year-index--remainder-' . ( $year_count % 3 );
}
?>
<section class="books-page section-wrap">
	<header class="books-hero">
		<div>
			<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<h1><?php echo esc_html( $page_title ); ?></h1>
		</div>
		<?php if ( $intro || $book_total ) : ?>
			<div class="books-hero__meta">
				<?php if ( $intro ) : ?><p class="collection-intro"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
				<?php if ( $book_total ) : ?><p class="collection-stats"><?php echo esc_html( $year_span . ' · ' . $book_total . ' 本' ); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( $books_by_year ) : ?>
		<nav class="<?php echo esc_attr( implode( ' ', $year_index_classes ) ); ?>" aria-label="按年份查看">
			<?php foreach ( $books_by_year as $year => $books ) : ?>
				<?php $is_default_year = in_array( $year, $expanded_years, true ); ?>
				<a href="#year-<?php echo esc_attr( $year ); ?>" aria-controls="book-grid-<?php echo esc_attr( $year ); ?>" aria-expanded="<?php echo $is_default_year ? 'true' : 'false'; ?>"><strong><?php echo esc_html( $year ); ?></strong><span><?php echo esc_html( count( $books ) ); ?> 本</span></a>
			<?php endforeach; ?>
		</nav>

		<?php foreach ( $books_by_year as $year => $books ) : ?>
			<?php $is_default_year = in_array( $year, $expanded_years, true ); ?>
			<section class="book-year-shelf" id="year-<?php echo esc_attr( $year ); ?>" aria-labelledby="book-year-title-<?php echo esc_attr( $year ); ?>" data-expanded="<?php echo $is_default_year ? 'true' : 'false'; ?>">
				<header class="book-year-heading">
					<h2 id="book-year-title-<?php echo esc_attr( $year ); ?>">
						<button class="book-year-toggle" type="button" aria-expanded="<?php echo $is_default_year ? 'true' : 'false'; ?>" aria-controls="book-grid-<?php echo esc_attr( $year ); ?>" aria-label="<?php echo esc_attr( ( $is_default_year ? '收起 ' : '展开 ' ) . $year . ' 年书籍' ); ?>">
							<span><?php echo esc_html( $year ); ?></span><i aria-hidden="true"></i>
						</button>
					</h2>
					<p><?php echo esc_html( count( $books ) ); ?> 本</p>
				</header>
				<div class="book-grid" id="book-grid-<?php echo esc_attr( $year ); ?>">
					<?php foreach ( $books as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
						<?php setup_postdata( $post ); ?>
						<?php get_template_part( 'template-parts/book', 'card' ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p class="empty-state">这里暂时还没有阅读记录。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>

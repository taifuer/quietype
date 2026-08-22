<?php
/**
 * Template Name: 文章归档
 *
 * @package Quietype
 */
get_header();
the_post();
$years         = $wpdb->get_col( "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY post_date DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$archive_stats = quietype_archive_stats();
?>
<section class="archive-page section-wrap">
	<header class="page-hero">
		<h1><?php the_title(); ?></h1>
		<p><?php echo esc_html( number_format_i18n( $archive_stats['posts'] ) ); ?> 篇文章，<?php echo esc_html( number_format_i18n( $archive_stats['views'] ) ); ?> 次浏览，<?php echo esc_html( number_format_i18n( $archive_stats['comments'] ) ); ?> 条评论</p>
	</header>
	<?php $archive_tags = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true ) ); ?>
	<?php if ( $archive_tags ) : ?>
		<nav class="archive-tags" aria-label="按标签浏览文章">
			<div class="archive-tags__heading"><h2>文章标签</h2><span><?php echo esc_html( count( $archive_tags ) ); ?> 个标签</span></div>
			<div class="archive-tags__list">
				<?php foreach ( $archive_tags as $archive_tag ) : ?>
					<a href="<?php echo esc_url( get_tag_link( $archive_tag ) ); ?>" title="<?php echo esc_attr( '#' . $archive_tag->name . '，' . $archive_tag->count . ' 篇文章' ); ?>"><span><i aria-hidden="true">#</i><?php echo esc_html( $archive_tag->name ); ?></span><small><?php echo esc_html( $archive_tag->count ); ?></small></a>
				<?php endforeach; ?>
			</div>
		</nav>
	<?php endif; ?>
	<?php foreach ( $years as $year ) : ?>
		<?php
		$year_posts = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'year'           => (int) $year,
				'no_found_rows'  => true,
			)
		);
		?>
		<section class="archive-year">
			<h2><?php echo esc_html( $year ); ?><sup><?php echo esc_html( $year_posts->post_count ); ?></sup></h2>
			<ol>
				<?php while ( $year_posts->have_posts() ) : $year_posts->the_post(); ?>
					<li><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'm-d' ) ); ?></time><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><span class="archive-details"><span class="archive-terms"><?php echo wp_kses_post( quietype_post_terms( get_the_ID(), 3 ) ); ?></span><span class="archive-views"><?php echo esc_html( quietype_post_views() ); ?> 次浏览</span></span></li>
				<?php endwhile; ?>
			</ol>
		</section>
		<?php wp_reset_postdata(); ?>
	<?php endforeach; ?>
</section>
<?php get_footer(); ?>

<?php
/**
 * Template Name: 文章归档
 *
 * @package Quietype
 */
get_header();
the_post();
$years = $wpdb->get_col( "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY post_date DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
?>
<section class="archive-page section-wrap">
	<header class="page-hero">
		<p class="eyebrow">ARCHIVE</p>
		<h1><?php the_title(); ?></h1>
		<p><?php echo esc_html( wp_count_posts()->publish ); ?> 篇文章，按时间安静地排好。</p>
	</header>
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
					<li><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'm-d' ) ); ?></time><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><span class="archive-terms"><?php echo wp_kses_post( quietype_post_terms( get_the_ID(), 3 ) ); ?></span></li>
				<?php endwhile; ?>
			</ol>
		</section>
		<?php wp_reset_postdata(); ?>
	<?php endforeach; ?>
</section>
<?php get_footer(); ?>

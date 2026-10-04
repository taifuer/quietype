<?php
/**
 * A compact project collection, not a directory of empty detail pages.
 *
 * @package Quietype
 */

get_header();
global $wp_query;
$intro = quietype_archive_page_text( 'project', 'intro' );
?>
<section class="projects-page section-wrap">
	<header class="page-hero projects-hero">
		<div>
			<h1><?php echo esc_html( quietype_archive_page_text( 'project', 'title' ) ); ?></h1>
			<?php if ( $intro ) : ?><p><?php echo esc_html( $intro ); ?></p><?php endif; ?>
		</div>
		<?php if ( have_posts() ) : ?><span class="project-count"><?php echo esc_html( number_format_i18n( $wp_query->post_count ) ); ?> 个项目</span><?php endif; ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="project-grid">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); get_template_part( 'template-parts/project', 'card' ); ?>
			<?php endwhile; ?>
		</div>
	<?php else : ?>
		<p class="project-empty">这里将记录一些正在做的项目。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>

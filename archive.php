<?php get_header(); ?>
<section class="archive-index section-wrap">
	<header class="page-hero">
		<p class="eyebrow">COLLECTION</p>
		<h1><?php the_archive_title(); ?></h1>
		<?php the_archive_description( '<div class="page-description">', '</div>' ); ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'template-parts/post', 'row' ); ?>
			<?php endwhile; ?>
		</div>
		<?php quietype_pagination(); ?>
	<?php else : ?>
		<p class="empty-state">这个分类中暂时没有文章。</p>
	<?php endif; ?>
</section>
<?php get_footer(); ?>

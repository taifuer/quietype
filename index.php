<?php get_header(); ?>

<section class="post-index section-wrap<?php echo is_home() ? ' post-index--home' : ''; ?>">
	<header class="section-title">
		<h1><?php echo is_home() ? '近期文章' : esc_html( get_the_archive_title() ); ?></h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'template-parts/post', 'row' ); ?>
			<?php endwhile; ?>
		</div>
		<?php quietype_pagination(); ?>
	<?php else : ?>
		<p class="empty-state">这里暂时没有文章。</p>
	<?php endif; ?>
</section>

<?php get_footer(); ?>

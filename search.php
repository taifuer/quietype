<?php get_header(); ?>
<section class="archive-index section-wrap">
	<header class="page-hero">
		<h1>“<?php echo esc_html( get_search_query() ); ?>”的搜索结果</h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php while ( have_posts() ) : the_post(); ?><?php get_template_part( 'template-parts/post', 'row' ); ?><?php endwhile; ?>
		</div>
		<?php quietype_pagination(); ?>
	<?php else : ?>
		<div class="empty-state"><p>没有找到匹配内容，换一个更短的关键词试试。</p><?php get_search_form(); ?></div>
	<?php endif; ?>
</section>
<?php get_footer(); ?>

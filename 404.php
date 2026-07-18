<?php get_header(); ?>
<section class="not-found section-wrap">
	<p class="eyebrow">404 · NOT FOUND</p>
	<h1>这一页没有留下来。</h1>
	<p>链接可能已经改变，也可能只是输入有误。可以搜索文章，或者从归档重新出发。</p>
	<?php get_search_form(); ?>
	<p class="not-found__links"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">返回首页</a><a href="<?php echo esc_url( quietype_archive_url() ); ?>">查看归档</a></p>
</section>
<?php get_footer(); ?>

<?php get_header(); ?>
<section class="not-found section-wrap">
	<p class="eyebrow">404 · NOT FOUND</p>
	<h1>没有找到这一页。</h1>
	<p>链接可能已更改或输入有误，可以搜索文章，或前往归档继续浏览。</p>
	<?php get_search_form(); ?>
	<p class="not-found__links"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">返回首页</a><a href="<?php echo esc_url( quietype_archive_url() ); ?>">查看归档</a></p>
</section>
<?php get_footer(); ?>

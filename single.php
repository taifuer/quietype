<?php
get_header();
the_post();
$stats    = quietype_reading_stats();
$rendered = apply_filters( 'the_content', get_the_content() );
$article  = quietype_prepare_article( $rendered );
?>
<article <?php post_class( count( $article['items'] ) >= 2 ? array( 'article', 'article--with-toc' ) : 'article' ); ?>>
	<header class="article-header content-width">
		<h1><?php the_title(); ?></h1>
		<div class="article-terms"><?php echo wp_kses_post( quietype_post_terms( get_the_ID(), 8 ) ); ?></div>
		<div class="article-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">发表于 <?php echo esc_html( get_the_date( 'Y 年 n 月 j 日' ) ); ?></time>
			<?php if ( get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' ) ) : ?><time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>">更新于 <?php echo esc_html( get_the_modified_date( 'Y 年 n 月 j 日' ) ); ?></time><?php endif; ?>
			<span><?php echo esc_html( number_format_i18n( $stats['characters'] ) ); ?> 字</span>
			<span>约 <?php echo esc_html( $stats['minutes'] ); ?> 分钟</span>
			<span><?php echo esc_html( quietype_post_views() ); ?> 次浏览</span>
		</div>
	</header>

	<?php if ( count( $article['items'] ) >= 2 ) : ?>
		<details class="mobile-toc content-width">
			<summary>文章目录 <span><?php echo esc_html( count( $article['items'] ) ); ?> 节</span></summary>
			<nav aria-label="文章目录">
				<?php foreach ( $article['items'] as $item ) : ?><a class="toc-level-<?php echo esc_attr( $item['level'] ); ?>" href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a><?php endforeach; ?>
			</nav>
		</details>
		<aside class="article-toc" aria-label="文章目录">
			<strong>本文目录</strong>
			<nav>
				<?php foreach ( $article['items'] as $item ) : ?><a class="toc-level-<?php echo esc_attr( $item['level'] ); ?>" href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a><?php endforeach; ?>
			</nav>
		</aside>
	<?php endif; ?>

	<div class="article-content content-width">
		<?php echo $article['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already filtered by the_content. ?>
		<?php wp_link_pages(); ?>
	</div>

	<footer class="article-end content-width">
		<nav class="post-navigation" aria-label="相邻文章">
			<div><span>上一篇</span><?php previous_post_link( '%link', '%title' ); ?></div>
			<div><span>下一篇</span><?php next_post_link( '%link', '%title' ); ?></div>
		</nav>
	</footer>
	<?php if ( comments_open() || get_comments_number() ) : ?><div class="content-width"><?php comments_template(); ?></div><?php endif; ?>
</article>
<?php get_footer(); ?>

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
			<div class="article-meta__group article-meta__dates">
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">发表于 <?php echo esc_html( get_the_date( 'Y 年 n 月 j 日' ) ); ?></time>
				<?php if ( get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' ) ) : ?><time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>">更新于 <?php echo esc_html( get_the_modified_date( 'Y 年 n 月 j 日' ) ); ?></time><?php endif; ?>
			</div>
			<div class="article-meta__group article-meta__stats">
				<span><?php echo esc_html( number_format_i18n( $stats['characters'] ) ); ?> 字</span>
				<span>约 <?php echo esc_html( $stats['minutes'] ); ?> 分钟</span>
				<span><span data-quietype-view-count><?php echo esc_html( quietype_post_views() ); ?></span> 次浏览</span>
			</div>
		</div>
	</header>

	<?php if ( count( $article['items'] ) >= 2 ) : ?>
		<details class="mobile-toc content-width">
			<summary>文章目录</summary>
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

	<?php if ( quietype_get_setting( 'quietype_article_copyright_enabled', false ) ) : ?>
		<?php $copyright_author = quietype_get_setting( 'quietype_article_author_name', '' ) ?: get_the_author_meta( 'display_name' ); ?>
		<?php
		$copyright_author_url = quietype_get_setting( 'quietype_article_author_url', '' );
		if ( ! $copyright_author_url && ! quietype_get_setting( 'quietype_disable_author_archives', true ) ) {
			$copyright_author_url = get_author_posts_url( get_the_author_meta( 'ID' ) );
		}
		?>
		<aside class="article-license content-width" aria-label="文章版权声明">
			<dl>
				<div><dt>文章标题</dt><dd><?php the_title(); ?></dd></div>
				<div><dt>文章链接</dt><dd><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_permalink() ); ?></a></dd></div>
				<div><dt>文章作者</dt><dd><?php if ( $copyright_author_url ) : ?><a href="<?php echo esc_url( $copyright_author_url ); ?>"><?php echo esc_html( $copyright_author ); ?></a><?php else : ?><?php echo esc_html( $copyright_author ); ?><?php endif; ?></dd></div>
				<div><dt>版权声明</dt><dd><?php echo wp_kses_post( quietype_article_license_html() ); ?></dd></div>
			</dl>
		</aside>
	<?php endif; ?>

	<footer class="article-end content-width">
		<nav class="post-navigation" aria-label="相邻文章">
			<div><span>上一篇</span><?php previous_post_link( '%link', '%title' ); ?></div>
			<div><span>下一篇</span><?php next_post_link( '%link', '%title' ); ?></div>
		</nav>
	</footer>
	<?php if ( comments_open() || get_comments_number() ) : ?><div class="content-width"><?php comments_template(); ?></div><?php endif; ?>
</article>
<?php get_footer(); ?>
